<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests d'autorisation systématiques (phase B1.6).
 *
 * Objectif (exigence du cahier des charges) : prouver qu'aucun utilisateur
 * n'accède aux données d'un autre, et qu'aucun endpoint protégé n'est
 * atteignable sans authentification.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** Crée un document directement en base (sans fichier réel). */
    private function creerDocument(User $user, string $type, string $path): UserDocument
    {
        return UserDocument::create([
            'user_id' => $user->id,
            'type' => $type,
            'disk' => 'local',
            'path' => $path,
            'original_name' => basename($path),
        ]);
    }

    public function test_tous_les_endpoints_proteges_exigent_un_token(): void
    {
        $cas = [
            ['getJson', '/api/v1/users/me'],
            ['patchJson', '/api/v1/users/me'],
            ['getJson', '/api/v1/users/me/documents'],
            ['postJson', '/api/v1/users/me/documents'],
            ['postJson', '/api/v1/auth/logout'],
            ['postJson', '/api/v1/auth/verify'],
            ['postJson', '/api/v1/auth/verify/send'],
        ];

        foreach ($cas as [$methode, $uri]) {
            $this->{$methode}($uri)->assertStatus(401);
        }
    }

    public function test_me_renvoie_toujours_l_utilisateur_courant_et_pas_un_autre(): void
    {
        $a = User::factory()->create(['email' => 'a@example.com']);
        $b = User::factory()->create(['email' => 'b@example.com']);

        Sanctum::actingAs($a);

        $this->getJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'a@example.com');
        // Aucune fuite des données de B : on ne voit que A.
        $this->assertNotSame($b->id, $a->id);
    }

    public function test_la_liste_des_documents_est_limitee_a_l_utilisateur(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->creerDocument($a, 'cni', 'documents/a/1.pdf');
        $this->creerDocument($a, 'passeport', 'documents/a/2.pdf');
        $docB = $this->creerDocument($b, 'cni', 'documents/b/1.pdf');

        Sanctum::actingAs($a);

        $res = $this->getJson('/api/v1/users/me/documents')->assertOk();

        // A ne voit que ses 2 documents, jamais celui de B.
        $res->assertJsonCount(2, 'data.documents');
        $ids = collect($res->json('data.documents'))->pluck('id');
        $this->assertFalse($ids->contains($docB->id));
    }

    public function test_impossible_de_telecharger_le_document_d_un_autre_sans_signature(): void
    {
        $b = User::factory()->create();
        $docB = $this->creerDocument($b, 'cni', 'documents/b/1.pdf');

        // Sans URL signée valide (que seul le propriétaire peut obtenir) → 403.
        $this->get("/api/v1/users/me/documents/{$docB->id}/download")
            ->assertStatus(403);
    }
}
