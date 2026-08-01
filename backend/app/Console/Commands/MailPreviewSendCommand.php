<?php

namespace App\Console\Commands;

use App\Support\Mail\MailPreview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie POUR DE VRAI les e-mails d'aperçu à une adresse, afin de les relire
 * dans un vrai client de messagerie.
 *
 * POURQUOI, alors qu'il existe déjà l'aperçu navigateur (`/apercu-emails`) ?
 * Parce que le navigateur ne dit RIEN de ce qui compte le plus une fois en
 * production :
 *   · ce que Gmail conserve ou ampute de la balise <style> (mode sombre,
 *     responsive) et comment son application Android rend l'ensemble ;
 *   · le texte d'aperçu affiché dans la liste des messages ;
 *   · le passage — ou non — des filtres anti-spam ;
 *   · le rendu de la version texte pour qui lit en « texte seul ».
 *
 * Les e-mails partent avec des DONNÉES FICTIVES (voir {@see MailPreview}) et un
 * sujet préfixé « [APERÇU n/N] » pour rester identifiables et groupés dans la
 * boîte de réception.
 *
 * Exemples :
 *   php artisan mail:apercu contact@exemple.com
 *   php artisan mail:apercu contact@exemple.com --only=bienvenue-proprietaire
 *   php artisan mail:apercu contact@exemple.com --only=bienvenue-client,devis-recu
 *
 * ⚠️ Envoi RÉEL : la commande consomme le quota du compte SMTP configuré
 * (MAIL_*). Elle refuse de s'exécuter si MAIL_MAILER=log, sans quoi elle
 * donnerait l'illusion d'un envoi.
 */
class MailPreviewSendCommand extends Command
{
    protected $signature = 'mail:apercu
        {to : Adresse destinataire}
        {--only= : Clés d\'aperçu à envoyer, séparées par des virgules (défaut : toutes)}
        {--pause=1 : Secondes d\'attente entre deux envois}';

    protected $description = 'Envoie les e-mails d\'aperçu (données fictives) à une adresse réelle.';

    public function handle(): int
    {
        $to = $this->argument('to');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error("Adresse invalide : {$to}");

            return self::FAILURE;
        }

        // Garde-fou : trois transports n'envoient RIEN — `log` (écrit dans les
        // logs), `array` (garde en mémoire, utilisé par les tests) et `null`
        // (jette). Sans ce contrôle, la commande afficherait « envoyé » sans que
        // rien ne parte : pire qu'une erreur franche, puisqu'on en conclurait
        // que les e-mails passent.
        // La clé lue est `default` de config/mail.php, c.-à-d. MAIL_MAILER.
        $mailer = Config::get('mail.default');

        if (in_array($mailer, ['log', 'array', 'null'], true)) {
            $this->error("MAIL_MAILER={$mailer} : aucun e-mail ne partirait réellement. Basculez sur `smtp`.");

            return self::FAILURE;
        }

        $catalog = MailPreview::catalog();

        // Filtre optionnel : n'envoyer que certaines clés.
        if ($only = $this->option('only')) {
            $keys = array_map('trim', explode(',', $only));
            $inconnues = array_diff($keys, array_keys($catalog));

            if ($inconnues !== []) {
                $this->error('Aperçu inconnu : '.implode(', ', $inconnues));
                $this->line('Clés disponibles : '.implode(', ', array_keys($catalog)));

                return self::FAILURE;
            }

            $catalog = array_intersect_key($catalog, array_flip($keys));
        }

        $total = count($catalog);
        $index = 0;
        $echecs = 0;

        $this->info("Envoi de {$total} e-mail(s) d'aperçu à {$to}…");
        $this->newLine();

        foreach ($catalog as $key => $label) {
            $index++;

            try {
                self::sendOne($key, $to, $index, $total);
                $this->line(sprintf('  <fg=green>✓</> %2d/%d  %s', $index, $total, $label));
            } catch (\Throwable $e) {
                $echecs++;
                $this->line(sprintf('  <fg=red>✗</> %2d/%d  %s — %s', $index, $total, $label, $e->getMessage()));
            }

            // Petite pause : une rafale de vingt messages en une seconde est
            // exactement le profil qu'un filtre anti-spam sanctionne.
            if ($index < $total && ($pause = (float) $this->option('pause')) > 0) {
                usleep((int) ($pause * 1_000_000));
            }
        }

        $this->newLine();

        if ($echecs > 0) {
            $this->error("{$echecs} envoi(s) en échec sur {$total}.");

            return self::FAILURE;
        }

        $this->info("{$total} e-mail(s) envoyé(s). Vérifiez la boîte de réception — et le dossier spam.");

        return self::SUCCESS;
    }

    /**
     * Envoie un aperçu, en reprenant EXACTEMENT le message que produirait la
     * notification correspondante (mêmes vues, mêmes données).
     */
    private static function sendOne(string $key, string $to, int $index, int $total): void
    {
        $message = MailPreview::message($key);
        [$htmlView, $textView] = $message->view;

        // Le préfixe numéroté garde les vingt messages groupés et ordonnés dans
        // la boîte de réception, et signale sans ambiguïté qu'il s'agit d'un test.
        $subject = sprintf('[APERÇU %d/%d] %s', $index, $total, $message->subject);

        // Clés explicites `html` / `text` : c'est ainsi que Mailer construit un
        // message « multipart » (les deux versions dans le même envoi).
        Mail::send(
            ['html' => $htmlView, 'text' => $textView],
            $message->viewData,
            fn ($mail) => $mail->to($to)->subject($subject),
        );
    }
}
