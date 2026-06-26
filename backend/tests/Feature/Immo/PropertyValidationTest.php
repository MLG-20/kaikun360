<?php

namespace Tests\Feature\Immo;

use App\Models\Region;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Events\PropertyCreated;
use App\Modules\Immo\Models\Property;
use App\Modules\Immo\Notifications\NewPropertyToValidateNotification;
use App\Modules\Immo\Notifications\PropertyValidatedNotification;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests des événements et de la validation des biens (phase B2.4).
 */
class PropertyValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    private function avecRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function donneesDepot(): array
    {
        $region = Region::where('name', 'Dakar')->first();

        return [
            'type' => 'villa',
            'title' => 'Villa à valider',
            'region_id' => $region->id,
            'department_id' => $region->departments()->first()->id,
        ];
    }

    public function test_la_creation_emet_l_evenement_property_created(): void
    {
        Event::fake([PropertyCreated::class]);
        Sanctum::actingAs($this->avecRole(UserRole::PROPRIETAIRE->value));

        $this->postJson('/api/v1/properties', $this->donneesDepot())->assertCreated();

        Event::assertDispatched(PropertyCreated::class);
    }

    public function test_les_agents_sont_notifies_d_un_nouveau_bien(): void
    {
        Notification::fake();
        $agent = $this->avecRole(UserRole::AGENT_KAIKUN->value);
        Sanctum::actingAs($this->avecRole(UserRole::PROPRIETAIRE->value));

        $this->postJson('/api/v1/properties', $this->donneesDepot())->assertCreated();

        Notification::assertSentTo($agent, NewPropertyToValidateNotification::class);
    }

    public function test_un_agent_valide_et_publie_un_bien(): void
    {
        $owner = $this->avecRole(UserRole::PROPRIETAIRE->value);
        $property = Property::factory()->pending()->create(['owner_id' => $owner->id]);
        $agent = $this->avecRole(UserRole::AGENT_KAIKUN->value);

        Sanctum::actingAs($agent);

        $this->patchJson("/api/v1/properties/{$property->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.property.status', 'publie');

        $property->refresh();
        $this->assertNotNull($property->published_at);
        $this->assertSame($agent->id, $property->approved_by);

        // Le bien apparaît désormais au catalogue public.
        $this->getJson('/api/v1/properties')->assertJsonCount(1, 'data');
    }

    public function test_le_proprietaire_est_notifie_de_la_validation(): void
    {
        Notification::fake();
        $owner = $this->avecRole(UserRole::PROPRIETAIRE->value);
        $property = Property::factory()->pending()->create(['owner_id' => $owner->id]);
        $agent = $this->avecRole(UserRole::AGENT_KAIKUN->value);

        Sanctum::actingAs($agent);
        $this->patchJson("/api/v1/properties/{$property->id}/approve")->assertOk();

        Notification::assertSentTo($owner, PropertyValidatedNotification::class);
    }

    public function test_un_non_agent_ne_peut_pas_valider(): void
    {
        $owner = $this->avecRole(UserRole::PROPRIETAIRE->value);
        $property = Property::factory()->pending()->create(['owner_id' => $owner->id]);

        // Le propriétaire lui-même ne peut pas valider son bien.
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/properties/{$property->id}/approve")->assertStatus(403);
    }

    public function test_un_agent_peut_rejeter_un_bien(): void
    {
        $property = Property::factory()->pending()->create();
        $agent = $this->avecRole(UserRole::AGENT_KAIKUN->value);

        Sanctum::actingAs($agent);

        $this->patchJson("/api/v1/properties/{$property->id}/reject", ['reason' => 'Documents manquants'])
            ->assertOk()
            ->assertJsonPath('data.property.status', 'rejete');
    }

    public function test_la_validation_exige_une_authentification(): void
    {
        $property = Property::factory()->pending()->create();

        $this->patchJson("/api/v1/properties/{$property->id}/approve")->assertStatus(401);
    }
}
