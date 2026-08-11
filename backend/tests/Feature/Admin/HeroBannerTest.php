<?php

namespace Tests\Feature\Admin;

use App\Models\HeroBanner;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Support\Heroes\HeroCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F12 : bandeaux d'en-tête pilotés au back-office.
 *
 * Le cœur de la fonctionnalité n'est pas le téléversement (déjà éprouvé par les
 * médias d'annonce) mais **l'héritage d'image** et son asymétrie avec le texte :
 * une image chargée sur un univers doit descendre sur ses pages filles, un
 * titre écrit pour un univers ne doit surtout pas descendre.
 */
class HeroBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
        HeroCatalog::flush();
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('hero.jpg', 1920, 900);
    }

    public function test_l_edition_est_reservee_a_gerer_parametres(): void
    {
        // L'agent a consulter:dashboard-admin mais PAS gerer:parametres.
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->getJson('/api/v1/admin/heroes')->assertStatus(403);
        $this->postJson('/api/v1/admin/heroes/immobilier', [])->assertStatus(403);
    }

    public function test_liste_tous_les_bandeaux_connus_meme_vierges(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->getJson('/api/v1/admin/heroes')
            ->assertOk()
            ->assertJsonCount(count(HeroCatalog::BANNERS), 'data.heroes')
            ->assertJsonFragment([
                'key' => 'immobilier',
                'label' => 'Immobilier',
                'image' => null,
                'inherited_image' => null,
            ]);
    }

    public function test_une_cle_inconnue_est_refusee(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/heroes/page-inventee', ['title' => 'Bonjour'])
            ->assertStatus(404);

        $this->assertDatabaseCount('hero_banners', 0);
    }

    public function test_depose_une_image_et_des_textes_en_un_seul_envoi(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/heroes/immobilier', [
            'image' => $this->image(),
            'eyebrow' => 'Univers Immobilier',
            'title' => 'Des biens vérifiés, prêts à visiter',
            'lead' => 'Chaque annonce est contrôlée avant sa mise en ligne.',
        ])->assertOk();

        $banner = HeroBanner::where('key', 'immobilier')->firstOrFail();

        $this->assertNotNull($banner->image_path);
        Storage::disk('public')->assertExists($banner->image_path);
        $this->assertSame('Des biens vérifiés, prêts à visiter', $banner->title);
    }

    /**
     * Une vignette n'est pas un fond de page.
     *
     * Le cas vient de la recette : une image de 360 × 360 px avait été déposée
     * comme fond, et le bandeau paraissait flou sans qu'aucune erreur ne le
     * signale. Rien ne peut le rattraper après coup — `ImageProcessor` réduit,
     * il n'agrandit pas ; le refus à l'envoi est donc le seul moment où l'équipe
     * peut encore l'apprendre.
     */
    public function test_une_image_trop_petite_pour_un_fond_est_refusee(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/heroes/immobilier', [
            'image' => UploadedFile::fake()->image('vignette.jpg', 360, 360),
        ])->assertStatus(422)->assertJsonValidationErrors('image');

        // Rien n'a été écrit : ni ligne en base, ni fichier sur le disque.
        $this->assertDatabaseCount('hero_banners', 0);
    }

    public function test_l_image_d_un_univers_descend_sur_sa_page_de_resultats(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        // Une seule photo chargée, sur l'univers Nuitées…
        $this->post('/api/v1/admin/heroes/nuitees', ['image' => $this->image()])->assertOk();

        $heroes = $this->getJson('/api/v1/heroes')->assertOk()->json('data.heroes');

        // …et la page de résultats filtrée sur les nuitées l'affiche déjà.
        $this->assertNotNull($heroes['recherche.nuitees']['image']);
        $this->assertSame($heroes['nuitees']['image'], $heroes['recherche.nuitees']['image']);

        // Mais elle ne déborde pas sur un univers voisin.
        $this->assertArrayNotHasKey('recherche.tourisme', $heroes);
    }

    public function test_le_bandeau_par_defaut_couvre_les_pages_sans_image(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/heroes/defaut', ['image' => $this->image()])->assertOk();

        $heroes = $this->getJson('/api/v1/heroes')->assertOk()->json('data.heroes');

        // Toutes les clés connues héritent de la racine : aucune n'est absente.
        $this->assertCount(count(HeroCatalog::BANNERS), $heroes);
        $this->assertSame($heroes['defaut']['image'], $heroes['contact']['image']);
    }

    public function test_une_image_propre_l_emporte_sur_celle_du_parent(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/heroes/defaut', ['image' => $this->image()])->assertOk();
        $this->post('/api/v1/admin/heroes/tourisme', ['image' => $this->image()])->assertOk();

        $heroes = $this->getJson('/api/v1/heroes')->assertOk()->json('data.heroes');

        $this->assertNotSame($heroes['defaut']['image'], $heroes['tourisme']['image']);
        // Et la page fille suit bien son parent direct, pas la racine.
        $this->assertSame($heroes['tourisme']['image'], $heroes['recherche.tourisme']['image']);
    }

    public function test_le_texte_n_est_jamais_herite(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/heroes/immobilier', [
            'image' => $this->image(),
            'title' => 'Des biens vérifiés',
        ])->assertOk();

        $heroes = $this->getJson('/api/v1/heroes')->assertOk()->json('data.heroes');

        // La page de résultats hérite de la PHOTO mais garde son propre titre :
        // « Des biens vérifiés » n'a aucun sens au-dessus d'une liste filtrée.
        $this->assertNotNull($heroes['recherche.immobilier']['image']);
        $this->assertNull($heroes['recherche.immobilier']['title']);
    }

    public function test_un_texte_vide_rend_a_la_page_son_libelle_d_origine(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/heroes/contact', ['title' => 'Écrivez-nous'])->assertOk();
        $this->assertSame('Écrivez-nous', HeroBanner::where('key', 'contact')->first()->title);

        // Champ vidé puis enregistré = retrait de la surcharge, et non un titre
        // vide qui laisserait la page sans en-tête.
        $this->post('/api/v1/admin/heroes/contact', ['title' => ''])->assertOk();

        $this->assertNull(HeroBanner::where('key', 'contact')->first()->title);
        $this->assertArrayNotHasKey('contact', $this->getJson('/api/v1/heroes')->json('data.heroes'));
    }

    public function test_remplacer_l_image_efface_l_ancien_fichier(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/heroes/pro', ['image' => $this->image()])->assertOk();
        $premier = HeroBanner::where('key', 'pro')->firstOrFail()->image_path;

        $this->post('/api/v1/admin/heroes/pro', ['image' => $this->image()])->assertOk();
        $second = HeroBanner::where('key', 'pro')->firstOrFail()->image_path;

        $this->assertNotSame($premier, $second);
        Storage::disk('public')->assertMissing($premier);
        Storage::disk('public')->assertExists($second);
    }

    public function test_retirer_l_image_fait_retomber_la_page_sur_son_parent(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/heroes/defaut', ['image' => $this->image()])->assertOk();
        $this->post('/api/v1/admin/heroes/diaspora', ['image' => $this->image()])->assertOk();

        $this->post('/api/v1/admin/heroes/diaspora', ['remove_image' => true])->assertOk();

        $heroes = $this->getJson('/api/v1/heroes')->assertOk()->json('data.heroes');

        $this->assertSame($heroes['defaut']['image'], $heroes['diaspora']['image']);
    }

    public function test_reinitialiser_un_bandeau_efface_image_et_textes(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/heroes/faqs', [
            'image' => $this->image(),
            'title' => 'Vos questions',
        ])->assertOk();

        $chemin = HeroBanner::where('key', 'faqs')->firstOrFail()->image_path;

        $this->deleteJson('/api/v1/admin/heroes/faqs')->assertOk();

        $this->assertDatabaseMissing('hero_banners', ['key' => 'faqs']);
        Storage::disk('public')->assertMissing($chemin);
    }

    public function test_la_lecture_publique_est_ouverte_et_vide_par_defaut(): void
    {
        // Aucune authentification : c'est une page vitrine.
        $this->getJson('/api/v1/heroes')
            ->assertOk()
            // Une plateforme fraîchement installée renvoie une MAP vide, pas un
            // tableau JSON : le frontend indexe la réponse par clé.
            ->assertExactJson(['data' => ['heroes' => []]]);
    }

    public function test_l_ecran_back_office_montre_l_image_reellement_affichee(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->post('/api/v1/admin/heroes/mobilite', ['image' => $this->image()])->assertOk();

        $heroes = collect($this->getJson('/api/v1/admin/heroes')->json('data.heroes'))
            ->keyBy('key');

        // La page fille n'a pas d'image PROPRE (le bouton « retirer » n'aurait
        // rien à retirer) mais elle en affiche bien une : les deux champs
        // doivent raconter cette nuance, sinon l'équipe recharge la même photo
        // sur chaque page.
        $this->assertNull($heroes['recherche.mobilite']['image']);
        $this->assertNotNull($heroes['recherche.mobilite']['inherited_image']);
        $this->assertSame('Mobilité', $heroes['recherche.mobilite']['parent_label']);
    }
}
