<?php

namespace App\Modules\Assistant\Brains;

use App\Models\Commune;
use App\Models\Region;
use App\Modules\Assistant\Contracts\AssistantBrain;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\AssistantReply;
use App\Modules\Assistant\Tools\ToolRegistry;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use Illuminate\Support\Facades\Cache;

/**
 * Cerveau DÉTERMINISTE de l'assistant (phase F10.0).
 *
 * Il ne « comprend » pas au sens d'un modèle de langage : il reconnaît des
 * intentions par mots-clés, puis actionne le bon outil. Ce qu'il perd en
 * souplesse de dialogue, il le gagne ailleurs — et ces gains ne sont pas
 * secondaires :
 *
 *   - **aucune clé API, aucun coût, aucun appel réseau** ;
 *   - **aucune hallucination possible** : tout ce qu'il affiche vient d'un
 *     outil, donc de la base ;
 *   - **reproductible** : le même message donne toujours la même réponse, donc
 *     il est testable — ce qui vaut aussi pour les garde-fous qu'il partage
 *     avec le futur driver Claude.
 *
 * Il reste le driver par défaut ET le repli du driver Claude (F10.4) : si les
 * clés sont absentes ou le fournisseur indisponible, on retombe ici plutôt que
 * d'afficher une erreur à un client.
 *
 * ⚠️ Ce cerveau ne consulte jamais la base lui-même : il passe par le registre
 * d'outils, donc par les scopes publics et les policies. La seule requête qu'il
 * émet en propre sert à reconnaître un nom de lieu (liste publique, mise en
 * cache) — pas à lire une donnée métier.
 */
class RuleBasedBrain implements AssistantBrain
{
    /**
     * Mots-clés d'un univers du catalogue. L'ordre compte : le premier univers
     * dont un mot apparaît l'emporte.
     */
    private const UNIVERSE_KEYWORDS = [
        'nuitees' => ['nuit', 'nuitee', 'nuitées', 'nuitee', 'hebergement', 'sejour', 'chambre', 'meuble', 'dormir', 'weekend', 'week-end', 'gite'],
        'transport' => ['voiture', 'berline', '4x4', 'vehicule', 'navette', 'aibd', 'bus', 'minibus', 'pirogue', 'transport', 'chauffeur', 'deplacer', 'trajet', 'aeroport'],
        // ⚠️ La comparaison se fait par MOTS ENTIERS (voir matchesAny) : « visite »
        // ne reconnaît donc pas « visiter », et « decouverte » pas « decouvrir ».
        // Chaque forme conjuguée courante doit figurer ici — « je veux visiter
        // Gorée » partait au support faute de ce seul mot.
        'tourisme' => ['circuit', 'excursion', 'visite', 'visiter', 'tourisme', 'touristique', 'decouverte', 'decouvrir', 'safari', 'colonie', 'vacances'],
        // ⚠️ Pas de « bien » ici : c'est aussi un adverbe très courant
        // (« je voudrais bien savoir comment payer »), et il détournait vers
        // l'immobilier des questions qui relevaient de la FAQ.
        'immobilier' => ['terrain', 'maison', 'appartement', 'villa', 'immeuble', 'bureau', 'acheter', 'achat', 'vendre', 'vente', 'louer', 'location', 'logement', 'parcelle'],
    ];

    /**
     * Mots signalant une question sur le SERVICE (et non sur une annonce).
     */
    private const FAQ_KEYWORDS = [
        'paiement', 'payer', 'paie', 'wave', 'orange money', 'free money', 'carte', 'frais',
        'commission', 'garantie', 'verification', 'verifie', 'securite', 'remboursement',
        'compte', 'inscription', 'fonctionne', 'caution', 'facture', 'annulation', 'annuler',
    ];

    /**
     * Mots signalant une demande d'aide humaine.
     */
    private const SUPPORT_KEYWORDS = [
        'conseiller', 'humain', 'quelqu\'un', 'agent', 'support', 'assistance', 'reclamation',
        'plainte', 'probleme', 'litige', 'urgent', 'telephone', 'appeler', 'joindre',
    ];

    /**
     * Salutations reconnues, français et wolof.
     */
    private const GREETINGS = ['bonjour', 'bonsoir', 'salut', 'coucou', 'hello', 'salam', 'asalam', 'dalal', 'nanga def', 'jamm'];

