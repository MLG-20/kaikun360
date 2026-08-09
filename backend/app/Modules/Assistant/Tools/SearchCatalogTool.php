<?php

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Contracts\AssistantTool;
use App\Modules\Assistant\Contracts\ProvidesInputSchema;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche dans le catalogue RÉEL de la plateforme (phase F10.0).
 *
 * L'assistant du prototype récitait des phrases écrites en dur. Celui-ci
 * interroge les mêmes tables que les pages publiques, et n'affiche donc que des
 * annonces qui existent, à des prix qui existent.
 *
 * ── Sécurité ────────────────────────────────────────────────────────────────
 * Chaque requête passe par le scope de publication du module concerné
 * (`Property::published()`, `Stay::bookable()`, `Vehicle::published()`,
 * `TourismExperience::published()`). C'est LA garantie que le catalogue public
 * offre déjà : une annonce en attente, suspendue ou rejetée n'est pas visible,
 * et elle ne le devient pas parce qu'on passe par l'assistant.
 *
 * ── Produit ─────────────────────────────────────────────────────────────────
 * L'outil renvoie trois résultats maximum, puis un lien profond vers la vraie
 * page catalogue avec les filtres déjà posés. L'assistant ORIENTE ; c'est le
 * catalogue, avec ses photos, ses filtres et son tunnel de réservation, qui
 * vend. Empiler vingt fiches dans une bulle de discussion n'aiderait personne.
 */
class SearchCatalogTool implements AssistantTool, ProvidesInputSchema
{
    /**
     * Univers reconnus → page publique correspondante (voir app.routes.ts).
     */
    private const UNIVERSES = [
        'immobilier' => '/immobilier',
        'nuitees' => '/nuitees',
        'tourisme' => '/tourisme',
        'transport' => '/transport',
    ];

    public function name(): string
    {
        return 'rechercher_catalogue';
    }

    public function description(): string
    {
        return 'Recherche des annonces publiées dans le catalogue Kaikun 360 : biens immobiliers '
            .'(achat, vente, location), nuitées, circuits touristiques et véhicules de transport. '
            ."À utiliser dès que la personne décrit un besoin d'hébergement, d'achat, de séjour ou "
            .'de déplacement, même vaguement. Paramètres : `univers` '
            .'(immobilier|nuitees|tourisme|transport), `ville` et `budget_max` (facultatifs).';
    }

    /**
     * Paramètres offerts au modèle (F10.4).
     *
     * `univers` est le seul champ requis, et il est ÉNUMÉRÉ : c'est la clé du
     * `match` de `run()`, et un univers inventé ne renverrait rien. L'énumérer
     * ici évite au modèle d'improviser « villas » ou « hotels ».
     *
     * `budget_max` est déclaré en entier et en FRANCS CFA. Sans cette précision
     * dans la description, un modèle propose volontiers un budget en euros —
     * l'écart de 650 vide le catalogue en silence, exactement le défaut n°2
     * trouvé en revue de F10.0 avec le cerveau déterministe.
     */
    public function inputSchema(): array
    {
        return [
            'properties' => [
                'univers' => [
                    'type' => 'string',
                    'enum' => array_keys(self::UNIVERSES),
                    'description' => 'Univers du catalogue où chercher.',
                ],
                'ville' => [
                    'type' => 'string',
                    'description' => 'Ville, commune, région ou zone touristique du Sénégal '
                        .'(ex. Dakar, Saly, Casamance). À omettre si la personne ne précise pas de lieu.',
                ],
                'budget_max' => [
                    'type' => 'integer',
                    'description' => 'Budget maximum EN FRANCS CFA (XOF), jamais en euros. '
                        .'À omettre si la personne ne donne pas de montant.',
                ],
            ],
            'required' => ['univers'],
        ];
    }

