<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Trash\ListingTrash;
use App\Support\Trash\PersonalHiding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Corbeille des espaces utilisateurs (F11.4, étendue à l'espace client en F11.5).
 *
 * Un seul écran pour tout ce qu'on range : le besoin exprimé est d'**alléger
 * les onglets** sans rien perdre. Une corbeille par onglet aurait raté le but —
 * on aurait remplacé une liste encombrée par cinq listes encombrées.
 *
 * ⚠️ **Deux familles y cohabitent, et elles n'obéissent PAS à la même règle** —
 * c'est la seule chose à retenir de ce fichier :
 *
 *   - les **annonces** (F11.4, `ListingTrash`) sont supprimées pour de bon au
 *     bout de 30 jours et reviennent **éteintes** : elles appartiennent à leur
 *     auteur, et il les publie à des tiers ;
 *   - les **dossiers** du client (F11.5, `PersonalHiding` : demandes,
 *     réservations, fils de discussion, notifications) ne sont **jamais**
 *     supprimés et reviennent **tels quels** : ils sont partagés avec Kaikun et
 *     un partenaire. Le client ne fait que retirer la ligne de SA vue.
 *
 * L'écran, lui, ne montre qu'un seul geste — l'utilisateur n'a pas à connaître
 * cette distinction, seulement à lire le compte à rebours quand il y en a un.
 *
 * ⚠️ **Ce contrôleur ne range rien.** Mettre à la corbeille reste le geste des
 * contrôleurs métier (qui connaissent leurs policies et savent refuser). Ici on
 * ne fait que **regarder** et **restaurer**.
 */
class TrashController extends Controller
{
    /**
     * Nombre maximum de lignes rendues en une fois.
     *
     * ⚠️ Généreux à dessein : il n'est pas là pour découper la lecture en pages
     * (une corbeille se parcourt d'un coup d'œil), mais pour **borner** une
     * réponse que les dossiers masqués rendent autrement infinie — eux ne sont
     * jamais purgés. Personne ne devrait l'atteindre en usage normal ; si
     * quelqu'un l'atteint, `truncated` le lui dit.
     */
    private const PLAFOND = 200;

    public function __construct(
        private readonly ListingTrash $corbeille,
        private readonly PersonalHiding $masquage,
    ) {}

    /**
     * Le contenu de la corbeille, tous types confondus, du plus récemment rangé
     * au plus ancien.
     *
     * ⚠️ **Pas de pagination, mais un PLAFOND — et la raison a changé en
     * F11.5.** Tant que la corbeille ne contenait que des annonces, elle se
     * vidait seule au bout de 30 jours : elle ne pouvait pas grossir. Les
     * dossiers masqués, eux, ne sont **jamais** purgés — quelqu'un qui range
     * ses notifications pendant deux ans en accumulerait indéfiniment, et cette
     * réponse n'aurait plus aucune borne.
     *
     * Le plafond porte sur la liste FUSIONNÉE et triée, pas sur chaque type :
     * c'est « les N derniers rangements », ce que l'écran lit naturellement. Il
     * est **annoncé** (`truncated`) et jamais silencieux — une corbeille qui
     * cache ce qu'elle contient ne remplit plus son seul office.
     */
    public function index(Request $request): JsonResponse
    {
        $utilisateur = $request->user();
        $elements = [];

        foreach (array_keys(ListingTrash::TYPES) as $slug) {
            $requete = $this->corbeille->corbeilleDe($slug, $utilisateur->id);

            if ($requete === null) {
                continue;
            }

            // La nuitée affiche le titre de son bien : sans ce chargement, on
            // paierait une requête par ligne (N+1) pour l'obtenir.
            if ($slug === 'stay') {
                $requete->with(['property' => fn ($q) => $q->withTrashed()]);
            }

            foreach ($requete->get() as $annonce) {
                $elements[] = $this->presenterAnnonce($slug, $annonce);
            }
        }

        // F11.5 — la seconde famille : les dossiers que le client a rangés.
        // ⚠️ Ils vivent dans le MÊME écran, et c'était le sens de la demande :
        // « ranger » est un seul geste dans la tête de l'utilisateur, il n'a pas
        // à savoir que la plateforme distingue ce qu'il possède de ce qu'il
        // partage avec nous. La différence n'apparaît que sur le compte à
        // rebours, absent de ces lignes-là.
        foreach (PersonalHiding::TYPES as $slug) {
            foreach ($this->masquage->elementsMasques($slug, $utilisateur) as $dossier) {
                $elements[] = $this->presenterDossier($slug, $dossier, $utilisateur);
            }
        }

        // Tri en PHP et non en SQL : les éléments viennent de sept tables
        // différentes, aucune requête ne peut les ordonner ensemble.
        usort($elements, static fn (array $a, array $b) => strcmp($b['removed_at'], $a['removed_at']));

        $total = count($elements);

        return ApiResponse::success([
            'items' => array_slice($elements, 0, self::PLAFOND),
            'retention_days' => ListingTrash::JOURS_DE_CONSERVATION,
            // L'écran doit pouvoir dire « il y en a d'autres, plus anciens »
            // plutôt que de laisser croire que la corbeille s'arrête là.
            'truncated' => $total > self::PLAFOND,
            'total' => $total,
        ]);
    }

    /**
     * Sort une annonce de la corbeille.
     *
     * ⚠️ Elle revient **éteinte** (voir `ListingTrash::eteindre()`) : jamais en
     * ligne d'elle-même. Entre-temps le bien a pu être vendu, le véhicule
     * accidenté, le prix devenir faux.
     */
    public function restore(Request $request, string $type, string $id): JsonResponse
    {
        // F11.5 — les dossiers du client passent par un chemin distinct : ils
        // n'ont jamais été supprimés, il n'y a donc rien à « restaurer » au sens
        // d'Eloquent, seulement un masque à retirer.
        if (in_array($type, PersonalHiding::TYPES, true)) {
            return $this->reafficher($request, $type, $id);
        }

        $requete = $this->corbeille->corbeilleDe($type, $request->user()->id);

        if ($requete === null) {
            return ApiResponse::error('Type d’élément inconnu.', 404);
        }

        // ⚠️ La contrainte `whereNumber` a quitté la route en F11.5 (l'id d'une
        // notification est un UUID) : une annonce, elle, se cherche toujours par
        // un entier, et on refuse ici ce que la route ne filtre plus.
        if (! ctype_digit($id)) {
            return ApiResponse::error('Cet élément n’est pas dans votre corbeille.', 404);
        }

        $annonce = $requete->whereKey((int) $id)->first();

        // ⚠️ Même réponse pour « n'existe pas » et « ne vous appartient pas » :
        // distinguer les deux dirait à un curieux qu'une annonce existe à cet
        // identifiant chez quelqu'un d'autre.
        if ($annonce === null) {
            return ApiResponse::error('Cet élément n’est pas dans votre corbeille.', 404);
        }

        $annonce->restore();
        $this->corbeille->eteindre($annonce);

        // ⚠️ Le message voyage DANS `data`, pas en second argument :
        // `ApiResponse::success()` attend là des métadonnées (tableau), pas une
        // phrase. C'est la convention de tous les autres contrôleurs.
        return ApiResponse::success([
            'item' => $this->presenterAnnonce($type, $annonce->fresh()),
            'message' => 'Élément restauré. Il est de nouveau dans votre liste, hors ligne : republiez-le quand vous le souhaitez.',
        ]);
    }

    /**
     * Remet un dossier du client (demande, réservation) dans sa liste (F11.5).
     *
     * ⚠️ Le dossier revient **tel quel**, statut compris — à l'inverse d'une
     * annonce, qui revient éteinte. Une annonce est publiée à des tiers et le
     * monde a pu changer pendant son séjour à la corbeille ; un dossier masqué,
     * lui, n'a jamais cessé d'exister pour Kaikun ni pour le partenaire, et
     * toucher à son statut reviendrait à réécrire un contrat.
     */
    private function reafficher(Request $request, string $type, string $id): JsonResponse
    {
        $utilisateur = $request->user();
        $dossier = $this->masquage->trouverMasque($type, $utilisateur, $id);

        // Même réponse que plus haut pour « n'existe pas » et « n'est pas à
        // vous » : la distinction renseignerait un curieux.
        if ($dossier === null) {
            return ApiResponse::error('Cet élément n’est pas dans votre corbeille.', 404);
        }

        // ⚠️ La ligne est présentée AVANT d'être démasquée : après, son
        // `hidden_at` est nul et `removed_at` sortirait vide.
        $ligne = $this->presenterDossier($type, $dossier, $utilisateur);

        $this->masquage->reafficher($dossier, $utilisateur);

        return ApiResponse::success([
            'item' => $ligne,
            'message' => 'Dossier remis dans votre liste, exactement dans l’état où vous l’aviez laissé.',
        ]);
    }

    /**
     * Forme d'affichage d'une ANNONCE rangée — volontairement FERMÉE : de quoi
     * la reconnaître et décider, rien de plus. Aucun prix, aucune donnée de
     * contact, aucun identifiant technique interne.
     */
    private function presenterAnnonce(string $slug, Model $annonce): array
    {
        return [
            'type' => $slug,
            // Chaîne, comme les dossiers de F11.5 : l'écran manipule les deux
            // familles dans une seule liste, un type d'identifiant qui change
            // d'une ligne à l'autre finirait par se comparer de travers.
            'id' => (string) $annonce->getKey(),
            'kind' => 'listing',
            'label' => $this->corbeille->intitule($annonce),
            'removed_at' => (string) $annonce->getAttribute('deleted_at'),
            'days_left' => $this->corbeille->joursRestants($annonce),
        ];
    }

    /**
     * Forme d'affichage d'un DOSSIER rangé (F11.5) — même structure que celle
     * d'une annonce, à un champ près qui fait toute la différence.
     *
     * ⚠️ **`days_left` vaut `null`, et ce n'est pas une valeur manquante** :
     * c'est l'information elle-même. Rien ne sera supprimé, le dossier attend
     * seulement d'être rappelé. Écrire 30 ici pour « faire pareil » mentirait
     * sur ce que la plateforme s'engage à conserver.
     */
    private function presenterDossier(string $slug, Model $dossier, User $utilisateur): array
    {
        return [
            'type' => $slug,
            // ⚠️ Rendu en CHAÎNE, pas en entier : l'identifiant d'une
            // notification est un UUID. Le typer `int` le ramènerait à 0 et
            // rendrait toutes les notifications indiscernables.
            'id' => (string) $dossier->getKey(),
            'kind' => 'record',
            'label' => $this->masquage->intitule($dossier),
            'removed_at' => $this->masquage->masqueLe($dossier, $utilisateur),
            'days_left' => null,
        ];
    }
}
