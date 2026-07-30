<?php

namespace Tests\Feature\Admin;

use App\Enums\CautionStatus;
use App\Models\Booking;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests de la CAUTION des nuitées (F7.3.f — CDC §6 module *Nuitées*).
 *
 * La caution était recopiée sur la réservation mais jamais suivie : son statut
 * restait `null` pour un séjour, là où la location de véhicule le renseigne depuis
 * B7.4. Elle est désormais retenue à la réservation (module Stay) puis tranchée au
 * départ depuis le back-office.
 */
class StayCautionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    /** Réservation de nuitée, caution retenue, séjour terminé (départ enregistré). */
    private function bookingWithCaution(array $overrides = []): Booking
    {
        $stay = Stay::factory()->create(['caution_xof' => 100_000]);

        return Booking::create(array_merge([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today()->subDays(3),
            'end_date' => today()->subDay(),
            'guests' => 2,
            'amount_xof' => 50_000,
            'caution_xof' => 100_000,
            'caution_status' => CautionStatus::RETENUE->value,
            'checked_in_at' => now()->subDays(3),
            'checked_out_at' => now()->subDay(),
            'status' => 'confirmee',
        ], $overrides));
    }

    public function test_une_reservation_de_nuitee_retient_la_caution_des_le_depart(): void
    {
        $stay = Stay::factory()->create(['caution_xof' => 75_000, 'min_nights' => 1]);
        $client = User::factory()->create();
        Sanctum::actingAs($client);

        $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => today()->addDays(10)->toDateString(),
            'end_date' => today()->addDays(12)->toDateString(),
            'guests' => 1,
        ])->assertCreated();

        $this->assertDatabaseHas('bookings', [
            'bookable_id' => $stay->id,
            'caution_xof' => 75_000,
            'caution_status' => CautionStatus::RETENUE->value,
        ]);
    }

    public function test_un_logement_sans_caution_ne_porte_aucun_statut(): void
    {
        $stay = Stay::factory()->create(['caution_xof' => 0, 'min_nights' => 1]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => today()->addDays(10)->toDateString(),
            'end_date' => today()->addDays(11)->toDateString(),
            'guests' => 1,
        ])->assertCreated();

        // `null` et non « retenue » : il n'y a rien à rendre.
        $this->assertDatabaseHas('bookings', [
            'bookable_id' => $stay->id,
            'caution_status' => null,
        ]);
    }

    public function test_un_agent_restitue_la_caution_apres_le_depart(): void
    {
        $booking = $this->bookingWithCaution();

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/caution", [
            'status' => CautionStatus::RESTITUEE->value,
        ])->assertOk()
            ->assertJsonPath('data.booking.caution_status', CautionStatus::RESTITUEE->value);

        $this->assertSame(CautionStatus::RESTITUEE, $booking->fresh()->caution_status);
    }

    public function test_conserver_la_caution_exige_un_motif(): void
    {
        $booking = $this->bookingWithCaution();

        Sanctum::actingAs($this->agent());

        // Sans motif : refusé — une caution perdue se justifie.
        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/caution", [
            'status' => CautionStatus::PERDUE->value,
        ])->assertStatus(422);

        $this->assertSame(CautionStatus::RETENUE, $booking->fresh()->caution_status);

        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/caution", [
            'status' => CautionStatus::PERDUE->value,
            'reason' => 'Porte de la chambre endommagée, devis 60 000 F.',
        ])->assertOk();

        $this->assertSame(CautionStatus::PERDUE, $booking->fresh()->caution_status);
    }

    public function test_la_decision_est_tracee_au_journal_d_audit(): void
    {
        $booking = $this->bookingWithCaution();

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/caution", [
            'status' => CautionStatus::PERDUE->value,
            'reason' => 'Vaisselle cassée',
        ])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'description' => 'Caution conservée',
        ]);
    }

    public function test_la_caution_ne_se_tranche_pas_avant_le_depart(): void
    {
        // Client encore sur place : l'état des lieux n'a pas été fait.
        $booking = $this->bookingWithCaution(['checked_out_at' => null]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/caution", [
            'status' => CautionStatus::RESTITUEE->value,
        ])->assertStatus(422);

        $this->assertSame(CautionStatus::RETENUE, $booking->fresh()->caution_status);
    }

    public function test_une_caution_deja_tranchee_ne_se_rejoue_pas(): void
    {
        $booking = $this->bookingWithCaution([
            'caution_status' => CautionStatus::RESTITUEE->value,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/caution", [
            'status' => CautionStatus::PERDUE->value,
            'reason' => 'Changement d’avis',
        ])->assertStatus(422);

        $this->assertSame(CautionStatus::RESTITUEE, $booking->fresh()->caution_status);
    }

    public function test_une_reservation_sans_caution_est_refusee(): void
    {
        $booking = $this->bookingWithCaution([
            'caution_xof' => 0,
            'caution_status' => null,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/caution", [
            'status' => CautionStatus::RESTITUEE->value,
        ])->assertStatus(422);
    }

    public function test_le_calendrier_expose_la_caution(): void
    {
        $this->bookingWithCaution();

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/stays/calendar')
            ->assertOk()
            ->assertJsonPath('data.0.caution_xof', 100_000)
            ->assertJsonPath('data.0.caution_status', CautionStatus::RETENUE->value);
    }

    public function test_un_compte_sans_gerer_nuitees_ne_tranche_pas_la_caution(): void
    {
        $booking = $this->bookingWithCaution();

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/caution", [
            'status' => CautionStatus::RESTITUEE->value,
        ])->assertStatus(403);
    }
}