    public function reply(
        string $message,
        array $history,
        AssistantContext $context,
        ToolRegistry $tools,
    ): AssistantReply {
        $normalized = $this->normalize($message);

        // 1. Demande explicite d'un humain — prioritaire sur tout le reste.
        //    Quand quelqu'un demande un conseiller, lui répondre par une liste
        //    d'annonces est la meilleure façon de le perdre.
        if ($this->matchesAny($normalized, self::SUPPORT_KEYWORDS)) {
            return $this->useTool($tools, $context, 'contacter_support', ['sujet' => $message]);
        }

        // 2. Recherche dans le catalogue.
        if ($universe = $this->detectUniverse($normalized)) {
            return $this->useTool($tools, $context, 'rechercher_catalogue', [
                'univers' => $universe,
                'ville' => $this->detectPlace($normalized),
                'budget_max' => $this->detectBudget($normalized),
            ]);
        }

        // 3. Question sur le fonctionnement de la plateforme.
        if ($this->matchesAny($normalized, self::FAQ_KEYWORDS)) {
            return $this->useTool($tools, $context, 'consulter_faq', ['question' => $message]);
        }

        // 4. Simple salutation : on accueille et on oriente, sans outil.
        if ($this->matchesAny($normalized, self::GREETINGS)) {
            return AssistantReply::fallback(
                $this->greeting($context),
                $this->orientationActions(),
            );
        }

        // 5. Rien de reconnu. On le DIT — inventer une réponse serait pire.
        return $this->useTool($tools, $context, 'contacter_support', ['sujet' => $message]);
    }

    /**
     * Actionne un outil, ou dégrade proprement s'il n'est pas ouvert à
     * l'appelant (cas normal : le registre filtre par rôle).
     */
    private function useTool(ToolRegistry $tools, AssistantContext $context, string $name, array $input): AssistantReply
    {
        $tool = $tools->find($name, $context);

        if ($tool === null) {
            return AssistantReply::fallback(
                "Je ne peux pas traiter cette demande ici. L'équipe Kaikun prendra le relais.",
                [AssistantAction::contact('Nous écrire')],
            );
        }

        return AssistantReply::fromTool($tool->name(), $tool->run($input, $context));
    }

    /**
     * Message d'accueil. On garde la salutation wolof du prototype — c'est
     * l'identité de la marque — mais la conversation se poursuit en français,
     * seule langue que ce cerveau (et le modèle en F10.4) traite correctement.
     */
    private function greeting(AssistantContext $context): string
    {
        $name = $context->user?->name;
        $hello = $name !== null ? "Dalal ak diam {$name} 👋" : 'Dalal ak diam 👋';

        return $hello.' Je peux vous orienter vers un bien immobilier, un hébergement, '
            .'un circuit touristique ou un véhicule — ou répondre à vos questions sur Kaikun 360.';
    }

    /**
     * Suggestions affichées après un accueil, pour amorcer la conversation.
     *
     * @return array<int, AssistantAction>
     */
    private function orientationActions(): array
    {
        return [
            AssistantAction::link('Immobilier', '/immobilier'),
            AssistantAction::link('Nuitées', '/nuitees'),
            AssistantAction::link('Tourisme', '/tourisme'),
            AssistantAction::link('Transport', '/transport'),
        ];
    }

    /**
     * Univers du catalogue visé par le message.
     */
    private function detectUniverse(string $normalized): ?string
    {
        foreach (self::UNIVERSE_KEYWORDS as $universe => $keywords) {
            if ($this->matchesAny($normalized, $keywords)) {
                return $universe;
            }
        }

        return null;
    }

