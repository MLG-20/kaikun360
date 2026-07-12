<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Génération de liens WhatsApp « click-to-chat » contextuels (B16.3).
 *
 * Produit une URL `wa.me` avec un message prérempli selon la page/le service
 * d'où provient l'utilisateur. Le numéro de support provient du paramétrage
 * back-office (`support.phone`, B13.4) — jamais codé en dur.
 */
class WhatsAppLinkController extends Controller
{
    /**
     * GET /api/v1/whatsapp/link?subject=...&reference=...
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        // wa.me exige un numéro en chiffres uniquement (indicatif compris).
        $phone = preg_replace('/\D/', '', (string) Settings::get('support.phone', ''));
        $message = $this->buildMessage($data['subject'] ?? null, $data['reference'] ?? null);

        return ApiResponse::success([
            'url' => "https://wa.me/{$phone}?text=".rawurlencode($message),
            'phone' => $phone,
            'message' => $message,
        ]);
    }

    /**
     * Construit le message prérempli à partir du contexte.
     */
    private function buildMessage(?string $subject, ?string $reference): string
    {
        if ($subject === null && $reference === null) {
            return 'Bonjour, je souhaite un renseignement sur Kaikun 360.';
        }

        $message = 'Bonjour, je vous contacte';
        $message .= $subject !== null ? " au sujet de : {$subject}" : '';
        $message .= $reference !== null ? " (réf. {$reference})" : '';

        return $message.'.';
    }
}
