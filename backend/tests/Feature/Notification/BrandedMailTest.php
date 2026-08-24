<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Models\Profile;
use App\Modules\Core\Notifications\VerificationCodeNotification;
use App\Modules\Core\Notifications\WelcomeNotification;
use App\Modules\Core\Services\VerificationService;
use App\Support\Mail\BrandedMail;
use App\Support\Mail\MailPreview;
use App\Support\Mail\SpaceLink;
use Tests\TestCase;

/**
 * Tests du GABARIT D'E-MAIL de marque (App\Support\Mail\BrandedMail).
 *
 * Ces tests ne touchent PAS la base : ils rendent les vues à partir de modèles
 * construits en mémoire. C'est volontaire — ce qu'on vérifie ici, c'est la
 * fabrique d'e-mails, pas la persistance ; le jeu de tests reste donc rapide.
 *
 * Ce qui est contrôlé :
 *   · chaque e-mail part bien en DEUX versions (HTML + texte) ;
 *   · la version texte ne contient aucune balise ni entité HTML résiduelle ;
 *   · les liens sont absolus et pointent vers l'espace du BON profil ;
 *   · les montants et les dates sont formatés de façon homogène ;
 *   · les 20 aperçus se rendent tous sans erreur (garde-fou anti-régression :
 *     une propriété renommée sur un modèle casserait un e-mail sans que rien
 *     d'autre ne le signale, puisque l'envoi est asynchrone et silencieux).
 */
class BrandedMailTest extends TestCase
{
    /**
     * Destinataire fictif, non persisté, doté du profil demandé.
     */
    private function recipient(ProfileType $type): object
    {
        $user = new \App\Models\User(['name' => 'Awa Ndiaye', 'email' => 'awa@example.sn']);
        $user->setRelation('profile', new Profile(['type' => $type->value]));

        return $user;
    }

    public function test_un_email_est_envoye_en_html_et_en_texte(): void
    {
        $message = (new WelcomeNotification(ProfileType::CLIENT))
            ->toMail($this->recipient(ProfileType::CLIENT));

        // ->view([html, texte]) : les deux versions partent dans le même envoi.
        $this->assertSame(['emails.branded', 'emails.branded-text'], $message->view);
    }

    public function test_la_version_texte_ne_contient_ni_balise_ni_entite_html(): void
    {
        $text = MailPreview::render('bienvenue-client', text: true);

        // Une entité résiduelle (« c&#039;est ») trahirait un {{ }} oublié.
        $this->assertStringNotContainsString('&#', $text);
        $this->assertStringNotContainsString('<', $text);
        $this->assertStringContainsString('Kaikun 360', $text);
    }

    public function test_le_sujet_porte_toujours_la_marque(): void
    {
        $message = (new WelcomeNotification(ProfileType::ENTREPRISE))
            ->toMail($this->recipient(ProfileType::ENTREPRISE));

        $this->assertStringEndsWith('· Kaikun 360', $message->subject);
    }

    public function test_les_liens_sont_absolus_et_pointent_vers_le_site_public(): void
    {
        $html = MailPreview::render('bienvenue-client');

        // Jamais de chemin relatif dans un e-mail : il n'a pas de page d'origine.
        $this->assertStringContainsString(config('branding.frontend').'/mon-espace', $html);
        $this->assertStringNotContainsString('href="/', $html);
    }

    /**
     * Chaque profil a son propre espace connecté : un lien codé en dur
     * enverrait quatre destinataires sur cinq vers une page inexistante.
     */
    public function test_le_lien_de_preferences_suit_le_profil_du_destinataire(): void
    {
        // Paires [profil, chemin attendu] — un enum ne peut pas servir de clé
        // de tableau en PHP, d'où cette liste plutôt qu'une table associative.
        $cases = [
            [ProfileType::CLIENT, '/mon-espace/notifications'],
            // Depuis la séparation de l'espace diaspora (F18, 2026-08-22).
            [ProfileType::DIASPORA, '/espace-diaspora/notifications'],
            [ProfileType::PROPRIETAIRE, '/espace-proprietaire/notifications'],
            [ProfileType::PRESTATAIRE, '/espace-prestataire/notifications'],
            [ProfileType::ENTREPRISE, '/espace-entreprise/notifications'],
        ];

        foreach ($cases as [$type, $expected]) {
            $this->assertSame($expected, SpaceLink::to($this->recipient($type), 'notifications'), $type->value);
        }
    }

