<?php

namespace Tests\Feature\Pro;

use App\Models\User;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderCertification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Justificatif de certification prestataire (F8.0).
 *
 * Dette comblée ici : la colonne `provider_certifications.file_path` existait
 * depuis B6 mais n'était JAMAIS renseignée — « Mes services » se contentait de
 * *déclarer* une certification (nom + organisme), aucun contrôleur n'acceptait
 * de fichier. Le back-office (Comptes → Documents) affichait donc une colonne
 * fichier structurellement vide.
 *
 * Ce que ces tests verrouillent :
 *   - le fichier part sur le disque PRIVÉ (un diplôme n'est pas un avatar) ;
 *   - il ne se lit QUE par URL signée, et une URL non signée est refusée ;
 *   - la pièce reste FACULTATIVE (déclarer maintenant, scanner plus tard) ;
 *   - supprimer la certification supprime le fichier.
 */
class ProviderCertificationFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Crée un utilisateur prestataire (profil validé) et l'authentifie. */
    private function actingAsProvider(): Provider
    {
        $user = User::factory()->create();
        $provider = Provider::factory()->validated()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        return $provider;
    }

    public function test_le_prestataire_joint_un_justificatif(): void
    {
        Storage::fake('local');
        $provider = $this->actingAsProvider();

        $response = $this->post('/api/v1/providers/certifications', [
            'name' => 'Licence de transport',
            'issuer' => 'Ministère des Transports',
            'file' => UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.certification.has_file', true)
            // Le nom d'ORIGINE est conservé : `file_path` est un nom aléatoire,
            // illisible pour l'agent qui contrôlera la pièce.
            ->assertJsonPath('data.certification.original_name', 'licence.pdf');

        $certification = ProviderCertification::firstOrFail();

        $this->assertNotNull($certification->file_path);
        $this->assertSame('local', $certification->disk);
        $this->assertStringStartsWith("certifications/{$provider->id}/", $certification->file_path);
        Storage::disk('local')->assertExists($certification->file_path);

        // Une URL de téléchargement est fournie, et elle est signée.
        $this->assertNotNull($response->json('data.certification.download_url'));
    }

    public function test_le_justificatif_reste_facultatif(): void
    {
        Storage::fake('local');
        $this->actingAsProvider();

        // Choix produit assumé : on doit pouvoir déclarer sa certification
        // tout de suite et revenir déposer le scan plus tard. Le back-office
        // distingue alors « pas de pièce » de « pièce à contrôler ».
        $this->postJson('/api/v1/providers/certifications', [
            'name' => 'Assurance responsabilité civile',
        ])
            ->assertCreated()
            ->assertJsonPath('data.certification.has_file', false)
            ->assertJsonPath('data.certification.original_name', null)
            ->assertJsonPath('data.certification.download_url', null);
    }

    public function test_un_format_de_fichier_interdit_est_refuse(): void
    {
        Storage::fake('local');
        $this->actingAsProvider();

        $this->post('/api/v1/providers/certifications', [
            'name' => 'Certif douteuse',
            'file' => UploadedFile::fake()->create('script.exe', 10),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_le_justificatif_se_telecharge_par_url_signee(): void
    {
        Storage::fake('local');
        $this->actingAsProvider();

        $response = $this->post('/api/v1/providers/certifications', [
            'name' => 'Agrément BTP',
            'file' => UploadedFile::fake()->create('agrement.pdf', 50, 'application/pdf'),
        ])->assertCreated();

        $this->get($response->json('data.certification.download_url'))
            ->assertOk()
            ->assertDownload('agrement.pdf');
    }

    public function test_une_url_non_signee_est_refusee(): void
    {
        Storage::fake('local');
        $this->actingAsProvider();

        $response = $this->post('/api/v1/providers/certifications', [
            'name' => 'Agrément BTP',
            'file' => UploadedFile::fake()->create('agrement.pdf', 50, 'application/pdf'),
        ])->assertCreated();

        $id = $response->json('data.certification.id');

        // Le fichier est privé : sans signature valable, pas d'accès. C'est la
        // seule barrière (la route est volontairement hors `auth:sanctum`,
        // comme pour le KYC), donc elle doit tenir.
        $this->get("/api/v1/providers/certifications/{$id}/download")
            ->assertStatus(403);
    }

    public function test_une_url_signee_expiree_est_refusee(): void
    {
        Storage::fake('local');
        $this->actingAsProvider();

        $response = $this->post('/api/v1/providers/certifications', [
            'name' => 'Agrément BTP',
            'file' => UploadedFile::fake()->create('agrement.pdf', 50, 'application/pdf'),
        ])->assertCreated();

        $url = URL::temporarySignedRoute(
            'providers.certifications.download',
            now()->subMinute(),
            ['certification' => $response->json('data.certification.id')],
        );

        $this->get($url)->assertStatus(403);
    }

    public function test_supprimer_la_certification_supprime_le_fichier(): void
    {
        Storage::fake('local');
        $this->actingAsProvider();

        $response = $this->post('/api/v1/providers/certifications', [
            'name' => 'Attestation à retirer',
            'file' => UploadedFile::fake()->create('attestation.pdf', 30, 'application/pdf'),
        ])->assertCreated();

        $certification = ProviderCertification::firstOrFail();
        $path = $certification->file_path;

        $this->deleteJson("/api/v1/providers/certifications/{$certification->id}")
            ->assertOk();

        // Sans ce nettoyage, on conserverait indéfiniment une pièce
        // personnelle que plus aucune ligne ne référence.
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('provider_certifications', ['id' => $certification->id]);
    }

    public function test_le_back_office_voit_le_nom_du_fichier(): void
    {
        Storage::fake('local');
        $provider = $this->actingAsProvider();

        $this->post('/api/v1/providers/certifications', [
            'name' => 'Licence de transport',
            'file' => UploadedFile::fake()->create('licence-2026.pdf', 40, 'application/pdf'),
        ])->assertCreated();

        // Un agent muni de `gerer:utilisateurs` consulte le module Documents.
        $agent = User::factory()->create();
        $agent->assignRole('admin');
        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/admin/documents?type=certification')
            ->assertOk()
            // C'était LE symptôme visible de la dette : cette colonne affichait
            // `file_path`, que rien ne renseignait — donc toujours vide.
            ->assertJsonPath('data.0.original_name', 'licence-2026.pdf')
            ->assertJsonPath('data.0.subject_id', $provider->id);
    }
}
