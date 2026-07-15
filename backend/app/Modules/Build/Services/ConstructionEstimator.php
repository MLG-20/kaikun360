<?php

namespace App\Modules\Build\Services;

use App\Modules\Build\Enums\ConstructionObjective;
use App\Modules\Build\Enums\ConstructionZone;
use App\Modules\Build\Enums\FinishLevel;
use App\Support\Settings;

/**
 * Simulateur de budget de construction (phase B5.4, enrichi pour coller aux
 * réalités sénégalaises).
 *
 * Produit une estimation INDICATIVE et détaillée du coût d'un projet à partir de :
 *   - l'objectif (coût de base au m²) ;
 *   - la surface au sol et le nombre de niveaux (RDC, R+1, R+2…) ;
 *   - le niveau de finition (coefficient) ;
 *   - la zone géographique (surcoût logistique hors Dakar) ;
 *   - le foncier (terrain possédé ou à acquérir + frais d'acquisition).
 * Il expose aussi les frais annexes officiels (études, permis, viabilisation),
 * la répartition des travaux, l'échéancier par jalons, le délai et une
 * projection de rentabilité locative.
 *
 * ⚠️ SOURCE UNIQUE DES CHIFFRES : tous les coefficients monétaires proviennent
 * du réglage `build.pricing` ({@see \App\Support\SettingsRepository::DEFAULTS}),
 * modifiable au back-office par l'équipe (chiffres réels validés par des experts
 * BTP) sans redéploiement. Les valeurs en base surchargent les défauts du code.
 *
 * ⚠️ Estimation non contractuelle : le devis ferme relève de la couche Quotes (B11).
 */
class ConstructionEstimator
{
    /**
     * Répartition indicative du coût des travaux par poste, selon l'objectif.
     * Méthodologie (structurelle), distincte des coefficients monétaires : reste
     * en code. Le dernier poste absorbe l'arrondi.
     *
     * @var array<string, array<int, array{key: string, label: string, ratio: float}>>
     */
    private const WORKS_BREAKDOWN = [
        ConstructionObjective::CONSTRUCTION_NEUVE->value => [
            ['key' => 'gros_oeuvre', 'label' => 'Gros œuvre (fondations, structure)', 'ratio' => 0.45],
            ['key' => 'second_oeuvre', 'label' => 'Second œuvre (toiture, réseaux, menuiseries)', 'ratio' => 0.35],
            ['key' => 'finitions', 'label' => 'Finitions (revêtements, peinture, équipements)', 'ratio' => 0.20],
        ],
        ConstructionObjective::EXTENSION->value => [
            ['key' => 'gros_oeuvre', 'label' => 'Gros œuvre (fondations, structure)', 'ratio' => 0.42],
            ['key' => 'second_oeuvre', 'label' => 'Second œuvre (toiture, réseaux, menuiseries)', 'ratio' => 0.36],
            ['key' => 'finitions', 'label' => 'Finitions (revêtements, peinture, équipements)', 'ratio' => 0.22],
        ],
        ConstructionObjective::RENOVATION->value => [
            ['key' => 'gros_oeuvre', 'label' => 'Reprises de structure', 'ratio' => 0.25],
            ['key' => 'second_oeuvre', 'label' => 'Second œuvre (réseaux, menuiseries)', 'ratio' => 0.40],
            ['key' => 'finitions', 'label' => 'Finitions (revêtements, peinture, équipements)', 'ratio' => 0.35],
        ],
    ];

    /**
     * Libellés des frais annexes officiels.
     *
     * @var array<string, string>
     */
    private const FEE_LABELS = [
        'etudes' => 'Études & honoraires (architecte/ingénieur)',
        'permis' => 'Permis de construire & taxes',
        'viabilisation' => 'Viabilisation (branchements SENELEC, eau)',
    ];

    /**
     * Échéancier indicatif des décaissements par jalons validés (structurel).
     *
     * @var array<int, array{key: string, label: string, ratio: float}>
     */
    private const MILESTONES = [
        ['key' => 'demarrage', 'label' => 'Signature & démarrage', 'ratio' => 0.20],
        ['key' => 'gros_oeuvre', 'label' => 'Fondations & gros œuvre', 'ratio' => 0.30],
        ['key' => 'hors_air', 'label' => "Hors d'eau / hors d'air", 'ratio' => 0.30],
        ['key' => 'livraison', 'label' => 'Finitions & remise des clés', 'ratio' => 0.20],
    ];

