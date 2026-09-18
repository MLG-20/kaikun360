<?php

namespace Tests\Feature\Explore;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Explore\Enums\ExperienceStatus;
use App\Modules\Explore\Models\TourismExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la couche de données des expériences touristiques (phase B6.1,
 * revue F21) : casts (itinéraire/statut), relation prestataire, dates de
 * départ, réservations polymorphes et scope de publication.
 */
class TourismExperienceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_experience_se_cree_avec_ses_casts(): void
    {
        $experience = TourismExperience::factory()->create([
            'itinerary' => [['day' => 1, 'title' => 'Arrivée', 'description' => 'Accueil à l\'aéroport']],
            'included' => 'Guide francophone',
            'excluded' => 'Boissons',
        ]);

        $experience->refresh();

        $this->assertSame(ExperienceStatus::EN_ATTENTE_VALIDATION, $experience->status);
        $this->assertIsArray($experience->itinerary);
        $this->assertSame('Arrivée', $experience->itinerary[0]['title']);
        $this->assertSame('Guide francophone', $experience->included);
        $this->assertSame('Boissons', $experience->excluded);
    }

    public function test_une_experience_a_des_dates_de_depart_avec_leurs_propres_places(): void
    {
        $experience = TourismExperience::factory()->withDepartures(3)->create();

        $this->assertCount(3, $experience->departures);
        $this->assertGreaterThan(0, $experience->departures->first()->seats_total);
    }

    public function test_une_experience_appartient_a_un_prestataire(): void
    {
        $provider = User::factory()->create();
        $experience = TourismExperience::factory()->create(['provider_id' => $provider->id]);

        $this->assertTrue($experience->provider->is($provider));
    }

    public function test_une_experience_a_des_reservations_polymorphes(): void
    {
        $experience = TourismExperience::factory()->create();
        Booking::create([
            'reference' => 'BK-TEST-1',
            'user_id' => User::factory()->create()->id,
            'bookable_type' => TourismExperience::class,
            'bookable_id' => $experience->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'guests' => 2,
            'amount_xof' => 100_000,
            'status' => 'en_attente',
        ]);

        $this->assertCount(1, $experience->bookings);
        $this->assertInstanceOf(TourismExperience::class, $experience->bookings->first()->bookable);
    }

    public function test_le_scope_published_ne_remonte_que_les_publiees(): void
    {
        TourismExperience::factory()->published()->count(2)->create();
        TourismExperience::factory()->create(); // en attente

        $this->assertCount(2, TourismExperience::query()->published()->get());
    }
}
