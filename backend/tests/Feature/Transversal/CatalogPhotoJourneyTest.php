<?php

namespace Tests\Feature\Transversal;

use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Mobility\Enums\VehicleStatus;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F8.18 — LE PARCOURS DE LA PHOTO, DU PARTENAIRE À LA CARTE DU CATALOGUE.
 *
 * POURQUOI CE FICHIER EXISTE
 * --------------------------
 * Trois univers sur cinq — véhicules, circuits, trajets — n'avaient **aucune
 * photo possible**, et le défaut était réparti sur quatre étages qui allaient
 * chacun très bien tout seuls :
 *
 *   1. le modèle portait `HasMedia` (F8.1) ;
 *   2. `POST /media/upload` acceptait les clés `vehicle` et `experience`
 *      (B12.1) et vérifiait la policy ;
 *   3. la file de validation du back-office savait afficher une galerie (F8.1) ;
 *   4. …mais l'API ne renvoyait la photo nulle part, aucun écran ne la déposait,
 *      et les cartes codaient `image: null` en dur.
 *
 * Chaque étage était donc « fait », et l'ensemble ne fonctionnait pas. Aucun
 * test de couche ne pouvait le voir : c'est un défaut de **chaînage**, et il
 * n'apparaît qu'en traversant.
 *
 * ⚠️ Comme `MoneyJourneyTest`, ce fichier **ne pose aucun état à la main** : la
 * photo doit être déposée par l'API, ressortir par l'API du catalogue, et être
 * vue par l'agent qui valide. Trois portes différentes, une seule vérité.
 */
class CatalogPhotoJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        // Les images sont stockées sur le disque public ; on ne touche jamais
        // au vrai stockage depuis les tests.
        Storage::fake('public');
    }

    private function prestataire(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::PRESTATAIRE->value);

        return $user;
    }

    /** L'agent qui valide les annonces avant publication. */
    private function agentValidateur(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    /** Dépose une photo sur une ressource, par l'API réelle. */
    private function deposer(string $type, int $id, bool $principale = true): int
    {
        return $this->post('/api/v1/media/upload', [
            'mediable_type' => $type,
            'mediable_id' => $id,
            'file' => UploadedFile::fake()->image('annonce.jpg', 1200, 800),
            'is_primary' => $principale ? '1' : '0',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.media.id');
    }

    // =========================================================================
    // 1. Véhicule : du dépôt par le loueur à la carte du catalogue
    // =========================================================================

    public function test_la_photo_deposee_par_le_loueur_ressort_sur_sa_carte_et_sur_sa_fiche(): void
    {
        $loueur = $this->prestataire();
        $vehicle = Vehicle::factory()->published()->create(['provider_id' => $loueur->id]);

        // --- 1. Avant tout dépôt, la carte n'a pas de photo ----------------
        // La clé doit EXISTER et valoir null : c'est ce contrat que l'écran lit
        // pour décider d'afficher sa vignette de repli.
        $avant = $this->getJson('/api/v1/vehicles')->assertOk();
        $this->assertNull($avant->json('data.0.photo_url'));
        $this->assertSame([], $avant->json('data.0.photos'));

        // --- 2. Le loueur dépose sa photo ----------------------------------
        Sanctum::actingAs($loueur);
        $mediaId = $this->deposer('vehicle', $vehicle->id);

        // --- 3. Elle ressort sur la CARTE, immédiatement --------------------
        // ⚠️ Sans la purge du cache catalogue déclenchée par le média, cette
        // assertion échouerait pendant 5 minutes : le loueur téléverserait,
        // reviendrait au catalogue, et n'y verrait rien changer.
        $carte = $this->getJson('/api/v1/vehicles')->assertOk();
        $this->assertNotNull(
            $carte->json('data.0.photo_url'),
            'La photo doit apparaître sur la carte sans attendre l\'expiration du cache.',
        );

        // --- 4. Et sur la FICHE, avec la galerie entière --------------------
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.photos.0.id', $mediaId)
            ->assertJsonPath('data.photos.0.is_primary', true);

        // --- 5. Le loueur la retrouve dans SON espace pour la gérer --------
        $this->getJson('/api/v1/vehicles/mine')
            ->assertOk()
            ->assertJsonPath('data.0.photos.0.id', $mediaId);
    }

    /**
     * Un média masqué par la modération quitte les écrans publics **tout de
     * suite**, sans attendre l'expiration du cache : c'est le geste par lequel
     * un agent retire une photo choquante ou trompeuse.
     */
    public function test_une_photo_masquee_par_la_moderation_quitte_aussitot_le_catalogue(): void
    {
        $loueur = $this->prestataire();
        $vehicle = Vehicle::factory()->published()->create(['provider_id' => $loueur->id]);

        Sanctum::actingAs($loueur);
        $mediaId = $this->deposer('vehicle', $vehicle->id);

        $this->assertNotNull($this->getJson('/api/v1/vehicles')->json('data.0.photo_url'));

        Sanctum::actingAs($this->agentValidateur());
        $this->patchJson("/api/v1/admin/media/{$mediaId}/status", [
            'status' => MediaStatus::MASQUE->value,
        ])->assertOk();

        $this->assertNull(
            $this->getJson('/api/v1/vehicles')->json('data.0.photo_url'),
            'Une photo masquée ne doit plus illustrer aucune carte publique.',
        );
    }

    // =========================================================================
    // 2. Circuit touristique
    // =========================================================================

    public function test_la_photo_du_circuit_ressort_sur_la_carte_tourisme(): void
    {
        $guide = $this->prestataire();
        $experience = TourismExperience::factory()->published()->create(['provider_id' => $guide->id]);

        Sanctum::actingAs($guide);
        $mediaId = $this->deposer('experience', $experience->id);

        $this->assertNotNull($this->getJson('/api/v1/experiences')->json('data.0.photo_url'));

        $this->getJson("/api/v1/experiences/{$experience->id}")
            ->assertOk()
            ->assertJsonPath('data.photos.0.id', $mediaId);
    }

    // =========================================================================
    // 3. Trajet : la photo est celle du véhicule qui l'opère
    // =========================================================================

    /**
     * ⚠️ Le cœur du choix d'architecture : le prestataire ne téléverse **rien**
     * pour son trajet. Il illustre son minibus une fois, et tous les départs
     * programmés avec ce minibus sont illustrés.
     */
    public function test_le_trajet_herite_des_photos_de_son_vehicule(): void
    {
        $transporteur = $this->prestataire();
        $vehicle = Vehicle::factory()->published()->create(['provider_id' => $transporteur->id]);
        $trajet = MobilityService::factory()->published()->create([
            'provider_id' => $transporteur->id,
            'vehicle_id' => $vehicle->id,
        ]);

        Sanctum::actingAs($transporteur);
        $this->deposer('vehicle', $vehicle->id);

        // Aucun dépôt sur le trajet lui-même, et pourtant la carte est illustrée.
        $this->assertNotNull(
            $this->getJson('/api/v1/mobility-services')->json('data.0.photo_url'),
            'Le trajet doit hériter de la photo du véhicule qui l\'opère.',
        );

        $this->getJson("/api/v1/mobility-services/{$trajet->id}")
            ->assertOk()
            ->assertJsonPath('data.mobility_service.photos.0.is_primary', true);
    }

    /**
     * Le cas que le repli dégradé doit continuer de servir : `vehicle_id` est
     * nullable, un trajet sans véhicule rattaché n'a légitimement pas de photo.
     * La clé doit valoir `null` — surtout pas être absente, sinon l'écran ne sait
     * pas s'il regarde un trajet sans photo ou une réponse incomplète.
     */
    public function test_un_trajet_sans_vehicule_annonce_franchement_son_absence_de_photo(): void
    {
        MobilityService::factory()->published()->create(['vehicle_id' => null]);

        $reponse = $this->getJson('/api/v1/mobility-services')->assertOk();

        $this->assertArrayHasKey('photo_url', $reponse->json('data.0'));
        $this->assertNull($reponse->json('data.0.photo_url'));
    }

    // =========================================================================
    // 4. Le back-office voit les photos AVANT de valider
    // =========================================================================

    /**
     * L'exigence posée par l'utilisateur : **rien ne se valide à l'aveugle**.
     * La file de validation transporte la galerie depuis F8.1 ; ce test prouve
     * qu'elle transporte enfin quelque chose, et que le lien tient de bout en
     * bout — dépôt par le prestataire, examen par l'agent, publication.
     */
    public function test_l_agent_voit_les_photos_dans_la_file_avant_de_valider(): void
    {
        $loueur = $this->prestataire();
        $vehicle = Vehicle::factory()->create([
            'provider_id' => $loueur->id,
            'status' => VehicleStatus::EN_ATTENTE_VALIDATION->value,
            'published_at' => null,
        ]);

        Sanctum::actingAs($loueur);
        $mediaId = $this->deposer('vehicle', $vehicle->id);

        Sanctum::actingAs($this->agentValidateur());

        // La FILE porte un aperçu : l'agent voit d'un coup d'œil les annonces
        // sans visuel, celles qu'il ne faut surtout pas publier telles quelles.
        $file = $this->getJson('/api/v1/admin/queue?type=vehicle')->assertOk();

        $ligne = collect($file->json('data'))->firstWhere('id', $vehicle->id);
        $this->assertNotNull($ligne, 'Le véhicule en attente doit figurer dans la file.');
        $this->assertSame(1, $ligne['media']['total']);
        $this->assertSame(1, $ligne['media']['images']);
        $this->assertNotEmpty($ligne['media']['items'][0]['url']);

        // La FICHE porte la galerie entière : c'est là que la décision se prend.
        $this->getJson("/api/v1/admin/queue/vehicle/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.entry.media.items.0.id', $mediaId);
    }

    /**
     * Une photo masquée reste visible de l'AGENT alors qu'elle a quitté le site :
     * sans cela il ne pourrait jamais rétablir une photo écartée par erreur.
     */
    public function test_l_agent_voit_encore_les_photos_qu_il_a_masquees(): void
    {
        $loueur = $this->prestataire();
        $vehicle = Vehicle::factory()->create([
            'provider_id' => $loueur->id,
            'status' => VehicleStatus::EN_ATTENTE_VALIDATION->value,
            'published_at' => null,
        ]);

        Sanctum::actingAs($loueur);
        $mediaId = $this->deposer('vehicle', $vehicle->id);

        Sanctum::actingAs($this->agentValidateur());
        $this->patchJson("/api/v1/admin/media/{$mediaId}/status", [
            'status' => MediaStatus::MASQUE->value,
        ])->assertOk();

        $detail = $this->getJson("/api/v1/admin/queue/vehicle/{$vehicle->id}")->assertOk();

        $this->assertSame(1, $detail->json('data.entry.media.hidden'));
        $this->assertTrue($detail->json('data.entry.media.items.0.is_hidden'));
    }

    // =========================================================================
    // 5. Les garde-fous d'autorisation, sur les univers fraîchement ouverts
    // =========================================================================

    /**
     * ⚠️ Le dépôt réutilise la policy `update` de la ressource illustrée. Ouvrir
     * les photos aux véhicules et aux circuits ouvre donc une porte d'écriture
     * sur les annonces des autres : ce test la referme explicitement, pour les
     * deux univers d'un coup.
     */
    public function test_un_prestataire_ne_peut_pas_illustrer_l_annonce_d_un_autre(): void
    {
        $vehicleDautrui = Vehicle::factory()->published()->create([
            'provider_id' => $this->prestataire()->id,
        ]);
        $circuitDautrui = TourismExperience::factory()->published()->create([
            'provider_id' => $this->prestataire()->id,
        ]);

        Sanctum::actingAs($this->prestataire());

        foreach ([['vehicle', $vehicleDautrui->id], ['experience', $circuitDautrui->id]] as [$type, $id]) {
            $this->post('/api/v1/media/upload', [
                'mediable_type' => $type,
                'mediable_id' => $id,
                'file' => UploadedFile::fake()->image('intrus.jpg'),
            ], ['Accept' => 'application/json'])->assertStatus(403);
        }

        $this->assertSame(0, Media::query()->count());
    }
}
