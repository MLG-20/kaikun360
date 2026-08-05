<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Fait avancer les réservations datées jusqu'au bout de leur cycle (F8.15.a).
 *
 * POURQUOI CETTE COMMANDE EXISTE
 * ------------------------------
 * `BookingStatus` décrit sept états depuis B11, mais seuls trois étaient
 * réellement atteints : `en_attente` à la création, `confirmee` au paiement, et
 * les trois `annulee_*`. **`en_cours` et `terminee` n'étaient écrits nulle part** :
 * ni le check-in/check-out des nuitées (qui n'horodatait que ses propres
 * colonnes), ni l'encaissement, ni aucun geste du back-office ne les posaient.
 *
 * Conséquence trouvée en confrontant le code au cahier des charges : la
 * `ReviewPolicy` exige une réservation **terminée** pour déposer un avis
 * (`Review::hasConsumed`). Comme aucune réservation ne le devenait jamais,
 * `POST /reviews` était inatteignable — le back-office modérait une file que
 * rien ne pouvait alimenter, et la note des prestataires ne pouvait pas monter.
 * Construire l'écran de dépôt d'avis sans fermer ce cycle n'aurait donné le
 * droit d'écrire à personne.
 *
 * CE QU'ELLE FAIT
 * ---------------
 * Pour les réservations **datées** (nuitée, véhicule, expérience, trajet) :
 *   - `confirmee` → `en_cours` quand le service a commencé ;
 *   - `confirmee`/`en_cours` → `terminee` quand il est achevé.
 *
 * ⚠️ **Les réservations `en_attente` ne sont jamais avancées.** Une réservation
 * jamais payée n'est pas un service consommé : l'avancer donnerait le droit de
 * noter un séjour qu'on n'a pas réglé, et fausserait les statistiques de vente.
 * Une réservation impayée dont la date est passée reste donc `en_attente` — la
 * clôturer ou l'annuler est une décision commerciale, pas une règle automatique.
 *
 * ⚠️ **Les réservations sur-mesure ne sont pas concernées.** Un devis accepté
 * (chantier, séminaire, mandat) n'a pas de dates dans `bookings` : sa fin se
 * constate au dossier, pas au calendrier. Elles sont écartées par
 * `whereNotNull('start_date')` et resteront `confirmee` jusqu'à un geste humain.
 *
 * ⚠️ **La date de fin des univers n'a pas la même convention** (bornes incluses
 * pour un véhicule, fin exclue pour une nuitée, pas de fin du tout pour un
 * circuit ou un trajet). Plutôt que de rejouer ces règles ici, on prend une
 * borne unique et prudente : le service est réputé achevé quand son dernier jour
 * connu (`end_date`, à défaut `start_date`) est **strictement passé**. Un séjour
 * qui s'achève aujourd'hui bascule demain — jamais l'inverse.
 *
 * SANS RISQUE À REJOUER : chaque passage recalcule l'état depuis les dates ; une
 * réservation déjà dans le bon état n'est pas réécrite. Annulations épargnées.
 */
class CloseBookingsCommand extends Command
{
    protected $signature = 'reservations:cloturer {--dry-run : Montre ce qui serait modifié, sans rien écrire}';

    protected $description = 'Passe les réservations datées en « en cours » puis « terminée » selon leurs dates (F8.15.a)';

    public function handle(): int
    {
        $simulation = (bool) $this->option('dry-run');

        if ($simulation) {
            $this->warn('Simulation : aucune écriture en base.');
        }

        $aujourdhui = Carbon::today();

        // Seuls ces deux statuts avancent : ni les annulations, ni les
        // réservations en attente de paiement (cf. en-tête).
        $candidates = Booking::query()
            ->whereIn('status', [BookingStatus::CONFIRMEE->value, BookingStatus::EN_COURS->value])
            ->whereNotNull('start_date')
            ->orderBy('id')
            ->get();

        $demarrees = 0;
        $terminees = 0;

        foreach ($candidates as $booking) {
            $cible = $this->statutAttendu($booking, $aujourdhui);

            if ($cible === null || $cible === $booking->status) {
                continue;
            }

            $this->line(sprintf(
                '  %s : %s → %s',
                $booking->reference,
                $booking->status->label(),
                $cible->label(),
            ));

            if (! $simulation) {
                $booking->update(['status' => $cible->value]);
            }

            $cible === BookingStatus::TERMINEE ? $terminees++ : $demarrees++;
        }

        $this->info(sprintf(
            '%d réservation(s) passée(s) en cours, %d terminée(s) sur %d examinée(s).',
            $demarrees,
            $terminees,
            $candidates->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Statut que les dates imposent à cette réservation, ou `null` si elle n'a
     * pas encore commencé (rien à faire : elle reste confirmée).
     */
    private function statutAttendu(Booking $booking, Carbon $aujourdhui): ?BookingStatus
    {
        // Un circuit ou un trajet n'a qu'une date de départ : elle sert alors de
        // dernier jour connu.
        $dernierJour = $booking->end_date ?? $booking->start_date;

        if ($dernierJour !== null && $dernierJour->lt($aujourdhui)) {
            return BookingStatus::TERMINEE;
        }

        if ($booking->start_date !== null && $booking->start_date->lte($aujourdhui)) {
            return BookingStatus::EN_COURS;
        }

        return null;
    }
}
