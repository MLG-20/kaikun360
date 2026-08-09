<?php

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;

/**
 * « Mon annonce est-elle en ligne ? » (phase F10.2).
 *
 * La question du propriétaire, et elle a une particularité : **cet outil montre
 * des biens NON publiés**, ce que le catalogue ne fera jamais.
 *
 * ⚠️ Ce n'est pas une entorse à la règle centrale du module, c'est son
 * application exacte. `SearchCatalogTool` passe par `published()` parce qu'il
 * répond à **tout le monde** ; celui-ci passe par `where('owner_id', …)` parce
 * qu'il répond au **seul propriétaire**, exactement comme
 * `PropertyManagementController::mine`. Dans les deux cas, l'assistant montre ce
 * que l'appelant verrait en ouvrant son espace — ni plus, ni moins.
 *
 * L'intérêt produit est là : un bien en attente de validation est invisible
 * partout ailleurs, et son propriétaire croit régulièrement l'avoir mal déposé.
 */
class MyPropertiesTool extends PersonalRecordsTool
{
    public function name(): string
    {
        return 'mes_biens';
    }

    public function description(): string
    {
        return 'Consulte les biens déposés par le propriétaire connecté, quel que soit leur statut '
            .'(en attente de validation, publié, suspendu, rejeté). À utiliser quand il demande où '
            .'en est SON annonce, si elle est en ligne, ou pourquoi elle n\'apparaît pas. '
            .'Aucun paramètre.';
    }

    /**
     * @return array<int, UserRole>
     */
    protected function roles(): array
    {
        return [UserRole::PROPRIETAIRE];
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $url = $this->spaceUrl($context, 'biens');

        $properties = Property::query()
            ->where('owner_id', $context->user->id)
            ->with(['commune'])
            ->latest()
            ->limit($this->limit())
            ->get();

        if ($properties->isEmpty()) {
            return $this->nothing(
                "Vous n'avez encore déposé aucun bien.",
                'Déposer un bien',
                $url,
            );
        }

        return new ToolResult(
            summary: $properties->count() === 1
                ? 'Voici votre bien :'
                : 'Voici vos '.$properties->count().' biens les plus récents :',
            items: $properties->map(fn (Property $bien) => [
                'titre' => $bien->title,
                'statut' => $bien->status?->label(),
                'lieu' => $bien->commune?->name ?? $bien->tourist_zone,
                // ⚠️ `montant` (déjà mis en forme) et non `prix_xof` : le panneau
                // privilégie le premier, envoyer les deux laisserait traîner un
                // champ jamais lu — et la sortie des outils doit rester fermée.
                'montant' => $this->money($bien->price_xof),
                'url' => $url.'/'.$bien->id,
            ])->all(),
            actions: [AssistantAction::link('Voir tous mes biens', $url)],
        );
    }
}
