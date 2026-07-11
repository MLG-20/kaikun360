<?php

namespace Tests\Feature\Admin;

use App\Models\Faq;
use App\Models\Page;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B13.4 : contenu éditorial (FAQ & pages). Lecture publique / édition
 * réservée à `gerer:parametres`.
 */
class EditorialContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);

        return $admin;
    }

    // --- FAQ -----------------------------------------------------------------

    public function test_la_faq_publique_ne_montre_que_les_entrees_publiees(): void
    {
        Faq::factory()->count(2)->create();
        Faq::factory()->hidden()->create();

        $this->getJson('/api/v1/faqs')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_un_non_admin_ne_gere_pas_la_faq(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/faqs')->assertStatus(403);
        $this->postJson('/api/v1/admin/faqs', ['question' => 'Q ?', 'answer' => 'R'])->assertStatus(403);
    }

    public function test_l_admin_cree_modifie_et_supprime_une_faq(): void
    {
        Sanctum::actingAs($this->admin());

        $id = $this->postJson('/api/v1/admin/faqs', [
            'question' => 'Comment payer ?',
            'answer' => 'Par Mobile Money.',
            'category' => 'paiement',
        ])->assertCreated()->json('data.faq.id');

        $this->patchJson("/api/v1/admin/faqs/{$id}", ['is_published' => false])
            ->assertOk()
            ->assertJsonPath('data.faq.is_published', false);

        $this->deleteJson("/api/v1/admin/faqs/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('faqs', ['id' => $id]);
    }

    // --- Pages ---------------------------------------------------------------

    public function test_une_page_publiee_est_lisible_par_slug(): void
    {
        Page::factory()->create(['slug' => 'a-propos', 'is_published' => true]);

        $this->getJson('/api/v1/pages/a-propos')
            ->assertOk()
            ->assertJsonPath('data.page.slug', 'a-propos');
    }

    public function test_une_page_non_publiee_ou_absente_donne_404(): void
    {
        Page::factory()->draft()->create(['slug' => 'brouillon']);

        $this->getJson('/api/v1/pages/brouillon')->assertStatus(404);
        $this->getJson('/api/v1/pages/inexistante')->assertStatus(404);
    }

    public function test_l_admin_cree_une_page_et_refuse_un_slug_duplique(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/admin/pages', [
            'slug' => 'cgu',
            'title' => 'Conditions générales',
            'body' => 'Texte des CGU.',
        ])->assertCreated();

        // Slug déjà pris.
        $this->postJson('/api/v1/admin/pages', [
            'slug' => 'cgu',
            'title' => 'Autre',
            'body' => 'Autre texte.',
        ])->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    public function test_l_admin_met_a_jour_et_supprime_une_page(): void
    {
        $page = Page::factory()->create(['slug' => 'aide']);

        Sanctum::actingAs($this->admin());

        $this->patchJson('/api/v1/admin/pages/aide', ['title' => 'Aide & support'])
            ->assertOk()
            ->assertJsonPath('data.page.title', 'Aide & support');

        $this->deleteJson('/api/v1/admin/pages/aide')->assertNoContent();
        $this->assertDatabaseMissing('pages', ['slug' => 'aide']);
    }
}
