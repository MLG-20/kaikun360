<?php

namespace App\Http\Controllers;

use App\Enums\ContactMessageStatus;
use App\Http\Requests\StoreContactMessageRequest;
use App\Http\Requests\UpdateContactMessageRequest;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use App\Support\ApiResponse;
use App\Support\Settings;
use App\Support\Webhooks\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Messages de contact (F2.8.1).
 *
 * Dépôt PUBLIC depuis la page Contact (`store`) ; consultation et traitement
 * réservés à l'équipe (`index`/`update`, permission `traiter:demandes` portée
 * par les routes admin).
 */
class ContactController extends Controller
{
    /**
     * Coordonnées publiques du siège. GET /api/v1/contact-info (public).
     *
     * Expose le sous-ensemble affichable sur la page Contact (e-mail, téléphone,
     * adresse, latitude/longitude pour la carte) et, depuis F7.2.l, les
     * **réseaux sociaux** du pied de page. Les valeurs proviennent des réglages
     * (back-office) : rien n'est codé en dur côté frontend.
     */
    public function info(): JsonResponse
    {
        return ApiResponse::success(['contact' => [
            'email' => Settings::get('support.email'),
            'phone' => Settings::get('support.phone'),
            'address' => Settings::get('contact.address'),
            'latitude' => Settings::get('contact.latitude'),
            'longitude' => Settings::get('contact.longitude'),
            // Cast en objet : sans lui, un tableau PHP vide se sérialiserait en
            // `[]` (tableau JSON) alors que le contrat côté frontend est une
            // **map** réseau → URL. On garde `{}` dans tous les cas.
            'social' => (object) $this->socialLinks(),
        ]]);
    }

    /**
     * Réseaux sociaux renseignés, dans l'ordre d'affichage du pied de page.
     *
     * Les réseaux laissés vides sont **omis** : le frontend rend simplement ce
     * qu'il reçoit, sans avoir à filtrer, et un réseau non ouvert n'apparaît
     * jamais sous forme de lien mort.
     *
     * @return array<string, string>
     */
    private function socialLinks(): array
    {
        $networks = ['facebook', 'instagram', 'tiktok', 'linkedin', 'youtube'];

        $links = [];
        foreach ($networks as $network) {
            $url = trim((string) Settings::get("social.{$network}", ''));
            if ($url !== '') {
                $links[$network] = $url;
            }
        }

        return $links;
    }

    /**
     * Enregistre un message de contact. POST /api/v1/contact (public).
     *
     * Émet l'événement n8n `contact.received` pour que l'équipe soit notifiée
     * (no-op tant que l'intégration n'est pas activée).
     */
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $message = ContactMessage::create($request->validated() + [
            'status' => ContactMessageStatus::NOUVEAU->value,
        ]);

        WebhookDispatcher::emit(WebhookDispatcher::CONTACT_RECEIVED, [
            'id' => $message->id,
            'name' => $message->name,
            'email' => $message->email,
            'subject' => $message->subject,
        ]);

        return ApiResponse::created(['contact_message' => ContactMessageResource::make($message)]);
    }

    /**
     * Liste des messages pour l'équipe. GET /api/v1/admin/contact-messages
     *
     * Filtrable par statut (`?status=nouveau|traite`), du plus récent au plus
     * ancien.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $messages = ContactMessage::query()
            // F8.15.c — sans ce chargement, `handled_by` restait absent de la
            // réponse (`whenLoaded`) : l'écran ne pouvait pas dire QUI avait
            // traité un message, donc deux agents rappelaient le même prospect.
            ->with('handledBy:id,name')
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
            )
            ->latest()
            ->paginate(20);

        // F8.15.c — le compteur des messages NON TRAITÉS voyage avec la page :
        // filtrer sur « traités » ne doit pas faire disparaître le nombre de
        // ceux qui attendent, sinon l'écran ment sur la charge restante.
        return ContactMessageResource::collection($messages)->additional([
            'meta' => [
                'pending' => ContactMessage::where('status', ContactMessageStatus::NOUVEAU->value)->count(),
            ],
        ]);
    }

    /**
     * Change le statut d'un message. PATCH /api/v1/admin/contact-messages/{contactMessage}
     *
     * Le passage à « traité » enregistre l'agent et l'horodatage ; le retour à
     * « nouveau » les efface.
     */
    public function update(UpdateContactMessageRequest $request, ContactMessage $contactMessage): JsonResponse
    {
        $status = ContactMessageStatus::from($request->validated()['status']);
        $traite = $status === ContactMessageStatus::TRAITE;

        $contactMessage->update([
            'status' => $status->value,
            'handled_by' => $traite ? $request->user()->id : null,
            'handled_at' => $traite ? now() : null,
        ]);

        activity()->causedBy($request->user())->performedOn($contactMessage)
            ->log('Traitement de message de contact');

        return ApiResponse::success([
            'contact_message' => ContactMessageResource::make($contactMessage->fresh()),
        ]);
    }
}
