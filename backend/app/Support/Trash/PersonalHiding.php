<?php

namespace App\Support\Trash;

use App\Enums\BookingStatus;
use App\Enums\RequestStatus;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Corbeille de l'espace CLIENT (F11.5) — les règles, en un seul endroit.
 *
 * Le pendant de {@see ListingTrash} pour les objets que le client ne possède
 * pas seul. La différence tient en une phrase, et c'est elle qui justifie une
 * classe séparée plutôt qu'un paramètre de plus dans l'autre :
 *
 *   - une ANNONCE appartient à son auteur → il peut la faire disparaître, et
 *     elle finit par être effacée pour de bon (`SoftDeletes`, 30 jours) ;
 *   - un DOSSIER (demande, réservation, fil, notification) est partagé avec
 *     Kaikun → il n'est **jamais** effacé, seulement **retiré de la vue du
 *     client**.
 *
 * Mélanger les deux dans une classe unique aurait rendu le second cas
 * paramétrable — donc, un jour, activable par erreur sur le premier. Ici, il
 * n'existe littéralement aucun chemin de code qui supprime une réservation.
 *
 * ⚠️ **Les quatre types ne rangent pas leur masque au même endroit** :
 * `requests`, `bookings` et `notifications` portent `hidden_at` sur leur propre
 * ligne ; un FIL le porte sur le **pivot** `conversation_user`, parce qu'il a
 * plusieurs lecteurs et que le client ne range que sa propre vue. C'est la
 * raison pour laquelle toutes les méthodes publiques d'ici prennent le `User`
 * en argument : sans lui, le cas du pivot ne pourrait pas s'écrire.
 */
class PersonalHiding
{
    /**
     * Les quatre types de dossiers masquables, par le mot d'URL qui les désigne.
     *
     * ⚠️ Ces slugs vivent dans le MÊME espace de noms que ceux de
     * `ListingTrash::TYPES` — la route `me/trash/{type}/{id}/restore` les sert
     * tous. Aucun ne collisionne avec les cinq types d'annonces, et aucun ne
     * doit jamais le faire.
     *
     * @var list<string>
     */
    public const TYPES = ['request', 'booking', 'conversation', 'notification'];

    /**
     * Statuts de demande qui autorisent le rangement : la clôture, et elle
     * seule.
     *
     * ⚠️ **Périmètre voulu : on ne range que ce qui est TERMINÉ, vu ou lu.** Une
     * demande en cours de négociation masquée serait une demande qu'on cesse de
     * suivre alors qu'un agent y travaille encore — et le client s'étonnerait
     * de ne plus rien voir arriver. Un dossier vivant reste sous les yeux.
     */
    private const DEMANDES_RANGEABLES = [RequestStatus::CLOTURE];

    /**
     * Statuts de réservation qui autorisent le rangement : le service rendu et
     * les trois annulations.
     *
     * ⚠️ `EN_ATTENTE` et `CONFIRMEE` en sont volontairement absents : une
     * réservation à venir se range en l'annulant, pas en la cachant. Cacher un
     * séjour payé qu'on prendra dans trois semaines n'allège pas une liste, ça
     * fabrique un oubli.
     */
    private const RESERVATIONS_RANGEABLES = [
        BookingStatus::TERMINEE,
        BookingStatus::ANNULEE_CLIENT,
        BookingStatus::ANNULEE_PRESTATAIRE,
        BookingStatus::ANNULEE_ADMIN,
    ];

