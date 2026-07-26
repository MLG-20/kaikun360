<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Http\Requests\StoreTeamMemberRequest;
use App\Modules\Admin\Http\Requests\SyncPermissionsRequest;
use App\Modules\Admin\Http\Requests\UpdateTeamMemberRequest;
use App\Modules\Admin\Http\Resources\TeamMemberResource;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Services\VerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gestion de l'ÉQUIPE back-office — « poste de commandement » (F7.1.a).
 *
 * Le super administrateur (et l'administrateur) pilote ici les employés du
 * back-office : agents opérationnels (sous-admins) et administrateurs. On
 * enrôle, liste, promeut/rétrograde et suspend un membre de l'équipe. La
 * délégation fine des dossiers à traiter (permissions par personne) est ajoutée
 * en F7.1.b ; le pointage des présences en F7.1.c.
 *
 * Accès réservé à la permission `gerer:utilisateurs` (admin + super_admin ; les
 * agents en sont exclus). Deux garde-fous protègent la hiérarchie, comme pour la
 * gestion des comptes publics (AdminUserController) :
 *   - escalade : attribuer/toucher le rôle `admin` exige d'être super_admin, et
 *     un compte super_admin n'est modifiable que par un super_admin ;
 *   - auto-modification : on ne modifie pas son propre compte depuis le back-office.
 *
 * Chaque action sensible est tracée dans le journal d'audit (Spatie Activitylog).
 */
class AdminTeamController extends Controller
{
    public function __construct(private readonly VerificationService $verification) {}

    /**
     * Annuaire de l'équipe back-office. GET /api/v1/admin/team
     *
     * Ne renvoie que les comptes portant un rôle d'équipe (agent / admin /
     * super_admin). Filtres : `role`, `status`, `q` (nom / e-mail / téléphone).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $members = User::query()
            ->role(UserRole::staff())
            ->when($request->filled('role'), fn ($q) => $q->role($request->string('role')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->with('roles', 'permissions')
            ->latest()
            ->paginate($perPage);

        return TeamMemberResource::collection($members);
    }

    /**
     * Enrôle (invite) un nouveau membre de l'équipe. POST /api/v1/admin/team
     *
     * Crée le compte employé, lui attribue son rôle, puis lui envoie par e-mail
     * un code pour qu'il définisse LUI-MÊME son mot de passe (flux de
     * réinitialisation existant). Aucun mot de passe n'est saisi ni renvoyé : le
     * compte naît avec un secret aléatoire jamais communiqué.
     */
    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        $actor = $request->user();
        $data = $request->validated();

        // Garde-fou d'escalade : créer un administrateur exige d'être super_admin.
        if ($data['role'] === UserRole::ADMIN->value && ! $actor->hasRole(UserRole::SUPER_ADMIN->value)) {
            abort(403, 'Seul un super administrateur peut créer un compte administrateur.');
        }

        // Création atomique du compte + attribution du rôle.
        $member = DB::transaction(function () use ($data) {
            $member = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                // Secret aléatoire jamais communiqué : l'employé définira le sien
                // via le code d'invitation ci-dessous.
                'password' => Str::password(32),
                // Compte enrôlé par un responsable de confiance : e-mail réputé
                // vérifié, mais statut « en attente » tant que le mot de passe
                // n'a pas été défini (l'invité honore l'invitation).
                'email_verified_at' => now(),
                'status' => UserStatus::EN_ATTENTE_VERIFICATION->value,
            ]);

            $member->assignRole($data['role']);

            return $member;
        });

        // Invitation : code de définition du mot de passe par e-mail (réutilise le
        // flux de réinitialisation — l'employé passe par /auth/password/reset).
        $this->verification->issue(
            $member,
            VerificationService::PURPOSE_PASSWORD_RESET,
            VerificationService::CHANNEL_EMAIL,
        );

        activity()->causedBy($actor)->performedOn($member)
            ->withProperties(['role' => $data['role']])
            ->log('Enrôlement d’un membre de l’équipe (back-office)');

        return ApiResponse::created([
            'member' => TeamMemberResource::make($member->load('roles', 'permissions')),
        ]);
    }

    /**
     * Met à jour un membre de l'équipe (rôle et/ou statut).
     * PATCH /api/v1/admin/team/{member}
     */
    public function update(UpdateTeamMemberRequest $request, User $member): JsonResponse
    {
        $actor = $request->user();
        $data = $request->validated();

        // Garde-fou : la cible doit bien être un membre de l'équipe.
        if (! $member->hasAnyRole(UserRole::staff())) {
            abort(404);
        }

        // Garde-fou : pas d'auto-modification depuis le back-office.
        if ($actor->id === $member->id) {
            abort(403, 'Vous ne pouvez pas modifier votre propre compte depuis le back-office.');
        }

        // Garde-fou : un super_admin n'est modifiable que par un super_admin.
        if ($member->hasRole(UserRole::SUPER_ADMIN->value) && ! $actor->hasRole(UserRole::SUPER_ADMIN->value)) {
            abort(403);
        }

        if (isset($data['role'])) {
            // Escalade : (dé)passer par le rôle `admin` exige d'être super_admin.
            if ($data['role'] === UserRole::ADMIN->value && ! $actor->hasRole(UserRole::SUPER_ADMIN->value)) {
                abort(403, 'Seul un super administrateur peut attribuer le rôle administrateur.');
            }

            $member->syncRoles([$data['role']]);
        }

        if (isset($data['status'])) {
            $member->update(['status' => $data['status']]);
        }

        activity()->causedBy($actor)->performedOn($member)
            ->withProperties($data)
            ->log('Mise à jour d’un membre de l’équipe (back-office)');

        return ApiResponse::success([
            'member' => TeamMemberResource::make($member->fresh()->load('roles', 'permissions')),
        ]);
    }

    /**
     * Matrice de délégation d'un agent : catalogue + droits actuellement cochés.
     * GET /api/v1/admin/team/{member}/permissions
     *
     * Renvoie les 12 permissions délégables (libellé, groupe, exigence
     * super_admin) et la liste des permissions **directes** déjà accordées à
     * l'agent (les cases cochées). L'accès de base (`consulter:dashboard-admin`,
     * porté par le rôle) n'apparaît pas : il n'est pas délégable.
     */
    public function permissions(User $member): JsonResponse
    {
        $this->assertDelegableTarget($member);

        return ApiResponse::success([
            'catalog' => AdminPermission::catalog(),
            // Seules les permissions DIRECTES sont pilotées ici (le reste vient du rôle).
            'granted' => $member->getDirectPermissions()
                ->pluck('name')
                ->intersect(AdminPermission::delegable())
                ->sort()
                ->values(),
        ]);
    }

    /**
     * Délègue (remplace) les dossiers qu'un agent a le droit de traiter.
     * PUT /api/v1/admin/team/{member}/permissions
     *
     * La liste envoyée REMPLACE l'ensemble des permissions directes de l'agent
     * (cases cochées = liste fournie ; une liste vide retire tous les droits de
     * traitement). Garde-fou d'escalade : déléguer une permission de gouvernance
     * (`gerer:utilisateurs`, `gerer:paiements`, `gerer:parametres`) exige d'être
     * super_admin.
     */
    public function syncPermissions(SyncPermissionsRequest $request, User $member): JsonResponse
    {
        $actor = $request->user();
        $this->assertDelegableTarget($member);

        $permissions = array_values(array_unique($request->validated()['permissions']));

        // Escalade : les permissions de gouvernance ne sont délégables que par un super_admin.
        $governance = array_intersect($permissions, AdminPermission::governance());
        if ($governance !== [] && ! $actor->hasRole(UserRole::SUPER_ADMIN->value)) {
            abort(403, 'Seul un super administrateur peut déléguer une permission de gouvernance.');
        }

        // Remplace l'ensemble des permissions DIRECTES (le rôle reste inchangé).
        $member->syncPermissions($permissions);

        activity()->causedBy($actor)->performedOn($member)
            ->withProperties(['permissions' => $permissions])
            ->log('Délégation des dossiers d’un membre de l’équipe (back-office)');

        return ApiResponse::success([
            'member' => TeamMemberResource::make($member->fresh()->load('roles', 'permissions')),
        ]);
    }

    /**
     * Garde-fou commun aux endpoints de délégation : la cible doit être un AGENT.
     *
     * On ne délègue de dossiers qu'aux sous-admins (`agent_kaikun`) : les
     * administrateurs disposent déjà de l'ensemble du back-office par leur rôle,
     * et un super_admin court-circuite toute autorisation. Cibler l'un d'eux ici
     * n'a pas de sens → 404 (hors périmètre de délégation) ou 422 (déjà tout-puissant).
     */
    private function assertDelegableTarget(User $member): void
    {
        if (! $member->hasAnyRole(UserRole::staff())) {
            abort(404);
        }

        if (! $member->hasRole(UserRole::AGENT_KAIKUN->value)) {
            abort(422, 'La délégation de dossiers ne concerne que les agents : un administrateur dispose déjà de tous les droits.');
        }
    }
}
