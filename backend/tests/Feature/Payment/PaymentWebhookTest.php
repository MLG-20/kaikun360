<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Stay\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Tests de l'IPN PayTech (B14.3, réécrits en F8.5 sur le contrat RÉEL).
 *
 * ⚠️ Ces tests validaient auparavant un protocole inventé — JSON `{id, status,
 * amount}` signé dans un en-tête `Signature`. Ils passaient au vert contre du
 * code qui n'aurait jamais reconnu une notification PayTech authentique : la
 * suite prouvait la cohérence du code avec lui-même, pas avec le PSP.
 *
 * Le contrat réel : un FORMULAIRE, un `type_event`, et une preuve
 * d'authenticité dans le corps (`hmac_compute` = HMAC-SHA256 de
 * `{final_item_price}|{ref_command}|{api_key}`, clé = api_secret).
 *
 * C'est le seul chemin par lequel une réservation devient payée : chaque
 * garde-fou a son test.
 */
class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'test_api_key';
    private const API_SECRET = 'test_api_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.paytech.api_key', self::API_KEY);
        config()->set('services.paytech.api_secret', self::API_SECRET);
    }

    private function payment(array $overrides = []): Payment
    {
        $stay = Stay::factory()->create();
        $booking = Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => Stay::class,
            'bookable_id' => $stay->id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 1,
            'amount_xof' => 100_000,
            'commission_xof' => 12_000,
            'status' => 'en_attente',
        ]);

        return Payment::create(array_merge([
            'reference' => 'PAY-'.uniqid(),
            'booking_id' => $booking->id,
            'amount_xof' => 100_000,
            'commission_xof' => 12_000,
            'provider_reference' => 'ptx_'.uniqid(),
            'status' => PaymentStatus::EN_ATTENTE->value,
        ], $overrides));
    }

    /**
     * Construit une notification PayTech complète et correctement signée.
     *
     * `finalPrice` est distinct de `itemPrice` par défaut : c'est le cas normal
     * en sandbox, où PayTech ne débite qu'un montant aléatoire.
     */
    private function ipn(Payment $payment, string $typeEvent, ?int $itemPrice = null, int $finalPrice = 127): array
    {
        $itemPrice ??= $payment->amount_xof;

        $payload = [
            'type_event' => $typeEvent,
            'ref_command' => $payment->reference,
            'token' => $payment->provider_reference,
            'item_name' => 'Kaikun 360',
            'item_price' => $itemPrice,
            'initial_item_price' => $itemPrice,
            'final_item_price' => $finalPrice,
            'currency' => 'XOF',
            'command_name' => 'Kaikun 360',
            'payment_method' => 'Orange Money',
            'client_phone' => '771234567',
            'env' => 'test',
        ];

        $payload['hmac_compute'] = hash_hmac(
            'sha256',
            implode('|', [$finalPrice, $payment->reference, self::API_KEY]),
            self::API_SECRET,
        );

        return $payload;
    }

    private function send(array $payload): TestResponse
    {
        // PayTech poste un formulaire, pas du JSON.
        return $this->post('/api/v1/payments/webhook', $payload, ['Accept' => 'application/json']);
    }

    // --- Authenticité ---------------------------------------------------------

    public function test_une_notification_sans_signature_est_rejetee(): void
    {
        $payment = $this->payment();
        $payload = $this->ipn($payment, 'sale_complete');
        unset($payload['hmac_compute']);

        $this->send($payload)->assertStatus(401);

        $this->assertSame(PaymentStatus::EN_ATTENTE, $payment->fresh()->status);
        $this->assertFalse($payment->fresh()->signature_verified);
    }

    public function test_une_signature_invalide_est_rejetee(): void
    {
        $payment = $this->payment();
        $payload = $this->ipn($payment, 'sale_complete');
        $payload['hmac_compute'] = str_repeat('0', 64);

        $this->send($payload)->assertStatus(401);
        $this->assertSame(PaymentStatus::EN_ATTENTE, $payment->fresh()->status);
    }

    /**
     * Le HMAC lie la preuve au CONTENU : changer le montant après signature doit
     * invalider la notification, sinon un attaquant rejouerait une vraie
     * notification en modifiant le prix.
     */
    public function test_une_notification_alteree_apres_signature_est_rejetee(): void
    {
        $payment = $this->payment();
        $payload = $this->ipn($payment, 'sale_complete');
        $payload['final_item_price'] = 999_999;

        $this->send($payload)->assertStatus(401);
    }

    /**
     * Revue de sécurité (2026-08) : les empreintes SHA-256 des clés sont
     * CONSTANTES d'une notification à l'autre — les accepter en repli rendait
     * un rejeu indéfiniment possible dès qu'une seule notification authentique
     * avait été captée quelque part (PoC réalisée : paiement `en_attente`
     * basculé en `complete` sans jamais connaître l'`API_SECRET`). Le repli est
     * supprimé : seul le HMAC, lié au montant et à la référence, fait foi.
     */
    public function test_le_repli_par_empreintes_sha256_est_desormais_rejete(): void
    {
        $payment = $this->payment();
        $payload = $this->ipn($payment, 'sale_complete');
        unset($payload['hmac_compute']);
        $payload['api_key_sha256'] = hash('sha256', self::API_KEY);
        $payload['api_secret_sha256'] = hash('sha256', self::API_SECRET);

        $this->send($payload)->assertStatus(401);
        $this->assertNotSame(PaymentStatus::COMPLETE, $payment->fresh()->status);
    }

    /**
     * Une plateforme sans clés configurées doit CESSER d'encaisser, pas tout
     * accepter : c'est la panne la plus dangereuse.
     */
    public function test_sans_cles_configurees_tout_est_refuse(): void
    {
        config()->set('services.paytech.api_secret', null);

        $payment = $this->payment();
        $this->send($this->ipn($payment, 'sale_complete'))->assertStatus(401);
    }

    // --- Traitement métier ----------------------------------------------------

    public function test_une_vente_aboutie_confirme_la_reservation(): void
    {
        $payment = $this->payment();

        $this->send($this->ipn($payment, 'sale_complete'))
            ->assertOk()
            ->assertJsonPath('data.status', 'complete');

        $fresh = $payment->fresh();
        $this->assertSame(PaymentStatus::COMPLETE, $fresh->status);
        $this->assertTrue($fresh->signature_verified);
        $this->assertSame('confirmee', $fresh->booking->status->value);
    }

    /**
     * Le cœur du mode sandbox : PayTech ne débite qu'un montant aléatoire entre
     * 100 et 150 FCFA. Réconcilier sur ce montant empêcherait TOUTE confirmation
     * en test — on réconcilie donc sur `item_price`, le montant demandé.
     */
    public function test_un_montant_debite_moindre_ne_bloque_pas_si_le_prix_demande_correspond(): void
    {
        $payment = $this->payment();

        $this->send($this->ipn($payment, 'sale_complete', itemPrice: 100_000, finalPrice: 118))
            ->assertOk();

        $fresh = $payment->fresh();
        $this->assertSame(PaymentStatus::COMPLETE, $fresh->status);
        // Le montant réellement débité reste tracé pour la comptabilité.
        $this->assertSame(118, $fresh->meta['debited_amount_xof']);
    }

    public function test_un_ecart_sur_le_prix_demande_ne_confirme_jamais(): void
    {
        $payment = $this->payment();

        $this->send($this->ipn($payment, 'sale_complete', itemPrice: 90_000))
            ->assertStatus(202)
            ->assertJsonPath('data.reconciliation', 'amount_mismatch');

        $fresh = $payment->fresh();
        $this->assertNotSame(PaymentStatus::COMPLETE, $fresh->status);
        $this->assertSame('en_attente', $fresh->booking->status->value);
        $this->assertTrue($fresh->meta['amount_mismatch']);
    }

    public function test_une_vente_annulee_ne_confirme_pas_la_reservation(): void
    {
        $payment = $this->payment();

        $this->send($this->ipn($payment, 'sale_canceled'))
            ->assertOk()
            ->assertJsonPath('data.status', 'annule');

        $this->assertSame('en_attente', $payment->fresh()->booking->status->value);
    }

    public function test_un_remboursement_est_enregistre(): void
    {
        $payment = $this->payment();

        $this->send($this->ipn($payment, 'refund_complete'))
            ->assertOk()
            ->assertJsonPath('data.status', 'rembourse');
    }

    public function test_un_evenement_inconnu_est_rejete_et_non_devine(): void
    {
        $payment = $this->payment();

        $this->send($this->ipn($payment, 'sale_pending_maybe'))->assertStatus(422);
        $this->assertSame(PaymentStatus::EN_ATTENTE, $payment->fresh()->status);
    }

    public function test_le_moyen_de_paiement_est_conserve(): void
    {
        $payment = $this->payment();

        $this->send($this->ipn($payment, 'sale_complete'))->assertOk();

        $this->assertSame('Orange Money', $payment->fresh()->mode);
    }

    /**
     * PayTech réémet ses notifications : un encaissement déjà acquis ne doit pas
     * être rejoué (double notification au client, écritures en double).
     */
    public function test_un_paiement_deja_complete_est_idempotent(): void
    {
        $payment = $this->payment(['status' => PaymentStatus::COMPLETE->value]);

        $this->send($this->ipn($payment, 'sale_complete'))
            ->assertOk()
            ->assertJsonPath('data.idempotent', true);
    }

    public function test_un_paiement_introuvable_donne_404(): void
    {
        $payment = $this->payment();
        $payload = $this->ipn($payment, 'sale_complete');
        $payload['ref_command'] = 'PAY-INEXISTANT';
        // On resigne pour que l'échec porte bien sur l'introuvable, pas sur la signature.
        $payload['hmac_compute'] = hash_hmac(
            'sha256',
            implode('|', [$payload['final_item_price'], 'PAY-INEXISTANT', self::API_KEY]),
            self::API_SECRET,
        );

        $this->send($payload)->assertStatus(404);
    }

    /**
     * L'IPN peut arriver avant que la réponse d'initiation ne soit enregistrée :
     * le jeton PayTech doit alors être rattrapé ici.
     */
    public function test_le_jeton_paytech_est_rattrape_s_il_manquait(): void
    {
        $payment = $this->payment(['provider_reference' => null]);
        $payload = $this->ipn($payment, 'sale_complete');
        $payload['token'] = 'ptx_rattrape';
        $payload['hmac_compute'] = hash_hmac(
            'sha256',
            implode('|', [$payload['final_item_price'], $payment->reference, self::API_KEY]),
            self::API_SECRET,
        );

        $this->send($payload)->assertOk();

        $this->assertSame('ptx_rattrape', $payment->fresh()->provider_reference);
    }
}
