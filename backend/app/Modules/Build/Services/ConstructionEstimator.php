<?php

namespace App\Modules\Build\Services;

use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\FinishLevel;

/**
 * Simulateur de budget de construction (phase B5.4).
 *
 * Produit une estimation INDICATIVE à partir de trois paramètres : l'objectif
 * (qui fixe un coût de base au m²), la surface et le niveau de finition (un
 * coefficient multiplicateur). Le résultat est arrondi pour rester indicatif.
 *
 * ⚠️ Estimation non contractuelle : le devis ferme relève de la couche Quotes (B11).
 */
class ConstructionEstimator
{
    /**
     * Coût de base au m² (XOF) selon l'objectif des travaux.
     */
    private const BASE_PRICE_PER_M2 = [
        ConstructionObjective::CONSTRUCTION_NEUVE->value => 250_000,
        ConstructionObjective::EXTENSION->value => 220_000,
        ConstructionObjective::RENOVATION->value => 150_000,
    ];

    /**
     * Coefficient multiplicateur selon le niveau de finition.
     */
    private const FINISH_COEFFICIENT = [
        FinishLevel::ECONOMIQUE->value => 0.85,
        FinishLevel::STANDARD->value => 1.0,
        FinishLevel::PREMIUM->value => 1.35,
    ];

    /**
     * Pas d'arrondi de l'estimation (pour rester indicatif).
     */
    private const ROUNDING_STEP = 100_000;

    /**
     * Estimation indicative du coût total (XOF).
     */
    public function estimate(ConstructionObjective $objective, int $surfaceM2, FinishLevel $finishLevel): int
    {
        $pricePerM2 = self::BASE_PRICE_PER_M2[$objective->value];
        $coefficient = self::FINISH_COEFFICIENT[$finishLevel->value];

        $raw = $pricePerM2 * max(0, $surfaceM2) * $coefficient;

        // Arrondi au pas le plus proche pour une estimation « ronde ».
        return (int) (round($raw / self::ROUNDING_STEP) * self::ROUNDING_STEP);
    }

    /**
     * Détail structuré de l'estimation (consommable par le frontend).
     *
     * @return array<string, mixed>
     */
    public function breakdown(ConstructionObjective $objective, int $surfaceM2, FinishLevel $finishLevel): array
    {
        return [
            'objective' => $objective->value,
            'surface_m2' => $surfaceM2,
            'finish_level' => $finishLevel->value,
            'price_per_m2_xof' => self::BASE_PRICE_PER_M2[$objective->value],
            'finish_coefficient' => self::FINISH_COEFFICIENT[$finishLevel->value],
            'estimated_cost_xof' => $this->estimate($objective, $surfaceM2, $finishLevel),
        ];
    }
}
