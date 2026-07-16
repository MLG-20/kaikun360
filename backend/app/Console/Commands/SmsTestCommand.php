<?php

namespace App\Console\Commands;

use App\Support\Notifications\OrangeSmsProvider;
use Illuminate\Console\Command;

/**
 * B18.2 — Envoie un vrai SMS de TEST via l'API Orange / Sonatel.
 *
 * Permet de vérifier « en conditions réelles » que la souscription à la « SMS API »
 * de developer.orange.com fonctionne (authentification OAuth2 + envoi), SANS avoir
 * à basculer tout le projet en `SMS_PROVIDER=orange` : la commande instancie le
 * fournisseur Orange directement depuis `config/services.sms.orange` (donc depuis le
 * `.env`), quel que soit le provider global. Le reste du dev continue en mode `log`.
 *
 * Exemples :
 *   php artisan sms:test                       # envoie au numéro expéditeur (soi-même)
 *   php artisan sms:test +221770000000         # envoie au numéro indiqué
 *   php artisan sms:test +221770000000 "Coucou de Kaikun360"
 *
 * ⚠️ En bac à sable Orange, l'envoi n'est en général autorisé que vers des numéros
 * déclarés sur le compte (souvent le vôtre) et consomme le quota de test acheté.
 */
class SmsTestCommand extends Command
{
    protected $signature = 'sms:test {to? : Numéro destinataire au format +221... (défaut : l\'expéditeur)} {message? : Texte du SMS}';

    protected $description = 'Envoie un vrai SMS de test via l\'API Orange (developer.orange.com).';

    public function handle(): int
    {
        $orange = config('services.sms.orange');

        // Garde-fou : sans identifiants, inutile d'appeler l'API.
        foreach (['client_id', 'client_secret', 'sender_address'] as $required) {
            if (empty($orange[$required] ?? null)) {
                $this->error("Configuration Orange incomplète : ORANGE_SMS_".strtoupper($required)." manquant dans le .env.");

                return self::FAILURE;
            }
        }

        // Destinataire : argument, sinon on s'envoie le SMS à soi-même (expéditeur),
        // ce qui est le cas d'usage le plus sûr en bac à sable.
        $to = $this->argument('to') ?: $orange['sender_address'];
        $message = $this->argument('message')
            ?: 'Test Kaikun360 : votre plateforme immobiliere et services au Senegal. Ceci est un SMS de verification.';

        $this->line("Provider global (SMS_PROVIDER) : <comment>".config('services.sms.provider')."</comment> (ignoré ici, on force Orange)");
        $this->line("Expéditeur : <comment>".$orange['sender_address']."</comment>"
            .($orange['sender_name'] ? " (« ".$orange['sender_name']." »)" : ''));
        $this->line("Destinataire : <comment>{$to}</comment>");
        $this->newLine();

        $provider = new OrangeSmsProvider(
            $orange['client_id'] ?? null,
            $orange['client_secret'] ?? null,
            $orange['base_url'] ?? 'https://api.orange.com',
            $orange['token_url'] ?? 'https://api.orange.com/oauth/v3/token',
            $orange['sender_address'] ?? null,
            $orange['sender_name'] ?? null,
        );

        $this->info('Envoi en cours…');
        $ok = $provider->send($to, $message);

        if ($ok) {
            $this->info('✅ SMS accepté par l\'API Orange. Vérifiez la réception sur le téléphone.');

            return self::SUCCESS;
        }

        $this->error('❌ Échec de l\'envoi. Détails dans storage/logs/laravel.log (authentification ou réponse API).');

        return self::FAILURE;
    }
}
