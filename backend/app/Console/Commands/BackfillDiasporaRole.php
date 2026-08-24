<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Enums\UserRole;
use Illuminate\Console\Command;

/**
 * Bascule les comptes « diaspora » du rôle `client` vers le nouveau rôle
 * `diaspora`, à l'occasion de la séparation de l'espace diaspora (2026-08-22).
 *
 * POURQUOI CETTE COMMANDE EXISTE
 * ------------------------------
 * Avant cette tranche, `UserRole::defaultForProfileType()` attribuait le rôle
 * `client` à tout profil `ProfileType::DIASPORA` : « ses projets diaspora
 * étaient une fonctionnalité, pas un rôle ». Tout compte inscrit AVANT ce
 * changement porte donc encore `client` alors que son profil dit `diaspora` —
 * sans cette commande, ces comptes existants perdraient l'accès à leurs
 * projets diaspora (nouvel espace gardé par le rôle `diaspora`) sans jamais
 * l'obtenir.
 *
 * SANS RISQUE À REJOUER : ne traite que les comptes profil `diaspora` qui
 * n'ont pas encore le rôle `diaspora` ; un compte déjà basculé est ignoré.
 *
 * À lancer une fois en local, puis une fois sur le VPS après déploiement
 * (de vrais comptes diaspora peuvent exister en production).
 */
class BackfillDiasporaRole extends Command
{
    protected $signature = 'diaspora:migrer-role {--dry-run : Montre qui serait basculé, sans rien écrire}';

    protected $description = 'Bascule les comptes de profil diaspora du rôle client vers le rôle diaspora';

    public function handle(): int
    {
        $simulation = (bool) $this->option('dry-run');

        if ($simulation) {
            $this->warn('Simulation : aucune écriture en base.');
        }

        $comptes = User::query()
            ->whereHas('profile', fn ($q) => $q->where('type', ProfileType::DIASPORA->value))
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', UserRole::DIASPORA->value))
            ->get();

        if ($comptes->isEmpty()) {
            $this->info('Rien à basculer : tous les comptes diaspora portent déjà le rôle diaspora.');

            return self::SUCCESS;
        }

        $this->line("{$comptes->count()} compte(s) profil diaspora à basculer.");

        foreach ($comptes as $user) {
            if ($simulation) {
                $this->line("    #{$user->id} {$user->email} → rôle diaspora (retrait de client)");

                continue;
            }

            $user->removeRole(UserRole::CLIENT->value);
            $user->assignRole(UserRole::DIASPORA->value);
            $this->line("    #{$user->id} {$user->email} basculé.");
        }

        $this->newLine();
        $this->info($simulation
            ? "{$comptes->count()} compte(s) à basculer."
            : "{$comptes->count()} compte(s) basculé(s).");

        return self::SUCCESS;
    }
}
