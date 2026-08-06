<?php

namespace App\Support\Payouts;

use App\Enums\BookingStatus;
use App\Enums\PartnerDueStatus;
use App\Models\Booking;
use App\Models\PartnerDue;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Enums\MissionStatus;
use App\Modules\Pro\Models\ProviderMission;
use App\Modules\Stay\Models\Stay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inscrit au registre ce que Kaikun doit à ses partenaires (F8.16.a).
 *
 * **Source unique de la règle** : c'est ici, et nulle part ailleurs, qu'on
 * décide qui est le bénéficiaire, quelle est l'assiette et à partir de quand la
 * dette est payable. Le reste du produit (commande planifiée, remboursement,
 * back-office) ne fait qu'appeler ce service.
 *
 * ⚠️ **Rien n'est jamais payé automatiquement.** Ce service *constate* une
 * dette ; c'est un agent qui prépare le versement et le déclenche.
 */
class PartnerDueRegistrar
{
    /**
     * Délai de sûreté entre la fin du service et l'exigibilité, en jours.
     *
     * ⚠️ **7 jours, aligné sur le plus long délai d'annulation du produit**
     * (`ExperienceCancellationService::REFUND_DELAY_DAYS`). Reverser avant, ce
     * serait risquer de payer un partenaire puis de devoir rembourser le client :
     * l'argent est alors sorti et il faut le réclamer. Le délai court sur la
     * **fin du service**, pas sur l'instant du calcul — un traitement lancé en
     * retard ne doit pas retarder le partenaire.
     */
    public const DELAI_JOURS = 7;

    /**
     * Inscrit (ou met à jour) la dette née d'une réservation.
     *
     * Renvoie `null` quand il n'y a rien à devoir : service non rendu, montant
     * nul, ou réservation dont **aucun partenaire unique** n'est le bénéficiaire
     * (les devis sur mesure — voir `beneficiaireDe()`).
     */
    public function registerForBooking(Booking $booking): ?PartnerDue
    {
        if ($booking->status !== BookingStatus::TERMINEE) {
            return null;
        }

        $beneficiaire = $this->beneficiaireDe($booking);

        if ($beneficiaire === null) {
            return null;
        }

        // ⚠️ L'assiette est `amount_xof`, JAMAIS `caution_xof` : la caution est
        // retenue puis restituée ou saisie, elle n'a jamais appartenu au
        // partenaire. L'inclure reviendrait à lui reverser l'argent du client.
        $brut = (int) $booking->amount_xof;

        if ($brut <= 0) {
            return null;
        }

        return $this->inscrire(
            source: $booking,
            beneficiaryId: $beneficiaire,
            brut: $brut,
            // ⚠️ Commission RECOPIÉE, jamais recalculée : elle est figée sur la
            // réservation depuis F8.4. La recalculer ferait dépendre une dette
            // passée du barème d'aujourd'hui.
            commission: (int) ($booking->commission_xof ?? 0),
            finDeService: $booking->end_date ?? $booking->start_date,
        );
    }

    /**
     * Inscrit la dette née d'une mission prestataire.
     *
     * ⚠️ **C'est par ici que passent le team building et la construction.** Leur
     * devis est un total « coûts + marge » qui ne dit rien de ce qui revient à
     * chaque intervenant : un séminaire peut devoir de l'argent à quatre
     * prestataires. Reverser depuis le devis serait faux ; ce qui est dû vit
     * mission par mission, telle qu'elle a été chiffrée à l'affectation.
     */
    public function registerForMission(ProviderMission $mission): ?PartnerDue
    {
        if ($mission->status !== MissionStatus::TERMINEE) {
            return null;
        }

        // Le bénéficiaire est l'UTILISATEUR derrière le prestataire :
        // `provider_missions.provider_id` pointe sur `providers` (contrairement
        // aux véhicules, circuits et trajets, dont le `provider_id` pointe
        // directement sur `users`). C'est la seule indirection du registre.
        $mission->loadMissing('provider');
        $beneficiaire = $mission->provider?->user_id;

        if ($beneficiaire === null) {
            return null;
        }

        $brut = (int) $mission->amount_xof;

        if ($brut <= 0) {
            return null;
        }

        return $this->inscrire(
            source: $mission,
            beneficiaryId: (int) $beneficiaire,
            brut: $brut,
            commission: (int) ($mission->commission_xof ?? 0),
            // Repli sur `updated_at` pour les missions terminées AVANT cette
            // livraison : elles n'ont pas de `completed_at`, et les priver de
            // date les rendrait éternellement inexigibles.
            finDeService: $mission->completed_at ?? $mission->updated_at,
        );
    }

