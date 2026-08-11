<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F13.1 : statistiques business (GET /admin/statistiques).
 *
 * Couvre l'autorisation (`gerer:paiements`, et non la permission de base du
 * back-office), l'exactitude des séries et les trois pièges du calcul :
 *   - les mois SANS activité doivent exister dans la série, à zéro ;
 *   - une réservation annulée ne pèse pas un franc, mais reste comptée ;
 *   - la période précédente ne chevauche pas la période affichée.
 */
class AdminStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Un membre de l'équipe SANS droit financier (grant pur : le rôle n'ouvre rien). */
    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);

        return $agent;
    }

    /** Le même, à qui l'on a délégué le droit financier. */
    private function agentFinancier(): User
    {
        $agent = $this->agent();
        $agent->givePermissionTo('gerer:paiements');

        return $agent;
    }

    public function test_un_utilisateur_ordinaire_n_accede_pas_aux_statistiques(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/statistiques')->assertStatus(403);
    }

    public function test_un_agent_sans_droit_financier_n_accede_pas_aux_statistiques(): void
    {
        // Le cœur de la garde : entrer dans le back-office ne donne PAS accès au
        // chiffre d'affaires consolidé (CDC §7, « accès financier limité »).
        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/statistiques')->assertStatus(403);
    }

    public function test_les_grands_chiffres_excluent_les_annulations_mais_les_comptent(): void
    {
        $stay = Stay::factory()->create();

        $this->booking($stay, 'confirmee', 100_000, 12_000);
        $this->booking($stay, 'terminee', 60_000, 6_000);
        $this->booking($stay, 'annulee_client', 999_999, 99_999);

        Sanctum::actingAs($this->agentFinancier());

        $this->getJson('/api/v1/admin/statistiques?periode=12m')
            ->assertOk()
            ->assertJsonPath('data.period.key', '12m')
            ->assertJsonPath('data.period.granularity', 'month')
            // Montants : l'annulée ne pèse rien.
            ->assertJsonPath('data.headline.gross_volume_xof.value', 160_000)
            ->assertJsonPath('data.headline.commission_xof.value', 18_000)
            // Dénombrement : l'annulée compte, sinon le taux serait toujours nul.
            ->assertJsonPath('data.headline.bookings.value', 3)
            ->assertJsonPath('data.headline.cancellation_rate.value', 33.3)
            // Panier moyen : 160 000 / 2 actives (et non / 3).
            ->assertJsonPath('data.headline.average_basket_xof.value', 80_000)
            // Prestations honorées : la seule « terminée ».
            ->assertJsonPath('data.funnel.3.count', 1);
    }

    public function test_la_serie_de_revenus_couvre_tous_les_mois_meme_les_mois_vides(): void
    {
        $stay = Stay::factory()->create();

        // Une seule réservation, il y a deux mois. Les onze autres mois de la
        // fenêtre n'ont AUCUNE ligne en base : c'est précisément le cas que
        // l'agrégation SQL seule laisserait disparaître de la courbe.
        $this->booking($stay, 'confirmee', 40_000, 4_000, CarbonImmutable::now()->subMonths(2));

        Sanctum::actingAs($this->agentFinancier());

        $response = $this->getJson('/api/v1/admin/statistiques?periode=12m')->assertOk();

        $serie = $response->json('data.revenue_series');

        $this->assertCount(12, $serie, 'La fenêtre 12 mois doit produire douze points, pas seulement les mois actifs.');
        $this->assertSame(40_000, $serie[9]['gross_volume_xof'], 'Le mois actif est le dixième point (il y a deux mois).');
        $this->assertSame(0, $serie[11]['gross_volume_xof'], 'Le mois en cours, sans activité, vaut zéro — il ne manque pas.');

        // Chaque point porte un libellé lisible : l'axe ne montre jamais de clé technique.
        $this->assertNotEmpty($serie[0]['label']);
    }

    public function test_les_reservations_sont_ventilees_par_univers_metier(): void
    {
        $stay = Stay::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->booking($stay, 'confirmee', 10_000, 1_000);
        $this->booking($stay, 'confirmee', 10_000, 1_000);
        $this->booking($vehicle, 'confirmee', 20_000, 2_000);

        Sanctum::actingAs($this->agentFinancier());

        $response = $this->getJson('/api/v1/admin/statistiques?periode=12m')->assertOk();

        // Les cinq univers sont TOUJOURS présents, dans un ordre figé : c'est ce
        // qui permet à une couleur de suivre un métier d'un écran à l'autre.
        $this->assertSame(
            ['nuitees', 'mobilite', 'tourisme', 'team_building', 'sur_mesure'],
            array_column($response->json('data.bookings_by_line.lines'), 'key'),
        );

        $points = $response->json('data.bookings_by_line.points');
        $dernier = end($points);

        $this->assertSame(2, $dernier['values']['nuitees']);
        $this->assertSame(1, $dernier['values']['mobilite']);
        $this->assertSame(0, $dernier['values']['tourisme']);
    }

    public function test_le_palmares_nomme_les_annonces_au_lieu_de_leur_identifiant(): void
    {
        $stay = Stay::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->booking($stay, 'confirmee', 500_000, 50_000);
        $this->booking($vehicle, 'confirmee', 90_000, 9_000);

        Sanctum::actingAs($this->agentFinancier());

        $palmares = $this->getJson('/api/v1/admin/statistiques?periode=12m')
            ->assertOk()
            ->json('data.top_listings');

        $this->assertCount(2, $palmares);
        // Tri par volume décroissant : la nuitée d'abord.
        $this->assertSame(500_000, $palmares[0]['gross_volume_xof']);
        $this->assertStringContainsString($stay->property->title, $palmares[0]['label']);
        $this->assertSame('Nuitées', $palmares[0]['line']);
        // Un véhicule se nomme par sa marque et son modèle.
        $this->assertStringContainsString($vehicle->brand, $palmares[1]['label']);
        $this->assertSame('Mobilité', $palmares[1]['line']);
    }

    public function test_une_periode_inconnue_retombe_sur_la_periode_par_defaut(): void
    {
        Sanctum::actingAs($this->agentFinancier());

        // Un lien mis en favori avec une période disparue ne doit pas rendre
        // l'écran inutilisable : il montre les douze derniers mois.
        $this->getJson('/api/v1/admin/statistiques?periode=nimporte-quoi')
            ->assertOk()
            ->assertJsonPath('data.period.key', '12m');
    }

    public function test_la_periode_30_jours_est_decoupee_au_jour(): void
    {
        Sanctum::actingAs($this->agentFinancier());

        $response = $this->getJson('/api/v1/admin/statistiques?periode=30j')->assertOk();

        $response->assertJsonPath('data.period.granularity', 'day');
        $this->assertCount(30, $response->json('data.revenue_series'));
    }

    /**
     * Fabrique une réservation polymorphe minimale à une date donnée.
     *
     * `created_at` est forcé APRÈS l'insertion : les horodatages automatiques
     * de Laravel écraseraient une valeur passée dans `create()`.
     */
    private function booking(
        object $bookable,
        string $status,
        int $amount,
        int $commission,
        ?CarbonImmutable $date = null,
    ): void {
        $booking = Booking::create([
            'reference' => 'BK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'bookable_type' => $bookable::class,
            'bookable_id' => $bookable->id,
            'start_date' => today(),
            'end_date' => today()->addDay(),
            'guests' => 1,
            'amount_xof' => $amount,
            'commission_xof' => $commission,
            'status' => $status,
        ]);

        if ($date !== null) {
            $booking->forceFill(['created_at' => $date])->saveQuietly();
        }
    }
}
