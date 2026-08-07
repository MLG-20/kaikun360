<?php

namespace Tests\Feature\Core;

use App\Models\Commune;
use App\Models\Department;
use App\Models\Region;
use App\Models\User;
use App\Modules\Core\Models\Profile;
use App\Modules\Core\Notifications\VerificationCodeNotification;
use App\Notifications\LoginEmailChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

    // =========================================================================
    // F8.22 — L'ADRESSE DE CONNEXION EST UNE SERRURE
    // =========================================================================

    /**
     * ⚠️ Le trou que ces tests ferment : avec une session ouverte (poste laissé
     * déverrouillé, jeton dérobé), n'importe qui pouvait remplacer l'e-mail d'un
     * compte — **y compris celui d'un super administrateur** — puis demander un
     * nouveau mot de passe sur cette adresse. Le compte changeait de mains sans
     * que le mot de passe soit jamais connu.
     */
    public function test_changer_d_email_exige_le_mot_de_passe_actuel(): void
    {
        $user = User::factory()->create([
            'email' => 'titulaire@exemple.sn',
            'password' => 'motdepasse-actuel',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', ['email' => 'pirate@exemple.sn'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertSame('titulaire@exemple.sn', $user->fresh()->email);
    }

    public function test_un_mauvais_mot_de_passe_ne_change_pas_l_email(): void
    {
        $user = User::factory()->create([
            'email' => 'titulaire@exemple.sn',
            'password' => 'motdepasse-actuel',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', [
            'email' => 'pirate@exemple.sn',
            'current_password' => 'au-hasard',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->assertSame('titulaire@exemple.sn', $user->fresh()->email);
    }

    /** Le reste du profil ne réclame évidemment rien. */
    public function test_modifier_son_nom_ne_demande_aucun_mot_de_passe(): void
    {
        $user = User::factory()->create(['email' => 'titulaire@exemple.sn']);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', ['name' => 'Nouveau Nom'])->assertOk();

        $this->assertSame('Nouveau Nom', $user->fresh()->name);
    }

    /** Renvoyer sa PROPRE adresse avec le formulaire ne doit rien réclamer. */
    public function test_renvoyer_la_meme_adresse_ne_demande_aucun_mot_de_passe(): void
    {
        $user = User::factory()->create(['email' => 'titulaire@exemple.sn']);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', [
            'email' => 'titulaire@exemple.sn',
            'name' => 'Titulaire',
        ])->assertOk();
    }

    public function test_le_changement_d_email_previent_l_ancienne_adresse_et_ferme_les_autres_sessions(): void
    {
        NotificationFacade::fake();

        $user = User::factory()->create([
            'email' => 'titulaire@exemple.sn',
            'password' => 'motdepasse-actuel',
        ]);

        // Une autre session ouverte ailleurs (téléphone, autre navigateur).
        $autreSession = $user->createToken('autre-appareil');

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', [
            'email' => 'nouvelle@exemple.sn',
            'current_password' => 'motdepasse-actuel',
        ])->assertOk();

        $this->assertSame('nouvelle@exemple.sn', $user->fresh()->email);

        // ⚠️ L'alerte part à l'ANCIENNE adresse : prévenir la nouvelle
        // reviendrait à informer l'attaquant de sa réussite.
        NotificationFacade::assertSentOnDemand(
            LoginEmailChangedNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'titulaire@exemple.sn',
        );

        // Les autres sessions tombent : un jeton dérobé ne doit pas survivre.
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $autreSession->accessToken->id,
        ]);
    }

    /**
     * ⚠️ Exception assumée : un compte Google n'a jamais vu son mot de passe
     * (chaîne aléatoire posée à la création). Le lui demander l'empêcherait
     * purement et simplement de corriger son adresse.
     */
    public function test_un_compte_google_change_son_email_sans_mot_de_passe(): void
    {
        $user = User::factory()->create([
            'email' => 'via-google@exemple.sn',
            'google_id' => 'google-123',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', ['email' => 'autre@exemple.sn'])->assertOk();

        $this->assertSame('autre@exemple.sn', $user->fresh()->email);
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

    public function test_changement_d_email_declenche_une_re_verification(): void
    {
        Notification::fake();
        $user = $this->utilisateurAvecProfil();
        $user->forceFill(['email_verified_at' => now()])->save();
        Sanctum::actingAs($user);

        // ⚠️ F8.22 — le mot de passe actuel accompagne désormais tout changement
        // d'adresse : elle commande la récupération du compte (`password/forgot`).
        // Le mot de passe de la factory est « password ».
        $this->patchJson('/api/v1/users/me', [
            'email' => 'nouvel@exemple.sn',
            'current_password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.verification.email_required', true);

        // Nouvel e-mail enregistré + canal remis à « non vérifié ».
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'nouvel@exemple.sn', 'email_verified_at' => null]);
        // Un code a été émis (ligne + notification vers le nouvel e-mail).
        $this->assertDatabaseHas('verification_codes', ['user_id' => $user->id, 'channel' => 'email', 'purpose' => 'account_verification']);
        Notification::assertSentTo($user, VerificationCodeNotification::class);
    }

    public function test_changement_de_telephone_declenche_une_re_verification(): void
    {
        Notification::fake();
        $user = $this->utilisateurAvecProfil();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', ['phone' => '+221770000000'])
            ->assertOk()
            ->assertJsonPath('data.verification.phone_required', true)
            // Le média réel accompagne la réponse : l'écran doit annoncer où
            // chercher le code, qui part par e-mail tant que le SMS est factice.
            ->assertJsonPath('data.verification.phone_delivery', 'mail');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '+221770000000', 'phone_verified_at' => null]);
        $this->assertDatabaseHas('verification_codes', ['user_id' => $user->id, 'channel' => 'phone']);
        Notification::assertSentTo($user, VerificationCodeNotification::class);
    }

    public function test_email_deja_utilise_est_refuse(): void
    {
        $autre = User::factory()->create(['email' => 'pris@exemple.sn']);
        $user = $this->utilisateurAvecProfil();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', ['email' => 'pris@exemple.sn'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_localisation_en_cascade_est_enregistree(): void
    {
        $region = Region::create(['name' => 'Dakar']);
        $departement = Department::create(['region_id' => $region->id, 'name' => 'Dakar']);
        $commune = Commune::create(['department_id' => $departement->id, 'name' => 'Dakar-Plateau']);

        $user = $this->utilisateurAvecProfil();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me', [
            'region_id' => $region->id,
            'department_id' => $departement->id,
            'commune_id' => $commune->id,
            'address' => 'Rue 10 x Avenue Bourguiba',
        ])->assertOk();

        // Localisation enregistrée + ville « texte » dérivée de la commune.
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'region_id' => $region->id,
            'department_id' => $departement->id,
            'commune_id' => $commune->id,
            'address' => 'Rue 10 x Avenue Bourguiba',
            'city' => 'Dakar-Plateau',
        ]);
    }

    public function test_localisation_incoherente_est_refusee(): void
    {
        $regionA = Region::create(['name' => 'Dakar']);
        $regionB = Region::create(['name' => 'Thiès']);
        // Département rattaché à la région B.
        $departementB = Department::create(['region_id' => $regionB->id, 'name' => 'Mbour']);

        $user = $this->utilisateurAvecProfil();
        Sanctum::actingAs($user);

        // On prétend que ce département appartient à la région A → incohérent.
        $this->patchJson('/api/v1/users/me', [
            'region_id' => $regionA->id,
            'department_id' => $departementB->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['department_id']);
    }

    public function test_changement_de_mot_de_passe(): void
    {
        $user = $this->utilisateurAvecProfil(); // mot de passe factory = "password"
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me/password', [
            'current_password' => 'password',
            'password' => 'nouveau-secret-1',
            'password_confirmation' => 'nouveau-secret-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('nouveau-secret-1', $user->fresh()->password));
    }

    public function test_changement_de_mot_de_passe_refuse_un_mauvais_actuel(): void
    {
        $user = $this->utilisateurAvecProfil();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/users/me/password', [
            'current_password' => 'mauvais',
            'password' => 'nouveau-secret-1',
            'password_confirmation' => 'nouveau-secret-1',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);
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