    /**
     * Qui doit être payé pour cette réservation ? `null` si personne d'unique.
     *
     * ⚠️ **Deux natures de bénéficiaire, un seul type** : propriétaire d'un bien
     * ou prestataire, et dans les deux cas un `User`. Les `provider_id` des
     * véhicules, circuits et trajets référencent `users` directement (vérifié
     * dans les migrations) — pas besoin de passer par `providers`.
     *
     * ⚠️ **Un devis accepté (team building, construction, sur mesure) ne
     * désigne aucun partenaire** : il est le contrat entre Kaikun et son client.
     * Ce qui est dû aux intervenants passe par leurs missions. Renvoyer `null`
     * ici n'est donc pas un trou, c'est la règle.
     */
    private function beneficiaireDe(Booking $booking): ?int
    {
        $booking->loadMissing('bookable');
        $cible = $booking->bookable;

        return match (true) {
            // Une nuitée appartient au bien, et le bien à son propriétaire.
            $cible instanceof Stay => $cible->property?->owner_id,
            $cible instanceof Vehicle,
            $cible instanceof TourismExperience,
            $cible instanceof MobilityService => $cible->provider_id,
            default => null,
        };
    }

    /**
     * Crée la dette si elle n'existe pas encore, sinon la laisse telle quelle.
     *
     * ⚠️ **Idempotent, et garanti par la BASE** : `partner_dues` porte un unique
     * sur (`source_type`, `source_id`). Sans lui, deux exécutions concurrentes du
     * calcul créeraient deux dettes pour le même service et le partenaire serait
     * payé deux fois. Le `firstOrCreate` seul ne suffirait pas.
     *
     * ⚠️ **Une dette déjà payée ou annulée n'est jamais réanimée** : on ne
     * touche à rien si la ligne existe. Rejouer le calcul est donc sans risque.
     */
    private function inscrire(
        Model $source,
        int $beneficiaryId,
        int $brut,
        int $commission,
        ?Carbon $finDeService,
    ): PartnerDue {
        // Le net ne peut pas être négatif : une commission supérieure au montant
        // signalerait une anomalie de saisie, on ne la transforme pas en dette
        // du partenaire envers Kaikun (ce serait une créance, autre sujet).
        $net = max(0, $brut - $commission);

        $exigibleLe = ($finDeService ? $finDeService->copy() : now())
            ->addDays(self::DELAI_JOURS);

        return DB::transaction(fn () => PartnerDue::query()->firstOrCreate(
            [
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
            ],
            [
                'reference' => 'DU-'.Str::upper(Str::random(8)),
                'beneficiary_id' => $beneficiaryId,
                'gross_xof' => $brut,
                'commission_xof' => $commission,
                'net_xof' => $net,
                'status' => $exigibleLe->isPast()
                    ? PartnerDueStatus::EXIGIBLE->value
                    : PartnerDueStatus::EN_ATTENTE->value,
                'eligible_at' => $exigibleLe,
            ],
        ));
    }

    /**
     * Fait passer à « exigible » les dettes dont le délai est écoulé.
     *
     * @return int nombre de dettes devenues exigibles
     */
    public function promoteEligibles(): int
    {
        return PartnerDue::query()
            ->where('status', PartnerDueStatus::EN_ATTENTE->value)
            ->whereNotNull('eligible_at')
            ->where('eligible_at', '<=', now())
            ->update(['status' => PartnerDueStatus::EXIGIBLE->value]);
    }

    /**
     * Éteint la dette née de cette source (remboursement, annulation).
     *
     * ⚠️ **Une dette DÉJÀ PAYÉE n'est pas annulée** : l'argent est parti. La
     * ligne reste « payée » et l'écart devient une **créance de Kaikun sur le
     * partenaire**, à régler hors application — la marquer annulée ferait
     * disparaître des comptes un virement bien réel.
     *
     * ⚠️ **Annulée, jamais supprimée** : le motif reste lisible, et le calcul
     * ne recrée pas la dette au passage suivant (la ligne existe toujours).
     *
     * @return bool vrai si une dette vivante a effectivement été éteinte
     */
    public function cancelForSource(Model $source, string $raison): bool
    {
        $due = PartnerDue::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->first();

        if ($due === null || ! $due->status->estVivante()) {
            return false;
        }

        $due->update([
            'status' => PartnerDueStatus::ANNULEE->value,
            'cancelled_reason' => $raison,
            'cancelled_at' => now(),
            // Une dette annulée quitte le lot où elle attendait : sinon le
            // montant du versement ne correspondrait plus à ce qu'il solde.
            'payout_id' => null,
        ]);

        return true;
    }
}
