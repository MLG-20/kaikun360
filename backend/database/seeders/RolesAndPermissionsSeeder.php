<?php

namespace Database\Seeders;

use App\Modules\Core\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder des 8 rôles Kaikun et de leurs permissions (phase B1.2).
 *
 * Idempotent (firstOrCreate + syncPermissions) : on peut le relancer sans
 * créer de doublons. Le jeu de permissions est volontairement minimal pour
 * l'instant ; il s'enrichira au fil des phases métier (B2→B14).
 *
 * Rappel : super_admin bénéficie en plus d'un bypass global défini par
 * Gate::before() dans AppServiceProvider — il n'a donc aucune restriction.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Toujours vider le cache des permissions avant de (re)créer.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1) Permissions transverses initiales (s'enrichiront phase par phase).
        $permissions = [
            // File de validation (back-office / agents)
            'valider:bien',
            'valider:vehicule',
            'valider:experience',
            'valider:prestataire',
            // Gestion locative (module Manage) : création/suivi des mandats,
            // loyers, incidents, dépenses et reversements par les agents.
            'gerer:gestion-locative',
            // Suivi de chantier (module Build) : publication des rapports
            // photo/vidéo de suivi par les agents.
            'gerer:chantiers',
            // Exploitation des nuitées (back-office B13.6) : calendrier global,
            // check-in / check-out et statut de ménage par les agents.
            'gerer:nuitees',
            // Traitement des demandes génériques (couche transversale B11) :
            // changement de statut (machine à états) par les agents.
            'traiter:demandes',
            // Administration
            'consulter:dashboard-admin',
            'gerer:utilisateurs',
            'gerer:paiements',
            'gerer:parametres',
            'moderer:avis',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        // 2) Rôles + permissions associées (matrice de droits initiale).
        //    Les rôles "publics" (client, propriétaire...) n'ont aucune permission
        //    d'administration : leur accès passe par les policies de propriété.
        $matrix = [
            UserRole::VISITEUR->value => [],
            UserRole::CLIENT->value => [],
            UserRole::PROPRIETAIRE->value => [],
            UserRole::PRESTATAIRE->value => [],
            UserRole::ENTREPRISE->value => [],

            // L'agent (sous-admin) ne reçoit par défaut QUE l'accès au back-office.
            // Depuis F7.1.b (« grant pur par personne »), chaque dossier qu'il a le
            // droit de traiter lui est DÉLÉGUÉ individuellement par le super
            // administrateur (permissions directes, cf. AdminPermission::delegable()
            // et AdminTeamController::syncPermissions). Le rôle ne porte donc plus
            // les permissions opérationnelles — elles ne sont plus « héritées en
            // bloc », ce qui donnerait le même pouvoir à tous les agents.
            UserRole::AGENT_KAIKUN->value => [
                'consulter:dashboard-admin',
            ],

            // L'admin a l'ensemble du back-office.
            UserRole::ADMIN->value => $permissions,

            // Le super-admin reçoit tout (et bypass de toute façon via Gate::before).
            UserRole::SUPER_ADMIN->value => $permissions,
        ];

        foreach ($matrix as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
