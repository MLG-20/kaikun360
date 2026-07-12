<?php

namespace App\Support\Payments;

/**
 * Vérification de l'authenticité des webhooks PayTech (B14.3).
 *
 * Règle de sécurité NON NÉGOCIABLE : aucune notification n'est traitée sans que
 * sa signature soit validée. PayTech signe le corps JSON brut en HMAC-SHA256
 * avec la « Signing Key » de la boutique ; on recalcule la signature et on la
 * compare en temps constant (`hash_equals`) pour éviter les attaques temporelles.
 */
class PaytechWebhookVerifier
{
    public function __construct(private readonly ?string $signingKey)
    {
    }

    /**
     * Vrai si `$signature` correspond au HMAC-SHA256 du corps brut.
     *
     * Renvoie faux si la clé de signature n'est pas configurée ou si l'en-tête
     * de signature est absent — on refuse par défaut, jamais l'inverse.
     */
    public function verify(string $rawPayload, ?string $signature): bool
    {
        if (empty($this->signingKey) || empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $this->signingKey);

        return hash_equals($expected, $signature);
    }
}
