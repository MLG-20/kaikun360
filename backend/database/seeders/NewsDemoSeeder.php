<?php

namespace Database\Seeders;

use App\Models\NewsArticle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Trois articles « Actualités Kaikun » publiés (F15), pour voir la section en
 * direct au navigateur avec plusieurs cartes empilées — notamment l'embed
 * vidéo, qui ne se juge pas dans un test.
 *
 * Optionnel, jamais lancé par `DatabaseSeeder` (comme `DemoSeeder`) :
 *
 *   php artisan db:seed --class=NewsDemoSeeder
 *
 * L'image de couverture est OBLIGATOIRE côté modèle (voir `NewsArticle`) :
 * réutiliser de vraies photos déjà en local (bandeaux ou médias déposés au
 * back-office), une différente par article, plutôt qu'un chemin fictif —
 * sinon la carte affiche une image cassée. Repli sur une image générée si
 * l'environnement est vierge.
 */
class NewsDemoSeeder extends Seeder
{
    /**
     * @var list<array{title: string, excerpt: string, body: string}>
     */
    private const ARTICLES = [
        [
            'title' => 'Kaikun 360 lance son offre Nuitées vérifiées',
            'excerpt' => 'Des hébergements contrôlés avant publication, photographiés et suivis comme le reste de la plateforme.',
            'body' => 'Chaque logement publié sur Kaikun 360 passe par la même vérification que nos biens à vendre ou à louer : documents contrôlés, visite filmée, et un interlocuteur unique pour réserver en confiance, où que vous soyez.',
        ],
        [
            'title' => 'Kaikun Diaspora : un suivi de chantier filmé à chaque étape',
            'excerpt' => 'Investir ou construire à distance, sans jamais perdre le fil de l’avancement de votre projet.',
            'body' => 'Fondations, gros œuvre, second œuvre, livraison : chaque étape est photographiée et datée, consultable depuis votre espace, où que vous soyez dans le monde.',
        ],
        [
            'title' => 'Kaikun Mobilité étend ses navettes vers de nouvelles régions',
            'excerpt' => 'De nouveaux départs programmés, aux horaires annoncés, pour voyager au Sénégal sans mauvaise surprise.',
            'body' => 'Les navettes et véhicules avec chauffeur couvrent désormais davantage de trajets inter-régions, avec les mêmes garanties que le reste de la plateforme : véhicules vérifiés, horaires fiables, suivi du trajet.',
        ],
    ];

    public function run(): void
    {
        foreach (self::ARTICLES as $position => $article) {
            NewsArticle::query()->updateOrCreate(
                ['title' => $article['title']],
                [
                    'excerpt' => $article['excerpt'],
                    'body' => $article['body'],
                    'image_path' => $this->coverImage($position),
                    'video_path' => null,
                    // Laissée vide volontairement : à coller depuis le
                    // back-office (onglet Actualités) — n'importe quel lien
                    // YouTube/Vimeo « normal » convient désormais
                    // (VideoEmbedUrl le convertit au format embed).
                    'video_url' => null,
                    'is_published' => true,
                    'position' => $position,
                ],
            );
        }
    }

    /**
     * Une photo réelle différente par article (indexée par `$position`),
     * plutôt que la même recopiée trois fois.
     */
    private function coverImage(int $position): string
    {
        $disk = Storage::disk('public');

        $candidates = collect($disk->exists('heroes') ? $disk->files('heroes') : [])
            ->merge($disk->exists('media') ? $disk->files('media') : [])
            ->filter(fn (string $path) => str_ends_with(strtolower($path), '.jpg') || str_ends_with(strtolower($path), '.jpeg'))
            ->values();

        $existing = $candidates->isNotEmpty() ? $candidates[$position % $candidates->count()] : null;

        $target = 'news/'.Str::uuid()->toString().'.jpg';

        $disk->put($target, $existing ? $disk->get($existing) : $this->placeholderJpeg($position));

        return $target;
    }

    /**
     * Environnement vierge : aucune photo à réutiliser, on en fabrique une
     * plutôt que de pointer vers un fichier qui n'existe pas.
     */
    private function placeholderJpeg(int $position): string
    {
        $image = imagecreatetruecolor(1200, 720);
        imagefill($image, 0, 0, imagecolorallocate($image, 3, 25, 63));
        imagestring($image, 5, 40, 40, 'Kaikun 360 - Actualite '.($position + 1), imagecolorallocate($image, 255, 255, 255));

        ob_start();
        imagejpeg($image, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
