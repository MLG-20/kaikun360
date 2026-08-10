<?php

namespace App\Support\Offers;

use App\Models\Media;
use App\Modules\Explore\Enums\ExperienceStatus;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Mobility\Enums\MobilityServiceStatus;
use App\Modules\Mobility\Enums\VehicleStatus;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Retrait d'une offre par son prestataire (F8.19).
 *
 * POURQUOI CE SERVICE EXISTE
 * --------------------------
 * Un prestataire ne pouvait **ni supprimer ni retirer** un véhicule ou un
 * circuit : une offre déposée par erreur, un véhicule vendu, un circuit qui ne
 * tourne plus restaient au catalogue pour toujours. Les seules routes existantes
 * étaient `POST` (dépôt) et, pour les véhicules seulement, `PATCH`.
 *
 * LA RÈGLE, EN UN MOT : **on ne supprime jamais une offre qui a servi.**
 *
 * ⚠️ `bookings` pointe sur l'offre par une relation **polymorphe**, donc **sans
 * clé étrangère** pour la retenir. Supprimer un véhicule réservé ne lèverait
 * aucune erreur en base : ça laisserait simplement des clients devant une
 * réservation dont l'objet a disparu — un historique qui ment, et un litige
 * impossible à instruire. La suppression réelle est donc réservée aux offres
 * **jamais réservées** ; les autres sont **retirées** (statut `RETIRE`), ce qui
 * les sort du catalogue sans toucher au passé.
 *
 * ⚠️ Le retrait est **réversible** (le prestataire republie, l'offre repasse en
 * validation), la suppression ne l'est pas. C'est pourquoi le service ne
 * *choisit* pas à la place de l'écran : il expose `peutEtreSupprimee()` pour que
 * l'interface annonce la bonne conséquence AVANT le clic.
 */
class OfferRetirementService
{
    /**
     * Cette offre peut-elle être réellement supprimée ?
     *
     * Non dès qu'un client l'a réservée — **même une réservation annulée
     * compte** : elle reste dans l'historique du client, et son objet doit
     * rester lisible.
     */
    public function peutEtreSupprimee(Model $offre): bool
    {
        return $this->raisonDeConservation($offre) === null;
    }

    /**
     * Pourquoi cette offre doit être conservée, ou `null` si rien ne s'y oppose.
     *
     * Le message est destiné au prestataire : il doit comprendre *pourquoi* son
     * offre est retirée plutôt que supprimée, sinon il croit à un bug.
     */
    public function raisonDeConservation(Model $offre): ?string
    {
        if ($offre->bookings()->exists()) {
            return 'Cette offre a déjà été réservée : elle est retirée du catalogue, '
                .'mais conservée pour l\'historique de vos clients.';
        }

        // ⚠️ Propre aux véhicules : `mobility_services.vehicle_id` est en
        // `nullOnDelete`. Supprimer le véhicule ne casserait donc rien en base,
        // mais viderait silencieusement les trajets de leur illustration et de
        // leur référence — le prestataire ne comprendrait pas ce qui s'est passé.
        if ($offre instanceof Vehicle && MobilityService::query()->where('vehicle_id', $offre->id)->exists()) {
            return 'Ce véhicule assure des trajets programmés : il est retiré du '
                .'catalogue, mais conservé tant que ces trajets existent.';
        }

        return null;
    }

    /**
     * Retire l'offre du catalogue : suppression réelle si elle n'a jamais servi,
     * retrait conservatoire sinon.
     *
     * @return array{deleted: bool, reason: string|null} ce qui a réellement eu lieu
     */
    public function retirer(Model $offre): array
    {
        $raison = $this->raisonDeConservation($offre);

        if ($raison !== null) {
            $offre->update(['status' => $this->statutRetire($offre)]);

            return ['deleted' => false, 'reason' => $raison];
        }

        DB::transaction(function () use ($offre) {
            // ⚠️ **LES MÉDIAS NE SONT PLUS EFFACÉS ICI** (F11.4), et c'est le
            // point à ne surtout pas « re-corriger » en les remettant.
            //
            // Depuis la corbeille, ce `delete()` n'efface plus la ligne : il la
            // met de côté 30 jours, récupérable. Détruire les fichiers au
            // passage rendrait la restauration illusoire — l'offre reviendrait
            // sans une seule photo, définitivement, alors même que l'écran
            // promet de la rendre intacte.
            //
            // Le ménage des fichiers appartient désormais au SEUL moment où
            // l'effacement devient irréversible : la purge des 30 jours
            // (`corbeille:purger`), qui appelle `supprimerLesMedias()`.
            $offre->delete();
        });

        return ['deleted' => true, 'reason' => null];
    }

    /**
     * Efface la galerie d'une offre, fichiers physiques compris.
     *
     * ⚠️ Publique depuis F11.4 : c'est la commande de purge qui l'appelle, au
     * moment où l'effacement devient définitif. Ne l'appeler nulle part
     * ailleurs — tout autre point d'appel détruirait les images d'une offre
     * encore récupérable.
     */
    public function supprimerLesMedias(Model $offre): void
    {
        $medias = Media::query()
            ->where('mediable_type', $offre->getMorphClass())
            ->where('mediable_id', $offre->getKey())
            ->get();

        foreach ($medias as $media) {
            if ($media->path) {
                Storage::disk($media->disk)->delete($media->path);
            }

            // Suppression une à une (et non `->delete()` sur la requête) pour que
            // le crochet du modèle purge les catalogues en cache — sans quoi la
            // carte survivrait cinq minutes à l'offre qu'elle illustre.
            $media->delete();
        }
    }

    /** Le statut « retiré » du type d'offre concerné. */
    private function statutRetire(Model $offre): string
    {
        return match (true) {
            $offre instanceof Vehicle => VehicleStatus::RETIRE->value,
            $offre instanceof TourismExperience => ExperienceStatus::RETIRE->value,
            // F8.23 — un départ programmé se retire comme les deux autres offres.
            $offre instanceof MobilityService => MobilityServiceStatus::RETIRE->value,
            default => throw new \InvalidArgumentException(
                'Type d\'offre non géré par le retrait : '.$offre::class,
            ),
        };
    }
}
