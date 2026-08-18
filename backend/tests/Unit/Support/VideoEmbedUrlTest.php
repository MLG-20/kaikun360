<?php

namespace Tests\Unit\Support;

use App\Support\Media\VideoEmbedUrl;
use PHPUnit\Framework\TestCase;

/**
 * Ce que ce test protège n'est pas « les regex marchent » — c'est que le lien
 * réellement copié depuis la barre d'adresse ou le bouton « Partager »
 * (`/watch?v=…`, `youtu.be/…`) devienne l'adresse `/embed/…` qui accepte
 * d'être encadrée. Trouvé en le vivant : un utilisateur a collé exactement
 * le texte d'exemple du champ Actualités, et la vidéo n'est jamais apparue.
 */
class VideoEmbedUrlTest extends TestCase
{
    public function test_convertit_un_lien_youtube_watch_classique(): void
    {
        $this->assertSame(
            'https://www.youtube.com/embed/wHvapfKeviA',
            VideoEmbedUrl::normalize('https://www.youtube.com/watch?v=wHvapfKeviA'),
        );
    }

    public function test_reprend_lhorodatage_dun_lien_youtube_en_parametre_start(): void
    {
        $this->assertSame(
            'https://www.youtube.com/embed/wHvapfKeviA?start=15',
            VideoEmbedUrl::normalize('https://www.youtube.com/watch?v=wHvapfKeviA&t=15s'),
        );
    }

    public function test_convertit_un_lien_court_youtube_be(): void
    {
        $this->assertSame(
            'https://www.youtube.com/embed/wHvapfKeviA',
            VideoEmbedUrl::normalize('https://youtu.be/wHvapfKeviA'),
        );
    }

    public function test_laisse_un_lien_youtube_deja_au_format_embed_inchange(): void
    {
        $this->assertSame(
            'https://www.youtube.com/embed/wHvapfKeviA',
            VideoEmbedUrl::normalize('https://www.youtube.com/embed/wHvapfKeviA'),
        );
    }

    public function test_convertit_un_lien_vimeo_classique(): void
    {
        $this->assertSame(
            'https://player.vimeo.com/video/76979871',
            VideoEmbedUrl::normalize('https://vimeo.com/76979871'),
        );
    }

    public function test_laisse_un_fournisseur_non_reconnu_inchange(): void
    {
        $this->assertSame(
            'https://exemple.test/ma-video.mp4',
            VideoEmbedUrl::normalize('https://exemple.test/ma-video.mp4'),
        );
    }

    public function test_rend_null_pour_une_valeur_vide_ou_absente(): void
    {
        $this->assertNull(VideoEmbedUrl::normalize(null));
        $this->assertNull(VideoEmbedUrl::normalize(''));
        $this->assertNull(VideoEmbedUrl::normalize('   '));
    }
}
