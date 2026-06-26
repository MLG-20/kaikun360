<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Profile;
use App\Modules\Core\Models\UserDocument;
use App\Modules\Immo\Models\Property;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
#[Fillable(['name', 'email', 'phone', 'password', 'city', 'status'])]
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
     * Relation N–N : les biens immobiliers mis en favori par l'utilisateur.
     */
    public function favoriteProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'favorites')->withTimestamps();
    }
}
