<?php

namespace App\Modules\Admin\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agrégateur du BUSINESS de la plateforme (F13.1) — la matière des graphiques
 * de la rubrique « Statistiques » du back-office.
 *
 * ⚠️ **Ne pas confondre avec `DashboardAggregator`.** Celui-là répond à « que
 * dois-je traiter maintenant ? » : des compteurs instantanés (files d'attente,
 * alertes du jour). Celui-ci répond à « comment va l'entreprise ? » : des
 * SÉRIES, c'est-à-dire des valeurs situées dans le temps et comparées à la
 * période précédente. Un compteur ne se dessine pas ; une série, oui — c'est
 * exactement ce qui manquait pour tracer la moindre courbe.
 *
 * Ce qu'il produit :
 *   - `headline` : les grands chiffres de la période, chacun avec la valeur de
 *     la période PRÉCÉDENTE de même longueur, donc une variation lisible ;
 *   - `revenue_series` : volume brut et commission, point par point ;
 *   - `bookings_by_line` : les réservations ventilées par univers métier ;
 *   - `funnel` : demande reçue → devis émis → réservation → réservation menée
 *     à terme, les quatre étages du tunnel commercial ;
 *   - `top_listings` : le palmarès des annonces qui rapportent ;
 *   - `booking_statuses` : la répartition des réservations par statut.
 *
 * **Règle de calcul, unique et constante** : un montant ne compte JAMAIS une
 * réservation annulée (quelle qu'en soit l'origine), mais un *dénombrement* de
 * réservations les compte toutes — sans quoi le taux d'annulation, qui est un
 * indicateur de santé à part entière, serait invisible par construction.
 */
class BusinessMetricsAggregator
{
    /**
     * Périodes proposées au filtre du back-office. La clé est ce que l'API
     * accepte ; le reste décrit la fenêtre et la finesse du découpage.
     *
     * Le pas (`granularity`) n'est pas un réglage libre : il est CHOISI pour
     * que le graphique reste lisible. Douze mois découpés en jours donneraient
     * 365 colonnes de trois pixels — un mur, pas une information.
     *
     * @var array<string, array{label: string, length: int, unit: string, granularity: string}>
     */
    private const PERIODS = [
        '7j' => ['label' => '7 derniers jours', 'length' => 7, 'unit' => 'day', 'granularity' => 'day'],
        '15j' => ['label' => '15 derniers jours', 'length' => 15, 'unit' => 'day', 'granularity' => 'day'],
        '30j' => ['label' => '30 derniers jours', 'length' => 30, 'unit' => 'day', 'granularity' => 'day'],
        '6m' => ['label' => '6 derniers mois', 'length' => 6, 'unit' => 'month', 'granularity' => 'month'],
        '12m' => ['label' => '12 derniers mois', 'length' => 12, 'unit' => 'month', 'granularity' => 'month'],
    ];

    /** Période retenue quand l'appelant n'en précise aucune (ou en propose une inconnue). */
    public const DEFAULT_PERIOD = '12m';

    /**
     * Les UNIVERS MÉTIER de Kaikun 360, tels qu'ils sont ventilés dans les
     * graphiques — et non tels qu'ils sont stockés.
     *
     * La colonne `bookings.bookable_type` connaît sept cibles polymorphes ; les
     * afficher telles quelles donnerait sept séries de couleurs, au-delà du
     * seuil où l'œil distingue encore deux teintes voisines. On regroupe donc
     * par LIGNE DE MÉTIER, ce qui est de toute façon la maille à laquelle un
     * dirigeant raisonne : le véhicule de location et le départ programmé sont
     * un seul métier — la mobilité — même s'ils sont deux tables.
     *
     * Clé = nom court de la classe polymorphe (cf. `BookingResource`).
     *
     * ⚠️ Toute nouvelle cible réservable doit être ajoutée ici. À défaut elle
     * tombe dans `sur_mesure`, ce qui la rend invisible en tant que métier :
     * les montants restent justes, la lecture business devient fausse.
     *
     * @var array<string, string>
     */
    private const LINE_OF_BUSINESS = [
        'Stay' => 'nuitees',
        'Vehicle' => 'mobilite',
        'MobilityService' => 'mobilite',
        'TourismExperience' => 'tourisme',
        'TeamBuildingQuote' => 'team_building',
        'Quote' => 'sur_mesure',
        'ConstructionQuote' => 'sur_mesure',
    ];

