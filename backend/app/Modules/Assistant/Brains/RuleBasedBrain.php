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

        // 0. ÉQUIPE BACK-OFFICE (F10.3) — avant toutes les autres règles, et
        //    l'ordre est ici encore plus décisif qu'en F10.2. Le vocabulaire du
        //    poste de commandement recoupe celui du public sur presque tous ses
        //    mots : « support » déclencherait l'escalade (règle 1) pour un agent
        //    qui demande sa boîte de réception, « paiement » enverrait un
        //    responsable financier dans la FAQ client (règle 4), « demande »
        //    croiserait les dossiers personnels (règle 2). Placée en tête et
        //    réservée au staff, cette règle laisse toutes les suivantes intactes
        //    pour les 5 rôles publics.
        if ($context->isStaff() && ($backOffice = $this->detectBackOfficeTopic($normalized, $message))) {
            [$outil, $entree] = $backOffice;

            // ⚠️ Ici, contrairement à F10.2, un outil hors trousse NE se
            // poursuit PAS dans les règles suivantes. Pour un client, « mes
            // missions » n'a simplement pas de sens et la suite du parcours est
            // utile ; pour un agent, l'outil manque parce que la DÉLÉGATION
            // manque (grant pur, F7.1.b) — le laisser filer lui servirait une
            // entrée de FAQ client en réponse à une question d'exploitation. On
            // le dit, et ce n'est pas une fuite : on ne lui apprend que ses
            // propres droits.
            if ($tools->find($outil, $context) === null) {
                return AssistantReply::fallback(
                    'Vos droits ne couvrent pas ce dossier — demandez la délégation correspondante '
                    .'au super administrateur.',
                    [AssistantAction::link('Ouvrir le back-office', '/back-office')],
                );
            }

            return $this->useTool($tools, $context, $outil, $entree);
        }

        // 1. Demande explicite d'un humain — prioritaire sur tout le reste.
        //    Quand quelqu'un demande un conseiller, lui répondre par une liste
        //    d'annonces est la meilleure façon de le perdre.
        if ($this->matchesAny($normalized, self::SUPPORT_KEYWORDS)) {
            return $this->useTool($tools, $context, 'contacter_support', ['sujet' => $message]);
        }

        // 2. « MES » dossiers (F10.2) — AVANT le catalogue, et l'ordre est tout.
        //    « où en est ma réservation de villa » contient « villa » : traité
        //    plus bas, il aurait renvoyé des annonces à vendre à quelqu'un qui
        //    demandait des nouvelles de son séjour.
        if ($personnel = $this->detectPersonalTopic($normalized)) {
            // Sans session, aucun de ces outils n'est ouvert — et le dire
            // franchement vaut mieux que de passer au support : la personne a
            // un compte, elle a juste oublié de se connecter.
            if (! $context->isAuthenticated()) {
                return AssistantReply::fallback(
                    'Connectez-vous et je consulte vos dossiers directement ici.',
                    [AssistantAction::link('Se connecter', '/auth/connexion')],
                );
            }

            // ⚠️ On ne se branche QUE si l'outil est réellement ouvert à ce rôle.
            // Un client qui écrit « mes missions » (rubrique qui n'existe pas
            // chez lui) doit poursuivre son chemin dans les règles suivantes,
            // pas buter sur un « je ne peux pas traiter cette demande ».
            if ($tools->find($personnel, $context) !== null) {
                return $this->useTool($tools, $context, $personnel, []);
            }
        }

        // 3. Recherche dans le catalogue.
        if ($universe = $this->detectUniverse($normalized)) {
            return $this->useTool($tools, $context, 'rechercher_catalogue', [
                'univers' => $universe,
                'ville' => $this->detectPlace($normalized),
                'budget_max' => $this->detectBudget($normalized),
            ]);
        }

        // 4. Question sur le fonctionnement de la plateforme.
        if ($this->matchesAny($normalized, self::FAQ_KEYWORDS)) {
            return $this->useTool($tools, $context, 'consulter_faq', ['question' => $message]);
        }

        // 5. Simple salutation : on accueille et on oriente, sans outil.
        if ($this->matchesAny($normalized, self::GREETINGS)) {
            return AssistantReply::fallback(
                $this->greeting($context),
                $this->orientationActions(),
            );
        }

        // 6. Rien de reconnu. On le DIT — inventer une réponse serait pire.
        return $this->useTool($tools, $context, 'contacter_support', ['sujet' => $message]);
    }

    /**
     * Sujets « MES dossiers » → outil correspondant (F10.2).
     *
     * L'ordre compte, comme pour les univers : le premier reconnu l'emporte.
     */
    private const PERSONAL_KEYWORDS = [
        'mes_reservations' => ['reservation', 'reservations', 'sejour', 'sejours'],
        'mes_missions' => ['mission', 'missions', 'affectation', 'affectations'],
        // ⚠️ « bien » redevient utilisable ICI, alors qu'il est banni des
        // mots-clés du catalogue : la marque de possession exigée ci-dessous
        // écarte l'adverbe (« je voudrais bien savoir… » n'a pas de possessif).
        'mes_biens' => ['bien', 'biens', 'annonce', 'annonces', 'propriete', 'proprietes'],
        'mes_projets_diaspora' => ['projet', 'projets', 'diaspora'],
        'mes_demandes' => ['demande', 'demandes', 'dossier', 'dossiers'],
    ];

    /**
     * Marques de possession — le filtre qui rend cette détection utilisable.
     */
    private const POSSESSIVES = ['ma', 'mon', 'mes', 'notre', 'nos'];

    /**
     * Reconnaît « où en est MA réservation », « MES annonces sont-elles en ligne ».
     *
     * ⚠️ **Deux conditions, pas une** : un mot de possession ET un sujet. Ce
     * n'est pas un raffinement, c'est ce qui sépare deux intentions opposées —
     * « je cherche une réservation » veut le catalogue, « où en est ma
     * réservation » veut un dossier. Sans le possessif, la détection avalerait
     * la moitié des recherches du site et l'assistant répondrait « vous n'avez
     * aucune réservation » à quelqu'un qui voulait en faire une.
     *
     * C'est aussi ce qui permet de reconnaître « mon bien » sans réintroduire
     * le faux positif de « je voudrais **bien** savoir comment payer » (défaut
     * corrigé en F10.0).
     */
    private function detectPersonalTopic(string $normalized): ?string
    {
        if (! $this->matchesAny($normalized, self::POSSESSIVES)) {
            return null;
        }

        foreach (self::PERSONAL_KEYWORDS as $tool => $keywords) {
            if ($this->matchesAny($normalized, $keywords)) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * Sujets du BACK-OFFICE → outil correspondant (F10.3).
     *
     * L'ordre compte, et il est réglé sur les collisions réelles : « paiement »
     * avant « compte » (« le compte du paiement PAY-… » vise le règlement),
     * « compte » avant « demande » (« le compte qui a fait la demande » vise la
     * personne), et l'activité en dernier — c'est la question la plus vague, elle
     * ne doit gagner que si aucune autre n'a reconnu de sujet précis.
     */
    private const BACKOFFICE_KEYWORDS = [
        'file_validation' => ['validation', 'valider', 'moderation', 'moderer', 'attente de validation'],
        'suivre_paiement' => ['paiement', 'paiements', 'reglement', 'reglements', 'transaction', 'transactions', 'encaisse', 'rembourse', 'remboursement'],
        'rechercher_compte' => ['compte', 'comptes', 'utilisateur', 'utilisateurs', 'inscrit', 'inscrits', 'annuaire'],
        'fils_support' => ['message', 'messages', 'fil', 'fils', 'conversation', 'conversations', 'support', 'boite de reception'],
        'demandes_a_traiter' => ['demande', 'demandes', 'file', 'traiter', 'dossier', 'dossiers'],
        'activite_plateforme' => ['activite', 'tableau de bord', 'statistiques', 'statistique', 'indicateurs', 'kpi', 'chiffres', 'plateforme', 'aujourd\'hui'],
    ];

    /**
     * Mots vides écartés lors de l'extraction d'un terme de recherche.
     */
    private const MOTS_VIDES = [
        'de', 'du', 'des', 'la', 'le', 'les', 'un', 'une', 'et', 'ou', 'a', 'au', 'aux',
        'pour', 'sur', 'dans', 'avec', 'chez', 'est', 'sont', 'qui', 'que', 'quoi',
        'moi', 'me', 'je', 'tu', 'il', 'elle', 'on', 'nous', 'vous',
        'trouve', 'trouver', 'retrouve', 'retrouver', 'cherche', 'chercher', 'ouvre',
        'ouvrir', 'montre', 'montrer', 'affiche', 'afficher', 'donne', 'donner',
        'fiche', 'compte', 'comptes', 'utilisateur', 'utilisateurs', 'inscrit',
        'inscrits', 'annuaire', 'client', 'cliente', 'monsieur', 'madame',
        'paiement', 'paiements', 'reglement', 'reglements', 'transaction',
        'transactions', 'reference', 'stp', 'svp',
    ];

    /**
     * Reconnaît un sujet de back-office et prépare l'entrée de l'outil.
     *
     * ⚠️ Deux des six outils exigent un ARGUMENT (`rechercher_compte`,
     * `suivre_paiement`) — c'est la première fois dans le module qu'un outil
     * dépend d'une donnée extraite du message. On le passe même vide : c'est
     * l'outil qui répond « précisez un nom » ou « donnez-moi la référence », et
     * non le cerveau, pour que la consigne reste la même quel que soit le
     * cerveau branché (le driver Claude de F10.4 y arrivera par un autre chemin).
     *
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private function detectBackOfficeTopic(string $normalized, string $message): ?array
    {
        // Une référence bien formée (PAY-XXXX, BK-XXXX) tranche à elle seule :
        // personne ne cite une référence pour parler d'autre chose, et l'agent
        // qui la colle sans phrase attend un règlement, pas un tableau de bord.
        $reference = $this->extractReference($message);

        foreach (self::BACKOFFICE_KEYWORDS as $outil => $keywords) {
            if (! $this->matchesAny($normalized, $keywords)) {
                continue;
            }

            return match ($outil) {
                'rechercher_compte' => [$outil, ['terme' => $this->extractTerm($normalized, $message)]],
                'suivre_paiement' => [$outil, ['reference' => $reference ?? '']],
                default => [$outil, []],
            };
        }

        return $reference !== null ? ['suivre_paiement', ['reference' => $reference]] : null;
    }

    /**
     * Référence de dossier citée dans le message (`PAY-3F9K2M`, `BK-…`).
     *
     * ⚠️ Lue sur le message BRUT et non sur sa version normalisée : les
     * références sont en capitales, et si la comparaison SQL les ignore, le
     * motif ci-dessous a besoin de la forme d'origine pour ne pas confondre un
     * identifiant avec un mot composé (« rendez-vous », « week-end »). D'où
     * l'exigence de capitales et d'au moins un chiffre dans la partie droite.
     */
    private function extractReference(string $message): ?string
    {
        // ⚠️ Le motif accepte PLUSIEURS segments (`PAY-ACPT-6YRYXV`) et pas un
        // seul. Défaut trouvé en curl sur la base réelle : sur une référence à
        // deux tirets, un motif à segment unique s'arrête sur « PAY-ACPT » — la
        // frontière de mot tombe sur le second tiret — puis se fait rejeter par
        // le contrôle de chiffre, et l'assistant répondait « donnez-moi la
        // référence » à quelqu'un qui venait de la coller en entier.
        if (preg_match('/\b([A-Z]{2,6}(?:-[A-Z0-9]{2,}){1,3})\b/u', $message, $matches) === 1
            && preg_match('/\d/', $matches[1]) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Terme de recherche d'un compte, extrait du message.
     *
     * Trois formes, de la plus fiable à la plus faible :
     *   1. une adresse e-mail — sans ambiguïté possible ;
     *   2. un numéro sénégalais à 9 chiffres ;
     *   3. à défaut, les mots qui restent une fois retirés les mots vides et le
     *      vocabulaire de la question elle-même (« retrouve-moi le compte de »).
     *
     * ⚠️ La troisième est une heuristique, et elle est assumée comme telle : sur
     * « le compte de Fatou Ndiaye » elle rend « fatou ndiaye », sur une phrase
     * tarabiscotée elle rendra du bruit — auquel cas la recherche ne trouve rien
     * et l'outil renvoie à l'annuaire. Un terme faux coûte un « aucun résultat »,
     * jamais une fuite : la requête reste bornée par `gerer:utilisateurs`.
     *
     * ⚠️ Le nom est repris du message BRUT (accents et capitales d'origine) pour
     * ne pas chercher « fatou ndiaye » là où la base contient « Fatou Ndiaye » :
     * `LIKE` est insensible à la casse, mais pas aux accents sur toutes les
     * collations.
     */
    private function extractTerm(string $normalized, string $message): string
    {
        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/u', $message, $matches) === 1) {
            return $matches[0];
        }

        // ⚠️ On concatène TOUS les chiffres du message avant de chercher, au
        // lieu de poser des frontières de mot sur le texte : au téléphone, un
        // numéro s'écrit « 77 123 45 67 » ou « +221 77 123 45 67 » bien plus
        // souvent que d'un bloc, et un `\b7\d{8}\b` ne reconnaît aucune de ces
        // deux formes (défaut trouvé au premier passage des tests).
        $chiffres = preg_replace('/\D+/u', '', $message) ?? '';

        if (preg_match('/7\d{8}/', $chiffres, $matches) === 1) {
            return $matches[0];
        }

        // Découpage du message brut, en gardant l'ordre : on compare chaque mot
        // à sa forme normalisée, mais on conserve la forme d'origine.
        $mots = preg_split('/[^\p{L}\p{N}\-]+/u', trim($message), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $retenus = array_filter(
            $mots,
            fn (string $mot) => ! $this->estMotVide($mot) && mb_strlen($mot) >= 2,
        );

        // Trois mots au plus : au-delà, c'est une phrase, et une phrase entière
        // passée en `LIKE` ne correspond jamais à rien.
        return implode(' ', array_slice(array_values($retenus), 0, 3));
    }

    /**
     * Ce mot est-il un mot vide ?
     *
     * ⚠️ **Le trait d'union est traité à part**, et c'est le second défaut
     * trouvé en curl. « retrouve-moi le compte de Pierre Robert » produisait le
     * terme « retrouve-moi Pierre Robert » : le découpage garde les traits
     * d'union (sans quoi « Anne-Marie » se casserait en deux), donc
     * « retrouve-moi » n'était comparé à aucune entrée de la liste et passait
     * pour un nom. La recherche partait alors sur une phrase et ne trouvait
     * évidemment rien — l'assistant paraissait ne pas connaître un client
     * parfaitement présent en base.
     *
     * Un mot composé est donc vide si TOUTES ses parties le sont : « Anne-Marie »
     * survit, « retrouve-moi » disparaît.
     */
    private function estMotVide(string $mot): bool
    {
        $parties = array_filter(explode('-', $this->normalize($mot)), fn ($p) => $p !== '');

        if ($parties === []) {
            return true;
        }

        foreach ($parties as $partie) {
            if (! in_array($partie, self::MOTS_VIDES, strict: true)) {
                return false;
            }
        }

        return true;
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