    public function test_le_message_de_bienvenue_est_adapte_a_chaque_profil(): void
    {
        // Un marqueur propre à chaque version : si deux profils recevaient le
        // même texte, la personnalisation ne servirait à rien.
        $attendus = [
            'bienvenue-client' => 'Des offres vérifiées',
            'bienvenue-diaspora' => 'Vous voyez, vous ne croyez pas',
            'bienvenue-proprietaire' => 'Gestion locative optionnelle',
            'bienvenue-prestataire' => 'Kaikun Pro',
            'bienvenue-entreprise' => 'interlocuteur dédié',
        ];

        foreach ($attendus as $key => $marqueur) {
            $this->assertStringContainsString($marqueur, MailPreview::render($key), "Aperçu : {$key}");
        }
    }

    /**
     * Le bandeau « protocole de confiance » est le cœur du positionnement :
     * il doit être sur l'accueil, et NULLE PART ailleurs (sinon il devient du
     * décor que plus personne ne lit).
     */
    public function test_le_bandeau_de_confiance_est_reserve_a_l_email_de_bienvenue(): void
    {
        $this->assertStringContainsString('C\'est un protocole', MailPreview::render('bienvenue-client'));
        $this->assertStringNotContainsString('C\'est un protocole', MailPreview::render('reservation-confirmee'));
    }

    public function test_l_email_de_code_met_en_avant_le_code_et_la_consigne_de_securite(): void
    {
        $html = MailPreview::render('code-verification');

        $this->assertStringContainsString('418 903', $html);
        $this->assertStringContainsString('Ne communiquez ce code à personne', $html);
    }

    /**
     * Le code doit aussi figurer dans l'aperçu de la boîte de réception :
     * sur mobile, beaucoup d'utilisateurs le lisent sans ouvrir le message.
     */
    public function test_le_code_apparait_dans_le_texte_d_apercu(): void
    {
        $message = (new VerificationCodeNotification('123456', VerificationService::PURPOSE_ACCOUNT, 'email'))
            ->toMail($this->recipient(ProfileType::CLIENT));

        $this->assertStringContainsString('123456', $message->viewData['preheader']);
    }

    public function test_les_montants_sont_formates_en_francs_cfa(): void
    {
        // Espace fine insécable entre les milliers, insécable avant l'unité.
        $this->assertSame("1\u{202F}250\u{202F}000\u{00A0}FCFA", BrandedMail::money(1250000));
        $this->assertNull(BrandedMail::money(null));
    }

    public function test_les_dates_sont_ecrites_en_toutes_lettres(): void
    {
        // 09/07 se lit « 9 juillet » ici et « 7 septembre » ailleurs : le mois
        // en toutes lettres supprime l'ambiguïté pour la diaspora.
        $this->assertSame('14 septembre 2026', BrandedMail::date('2026-09-14'));
        $this->assertNull(BrandedMail::date(null));
    }

    /**
     * Les lignes vides d'un récapitulatif sont ignorées : une notification peut
     * ainsi proposer des champs facultatifs sans produire « Ville : » tout seul.
     */
    public function test_les_lignes_vides_du_recapitulatif_sont_ignorees(): void
    {
        $payload = BrandedMail::make()
            ->facts(['Référence' => 'AB-1', 'Ville' => null, 'Budget' => ''])
            ->payload();

        $this->assertSame(['Référence' => 'AB-1'], $payload['facts']);
    }

    /**
     * Garde-fou global : les 20 e-mails du catalogue se rendent sans erreur,
     * dans leurs deux versions. C'est le test qui rattrapera une propriété de
     * modèle renommée — un envoi de notification échoue en silence, en file.
     */
    public function test_tous_les_emails_du_catalogue_se_rendent(): void
    {
        foreach (array_keys(MailPreview::catalog()) as $key) {
            $this->assertNotEmpty(MailPreview::render($key), "Version HTML : {$key}");
            $this->assertNotEmpty(MailPreview::render($key, text: true), "Version texte : {$key}");
        }
    }
}
