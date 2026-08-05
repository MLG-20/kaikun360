<?php

namespace Tests\Feature\Transversal;

use App\Models\Page;
use Database\Seeders\ContentSeeder;
use Database\Seeders\PublicPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests F8.15.e : les pages légales et institutionnelles du site public.
 *
 * Le CDC §4.2 impose six pages légales (« CGU, CGV, confidentialité, cookies,
 * conditions de mandat, politique de remboursement »). Quatre manquaient. Ces
 * tests verrouillent deux choses distinctes :
 *
 * 1. **Le contenu existe et est atteignable** — une page seedée mais non
 *    publiée, ou un slug qui ne correspond pas au lien du pied de page,
 *    produirait un 404 sur une page que la loi impose.
 * 2. **Le seeder pose les pages MANQUANTES sur une base déjà remplie** — c'est
 *    exactement ce que l'ancien `ContentSeeder` ne savait pas faire, et la
 *    raison pour laquelle ajouter une page à sa liste n'aurait rien produit en
 *    production. Sans ce test, la régression est invisible : le seeder répond
 *    « rien à faire » et personne ne voit qu'il manque une page.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les six pages légales du CDC §4.2, indexées par le libellé du cahier des
     * charges — ce libellé sert de message d'échec, pour qu'un test rouge dise
     * quelle EXIGENCE n'est pas couverte et pas seulement quel slug manque.
     *
     * @return array<string, string> exigence CDC => slug attendu
     */
    private const PAGES_LEGALES_DU_CDC = [
        'CGU' => 'cgu',
        'CGV' => 'cgv',
        'confidentialité' => 'politique-confidentialite',
        'cookies' => 'politique-cookies',
        'conditions de mandat' => 'conditions-de-mandat',
        'politique de remboursement' => 'politique-annulation-remboursement',
    ];

    public function test_chaque_page_legale_du_cdc_est_seedee_publiee_et_servie_publiquement(): void
    {
        $this->seed(PublicPagesSeeder::class);

        foreach (self::PAGES_LEGALES_DU_CDC as $exigence => $slug) {
            $page = Page::query()->where('slug', $slug)->first();

            $this->assertNotNull($page, "Page manquante pour l'exigence CDC §4.2 « {$exigence} » (slug attendu : {$slug}).");
            $this->assertTrue($page->is_published, "La page « {$exigence} » est en base mais non publiée : elle répondrait 404.");

            // Aucune authentification : ces pages sont publiques par nature.
            $this->getJson("/api/v1/pages/{$slug}")
                ->assertOk()
                ->assertJsonPath('data.page.slug', $slug)
                ->assertJsonPath('data.page.title', $page->title);
        }
    }

    public function test_la_page_a_propos_liee_au_pied_de_page_reste_servie(): void
    {
        // Non exigée par le §4.2 mais câblée dans le pied de page depuis F2.8 :
        // la déplacer de seeder ne doit pas casser le lien.
        $this->seed(PublicPagesSeeder::class);

        $this->getJson('/api/v1/pages/a-propos')->assertOk();
        $this->getJson('/api/v1/pages/mentions-legales')->assertOk();
    }

    public function test_le_seeder_est_idempotent_et_ne_duplique_aucune_page(): void
    {
        $this->seed(PublicPagesSeeder::class);
        $attendu = Page::query()->count();

        $this->seed(PublicPagesSeeder::class);

        $this->assertSame($attendu, Page::query()->count());
    }

    public function test_le_seeder_ne_reecrit_jamais_une_page_editee_au_back_office(): void
    {
        $this->seed(PublicPagesSeeder::class);

        // L'équipe remplace le texte de travail par la version du juriste.
        Page::query()->where('slug', 'cgv')->update([
            'title' => 'CGV — version validée',
            'body' => '<p>Texte relu par le conseil juridique.</p>',
        ]);

        $this->seed(PublicPagesSeeder::class);

        $cgv = Page::query()->where('slug', 'cgv')->firstOrFail();
        $this->assertSame('CGV — version validée', $cgv->title);
        $this->assertSame('<p>Texte relu par le conseil juridique.</p>', $cgv->body);
    }

    public function test_une_page_manquante_est_posee_meme_si_la_base_en_contient_deja(): void
    {
        // Le défaut historique : `ContentSeeder` s'arrêtait dès qu'UNE page
        // existait, donc toute base réelle. On simule cet état — une seule page
        // en base — et on vérifie que les autres arrivent quand même.
        Page::query()->create([
            'slug' => 'cgu',
            'title' => "Conditions générales d'utilisation",
            'body' => '<p>Déjà en base avant la livraison.</p>',
            'is_published' => true,
        ]);

        $this->seed(PublicPagesSeeder::class);

        foreach (self::PAGES_LEGALES_DU_CDC as $exigence => $slug) {
            $this->assertTrue(
                Page::query()->where('slug', $slug)->exists(),
                "« {$exigence} » n'a pas été posée sur une base qui contenait déjà une page.",
            );
        }
    }

    public function test_content_seeder_pose_les_pages_sans_toucher_a_la_faq_deja_presente(): void
    {
        // ContentSeeder délègue les pages et garde sa propre idempotence pour la
        // FAQ : le relancer doit être sûr dans les deux sens.
        $this->seed(ContentSeeder::class);
        $faqs = \App\Models\Faq::query()->count();
        $pages = Page::query()->count();

        $this->seed(ContentSeeder::class);

        $this->assertSame($faqs, \App\Models\Faq::query()->count());
        $this->assertSame($pages, Page::query()->count());
        $this->assertGreaterThan(0, $faqs);
    }
}
