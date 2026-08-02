<?php

namespace Tests\Feature\Admin;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F8.9 : file de traitement des demandes clients au back-office.
 *
 * Le trou comblé ici : depuis B11.2 l'équipe recevait l'alerte « Nouvelle
 * demande à traiter » et pouvait changer un statut, mais aucune route ne lui
 * permettait de **retrouver** les demandes.
 */
class AdminRequestQueueTest extends TestCase
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
        // F7.1.b : les droits sont délégués permission par permission.
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    public function test_la_file_est_fermee_sans_la_permission(): void
    {
        // Un client connecté n'est pas un agent : la file lui est fermée, tout
        // comme la fiche — les coordonnées d'autres clients y figurent.
        $demande = ServiceRequest::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/requests')->assertStatus(403);
        $this->getJson("/api/v1/admin/requests/{$demande->id}")->assertStatus(403);
        $this->getJson('/api/v1/admin/requests/filters')->assertStatus(403);
    }

    public function test_la_file_liste_toutes_les_demandes_avec_leur_demandeur(): void
    {
        $client = User::factory()->create(['name' => 'Awa Diop', 'phone' => '+221770000001']);
        ServiceRequest::factory()->create(['user_id' => $client->id]);
        ServiceRequest::factory()->count(2)->create();

        Sanctum::actingAs($this->agent());

        $reponse = $this->getJson('/api/v1/admin/requests')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        // Le demandeur est joignable depuis la liste : traiter une demande
        // commence presque toujours par rappeler son auteur.
        $ligne = collect($reponse->json('data'))
            ->firstWhere('requester.name', 'Awa Diop');

        $this->assertNotNull($ligne, 'Le demandeur devrait remonter dans la liste.');
        $this->assertSame('+221770000001', $ligne['requester']['phone']);
    }

    public function test_les_urgences_passent_devant_puis_les_plus_anciennes(): void
    {
        // Une file de traitement se lit par l'urgence, pas par la date d'arrivée :
        // c'est le dossier qui attend depuis le plus longtemps qui coûte cher.
        $ancienneNormale = ServiceRequest::factory()->create([
            'priority' => RequestPriority::NORMALE->value,
            'created_at' => now()->subDays(5),
        ]);
        $recenteUrgente = ServiceRequest::factory()->create([
            'priority' => RequestPriority::URGENTE->value,
            'created_at' => now()->subHour(),
        ]);
        $ancienneHaute = ServiceRequest::factory()->create([
            'priority' => RequestPriority::HAUTE->value,
            'created_at' => now()->subDays(2),
        ]);

        Sanctum::actingAs($this->agent());

        $references = collect($this->getJson('/api/v1/admin/requests')->json('data'))
            ->pluck('reference')
            ->all();

        $this->assertSame(
            [$recenteUrgente->reference, $ancienneHaute->reference, $ancienneNormale->reference],
            $references,
        );
    }

    public function test_les_filtres_et_la_recherche_restreignent_la_file(): void
    {
        $client = User::factory()->create(['name' => 'Moussa Fall']);
        $cible = ServiceRequest::factory()->create([
            'user_id' => $client->id,
            'service_type' => 'immo',
            'status' => RequestStatus::VERIFICATION->value,
        ]);
        ServiceRequest::factory()->create(['service_type' => 'mobility']);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/requests?service_type=immo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $cible->reference);

        $this->getJson('/api/v1/admin/requests?status=verification')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // On cherche par identité : un client rappelle rarement avec son
        // numéro de dossier sous les yeux.
        $this->getJson('/api/v1/admin/requests?search=Moussa')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $cible->reference);
    }

    public function test_la_fiche_donne_le_dossier_ses_devis_et_son_historique(): void
    {
        $demande = ServiceRequest::factory()->create(['status' => RequestStatus::DEVIS->value]);
        Quote::factory()->create(['request_id' => $demande->id]);

        $agent = $this->agent();
        Sanctum::actingAs($agent);

        // Un changement de statut journalise l'acteur : c'est ce qui rend la
        // file auditable.
        $this->patchJson("/api/v1/requests/{$demande->id}/status", ['status' => 'negociation'])
            ->assertOk();

        $this->getJson("/api/v1/admin/requests/{$demande->id}")
            ->assertOk()
            ->assertJsonPath('data.request.reference', $demande->reference)
            ->assertJsonPath('data.request.status', 'negociation')
            ->assertJsonCount(1, 'data.quotes')
            ->assertJsonPath('data.activity.0.causer_name', $agent->name);
    }

    public function test_la_fiche_annonce_les_transitions_permises(): void
    {
        // L'écran ne doit jamais proposer un bouton qui se ferait refuser en
        // 422 : c'est le serveur qui dit ce qui est permis.
        $demande = ServiceRequest::factory()->create(['status' => RequestStatus::RECU->value]);

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/requests/{$demande->id}")
            ->assertOk()
            ->assertJsonPath('data.request.allowed_transitions.0.value', 'verification')
            ->assertJsonPath('data.request.allowed_transitions.1.value', 'cloture');
    }

    public function test_les_referentiels_de_filtrage_viennent_des_enums(): void
    {
        Sanctum::actingAs($this->agent());

        $reponse = $this->getJson('/api/v1/admin/requests/filters')->assertOk();

        $this->assertCount(count(RequestStatus::cases()), $reponse->json('data.statuses'));
        $this->assertSame('Reçu', $reponse->json('data.statuses.0.label'));
        $this->assertNotEmpty($reponse->json('data.service_types'));
        $this->assertNotEmpty($reponse->json('data.priorities'));
    }
}
