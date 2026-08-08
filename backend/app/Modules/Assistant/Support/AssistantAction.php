<?php

namespace App\Modules\Assistant\Support;

/**
 * Geste PROPOSÉ à l'utilisateur par l'assistant (phase F10.0).
 *
 * Principe de conception central : **l'assistant ne fait pas, il propose**.
 * Plutôt que d'écrire lui-même en base, il renvoie une action que le panneau
 * Angular affiche sous forme de bouton ; c'est le clic de l'utilisateur qui
 * déclenche l'appel à l'endpoint métier existant, déjà validé et déjà testé.
 *
 * Trois bénéfices, tous liés à la sécurité :
 *   - aucune logique métier n'est dupliquée dans le module Assistant ;
 *   - une erreur de compréhension de l'assistant ne produit jamais d'écriture,
 *     seulement un bouton que l'utilisateur peut ignorer ;
 *   - l'action passe par le contrôleur d'origine, donc par ses Form Requests
 *     et ses policies — l'assistant ne contourne rien.
 */
final class AssistantAction
{
    /**
     * @param  string  $kind     Nature du geste, interprétée par le frontend :
     *                           `link` (naviguer), `support` (ouvrir un fil),
     *                           `contact` (formulaire public de contact).
     * @param  string  $label    Libellé du bouton, en français.
     * @param  array   $payload  Données du geste (url, filtres, sujet…).
     */
    private function __construct(
        public readonly string $kind,
        public readonly string $label,
        public readonly array $payload = [],
    ) {}

    /**
     * Navigation vers une page de la plateforme (lien profond).
     *
     * `$url` est TOUJOURS un chemin interne (« /immobilier?… ») construit par
     * nos soins, jamais une URL fournie par l'utilisateur ou par un modèle :
     * un lien externe sortant d'un assistant est un vecteur d'hameçonnage.
     */
    public static function link(string $label, string $url): self
    {
        return new self('link', $label, ['url' => $url]);
    }

    /**
     * Ouverture d'un fil de support.
     *
     * L'action ne crée rien : le frontend appellera `POST /api/v1/messages/support`
     * (MessageController::startWithSupport), qui choisit l'agent de permanence,
     * reprend un fil existant au lieu d'en empiler un second, et rouvre un fil
     * clos. Toute cette logique reste à son unique endroit.
     *
     * ⚠️ La charge utile respecte le contrat de `StartSupportConversationRequest` :
     * `body` y est OBLIGATOIRE, `subject` facultatif. Ne transmettre que le sujet
     * ferait échouer l'appel en validation (422) au moment du clic — l'erreur
     * n'apparaîtrait qu'en bout de chaîne, côté utilisateur.
     */
    public static function support(string $label, string $subject, string $body): self
    {
        return new self('support', $label, [
            'subject' => $subject,
            'body' => $body,
        ]);
    }

    /**
     * Renvoi vers le formulaire de contact public (visiteur non connecté, qui
     * ne peut pas ouvrir de fil de messagerie faute de compte).
     */
    public static function contact(string $label): self
    {
        return new self('contact', $label);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'label' => $this->label,
            'payload' => $this->payload,
        ];
    }
}