    /**
     * Lieu mentionné dans le message.
     *
     * On confronte le message à la liste RÉELLE des communes et régions du
     * pays (données de référence déjà en base), plutôt qu'à une liste de villes
     * écrite en dur qui vieillirait mal et raterait les petites communes.
     *
     * ⚠️ **Les lieux TOURISTIQUES en font partie, et ce n'est pas un détail**
     * (correctif F10.1). Ni « Saly » (une `tourist_zone` d'un bien) ni
     * « Casamance », « Gorée » ou « Lompoul » (les `destination` des circuits)
     * ne sont des communes ou des régions. `SearchCatalogTool` sait pourtant
     * chercher sur ces deux colonnes — mais il ne recevait jamais le mot, faute
     * de le reconnaître ici : mesuré sur la base réelle, « un circuit en
     * Casamance » renvoyait les trois derniers circuits publiés, n'importe où.
     * Le vocabulaire de compréhension doit couvrir tout ce que la recherche sait
     * exploiter, sinon la moitié de l'outil reste hors d'atteinte.
     *
     * La liste est mise en cache une heure : elle ne change qu'au rythme du
     * découpage administratif (et des zones saisies par les déposants), et on ne
     * veut pas de trois requêtes par message.
     */
    private function detectPlace(string $normalized): ?string
    {
        $places = Cache::remember('assistant:places', 3600, function () {
            return Region::query()->pluck('name')
                ->merge(Commune::query()->pluck('name'))
                // Zones touristiques des biens et destinations des circuits.
                // ⚠️ Bornées aux annonces PUBLIÉES, comme la recherche : le
                // vocabulaire de l'assistant ne doit pas trahir l'existence
                // d'une annonce en attente de validation.
                ->merge(
                    Property::query()
                        ->published()
                        ->whereNotNull('tourist_zone')
                        ->distinct()
                        ->pluck('tourist_zone'),
                )
                ->merge(
                    TourismExperience::query()
                        ->published()
                        ->whereNotNull('destination')
                        ->distinct()
                        ->pluck('destination'),
                )
                ->filter(fn ($name) => is_string($name) && mb_strlen($name) >= 3)
                ->unique()
                // Les noms longs d'abord : « Dakar Plateau » doit gagner sur « Dakar ».
                ->sortByDesc(fn (string $name) => mb_strlen($name))
                ->values()
                ->all();
        });

        foreach ($places as $place) {
            if (str_contains($normalized, $this->normalize($place))) {
                return $place;
            }
        }

        return null;
    }

    /**
     * Budget maximum exprimé dans le message.
     *
     * Reconnaît « 50 millions », « 45000 », « 200 000 f », « 3 m ».
     *
     * ⚠️ Le piège ici est le FAUX POSITIF, pas le faux négatif. Un message
     * contient souvent des nombres qui ne sont pas des prix : « hébergement pour
     * 10 personnes », « du 12 au 15 août », « terrain de 300 m² », un numéro de
     * téléphone. Pris pour un budget, chacun d'eux produit un filtre absurde
     * (`price <= 10`) et donc zéro résultat — l'utilisateur conclut que le
     * catalogue est vide, ce qui est bien pire que de ne pas filtrer du tout.
     *
     * D'où deux garde-fous : un plancher à 1 000 F CFA (aucun bien, nuitée ou
     * trajet ne coûte moins), et l'exclusion des numéros de téléphone sénégalais
     * (9 chiffres commençant par 7).
     */
    private function detectBudget(string $normalized): ?int
    {
        // « 50 millions », « 3 million », « 2 m » (le « m » collé est exclu par
        // \b pour ne pas confondre avec « 300 m² »).
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(millions?|m\b)/u', $normalized, $matches) === 1) {
            $value = (float) str_replace(',', '.', $matches[1]);

            return (int) round($value * 1_000_000);
        }

        // Nombre simple, espaces de milliers tolérés (« 200 000 »).
        if (preg_match('/(\d[\d\s]{2,})/u', $normalized, $matches) === 1) {
            $digits = preg_replace('/\s+/u', '', $matches[1]) ?? '';

            // Numéro de téléphone sénégalais : 9 chiffres commençant par 7.
            if (preg_match('/^7\d{8}$/', $digits) === 1) {
                return null;
            }

            $budget = (int) $digits;

            return $budget >= 1_000 ? $budget : null;
        }

        return null;
    }

    /**
     * Un des mots-clés apparaît-il dans le message normalisé ?
     *
     * La comparaison se fait sur des MOTS ENTIERS, pas par sous-chaîne : sans
     * cela, « bus » déclencherait sur « abusif » et « m » sur n'importe quoi.
     * Les mots-clés composés (« orange money », « nanga def ») restent gérés,
     * les frontières encadrant l'expression entière.
     *
     * @param  array<int, string>  $keywords
     */
    private function matchesAny(string $normalized, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($this->normalize($keyword), '/').'(?![\p{L}\p{N}])/u';

            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalise pour la comparaison : minuscules et accents retirés.
     *
     * Indispensable ici : les visiteurs tapent « nuitee » aussi souvent que
     * « nuitée », et « Thies » aussi souvent que « Thiès ».
     */
    private function normalize(string $value): string
    {
        $lower = mb_strtolower(trim($value));

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $lower);

        return $transliterated !== false ? mb_strtolower($transliterated) : $lower;
    }
}
