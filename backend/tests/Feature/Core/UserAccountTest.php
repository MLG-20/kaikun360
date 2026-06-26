<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests du compte de l'utilisateur connecté (phase B1.5) :
 * consultation/mise à jour du profil et dépôt/téléchargement de documents.
 */
class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    /** Crée un utilisateur muni de son profil. */
    private function utilisateurAvecProfil(): User
    {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id]);

        return $user->load('profile');
    }

    public function test_me_renvoie_l_utilisateur_connecte(): void
    {
        $user = $this->utilisateurAvecProfil();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['user' => ['id', 'email', 'roles', 'profile']]]);
    }

    public function test_me_exige_d_etre_authentifie(): void
    {
        $this->getJson('/api/v1/users/me')->assertStatus(401);
    }

    public function test_mise_a_jour_du_profil(): void
    {
        $user = $this->utilisateurAvecProfil();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', [
            'name' => 'Nouveau Nom',
            'city' => 'Thiès',
            'preferences' => ['langue' => 'fr'],
        ])->assertOk()->assertJsonPath('data.user.name', 'Nouveau Nom');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nouveau Nom', 'city' => 'Thiès']);
        $this->assertSame(['langue' => 'fr'], $user->profile->fresh()->preferences);
    }

    public function test_depot_de_document_sur_disque_prive(): void
    {
        Storage::fake('local');
        $user = $this->utilisateurAvecProfil();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('cni.pdf', 100, 'application/pdf');

        $res = $this->postJson('/api/v1/users/me/documents', ['type' => 'cni', 'file' => $file]);

        $res->assertCreated()
            ->assertJsonPath('data.document.type', 'cni')
            ->assertJsonStructure(['data' => ['document' => ['id', 'download_url']]]);

        $this->assertDatabaseHas('user_documents', ['user_id' => $user->id, 'type' => 'cni']);

        // Le fichier existe bien sur le disque privé.
        $document = $user->documents()->first();
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_depot_refuse_un_fichier_non_autorise(): void
    {
        Storage::fake('local');
        $user = $this->utilisateurAvecProfil();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream');

        $this->postJson('/api/v1/users/me/documents', ['type' => 'cni', 'file' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_telechargement_via_url_signee(): void
    {
        Storage::fake('local');
        $user = $this->utilisateurAvecProfil();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('cni.pdf', 100, 'application/pdf');
        $res = $this->postJson('/api/v1/users/me/documents', ['type' => 'cni', 'file' => $file]);

        $url = $res->json('data.document.download_url');

        // Avec la signature valide : téléchargement OK.
        $this->get($url)->assertOk();

        // Sans la signature (query retirée) : accès refusé (403).
        $sansSignature = strtok($url, '?');
        $this->get($sansSignature)->assertStatus(403);
    }
}
