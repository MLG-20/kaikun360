<?php

namespace App\Modules\Core\Models;

use App\Models\User;
use App\Modules\Core\Enums\ProfileType;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Modèle Profil — la "casquette métier" d'un utilisateur.
 *
 * Relation 1–1 avec User (un utilisateur = un profil). Porte le type de profil,
 * l'état de vérification (KYC) et les préférences.
 */
class Profile extends Model
{
    use HasFactory;

    /**
     * Champs autorisés à l'assignation de masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'avatar_path',
        'verification_status',
        'preferences',
    ];

    /**
     * Conversions automatiques des attributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProfileType::class,   // typé via l'enum ProfileType
            'preferences' => 'array',        // JSON <-> tableau PHP automatiquement
        ];
    }

    /**
     * Relation inverse : le profil appartient à un utilisateur.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Disque sur lequel vivent les avatars.
     *
     * PUBLIC, et c'est voulu : une photo de profil est faite pour être affichée
     * (en-tête de l'espace, fiche prestataire, en regard d'un devis). La passer
     * par des URL signées comme le KYC ferait expirer les images au bout de dix
     * minutes, en pleine page. Rien de sensible n'y est déposé : les pièces
     * d'identité, elles, restent sur le disque privé (`user_documents`).
     */
    public const AVATAR_DISK = 'public';

    /**
     * URL publique de la photo / du logo, ou `null` si le compte n'en a pas.
     *
     * L'appelant qui reçoit `null` retombe sur l'initiale du nom : ne jamais
     * inventer d'image par défaut ici, l'affichage est une décision d'interface.
     */
    public function avatarUrl(): ?string
    {
        if ($this->avatar_path === null) {
            return null;
        }

        return Storage::disk(self::AVATAR_DISK)->url($this->avatar_path);
    }

    /**
     * Nature de l'image attendue pour ce profil : `logo` ou `photo`.
     *
     * Une entreprise s'identifie par son LOGO, une personne par son visage. La
     * colonne est la même (cf. migration) ; c'est l'interface qui doit demander
     * la bonne chose, d'où ce drapeau exposé jusqu'au front.
     */
    public function avatarKind(): string
    {
        return $this->type === ProfileType::ENTREPRISE ? 'logo' : 'photo';
    }

    /**
     * Supprime le fichier d'avatar du disque, s'il y en a un.
     *
     * Appelé au remplacement ET à la suppression : sans ça, chaque changement
     * de photo laisserait un orphelin sur le disque, jamais référencé et jamais
     * nettoyé.
     */
    public function deleteAvatarFile(): void
    {
        if ($this->avatar_path !== null) {
            Storage::disk(self::AVATAR_DISK)->delete($this->avatar_path);
        }
    }

    /**
     * Indique à Eloquent où trouver la factory de ce modèle.
     * Nécessaire car le modèle vit dans un module (hors de App\Models),
     * là où la résolution automatique des factories ne s'applique pas.
     */
    protected static function newFactory(): ProfileFactory
    {
        return ProfileFactory::new();
    }
}
