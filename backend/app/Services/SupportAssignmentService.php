<?php

namespace App\Services;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Admin\Models\Attendance;

/**
 * À QUI revient un nouveau fil de support (F8.12).
 *
 * L'architecture retenue (« option B — support pivot ») veut que le client
 * écrive TOUJOURS à Kaikun, jamais directement au propriétaire ou au
 * prestataire. Encore faut-il que « Kaikun » soit quelqu'un : sans responsable
 * nommé, un fil tombe dans une boîte partagée entre plusieurs comptes staff où
 * chacun suppose que l'autre a répondu — et le client n'a personne en face.
 *
 * Trois règles, dans cet ordre :
 *
 *   1. **Le vivier** = les comptes qui portent `repondre:messages`. Depuis
 *      F8.12.b, cette permission est **portée par le rôle** `agent_kaikun` :
 *      répondre aux clients n'est pas un levier sensible qu'on délègue au
 *      compte-gouttes, c'est le métier de base d'un agent. Tout membre de
 *      l'équipe est donc de permanence d'office.
 *   2. **En poste d'abord** : parmi eux, ceux qui ont **pointé leur entrée** à
 *      la pointeuse (F7.1.c) et pas encore leur sortie. Un agent parti à 18 h ne
 *      doit pas recevoir le fil de 22 h — il ne le verrait qu'au matin, et le
 *      client aurait attendu la nuit pour rien.
 *   3. **Le moins chargé**, à égalité le plus ancien identifiant : on compte les
 *      fils OUVERTS déjà assignés, pas le total historique — sinon un agent
 *      présent depuis un an ne recevrait plus jamais rien. C'est ce qui donne le
 *      sens concret de « libre » : zéro conversation en cours passe avant une.
 *
 * ⚠️ **Deux filets, dans cet ordre** : si personne n'est en poste (nuit,
 * week-end), on retombe sur **tout le vivier** — le message d'un client ne doit
 * jamais dépendre d'un pointage oublié. Et si le vivier lui-même est vide, on
 * renvoie `null` : le fil est créé quand même, non assigné, et apparaît au
 * back-office dans « Non assignés ». Le super administrateur peut de toute façon
 * réassigner n'importe quel fil à la main (`PATCH /admin/conversations/{id}`).
 */
class SupportAssignmentService
{
    /**
     * Choisit l'agent de permanence pour un nouveau fil (ou `null`, cf. en-tête).
     */
    public function pick(): ?User
    {
        // On tente d'abord parmi les personnes en poste ; à défaut, tout le
        // vivier (le repli est délibéré, cf. en-tête).
        return $this->leastBusy(onDuty: true) ?? $this->leastBusy(onDuty: false);
    }

    /**
     * Le vivier : tous les comptes habilités à répondre.
     *
     * On interroge la permission (et non le rôle) : elle arrive au staff par son
     * rôle et pourrait être accordée à un compte isolé — `permission()` de
     * Spatie couvre les deux chemins.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function pool(): \Illuminate\Support\Collection
    {
        return User::query()
            ->permission(AdminPermission::REPONDRE_MESSAGES->value)
            ->get();
    }

    /**
     * L'agent du vivier qui suit le moins de conversations ouvertes, restreint
     * ou non aux personnes actuellement en poste.
     */
    private function leastBusy(bool $onDuty): ?User
    {
        return User::query()
            ->permission(AdminPermission::REPONDRE_MESSAGES->value)
            // « En poste » = une session de pointeuse ouverte (entrée pointée,
            // sortie non pointée). On passe par une sous-requête plutôt que par
            // une relation sur `User` : le modèle transverse n'a pas à connaître
            // un modèle du module Admin.
            ->when($onDuty, fn ($query) => $query->whereIn(
                'id',
                Attendance::query()->open()->select('user_id'),
            ))
            // Charge courante, comptée en SQL (le vivier reste petit, mais on ne
            // veut pas une requête par agent à chaque message reçu).
            ->withCount(['assignedConversations as open_threads_count' => fn ($query) => $query->whereNull('closed_at')])
            ->orderBy('open_threads_count')
            ->orderBy('id')
            ->first();
    }
}
