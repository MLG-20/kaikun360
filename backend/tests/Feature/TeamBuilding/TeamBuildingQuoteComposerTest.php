<?php

namespace Tests\Feature\TeamBuilding;

use App\Modules\TeamBuilding\Enums\TeamBuildingQuoteStatus;
use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use App\Modules\TeamBuilding\Services\TeamBuildingQuoteComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Tests de la composition de devis multi-prestataires (phase B9.2) : calcul des
 * lignes, des totaux (sous-total + marge) et persistance en brouillon.
 */
class TeamBuildingQuoteComposerTest extends TestCase
{
    use RefreshDatabase;

    private function components(): array
    {
        return [
            ['category' => 'hebergement', 'label' => 'Lodge', 'module' => 'Stay', 'quantity' => 20, 'unit_price_xof' => 40_000],
            ['category' => 'activite', 'label' => 'Excursion', 'module' => 'Explore', 'quantity' => 20, 'unit_price_xof' => 10_000],
            ['category' => 'mobilite', 'label' => 'Bus', 'module' => 'Mobility', 'quantity' => 1, 'unit_price_xof' => 150_000],
        ];
    }

    public function test_le_montant_d_une_ligne_est_quantite_fois_prix(): void
    {
        $lines = app(TeamBuildingQuoteComposer::class)->buildLines($this->components());

        $this->assertSame(800_000, $lines[0]['amount_xof']); // 20 × 40 000
        $this->assertSame(200_000, $lines[1]['amount_xof']); // 20 × 10 000
        $this->assertSame(150_000, $lines[2]['amount_xof']); // 1 × 150 000
    }

    public function test_les_totaux_incluent_la_marge(): void
    {
        $composer = app(TeamBuildingQuoteComposer::class);
        $lines = $composer->buildLines($this->components());

        $totals = $composer->totals($lines, 15.0);

        // Sous-total = 800 000 + 200 000 + 150 000 = 1 150 000.
        $this->assertSame(1_150_000, $totals['subtotal_xof']);
        // Marge 15 % = 172 500 ; total = 1 322 500.
        $this->assertSame(172_500, $totals['margin_xof']);
        $this->assertSame(1_322_500, $totals['total_xof']);
    }

    public function test_composer_persiste_un_devis_brouillon(): void
    {
        $request = TeamBuildingRequest::factory()->create();

        $quote = app(TeamBuildingQuoteComposer::class)->composeFor($request, $this->components());

        $this->assertInstanceOf(TeamBuildingQuote::class, $quote);
        $this->assertSame(TeamBuildingQuoteStatus::BROUILLON, $quote->status);
        $this->assertSame(1_322_500, $quote->total_xof);
        $this->assertCount(3, $quote->lines);
        $this->assertTrue($request->quotes()->whereKey($quote->id)->exists());
    }

    public function test_un_devis_vide_est_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(TeamBuildingQuoteComposer::class)->buildLines([]);
    }
}