    /**
     * Tous les dossiers d'un type que cette personne a rangés.
     *
     * ⚠️ Le cloisonnement passe TOUJOURS par une relation de l'utilisateur
     * (`$user->conversations()`, `$user->notifications()`) ou par un
     * `where('user_id', …)` explicite : jamais par un `Model::find()` global,
     * qu'un identifiant deviné suffirait à détourner.
     *
     * @return Collection<int, Model>
     */
    public function elementsMasques(string $slug, User $user): Collection
    {
        return match ($slug) {
            'request' => ServiceRequest::query()
                ->where('user_id', $user->id)->whereNotNull('hidden_at')->get(),

            'booking' => Booking::query()
                ->where('user_id', $user->id)->whereNotNull('hidden_at')->get(),

            'notification' => $user->notifications()->whereNotNull('hidden_at')->get(),

            // ⚠️ `wherePivotNotNull` et non `whereNotNull` : le masque est sur
            // le pivot. Un `whereNotNull('hidden_at')` compilerait et
            // interrogerait la colonne… de `conversations`, qui n'existe pas.
            'conversation' => $user->conversations()
                ->wherePivotNotNull('hidden_at')
                ->get(),

            default => new Collection,
        };
    }

    /**
     * Retrouve UN dossier rangé de cette personne, ou `null`.
     *
     * ⚠️ `$id` est volontairement typé `string` : l'identifiant d'une
     * notification est un **UUID**, pas un entier. Le comparer à un `int`
     * l'écraserait à 0.
     */
    public function trouverMasque(string $slug, User $user, string $id): ?Model
    {
        return $this->elementsMasques($slug, $user)
            ->first(fn (Model $dossier) => (string) $dossier->getKey() === $id);
    }

    /**
     * Raison pour laquelle un dossier ne peut pas être rangé, ou `null`.
     *
     * Le message est destiné à être affiché tel quel : il dit ce qui bloque
     * *et* ce qu'il faut faire pour débloquer. « Impossible » tout court laisse
     * la personne sans issue.
     */
    public function raisonDeBlocage(Model $dossier, User $user): ?string
    {
        if ($dossier instanceof ServiceRequest) {
            return in_array($dossier->status, self::DEMANDES_RANGEABLES, true)
                ? null
                : 'Cette demande est encore en cours de traitement. Vous pourrez la ranger une fois qu’elle sera clôturée.';
        }

        if ($dossier instanceof Booking) {
            return in_array($dossier->status, self::RESERVATIONS_RANGEABLES, true)
                ? null
                : 'Cette réservation n’est pas terminée. Attendez la fin du séjour ou de la prestation, ou annulez-la, avant de la ranger.';
        }

        if ($dossier instanceof DatabaseNotification) {
            // « Déjà lue » est la seule condition, et elle suffit : ranger une
            // notification non lue reviendrait à effacer l'information avant
            // de l'avoir reçue.
            return $dossier->read_at !== null
                ? null
                : 'Cette notification n’a pas encore été lue. Ouvrez-la avant de la ranger.';
        }

        if ($dossier instanceof Conversation) {
            return $this->filEntierementLu($dossier, $user)
                ? null
                : 'Ce fil contient des messages que vous n’avez pas encore lus. Ouvrez-le avant de le ranger.';
        }

        return 'Ce dossier ne peut pas être rangé.';
    }

    /** Le dossier est-il rangeable en l'état ? (miroir exact du blocage). */
    public function estRangeable(Model $dossier, User $user): bool
    {
        return $this->raisonDeBlocage($dossier, $user) === null;
    }

