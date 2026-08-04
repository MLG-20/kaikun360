<?php

namespace App\Console\Commands;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Modules\Build\Enums\ConstructionQuoteStatus;
use App\Modules\Build\Models\ConstructionQuote;
use App\Modules\TeamBuilding\Enums\TeamBuildingQuoteStatus;
use App\Modules\TeamBuilding\Models\TeamBuildingQuote;
use App\Services\QuoteConversionService;
use Illuminate\Console\Command;

/**
 * Rattrape les devis **déjà acceptés** qui n'ont pas de réservation (F8.14).
 *
 * POURQUOI CETTE COMMANDE EXISTE
 * ------------------------------
 * F8.11 puis F8.14 ont branché la conversion « devis accepté → réservation
 * payable » sur les trois familles de devis. Mais la conversion se déclenche
 * **au moment de l'acceptation** : tous les devis acceptés AVANT le déploiement
 * restent donc orphelins — statut « accepté », aucun montant exigible, et à
 * l'écran un client qui a dit oui sans jamais voir de bouton pour payer.
 *
 * Le défaut est apparu à la première vérification navigateur : deux devis
 * team building acceptés de longue date affichaient « ✓ Devis accepté » et rien
 * d'autre. Livrer la conversion sans rattraper l'existant, c'était corriger le
 * futur en laissant le passé cassé — et le passé, ici, ce sont de vraies ventes.
 *
 * SANS RISQUE À REJOUER : la conversion est idempotente (verrou de ligne +
 * `morphOne`), un devis qui porte déjà sa réservation est simplement ignoré.
 *
 * ⚠️ **Aucune notification n'est envoyée.** Prévenir aujourd'hui « votre devis
 * est accepté, réglez-le » pour un accord donné il y a des semaines serait au
 * mieux déroutant. La réservation est créée, l'écran l'affiche : la reprise de
 * contact, si elle a lieu d'être, est un geste commercial, pas un e-mail
 * automatique.
 */
class BackfillQuoteBookings extends Command
{
    protected $signature = 'devis:rattraper-reservations {--dry-run : Montre ce qui serait créé, sans rien écrire}';

    protected $description = 'Crée les réservations manquantes des devis déjà acceptés (F8.14)';

    public function handle(QuoteConversionService $conversion): int
    {
        $simulation = (bool) $this->option('dry-run');

        if ($simulation) {
            $this->warn('Simulation : aucune écriture en base.');
        }

        $total = 0;

        // Chaque famille a sa table, son enum de statut et sa méthode de
        // conversion — d'où les trois passes plutôt qu'une boucle générique.
        $total += $this->traiter(
            'devis sur-mesure',
            Quote::query()->where('status', QuoteStatus::ACCEPTE->value)->whereDoesntHave('booking')->get(),
            fn (Quote $q) => $conversion->convert($q),
            $simulation,
        );

        $total += $this->traiter(
            'devis team building',
            TeamBuildingQuote::query()
                ->where('status', TeamBuildingQuoteStatus::ACCEPTE->value)
                ->whereDoesntHave('booking')
                ->get(),
            fn (TeamBuildingQuote $q) => $conversion->convertTeamBuilding($q),
            $simulation,
        );

        $total += $this->traiter(
            'devis de chantier',
            ConstructionQuote::query()
                ->where('status', ConstructionQuoteStatus::ACCEPTE->value)
                ->whereDoesntHave('booking')
                ->get(),
            fn (ConstructionQuote $q) => $conversion->convertConstruction($q),
            $simulation,
        );

        $this->newLine();
        $this->info($total === 0
            ? 'Rien à rattraper : tous les devis acceptés portent leur réservation.'
            : ($simulation ? "{$total} réservation(s) à créer." : "{$total} réservation(s) créée(s)."));

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $devis
     * @param  callable(object): \App\Models\Booking  $convertir
     */
    private function traiter(string $libelle, $devis, callable $convertir, bool $simulation): int
    {
        if ($devis->isEmpty()) {
            $this->line("· {$libelle} : rien à rattraper.");

            return 0;
        }

        $this->line("· {$libelle} : {$devis->count()} à rattraper.");

        foreach ($devis as $q) {
            if ($simulation) {
                $this->line("    {$q->reference} → réservation à créer");

                continue;
            }

            $booking = $convertir($q);
            $this->line("    {$q->reference} → {$booking->reference} ("
                .number_format($booking->amount_xof, 0, ',', ' ').' F)');
        }

        return $devis->count();
    }
}
