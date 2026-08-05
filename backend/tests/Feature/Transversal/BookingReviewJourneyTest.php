<?php

namespace Tests\Feature\Transversal;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F8.15.a — le PARCOURS « je réserve, je consomme, je note », de bout en bout.
 *
 * POURQUOI CE FICHIER EXISTE À CÔTÉ DE `ReviewTest`
 * -------------------------------------------------
 * `ReviewTest` vérifie la couche des avis : une réservation `terminee` posée à
 * la main dans la base, puis le dépôt. Elle passait au vert alors que **rien
 * dans tout le produit ne rendait jamais une réservation `terminee`** — le
 * scénario que le test fabriquait ne pouvait pas se produire en vrai. C'est
 * exactement l'angle mort dénoncé en interne : chaque couche testée contre
 * elle-même, aucun test ne franchissant la frontière.
 *
 * Ici on ne pose donc AUCUN statut à la main : la réservation part `confirmee`
 * comme après un paiement, et c'est la mécanique du produit (tâche planifiée ou
 * check-out d'un agent) qui doit la mener jusqu'à l'avis.
 */
class BookingReviewJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Une réservation telle que le produit la crée : confirmée après paiement,
     * jamais `terminee` — c'est tout l'objet du test.
     */
    private function reservation(User $user, string $type, int $id, ?string $debut, ?string $fin): Booking
    {
        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => $type,
            'bookable_id' => $id,
            'start_date' => $debut,
            'end_date' => $fin,
            'guests' => 2,
            'amount_xof' => 120_000,
            'status' => BookingStatus::CONFIRMEE->value,
        ]);
    }

    // =========================================================================
    // Le parcours complet
    // =========================================================================

    public function test_de_la_reservation_confirmee_a_l_avis_depose(): void
    {
        $client = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // 1. Location terminée hier, réservation restée « confirmée ».
        $booking = $this->reservation(
            $client,
            Vehicle::class,
            $vehicle->id,
            now()->subDays(4)->toDateString(),
            now()->subDay()->toDateString(),
        );

        Sanctum::actingAs($client);

        // 2. Tant que la réservation n'a pas été clôturée, l'écran ne propose
        //    rien et le serveur refuse : les deux doivent dire la même chose.
        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.can_review', false);

        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'vehicle',
            'reviewable_id' => $vehicle->id,
            'rating' => 5,
        ])->assertStatus(403);

        // 3. La tâche planifiée passe : le service est achevé.
        $this->artisan('reservations:cloturer')->assertSuccessful();

        $this->assertSame(
            BookingStatus::TERMINEE,
            $booking->fresh()->status,
            'La commande doit clore une réservation confirmée dont la fin est passée.',
        );

        // 4. L'écran propose désormais de noter, et le couple à noter est servi
        //    par l'API (le front ne peut pas le déduire de l'id de réservation).
        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.can_review', true)
            ->assertJsonPath('data.booking.reviewable_type', 'vehicle')
            ->assertJsonPath('data.booking.reviewable_id', $vehicle->id);

        // 5. Le dépôt aboutit et part en modération.
        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'vehicle',
            'reviewable_id' => $vehicle->id,
            'rating' => 5,
            'comment' => 'Voiture impeccable, chauffeur ponctuel.',
        ])->assertCreated()->assertJsonPath('data.review.status', 'en_attente');

        // 6. « Mes avis » le voit ALORS QU'IL N'EST PAS PUBLIÉ — c'est toute sa
        //    raison d'être : sans lui, l'écran rouvrirait le formulaire et le
        //    client se heurterait au 422.
        $this->getJson('/api/v1/reviews/mine')
            ->assertOk()
            ->assertJsonPath('data.reviews.0.reviewable_type', 'vehicle')
            ->assertJsonPath('data.reviews.0.reviewable_id', $vehicle->id)
            ->assertJsonPath('data.reviews.0.status', 'en_attente');

        // La liste publique, elle, ne le montre pas encore.
        $this->getJson("/api/v1/reviews?reviewable_type=vehicle&reviewable_id={$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.count', 0);
    }

    public function test_mes_avis_ne_montrent_que_les_miens(): void
    {
        $client = User::factory()->create();
        $autre = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $booking = $this->reservation(
            $autre,
            Vehicle::class,
            $vehicle->id,
            now()->subDays(4)->toDateString(),
            now()->subDay()->toDateString(),
        );
        $this->artisan('reservations:cloturer');

        Sanctum::actingAs($autre);
        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'vehicle',
            'reviewable_id' => $vehicle->id,
            'rating' => 2,
        ])->assertCreated();

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/reviews/mine')
            ->assertOk()
            ->assertJsonCount(0, 'data.reviews');

        $this->assertNotNull($booking->fresh());
    }

    // =========================================================================
    // Le cycle de vie : ce que la commande avance, et ce qu'elle épargne
    // =========================================================================

    public function test_une_reservation_impayee_n_est_jamais_cloturee(): void
    {
        $client = User::factory()->create();
        $stay = Stay::factory()->create();

        $booking = $this->reservation(
            $client,
            Stay::class,
            $stay->id,
            now()->subWeek()->toDateString(),
            now()->subDays(3)->toDateString(),
        );
        // Jamais réglée : elle en est restée à l'état de création.
        $booking->update(['status' => BookingStatus::EN_ATTENTE->value]);

        $this->artisan('reservations:cloturer')->assertSuccessful();

        $this->assertSame(
            BookingStatus::EN_ATTENTE,
            $booking->fresh()->status,
            "Un séjour jamais payé n'est pas un séjour consommé : le clore donnerait le droit de le noter.",
        );

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'stay',
            'reviewable_id' => $stay->id,
            'rating' => 5,
        ])->assertStatus(403);
    }

    public function test_une_reservation_annulee_est_epargnee(): void
    {
        $client = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $booking = $this->reservation(
            $client,
            Vehicle::class,
            $vehicle->id,
            now()->subDays(4)->toDateString(),
            now()->subDay()->toDateString(),
        );
        $booking->update(['status' => BookingStatus::ANNULEE_CLIENT->value]);

        $this->artisan('reservations:cloturer')->assertSuccessful();

        $this->assertSame(BookingStatus::ANNULEE_CLIENT, $booking->fresh()->status);
    }

    public function test_un_service_en_cours_passe_en_cours_mais_pas_terminee(): void
    {
        $client = User::factory()->create();
        $stay = Stay::factory()->create();

        $booking = $this->reservation(
            $client,
            Stay::class,
            $stay->id,
            now()->subDay()->toDateString(),
            now()->addDays(3)->toDateString(),
        );

        $this->artisan('reservations:cloturer')->assertSuccessful();

        $this->assertSame(BookingStatus::EN_COURS, $booking->fresh()->status);

        // Et donc : toujours pas d'avis possible.
        Sanctum::actingAs($client);
        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'stay',
            'reviewable_id' => $stay->id,
            'rating' => 4,
        ])->assertStatus(403);
    }

    public function test_un_circuit_sans_date_de_fin_se_cloture_sur_sa_date_de_depart(): void
    {
        $client = User::factory()->create();
        $experience = TourismExperience::factory()->create();

        // Un circuit n'a qu'une date de départ : `end_date` est nulle.
        $booking = $this->reservation(
            $client,
            TourismExperience::class,
            $experience->id,
            now()->subDays(2)->toDateString(),
            null,
        );

        $this->artisan('reservations:cloturer')->assertSuccessful();

        $this->assertSame(BookingStatus::TERMINEE, $booking->fresh()->status);
    }

    public function test_une_prestation_sur_mesure_sans_dates_reste_confirmee(): void
    {
        $client = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // Cas des devis acceptés (chantier, séminaire) : aucune date au contrat.
        $booking = $this->reservation($client, Vehicle::class, $vehicle->id, null, null);

        $this->artisan('reservations:cloturer')->assertSuccessful();

        $this->assertSame(
            BookingStatus::CONFIRMEE,
            $booking->fresh()->status,
            'Sans dates, la fin du service se constate au dossier, pas au calendrier.',
        );
    }

    public function test_la_commande_est_idempotente(): void
    {
        $client = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $booking = $this->reservation(
            $client,
            Vehicle::class,
            $vehicle->id,
            now()->subDays(4)->toDateString(),
            now()->subDay()->toDateString(),
        );

        $this->artisan('reservations:cloturer')->assertSuccessful();
        $premier = $booking->fresh()->updated_at;

        $this->artisan('reservations:cloturer')->assertSuccessful();

        $this->assertSame(BookingStatus::TERMINEE, $booking->fresh()->status);
        $this->assertEquals(
            $premier,
            $booking->fresh()->updated_at,
            'Un second passage ne doit pas réécrire une réservation déjà dans le bon état.',
        );
    }

    // =========================================================================
    // Le départ enregistré par un agent clôt le séjour sans attendre la tâche
    // =========================================================================

    public function test_le_check_out_cloture_la_reservation_et_ouvre_l_avis(): void
    {
        $client = User::factory()->create();
        $stay = Stay::factory()->create();

        // Séjour en cours : la tâche planifiée ne le clôturerait pas aujourd'hui.
        $booking = $this->reservation(
            $client,
            Stay::class,
            $stay->id,
            now()->subDay()->toDateString(),
            now()->addDays(2)->toDateString(),
        );

        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational());
        Sanctum::actingAs($agent);

        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/check-in")->assertOk();
        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/check-out")
            ->assertOk()
            ->assertJsonPath('data.booking.status', BookingStatus::TERMINEE->value);

        // Le client peut noter dès son départ, sans attendre la nuit.
        Sanctum::actingAs($client);
        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'stay',
            'reviewable_id' => $stay->id,
            'rating' => 4,
            'comment' => 'Logement conforme aux photos.',
        ])->assertCreated();
    }
}
