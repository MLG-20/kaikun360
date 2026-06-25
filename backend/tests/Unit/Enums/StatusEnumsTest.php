<?php

namespace Tests\Unit\Enums;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Modules\Immo\Enums\PropertyStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires des enums de statuts métier (phase B0.6).
 *
 * Pures vérifications de logique (pas de base de données) : on étend donc
 * directement PHPUnit\Framework\TestCase pour des tests rapides.
 */
class StatusEnumsTest extends TestCase
{
    public function test_property_status_expose_libelle_et_valeurs(): void
    {
        $this->assertSame('en_attente_validation', PropertyStatus::EN_ATTENTE_VALIDATION->value);
        $this->assertSame('En attente de validation', PropertyStatus::EN_ATTENTE_VALIDATION->label());
        $this->assertContains('publie', PropertyStatus::values());
    }

    public function test_booking_status_detecte_les_annulations(): void
    {
        $this->assertTrue(BookingStatus::ANNULEE_CLIENT->estAnnulee());
        $this->assertTrue(BookingStatus::ANNULEE_ADMIN->estAnnulee());
        $this->assertFalse(BookingStatus::CONFIRMEE->estAnnulee());
    }

    public function test_payment_status_seul_complete_est_reussi(): void
    {
        $this->assertTrue(PaymentStatus::COMPLETE->estReussi());
        $this->assertFalse(PaymentStatus::EN_ATTENTE->estReussi());
        $this->assertFalse(PaymentStatus::REFUSE->estReussi());
    }

    public function test_request_status_contient_les_six_etapes_de_la_machine(): void
    {
        // La machine à états B11 compte exactement 6 étapes :
        // reçu → vérification → visite → devis → négociation → clôturé.
        $this->assertCount(6, RequestStatus::cases());
        $this->assertSame('recu', RequestStatus::RECU->value);
    }
}
