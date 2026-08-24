<?php

namespace Tests\Feature\Notification;

use App\Models\User;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Enums\UserRole;
use App\Support\Mail\SpaceLink;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Repli de {@see SpaceLink} sur le RÔLE quand le profil manque (F8.14).
 *
 * POURQUOI UN FICHIER À PART. {@see BrandedMailTest} vérifie la fabrique
 * d'e-mails **sans toucher la base**, et c'est un parti pris qui le garde
 * rapide. Or attribuer un rôle passe forcément par la base (Spatie Permission) :
 * ces cas-là vivent donc ici, avec `RefreshDatabase`, plutôt que d'alourdir le
 * fichier voisin.
 *
 * LE DÉFAUT CORRIGÉ. `SpaceLink` déduisait l'espace du seul profil et retombait
 * sur `/mon-espace` quand il manquait — compte importé, jeu d'essai, création
 * hors du parcours d'inscription. Ce repli semblait inoffensif (« l'espace
 * client existe toujours »), mais `/mon-espace` **n'est pas neutre** : il est
 * gardé par le rôle `client` côté Angular. Une entreprise l'aurait donc reçu en
 * pleine figure comme un refus d'accès, dans un e-mail dont le seul but était de
 * l'emmener régler son séminaire.
 */
class SpaceLinkRoleFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_sans_profil_le_lien_suit_le_role_du_destinataire(): void
    {
        $cases = [
            [UserRole::ENTREPRISE, '/espace-entreprise/notifications'],
            [UserRole::PRESTATAIRE, '/espace-prestataire/notifications'],
            [UserRole::PROPRIETAIRE, '/espace-proprietaire/notifications'],
            [UserRole::DIASPORA, '/espace-diaspora/notifications'],
            // Aucun des quatre : le dernier repli reste l'espace client, la seule
            // adresse qui existe pour tout le monde.
            [UserRole::CLIENT, '/mon-espace/notifications'],
        ];

        foreach ($cases as [$role, $expected]) {
            $user = User::factory()->create();
            $user->assignRole($role->value);

            $this->assertNull($user->profile, 'Le cas testé est bien celui du profil absent.');
            $this->assertSame($expected, SpaceLink::to($user->fresh(), 'notifications'), $role->value);
        }
    }

    /**
     * Le profil reste PRIORITAIRE : c'est lui qui porte le sens métier.
     */
    public function test_le_profil_prime_sur_le_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::CLIENT->value);
        $user->profile()->create([
            'type' => ProfileType::ENTREPRISE->value,
            'verification_status' => 'non_verifie',
        ]);

        $this->assertSame('/espace-entreprise', SpaceLink::base($user->fresh()));
    }

    /**
     * Les écrans dérivés du préfixe héritent du repli : sans cela, une entreprise
     * sans profil aurait été renvoyée vers `/mon-espace/demandes`.
     */
    public function test_le_lien_vers_les_demandes_herite_du_repli(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::ENTREPRISE->value);

        $this->assertSame('/espace-entreprise/demandes', SpaceLink::requests($user->fresh()));
    }

    /**
     * L'espace diaspora n'a pas d'écran « demandes » (le suivi se fait projet
     * par projet) : le lien doit rester sur la racine de l'espace, pas
     * `/espace-diaspora/demandes`, qui n'existe pas.
     */
    public function test_le_lien_vers_les_demandes_reste_sur_la_racine_pour_la_diaspora(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::DIASPORA->value);

        $this->assertSame('/espace-diaspora', SpaceLink::requests($user->fresh()));
    }
}
