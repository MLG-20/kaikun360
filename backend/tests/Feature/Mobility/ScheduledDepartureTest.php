<?php

namespace Tests\Feature\Mobility;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Models\Profile;
use App\Modules\Mobility\Enums\MobilityServiceStatus;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F8.23 — LE DÉPART PROGRAMMÉ, DE SA CRÉATION À SON RETRAIT.
 *
 * POURQUOI CE FICHIER EXISTE
 * --------------------------
 * ⚠️ La table `mobility_services` était en **lecture seule depuis B7.2**. Le
 * module exposait la recherche, la fiche et la réservation de places — mais
 * **rien ne pouvait créer un départ**. Le catalogue public `/mobilite` ne
 * pouvait donc être alimenté que par le seeder : en production, il aurait été
 * vide, et aucune navette AIBD, aucun bus interurbain, aucun transfert n'aurait
 * pu être mis en vente.
 *
 * Même motif que les orphelins de F8.15 : l'écriture manquait, la lecture et
 * tout l'aval (fiche, réservation, commission, reversement) attendaient
 * derrière une porte murée.
 *
 * Les tests sont écrits **comme un parcours**, pas comme une couche : c'est
 * précisément parce que les couches étaient vertes séparément que le trou est
 * resté invisible aussi longtemps.
 */
class ScheduledDepartureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Un prestataire VÉRIFIÉ — seul autorisé à programmer un départ. */
    private function prestataire(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::PRESTATAIRE->value);
        Profile::factory()->prestataire()->verifie()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    /** Un agent habilité à valider la mobilité. */
    private function agent(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::AGENT_KAIKUN->value);
        $user->givePermissionTo('valider:vehicule');

        return $user->fresh();
    }

    /** Le corps d'un départ valide, surchargeable champ par champ. */
    private function depart(array $surcharges = []): array
    {
        return array_merge([
            'type' => 'navette',
            'departure' => 'Dakar',
            'destination' => 'AIBD',
            'departure_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'capacity' => 15,
            'price_xof' => 8_000,
            'description' => 'Navette aéroport, départ garanti.',
        ], $surcharges);
    }

    // =========================================================================
    // 1. LE PARCOURS COMPLET — celui qui n'existait pas
    // =========================================================================

    /**
     * Du dépôt par le prestataire jusqu'à la place vendue à un client.
     *
     * C'est LE test de la tranche : chaque maillon existait, aucun n'était relié
     * au précédent.
     */
    public function test_un_depart_programme_va_du_depot_a_la_place_vendue(): void
    {
        $prestataire = $this->prestataire();

        // --- 1. Le prestataire programme son départ -------------------------
        Sanctum::actingAs($prestataire);

        $id = $this->postJson('/api/v1/mobility-services', $this->depart())
            ->assertCreated()
            ->assertJsonPath('data.mobility_service.status', 'en_attente_validation')
            ->assertJsonPath('data.mobility_service.departure', 'Dakar')
            ->json('data.mobility_service.id');

        // La référence est propre aux trajets, pas recopiée de celle d'un véhicule.
        $this->assertStringStartsWith('TRJ-', MobilityService::find($id)->reference);

        // --- 2. Il n'est PAS au catalogue public tant qu'il n'est pas validé --
        app('auth')->forgetGuards();
        $this->getJson('/api/v1/mobility-services')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/mobility-services/{$id}")->assertNotFound();

        // --- 3. Il entre dans la file de validation de l'équipe --------------
        $agent = $this->agent();
        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/admin/queue')
            ->assertOk()
            ->assertJsonPath('data.queue.mobility_service.count', 1)
            ->assertJsonPath('data.queue.mobility_service.items.0.label', 'Dakar → AIBD');

        // Le dossier complet se relit avant la décision.
        $this->getJson("/api/v1/admin/queue/mobility_service/{$id}")
            ->assertOk()
            ->assertJsonPath('data.is_pending', true)
            ->assertJsonPath('data.entry.fields.Places mises en vente', 15);

        // --- 4. L'agent valide ----------------------------------------------
        $this->patchJson("/api/v1/admin/validate/mobility_service/{$id}", ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.mobility_service.status', 'publie');

        // --- 5. Le départ paraît au catalogue public -------------------------
        app('auth')->forgetGuards();
        $this->getJson('/api/v1/mobility-services')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/mobility-services/{$id}")
            ->assertOk()
            ->assertJsonPath('data.seats_left', 15);

        // --- 6. Un client achète des places ----------------------------------
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/mobility-services/{$id}/bookings", ['guests' => 3])
            ->assertCreated()
            ->assertJsonPath('data.booking.amount_xof', 24_000);

        app('auth')->forgetGuards();
        $this->getJson("/api/v1/mobility-services/{$id}")
            ->assertOk()
            ->assertJsonPath('data.seats_left', 12);
    }

    // =========================================================================
    // 2. QUI PEUT PROGRAMMER — la porte est gardée comme celle des véhicules
    // =========================================================================

    public function test_un_prestataire_non_verifie_ne_peut_pas_programmer_de_depart(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::PRESTATAIRE->value);
        Profile::factory()->prestataire()->create(['user_id' => $user->id]); // non vérifié

        Sanctum::actingAs($user->fresh());

        $this->postJson('/api/v1/mobility-services', $this->depart())->assertForbidden();
    }

    public function test_un_simple_client_ne_peut_pas_programmer_de_depart(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/mobility-services', $this->depart())->assertForbidden();
    }

    // =========================================================================
    // 3. LE VÉHICULE RATTACHÉ — le trou le plus discret de la tranche
    // =========================================================================

    /**
     * ⚠️ `vehicle_id` n'est qu'une clé étrangère vers `vehicles` : rien, dans le
     * schéma, ne la relie à l'auteur du départ. Sans ce contrôle, un prestataire
     * vendrait des places en illustrant son annonce avec le minibus d'un
     * concurrent — puisqu'un départ hérite des photos de son véhicule (F8.18).
     */
    public function test_on_ne_peut_pas_rattacher_le_vehicule_d_un_concurrent(): void
    {
        $concurrent = Vehicle::factory()->published()->create();

        Sanctum::actingAs($this->prestataire());

        $this->postJson('/api/v1/mobility-services', $this->depart([
            'vehicle_id' => $concurrent->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');
    }

    /**
     * On ne vend pas plus de places que le véhicule n'en transporte : le refus
     * arriverait sinon à l'embarquement, devant les passagers.
     */
    public function test_la_capacite_ne_peut_pas_depasser_celle_du_vehicule(): void
    {
        $prestataire = $this->prestataire();
        $minibus = Vehicle::factory()->create([
            'provider_id' => $prestataire->id,
            'capacity' => 9,
        ]);

        Sanctum::actingAs($prestataire);

        $this->postJson('/api/v1/mobility-services', $this->depart([
            'vehicle_id' => $minibus->id,
            'capacity' => 30,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capacity');

        // La même capacité, dans les clous, passe.
        $this->postJson('/api/v1/mobility-services', $this->depart([
            'vehicle_id' => $minibus->id,
            'capacity' => 9,
        ]))->assertCreated();
    }

    // =========================================================================
    // 4. LA DATE — ce qui distingue un départ de toutes les autres offres
    // =========================================================================

    public function test_un_depart_dans_le_passe_est_refuse_au_depot(): void
    {
        Sanctum::actingAs($this->prestataire());

        $this->postJson('/api/v1/mobility-services', $this->depart([
            'departure_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('departure_at');
    }

    /**
     * ⚠️ Le cas vicieux : le départ était valide au dépôt, l'agent l'a laissé
     * dormir dans la file, et sa date est passée entre-temps. Le publier ne
     * créerait qu'une ligne morte au catalogue.
     */
    public function test_l_agent_ne_peut_pas_publier_un_depart_deja_passe(): void
    {
        // Écrit directement en base : le formulaire refuserait cette date, et
        // c'est justement le point — ce départ est devenu périmé après coup.
        $service = MobilityService::factory()->create([
            'departure_at' => now()->subHours(3),
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/validate/mobility_service/{$service->id}", ['decision' => 'approve'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('departure_at');

        $this->assertSame(
            MobilityServiceStatus::EN_ATTENTE_VALIDATION,
            $service->fresh()->status,
        );

        // Le refuser, en revanche, reste possible : c'est même la bonne issue.
        $this->patchJson("/api/v1/admin/validate/mobility_service/{$service->id}", [
            'decision' => 'reject',
            'reason' => 'Départ passé, à reprogrammer.',
        ])->assertOk();
    }

    /**
     * ⚠️ Un départ hérite de la conformité de son véhicule. Le publier alors que
     * le véhicule ne l'est pas ferait entrer par la bande, dans le catalogue
     * public, un transport dont l'assurance n'a jamais été contrôlée — ce que le
     * CDC §12 interdit explicitement.
     */
    public function test_l_agent_ne_peut_pas_publier_un_depart_opere_par_un_vehicule_non_conforme(): void
    {
        $prestataire = $this->prestataire();
        $sansAssurance = Vehicle::factory()->create([
            'provider_id' => $prestataire->id,
            'insurance_ref' => null,
        ]);

        $service = MobilityService::factory()->create([
            'provider_id' => $prestataire->id,
            'vehicle_id' => $sansAssurance->id,
            'departure_at' => now()->addDays(5),
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/validate/mobility_service/{$service->id}", ['decision' => 'approve'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('compliance');
    }

    // =========================================================================
    // 5. LA CORRECTION — et la place déjà vendue qu'elle ne peut pas effacer
    // =========================================================================

    public function test_le_prestataire_corrige_son_depart_sans_le_renvoyer_en_validation(): void
    {
        $prestataire = $this->prestataire();
        $service = MobilityService::factory()->published()->create(['provider_id' => $prestataire->id]);

        Sanctum::actingAs($prestataire);

        $this->patchJson("/api/v1/mobility-services/{$service->id}", ['price_xof' => 12_500])
            ->assertOk()
            ->assertJsonPath('data.mobility_service.price_xof', 12_500)
            // ⚠️ Toujours publié : ajuster un prix ne doit pas faire disparaître
            // l'offre du catalogue le temps d'une nouvelle validation.
            ->assertJsonPath('data.mobility_service.status', 'publie');
    }

    /**
     * ⚠️ Douze places vendues, la capacité ramenée à quatre : douze clients
     * détiendraient une place qui n'existe plus. Annuler des réservations est
     * une décision commerciale — jamais l'effet de bord d'un champ corrigé.
     */
    public function test_la_capacite_ne_peut_pas_descendre_sous_les_places_deja_vendues(): void
    {
        $prestataire = $this->prestataire();
        $service = MobilityService::factory()->published()->create([
            'provider_id' => $prestataire->id,
            'capacity' => 30,
            'price_xof' => 5_000,
        ]);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/mobility-services/{$service->id}/bookings", ['guests' => 12])
            ->assertCreated();

        Sanctum::actingAs($prestataire);

        $this->patchJson("/api/v1/mobility-services/{$service->id}", ['capacity' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capacity');

        // Au-dessus des places vendues, la réduction passe.
        $this->patchJson("/api/v1/mobility-services/{$service->id}", ['capacity' => 12])
            ->assertOk()
            ->assertJsonPath('data.mobility_service.capacity', 12);
    }

    /**
     * Une réservation ANNULÉE rend sa place : elle ne doit plus bloquer la
     * réduction de capacité.
     */
    public function test_une_reservation_annulee_ne_bloque_plus_la_capacite(): void
    {
        $prestataire = $this->prestataire();
        $service = MobilityService::factory()->published()->create([
            'provider_id' => $prestataire->id,
            'capacity' => 30,
        ]);

        Sanctum::actingAs(User::factory()->create());
        $reservation = $this->postJson("/api/v1/mobility-services/{$service->id}/bookings", ['guests' => 10])
            ->assertCreated()->json('data.booking.id');

        Booking::find($reservation)->update([
            'status' => BookingStatus::ANNULEE_CLIENT->value,
        ]);

        Sanctum::actingAs($prestataire);

        $this->patchJson("/api/v1/mobility-services/{$service->id}", ['capacity' => 2])
            ->assertOk()
            ->assertJsonPath('data.mobility_service.capacity', 2);
    }

    // =========================================================================
    // 6. LE RETRAIT — la règle commune à toutes les offres
    // =========================================================================

    public function test_un_depart_jamais_reserve_est_reellement_supprime(): void
    {
        $prestataire = $this->prestataire();
        $service = MobilityService::factory()->published()->create(['provider_id' => $prestataire->id]);

        Sanctum::actingAs($prestataire);

        $this->deleteJson("/api/v1/mobility-services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('mobility_services', ['id' => $service->id]);
    }

    /**
     * ⚠️ `bookings` désigne le départ par une relation POLYMORPHE, donc sans clé
     * étrangère : la base laisserait supprimer sans broncher, et des clients
     * garderaient une réservation dont l'objet a disparu.
     */
    public function test_un_depart_deja_reserve_est_retire_et_non_supprime(): void
    {
        $prestataire = $this->prestataire();
        $service = MobilityService::factory()->published()->create([
            'provider_id' => $prestataire->id,
            'capacity' => 20,
        ]);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/mobility-services/{$service->id}/bookings", ['guests' => 2])
            ->assertCreated();

        Sanctum::actingAs($prestataire);

        $this->deleteJson("/api/v1/mobility-services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', false);

        $this->assertDatabaseHas('mobility_services', [
            'id' => $service->id,
            'status' => MobilityServiceStatus::RETIRE->value,
        ]);

        // Et il a bien quitté le catalogue public.
        app('auth')->forgetGuards();
        $this->getJson('/api/v1/mobility-services')->assertOk()->assertJsonCount(0, 'data');
    }

    // =========================================================================
    // 7. CHACUN CHEZ SOI
    // =========================================================================

    public function test_un_prestataire_ne_touche_pas_au_depart_d_un_autre(): void
    {
        $autre = MobilityService::factory()->published()->create();

        Sanctum::actingAs($this->prestataire());

        $this->patchJson("/api/v1/mobility-services/{$autre->id}", ['price_xof' => 1])->assertForbidden();
        $this->deleteJson("/api/v1/mobility-services/{$autre->id}")->assertForbidden();
    }

    public function test_mes_departs_ne_montrent_que_les_miens_tous_statuts_confondus(): void
    {
        $prestataire = $this->prestataire();

        MobilityService::factory()->count(2)->create(['provider_id' => $prestataire->id]);
        MobilityService::factory()->published()->create(['provider_id' => $prestataire->id]);
        MobilityService::factory()->published()->create(); // un concurrent

        Sanctum::actingAs($prestataire);

        $this->getJson('/api/v1/mobility-services/mine')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