    /** Cadence indicative de chantier, en mois par m² de surface totale. */
    private const MONTHS_PER_M2 = [
        ConstructionObjective::CONSTRUCTION_NEUVE->value => 1 / 28,
        ConstructionObjective::EXTENSION->value => 1 / 32,
        ConstructionObjective::RENOVATION->value => 1 / 40,
    ];

    /**
     * Barème monétaire courant (réglage `build.pricing` fusionné sur les défauts).
     *
     * @return array<string, mixed>
     */
    private function pricing(): array
    {
        $defaults = \App\Support\SettingsRepository::DEFAULTS['build.pricing']['value'];
        $override = Settings::get('build.pricing');

        // Fusion récursive : une surcharge partielle (ex. un seul prix au m²)
        // conserve les autres valeurs par défaut.
        return is_array($override) ? array_replace_recursive($defaults, $override) : $defaults;
    }

    /** Coût de base au m² (XOF) pour l'objectif. */
    private function pricePerM2(ConstructionObjective $objective): int
    {
        return (int) $this->pricing()['price_m2'][$objective->value];
    }

    /** Pas d'arrondi courant. */
    private function roundingStep(): int
    {
        return (int) ($this->pricing()['rounding_step'] ?? 100_000);
    }

    /**
     * Estimation indicative du coût des TRAVAUX seuls (XOF), au niveau RDC/Dakar
     * par défaut. Conserve la signature historique (utilisée par le dépôt de
     * demande) : niveaux = 1, zone = Dakar.
     */
    public function estimate(ConstructionObjective $objective, int $surfaceM2, FinishLevel $finishLevel): int
    {
        return $this->worksCost($objective, $surfaceM2, 1, $finishLevel, ConstructionZone::DAKAR);
    }

    /**
     * Coût des travaux (XOF) = prix/m² × surface totale × coeff finition × coeff zone,
     * arrondi au pas. La surface totale intègre les niveaux (RDC + étages).
     */
    public function worksCost(
        ConstructionObjective $objective,
        int $surfaceM2,
        int $levels,
        FinishLevel $finishLevel,
        ConstructionZone $zone
    ): int {
        $pricing = $this->pricing();
        $pricePerM2 = (int) $pricing['price_m2'][$objective->value];
        $finishCoeff = (float) $pricing['finish_coeff'][$finishLevel->value];
        $zoneCoeff = (float) $pricing['zone_coeff'][$zone->value];
        $totalSurface = max(0, $surfaceM2) * max(1, $levels);

        $raw = $pricePerM2 * $totalSurface * $finishCoeff * $zoneCoeff;
        $step = $this->roundingStep();

        return (int) (round($raw / $step) * $step);
    }