    /**
     * Libellés des univers, dans l'ORDRE d'affichage — qui est aussi l'ordre
     * d'attribution des couleurs côté frontend. Cet ordre est figé : une
     * couleur suit un métier, jamais son rang du moment. Si « Tourisme » passe
     * devant « Mobilité » en volume, les deux gardent leur teinte, faute de
     * quoi un lecteur qui a appris « le vert, c'est le tourisme » serait trompé
     * au premier filtre changé.
     *
     * @var array<string, string>
     */
    private const LINE_LABELS = [
        'nuitees' => 'Nuitées',
        'mobilite' => 'Mobilité',
        'tourisme' => 'Tourisme',
        'team_building' => 'Team building',
        'sur_mesure' => 'Sur-mesure',
    ];

    /**
     * Point d'entrée unique : toute la matière des graphiques pour une période.
     *
     * @return array<string, mixed>
     */
    public function metrics(?string $periodKey = null): array
    {
        $key = isset(self::PERIODS[$periodKey]) ? $periodKey : self::DEFAULT_PERIOD;
        $period = self::PERIODS[$key];

        [$from, $to] = $this->window($period);
        // Fenêtre PRÉCÉDENTE de longueur identique, pour la comparaison. Elle
        // s'arrête juste avant le début de la fenêtre courante : les deux ne se
        // chevauchent pas d'une seconde, sinon la variation compterait deux
        // fois les mêmes réservations.
        [$prevFrom, $prevTo] = [
            $this->shiftBack($from, $period),
            $from->subSecond(),
        ];

        $buckets = $this->buckets($from, $to, $period['granularity']);

        return [
            'period' => [
                'key' => $key,
                'label' => $period['label'],
                'granularity' => $period['granularity'],
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'periods' => $this->availablePeriods(),
            'headline' => $this->headline($from, $to, $prevFrom, $prevTo),
            'revenue_series' => $this->revenueSeries($from, $to, $buckets, $period['granularity']),
            'bookings_by_line' => $this->bookingsByLine($from, $to, $buckets, $period['granularity']),
            'funnel' => $this->funnel($from, $to),
            'top_listings' => $this->topListings($from, $to),
            'booking_statuses' => $this->bookingStatuses($from, $to),
        ];
    }

    /**
     * Catalogue des périodes, pour que le frontend construise son filtre sans
     * réécrire cette liste (une liste dupliquée est une liste qui divergera).
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function availablePeriods(): array
    {
        return array_map(
            fn (string $key) => ['key' => $key, 'label' => self::PERIODS[$key]['label']],
            array_keys(self::PERIODS),
        );
    }

    // =========================================================================
    // Fenêtre de temps & découpage
    // =========================================================================

    /**
     * Bornes de la fenêtre courante. Elle se termine à la fin du jour COURANT
     * (l'activité d'aujourd'hui compte : un dirigeant qui ouvre l'écran à midi
     * s'attend à y voir la vente du matin) et commence au début du premier
     * segment complet.
     *
     * @param  array{label: string, length: int, unit: string, granularity: string}  $period
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(array $period): array
    {
        $to = CarbonImmutable::now()->endOfDay();

        $from = $period['unit'] === 'month'
            // `length - 1` : les « 12 derniers mois » incluent le mois en cours,
            // donc onze mois révolus plus celui-ci — et non douze plus celui-ci.
            ? $to->startOfMonth()->subMonths($period['length'] - 1)->startOfDay()
            : $to->subDays($period['length'] - 1)->startOfDay();

        return [$from, $to];
    }

    /**
     * Début de la fenêtre précédente : la même longueur, reculée d'un cran.
     *
     * @param  array{label: string, length: int, unit: string, granularity: string}  $period
     */
    private function shiftBack(CarbonImmutable $from, array $period): CarbonImmutable
    {
        return $period['unit'] === 'month'
            ? $from->subMonths($period['length'])
            : $from->subDays($period['length']);
    }

    /**
     * Les segments de l'axe des abscisses, PRÉ-CALCULÉS et complets.
     *
     * C'est le point le plus important de cette classe. Une agrégation SQL ne
     * renvoie que les segments où il s'est passé quelque chose : un mois sans
     * la moindre réservation n'existe pas dans le résultat. Tracer une courbe
     * directement sur ce résultat relierait juillet à septembre en sautant août
     * — dessinant une pente régulière là où il y a eu un trou. On fabrique donc
     * l'axe d'abord, et on y VERSE les valeurs ensuite ; un mois vide vaut zéro
     * et se voit comme tel.
     *
     * @return array<int, array{key: string, label: string}>
     */
    private function buckets(CarbonImmutable $from, CarbonImmutable $to, string $granularity): array
    {
        $buckets = [];
        $cursor = $granularity === 'month' ? $from->startOfMonth() : $from->startOfDay();

        while ($cursor <= $to) {
            $buckets[] = [
                'key' => $cursor->format($granularity === 'month' ? 'Y-m' : 'Y-m-d'),
                // Libellés courts : l'axe doit tenir sans pivoter le texte.
                // « août 26 » sur douze mois (l'année change en cours de route,
                // la taire rendrait deux « janvier » indiscernables) ; « 12/08 »
                // au jour.
                'label' => $granularity === 'month'
                    ? $cursor->translatedFormat('M y')
                    : $cursor->format('d/m'),
            ];

            $cursor = $granularity === 'month' ? $cursor->addMonth() : $cursor->addDay();
        }

        return $buckets;
    }

    /**
     * Expression SQL qui range une date dans son segment.
     *
     * Écrite par driver, à dessein : `DATE_FORMAT` est du MySQL, `strftime` du
     * SQLite, `to_char` du PostgreSQL. Le projet tourne sur MySQL mais teste
     * sur SQLite — un agrégat qui ne s'exécuterait que sur l'un des deux serait
     * un agrégat non testé.
     *
     * Aucune donnée d'utilisateur n'entre ici : `$column` et `$granularity`
     * sont fournis par cette classe seule.
     */
    private function bucketExpression(string $column, string $granularity): string
    {
        $pattern = $granularity === 'month' ? ['%Y-%m', 'YYYY-MM'] : ['%Y-%m-%d', 'YYYY-MM-DD'];

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('{$pattern[0]}', {$column})",
            'pgsql' => "to_char({$column}, '{$pattern[1]}')",
            default => "DATE_FORMAT({$column}, '{$pattern[0]}')",
        };
    }

