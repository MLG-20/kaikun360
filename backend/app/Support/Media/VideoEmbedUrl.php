<?php

namespace App\Support\Media;

/**
 * Normalise un lien vidéo « partagé » (YouTube, Vimeo) en lien D'INTÉGRATION
 * (embed) — le seul qui accepte de s'afficher dans une `<iframe>`.
 *
 * ## Le problème réel
 *
 * Les deux écrans qui acceptent ce champ (Actualités F15, héros de l'accueil
 * F15.1) affichent tous deux « …ou lien vidéo (YouTube, Vimeo) », et le champ
 * Actualités montre même `https://www.youtube.com/watch?v=…` comme exemple —
 * exactement l'adresse qu'un navigateur affiche et qu'un bouton « Partager »
 * copie. Or une page `/watch` **refuse d'être encadrée**
 * (`X-Frame-Options`/CSP) : collée telle quelle, la vidéo n'apparaît jamais,
 * sans erreur visible pour qui la dépose. Seule l'adresse `/embed/ID`
 * l'accepte. Cette classe convertit donc ce qu'un agent colle réellement en
 * ce que l'`<iframe>` du site peut afficher.
 */
class VideoEmbedUrl
{
    public static function normalize(?string $url): ?string
    {
        $url = $url !== null ? trim($url) : '';

        if ($url === '') {
            return null;
        }

        if ($id = self::youtubeId($url)) {
            $embed = "https://www.youtube.com/embed/{$id}";
            $start = self::youtubeStartSeconds($url);

            return $start !== null ? "{$embed}?start={$start}" : $embed;
        }

        if ($id = self::vimeoId($url)) {
            return "https://player.vimeo.com/video/{$id}";
        }

        // Lien déjà au format embed, ou fournisseur non reconnu : inchangé —
        // mieux vaut laisser passer une adresse qu'on ne comprend pas que la
        // rejeter et priver l'équipe d'un fournisseur qu'on n'a pas prévu.
        return $url;
    }

    private static function youtubeId(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return null;
        }

        if (str_ends_with($host, 'youtu.be')) {
            $id = trim((string) parse_url($url, PHP_URL_PATH), '/');

            return $id !== '' ? $id : null;
        }

        if (! str_contains($host, 'youtube.com')) {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        if (preg_match('#^/(?:embed|shorts)/([\w-]+)#', $path, $m)) {
            return $m[1];
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['v'] ?? null;
    }

    /**
     * `&t=90s` ou `&t=90` sur un lien /watch → secondes, pour reprendre la
     * vidéo au même endroit dans l'embed plutôt que de perdre le repère que
     * l'agent avait choisi en copiant le lien.
     */
    private static function youtubeStartSeconds(string $url): ?int
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $t = $query['t'] ?? $query['start'] ?? null;

        if ($t === null) {
            return null;
        }

        return preg_match('/^(\d+)/', (string) $t, $m) ? (int) $m[1] : null;
    }

    private static function vimeoId(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host !== 'vimeo.com' && $host !== 'www.vimeo.com') {
            return null;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return preg_match('#^(\d+)#', $path, $m) ? $m[1] : null;
    }
}
