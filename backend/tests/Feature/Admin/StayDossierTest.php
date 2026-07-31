<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F8.2.a : **fiche d'un séjour** dans le back-office.
 *
 * Le calendrier dit qui arrive quand ; la fiche rassemble le séjour d'un seul
 * tenant — logement + hôte, client, argent (encaissé / reste à payer, paiements
 * un par un) et journal d'audit, où figure le motif d'une caution conservée.
 *
 * Deux exigences tenues ici : la fiche est réservée à `gerer:nuitees` comme le
 * reste du module, et elle **survit à la disparition du bien** — le séjour a eu
 * lieu, son dossier doit rester consultable.
 */
class StayDossierTest extends TestCase
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

    private function stayBooking(?Stay $stay = null, ?User $client = null): Booking
    {
        $stay ??= Stay::factory()->create();

        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => ($client ?? User::factory()->create())->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDays(3),
            'guests' => 2,
            'amount_xof' => 90_000,
            'commission_xof' => 9_000,
            'caution_xof' => 50_000,
            'caution_status' => 'retenue',
            'status' => 'confirmee',
        ]);
    }

    public function test_un_utilisateur_sans_gerer_nuitees_est_refuse(): void
    {
        $booking = $this->stayBooking();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/admin/stay-bookings/{$booking->id}")->assertStatus(403);
    }

    public function test_la_fiche_rassemble_sejour_logement_client_et_argent(): void
    {
        $stay = Stay::factory()->create(['capacity' => 4]);
        $client = User::factory()->create(['name' => 'Awa Diop']);
        $booking = $this->stayBooking($stay, $client);

        // Un acompte encaissé : la fiche doit en déduire le reste à payer.
        Payment::create([
            'reference' => 'PAY-'.uniqid(),
            'booking_id' => $booking->id,
            'provider' => 'paytech',
            'amount_xof' => 30_000,
            'commission_xof' => 0,
            'kind' => 'acompte',
            'status' => 'complete',
            'mode' => 'wave',
        ]);

        Sanctum::actingAs($this->agent());

        $response = $this->getJson("/api/v1/admin/stay-bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.booking_id', $booking->id)
            ->assertJsonPath('data.booking.nights', 3)
            ->assertJsonPath('data.booking.guests', 2)
            ->assertJsonPath('data.booking.amount_xof', 90_000)
            ->assertJsonPath('data.booking.paid_xof', 30_000)
            ->assertJsonPath('data.booking.remaining_xof', 60_000)
            ->assertJsonPath('data.booking.caution_status', 'retenue')
            ->assertJsonPath('data.client.name', 'Awa Diop')
            ->assertJsonPath('data.stay.stay_id', $stay->id)
            ->assertJsonPath('data.stay.capacity', 4)
            ->assertJsonPath('data.stay.property_title', $stay->property->title)
            ->assertJsonPath('data.stay.host.id', $stay->property->owner_id);

        // Les paiements sont détaillés un par un (l'agent répond « oui, votre
        // acompte est bien arrivé » sans changer d'écran).
        $response->assertJsonPath('data.payments.0.amount_xof', 30_000)
            ->assertJsonPath('data.payments.0.kind_label', 'Acompte')
            ->assertJsonPath('data.payments.0.status_label', 'Complété');
    }

    public function test_le_journal_porte_le_motif_de_la_caution_conservee(): void
    {
        $booking = $this->stayBooking();
        $booking->update([
            'checked_in_at' => now()->subDays(2),
            'checked_out_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/caution", [
            'status' => 'perdue',
            'reason' => 'Vitre brisée dans le salon',
        ])->assertOk();

        $this->getJson("/api/v1/admin/stay-bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.caution_status', 'perdue')
            ->assertJsonPath('data.activity.0.description', 'Caution conservée')
            ->assertJsonPath('data.activity.0.properties.reason', 'Vitre brisée dans le salon');
    }

    public function test_la_fiche_survit_a_la_disparition_du_bien(): void
    {
        $stay = Stay::factory()->create();
        $booking = $this->stayBooking($stay);
        $stay->delete();

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/stay-bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.booking.booking_id', $booking->id)
            ->assertJsonPath('data.stay', null);
    }

    public function test_la_fiche_refuse_une_reservation_qui_nest_pas_une_nuitee(): void
    {
        $vehicle = Vehicle::factory()->create();
        $booking = Booking::create([
            'reference' => 'BK-VEH-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Vehicle::class,
            'bookable_id' => $vehicle->id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 1,
            'amount_xof' => 30_000,
            'status' => 'confirmee',
        ]);

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/stay-bookings/{$booking->id}")->assertStatus(422);
    }
}