    /**
     * Le catalogue est public : tout le monde y a droit, y compris un visiteur
     * non authentifié. C'est déjà le cas des endpoints `GET /properties` etc.
     */
    public function isAvailableFor(AssistantContext $context): bool
    {
        return true;
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $universe = $input['univers'] ?? null;

        if (! is_string($universe) || ! array_key_exists($universe, self::UNIVERSES)) {
            return ToolResult::empty(
                "Je peux chercher dans l'immobilier, les nuitées, le tourisme ou le transport. "
                .'Lequel vous intéresse ?',
            );
        }

        $city = $this->cleanString($input['ville'] ?? null);
        $budgetMax = $this->cleanBudget($input['budget_max'] ?? null);
        $limit = (int) config('assistant.limits.results_per_tool', 3);

        $items = match ($universe) {
            'immobilier' => $this->searchProperties($city, $budgetMax, $limit),
            'nuitees' => $this->searchStays($city, $budgetMax, $limit),
            'tourisme' => $this->searchExperiences($city, $budgetMax, $limit),
            'transport' => $this->searchVehicles($budgetMax, $limit),
        };

        $catalogUrl = $this->catalogUrl($universe, $budgetMax);

        if ($items === []) {
            return ToolResult::empty(
                "Je n'ai trouvé aucune annonce publiée correspondant à cette recherche pour le moment.",
                [
                    AssistantAction::link('Voir tout le catalogue', self::UNIVERSES[$universe]),
                ],
            );
        }

        return new ToolResult(
            summary: $this->summarize(count($items), $universe, $city),
            items: $items,
            actions: [AssistantAction::link('Voir toutes les annonces', $catalogUrl)],
        );
    }

    /**
     * Biens immobiliers publiés.
     */
    private function searchProperties(?string $city, ?int $budgetMax, int $limit): array
    {
        $query = Property::query()
            ->published() // garantie du catalogue public : jamais d'annonce non validée
            ->with(['commune', 'region']);

        // La ville peut être une commune, une région ou une zone touristique
        // (« Saly » est une zone, pas une commune) : on ratisse les trois.
        $query->when($city, function (Builder $q, string $value) {
            $like = '%'.$value.'%';
            $q->where(function (Builder $sub) use ($like) {
                $sub->whereHas('commune', fn (Builder $c) => $c->where('name', 'like', $like))
                    ->orWhereHas('region', fn (Builder $r) => $r->where('name', 'like', $like))
                    ->orWhere('tourist_zone', 'like', $like)
                    ->orWhere('title', 'like', $like);
            });
        });

        $query->when($budgetMax, fn (Builder $q, int $value) => $q->where('price_xof', '<=', $value));

        return $query->latest()->limit($limit)->get()
            ->map(fn (Property $property) => [
                'id' => $property->id,
                'titre' => $property->title,
                'lieu' => $property->commune?->name ?? $property->tourist_zone ?? $property->region?->name,
                'prix_xof' => $property->price_xof,
                'unite' => 'à la vente ou à louer',
                'url' => '/immobilier/'.$property->id,
            ])
            ->all();
    }

    /**
     * Nuitées réservables (bien publié + nuitée active).
     */
    private function searchStays(?string $city, ?int $budgetMax, int $limit): array
    {
        $query = Stay::query()
            ->bookable() // actif ET bien publié
            ->with(['property.commune']);

        $query->when($city, function (Builder $q, string $value) {
            $like = '%'.$value.'%';
            $q->whereHas('property', function (Builder $p) use ($like) {
                $p->where('tourist_zone', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhereHas('commune', fn (Builder $c) => $c->where('name', 'like', $like));
            });
        });

        $query->when($budgetMax, fn (Builder $q, int $value) => $q->where('price_per_night_xof', '<=', $value));

        return $query->latest()->limit($limit)->get()
            ->map(fn (Stay $stay) => [
                'id' => $stay->id,
                'titre' => $stay->property?->title,
                'lieu' => $stay->property?->commune?->name ?? $stay->property?->tourist_zone,
                'prix_xof' => $stay->price_per_night_xof,
                'unite' => 'par nuit',
                'url' => '/nuitees/'.$stay->id,
            ])
            ->all();
    }

    /**
     * Circuits et expériences touristiques publiés.
     */
    private function searchExperiences(?string $city, ?int $budgetMax, int $limit): array
    {
        $query = TourismExperience::query()->published();

        $query->when($city, function (Builder $q, string $value) {
            $like = '%'.$value.'%';
            $q->where(fn (Builder $sub) => $sub->where('destination', 'like', $like)
                ->orWhere('title', 'like', $like));
        });

        $query->when($budgetMax, fn (Builder $q, int $value) => $q->where('price_xof', '<=', $value));

        return $query->latest()->limit($limit)->get()
            ->map(fn (TourismExperience $experience) => [
                'id' => $experience->id,
                'titre' => $experience->title,
                'lieu' => $experience->destination,
                'prix_xof' => $experience->price_xof,
                'unite' => 'par personne',
                'url' => '/tourisme/'.$experience->id,
            ])
            ->all();
    }

    /**
     * Véhicules publiés.
     *
     * Pas de filtre « ville » : un véhicule n'est pas rattaché à une commune —
     * c'est le trajet qui l'est. Filtrer dessus produirait zéro résultat et
     * laisserait croire au catalogue vide.
     */
    private function searchVehicles(?int $budgetMax, int $limit): array
    {
        $query = Vehicle::query()->published();

        $query->when($budgetMax, fn (Builder $q, int $value) => $q->where('price_per_day_xof', '<=', $value));

        return $query->latest()->limit($limit)->get()
            ->map(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'titre' => trim(($vehicle->brand ?? '').' '.($vehicle->model ?? '')) ?: 'Véhicule',
                'lieu' => null,
                'prix_xof' => $vehicle->price_per_day_xof,
                'unite' => 'par jour',
                'url' => '/transport/'.$vehicle->id,
            ])
            ->all();
    }

