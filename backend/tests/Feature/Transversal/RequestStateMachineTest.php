<?php

namespace Tests\Feature\Transversal;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\ServiceType;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la couche de données des demandes génériques (B11.1) et de la
 * machine à états STRICTE des statuts de demande.
 */
class RequestStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_demande_se_cree_avec_ses_casts(): void
    {
        $request = ServiceRequest::factory()->create([
            'service_type' => ServiceType::IMMO->value,
        ]);
        $request->refresh();

        $this->assertSame(ServiceType::IMMO, $request->service_type);
        $this->assertSame(RequestStatus::RECU, $request->status);
        $this->assertSame(RequestPriority::NORMALE, $request->priority);
    }

    public function test_une_demande_appartient_a_un_utilisateur(): void
    {
        $user = User::factory()->create();
        $request = ServiceRequest::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($request->user->is($user));
    }

    public function test_les_transitions_lineaires_sont_autorisees(): void
    {
        $this->assertTrue(RequestStatus::RECU->canTransitionTo(RequestStatus::VERIFICATION));
        $this->assertTrue(RequestStatus::VERIFICATION->canTransitionTo(RequestStatus::VISITE));
        $this->assertTrue(RequestStatus::VISITE->canTransitionTo(RequestStatus::DEVIS));
        $this->assertTrue(RequestStatus::DEVIS->canTransitionTo(RequestStatus::NEGOCIATION));
        $this->assertTrue(RequestStatus::NEGOCIATION->canTransitionTo(RequestStatus::CLOTURE));
    }

    public function test_la_cloture_est_possible_a_toute_etape(): void
    {
        $this->assertTrue(RequestStatus::RECU->canTransitionTo(RequestStatus::CLOTURE));
        $this->assertTrue(RequestStatus::VISITE->canTransitionTo(RequestStatus::CLOTURE));
    }

    public function test_les_sauts_d_etape_sont_interdits(): void
    {
        // Sauter une étape.
        $this->assertFalse(RequestStatus::RECU->canTransitionTo(RequestStatus::VISITE));
        $this->assertFalse(RequestStatus::RECU->canTransitionTo(RequestStatus::DEVIS));
    }

    public function test_les_retours_en_arriere_sont_interdits(): void
    {
        $this->assertFalse(RequestStatus::DEVIS->canTransitionTo(RequestStatus::VISITE));
        $this->assertFalse(RequestStatus::NEGOCIATION->canTransitionTo(RequestStatus::RECU));
    }

    public function test_un_statut_cloture_est_terminal(): void
    {
        $this->assertSame([], RequestStatus::CLOTURE->allowedNext());
        $this->assertFalse(RequestStatus::CLOTURE->canTransitionTo(RequestStatus::RECU));
    }
}
