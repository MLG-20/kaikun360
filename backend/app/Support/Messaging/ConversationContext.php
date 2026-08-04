<?php

namespace App\Support\Messaging;

use App\Models\Booking;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\MobilityService;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Stay\Models\Stay;
use Illuminate\Database\Eloquent\Model;

/**
 * Le DOSSIER dont parle une conversation (F8.12).
 *
 * `conversations.context_type` / `context_id` existent depuis F3.7 et n'avaient
 * jamais été renseignés : tous les fils arrivaient nus. Un agent qui ouvre
 * « Bonjour, est-ce toujours disponible ? » sans savoir de QUOI il s'agit perd
 * son temps à demander — ce registre est ce qui évite ce premier aller-retour.
 *
 * Deux services rendus, et un seul endroit où les tenir :
 *
 *   1. **Traduire un slug public en modèle.** Le frontend envoie `demande` ou
 *      `nuitee`, jamais un nom de classe PHP : accepter `context_type` brut
 *      depuis le réseau laisserait pointer un fil vers n'importe quelle table.
 *      La liste blanche ci-dessous est donc la frontière de sécurité.
 *   2. **Dire qui a le droit de citer ce dossier.** Deux familles :
 *      - les dossiers PERSONNELS (demande, devis, réservation) : seul leur
 *        titulaire peut ouvrir un fil dessus, sinon on offrirait à n'importe
 *        qui un moyen de sonder l'existence des dossiers d'autrui ;
 *      - les fiches du CATALOGUE (bien, nuitée, véhicule, circuit, trajet) :
 *        publiques par nature, c'est justement le visiteur intéressé qui écrit.
 *
 * ⚠️ Le contexte reste FACULTATIF : un client peut toujours écrire au support
 * sans dossier (« comment fonctionne la caution ? »).
 */
final class ConversationContext
{
    /**
     * Liste blanche des contextes admis, par slug public.
     *
     * `owned` : le fil ne peut être ouvert que par le titulaire du dossier.
     * `label` : étiquette française affichée des deux côtés du fil.
     *
     * @return array<string, array{model: class-string<Model>, label: string, owned: bool}>
     */
    public static function map(): array
    {
        return [
            // Dossiers personnels — réservés à leur titulaire.
            'demande' => ['model' => ServiceRequest::class, 'label' => 'Demande', 'owned' => true],
            'devis' => ['model' => Quote::class, 'label' => 'Devis', 'owned' => true],
            'reservation' => ['model' => Booking::class, 'label' => 'Réservation', 'owned' => true],

            // Fiches du catalogue — publiques, on écrit AVANT d'être client.
            'bien' => ['model' => Property::class, 'label' => 'Bien immobilier', 'owned' => false],
            'nuitee' => ['model' => Stay::class, 'label' => 'Nuitée', 'owned' => false],
            'vehicule' => ['model' => Vehicle::class, 'label' => 'Véhicule', 'owned' => false],
            'circuit' => ['model' => TourismExperience::class, 'label' => 'Circuit', 'owned' => false],
            'trajet' => ['model' => MobilityService::class, 'label' => 'Trajet', 'owned' => false],
        ];
    }

    /**
     * Les slugs acceptés par la validation (`Rule::in`).
     *
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_keys(self::map());
    }

    /**
     * Résout un couple (slug, identifiant) en modèle, en refusant ce que
     * l'utilisateur n'a pas le droit de citer.
     *
     * @return Model|null le dossier, ou null si le slug est inconnu,
     *                    l'enregistrement absent, ou l'accès non légitime
     */
    public static function resolve(?string $slug, ?int $id, User $user): ?Model
    {
        if ($slug === null || $id === null) {
            return null;
        }

        $entry = self::map()[$slug] ?? null;

        if ($entry === null) {
            return null;
        }

        /** @var Model|null $model */
        $model = $entry['model']::find($id);

        if ($model === null) {
            return null;
        }

        return ! $entry['owned'] || self::belongsTo($model, $user) ? $model : null;
    }

    /**
     * Étiquette française d'un contexte déjà enregistré (on relit alors le nom
     * de classe stocké, pas le slug — c'est lui qui est en base).
     */
    public static function labelForClass(?string $class): ?string
    {
        if ($class === null) {
            return null;
        }

        foreach (self::map() as $entry) {
            if ($entry['model'] === $class || class_basename($entry['model']) === class_basename($class)) {
                return $entry['label'];
            }
        }

        return null;
    }

    /**
     * Le PROFESSIONNEL derrière le dossier (F8.12.c) : le propriétaire du bien,
     * l'hôte de la nuitée, le propriétaire du véhicule, le prestataire du
     * circuit ou du trajet.
     *
     * C'est la personne que l'agent proposera d'ajouter au fil en un clic —
     * « ajouter » restant **son jugement**, jamais une règle automatique : une
     * question de disponibilité se transmet volontiers, une négociation de prix
     * se garde.
     *
     * `null` quand le dossier n'a pas de tiers : une demande générique ou un
     * devis sur-mesure ne concernent que le client et Kaikun.
     */
    public static function holder(?Model $context): ?User
    {
        return match (true) {
            $context === null => null,
            $context instanceof Property => $context->owner,
            // Une nuitée n'a pas d'hôte à elle : elle se loue dans un BIEN,
            // c'est le propriétaire de celui-ci qui répond.
            $context instanceof Stay => $context->property?->owner,
            $context instanceof Vehicle,
            $context instanceof TourismExperience,
            $context instanceof MobilityService => $context->provider,
            // Une réservation renvoie à ce qui est réservé (`bookable`) : un
            // séjour, un véhicule, un circuit, un trajet — ou un devis, qui n'a
            // pas de tiers. On repasse donc par la même table.
            $context instanceof Booking => self::holder($context->bookable),
            default => null,
        };
    }

    /**
     * Le dossier appartient-il à cet utilisateur ?
     *
     * Le devis n'a pas de `user_id` : il pend à une demande, c'est le titulaire
     * de CELLE-CI qui est concerné (même chaîne qu'en F8.11).
     */
    private static function belongsTo(Model $model, User $user): bool
    {
        if ($model instanceof Quote) {
            return $model->request?->user_id === $user->id;
        }

        return $model->getAttribute('user_id') === $user->id;
    }
}
