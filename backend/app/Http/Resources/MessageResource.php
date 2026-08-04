<?php

namespace App\Http\Resources;

use App\Support\Messaging\ContactMasker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation JSON d'un message de la messagerie (transversale, F3.7).
 *
 * `is_mine` est calculé depuis l'utilisateur courant : le frontend aligne ainsi
 * les bulles (à droite = mes messages, à gauche = ceux du correspondant) sans
 * avoir à connaître son propre identifiant. Le nom de l'auteur n'est exposé que
 * si la relation `sender` a été chargée (évite les requêtes N+1).
 *
 * @mixin \App\Models\Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lecteur = $request->user();

        // F8.12.c — masquage des coordonnées ENTRE NON-STAFF. Depuis que l'agent
        // peut faire entrer un propriétaire ou un prestataire dans un fil, les
        // deux parties se parlent directement : le premier réflexe est
        // d'échanger un numéro et de partir sur WhatsApp, où il n'y a plus ni
        // paiement sécurisé, ni caution, ni recours. ⚠️ L'ÉQUIPE, elle, voit le
        // texte entier — sans quoi elle ne pourrait ni comprendre un litige ni
        // sanctionner une désintermédiation manifeste.
        $corps = $lecteur?->estStaff()
            ? $this->body
            : ContactMasker::mask($this->body);

        return [
            'id' => $this->id,
            'body' => $corps,
            'sender' => [
                'id' => $this->sender_id,
                'name' => $this->whenLoaded('sender', fn () => $this->sender?->name),
            ],
            // Ce message a-t-il été émis par l'utilisateur qui consulte ?
            'is_mine' => $request->user()?->id === $this->sender_id,
            'created_at' => $this->created_at,
        ];
    }
}
