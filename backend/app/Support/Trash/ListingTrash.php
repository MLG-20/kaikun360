<?php

namespace App\Support\Trash;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Enums\MandateStatus;
use App\Modules\Manage\Models\ManagementMandate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Corbeille des espaces utilisateurs (F11.4) — les règles, en un seul endroit.
 *
 * Cette classe ne fait que DEUX choses, et c'est volontaire : dire si une
 * annonce peut partir à la corbeille, et dire dans quel état elle en revient.
 * Tout le reste (l'effacement lui-même) appartient à Eloquent via `SoftDeletes`.
 *
 * ⚠️ **Pourquoi une classe partagée et non une méthode par contrôleur** : la
 * même question se pose pour cinq types d'annonces dans trois espaces
 * différents. Réécrire « à peu près la même » condition cinq fois est la façon
 * la plus banale de laisser passer un cas — ici, celui qui casse un contrat.
 */
class ListingTrash
{
    /**
     * Durée de conservation, en jours, avant effacement définitif.
     *
     * ⚠️ Cette constante est la SEULE source : l'écran affiche son compte à
     * rebours à partir d'elle, et la commande de purge s'en sert pour décider.
     * La changer à un seul endroit change les deux, sans dérive possible.
     */
    public const JOURS_DE_CONSERVATION = 30;

    /**
     * Les cinq types d'annonces qui ont une corbeille, par le mot d'URL qui les
     * désigne.
     *
     * ⚠️ **Ce sont exactement les slugs de `App\Support\Favoritables`**, et ce
     * n'est pas un hasard : ce sont les cinq objets qu'un visiteur peut mettre
     * en favori, donc les cinq annonces publiables de la plateforme. Garder le
     * même vocabulaire évite qu'un `property` ici désigne autre chose qu'un
     * `property` là-bas.
     *
     * @var array<string, class-string<Model>>
     */
    public const TYPES = [
        'property' => Property::class,
        'stay' => \App\Modules\Stay\Models\Stay::class,
        'vehicle' => \App\Modules\Mobility\Models\Vehicle::class,
        'experience' => \App\Modules\Explore\Models\TourismExperience::class,
        'mobility' => \App\Modules\Mobility\Models\MobilityService::class,
    ];

    /**
     * Statuts de réservation qui ENGAGENT encore l'annonce.
     *
     * ⚠️ Les statuts d'annulation et « terminée » n'y figurent pas, et c'est le
     * cœur du raisonnement : une réservation passée ou annulée n'empêche rien —
     * sinon un prestataire actif depuis un an ne pourrait plus jamais ranger un
     * seul véhicule. Ce qui bloque, c'est ce qui est encore devant nous.
     */
    private const RESERVATIONS_BLOQUANTES = [
        BookingStatus::EN_ATTENTE,
        BookingStatus::CONFIRMEE,
        BookingStatus::EN_COURS,
    ];

    /**
     * Requête des annonces d'un type DÉJÀ à la corbeille et appartenant à cette
     * personne — la seule porte d'entrée de la corbeille côté lecture.
     *
     * ⚠️ **Le cloisonnement est RECOPIÉ de la colonne réelle de chaque modèle,
     * pas deviné.** Les cinq tables ne nomment pas leur propriétaire pareil :
     * `owner_id` pour un bien, `provider_id` pour un véhicule, une expérience
     * ou un service de mobilité — et une nuitée n'en porte AUCUN : elle
     * appartient au propriétaire de son bien, par ricochet. Écrire un
     * `where('user_id', …)` de bonne foi compilerait et ne renverrait
     * simplement rien, ou pire, renverrait les annonces d'un autre.
     */
    public function corbeilleDe(string $slug, int $userId): ?Builder
    {
        $classe = self::TYPES[$slug] ?? null;

        if ($classe === null) {
            return null;
        }

        /** @var Builder $requete */
        $requete = $classe::onlyTrashed();

        return match ($slug) {
            'property' => $requete->where('owner_id', $userId),
            'vehicle', 'experience', 'mobility' => $requete->where('provider_id', $userId),
            // La nuitée n'a pas de propriétaire à elle : on passe par son bien.
            // `withTrashed()` sur la relation est indispensable — si le bien est
            // lui aussi à la corbeille, la nuitée deviendrait introuvable et
            // resterait bloquée là pour toujours.
            'stay' => $requete->whereHas(
                'property',
                fn (Builder $q) => $q->withTrashed()->where('owner_id', $userId),
            ),
            default => null,
        };
    }

    /**
     * Intitulé lisible d'une annonce, pour l'afficher dans la corbeille.
     *
     * ⚠️ Aucun des cinq modèles ne porte le même champ : un bien et une
     * expérience ont un `title`, un véhicule se nomme par sa marque et son
     * modèle, un service de mobilité par son trajet, et une nuitée n'a
     * strictement rien — son nom est celui de son bien.
     */
    public function intitule(Model $annonce): string
    {
        return match (true) {
            $annonce instanceof Property => (string) $annonce->title,
            $annonce instanceof \App\Modules\Explore\Models\TourismExperience => (string) $annonce->title,
            $annonce instanceof \App\Modules\Mobility\Models\Vehicle => trim("{$annonce->brand} {$annonce->model}"),
            $annonce instanceof \App\Modules\Mobility\Models\MobilityService => "{$annonce->departure} → {$annonce->destination}",
            $annonce instanceof \App\Modules\Stay\Models\Stay => 'Nuitée — '.($annonce->property?->title ?? 'bien supprimé'),
            default => 'Annonce',
        };
    }

    /**
     * Nombre de jours restants avant l'effacement définitif, jamais négatif.
     *
     * ⚠️ Une annonce dont le compte est à 0 n'est PAS encore effacée : la purge
     * est une tâche planifiée qui passe une fois par jour. L'écran doit donc
     * savoir dire « aujourd'hui » sans mentir sur ce qui est déjà parti.
     */
    public function joursRestants(Model $annonce): int
    {
        $supprimeLe = $annonce->getAttribute('deleted_at');

        if ($supprimeLe === null) {
            return self::JOURS_DE_CONSERVATION;
        }

        $limite = $supprimeLe->copy()->addDays(self::JOURS_DE_CONSERVATION);

        return max(0, (int) ceil(now()->diffInDays($limite, false)));
    }

    /**
     * Raison pour laquelle un BIEN ne peut pas aller à la corbeille, ou `null`.
     *
     * ⚠️ **Réservée aux biens immobiliers, et à eux seuls.** Les trois offres
     * de prestataire (véhicule, expérience, départ) ont déjà leur doctrine
     * depuis F8.19 — `OfferRetirementService`, plus stricte encore : *toute*
     * réservation, même annulée, y interdit la suppression et déclenche un
     * retrait conservatoire. Poser une seconde règle « à peu près pareille »
     * sur ces types-là aurait créé deux vérités concurrentes ; le bien, lui,
     * n'était couvert par aucune des deux.
     *
     * Le message est destiné à être affiché tel quel : il dit ce qui bloque
     * *et* ce qu'il faut faire pour débloquer. « Suppression impossible » tout
     * court laisse la personne sans issue.
     */
    public function raisonDeBlocage(Property $annonce): ?string
    {
        if ($nombre = $this->reservationsEnCours($annonce)) {
            return $nombre === 1
                ? 'Une réservation est encore en cours sur cette annonce. Attendez qu’elle se termine, ou annulez-la avant de la mettre à la corbeille.'
                : "{$nombre} réservations sont encore en cours sur cette annonce. Attendez qu’elles se terminent, ou annulez-les avant de la mettre à la corbeille.";
        }

        if ($this->sousMandatActif($annonce)) {
            return 'Ce bien est confié en gestion locative par un mandat actif. Mettez fin au mandat avant de le mettre à la corbeille.';
        }

        return null;
    }

    /**
     * Compte les réservations qui engagent encore l'annonce.
     *
     * ⚠️ La relation est POLYMORPHE (`bookable_type` / `bookable_id`), ce qui
     * permet à cette seule requête de servir les cinq types d'annonces. On
     * interroge donc `Booking` directement plutôt que la relation du modèle,
     * que tous ne déclarent pas sous le même nom.
     */
    private function reservationsEnCours(Model $annonce): int
    {
        return Booking::query()
            ->where('bookable_type', $annonce->getMorphClass())
            ->where('bookable_id', $annonce->getKey())
            ->whereIn('status', array_map(
                static fn (BookingStatus $statut) => $statut->value,
                self::RESERVATIONS_BLOQUANTES,
            ))
            ->count();
    }

    /** Le bien est-il actuellement confié en gestion locative ? */
    private function sousMandatActif(Property $bien): bool
    {
        return ManagementMandate::query()
            ->where('property_id', $bien->getKey())
            ->whereIn('status', [MandateStatus::ACTIF->value, MandateStatus::EN_ATTENTE->value])
            ->exists();
    }

    /**
     * Remet une annonce sortie de la corbeille dans un état ÉTEINT.
     *
     * ⚠️ **La règle de sécurité de toute la tranche.** Une annonce restaurée ne
     * doit jamais se remettre en ligne d'elle-même : entre sa mise à la
     * corbeille et sa restauration, le bien a pu être vendu, le véhicule
     * accidenté, le prix devenir faux. Elle revient donc dans la liste de son
     * propriétaire, visible de lui seul, et c'est un geste explicite qui la
     * republie — avec, pour les biens, le passage par la validation habituelle.
     *
     * Les statuts diffèrent d'un type à l'autre ; on écrit celui qui existe.
     */
    public function eteindre(Model $annonce): void
    {
        if ($annonce instanceof Property) {
            $annonce->status = PropertyStatus::ARCHIVE->value;
            $annonce->save();

            return;
        }

        // Les autres types portent tous un statut sous forme de chaîne, avec
        // une valeur « suspendu » ou « archive » selon le module. On ne devine
        // pas : on ne touche au statut que si la valeur cible existe vraiment
        // dans l'énumération du modèle, sinon on laisse le statut d'origine —
        // l'annonce reste hors ligne de toute façon tant qu'elle n'a pas été
        // republiée par son propriétaire.
        $statutActuel = $annonce->getAttribute('status');

        if ($statutActuel === null) {
            return;
        }

        $enumeration = $annonce->getCasts()['status'] ?? null;

        if (! is_string($enumeration) || ! enum_exists($enumeration)) {
            return;
        }

        foreach (['suspendu', 'archive', 'inactif'] as $cible) {
            if ($enumeration::tryFrom($cible) !== null) {
                $annonce->status = $cible;
                $annonce->save();

                return;
            }
        }
    }
}
