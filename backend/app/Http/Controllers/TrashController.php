<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use App\Support\Trash\ListingTrash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Corbeille des espaces utilisateurs (F11.4).
 *
 * Un seul écran pour les cinq types d'annonces : le besoin exprimé est
 * d'**alléger les onglets** sans rien perdre. Une corbeille par onglet aurait
 * raté le but — on aurait remplacé une liste encombrée par cinq listes
 * encombrées.
 *
 * ⚠️ **Ce contrôleur ne supprime rien.** La mise à la corbeille reste le geste
 * des contrôleurs métier de chaque module (qui connaissent leurs policies) ;
 * `SoftDeletes` fait qu'un `delete()` y devient un effacement différé, sans
 * qu'une seule ligne de leur code ne change. Ici on ne fait que **regarder** et
 * **restaurer**.
 */
class TrashController extends Controller
{
    public function __construct(private readonly ListingTrash $corbeille) {}

    /**
     * Le contenu de la corbeille, tous types confondus, du plus récemment jeté
     * au plus ancien.
     *
     * ⚠️ **Pas de pagination, et c'est un choix argumenté** : la corbeille se
     * vide seule au bout de 30 jours, elle ne peut donc pas grossir
     * indéfiniment comme une liste d'annonces. Une personne qui range beaucoup
     * y verra quelques dizaines de lignes, jamais des milliers.
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
                $elements[] = $this->presenter($slug, $annonce);
            }
        }

        // Tri en PHP et non en SQL : les éléments viennent de cinq tables
        // différentes, aucune requête ne peut les ordonner ensemble.
        usort($elements, static fn (array $a, array $b) => strcmp($b['deleted_at'], $a['deleted_at']));

        return ApiResponse::success([
            'items' => $elements,
            'retention_days' => ListingTrash::JOURS_DE_CONSERVATION,
        ]);
    }

    /**
     * Sort une annonce de la corbeille.
     *
     * ⚠️ Elle revient **éteinte** (voir `ListingTrash::eteindre()`) : jamais en
     * ligne d'elle-même. Entre-temps le bien a pu être vendu, le véhicule
     * accidenté, le prix devenir faux.
     */
    public function restore(Request $request, string $type, int $id): JsonResponse
    {
        $requete = $this->corbeille->corbeilleDe($type, $request->user()->id);

        if ($requete === null) {
            return ApiResponse::error('Type d’annonce inconnu.', 404);
        }

        $annonce = $requete->whereKey($id)->first();

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
            'item' => $this->presenter($type, $annonce->fresh()),
            'message' => 'Élément restauré. Il est de nouveau dans votre liste, hors ligne : republiez-le quand vous le souhaitez.',
        ]);
    }

    /**
     * Forme d'affichage d'une ligne de corbeille — volontairement FERMÉE :
     * de quoi reconnaître l'annonce et décider, rien de plus. Aucun prix,
     * aucune donnée de contact, aucun identifiant technique interne.
     */
    private function presenter(string $slug, Model $annonce): array
    {
        return [
            'type' => $slug,
            'id' => $annonce->getKey(),
            'label' => $this->corbeille->intitule($annonce),
            'deleted_at' => (string) $annonce->getAttribute('deleted_at'),
            'days_left' => $this->corbeille->joursRestants($annonce),
        ];
    }
}
