<?php

namespace App\Modules\Assistant\Tools\BackOffice;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Services\DashboardAggregator;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;

/**
 * « Où en est la plateforme aujourd'hui ? » (phase F10.3).
 *
 * La première question d'une prise de poste, et la seule qui n'appelle pas un
 * dossier précis. Elle a une réponse à l'écran depuis B13.1 — la Vue d'ensemble
 * — mais la lire suppose d'ouvrir le poste de commandement, de traverser la 2FA
 * et de parcourir cinq blocs de chiffres. Posée à la bulle, elle tient en une
 * phrase.
 *
 * ⚠️ **L'agrégateur est réutilisé, pas réécrit.** `DashboardAggregator` est déjà
 * le point unique qui définit ce que « en attente », « aujourd'hui » ou « revenu
 * estimé » veulent dire (une réservation annulée sort du volume, la journée est
 * calendaire côté serveur…). Recalculer ces chiffres ici produirait, tôt ou
 * tard, un assistant qui contredit l'écran d'à côté — et c'est l'écran qu'on
 * croirait, à raison.
 *
 * ⚠️ **Ces chiffres ne sont pas anodins** : le volume transactionnel et la
 * commission sont la santé financière de l'entreprise. Ils ne sortent donc que
 * derrière `consulter:dashboard-admin`, la permission qui garde déjà la route
 * `GET /admin/dashboard` — et l'on ne remonte que les agrégats, jamais le détail
 * qui les compose.
 */
class PlatformActivityTool extends BackOfficeTool
{
    /**
     * Libellés courts des files, indexés par la clé de `DashboardAggregator`.
     */
    private const LIBELLES = [
        'properties_pending' => 'bien(s)',
        'vehicles_pending' => 'véhicule(s)',
        'mobility_services_pending' => 'départ(s)',
        'experiences_pending' => 'circuit(s)',
        'providers_pending' => 'prestataire(s)',
    ];

    public function __construct(
        private readonly DashboardAggregator $aggregator,
    ) {}

    public function name(): string
    {
        return 'activite_plateforme';
    }

    public function description(): string
    {
        return 'Donne la photographie du jour pour l\'équipe Kaikun : ce qui attend une validation, '
            .'les demandes et réservations reçues aujourd\'hui, le volume transactionnel et la '
            .'commission estimée, les avis à modérer et les incidents ouverts. À utiliser quand un '
            .'membre de l\'équipe demande où en est la plateforme, l\'activité du jour, les chiffres '
            .'ou le tableau de bord. Aucun paramètre.';
    }

    protected function permission(): AdminPermission
    {
        return AdminPermission::CONSULTER_DASHBOARD;
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $snapshot = $this->aggregator->snapshot();

        $queues = $snapshot['queues'];
        $today = $snapshot['today'];
        $revenue = $snapshot['revenue'];
        $alerts = $snapshot['alerts'];

        $enAttente = array_sum($queues);

        // Les fiches suivent l'ordre d'une prise de poste : ce qui bloque
        // quelqu'un d'autre d'abord (la file), l'activité du jour ensuite, la
        // santé financière enfin. Un tableau de bord qui commence par le chiffre
        // d'affaires fait oublier les dossiers qui attendent.
        $items = [
            $this->counter(
                'En attente de validation',
                $enAttente,
                $this->boUrl('validation'),
                $this->detailFile($queues),
            ),
            $this->counter(
                "Reçu aujourd'hui",
                (int) $today['requests'] + (int) $today['bookings'],
                $this->boUrl('demandes'),
                $today['requests'].' demande(s), '.$today['bookings'].' réservation(s)',
            ),
            $this->counter(
                'Avis à modérer',
                (int) $alerts['reviews_to_moderate'],
                $this->boUrl('qualite'),
                $alerts['open_incidents'].' incident(s) ouvert(s)',
            ),
        ];

        return new ToolResult(
            summary: sprintf(
                '%s en attente de validation, %s reçu(s) aujourd\'hui. '
                .'Volume encaissable : %s, dont %s de commission.',
                $enAttente === 0 ? 'Rien' : $enAttente.' élément(s)',
                (int) $today['requests'] + (int) $today['bookings'] === 0
                    ? 'aucun dossier'
                    : ((int) $today['requests'] + (int) $today['bookings']).' dossier(s)',
                $this->money((int) $revenue['gross_volume_xof']),
                $this->money((int) $revenue['commission_xof']),
            ),
            items: $items,
            actions: [AssistantAction::link('Ouvrir la vue d\'ensemble', $this->boUrl())],
        );
    }

    /**
     * Ventilation de la file, en une ligne — et seulement pour les types qui
     * ont réellement quelque chose en attente : énumérer quatre zéros n'apprend
     * rien et noie la seule ligne qui compte.
     *
     * @param  array<string, int>  $queues
     */
    private function detailFile(array $queues): ?string
    {
        $parts = [];

        // ⚠️ On parcourt les DONNÉES, pas une liste de clés écrite ici. La
        // version précédente énumérait quatre libellés : le total (`array_sum`)
        // annonçait 15 pendant que la ventilation en détaillait 10, les départs
        // programmés manquant à la liste. C'est la troisième occurrence du même
        // défaut dans cette tranche (agrégateur, écran, puis ici) — le motif est
        // toujours le même : une énumération figée à côté d'un ensemble qui
        // grandit. Un type inconnu s'affiche sous sa clé brute, donc visible.
        foreach ($queues as $cle => $nombre) {
            if ((int) $nombre > 0) {
                $parts[] = $nombre.' '.(self::LIBELLES[$cle] ?? $cle);
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