    // =========================================================================
    // Les grands chiffres (tuiles d'en-tête)
    // =========================================================================

    /**
     * Six indicateurs, chacun accompagné de sa valeur sur la période
     * précédente. Le back-office affiche la variation : un chiffre d'affaires
     * sans point de comparaison ne dit pas si l'entreprise monte ou descend.
     *
     * @return array<string, array{value: int|float, previous: int|float}>
     */
    private function headline(
        CarbonImmutable $from,
        CarbonImmutable $to,
        CarbonImmutable $prevFrom,
        CarbonImmutable $prevTo,
    ): array {
        $current = $this->headlineFor($from, $to);
        $previous = $this->headlineFor($prevFrom, $prevTo);

        return collect($current)
            ->map(fn ($value, string $name) => [
                'value' => $value,
                'previous' => $previous[$name],
            ])
            ->all();
    }

    /**
     * Les mêmes indicateurs sur une fenêtre quelconque (sert deux fois : pour
     * la période affichée et pour celle qui la précède).
     *
     * @return array<string, int|float>
     */
    private function headlineFor(CarbonImmutable $from, CarbonImmutable $to): array
    {
        // Une seule requête pour les trois agrégats monétaires : les sommes
        // n'excluent les annulations que via un CASE, ce qui évite de balayer
        // trois fois la même plage de dates.
        $bookings = Booking::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('count(*) as total_count')
            ->selectRaw('sum(case when status in (?, ?, ?) then 1 else 0 end) as cancelled_count', $this->cancelledStatuses())
            ->selectRaw('sum(case when status in (?, ?, ?) then 0 else amount_xof end) as gross', $this->cancelledStatuses())
            ->selectRaw('sum(case when status in (?, ?, ?) then 0 else commission_xof end) as commission', $this->cancelledStatuses())
            ->first();

        $total = (int) ($bookings->total_count ?? 0);
        $cancelled = (int) ($bookings->cancelled_count ?? 0);
        $gross = (int) ($bookings->gross ?? 0);
        $active = $total - $cancelled;

        return [
            'gross_volume_xof' => $gross,
            'commission_xof' => (int) ($bookings->commission ?? 0),
            'bookings' => $total,
            // Panier moyen : sur les réservations NON annulées uniquement —
            // diviser un volume amputé des annulations par un compte qui les
            // inclut donnerait un panier artificiellement bas.
            'average_basket_xof' => $active > 0 ? (int) round($gross / $active) : 0,
            // Taux d'annulation en points de pourcentage, une décimale.
            'cancellation_rate' => $total > 0 ? round($cancelled * 100 / $total, 1) : 0.0,
            'new_users' => User::whereBetween('created_at', [$from, $to])->count(),
        ];
    }

