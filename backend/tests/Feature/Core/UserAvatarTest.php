<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Photo de profil / logo d'entreprise (F8.0).
 *
 * Ce que ces tests protègent, au-delà du « ça marche » :
 *   - le disque est PUBLIC (une photo doit rester affichable, pas expirer) ;
 *   - remplacer une photo SUPPRIME l'ancien fichier (sinon fuite de stockage) ;
 *   - un profil « entreprise » demande un LOGO, pas une photo (`avatar_kind`) ;
 *   - l'anonymisation RGPD efface le visage, fichier compris.
 */
class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    /** Crée un utilisateur muni de son profil, du type demandé, et l'authentifie. */
    private function actingAsUserAvecProfil(ProfileType $type = ProfileType::CLIENT): User
    {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id, 'type' => $type->value]);
        Sanctum::actingAs($user);

        return $user->load('profile');
    }

    public function test_l_utilisateur_depose_sa_photo_de_profil(): void
    {
        Storage::fake('public');
        $user = $this->actingAsUserAvecProfil();

        $this->post('/api/v1/users/me/avatar', [
            'avatar' => UploadedFile::fake()->image('moi.jpg', 400, 400),
        ])->assertOk();

        $path = $user->profile->fresh()->avatar_path;

        $this->assertNotNull($path, 'Le chemin de la photo doit être enregistré.');
        Storage::disk('public')->assertExists($path);
        // Rangement par utilisateur : deux comptes ne se marchent pas dessus.
        $this->assertStringStartsWith("avatars/{$user->id}/", $path);
    }

    public function test_la_reponse_contient_l_url_publique_de_la_photo(): void
    {
        Storage::fake('public');
        $this->actingAsUserAvecProfil();

        $response = $this->post('/api/v1/users/me/avatar', [
            'avatar' => UploadedFile::fake()->image('moi.png', 300, 300),
        ])->assertOk();

        // L'URL doit être exploitable telle quelle par le front (pas de
        // signature à renouveler) : c'est tout l'intérêt du disque public.
        $this->assertNotNull($response->json('data.user.profile.avatar_url'));
        $this->assertSame('photo', $response->json('data.user.profile.avatar_kind'));
    }

    public function test_un_profil_entreprise_attend_un_logo(): void
    {
        Storage::fake('public');
        $this->actingAsUserAvecProfil(ProfileType::ENTREPRISE);

        $this->post('/api/v1/users/me/avatar', [
            'avatar' => UploadedFile::fake()->image('logo.png', 512, 512),
        ])
            ->assertOk()
            // Même colonne, sens différent : l'interface doit demander un logo
            // à une entreprise, pas un portrait.
            ->assertJsonPath('data.user.profile.avatar_kind', 'logo');
    }

    public function test_remplacer_la_photo_supprime_l_ancien_fichier(): void
    {
        Storage::fake('public');
        $user = $this->actingAsUserAvecProfil();

        $this->post('/api/v1/users/me/avatar', [
            'avatar' => UploadedFile::fake()->image('ancienne.jpg', 300, 300),
        ])->assertOk();
        $ancien = $user->profile->fresh()->avatar_path;

        $this->post('/api/v1/users/me/avatar', [
            'avatar' => UploadedFile::fake()->image('nouvelle.jpg', 300, 300),
        ])->assertOk();
        $nouveau = $user->profile->fresh()->avatar_path;

        $this->assertNotSame($ancien, $nouveau);
        // Le point du test : sans ce nettoyage, chaque changement de photo
        // laisserait un orphelin que plus rien ne référence.
        Storage::disk('public')->assertMissing($ancien);
        Storage::disk('public')->assertExists($nouveau);
    }

    public function test_l_utilisateur_retire_sa_photo(): void
    {
        Storage::fake('public');
        $user = $this->actingAsUserAvecProfil();

        $this->post('/api/v1/users/me/avatar', [
            'avatar' => UploadedFile::fake()->image('moi.jpg', 300, 300),
        ])->assertOk();
        $path = $user->profile->fresh()->avatar_path;

        $this->deleteJson('/api/v1/users/me/avatar')
            ->assertOk()
            ->assertJsonPath('data.user.profile.avatar_url', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->profile->fresh()->avatar_path);
    }

    public function test_retirer_une_photo_absente_reste_sans_erreur(): void
    {
        Storage::fake('public');
        $this->actingAsUserAvecProfil();

        // Idempotence : un double clic sur « Retirer » ne doit pas produire
        // d'erreur, le compte est déjà dans l'état demandé.
        $this->deleteJson('/api/v1/users/me/avatar')->assertOk();
    }

    public function test_un_fichier_non_image_est_refuse(): void
    {
        Storage::fake('public');
        $this->actingAsUserAvecProfil();

        // Le disque étant PUBLIC, un PDF déposé là serait servi tel quel par le
        // serveur web. La validation `image` doit fermer la porte.
        $this->post('/api/v1/users/me/avatar', [
            'avatar' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');
    }

    public function test_une_image_trop_petite_est_refusee(): void
    {
        Storage::fake('public');
        $this->actingAsUserAvecProfil();

        $this->post('/api/v1/users/me/avatar', [
            'avatar' => UploadedFile::fake()->image('minuscule.jpg', 40, 40),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');
    }

    public function test_le_depot_exige_d_etre_authentifie(): void
    {
        Storage::fake('public');

        $this->post('/api/v1/users/me/avatar', [
            'avatar' => UploadedFile::fake()->image('moi.jpg', 300, 300),
        ], ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_l_anonymisation_rgpd_efface_la_photo(): void
    {
        Storage::fake('public');
        $user = $this->actingAsUserAvecProfil();

        $this->post('/api/v1/users/me/avatar', [
            'avatar' => UploadedFile::fake()->image('moi.jpg', 300, 300),
        ])->assertOk();
        $path = $user->profile->fresh()->avatar_path;

        $this->deleteJson('/api/v1/users/me')->assertOk();

        // Un visage est la donnée personnelle la plus directe, et l'URL était
        // PUBLIQUE : la laisser servie après suppression du compte serait une
        // fuite pure et simple.
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->profile->fresh()->avatar_path);
    }
}
