<?php

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Pro\Models\ProviderMission;

/**
 * « Qu'est-ce qu'on m'a affecté ? » (phase F10.2).
 *
 * Le prestataire est le profil le plus mobile de la plateforme : il consulte
 * depuis un téléphone, entre deux chantiers. Une mission **affectée** attend sa
 * réponse et bloque un client pendant ce temps — la lui rappeler d'une phrase a
 * plus de valeur ici que partout ailleurs.
 *
 * ⚠️ **Le scope passe par une INDIRECTION** : `provider_missions.provider_id`
 * pointe sur la table `providers`, pas sur `users` — c'est la seule relation du
 * projet dans ce cas (les véhicules, départs et circuits, eux, portent
 * directement un `user_id`). D'où le `whereHas('provider', …)`, recopié de
 * `ProviderMissionController::mine`. Écrire `where('provider_id', $user->id)`
 * paraîtrait juste, compilerait, et montrerait les missions de quelqu'un
 * d'autre : c'est précisément le genre de fuite silencieuse que la recopie du
 * contrôleur évite.
 *
 * ⚠️ **Lecture seule.** Accepter ou refuser une mission engage un professionnel
 * sur une date et un montant : ce geste reste sur l'écran des missions, avec sa
 * confirmation. L'assistant conduit à la porte, il ne signe pas.
 */
class MyMissionsTool extends PersonalRecordsTool
{
    public function name(): string
    {
        return 'mes_missions';
    }

    public function description(): string
    {
        return 'Consulte les missions du prestataire connecté : référence, intitulé, statut '
            .'(affectée, acceptée, en cours, terminée), date prévue et montant. À utiliser quand il '
            .'demande ce qu\'on lui a confié, ce qui l\'attend, ou où en est une mission. '
            .'Aucun paramètre.';
    }

    /**
     * @return array<int, UserRole>
     */
    protected function roles(): array
    {
        return [UserRole::PRESTATAIRE];
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $url = $this->spaceUrl($context, 'missions');

        $missions = ProviderMission::query()
            // ⚠️ Indirection obligatoire : provider_id → providers, pas users.
            ->whereHas('provider', fn ($query) => $query->where('user_id', $context->user->id))
            ->latest()
            ->limit($this->limit())
            ->get();

        if ($missions->isEmpty()) {
            return $this->nothing(
                "Aucune mission ne vous est affectée pour l'instant.",
                'Voir mes missions',
                $url,
            );
        }

        return new ToolResult(
            summary: $missions->count() === 1
                ? 'Voici votre mission :'
                : 'Voici vos '.$missions->count().' missions les plus récentes :',
            items: $missions->map(fn (ProviderMission $mission) => [
                'reference' => $mission->reference,
                'titre' => $mission->title,
                'statut' => $mission->status?->label(),
                'periode' => $mission->scheduled_at?->format('d/m/Y'),
                // Le montant BRUT, celui du contrat. Le net après commission est
                // sur l'écran « Revenus », qui l'explique — l'annoncer ici sans
                // son explication ferait croire à une retenue surprise.
                'montant' => $this->money($mission->amount_xof),
                'url' => $url,
            ])->all(),
            actions: [AssistantAction::link('Voir toutes mes missions', $url)],
        );
    }
}