    /**
     * Détail complet et structuré de l'estimation (consommé par le frontend).
     *
     * @return array<string, mixed>
     */
    public function breakdown(
        ConstructionObjective $objective,
        int $surfaceM2,
        FinishLevel $finishLevel,
        int $levels = 1,
        ConstructionZone $zone = ConstructionZone::DAKAR,
        int $landCostXof = 0
    ): array {
        $pricing = $this->pricing();
        $levels = max(1, $levels);
        $landCostXof = max(0, $landCostXof);
        $totalSurface = max(0, $surfaceM2) * $levels;

        $worksCost = $this->worksCost($objective, $surfaceM2, $levels, $finishLevel, $zone);

        // Répartition des travaux (le dernier poste absorbe l'arrondi).
        $worksBreakdown = $this->allocate(self::WORKS_BREAKDOWN[$objective->value], $worksCost);

        // Frais annexes officiels (part des travaux), par objectif.
        $feeRatios = $pricing['fees'][$objective->value];
        $feeItems = [];
        $feesTotal = 0;
        foreach ($feeRatios as $key => $ratio) {
            $amount = $this->roundTo((int) round($worksCost * (float) $ratio), 1_000);
            $feeItems[] = [
                'key' => $key,
                'label' => self::FEE_LABELS[$key] ?? $key,
                'ratio' => (float) $ratio,
                'amount_xof' => $amount,
            ];
            $feesTotal += $amount;
        }

        // Foncier : le prix du terrain est SAISI (jamais deviné) ; seuls les
        // frais d'acquisition sont dérivés d'un taux réglable.
        $acquisitionRate = (float) $pricing['land_acquisition_rate'];
        $acquisitionFees = $this->roundTo((int) round($landCostXof * $acquisitionRate), 1_000);
        $landTotal = $landCostXof + $acquisitionFees;

        $grandTotal = $worksCost + $feesTotal + $landTotal;

        return [
            // Ancre historique = coût des travaux (compat dépôt de demande & tests).
            'estimated_cost_xof' => $worksCost,
            'inputs' => [
                'objective' => $objective->value,
                'surface_m2' => $surfaceM2,
                'levels' => $levels,
                'total_surface_m2' => $totalSurface,
                'finish_level' => $finishLevel->value,
                'zone' => $zone->value,
                'land_cost_xof' => $landCostXof,
            ],
            'works' => [
                'price_per_m2_xof' => $this->pricePerM2($objective),
                'finish_coefficient' => (float) $pricing['finish_coeff'][$finishLevel->value],
                'zone_coefficient' => (float) $pricing['zone_coeff'][$zone->value],
                'cost_xof' => $worksCost,
                'breakdown' => $worksBreakdown,
                'milestones' => $this->allocate(self::MILESTONES, $worksCost),
            ],
            'fees' => [
                'items' => $feeItems,
                'total_xof' => $feesTotal,
            ],
            'land' => [
                'included' => $landCostXof === 0,
                'cost_xof' => $landCostXof,
                'acquisition_rate' => $acquisitionRate,
                'acquisition_fees_xof' => $acquisitionFees,
                'total_xof' => $landTotal,
            ],
            'grand_total_xof' => $grandTotal,
            'duration' => $this->duration($objective, $totalSurface),
            'rental' => $this->rental($grandTotal, $pricing['rental_yield']),
        ];
    }

    /**
     * Répartit un montant total selon une liste de parts {key,label,ratio}.
     * Le dernier poste absorbe l'arrondi pour que la somme égale le total.
     *
     * @param  array<int, array{key: string, label: string, ratio: float}>  $shares
     * @return array<int, array{key: string, label: string, ratio: float, amount_xof: int}>
     */
    private function allocate(array $shares, int $total): array
    {
        $allocated = 0;
        $last = count($shares) - 1;

        $out = [];
        foreach ($shares as $index => $share) {
            $amount = $index === $last ? $total - $allocated : (int) round($total * $share['ratio']);
            $allocated += $amount;
            $out[] = $share + ['amount_xof' => $amount];
        }

        return $out;
    }

    /**
     * Délai indicatif du chantier (mois), fonction de la surface totale et de
     * l'objectif. Une part fixe couvre la mobilisation (études, autorisations).
     *
     * @return array{min_months: int, max_months: int}
     */
    private function duration(ConstructionObjective $objective, int $totalSurface): array
    {
        $mobilization = $objective === ConstructionObjective::RENOVATION ? 1 : 2;
        $central = $mobilization + $totalSurface * self::MONTHS_PER_M2[$objective->value];

        return [
            'min_months' => max(1, (int) round($central * 0.85)),
            'max_months' => max(1, (int) round($central * 1.15)),
        ];
    }

    /**
     * Projection de rentabilité locative brute indicative, pour chaque mode
     * d'exploitation, à partir du total investi (traité comme capital).
     *
     * @param  array<string, array{min: float, max: float}>  $yields
     * @return array<string, array{monthly_income_xof: int, yield_min_pct: int, yield_max_pct: int, payback_years: int}>
     */
    private function rental(int $totalInvested, array $yields): array
    {
        $out = [];
        foreach ($yields as $mode => $range) {
            $central = ((float) $range['min'] + (float) $range['max']) / 2;
            $out[$mode] = [
                'monthly_income_xof' => $this->roundTo((int) round($totalInvested * $central / 12), 1_000),
                'yield_min_pct' => (int) round((float) $range['min'] * 100),
                'yield_max_pct' => (int) round((float) $range['max'] * 100),
                'payback_years' => $central > 0 ? (int) round(1 / $central) : 0,
            ];
        }

        return $out;
    }

    /** Arrondi d'un montant au pas indiqué (lisibilité des sous-totaux). */
    private function roundTo(int $amount, int $step): int
    {
        return $step > 0 ? (int) (round($amount / $step) * $step) : $amount;
    }
}
