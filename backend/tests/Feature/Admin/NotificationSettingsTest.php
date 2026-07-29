<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Notifications\VerificationCodeNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\NewMessageNotification;
use App\Support\Notifications\NotificationEvent;
use App\Support\Notifications\NotificationSettings;
use App\Support\SettingsRepository;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F7.2.l : pilotage des notifications depuis les paramètres du back-office
 * (CDC §6, module *Paramètres* — « … pages, FAQ, notifications »).
 *
 * On vérifie que le réglage n'est pas décoratif : il change réellement les
 * canaux retournés par les `via()` des notifications d'exploitation. Et surtout
 * qu'il ne peut PAS éteindre les notifications de sécurité (codes de
 * vérification / 2FA), sous peine de verrouiller la plateforme.
 */
class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);

        return $admin;
    }

    /** Enregistre une surcharge de réglage comme le ferait le back-office. */
    private function setSetting(string $key, mixed $value): void
    {
        app(SettingsRepository::class)->set($key, $value);
    }

    public function test_les_reglages_exposent_le_catalogue_des_evenements(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/v1/admin/settings')->assertOk();

        $events = collect($response->json('data.notification_events'));

        // Les 10 événements pilotables, tous actifs tant que rien n'est enregistré.
        $this->assertCount(count(NotificationEvent::cases()), $events);
        $this->assertTrue($events->every(fn ($event) => $event['enabled'] === true));

        $booking = $events->firstWhere('value', 'booking_confirmed');
        $this->assertSame('Confirmation de réservation', $booking['label']);
        $this->assertSame('Clients & partenaires', $booking['audience']);

        // Les interrupteurs de canal figurent bien dans les réglages.
        $this->assertContains('notifications.sms_enabled', array_column($response->json('data.settings'), 'key'));
    }

    public function test_couper_un_evenement_annule_tous_ses_canaux(): void
    {
        $client = User::factory()->create(['phone' => '+221770000000']);

        // Avant : e-mail + SMS + trace en base.
        $this->assertSame(
            ['mail', 'sms', 'database'],
            (new BookingConfirmedNotification(new Booking))->via($client),
        );

        $this->setSetting('notifications.events', ['booking_confirmed' => false]);

        // Un `via()` vide court-circuite l'envoi : rien ne part, même pas la
        // notification en base.
        $this->assertSame([], (new BookingConfirmedNotification(new Booking))->via($client));

        // Les autres événements ne sont pas affectés par la coupure.
        $this->assertSame(['database'], (new NewMessageNotification(new \App\Models\Message))->via($client));
    }

    public function test_couper_un_canal_retire_ce_canal_sans_annuler_l_envoi(): void
    {
        $client = User::factory()->create(['phone' => '+221770000000']);

        $this->setSetting('notifications.sms_enabled', false);

        // Le SMS (canal facturé) tombe ; l'e-mail et la trace restent.
        $this->assertSame(
            ['mail', 'database'],
            (new BookingConfirmedNotification(new Booking))->via($client),
        );

        $this->setSetting('notifications.email_enabled', false);

        // Tout coupé sauf `database`, qui n'est jamais soumis aux canaux.
        $this->assertSame(['database'], (new BookingConfirmedNotification(new Booking))->via($client));
    }

    public function test_un_destinataire_sans_numero_ne_recoit_jamais_de_sms(): void
    {
        $client = User::factory()->create(['phone' => null]);

        // Règle historique (dupliquée dans plusieurs via()), désormais centralisée.
        $this->assertSame(
            ['mail', 'database'],
            (new BookingConfirmedNotification(new Booking))->via($client),
        );
    }

    public function test_les_notifications_de_securite_echappent_au_pilotage(): void
    {
        $admin = $this->admin();

        // Même tout coupé — canaux ET événements — le code de vérification part.
        $this->setSetting('notifications.email_enabled', false);
        $this->setSetting('notifications.sms_enabled', false);
        $this->setSetting('notifications.events', array_fill_keys(NotificationEvent::values(), false));

        $this->assertSame(['mail'], (new VerificationCodeNotification('123456', 'two_factor', 'email'))->via($admin));

        // Le helper confirme la coupure côté exploitation, pour bien montrer que
        // l'exemption vient de la notification de sécurité, pas d'un réglage raté.
        $this->assertFalse(NotificationSettings::eventEnabled(NotificationEvent::BOOKING_CONFIRMED));
    }

    public function test_un_evenement_inconnu_est_refuse(): void
    {
        Sanctum::actingAs($this->admin());

        // Une clé inconnue serait ignorée en silence par NotificationSettings
        // (tout événement absent est actif) → l'équipe croirait avoir coupé une
        // notification qui continue de partir. On la refuse donc à l'écriture.
        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['notifications.events' => ['evenement_fantome' => false]],
        ])->assertStatus(422);

        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['notifications.events' => ['booking_confirmed' => 'oui']],
        ])->assertStatus(422);
    }

    public function test_un_lien_de_reseau_social_doit_etre_une_url_complete(): void
    {
        Sanctum::actingAs($this->admin());

        // Ces liens finissent dans le pied de page PUBLIC : une adresse mal
        // saisie y resterait visible et cliquable.
        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['social.facebook' => 'facebook.com/kaikun'],
        ])->assertStatus(422);

        // Vide = réseau simplement masqué, pas une erreur.
        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['social.facebook' => ''],
        ])->assertOk();

        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['social.instagram' => 'https://instagram.com/kaikun360'],
        ])->assertOk()->assertJsonFragment([
            'key' => 'social.instagram',
            'value' => 'https://instagram.com/kaikun360',
            'overridden' => true,
        ]);
    }

    public function test_un_reglage_json_refuse_une_valeur_non_structuree(): void
    {
        Sanctum::actingAs($this->admin());

        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['build.pricing' => 'barème'],
        ])->assertStatus(422);
    }

    public function test_le_back_office_coupe_puis_retablit_un_evenement(): void
    {
        Sanctum::actingAs($this->admin());
        $client = User::factory()->create(['phone' => '+221770000000']);

        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['notifications.events' => ['booking_confirmed' => false]],
        ])->assertOk()->assertJsonFragment(['value' => 'booking_confirmed', 'enabled' => false]);

        $this->assertSame([], (new BookingConfirmedNotification(new Booking))->via($client));

        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['notifications.events' => ['booking_confirmed' => true]],
        ])->assertOk();

        $this->assertSame(
            ['mail', 'sms', 'database'],
            (new BookingConfirmedNotification(new Booking))->via($client),
        );
    }
}
