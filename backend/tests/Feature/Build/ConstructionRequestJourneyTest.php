<?php

namespace Tests\Feature\Build;

use App\Models\User;
use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\FinishLevel;
use App\Modules\Build\Notifications\NewConstructionRequestNotification;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Admin\Enums\AdminPermission;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F8.15.b — le PARCOURS « un visiteur dépose un chantier, l'équipe le voit ».
 *
 * `ConstructionRequestApiTest` couvre le dépôt lui-même depuis B5.5, et passait
 * au vert — mais **aucun écran du site n'appelait cette route** : la page
 * `/construction` envoyait un `POST /requests` générique, et le dossier
 * n'atteignait donc jamais la table que lit le back-office. Le module entier
 * (jalons, rapports, devis par lot, acceptation client, conversion en
 * réservation payable) partait d'une porte murée.
 *
 * Ce fichier vérifie ce que le test d'API ne regardait pas : que le dépôt
 * **alerte l'équipe** et que le dossier **apparaît dans son écran**.
 */
class ConstructionRequestJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Un profil back-office : c'est le vivier des alertes de file d'attente. */
    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    /** Le corps qu'envoie la page `/construction` depuis F8.15.b. */
    private function payload(): array
    {
        return [
            'objective' => ConstructionObjective::CONSTRUCTION_NEUVE->value,
            'city' => 'Thiès',
            'surface_m2' => 120,
            'finish_level' => FinishLevel::STANDARD->value,
            'budget_xof' => 30_000_000,
            'description' => "Villa R+1, terrain déjà possédé.\nEstimation du simulateur : 30 000 000 FCFA.",
        ];
    }

    public function test_le_depot_alerte_l_equipe(): void
    {
        Notification::fake();

        $agent = $this->agent();
        $client = User::factory()->create();
        Sanctum::actingAs($client);

        $this->postJson('/api/v1/construction-requests', $this->payload())->assertCreated();

        Notification::assertSentTo($agent, NewConstructionRequestNotification::class);
        // Le demandeur n'est pas de l'équipe : il ne reçoit pas l'alerte interne.
        Notification::assertNotSentTo($client, NewConstructionRequestNotification::class);
    }

    public function test_le_dossier_depose_apparait_dans_l_ecran_construction_du_back_office(): void
    {
        $client = User::factory()->create();
        Sanctum::actingAs($client);

        $reference = $this->postJson('/api/v1/construction-requests', $this->payload())
            ->assertCreated()
            ->json('data.construction_request.reference');

        // C'est LE point du parcours : la demande atterrit dans
        // `construction_requests`, la table que lit le back-office — et non dans
        // `requests`, où la page publique la déposait jusqu'ici.
        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/construction-requests')
            ->assertOk()
            ->assertJsonFragment(['reference' => $reference]);
    }

    public function test_le_dossier_porte_l_estimation_et_ses_jalons(): void
    {
        $client = User::factory()->create();
        Sanctum::actingAs($client);

        $response = $this->postJson('/api/v1/construction-requests', $this->payload())
            ->assertCreated();

        // Ces deux-là étaient perdus quand la demande partait en `requests` :
        // le simulateur n'y laissait qu'une phrase, et rien n'était chiffré ni
        // planifié côté serveur.
        $this->assertGreaterThan(0, $response->json('data.construction_request.estimated_cost_xof'));
        $this->assertNotEmpty($response->json('data.construction_request.milestones'));

        // Et le client le retrouve dans son espace.
        $this->getJson('/api/v1/construction-requests/mine')
            ->assertOk()
            ->assertJsonFragment(['reference' => $response->json('data.construction_request.reference')]);
    }

    public function test_un_visiteur_anonyme_ne_depose_pas_de_chantier(): void
    {
        $this->postJson('/api/v1/construction-requests', $this->payload())->assertStatus(401);
    }
}
