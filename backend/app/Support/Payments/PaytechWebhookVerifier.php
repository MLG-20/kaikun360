<?php

namespace App\Support\Payments;

/**
 * Vérification de l'authenticité des notifications PayTech (IPN) — B14.3,
 * réécrite en F8.5, repli rejouable retiré en F14 (revue de sécurité).
 *
 * ⚠️ **La version précédente ne pouvait rien valider.** Elle recalculait un
 * HMAC-SHA256 du corps brut avec une « signing key », comparé à un en-tête
 * `Signature`. PayTech ne procède pas ainsi : il n'envoie aucun en-tête de
 * signature, et il n'existe pas de signing key — c'est l'`API_SECRET` qui signe.
 * Toute notification réelle aurait été rejetée en 401.
 *
 * **Le contrat réel**, tel que documenté (docs.intech.sn) : PayTech poste un
 * formulaire contenant `hmac_compute`, un HMAC-SHA256 du message
 * `{final_item_price}|{ref_command}|{api_key}` avec l'`api_secret` pour clé —
 * la SEULE preuve retenue ici.
 *
 * ⚠️ **PayTech expose aussi `api_key_sha256` / `api_secret_sha256`**, deux
 * simples empreintes SHA-256 des clés. Une version antérieure les acceptait en
 * repli quand `hmac_compute` manquait. Ces empreintes sont **constantes**
 * d'une notification à l'autre — elles ne prouvent que la connaissance des
 * clés, pas l'authenticité du CONTENU (montant + référence) — donc rejouables
 * indéfiniment dès qu'une seule notification authentique a été captée
 * (log, outil de monitoring qui journalise le corps brut, etc.). Preuve de
 * concept réalisée en revue de sécurité : un paiement `en_attente` a pu être
 * basculé en `complete` avec les seules empreintes, sans jamais connaître
 * l'`API_SECRET`. Le repli est donc supprimé : une notification sans
 * `hmac_compute` valide est rejetée, jamais acceptée sur la seule foi des
 * empreintes.
 *
 * La comparaison passe par `hash_equals` (temps constant) : comparer deux
 * signatures avec `===` laisse fuir, par le temps de réponse, le nombre de
 * caractères devinés.
 */
class PaytechWebhookVerifier
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly ?string $apiSecret,
    ) {
    }

    /**
     * La notification est-elle authentique ?
     *
     * @param  array<string, mixed>  $payload  Corps de la requête IPN.
     */
    public function verify(array $payload): bool
    {
        // Sans clés configurées, on refuse : jamais l'inverse. Une plateforme
        // mal configurée doit cesser d'encaisser, pas tout accepter.
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return false;
        }

        return $this->verifyHmac($payload);
    }

    /**
     * HMAC-SHA256 lié au contenu (seule preuve d'authenticité retenue).
     *
     * @param  array<string, mixed>  $payload
     */
    private function verifyHmac(array $payload): bool
    {
        $received = $payload['hmac_compute'] ?? null;
        if (! is_string($received) || $received === '') {
            return false;
        }

        // ⚠️ L'ordre et le séparateur du message sont imposés par PayTech :
        // final_item_price | ref_command | api_key.
        $message = implode('|', [
            (string) ($payload['final_item_price'] ?? ''),
            (string) ($payload['ref_command'] ?? ''),
            (string) $this->apiKey,
        ]);

        $expected = hash_hmac('sha256', $message, (string) $this->apiSecret);

        return hash_equals($expected, $received);
    }
}