    // =========================================================================
    // Séries temporelles
    // =========================================================================

    /**
     * Volume brut et commission, segment par segment.
     *
     * Les deux séries partagent l'unité (le franc CFA) et donc UN SEUL axe.
     * C'est ce qui autorise à les superposer : deux grandeurs d'unités
     * différentes sur un même graphique exigeraient deux échelles, dont
     * l'alignement — arbitraire — inventerait une corrélation absente des
     * données. Ici la commission est une PART du volume : la voir courir sous
     * la courbe du volume est exactement la lecture recherchée.
     *
     * @param  array<int, array{key: string, label: string}>  $buckets
     * @return array<int, array{key: string, label: string, gross_volume_xof: int, commission_xof: int}>
     */
    private function revenueSeries(
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $buckets,
        string $granularity,
    ): array {
        $expression = $this->bucketExpression('created_at', $granularity);

        $rows = Booking::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', $this->cancelledStatuses())
            ->selectRaw("{$expression} as bucket")
            ->selectRaw('sum(amount_xof) as gross, sum(commission_xof) as commission')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        return array_map(fn (array $bucket) => [
            'key' => $bucket['key'],
            'label' => $bucket['label'],
            'gross_volume_xof' => (int) ($rows[$bucket['key']]->gross ?? 0),
            'commission_xof' => (int) ($rows[$bucket['key']]->commission ?? 0),
        ], $buckets);
    }

    /**
     * Réservations ventilées par univers métier, segment par segment — la
     * matière des colonnes empilées.
     *
     * Chaque point porte les CINQ univers, y compris ceux à zéro : une pile
     * dont les composantes changent d'un point à l'autre serait illisible, et
     * le frontend n'aurait aucun moyen de garder une couleur stable.
     *
     * @param  array<int, array{key: string, label: string}>  $buckets
     * @return array{lines: array<int, array{key: string, label: string}>, points: array<int, array{key: string, label: string, values: array<string, int>}>}
     */
    private function bookingsByLine(
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $buckets,
        string $granularity,
    ): array {
        $expression = $this->bucketExpression('created_at', $granularity);

        $rows = Booking::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', $this->cancelledStatuses())
            ->selectRaw("{$expression} as bucket, bookable_type, count(*) as total")
            ->groupBy('bucket', 'bookable_type')
            ->get();

        // Les univers vides d'abord, remplis ensuite : voir `buckets()`.
        $grid = [];
        foreach ($buckets as $bucket) {
            $grid[$bucket['key']] = array_fill_keys(array_keys(self::LINE_LABELS), 0);
        }

        foreach ($rows as $row) {
            $line = $this->lineOfBusiness((string) $row->bookable_type);

            if (isset($grid[$row->bucket])) {
                $grid[$row->bucket][$line] += (int) $row->total;
            }
        }

        return [
            'lines' => array_map(
                fn (string $key) => ['key' => $key, 'label' => self::LINE_LABELS[$key]],
                array_keys(self::LINE_LABELS),
            ),
            'points' => array_map(fn (array $bucket) => [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'values' => $grid[$bucket['key']],
            ], $buckets),
        ];
    }

    /**
     * Univers métier d'une cible polymorphe. Un type inconnu tombe dans
     * « Sur-mesure » plutôt que de disparaître : mieux vaut un chiffre rangé
     * dans une case imparfaite qu'un chiffre perdu.
     */
    private function lineOfBusiness(string $bookableType): string
    {
        return self::LINE_OF_BUSINESS[class_basename($bookableType)] ?? 'sur_mesure';
    }

