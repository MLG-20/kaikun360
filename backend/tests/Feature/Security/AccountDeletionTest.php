<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Stay\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests B15.4 : suppression du compte sur demande (RGPD). Le compte est
 * ANONYMISÉ ; les données transactionnelles (réservations) sont conservées.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_suppression_anonymise_le_compte_et_conserve_les_reservations(): void
    {
        $user = User::factory()->create(['name' => 'Awa Ndiaye', 'phone' => '+221770000000']);

        // Une pièce KYC et une réservation rattachées.
        DB::table('user_documents')->insert([
            'user_id' => $user->id,
            'type' => 'cni',
            'disk' => 'local',
            'path' => 'kyc/fake.pdf',
            'original_name' => 'cni.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'status' => 'en_attente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stay = Stay::factory()->create();
        $booking = Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => $user->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 1,
            'amount_xof' => 50_000,
            'status' => 'en_attente',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/users/me')
            ->assertOk();

        $fresh = $user->fresh();
        // Identité neutralisée.
        $this->assertSame('Utilisateur supprimé', $fresh->name);
        $this->assertSame("deleted-{$user->id}@anonymized.local", $fresh->email);
        $this->assertNull($fresh->phone);
        $this->assertSame(UserStatus::DESACTIVE, $fresh->status);

        // Accès coupés (jetons révoqués) et pièces KYC supprimées.
        $this->assertSame(0, $fresh->tokens()->count());
        $this->assertDatabaseMissing('user_documents', ['user_id' => $user->id]);

        // Donnée transactionnelle conservée (rétention comptable/légale).
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'user_id' => $user->id]);

        // Trace d'audit.
        $this->assertDatabaseHas('activity_log', ['description' => 'Anonymisation de compte (RGPD)']);
    }

    public function test_la_suppression_exige_une_authentification(): void
    {
        $this->deleteJson('/api/v1/users/me')->assertStatus(401);
    }
}