    /**
     * Intitulé lisible d'un dossier, pour l'afficher dans la corbeille.
     *
     * ⚠️ On reprend le vocabulaire des écrans d'origine — l'univers pour une
     * demande, le sujet pour un fil. La référence (`REQ-XXXXXXXX`) vient en
     * second : c'est elle qu'on cite au support, mais seule elle ne rappelle
     * rien.
     */
    public function intitule(Model $dossier): string
    {
        if ($dossier instanceof ServiceRequest) {
            $univers = $dossier->service_type?->label() ?? 'Demande';

            return "Demande — {$univers} ({$dossier->reference})";
        }

        if ($dossier instanceof Booking) {
            // La ressource sait déjà nommer une réservation, mais elle expose
            // une trentaine de champs dont aucun n'a sa place ici. On garde la
            // référence, seule information toujours présente quel que soit le
            // type de `bookable` (et même si celui-ci a disparu).
            return "Réservation {$dossier->reference}";
        }

        if ($dossier instanceof Conversation) {
            return 'Discussion — '.($dossier->subject ?: 'sans objet');
        }

        if ($dossier instanceof DatabaseNotification) {
            // ⚠️ Le contenu d'une notification vit dans un JSON libre, dont les
            // clés varient d'un flux à l'autre. On prend la première qui parle,
            // et jamais le type de classe PHP — « BookingConfirmedNotification »
            // n'est pas une phrase qu'on montre à quelqu'un.
            $donnees = (array) $dossier->data;

            foreach (['title', 'titre', 'message', 'body'] as $cle) {
                if (! empty($donnees[$cle]) && is_string($donnees[$cle])) {
                    return 'Notification — '.$donnees[$cle];
                }
            }

            return 'Notification';
        }

        return 'Dossier';
    }

    /**
     * Quand ce dossier a-t-il été rangé ? (chaîne vide s'il ne l'est pas).
     *
     * ⚠️ Le fil lit son masque sur le **pivot** : `$fil->hidden_at` renverrait
     * `null` sans erreur, et la corbeille trierait tous les fils au même rang.
     */
    public function masqueLe(Model $dossier, User $user): string
    {
        if ($dossier instanceof Conversation) {
            $pivot = $dossier->pivot
                ?? $user->conversations()->find($dossier->getKey())?->pivot;

            return (string) ($pivot?->hidden_at ?? '');
        }

        return (string) ($dossier->getAttribute('hidden_at') ?? '');
    }

    /**
     * Range un dossier : il quitte la liste du client, rien de plus.
     *
     * ⚠️ Le fil passe par le pivot — c'est le seul des quatre types dont le
     * masque n'est pas une colonne de sa propre table.
     */
    public function masquer(Model $dossier, User $user): void
    {
        if ($dossier instanceof Conversation) {
            $user->conversations()->updateExistingPivot($dossier->getKey(), ['hidden_at' => now()]);

            return;
        }

        $dossier->forceFill(['hidden_at' => now()])->save();
    }

    /**
     * Sort un dossier de la corbeille : il réapparaît dans la liste du client,
     * **exactement dans l'état où il était**.
     *
     * ⚠️ Aucune symétrie avec `ListingTrash::eteindre()`, et c'est normal : une
     * annonce restaurée revient éteinte parce qu'elle est *publiée à des tiers*
     * et que le monde a pu changer entre-temps. Un dossier masqué n'a jamais
     * cessé d'exister pour Kaikun ni pour le partenaire — le rendre visible ne
     * change rien à son statut, et y toucher serait réécrire un contrat.
     */
    public function reafficher(Model $dossier, User $user): void
    {
        if ($dossier instanceof Conversation) {
            $user->conversations()->updateExistingPivot($dossier->getKey(), ['hidden_at' => null]);

            return;
        }

        $dossier->forceFill(['hidden_at' => null])->save();
    }

    /**
     * Le participant a-t-il tout lu de ce fil ?
     *
     * ⚠️ On réutilise `Conversation::unreadCountFor()` — la MÊME méthode que la
     * pastille de non-lus — plutôt que de recomparer `last_read_at` à la main.
     * Deux calculs de « non lu » qui divergent d'une seconde donneraient un
     * bouton « Ranger » qui refuse un fil que l'écran affiche comme lu.
     */
    private function filEntierementLu(Conversation $fil, User $user): bool
    {
        // `unreadCountFor` lit le pivot via la relation `participants` : sans ce
        // chargement, elle ne trouverait aucun participant et compterait tous
        // les messages comme non lus.
        $fil->loadMissing('participants');

        return $fil->unreadCountFor($user) === 0;
    }
}