    // =========================================================================
    // Tunnel commercial
    // =========================================================================

    /**
     * Les quatre étages par lesquels passe une affaire, du premier contact à
     * la prestation honorée. Chaque étage est un SOUS-ENSEMBLE théorique du
     * précédent — c'est ce qui donne au dessin sa forme d'entonnoir et rend
     * visible l'étage où l'on perd les gens.
     *
     * ⚠️ Ces quatre comptes portent sur des objets DIFFÉRENTS mesurés sur la
     * même fenêtre, pas sur le suivi individuel d'une même demande dans le
     * temps : une réservation de la période peut découler d'une demande du mois
     * précédent. C'est un entonnoir de VOLUMES, pas de cohorte — la nuance est
     * dite à l'écran, faute de quoi on lirait le taux de passage comme un
     * rendement exact.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function funnel(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            [
                'key' => 'requests',
                'label' => 'Demandes reçues',
                'count' => ServiceRequest::whereBetween('created_at', [$from, $to])->count(),
            ],
            [
                'key' => 'quotes',
                'label' => 'Devis émis',
                'count' => Quote::whereBetween('created_at', [$from, $to])->count(),
            ],
            [
                'key' => 'bookings',
                'label' => 'Réservations',
                'count' => Booking::whereBetween('created_at', [$from, $to])
                    ->whereNotIn('status', $this->cancelledStatuses())
                    ->count(),
            ],
            [
                'key' => 'completed',
                'label' => 'Prestations honorées',
                'count' => Booking::whereBetween('created_at', [$from, $to])
                    ->where('status', BookingStatus::TERMINEE->value)
                    ->count(),
            ],
        ];
    }

    // =========================================================================
    // Palmarès des annonces
    // =========================================================================

    /**
     * Les CINQ annonces qui ont rapporté le plus sur la période.
     *
     * Le classement se fait en base (regroupement sur le couple polymorphe,
     * tri, limite), et seuls les gagnants voient leur libellé résolu : on ne
     * charge jamais le catalogue entier pour n'en afficher que cinq lignes.
     *
     * ⚠️ **Cinq et non six depuis F13.2**, parce que l'écran en a fait un
     * diagramme circulaire. Un disque se lit d'un coup d'œil jusqu'à cinq ou six
     * parts, pas au-delà ; et surtout le frontend y ajoute une part « Autres
     * annonces » calculée par différence avec le volume total de la période —
     * un camembert dont les parts ne totalisent pas le tout est un mensonge de
     * forme. Cinq gagnants plus le reste font donc six parts, le maximum
     * lisible.
     *
     * @return array<int, array{label: string, line: string, bookings: int, gross_volume_xof: int}>
     */
    private function topListings(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Booking::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', $this->cancelledStatuses())
            ->selectRaw('bookable_type, bookable_id, count(*) as bookings, sum(amount_xof) as gross')
            ->groupBy('bookable_type', 'bookable_id')
            ->orderByDesc('gross')
            ->limit(5)
            ->get();

        $labels = $this->resolveListingLabels($rows);

        return $rows->map(fn ($row) => [
            'label' => $labels["{$row->bookable_type}#{$row->bookable_id}"]
                ?? 'Annonce n° '.$row->bookable_id,
            'line' => self::LINE_LABELS[$this->lineOfBusiness((string) $row->bookable_type)],
            'bookings' => (int) $row->bookings,
            'gross_volume_xof' => (int) $row->gross,
        ])->all();
    }

