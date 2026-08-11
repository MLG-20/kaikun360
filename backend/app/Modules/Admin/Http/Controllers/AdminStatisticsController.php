<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\BusinessMetricsAggregator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Statistiques business du back-office (F13.1).
 *
 * Sert la matière des graphiques de la rubrique « Statistiques » : séries de
 * revenus, ventilation par univers métier, tunnel commercial, palmarès des
 * annonces. Un seul appel, une seule période.
 *
 * **Pourquoi `gerer:paiements` et non `consulter:dashboard-admin`** — la
 * permission de base du back-office ouvrirait cet écran à tout agent. Or il
 * consolide le chiffre d'affaires, la commission et le panier moyen de la
 * plateforme : c'est la vue la plus financière du produit. Le CDC §7 borne
 * explicitement l'agent Kaikun à un « accès financier limité », et le
 * back-office range déjà tout ce qui touche à l'argent (Paiements,
 * Reversements) derrière cette même permission. Un droit distinct aurait
 * fabriqué une troisième porte sur le même coffre.
 */
class AdminStatisticsController extends Controller
{
    /**
     * Statistiques de la période demandée. GET /api/v1/admin/statistiques
     *
     * La période arrive en paramètre de requête (`?periode=12m`). Une valeur
     * inconnue n'est pas une erreur : l'agrégateur retombe sur sa période par
     * défaut. Un écran de pilotage qui renvoie 422 sur un lien mis en favori,
     * puis n'affiche rien, sert moins bien qu'un écran qui montre les douze
     * derniers mois.
     */
    public function show(Request $request, BusinessMetricsAggregator $aggregator): JsonResponse
    {
        // `query()` peut rendre un tableau (`?periode[]=…`) : on ne transmet
        // qu'une chaîne, sinon le `isset()` de l'agrégateur recevrait un type
        // qu'il n'attend pas.
        $periode = $request->query('periode');

        return ApiResponse::success(
            $aggregator->metrics(is_string($periode) ? $periode : null),
        );
    }
}
