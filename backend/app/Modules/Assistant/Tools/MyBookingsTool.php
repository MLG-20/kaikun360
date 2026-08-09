<?php

namespace App\Modules\Assistant\Tools;

use App\Models\Booking;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Stay\Models\Stay;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * « Où en sont mes réservations ? » (phase F10.2).
 *
 * Première question d'un client qui revient sur le site, et jusqu'ici l'assistant
 * n'avait rien à répondre : il renvoyait au support une demande à laquelle la
 * plateforme savait parfaitement répondre elle-même.
 *
 * ⚠️ **Le scope est celui de `BookingController::my`, à l'identique** :
 * `where('user_id', …)`. Toute la sécurité tient dans cette ligne — la
 * reformuler autrement serait rouvrir la question de l'isolation dans un second
 * endroit.
 *
 * Ouvert au **client** et à l'**entreprise** : les deux espaces montent les mêmes
 * écrans de réservation depuis F8.14.
 */
class MyBookingsTool extends PersonalRecordsTool
{
    public function name(): string
    {
        return 'mes_reservations';
    }

    public function description(): string
    {
        return 'Consulte les réservations de la personne connectée (séjours, véhicules, circuits, '
            .'trajets) : référence, dates, statut, montant et état du règlement. À utiliser quand '
            .'elle parle de SES réservations, de SON séjour, ou demande où en est un dossier '
            .'qu\'elle a déjà engagé. Aucun paramètre.';
    }

    /**
     * @return array<int, UserRole>
     */
    protected function roles(): array
    {
        return [UserRole::CLIENT, UserRole::ENTREPRISE];
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $url = $this->spaceUrl($context, 'reservations');

        $bookings = Booking::query()
            // ⚠️ Le cloisonnement, recopié du contrôleur et non réinventé.
            ->where('user_id', $context->user->id)
            // Le libellé d'une nuitée vient de son bien : sans ce chargement,
            // une requête par ligne (le N+1 que BookingResource évite déjà).
            ->with(['bookable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Stay::class => ['property'],
            ])])
            ->latest()
            ->limit($this->limit())
            ->get();

        if ($bookings->isEmpty()) {
            return $this->nothing(
                "Vous n'avez aucune réservation pour le moment.",
                'Voir le catalogue',
                '/immobilier',
            );
        }

        return new ToolResult(
            summary: $bookings->count() === 1
                ? 'Voici votre réservation la plus récente :'
                : 'Voici vos '.$bookings->count().' réservations les plus récentes :',
            items: $bookings->map(fn (Booking $booking) => [
                'reference' => $booking->reference,
                'titre' => $this->label($booking),
                'statut' => $booking->status?->label(),
                'periode' => $this->period($booking),
                'montant' => $this->money($booking->amount_xof),
                'url' => $url.'/'.$booking->id,
            ])->all(),
            actions: [AssistantAction::link('Voir toutes mes réservations', $url)],
        );
    }

    /**
     * Libellé de la chose réservée.
     *
     * ⚠️ On ne devine pas par « duck typing » (`isset($cible->title)`) : c'est
     * ce raccourci qui avait fait afficher « Vehicle » et « MobilityService » sur
     * l'écran des reversements (F8.16.a). Un `match` sur le type dit clairement
     * ce qu'on sait nommer, et le repli reste honnête plutôt qu'inventé.
     */
    private function label(Booking $booking): string
    {
        $bookable = $booking->bookable;

        if ($bookable === null) {
            return 'Réservation';
        }

        return match (true) {
            $bookable instanceof Stay => $bookable->property?->title ?? 'Séjour',
            isset($bookable->title) && is_string($bookable->title) => $bookable->title,
            default => 'Réservation',
        };
    }

    /**
     * Période lisible, quand la réservation en a une (un trajet n'en a pas).
     */
    private function period(Booking $booking): ?string
    {
        if ($booking->start_date === null) {
            return null;
        }

        $debut = $booking->start_date->format('d/m/Y');

        return $booking->end_date === null
            ? $debut
            : $debut.' → '.$booking->end_date->format('d/m/Y');
    }
}
