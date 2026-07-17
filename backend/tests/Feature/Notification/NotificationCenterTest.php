<?php

namespace Tests\Feature\Notification;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests du centre de notifications de l'espace client (phase F3.6).
 *
 * Couvre les endpoints `/users/me/notifications*` : liste paginée, compteur de
 * non-lues, marquage d'une notification / de toutes, et l'isolation stricte
 * entre utilisateurs. Un test d'intégration vérifie aussi que le canal
 * `database` produit bien une ligne exploitable à partir d'une vraie
 * notification métier.
 */
class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crée une notification "base de données" pour un utilisateur, en insérant
     * directement dans la table via la relation Notifiable (déterministe, sans
     * dépendre d'un déclencheur métier).
     */
    private function pousserNotification(User $user, array $data = [], bool $lue = false): string
    {
        $id = (string) Str::uuid();

        $user->notifications()->create([
            'id' => $id,
            'type' => RequestStatusChangedNotification::class,
            'data' => array_merge([
                'category' => 'request',
                'title' => 'Votre demande a avancé',
                'body' => 'La demande « REQ-001 » est passée au statut : Reçue.',
                'action_url' => '/mon-espace/demandes',
            ], $data),
            'read_at' => $lue ? now() : null,
        ]);

        return $id;
    }

    public function test_la_liste_exige_d_etre_authentifie(): void
    {
        $this->getJson('/api/v1/users/me/notifications')->assertStatus(401);
    }

    public function test_liste_paginee_avec_compteur_de_non_lues(): void
    {
        $user = User::factory()->create();
        $this->pousserNotification($user, ['title' => 'Une']);
        $this->pousserNotification($user, ['title' => 'Deux']);
        $this->pousserNotification($user, ['title' => 'Trois'], lue: true);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/me/notifications')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'category', 'title', 'body', 'action_url', 'read', 'created_at']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'unread_count',
            ])
            ->assertJsonPath('unread_count', 2);
    }

    public function test_le_compteur_de_non_lues(): void
    {
        $user = User::factory()->create();
        $this->pousserNotification($user);
        $this->pousserNotification($user, [], lue: true);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/me/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }

    public function test_marquer_une_notification_comme_lue(): void
    {
        $user = User::factory()->create();
        $id = $this->pousserNotification($user);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/users/me/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('data.notification.read', true)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertNotNull($user->notifications()->find($id)->read_at);
    }

    public function test_impossible_de_marquer_la_notification_d_autrui(): void
    {
        $moi = User::factory()->create();
        $autre = User::factory()->create();
        $id = $this->pousserNotification($autre);

        Sanctum::actingAs($moi);

        // La notification existe, mais pas pour moi → 404 (aucune fuite).
        $this->patchJson("/api/v1/users/me/notifications/{$id}/read")->assertStatus(404);
        $this->assertNull($autre->notifications()->find($id)->read_at);
    }

    public function test_marquer_toutes_comme_lues(): void
    {
        $user = User::factory()->create();
        $this->pousserNotification($user);
        $this->pousserNotification($user);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_le_canal_database_ecrit_une_ligne_exploitable(): void
    {
        $user = User::factory()->create();
        $request = ServiceRequest::factory()->create(['user_id' => $user->id]);

        // Vraie notification métier (ShouldQueue exécuté en sync pendant les tests).
        $user->notify(new RequestStatusChangedNotification($request));

        $notification = $user->fresh()->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('request', $notification->data['category']);
        $this->assertSame('/mon-espace/demandes', $notification->data['action_url']);
        $this->assertArrayHasKey('title', $notification->data);
        $this->assertArrayHasKey('body', $notification->data);
    }
}
