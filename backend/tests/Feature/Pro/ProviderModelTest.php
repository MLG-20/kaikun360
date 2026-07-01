<?php

namespace Tests\Feature\Pro;

use App\Models\User;
use App\Modules\Pro\Enums\ProviderStatus;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderCertification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la couche de données du prestataire marketplace (phase B10.1) :
 * casts, relation 1–1 à l'utilisateur, certifications et helper de validation.
 */
class ProviderModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_prestataire_se_cree_avec_ses_casts(): void
    {
        $provider = Provider::factory()->create(['warnings_count' => 0]);
        $provider->refresh();

        $this->assertSame(ProviderStatus::EN_ATTENTE, $provider->status);
        $this->assertFalse($provider->isValidated());
    }

    public function test_un_prestataire_appartient_a_un_utilisateur(): void
    {
        $user = User::factory()->create();
        $provider = Provider::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($provider->user->is($user));
    }

    public function test_un_prestataire_a_des_certifications(): void
    {
        $provider = Provider::factory()->create();
        ProviderCertification::factory()->count(2)->create(['provider_id' => $provider->id]);

        $this->assertCount(2, $provider->certifications);
    }

    public function test_le_helper_is_validated_reflete_le_statut(): void
    {
        $this->assertTrue(Provider::factory()->validated()->create()->isValidated());
        $this->assertFalse(Provider::factory()->suspended()->create()->isValidated());
    }
}
