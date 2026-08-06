<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\PartnerDueStatus;
use App\Models\Booking;
use App\Models\PartnerDue;
use App\Modules\Pro\Enums\MissionStatus;
use App\Modules\Pro\Models\ProviderMission;
use App\Support\Payouts\PartnerDueRegistrar;
use Illuminate\Console\Command;

/**
 * Alimente le registre des reversements aux partenaires (F8.16.a).
 *
 * POURQUOI CETTE COMMANDE EXISTE
 * ------------------------------
 * Kaikun encaisse et prélève sa commission sur **tous** les univers depuis F8.4,
 * mais ne reversait qu'en gestion locative : `owner_payouts.mandate_id` est non
 * nullable, la table ne peut structurellement pas porter le reversement d'un
 * hôte, d'un loueur de véhicule ou d'un organisateur de circuit. Résultat :
 * **si un partenaire demandait ce que Kaikun lui devait, personne ne pouvait
 * répondre.**
 *
 * CE QU'ELLE FAIT
 * ---------------
 * Deux passes, dans cet ordre :
 *   1. **Inscrire** les dettes nées des services rendus — réservations
 *      `terminee` et missions prestataires `terminee` — qui n'en ont pas encore.
 *   2. **Rendre exigibles** celles dont le délai de sûreté est écoulé
 *      (`PartnerDueRegistrar::DELAI_JOURS`).
 *
 * ⚠️ **Elle ne paie RIEN et ne déclenche aucun virement.** Elle constate une
 * dette ; c'est un agent qui prépare le versement au back-office et pointe le
 * justificatif. Aucun argent ne bouge sans un clic humain — c'est le choix
 * assumé de cette tranche.
 *
 * ⚠️ **Elle dépend de `reservations:cloturer`** (F8.15.a) : sans elle, aucune
 * réservation n'atteint `terminee`, donc aucune dette n'existe. Les deux
 * supposent un cron `schedule:run` en production, et l'absence de ce cron est
 * **silencieuse** dans les deux cas.
 *
 * SANS RISQUE À REJOUER : l'inscription est idempotente (unique en base sur la
 * source), et une dette déjà payée ou annulée n'est jamais réanimée.
 */
class CalculatePartnerDuesCommand extends Command
{
    protected $signature = 'reversements:calculer {--dry-run : Montre ce qui serait inscrit, sans rien écrire}';

    protected $description = 'Inscrit au registre ce que Kaikun doit aux partenaires et rend exigibles les dettes échues (F8.16.a)';

    public function handle(PartnerDueRegistrar $registrar): int
    {
        $simulation = (bool) $this->option('dry-run');

        if ($simulation) {
            $this->warn('Mode simulation : aucune écriture.');
        }

        $inscrites = $this->inscrireReservations($registrar, $simulation);
        $inscrites += $this->inscrireMissions($registrar, $simulation);

        // La promotion ne se simule pas ligne à ligne : on annonce le volume.
        $exigibles = $simulation
            ? $this->compterPromouvables()
            : $registrar->promoteEligibles();

        $this->info(sprintf(
            '%d dette(s) inscrite(s), %d devenue(s) exigible(s).',
            $inscrites,
            $exigibles,
        ));

        return self::SUCCESS;
    }

    /**
     * Réservations terminées sans dette au registre.
     *
     * ⚠️ On ne filtre pas ici les univers sans bénéficiaire (devis sur mesure) :
     * c'est le registre qui tranche, pour que la règle vive à un seul endroit.
     * Il renvoie simplement `null` et la réservation est comptée comme ignorée.
     */
    private function inscrireReservations(PartnerDueRegistrar $registrar, bool $simulation): int
    {
        $inscrites = 0;

        Booking::query()
            ->where('status', BookingStatus::TERMINEE->value)
            ->whereDoesntHave('partnerDue')
            ->with('bookable')
            ->chunkById(200, function ($lot) use ($registrar, $simulation, &$inscrites) {
                foreach ($lot as $booking) {
                    if ($simulation) {
                        $inscrites += $this->simulerReservation($booking) ? 1 : 0;

                        continue;
                    }

                    if ($registrar->registerForBooking($booking) !== null) {
                        $this->line("  Réservation {$booking->reference} → dette inscrite");
                        $inscrites++;
                    }
                }
            });

        return $inscrites;
    }

    /** Missions prestataires terminées sans dette au registre. */
    private function inscrireMissions(PartnerDueRegistrar $registrar, bool $simulation): int
    {
        $inscrites = 0;

        ProviderMission::query()
            ->where('status', MissionStatus::TERMINEE->value)
            ->whereDoesntHave('partnerDue')
            ->with('provider')
            ->chunkById(200, function ($lot) use ($registrar, $simulation, &$inscrites) {
                foreach ($lot as $mission) {
                    if ($simulation) {
                        $inscrites += ($mission->amount_xof > 0 && $mission->provider?->user_id) ? 1 : 0;

                        continue;
                    }

                    if ($registrar->registerForMission($mission) !== null) {
                        $this->line("  Mission {$mission->reference} → dette inscrite");
                        $inscrites++;
                    }
                }
            });

        return $inscrites;
    }

    /** En simulation : cette réservation produirait-elle une dette ? */
    private function simulerReservation(Booking $booking): bool
    {
        return $booking->amount_xof > 0 && $booking->bookable !== null;
    }

    /** Volume de dettes que la promotion ferait basculer (mode simulation). */
    private function compterPromouvables(): int
    {
        return PartnerDue::query()
            ->where('status', PartnerDueStatus::EN_ATTENTE->value)
            ->whereNotNull('eligible_at')
            ->where('eligible_at', '<=', now())
            ->count();
    }
}
