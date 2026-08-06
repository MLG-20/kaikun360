<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la localisation des messages de validation (dette soldée le
 * 2026-08-06).
 *
 * ⚠️ **Ce que ces tests empêchent de revenir.** Le projet tournait bien en locale
 * `fr` — c'était réglé dans `.env` depuis le début — mais **aucun dossier
 * `lang/` n'existait**, et `APP_FALLBACK_LOCALE` valait lui aussi `fr` : le repli
 * pointait sur le même dossier vide et ne rattrapait rien. Laravel ne résolvant
 * un message que s'il trouve une traduction, il renvoyait la **clé brute**. Sur
 * `POST /api/v1/contact` — endpoint **public**, canal de conversion prioritaire
 * du CDC §4.1 — de vrais visiteurs lisaient « validation.required ».
 *
 * ⚠️ **Le défaut était invisible sur les écrans les plus soignés** : les
 * `FormRequest` définissant leurs propres `messages()` répondaient correctement
 * en français, ce qui donnait l'impression que tout allait bien. Un test qui se
 * contenterait de vérifier `assertJsonValidationErrors('name')` serait passé au
 * vert pendant tout ce temps — il ne regarde que les CLÉS d'erreur, jamais le
 * texte. D'où des assertions portant sur le **contenu du message**.
 */
class ValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Endpoints représentatifs : un **public** (le cas qui fuyait réellement) et
     * un derrière authentification, pour couvrir les deux régimes.
     *
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: string}>
     */
    private function casDeValidation(): array
    {
        return [
            // Le cas historique : formulaire public de la page Contact.
            'contact public' => ['/api/v1/contact', [], 'name'],
            // Inscription : porte déjà des `messages()` explicites, on vérifie
            // qu'ils continuent de primer.
            'inscription' => ['/api/v1/auth/register', [], 'email'],
        ];
    }

    public function test_aucun_endpoint_ne_renvoie_une_cle_de_traduction_brute(): void
    {
        foreach ($this->casDeValidation() as $nom => [$url, $payload, $champ]) {
            $reponse = $this->postJson($url, $payload)->assertStatus(422);

            $message = $reponse->json("errors.{$champ}.0");

            $this->assertIsString($message, "Aucun message d'erreur pour « {$nom} ».");
            // Le cœur du test : une clé brute commence par « validation. ».
            $this->assertStringNotContainsString(
                'validation.',
                $message,
                "L'endpoint « {$nom} » fuit une clé de traduction brute : « {$message} ».",
            );

            // Et le résumé global, qui compose le message de l'exception.
            $this->assertStringNotContainsString('validation.', (string) $reponse->json('message'));
        }
    }

    public function test_les_messages_publics_sont_en_francais_et_nomment_le_champ_lisiblement(): void
    {
        $reponse = $this->postJson('/api/v1/contact', [])->assertStatus(422);

        // ⚠️ « nom » et non « name » : sans la table `attributes`, le message
        // citerait le nom TECHNIQUE de la colonne — « Le champ commune_id est
        // obligatoire » ne veut rien dire pour un visiteur.
        $this->assertSame('Le champ nom est obligatoire.', $reponse->json('errors.name.0'));
        $this->assertSame(
            'Le champ adresse e-mail est obligatoire.',
            $reponse->json('errors.email.0'),
        );
    }

    public function test_le_resume_de_l_exception_est_traduit_lui_aussi(): void
    {
        // Laravel compose « … (and N more errors) » via le traducteur, avec une
        // clé SANS point : elle se résout dans `lang/fr.json`, pas dans
        // `lang/fr/validation.php`. Oublier ce fichier laissait une bribe
        // anglaise au milieu d'un message français.
        $message = (string) $this->postJson('/api/v1/contact', [])->json('message');

        $this->assertStringContainsString('autres erreurs', $message);
        $this->assertStringNotContainsString('more errors', $message);
    }

    public function test_une_regle_absente_de_la_traduction_francaise_reste_lisible(): void
    {
        // ⚠️ **Le vrai filet de sécurité, et le cœur de la correction.** La
        // locale était DÉJÀ `fr` avant cette tranche : le défaut ne venait pas
        // de là, mais de l'absence de `lang/fr` — doublée d'un repli qui valait
        // lui aussi `fr`, donc pointait sur le même dossier vide et ne
        // rattrapait rien.
        //
        // On teste le COMPORTEMENT et non les valeurs de config : ce qui compte
        // n'est pas que le repli s'appelle « en », c'est qu'une règle non
        // traduite produise une phrase lisible. Un test sur `config()` passerait
        // au vert avec un dossier de repli vide.
        $this->app->setLocale('fr');

        foreach (['uuid', 'ulid', 'timezone', 'mac_address', 'multiple_of'] as $regle) {
            $traduit = __("validation.{$regle}", ['attribute' => 'identifiant', 'value' => 2]);

            $this->assertStringNotContainsString(
                'validation.',
                $traduit,
                "La règle « {$regle} » ressort en clé brute : ni traduite, ni rattrapée par le repli.",
            );
        }
    }

    public function test_le_repli_ne_pointe_pas_sur_la_locale_elle_meme(): void
    {
        // Ce réglage était `fr` dans `.env` : un repli identique à la locale est
        // un repli qui ne replie sur rien. Le laisser revenir rouvrirait la
        // brèche sans qu'aucun autre test ne s'en aperçoive tant que `lang/fr`
        // est complet — c'est-à-dire jusqu'au jour où Laravel ajoute une règle.
        $this->assertNotSame(
            config('app.locale'),
            config('app.fallback_locale'),
            'Le repli de traduction doit différer de la locale, sinon il ne rattrape aucune clé manquante.',
        );
    }
}
