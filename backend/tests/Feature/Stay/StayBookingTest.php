<?php

namespace Tests\Feature\Stay;

use App\Enums\BookingStatus;
use App\Models\User;
use App\Modules\Stay\Models\Stay;
use App\Support\Settings;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests de la disponibilité et de la réservation de nuitées (phase B3.3).
 *
 * Point capital : impossible de réserver un créneau déjà occupé.
 */
class StayBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    private function nuitee(array $surcharge = []): Stay
    {
        return Stay::factory()->create(array_merge([
            'capacity' => 4,
            'price_per_night_xof' => 20_000,
            'caution_xof' => 50_000,
            'min_nights' => 1,
            'max_nights' => null,
        ], $surcharge));
    }

    /** Crée une réservation existante (non annulée) sur un créneau. */
    private function reserverCreneau(Stay $stay, string $start, string $end): void
    {
        $stay->bookings()->create([
            'reference' => 'BK-EXISTANT-'.fake()->unique()->numerify('####'),
            'user_id' => User::factory()->create()->id,
            'start_date' => $start,
            'end_date' => $end,
            'guests' => 2,
            'amount_xof' => 40_000,
            'caution_xof' => 50_000,
            'status' => BookingStatus::CONFIRMEE->value,
        ]);
    }

    public function test_un_utilisateur_peut_reserver_des_dates_libres(): void
    {
        $stay = $this->nuitee();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(), // 3 nuits
            'guests' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('data.booking.status', 'en_attente')
            ->assertJsonPath('data.booking.amount_xof', 60_000)   // 3 × 20 000
            ->assertJsonPath('data.booking.caution_xof', 50_000);

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_impossible_de_reserver_un_creneau_qui_chevauche(): void
    {
        $stay = $this->nuitee();
        $this->reserverCreneau($stay, now()->addDays(5)->toDateString(), now()->addDays(8)->toDateString());

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => now()->addDays(6)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
            'guests' => 2,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_les_creneaux_adjacents_sont_autorises(): void
    {
        $stay = $this->nuitee();
        // Occupé du J+5 au J+8 (départ exclusif).
        $this->reserverCreneau($stay, now()->addDays(5)->toDateString(), now()->addDays(8)->toDateString());

        Sanctum::actingAs(User::factory()->create());

        // Arrivée le jour du départ précédent → pas de chevauchement.
        $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => now()->addDays(8)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'guests' => 2,
        ])->assertCreated();
    }

    public function test_la_capacite_est_respectee(): void
    {
        $stay = $this->nuitee(['capacity' => 2]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'guests' => 5,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['guests']);
    }

    public function test_le_sejour_minimum_est_respecte(): void
    {
        $stay = $this->nuitee(['min_nights' => 3]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(), // 1 nuit
            'guests' => 2,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_la_disponibilite_liste_les_creneaux_occupes(): void
    {
        $stay = $this->nuitee();
        $this->reserverCreneau($stay, now()->addDays(5)->toDateString(), now()->addDays(8)->toDateString());

        $this->getJson("/api/v1/stays/{$stay->id}/availability")
            ->assertOk()
            ->assertJsonCount(1, 'data.booked')
            ->assertJsonPath('data.booked.0.start_date', now()->addDays(5)->toDateString());
    }

    /**
     * F8.4 — la commission plateforme est FIGÉE à la réservation.
     *
     * Elle ne l'était pas : la colonne restait à 0 sur les nuitées, donc
     * l'export comptable et le tableau de bord sous-estimaient le revenu réel.
     */
    public function test_la_commission_plateforme_est_calculee_et_figee(): void
    {
        $stay = $this->nuitee();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(), // 3 nuits × 20 000
            'guests' => 2,
        ])->assertCreated();

        // 12 % (taux de repli) de 60 000 = 7 200.
        $this->assertDatabaseHas('bookings', ['amount_xof' => 60_000, 'commission_xof' => 7_200]);
    }

    public function test_le_taux_de_commission_suit_le_reglage_du_back_office(): void
    {
        // Rien n'est codé en dur : la direction fixe le taux depuis les Paramètres.
        Settings::set('commission.default_rate', 20.0);

        $stay = $this->nuitee();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'guests' => 2,
        ])->assertCreated();

        $this->assertDatabaseHas('bookings', ['commission_xof' => 12_000]); // 20 % de 60 000
    }

    /**
     * La caution est un dépôt rendu au client, pas un revenu : elle ne doit
     * jamais entrer dans l'assiette de commission.
     */
    public function test_la_caution_n_entre_pas_dans_l_assiette_de_commission(): void
    {
        $stay = $this->nuitee(['caution_xof' => 500_000]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(), // 1 nuit = 20 000
            'guests' => 2,
        ])->assertCreated();

        // 12 % de 20 000 seulement, la caution de 500 000 est ignorée.
        $this->assertDatabaseHas('bookings', ['commission_xof' => 2_400]);
    }

    public function test_la_reservation_exige_une_authentification(): void
    {
        $stay = $this->nuitee();

        $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'guests' => 2,
        ])->assertStatus(401);
    }
}
