<?php

namespace App\Modules\TeamBuilding\Services;

use App\Modules\TeamBuilding\Enums\QuoteLineCategory;
use App\Modules\TeamBuilding\Enums\TeamBuildingQuoteStatus;
use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use App\Modules\TeamBuilding\Models\TeamBuildingRequest;
use App\Support\Settings;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Composition d'un devis team building multi-prestataires (phase B9.2).
 *
 * Agrège des composants issus de plusieurs modules (lieu, hébergement,
 * restauration, activité, mobilité, animation) en lignes de devis, applique une
 * marge et fige les totaux. Chaque composant : {category, label, module?,
 * quantity, unit_price_xof}. Le montant d'une ligne = quantité × prix unitaire.
 */
class TeamBuildingQuoteComposer
{
    /**
     * Taux de marge par défaut de la plateforme (pourcentage).
     */
    public const DEFAULT_MARGIN_RATE = 15.0;

    /**
     * Normalise les composants en lignes de devis (avec montant calculé).
     *
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int, array<string, mixed>>
     */
    public function buildLines(array $components): array
    {
        if ($components === []) {
            throw new InvalidArgumentException('Un devis doit contenir au moins une ligne.');
        }

        return array_map(function (array $c): array {
            $category = QuoteLineCategory::from($c['category']);
            $quantity = max(1, (int) ($c['quantity'] ?? 1));
            $unitPrice = max(0, (int) ($c['unit_price_xof'] ?? 0));

            return [
                'category' => $category->value,
                'label' => (string) ($c['label'] ?? $category->label()),
                'module' => $c['module'] ?? null,
                'quantity' => $quantity,
                'unit_price_xof' => $unitPrice,
                'amount_xof' => $quantity * $unitPrice,
            ];
        }, array_values($components));
    }

    /**
     * Calcule les totaux (sous-total, marge, total) pour un jeu de lignes.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{subtotal_xof:int, margin_rate:float, margin_xof:int, total_xof:int}
     */
    public function totals(array $lines, float $marginRate): array
    {
        $subtotal = array_sum(array_column($lines, 'amount_xof'));
        $margin = (int) round($subtotal * $marginRate / 100);

        return [
            'subtotal_xof' => (int) $subtotal,
            'margin_rate' => $marginRate,
            'margin_xof' => $margin,
            'total_xof' => (int) $subtotal + $margin,
        ];
    }

    /**
     * Compose et persiste un devis (statut brouillon) pour une demande.
     *
     * @param  array<int, array<string, mixed>>  $components
     */
    public function composeFor(
        TeamBuildingRequest $request,
        array $components,
        ?float $marginRate = null
    ): TeamBuildingQuote {
        $marginRate ??= (float) Settings::get('teambuilding.margin_rate', self::DEFAULT_MARGIN_RATE);

        $lines = $this->buildLines($components);
        $totals = $this->totals($lines, $marginRate);

        return $request->quotes()->create([
            'reference' => 'TBQ-'.Str::upper(Str::random(8)),
            'lines' => $lines,
            'status' => TeamBuildingQuoteStatus::BROUILLON->value,
            ...$totals,
        ]);
    }
}