    /**
     * Libellé humain de chaque annonce du palmarès, résolu type par type et en
     * une requête par type (jamais une par ligne).
     *
     * Chaque univers nomme ses fiches à sa façon — un bien porte un titre, un
     * véhicule une marque et un modèle, un départ une origine et une
     * destination. Un identifiant technique dans un palmarès ne se lit pas :
     * c'est précisément le nom qu'on vient y chercher.
     *
     * @param  Collection<int, object>  $rows
     * @return array<string, string>
     */
    private function resolveListingLabels(Collection $rows): array
    {
        /** @var array<string, list<int>> $idsByType */
        $idsByType = [];
        foreach ($rows as $row) {
            $idsByType[(string) $row->bookable_type][] = (int) $row->bookable_id;
        }

        $labels = [];

        foreach ($idsByType as $type => $ids) {
            $resolved = match (class_basename($type)) {
                'Stay' => Stay::with('property:id,title')
                    ->whereIn('id', $ids)
                    ->get(['id', 'property_id'])
                    ->mapWithKeys(fn (Stay $stay) => [
                        $stay->id => 'Nuitée — '.($stay->property?->title ?? 'bien retiré'),
                    ]),
                'Vehicle' => Vehicle::whereIn('id', $ids)
                    ->get(['id', 'brand', 'model'])
                    ->mapWithKeys(fn (Vehicle $v) => [$v->id => trim($v->brand.' '.$v->model)]),
                'TourismExperience' => TourismExperience::whereIn('id', $ids)
                    ->get(['id', 'title'])
                    ->mapWithKeys(fn (TourismExperience $e) => [$e->id => $e->title]),
                'MobilityService' => MobilityService::whereIn('id', $ids)
                    ->get(['id', 'departure', 'destination'])
                    ->mapWithKeys(fn (MobilityService $m) => [
                        $m->id => $m->departure.' → '.$m->destination,
                    ]),
                // Devis (sur-mesure, chantier, team building) : la référence EST
                // le nom d'usage du dossier, celui que l'équipe échange.
                default => $this->quoteReferences($type, $ids),
            };

            foreach ($resolved as $id => $label) {
                $labels["{$type}#{$id}"] = $label;
            }
        }

        return $labels;
    }

    /**
     * Références des devis, lues sans importer les classes de devis des trois
     * modules concernés : le type polymorphe fournit lui-même le modèle.
     *
     * @param  class-string  $type
     * @param  list<int>  $ids
     * @return Collection<int, string>
     */
    private function quoteReferences(string $type, array $ids): Collection
    {
        if (! class_exists($type)) {
            return collect();
        }

        /** @var Model $model */
        $model = new $type;

        return $model->newQuery()
            ->whereIn('id', $ids)
            ->get(['id', 'reference'])
            ->mapWithKeys(fn ($quote) => [$quote->id => 'Devis '.$quote->reference]);
    }

    // =========================================================================
    // Répartition par statut
    // =========================================================================

    /**
     * Les réservations de la période par statut — les annulations comprises,
     * puisque c'est ici qu'elles sont le sujet.
     *
     * Les trois statuts d'annulation sont FUSIONNÉS en un seul segment :
     * distinguer à l'écran l'annulation du client, celle du prestataire et
     * celle de l'administration coûterait trois teintes pour une nuance qui
     * relève de la fiche, pas du panorama.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function bookingStatuses(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = Booking::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (BookingStatus $status) => (int) ($counts[$status->value] ?? 0);

        return [
            ['key' => 'en_attente', 'label' => 'En attente', 'count' => $count(BookingStatus::EN_ATTENTE)],
            ['key' => 'confirmee', 'label' => 'Confirmées', 'count' => $count(BookingStatus::CONFIRMEE)],
            ['key' => 'en_cours', 'label' => 'En cours', 'count' => $count(BookingStatus::EN_COURS)],
            ['key' => 'terminee', 'label' => 'Terminées', 'count' => $count(BookingStatus::TERMINEE)],
            [
                'key' => 'annulee',
                'label' => 'Annulées',
                'count' => array_sum(array_map(
                    fn (string $status) => (int) ($counts[$status] ?? 0),
                    $this->cancelledStatuses(),
                )),
            ],
        ];
    }

    /**
     * Valeurs de statut correspondant à une annulation.
     *
     * ⚠️ Plusieurs `selectRaw` ci-dessus lient exactement TROIS paramètres à
     * leur `in (?, ?, ?)`. Si un quatrième statut d'annulation apparaissait un
     * jour dans `BookingStatus`, ces requêtes lèveraient une erreur de liaison
     * — bruyamment, et c'est voulu : mieux vaut une exception au premier appel
     * qu'un chiffre d'affaires faux affiché sans broncher.
     *
     * @return array<int, string>
     */
    private function cancelledStatuses(): array
    {
        return array_values(array_map(
            fn (BookingStatus $s) => $s->value,
            array_filter(BookingStatus::cases(), fn (BookingStatus $s) => $s->estAnnulee()),
        ));
    }
}
