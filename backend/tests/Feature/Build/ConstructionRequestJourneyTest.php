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

    /**
     * Le mauvais aiguillage est devenu impossible : sans ce garde-fou, un futur
     * écran (ou l'application mobile) pourrait redéposer un chantier en demande
     * générique, et le dossier retomberait dans la table que le back-office
     * « Construction » ne lit pas.
     */
    public function test_un_chantier_ne_peut_plus_partir_en_demande_generique(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/requests', [
            'service_type' => 'build',
            'message' => 'Je veux construire une villa à Thiès.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('service_type');

        // Les autres univers, eux, restent des demandes génériques légitimes :
        // leur conversion EST une prise de contact.
        $this->postJson('/api/v1/requests', [
            'service_type' => 'manage',
            'message' => 'Je souhaite confier mon appartement en gestion.',
        ])->assertCreated();
    }

    /**
     * Les demandes déposées AVANT F8.15.b portent encore `build` : le cas reste
     * dans l'enum et doit rester relisible, sans quoi on casserait l'historique
     * en fermant la porte d'entrée.
     */
    public function test_les_anciennes_demandes_build_restent_lisibles(): void
    {
        $client = User::factory()->create();

        $ancienne = \App\Models\ServiceRequest::create([
            'reference' => 'REQ-LEGACY01',
            'user_id' => $client->id,
            'service_type' => 'build',
            'message' => 'Demande de chantier déposée avant F8.15.b.',
            'status' => \App\Enums\RequestStatus::RECU->value,
        ]);

        Sanctum::actingAs($client);

        $this->getJson('/api/v1/requests/my')
            ->assertOk()
            ->assertJsonFragment(['reference' => $ancienne->reference]);
    }
}
