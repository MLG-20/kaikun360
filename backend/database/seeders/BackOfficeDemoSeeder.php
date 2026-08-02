<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Enums\UserStatus;
use Illuminate\Database\Seeder;

/**
 * Comptes de DÉMO du back-office (F7.1.e) — usage LOCAL / vérification uniquement.
 *
 * Ce seeder n'est PAS appelé par `DatabaseSeeder` : on le lance explicitement en
 * développement pour pouvoir se connecter au poste de commandement et vérifier
 * les écrans « de ses propres yeux » :
 *
 *     php artisan db:seed --class=Database\\Seeders\\BackOfficeDemoSeeder
 *
 * Il crée (idempotent) :
 *   - un **super administrateur** (soumis à la 2FA e-mail à la connexion) ;
 *   - un **agent** (sous-admin) sans dossier délégué, pour tester la délégation.
 *
 * ⚠️ À ne jamais exécuter en production : ce sont des comptes à mot de passe connu.
 */
class BackOfficeDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Les rôles doivent exister (au cas où on lance ce seeder isolément).
        $this->call(RolesAndPermissionsSeeder::class);

        // L'e-mail du super_admin de démo est lu depuis l'environnement : en local
        // on le pointe vers une VRAIE boîte (ex. Gmail) pour recevoir les codes 2FA
        // en conditions réelles, sans coder d'adresse personnelle dans le dépôt.
        $superEmail = env('BACKOFFICE_DEMO_SUPER_EMAIL', 'super@kaikun.test');

        // ⚠️ Même précaution pour l'AGENT, et pas seulement pour le super_admin.
        // Toute alerte interne (« Nouvelle demande à traiter », biens à valider…)
        // part vers TOUS les comptes portant `consulter:dashboard-admin` : un
        // agent de démo laissé sur un domaine inexistant fait rebondir chaque
        // alerte, et les rapports d'échec retombent dans la boîte d'expédition.
        $agentEmail = env('BACKOFFICE_DEMO_AGENT_EMAIL', 'agent@kaikun.test');

        $superAdmin = User::firstOrCreate(
            ['email' => $superEmail],
            [
                'name' => 'Super Admin (démo)',
                'password' => 'password',   // haché automatiquement (cast 'hashed')
                'status' => UserStatus::ACTIF->value,
                'email_verified_at' => now(),
            ],
        );
        $superAdmin->syncRoles([UserRole::SUPER_ADMIN->value]);

        $agent = User::firstOrCreate(
            ['email' => $agentEmail],
            [
                'name' => 'Agent Kaikun (démo)',
                'password' => 'password',
                'status' => UserStatus::ACTIF->value,
                'email_verified_at' => now(),
            ],
        );
        $agent->syncRoles([UserRole::AGENT_KAIKUN->value]);
        // Un dossier délégué pour l'exemple (validation des biens).
        $agent->syncPermissions([AdminPermission::VALIDER_BIEN->value]);

        $this->command?->info('Comptes back-office de démo prêts :');
        $this->command?->info("  {$superEmail} / password  (super_admin — 2FA e-mail à la connexion)");
        $this->command?->info("  {$agentEmail} / password  (agent — sans 2FA)");
    }
}
