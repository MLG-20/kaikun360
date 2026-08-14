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
            // Messagerie du support (F8.12) : répondre aux fils ouverts par les
            // clients. ⚠️ Cette permission désigne AUSSI le vivier des agents à
            // qui un nouveau fil est assigné (SupportAssignmentService) — et
            // depuis F8.12.b elle est portée par le RÔLE agent_kaikun (voir la
            // matrice ci-dessous) : tout agent est de permanence d'office.
            'repondre:messages',
            // Administration
            'consulter:dashboard-admin',
            'gerer:utilisateurs',
            'gerer:paiements',
            'gerer:parametres',
            'moderer:avis',
            // Fermeture d'accès avant ouverture (2026-08-14) : permission DIRECTE,
            // jamais portée par un rôle public (les 4 rôles publics restent à []
            // dans la matrice ci-dessous) — accordée compte par compte par le
            // super_admin depuis la fiche « Comptes ». Voir EnsurePlatformOpen.
            'acces:plateforme',
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
            // (Une seule exception depuis F8.12.b : `repondre:messages`.)
            UserRole::AGENT_KAIKUN->value => [
                'consulter:dashboard-admin',
                // ⚠️ EXCEPTION au grant pur (F8.12.b, arbitrage produit) :
                // répondre aux messages des clients est le **métier de base**
                // d'un agent, pas un levier sensible qu'on délègue au
                // compte-gouttes. Tout agent est donc de permanence d'office —
                // sans quoi un nouvel agent serait invisible du routage tant que
                // personne n'aurait pensé à cocher une case, et les fils
                // s'entasseraient dans « Non assignés ». Le super administrateur
                // garde la main : il réassigne n'importe quel fil à la main.
                'repondre:messages',
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
