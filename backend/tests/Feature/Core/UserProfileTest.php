<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du socle de données utilisateurs/profils (phase B1.1).
 *
 * On vérifie : la relation 1–1 User <-> Profile, le typage des enums
 * (type de profil, statut de compte) et la valeur de statut par défaut.
 */
class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_possede_un_profil(): void
    {
        $user = User::factory()->create();
        $profile = Profile::factory()->create(['user_id' => $user->id]);

        // La relation fonctionne dans les deux sens.
        $this->assertTrue($user->profile->is($profile));
        $this->assertTrue($profile->user->is($user));
    }

    public function test_le_type_de_profil_est_caste_en_enum(): void
    {
        $profile = Profile::factory()->proprietaire()->create();

        $this->assertSame(ProfileType::PROPRIETAIRE, $profile->type);
    }

    public function test_le_statut_de_compte_par_defaut_est_en_attente_verification(): void
    {
        $user = User::factory()->create();

        // Aucun statut fourni à la création → valeur par défaut de la colonne,
        // relue depuis la base et castée en enum.
        $this->assertSame(UserStatus::EN_ATTENTE_VERIFICATION, $user->fresh()->status);
    }
}