    /**
     * Lien vers la page catalogue de l'univers.
     *
     * ⚠️ On NE reporte PAS la ville dans l'URL. Les quatre catalogues traitent
     * `q` comme une recherche sur le TITRE de l'annonce (la marque pour les
     * véhicules), pas sur la localisation : passer « Saly » en `q` renverrait
     * une page vide juste après que l'assistant a montré trois résultats
     * pertinents — le pire des enchaînements pour la confiance.
     *
     * Le budget, lui, correspond bien au filtre `price_max` du catalogue.
     *
     * Chemin interne uniquement, construit ici : jamais une URL venue du
     * message de l'utilisateur (un lien externe sortant d'un assistant est un
     * vecteur d'hameçonnage tout trouvé).
     */
    private function catalogUrl(string $universe, ?int $budgetMax): string
    {
        $base = self::UNIVERSES[$universe];

        // `price_max` n'existe que sur le catalogue immobilier ; ailleurs, un
        // paramètre inconnu ferait échouer la validation de l'index.
        if ($universe === 'immobilier' && $budgetMax !== null) {
            return $base.'?'.http_build_query(['price_max' => $budgetMax]);
        }

        return $base;
    }

    /**
     * Phrase de synthèse affichée au-dessus des fiches.
     */
    private function summarize(int $count, string $universe, ?string $city): string
    {
        $label = match ($universe) {
            'immobilier' => $count > 1 ? 'biens' : 'bien',
            'nuitees' => $count > 1 ? 'hébergements' : 'hébergement',
            'tourisme' => $count > 1 ? 'circuits' : 'circuit',
            'transport' => $count > 1 ? 'véhicules' : 'véhicule',
        };

        // ⚠️ Le verbe s'accorde, sinon on lit « Voici 1 circuit qui
        // correspondent » — une faute qui, sur un site marchand, coûte plus de
        // confiance qu'elle n'a coûté de caractères à corriger.
        $correspondent = $count > 1 ? 'correspondent' : 'correspond';
        $pourraient = $count > 1 ? 'pourraient' : 'pourrait';

        return $city !== null
            ? "Voici {$count} {$label} qui {$correspondent} à votre recherche du côté de {$city}."
            : "Voici {$count} {$label} qui {$pourraient} vous convenir.";
    }

    /**
     * Normalise une chaîne venue du message (ou du modèle en F10.4).
     */
    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 60);
    }

    /**
     * Normalise un budget. Une valeur absurde (négative, démesurée) est
     * ignorée plutôt que refusée : mieux vaut une recherche large qu'une
     * erreur technique affichée à un visiteur.
     */
    private function cleanBudget(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $budget = (int) $value;

        return ($budget > 0 && $budget < 100_000_000_000) ? $budget : null;
    }
}
