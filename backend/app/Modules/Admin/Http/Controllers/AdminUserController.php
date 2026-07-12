<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Http\Requests\UpdateUserRequest;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\DocumentRequiredNotification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Gestion des comptes utilisateurs par le back-office (B13.3).
 *
 * Réservé à la permission `gerer:utilisateurs` (admin et super_admin ; les
 * agents en sont exclus). Deux garde-fous protègent la hiérarchie :
 *   - escalade : attribuer `admin`/`super_admin` exige d'être super_admin, et
 *     un compte super_admin n'est modifiable que par un super_admin ;
 *   - auto-modification : on ne modifie pas son propre compte ici (évite
 *     l'auto-verrouillage ou l'auto-rétrogradation).
 */
class AdminUserController extends Controller
{
    /**
     * Liste filtrable et paginée des comptes. GET /api/v1/admin/users
     *
     * Filtres : `role`, `status`, `q` (nom / email / téléphone).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $users = User::query()
            ->when($request->filled('role'), fn ($q) => $q->role($request->string('role')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->with('profile')
            ->latest()
            ->paginate($perPage);

        return UserResource::collection($users);
    }

    /**
     * Met à jour le rôle et/ou le statut d'un compte.
     * PATCH /api/v1/admin/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $actor = $request->user();
        $data = $request->validated();

        // Garde-fou : pas d'auto-modification via le back-office.
        if ($actor->id === $user->id) {
            abort(403, 'Vous ne pouvez pas modifier votre propre compte depuis le back-office.');
        }

        // Garde-fou : un compte super_admin n'est modifiable que par un super_admin.
        if ($user->hasRole(UserRole::SUPER_ADMIN->value) && ! $actor->hasRole(UserRole::SUPER_ADMIN->value)) {
            abort(403);
        }

        if (isset($data['role'])) {
            // Escalade de privilèges : attribuer un rôle d'administration exige super_admin.
            $privileged = in_array($data['role'], [UserRole::ADMIN->value, UserRole::SUPER_ADMIN->value], true);
            if ($privileged && ! $actor->hasRole(UserRole::SUPER_ADMIN->value)) {
                abort(403, 'Seul un super administrateur peut attribuer ce rôle.');
            }

            // Un compte ne porte qu'un rôle « principal » à la fois côté Kaikun.
            $user->syncRoles([$data['role']]);
        }

        if (isset($data['status'])) {
            $user->update(['status' => $data['status']]);
        }

        activity()->causedBy($actor)->performedOn($user)
            ->withProperties($data)
            ->log('Mise à jour de compte (back-office)');

        return ApiResponse::success([
            'user' => UserResource::make($user->fresh()->load('profile')),
        ]);
    }

    /**
     * Demande une pièce à un utilisateur (notification, B16.2).
     * POST /api/v1/admin/users/{user}/request-document
     */
    public function requestDocument(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user->notify(new DocumentRequiredNotification($data['document_type'], $data['note'] ?? null));

        return ApiResponse::success(['message' => 'Demande de document envoyée.']);
    }
}
