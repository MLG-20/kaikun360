<?php

namespace App\Modules\Build\Services;

use App\Modules\Build\Enums\ConstructionLot;
use App\Modules\Build\Enums\ConstructionQuoteStatus;
use App\Modules\Build\Models\ConstructionQuote;
use App\Modules\Build\Models\ConstructionRequest;
use App\Support\Settings;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Composition d'un devis de chantier ventilé par lot (F7.3.e2).
 *
 * Reprend le motif éprouvé du composeur de devis pack du team building (B9.2) :
 * des lignes libres normalisées, une marge appliquée sur le sous-total, des
 * totaux FIGÉS à la composition (un devis envoyé ne doit plus bouger).
 *
 * Différence avec le team building : la ligne porte un **lot** (corps d'état) et
 * une **unité** (m², u, forfait, jour…), parce qu'un devis BTP se lit et se
 * conteste poste par poste.
 */
class ConstructionQuoteComposer
{
    /**
     * Marge par défaut, en pourcentage, si le réglage `build.margin_rate` n'a pas
     * été surchargé au back-office.
     */
    public const DEFAULT_MARGIN_RATE = 15.0;

    /**
     * Normalise les lignes saisies (montant calculé, lot validé, ordre d'exécution).
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    public function buildLines(array $entries): array
    {
        if ($entries === []) {
            throw new InvalidArgumentException('Un devis doit contenir au moins une ligne.');
        }

        $lines = array_map(function (array $entry): array {
            $lot = ConstructionLot::from($entry['lot']);
            // Une quantité peut être décimale (18,5 m² de carrelage) — contrairement
            // au team building, qui compte des participants.
            $quantity = max(0.01, (float) ($entry['quantity'] ?? 1));
            $unitPrice = max(0, (int) ($entry['unit_price_xof'] ?? 0));

            return [
                'lot' => $lot->value,
                'lot_label' => $lot->label(),
                'label' => (string) ($entry['label'] ?? $lot->label()),
                'unit' => $entry['unit'] ?? null,
                'quantity' => $quantity,
                'unit_price_xof' => $unitPrice,
                // Arrondi au franc : le XOF n'a pas de subdivision en circulation.
                'amount_xof' => (int) round($quantity * $unitPrice),
            ];
        }, array_values($entries));

        return $this->sortByExecutionOrder($lines);
    }

    /**
     * Calcule les totaux (sous-total, marge, total) pour un jeu de lignes.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{subtotal_xof:int, margin_rate:float, margin_xof:int, total_xof:int}
     */
    public function totals(array $lines, float $marginRate): array
    {
        $subtotal = (int) array_sum(array_column($lines, 'amount_xof'));
        $margin = (int) round($subtotal * $marginRate / 100);

        return [
            'subtotal_xof' => $subtotal,
            'margin_rate' => $marginRate,
            'margin_xof' => $margin,
            'total_xof' => $subtotal + $margin,
        ];
    }

    /**
     * Compose et persiste un devis en brouillon.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    public function composeFor(
        ConstructionRequest $request,
        array $entries,
        ?float $marginRate = null,
        ?string $validUntil = null,
        ?int $authorId = null,
    ): ConstructionQuote {
        $marginRate ??= (float) Settings::get('build.margin_rate', self::DEFAULT_MARGIN_RATE);

        $lines = $this->buildLines($entries);
        $totals = $this->totals($lines, $marginRate);

        return $request->quotes()->create([
            'reference' => 'CQ-'.Str::upper(Str::random(8)),
            'lines' => $lines,
            'valid_until' => $validUntil,
            'status' => ConstructionQuoteStatus::BROUILLON->value,
            'created_by' => $authorId,
            ...$totals,
        ]);
    }

    /**
     * Trie les lignes dans l'ordre d'exécution du chantier (ordre des cas de
     * l'enum), en conservant l'ordre de saisie à l'intérieur d'un même lot.
     *
     * Un devis présenté dans l'ordre où l'agent a tapé ses lignes est illisible :
     * on veut voir les fondations avant les finitions. Ce tri est fait une fois, à
     * la composition, puisque les lignes sont ensuite figées.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function sortByExecutionOrder(array $lines): array
    {
        $order = array_flip(ConstructionLot::values());

        // `usort` n'est pas stable avant PHP 8.0 ; il l'est depuis, l'ordre de
        // saisie est donc préservé au sein d'un lot.
        usort($lines, fn (array $a, array $b) => $order[$a['lot']] <=> $order[$b['lot']]);

        return $lines;
    }
}
