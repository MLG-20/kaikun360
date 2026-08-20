<?php

namespace App\Console\Commands;

use App\Models\Page;
use Database\Seeders\PublicPagesSeeder;
use Illuminate\Console\Command;

/**
 * Réécrit UNE page légale existante avec le texte actuel de
 * `PublicPagesSeeder` (F16, 2026-08-20).
 *
 * POURQUOI CETTE COMMANDE EXISTE
 * -------------------------------
 * `PublicPagesSeeder` utilise volontairement `firstOrCreate` : une fois une
 * page en base, le seeder ne la touche plus jamais, pour ne pas écraser un
 * texte relu au back-office (`PATCH /admin/pages`). Ça pose un problème
 * inverse quand la mise à jour vient au contraire DU CODE : après l'ajout de
 * Google Analytics, la « Politique de cookies » et la « Politique de
 * confidentialité » ont été réécrites dans le seeder, mais une base déjà
 * peuplée — locale ou le VPS de production — continue de servir l'ancien
 * texte tant que personne ne recolle le nouveau à la main.
 *
 * Cette commande fait exactement ce geste manuel, sans y ajouter de nouvelle
 * page ni toucher les autres : un slug donné, réécrit avec `pages()` du
 * seeder, rien d'autre. Elle n'est PAS un remplacement du seeder — juste le
 * complément pour le cas « le code a changé, la base doit suivre ».
 *
 * ⚠️ **Écrase sans confirmation.** Si le back-office a corrigé cette page
 * depuis la dernière relecture juridique, cette correction est perdue — d'où
 * `--dry-run` pour comparer avant d'écrire.
 */
class ResyncPublicPageCommand extends Command
{
    protected $signature = 'pages:resynchroniser
        {slug : Le slug de la page (ex. politique-cookies)}
        {--dry-run : Affiche le nouveau texte sans rien écrire}';

    protected $description = "Réécrit une page légale avec le texte actuel du seeder (PublicPagesSeeder)";

    public function handle(PublicPagesSeeder $seeder): int
    {
        $slug = (string) $this->argument('slug');
        $pages = $seeder->pages();

        if (! array_key_exists($slug, $pages)) {
            $this->error("Aucune page « {$slug} » dans PublicPagesSeeder.");

            return self::FAILURE;
        }

        $page = $pages[$slug];

        $modele = Page::query()->where('slug', $slug)->first();

        if (! $modele) {
            $this->error("Aucune page « {$slug} » en base — utilisez le seeder pour la créer.");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->line($page['body']);
            $this->info("Simulation : « {$slug} » ne sera pas modifiée.");

            return self::SUCCESS;
        }

        $modele->update([
            'title' => $page['title'],
            'body' => $page['body'],
        ]);

        $this->info("Page « {$slug} » resynchronisée avec le seeder.");

        return self::SUCCESS;
    }
}
