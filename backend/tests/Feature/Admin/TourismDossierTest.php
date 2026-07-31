<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Pro\Models\Provider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F8.2.c : **fiches de l'écran Tourisme** — un circuit, un partenaire.
 *
 * Le tableau dit qu'un circuit est rempli à 12/15 ; la fiche dit **qui part** et
 * ce que le circuit promet. La liste des partenaires affiche une note et un
 * compteur d'avertissements ; la fiche donne les **avis en clair** — 3,2 sur
 * quarante avis et 3,2 sur deux ne se décident pas pareil.
 */
class TourismDossierTest extends TestCase
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
        // La fiche partenaire est gardée par `valider:prestataire`, une
        // permission déléguée (F7.1.b) et non portée par le rôle.
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    private function booking(int $experienceId, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => TourismExperience::class,
            'bookable_id' => $experienceId,
            'start_date' => today()->addWeek(),
            'end_date' => today()->addWeek()->addDays(2),
            'guests' => 2,
            'amount_xof' => 80_000,
            'status' => 'confirmee',
        ], $overrides));
    }

    // --- Circuit ----------------------------------------------------------------

    public function test_un_utilisateur_sans_acces_back_office_est_refuse(): void
    {
        $experience = TourismExperience::factory()->create();
        $provider = Provider::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/admin/experiences/{$experience->id}")->assertStatus(403);
        $this->getJson("/api/v1/admin/providers/{$provider->id}")->assertStatus(403);
    }

    public function test_la_fiche_circuit_donne_le_programme_et_les_participants(): void
    {
        $experience = TourismExperience::factory()->create([
            'capacity' => 15,
            'inclusions' => ['guide', 'restauration'],
        ]);

        $client = User::factory()->create(['name' => 'Ndeye Ba']);
        $this->booking($experience->id, ['user_id' => $client->id, 'guests' => 3]);

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/experiences/{$experience->id}")
            ->assertOk()
            ->assertJsonPath('data.experience.id', $experience->id)
            ->assertJsonPath('data.experience.seats_taken', 3)
            ->assertJsonPath('data.experience.seats_left', 12)
            ->assertJsonCount(1, 'data.participants')
            ->assertJsonPath('data.participants.0.client_name', 'Ndeye Ba')
            ->assertJsonPath('data.participants.0.guests', 3)
            // Rien d'encaissé : le solde dû se voit avant le départ.
            ->assertJsonPath('data.participants.0.remaining_xof', 80_000);
    }

    public function test_un_participant_annule_reste_liste_mais_ne_compte_pas(): void
    {
        $experience = TourismExperience::factory()->create(['capacity' => 15]);

        $this->booking($experience->id, ['guests' => 2]);
        $this->booking($experience->id, ['guests' => 6, 'status' => 'annulee_client']);

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/experiences/{$experience->id}")
            ->assertOk()
            ->assertJsonPath('data.experience.seats_taken', 2)
            ->assertJsonCount(2, 'data.participants');
    }

    // --- Partenaire --------------------------------------------------------------

    public function test_la_fiche_partenaire_donne_le_contact_et_les_avis(): void
    {
        $account = User::factory()->create(['name' => 'Ousmane Ndiaye']);
        $provider = Provider::factory()->create([
            'user_id' => $account->id,
            'business_name' => 'Saloum Découverte',
            'warnings_count' => 1,
            'sanction_note' => 'Retard répété sur les prises en charge',
        ]);

        Review::create([
            'reference' => 'AVI-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'reviewable_type' => Provider::class,
            'reviewable_id' => $provider->id,
            'rating' => 2,
            'comment' => 'Guide arrivé avec une heure de retard.',
            'status' => 'publie',
        ]);

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/providers/{$provider->id}")
            ->assertOk()
            ->assertJsonPath('data.provider.business_name', 'Saloum Découverte')
            ->assertJsonPath('data.provider.warnings_count', 1)
            ->assertJsonPath('data.provider.sanction_note', 'Retard répété sur les prises en charge')
            // Le compte derrière l'enseigne : c'est lui qu'on appelle.
            ->assertJsonPath('data.account.name', 'Ousmane Ndiaye')
            ->assertJsonCount(1, 'data.reviews')
            ->assertJsonPath('data.reviews.0.rating', 2)
            ->assertJsonPath('data.reviews.0.comment', 'Guide arrivé avec une heure de retard.');
    }
}
