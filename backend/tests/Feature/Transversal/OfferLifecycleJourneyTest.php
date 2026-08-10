<?php

namespace Tests\Feature\Transversal;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Media;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Models\Profile;
use App\Modules\Explore\Enums\ExperienceStatus;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Mobility\Enums\VehicleStatus;
use App\Support\Trash\ListingTrash;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F8.19 — LA VIE COMPLÈTE D'UNE OFFRE : déposée, corrigée, illustrée, retirée.
 *
 * POURQUOI CE FICHIER EXISTE
 * --------------------------
 * Deux gestes manquaient au prestataire, et le second rendait le premier
 * inutile :
 *
 *   1. **Un circuit déposé était DÉFINITIF** — `POST /experiences` existait,
 *      aucun `PATCH`. Une faute de frappe, un prix qui change, un départ qui se
 *      décale : rien n'était rattrapable. Et surtout, les photos n'étant
 *      déposables qu'au moment de la création (F8.18), **un circuit créé sans
 *      photo ne pouvait plus jamais être illustré**.
 *   2. **Aucune offre ne pouvait être retirée** — ni véhicule, ni circuit. Un
 *      véhicule vendu restait au catalogue pour toujours, et le prestataire
 *      n'avait d'autre recours que d'écrire au support.
 *
 * ⚠️ **La règle centrale, vérifiée ici sous tous les angles : on ne supprime
 * jamais une offre qui a servi.** Les réservations désignent l'offre par une
 * relation polymorphe, donc **sans clé étrangère** : la base laisserait faire
 * sans broncher, et des clients se retrouveraient avec une réservation dont
 * l'objet a disparu de leur historique.
 */
class OfferLifecycleJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    /** Un prestataire VÉRIFIÉ — seul autorisé à publier une offre. */
    private function prestataire(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::PRESTATAIRE->value);
        // ⚠️ Le profil VÉRIFIÉ n'est pas un détail de montage : la policy
        // `create` le réclame — seul un prestataire au KYC validé publie.
        Profile::factory()->prestataire()->verifie()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    private function deposerPhoto(string $type, int $id): int
    {
        return $this->post('/api/v1/media/upload', [
            'mediable_type' => $type,
            'mediable_id' => $id,
            'file' => UploadedFile::fake()->image('offre.jpg'),
            'is_primary' => '1',
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.media.id');
    }

    /** Une réservation telle que le produit la crée, sur n'importe quelle offre. */
    private function reserver(string $type, int $id): Booking
    {
        return Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => $type,
            'bookable_id' => $id,
            'start_date' => now()->addDays(3)->toDateString(),
            'guests' => 2,
            'amount_xof' => 90_000,
            'status' => BookingStatus::CONFIRMEE->value,
        ]);
    }

    // =========================================================================
    // 1. Le circuit devient enfin modifiable — et donc illustrable après coup
    // =========================================================================

    /**
     * Le cœur du défaut signalé : un circuit créé sans photo était condamné à
     * n'en avoir jamais.
     */
    public function test_un_circuit_depose_sans_photo_peut_etre_illustre_plus_tard(): void
    {
        $guide = $this->prestataire();
        Sanctum::actingAs($guide);

        // 1. Dépôt SANS photo — le cas de tout circuit créé avant F8.18.
        $id = $this->postJson('/api/v1/experiences', [
            'title' => 'Saint-Louis en 3 jours',
            'destination' => 'Saint-Louis',
            'duration_days' => 3,
            'price_xof' => 150_000,
            'capacity' => 12,
        ])->assertCreated()->json('data.experience.id');

        // 2. Le prestataire corrige son annonce ET l'illustre.
        $this->patchJson("/api/v1/experiences/{$id}", [
            'title' => 'Saint-Louis et le parc du Djoudj — 3 jours',
            'price_xof' => 165_000,
        ])->assertOk()->assertJsonPath('data.experience.price_xof', 165_000);

        $mediaId = $this->deposerPhoto('experience', $id);

        // 3. La photo est bien accrochée au circuit, et ressort par son API.
        $this->getJson('/api/v1/experiences/mine')
            ->assertOk()
            ->assertJsonPath('data.0.photos.0.id', $mediaId)
            ->assertJsonPath('data.0.title', 'Saint-Louis et le parc du Djoudj — 3 jours');
    }

    public function test_un_prestataire_ne_modifie_ni_ne_retire_le_circuit_d_un_autre(): void
    {
        $circuitDautrui = TourismExperience::factory()->published()->create([
            'provider_id' => $this->prestataire()->id,
        ]);

        Sanctum::actingAs($this->prestataire());

        $this->patchJson("/api/v1/experiences/{$circuitDautrui->id}", ['price_xof' => 1])
            ->assertStatus(403);
        $this->deleteJson("/api/v1/experiences/{$circuitDautrui->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('tourism_experiences', ['id' => $circuitDautrui->id]);
    }

    /** Le statut n'est pas un champ du formulaire : il vient de la validation. */
    public function test_un_prestataire_ne_peut_pas_se_publier_lui_meme(): void
    {
        $guide = $this->prestataire();
        $circuit = TourismExperience::factory()->create([
            'provider_id' => $guide->id,
            'status' => ExperienceStatus::EN_ATTENTE_VALIDATION->value,
        ]);

        Sanctum::actingAs($guide);

        $this->patchJson("/api/v1/experiences/{$circuit->id}", [
            'status' => ExperienceStatus::PUBLIE->value,
            'price_xof' => 80_000,
        ])->assertOk();

        $this->assertSame(
            ExperienceStatus::EN_ATTENTE_VALIDATION,
            $circuit->fresh()->status,
            'Le statut doit rester la décision d\'un agent, quoi qu\'envoie le formulaire.',
        );
        $this->assertSame(80_000, (int) $circuit->fresh()->price_xof);
    }

    // =========================================================================
    // 2. Le retrait : supprimer vraiment, ou conserver
    // =========================================================================

    /**
     * Une offre jamais réservée part à la CORBEILLE, ses photos avec elle — puis
     * tout disparaît à la purge des 30 jours, fichiers compris.
     *
     * ⚠️ **Ce test vérifie les deux moitiés, et c'est délibéré.** Il ne
     * demandait autrefois qu'une chose : que tout disparaisse d'un coup. Depuis
     * la corbeille (F11.4), l'effacement se fait en DEUX temps, et le défaut
     * qui guette est précisément entre les deux — détruire les fichiers dès la
     * mise à la corbeille rendrait la restauration illusoire, l'offre revenant
     * sans une seule image. La vérification du milieu (les photos survivent)
     * est donc le cœur du test, pas un détail.
     */
    public function test_une_offre_jamais_reservee_part_a_la_corbeille_puis_disparait_a_la_purge(): void
    {
        $loueur = $this->prestataire();
        $vehicle = Vehicle::factory()->published()->create(['provider_id' => $loueur->id]);

        Sanctum::actingAs($loueur);
        $this->deposerPhoto('vehicle', $vehicle->id);

        $chemin = Media::query()->firstOrFail()->path;
        Storage::disk('public')->assertExists($chemin);

        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true)
            ->assertJsonPath('data.reason', null);

        // — Premier temps : la corbeille. La ligne est datée, PAS effacée…
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);

        // …et les photos sont intactes, sans quoi « restaurer » serait un mot vide.
        $this->assertSame(1, Media::query()->count());
        Storage::disk('public')->assertExists($chemin);

        // — Second temps : le délai s'écoule, la purge passe.
        Vehicle::withTrashed()->findOrFail($vehicle->id)
            ->forceFill(['deleted_at' => now()->subDays(ListingTrash::JOURS_DE_CONSERVATION + 1)])
            ->saveQuietly();

        $this->artisan('corbeille:purger')->assertExitCode(0);

        // Là seulement tout s'en va : la ligne, la métadonnée ET le fichier.
        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
        $this->assertSame(0, Media::query()->count());
        Storage::disk('public')->assertMissing($chemin);
    }

    /**
     * ⚠️ LE GARDE-FOU CENTRAL. Une offre réservée n'est jamais supprimée : elle
     * quitte le catalogue et reste lisible dans l'historique du client.
     */
    public function test_une_offre_deja_reservee_est_retiree_et_non_supprimee(): void
    {
        $loueur = $this->prestataire();
        $vehicle = Vehicle::factory()->published()->create(['provider_id' => $loueur->id]);
        $reservation = $this->reserver(Vehicle::class, $vehicle->id);

        Sanctum::actingAs($loueur);

        $reponse = $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', false);

        // Le prestataire doit COMPRENDRE pourquoi son offre n'a pas disparu.
        $this->assertStringContainsString('réservée', (string) $reponse->json('data.reason'));

        $this->assertSame(VehicleStatus::RETIRE, $vehicle->fresh()->status);

        // Le client garde une réservation dont l'objet existe toujours.
        $this->assertNotNull($reservation->fresh()->bookable);

        // Mais l'offre a bien quitté le catalogue public.
        $this->getJson('/api/v1/vehicles')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertStatus(404);
    }

    /**
     * Même une réservation ANNULÉE protège l'offre : elle reste dans
     * l'historique du client, et son objet doit rester lisible.
     */
    public function test_meme_une_reservation_annulee_protege_l_offre(): void
    {
        $loueur = $this->prestataire();
        $vehicle = Vehicle::factory()->published()->create(['provider_id' => $loueur->id]);

        $reservation = $this->reserver(Vehicle::class, $vehicle->id);
        $reservation->update(['status' => BookingStatus::ANNULEE_CLIENT->value]);

        Sanctum::actingAs($loueur);

        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', false);

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id]);
    }

    /**
     * ⚠️ Piège propre aux véhicules : `mobility_services.vehicle_id` est en
     * `nullOnDelete`. La base n'aurait rien empêché — elle aurait silencieusement
     * vidé les trajets de leur véhicule, donc de leur illustration (F8.18).
     */
    public function test_un_vehicule_qui_assure_des_trajets_est_retiru_et_non_supprime(): void
    {
        $transporteur = $this->prestataire();
        $vehicle = Vehicle::factory()->published()->create(['provider_id' => $transporteur->id]);
        $trajet = MobilityService::factory()->published()->create([
            'provider_id' => $transporteur->id,
            'vehicle_id' => $vehicle->id,
        ]);

        Sanctum::actingAs($transporteur);

        $reponse = $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', false);

        $this->assertStringContainsString('trajets', (string) $reponse->json('data.reason'));
        $this->assertSame($vehicle->id, (int) $trajet->fresh()->vehicle_id);
    }

    /**
     * Le circuit suit exactement la même règle que le véhicule : le service de
     * retrait est unique, et c'est ce qui garantit qu'elle ne divergera pas.
     */
    public function test_le_circuit_obeit_a_la_meme_regle_que_le_vehicule(): void
    {
        $guide = $this->prestataire();
        $jamaisReserve = TourismExperience::factory()->published()->create(['provider_id' => $guide->id]);
        $dejaReserve = TourismExperience::factory()->published()->create(['provider_id' => $guide->id]);
        $this->reserver(TourismExperience::class, $dejaReserve->id);

        Sanctum::actingAs($guide);

        $this->deleteJson("/api/v1/experiences/{$jamaisReserve->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
        // F11.4 — corbeille : la ligne est rangée, pas effacée.
        $this->assertSoftDeleted('tourism_experiences', ['id' => $jamaisReserve->id]);

        $this->deleteJson("/api/v1/experiences/{$dejaReserve->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', false);
        $this->assertSame(ExperienceStatus::RETIRE, $dejaReserve->fresh()->status);

        // Le catalogue public ne montre plus ni l'une ni l'autre.
        $this->getJson('/api/v1/experiences')->assertOk()->assertJsonCount(0, 'data');
    }

    /**
     * Un retrait n'est pas une condamnation : le prestataire peut remettre son
     * offre en vente, elle repasse alors par la validation d'un agent.
     */
    public function test_une_offre_retiree_peut_etre_remise_en_vente(): void
    {
        $loueur = $this->prestataire();
        $vehicle = Vehicle::factory()->published()->create(['provider_id' => $loueur->id]);
        $this->reserver(Vehicle::class, $vehicle->id);

        Sanctum::actingAs($loueur);
        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")->assertOk();
        $this->assertSame(VehicleStatus::RETIRE, $vehicle->fresh()->status);

        // La remise en vente passe par le geste normal d'édition ; le statut
        // n'étant pas modifiable par le prestataire, c'est un agent qui republie.
        $this->patchJson("/api/v1/vehicles/{$vehicle->id}", ['price_per_day_xof' => 45_000])
            ->assertOk();

        $this->assertSame(
            VehicleStatus::RETIRE,
            $vehicle->fresh()->status,
            'Une offre retirée ne se republie pas toute seule à la première modification.',
        );
        $this->assertSame(45_000, (int) $vehicle->fresh()->price_per_day_xof);
    }
}
