<?php

namespace App\Console\Commands;

use App\Support\Offers\OfferRetirementService;
use App\Support\Trash\ListingTrash;
use Illuminate\Console\Command;

/**
 * Vide la corbeille des annonces passées le délai de conservation (F11.4).
 *
 * C'est la moitié invisible de la corbeille : sans cette commande, « supprimé »
 * ne voudrait plus jamais rien dire — les lignes resteraient en base pour
 * toujours, et la promesse faite à l'écran (« supprimé définitivement dans 12
 * jours ») serait un mensonge.
 *
 * ⚠️ **`forceDelete()` et non `delete()`** : sur un modèle en `SoftDeletes`, un
 * `delete()` ne ferait que réécrire la date d'effacement — la commande
 * tournerait chaque nuit sans jamais rien supprimer, en silence.
 */
class PurgeTrashCommand extends Command
{
    protected $signature = 'corbeille:purger {--dry-run : Compter sans rien supprimer}';

    protected $description = 'Supprime définitivement les annonces à la corbeille depuis plus de '.ListingTrash::JOURS_DE_CONSERVATION.' jours';

    public function __construct(private readonly OfferRetirementService $medias)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limite = now()->subDays(ListingTrash::JOURS_DE_CONSERVATION);
        $simulation = (bool) $this->option('dry-run');
        $total = 0;

        foreach (ListingTrash::TYPES as $slug => $classe) {
            $expirees = $classe::onlyTrashed()->where('deleted_at', '<=', $limite)->get();

            if ($expirees->isEmpty()) {
                continue;
            }

            $this->line(sprintf(
                '%-12s %d élément(s) au-delà de %s',
                $slug,
                $expirees->count(),
                $limite->toDateString(),
            ));

            if (! $simulation) {
                // ⚠️ Un par un, PAS en masse : la suppression définitive doit
                // déclencher les événements du modèle (photos, cache du
                // catalogue). Un `forceDelete()` sur la requête les
                // court-circuiterait et laisserait des fichiers orphelins.
                foreach ($expirees as $annonce) {
                    // ⚠️ Les fichiers d'abord, la ligne ensuite. C'est ICI et
                    // nulle part ailleurs que les photos d'une annonce sont
                    // détruites : tant qu'elle dort à la corbeille, elle doit
                    // pouvoir revenir intacte. Sans cet appel, chaque purge
                    // laisserait sur le disque des fichiers que plus rien ne
                    // référence et que personne ne nettoierait jamais.
                    $this->medias->supprimerLesMedias($annonce);
                    $annonce->forceDelete();
                }
            }

            $total += $expirees->count();
        }

        $this->info($simulation
            ? "{$total} élément(s) seraient supprimés définitivement."
            : "{$total} élément(s) supprimés définitivement.");

        return self::SUCCESS;
    }
}
