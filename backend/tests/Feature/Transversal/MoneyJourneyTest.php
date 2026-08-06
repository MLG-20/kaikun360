<?php

namespace Tests\Feature\Transversal;

use App\Enums\BookingStatus;
use App\Enums\PartnerDueStatus;
use App\Enums\PartnerPayoutStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\PartnerDue;
use App\Models\PartnerPayout;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F8.17 — LE PARCOURS DE L'ARGENT, DU VISITEUR AU PARTENAIRE.
 *
 * POURQUOI CE FICHIER EXISTE
 * --------------------------
 * C'est le test qui manquait depuis le début du projet, et le trou par lequel
 * sont passés **tous** les défauts trouvés à la main : des avis inatteignables,
 * une demande de chantier mal aiguillée, un comparateur sans écran, et un
 * circuit de reversement qui n'existait que pour un univers sur cinq.
 *
 * Chaque couche avait pourtant ses tests, et ils étaient verts. Le paiement
 * était testé contre le PSP, la clôture contre ses réservations, le registre des
 * reversements contre ses dettes — mais **aucun test ne franchissait la
 * frontière entre deux couches**, si bien qu'un maillon absent au milieu de la
 * chaîne ne faisait échouer personne.
 *
 * CE QU'IL VÉRIFIE
 * ----------------
 * La chaîne entière, sans jamais poser un état à la main :
 *
 *   visiteur → réservation → paiement → IPN PayTech → confirmation
 *            → service rendu → clôture → dette inscrite → délai de sûreté
 *            → versement préparé → virement constaté avec justificatif
 *
 * ⚠️ **RÈGLE DE CE FICHIER : aucun `status` écrit à la main.** Les autres tests
 * fabriquent l'état dont ils ont besoin (`'status' => 'terminee'`) et prouvent
 * seulement que la couche suivante sait le traiter. Ici, chaque état doit être
 * PRODUIT par le produit lui-même — c'est la seule façon de prouver qu'il est
 * atteignable en vrai. Si un maillon disparaît, ce test tombe.
 *
 * ⚠️ **Le temps passe pour de vrai** (`travel`), parce que le circuit dépend de
 * deux délais réels : la fin du service et les 7 jours de sûreté avant
 * exigibilité. Les figer rendrait le test aveugle à leur inversion.
 */
class MoneyJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'test_api_key';

    private const API_SECRET = 'test_api_secret';

    /** Taux de commission de la plateforme appliqué par `CommissionCalculator`. */
    private const TAUX_COMMISSION = 0.12;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, SenegalGeographySeeder::class, CommunesSeeder::class]);

        config()->set('services.paytech.base_url', 'https://paytech.sn/api');
        config()->set('services.paytech.api_key', self::API_KEY);
        config()->set('services.paytech.api_secret', self::API_SECRET);

        // Le PSP est simulé, mais **son contrat ne l'est pas** : l'IPN plus bas
        // est signé comme le vrai (voir `PaymentWebhookTest`).
        Http::fake([
            'paytech.sn/*' => Http::response([
                'success' => 1,
                'token' => 'ptx_journey',
                'redirect_url' => 'https://paytech.sn/payment/checkout/ptx_journey',
            ], 200),
        ]);
    }

    // =========================================================================
    // Outils
    // =========================================================================

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** Un agent Kaikun avec les droits d'exploitation (arrivées, départs). */
    private function agentOperationnel(): User
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    /**
     * La notification PayTech telle que le PSP l'envoie : un FORMULAIRE, un
     * `type_event`, et un HMAC calculé sur `{final_item_price}|{ref_command}|{api_key}`.
     *
     * ⚠️ `final_item_price` est volontairement DIFFÉRENT du montant dû : c'est le
     * comportement du sandbox PayTech, qui ne débite qu'un montant symbolique.
     */
    private function notifierPaytech(Payment $payment, string $typeEvent = 'sale_complete'): TestResponse
    {
        $finalPrice = 127;

        $payload = [
            'type_event' => $typeEvent,
            'ref_command' => $payment->reference,
            'token' => $payment->provider_reference,
            'item_name' => 'Kaikun 360',
            'item_price' => $payment->amount_xof,
            'initial_item_price' => $payment->amount_xof,
            'final_item_price' => $finalPrice,
            'currency' => 'XOF',
            'command_name' => 'Kaikun 360',
            'payment_method' => 'Orange Money',
            'client_phone' => '771234567',
            'env' => 'test',
            'hmac_compute' => hash_hmac(
                'sha256',
                implode('|', [$finalPrice, $payment->reference, self::API_KEY]),
                self::API_SECRET,
            ),
        ];

        return $this->post('/api/v1/payments/webhook', $payload, ['Accept' => 'application/json']);
    }

    /** Le dernier paiement créé pour cette réservation. */
    private function paiementDe(Booking $booking): Payment
    {
        return Payment::query()->where('booking_id', $booking->id)->latest('id')->firstOrFail();
    }

    // =========================================================================
    // 1. Le parcours complet — location de véhicule, clôture par la tâche
    // =========================================================================

    /**
     * Le cas nominal, bout en bout, sur l'univers Mobilité : le bénéficiaire est
     * un **prestataire** (`vehicles.provider_id` pointe sur `users`).
     */
    public function test_du_visiteur_anonyme_au_virement_recu_par_le_loueur(): void
    {
        Storage::fake('local');

        $loueur = $this->withRole(UserRole::PRESTATAIRE->value);
        $vehicle = Vehicle::factory()->published()->create([
            'provider_id' => $loueur->id,
            'price_per_day_xof' => 50_000,
            'caution_xof' => 100_000,
        ]);

        // --- 1. Le visiteur, sans compte, voit l'offre --------------------
        // Le parcours commence ici : une fiche invisible au public ne peut
        // produire aucune réservation, donc aucun reversement.
        // ⚠️ La fiche véhicule renvoie la ressource NUE (`data.id`), là où les
        // réservations et paiements passent par l'enveloppe `ApiResponse`
        // (`data.booking`, `data.payment`). Divergence de forme héritée du
        // module Mobilité, à ne pas « corriger » ici : des écrans en dépendent.
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $vehicle->id);

        // --- 2. Il crée un compte et réserve -----------------------------
        $client = User::factory()->create();
        Sanctum::actingAs($client);

        $reservation = $this->postJson("/api/v1/vehicles/{$vehicle->id}/bookings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
        ])->assertCreated();

        $booking = Booking::query()->findOrFail($reservation->json('data.booking.id'));

        // 3 jours × 50 000 = 150 000, commission 12 % = 18 000.
        $this->assertSame(150_000, (int) $booking->amount_xof);
        $this->assertSame(18_000, (int) $booking->commission_xof);
        $this->assertSame(BookingStatus::EN_ATTENTE, $booking->status);

        // --- 3. Rien n'est dû tant que rien n'est encaissé ----------------
        // ⚠️ Le garde-fou le plus important de toute la chaîne : une réservation
        // non payée ne doit JAMAIS produire une dette envers un partenaire.
        // L'inscrire ici reviendrait à promettre de l'argent que Kaikun n'a pas
        // reçu.
        $this->artisan('reversements:calculer')->assertSuccessful();
        $this->assertSame(0, PartnerDue::query()->count());

        // --- 4. Le client règle ------------------------------------------
        $initiation = $this->postJson('/api/v1/payments/initiate', [
            'booking_id' => $booking->id,
        ])->assertCreated();

        // La caution (100 000) n'est PAS appelée au paiement : elle est retenue,
        // pas encaissée. Le client règle la location, rien de plus.
        $this->assertSame(150_000, $initiation->json('data.payment.amount_xof'));
        $this->assertNotNull($initiation->json('data.redirect_url'));

        $payment = $this->paiementDe($booking);
        $this->assertSame(PaymentStatus::EN_ATTENTE, $payment->status);

        // Payé côté client mais non confirmé par le PSP : toujours rien de dû.
        $this->artisan('reversements:calculer')->assertSuccessful();
        $this->assertSame(0, PartnerDue::query()->count());

        // --- 5. PayTech confirme l'encaissement ---------------------------
        $this->notifierPaytech($payment)->assertOk();

        $this->assertSame(PaymentStatus::COMPLETE, $payment->fresh()->status);
        $this->assertSame(
            BookingStatus::CONFIRMEE,
            $booking->fresh()->status,
            "L'IPN encaissé doit confirmer la réservation : c'est le seul chemin qui la rend réelle.",
        );

        // --- 6. Le service est rendu, puis clôturé ------------------------
        $this->travel(10)->days();

        $this->artisan('reservations:cloturer')->assertSuccessful();
        $this->assertSame(BookingStatus::TERMINEE, $booking->fresh()->status);

        // --- 7. La dette est inscrite, mais pas encore exigible -----------
        $this->artisan('reversements:calculer')->assertSuccessful();

        $due = PartnerDue::query()->where('source_id', $booking->id)->firstOrFail();

        $this->assertSame($loueur->id, (int) $due->beneficiary_id);
        $this->assertSame(150_000, (int) $due->gross_xof);
        $this->assertSame(18_000, (int) $due->commission_xof);
        $this->assertSame(132_000, (int) $due->net_xof);
        $this->assertSame(
            PartnerDueStatus::EN_ATTENTE,
            $due->status,
            'Le délai de sûreté de 7 jours court depuis la FIN du service, pas depuis la clôture.',
        );

        // Le back-office le dit dans les mêmes termes : payable ≠ en attente.
        $admin = $this->withRole(UserRole::ADMIN->value);
        Sanctum::actingAs($admin);

        $ligne = collect($this->getJson('/api/v1/admin/partner-dues/beneficiaries')->assertOk()
            ->json('data.beneficiaries'))
            ->firstWhere('beneficiary.id', $loueur->id);

        $this->assertSame(0, $ligne['payable_xof']);
        $this->assertSame(132_000, $ligne['pending_xof']);

        // Un versement préparé maintenant serait payé trop tôt : le serveur refuse.
        $this->postJson('/api/v1/admin/partner-payouts', ['due_ids' => [$due->id]])
            ->assertStatus(422);

        // --- 8. Le délai s'écoule -----------------------------------------
        $this->travel(8)->days();
        $this->artisan('reversements:calculer')->assertSuccessful();

        $this->assertSame(PartnerDueStatus::EXIGIBLE, $due->fresh()->status);

        // --- 9. L'agent prépare le versement et constate le virement ------
        $payoutId = $this->postJson('/api/v1/admin/partner-payouts', [
            'due_ids' => [$due->id],
        ])->assertCreated()->json('data.payout.id');

        $this->post("/api/v1/admin/partner-payouts/{$payoutId}/pay", [
            'method' => 'wave',
            'external_reference' => 'TX-JOURNEY-01',
            'proof' => UploadedFile::fake()->image('recu.jpg'),
        ])->assertOk();

        // --- 10. Les deux bouts de la chaîne se rejoignent -----------------
        $payout = PartnerPayout::query()->findOrFail($payoutId);

        $this->assertSame(PartnerPayoutStatus::PAYE, $payout->status);
        $this->assertSame(PartnerDueStatus::PAYEE, $due->fresh()->status);
        Storage::disk('local')->assertExists($payout->proof_path);

        // L'égalité qui résume tout le circuit : ce que le client a payé, moins
        // la commission de Kaikun, est exactement ce que le partenaire a reçu.
        $encaisse = (int) $this->paiementDe($booking)->amount_xof;
        $commission = (int) $booking->fresh()->commission_xof;

        $this->assertSame(
            $encaisse - $commission,
            (int) $payout->amount_xof,
            "L'argent encaissé doit se retrouver intégralement réparti entre la commission et le partenaire.",
        );
        $this->assertSame((int) round($encaisse * self::TAUX_COMMISSION), $commission);
    }

    // =========================================================================
    // 2. Le même parcours, l'autre nature de bénéficiaire
    // =========================================================================

    /**
     * Univers Nuitées : le bénéficiaire est le **propriétaire du bien**, atteint
     * par une indirection (`Stay` → `Property` → `owner_id`), et le séjour est
     * clos par un agent au comptoir plutôt que par la tâche planifiée.
     *
     * ⚠️ Ce test existe parce que la **caution** est le piège du circuit : elle
     * est retenue au client, elle n'a jamais appartenu à l'hôte, et la reverser
     * serait donner l'argent du client à quelqu'un d'autre.
     */
    public function test_la_caution_retenue_au_client_n_est_jamais_reversee_a_l_hote(): void
    {
        Storage::fake('local');

        $hote = $this->withRole(UserRole::PROPRIETAIRE->value);
        $property = Property::factory()->published()->create(['owner_id' => $hote->id]);
        $stay = Stay::factory()->create([
            'property_id' => $property->id,
            'price_per_night_xof' => 80_000,
            'caution_xof' => 100_000,
            'capacity' => 4,
            'min_nights' => 1,
            'max_nights' => null,
            'is_active' => true,
        ]);

        $client = User::factory()->create();
        Sanctum::actingAs($client);

        $reservation = $this->postJson("/api/v1/stays/{$stay->id}/bookings", [
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'guests' => 2,
        ])->assertCreated();

        $booking = Booking::query()->findOrFail($reservation->json('data.booking.id'));

        // 2 nuits × 80 000 = 160 000 ; commission 12 % = 19 200 ; caution à part.
        $this->assertSame(160_000, (int) $booking->amount_xof);
        $this->assertSame(19_200, (int) $booking->commission_xof);
        $this->assertSame(100_000, (int) $booking->caution_xof);

        // Règlement, puis confirmation par le PSP.
        $this->postJson('/api/v1/payments/initiate', ['booking_id' => $booking->id])->assertCreated();
        $this->notifierPaytech($this->paiementDe($booking))->assertOk();
        $this->assertSame(BookingStatus::CONFIRMEE, $booking->fresh()->status);

        // Le séjour a lieu : arrivée puis départ enregistrés au comptoir. Le
        // départ clôt la réservation sans attendre la tâche planifiée.
        $this->travel(4)->days();
        Sanctum::actingAs($this->agentOperationnel());

        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/check-in")->assertOk();

        $this->travel(1)->days();
        $this->patchJson("/api/v1/admin/stay-bookings/{$booking->id}/check-out")
            ->assertOk()
            ->assertJsonPath('data.booking.status', BookingStatus::TERMINEE->value);

        // Le délai de sûreté s'écoule, la dette naît et devient exigible.
        $this->travel(8)->days();
        $this->artisan('reversements:calculer')->assertSuccessful();

        $due = PartnerDue::query()->where('source_id', $booking->id)->firstOrFail();

        $this->assertSame($hote->id, (int) $due->beneficiary_id);
        $this->assertSame(PartnerDueStatus::EXIGIBLE, $due->status);

        // 160 000 − 19 200 = 140 800. **Et surtout pas 240 800** : la caution
        // reste chez Kaikun jusqu'à sa restitution ou sa saisie.
        $this->assertSame(160_000, (int) $due->gross_xof);
        $this->assertSame(140_800, (int) $due->net_xof);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $payoutId = $this->postJson('/api/v1/admin/partner-payouts', [
            'due_ids' => [$due->id],
        ])->assertCreated()->json('data.payout.id');

        $this->post("/api/v1/admin/partner-payouts/{$payoutId}/pay", [
            'method' => 'virement',
            'external_reference' => 'VIR-77123',
            'proof' => UploadedFile::fake()->image('avis-virement.jpg'),
        ])->assertOk();

        $this->assertSame(
            140_800,
            (int) PartnerPayout::query()->findOrFail($payoutId)->amount_xof,
            "Le virement ne doit porter que le loyer net : la caution n'a jamais appartenu à l'hôte.",
        );
    }

    // =========================================================================
    // 3. La chaîne s'arrête quand l'argent n'est pas arrivé
    // =========================================================================

    /**
     * Une réservation jamais payée traverse toutes les échéances sans rien
     * produire : ni clôture, ni dette, ni versement possible.
     *
     * ⚠️ Ce test est le pendant négatif du premier, et il vaut autant : un
     * circuit de reversement qui se déclenche sur une réservation impayée fait
     * sortir de l'argent que Kaikun n'a jamais encaissé.
     */
    public function test_une_reservation_impayee_ne_produit_jamais_de_dette(): void
    {
        $loueur = $this->withRole(UserRole::PRESTATAIRE->value);
        $vehicle = Vehicle::factory()->published()->create([
            'provider_id' => $loueur->id,
            'price_per_day_xof' => 50_000,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $booking = Booking::query()->findOrFail(
            $this->postJson("/api/v1/vehicles/{$vehicle->id}/bookings", [
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(4)->toDateString(),
            ])->assertCreated()->json('data.booking.id')
        );

        // Bien après la fin de la location ET après le délai de sûreté.
        $this->travel(30)->days();

        $this->artisan('reservations:cloturer')->assertSuccessful();
        $this->artisan('reversements:calculer')->assertSuccessful();

        $this->assertSame(
            BookingStatus::EN_ATTENTE,
            $booking->fresh()->status,
            'Une réservation impayée ne doit pas être clôturée : rien ne prouve que le service a eu lieu.',
        );
        $this->assertSame(0, PartnerDue::query()->count());

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->getJson('/api/v1/admin/partner-dues/beneficiaries')
            ->assertOk()
            ->assertJsonPath('data.totals.payable_xof', 0);
    }
}
