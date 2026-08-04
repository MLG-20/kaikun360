<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Profile;
use App\Modules\Core\Models\UserDocument;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modèle Utilisateur — identité centrale de la plateforme.
 *
 * Conserve l'emplacement Laravel par défaut (App\Models\User) pour rester
 * compatible avec config/auth.php, Sanctum et la factory. La logique métier
 * d'authentification (contrôleurs, requests, policies) vit, elle, dans le
 * module Core (app/Modules/Core).
 */
// Champs autorisés à l'assignation de masse (create/update).
#[Fillable(['name', 'email', 'phone', 'password', 'city', 'address', 'region_id', 'department_id', 'commune_id', 'status', 'google_id', 'email_verified_at'])]
// Champs masqués dans les sérialisations JSON (jamais renvoyés au client).
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    // HasApiTokens : émission de tokens d'API (Sanctum) pour le front Angular et le mobile.
    // HasRoles    : gestion des rôles et permissions (Spatie) — les 8 rôles Kaikun.
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Conversions automatiques des attributs (casts).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',          // hachage automatique à l'affectation
            'status' => UserStatus::class,   // typé via l'enum de statut de compte
        ];
    }

    /**
     * Relation 1–1 : un utilisateur possède un profil métier.
     */
    /**
     * Numéro destinataire pour le canal de notification « sms » (B16.1).
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Relation 1–N : les pièces justificatives (KYC) déposées par l'utilisateur.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(UserDocument::class);
    }

    /**
     * Relation 1–N : les favoris POLYMORPHES de l'utilisateur (tous univers).
     * Chaque favori pointe vers un élément favorisable (bien, nuitée, véhicule,
     * expérience, service de mobilité) via `App\Support\Favoritables`.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Relation N–N : les conversations (messagerie F3.7) auxquelles l'utilisateur
     * participe. Le pivot porte son `last_read_at` (calcul des messages non lus).
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * Fils de support dont ce compte est RESPONSABLE (F8.12) — sous-ensemble de
     * `conversations` : participer à un fil n'est pas en répondre. Sert à
     * mesurer la charge d'un agent au moment d'assigner un nouveau fil.
     */
    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_agent_id');
    }

    /**
     * Localisation structurée du compte (F3.2b), en cascade Région → Département
     * → Commune. Toutes optionnelles (référentiel géo partagé avec les biens).
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * Le compte appartient-il à l'ÉQUIPE Kaikun (back-office) ?
     *
     * Trois rôles seulement ouvrent la salle de contrôle ; tout le reste
     * (client, propriétaire, prestataire, entreprise) en est exclu.
     */
    public function estStaff(): bool
    {
        return $this->hasAnyRole([
            UserRole::AGENT_KAIKUN->value,
            UserRole::ADMIN->value,
            UserRole::SUPER_ADMIN->value,
        ]);
    }

    /**
     * Permissions back-office RÉELLEMENT applicables à ce compte (F7.4.a).
     *
     * Sert au frontend à n'afficher au rail que les rubriques que la personne
     * peut ouvrir. C'est un confort d'interface, PAS la sécurité : chaque route
     * `/admin/…` reste gardée par son `can:` côté serveur — le rail masqué
     * évite le mur de 403, il ne le remplace pas.
     *
     * ⚠️ Le super_admin est traité à part : ses droits ne viennent pas de
     * `getAllPermissions()` (il n'en a aucune d'assignée) mais du `Gate::before`
     * de l'AppServiceProvider qui autorise tout. Sans ce cas particulier, le
     * compte le plus puissant se retrouverait avec le rail le plus vide.
     *
     * @return array<int, string>
     */
    public function permissionsBackOffice(): array
    {
        if (! $this->estStaff()) {
            return [];
        }

        if ($this->hasRole(UserRole::SUPER_ADMIN->value)) {
            return array_map(fn (AdminPermission $p) => $p->value, AdminPermission::cases());
        }

        return $this->getAllPermissions()->pluck('name')->values()->all();
    }
}
