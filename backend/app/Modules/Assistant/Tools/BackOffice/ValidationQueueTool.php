<?php

namespace App\Modules\Assistant\Tools\BackOffice;

use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Validation\ResourceValidator;
use App\Modules\Admin\Validation\ValidatorRegistry;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;
use Illuminate\Support\Carbon;

/**
 * « Qu'est-ce qui attend une validation ? » (phase F10.3).
 *
 * Le premier écran du matin d'un agent, et le seul dont le retard se voit
 * dehors : un bien en attente est un propriétaire qui croit avoir mal déposé
 * son annonce (c'est exactement la question à laquelle répond `mes_biens`, de
 * l'autre côté du guichet). Cet outil met les deux bouts de la même chaîne à
 * portée de phrase.
 *
 * ── Pourquoi la garde est l'ACCÈS et non les permissions de validation ──────
 * ⚠️ Point qui semble incohérent et ne l'est pas : l'outil est ouvert à
 * `consulter:dashboard-admin`, alors que valider un bien exige `valider:bien`.
 * C'est **la règle du contrôleur, recopiée** — `ValidationQueueController` le
 * dit dans son propre commentaire : « consulter un dossier n'est pas le
 * modérer ». La file est une information de coordination d'équipe ; le geste,
 * lui, reste derrière sa permission fine, sur l'écran, et l'assistant ne le
 * propose jamais (règle 2 du socle).
 *
 * Filtrer ici par `valider:*` produirait par ailleurs un compteur MENTEUR : un
 * agent sans délégation lirait « rien en attente » alors que la file déborde.
 * Un chiffre partiel présenté comme un total est pire qu'un refus d'accès.
 */
class ValidationQueueTool extends BackOfficeTool
{
    /**
     * Libellés français des types validables.
     *
     * ⚠️ Indexés par la clé de `ResourceValidator::type()`, pas par une liste
     * écrite à côté : le registre gagne des types au fil des phases (les départs
     * mobilité en F8.23). Un type inconnu de cette table reste compté et affiché
     * sous sa clé brute — visible, donc corrigé, plutôt que silencieusement omis
     * du total.
     */
    private const LIBELLES = [
        'property' => 'Biens immobiliers',
        'vehicle' => 'Véhicules',
        'mobility_service' => 'Départs programmés',
        'experience' => 'Circuits & expériences',
        'provider' => 'Prestataires',
    ];

    public function __construct(
        private readonly ValidatorRegistry $registry,
    ) {}

    public function name(): string
    {
        return 'file_validation';
    }

    public function description(): string
    {
        return 'Compte ce qui attend une validation de l\'équipe, type par type : biens immobiliers, '
            .'véhicules, départs programmés, circuits et prestataires, avec l\'ancienneté du dossier '
            .'le plus vieux. À utiliser quand un membre de l\'équipe demande la file de validation, '
            .'ce qu\'il reste à valider ou à modérer. Aucun paramètre. '
            .'⚠️ Cet outil ne valide RIEN : il conduit à l\'écran de validation.';
    }

    protected function permission(): AdminPermission
    {
        return AdminPermission::CONSULTER_DASHBOARD;
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $url = $this->boUrl('validation');
        $items = [];
        $total = 0;

        foreach ($this->registry->all() as $type => $validator) {
            $count = $validator->pendingCount();
            $total += $count;

            if ($count === 0) {
                continue;
            }

            $items[] = $this->counter(
                self::LIBELLES[$type] ?? $type,
                $count,
                $url,
                $this->anciennete($validator),
            );
        }

        if ($total === 0) {
            return $this->nothing(
                'La file de validation est vide — rien n\'attend l\'équipe.',
                'Ouvrir la file de validation',
                $url,
            );
        }

        // Les files les plus chargées d'abord, puis troncature : c'est là que le
        // travail est. Le total, lui, reste ANNONCÉ EN ENTIER dans la phrase —
        // tronquer les lignes sans dire combien il en reste ferait sous-estimer
        // la charge au lieu de la résumer.
        usort($items, fn (array $a, array $b) => (int) $b['statut'] <=> (int) $a['statut']);
        $affiches = array_slice($items, 0, $this->limit());

        return new ToolResult(
            summary: count($items) > count($affiches)
                ? $total.' élément(s) attendent une validation, répartis sur '.count($items)
                    .' types. Les '.count($affiches).' plus chargés :'
                : $total.' élément(s) attendent une validation :',
            items: $affiches,
            actions: [AssistantAction::link('Ouvrir la file de validation', $url)],
        );
    }

    /**
     * Depuis quand le plus ancien dossier de cette file attend-il ?
     *
     * C'est l'information qui fait agir : « 12 biens » se regarde, « le plus
     * ancien attend depuis 9 jours » se traite.
     *
     * ⚠️ **On agrège, on ne prend pas la première ligne** — et c'est un piège
     * qui coûte cher. Quatre validateurs sur cinq trient leur file par `oldest()`,
     * ce qui rendrait `->first()` juste ; mais `MobilityServiceValidator` trie
     * par **date de départ** (un départ a une péremption, celui de demain passe
     * avant celui déposé en septembre). Lire sa première ligne donnerait
     * l'ancienneté du départ le plus PROCHE, présentée comme celle du dossier le
     * plus vieux — un chiffre faux et parfaitement crédible. `reorder()` retire
     * le tri du validateur, que MySQL refuserait à côté d'un agrégat.
     */
    private function anciennete(ResourceValidator $validator): ?string
    {
        $plusAncien = $validator->pendingQuery()->reorder()->min('created_at');

        if ($plusAncien === null) {
            return null;
        }

        $jours = (int) Carbon::parse($plusAncien)->startOfDay()->diffInDays(now()->startOfDay());

        return match (true) {
            $jours <= 0 => 'le plus ancien est arrivé aujourd\'hui',
            $jours === 1 => 'le plus ancien attend depuis hier',
            default => 'le plus ancien attend depuis '.$jours.' jours',
        };
    }
}
