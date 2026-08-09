<?php

namespace Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Faux transporteur HTTP pour éprouver `ClaudeBrain` (phase F10.4).
 *
 * Le SDK Anthropic délègue l'envoi à un client PSR-18 injectable. On y pose
 * celui-ci, et la suite de tests devient hermétique : **aucun appel réseau,
 * aucune clé, aucun coût**, et surtout des réponses de modèle reproductibles.
 *
 * C'est ce qui rend testable ce qui, autrement, ne le serait pas : la façon
 * dont le cerveau réagit à un appel d'outil inventé, à une réponse vide, à un
 * fournisseur en panne. Ces cas-là ne se provoquent pas à la demande sur une
 * vraie API — ici, ils se scriptent.
 *
 * ⚠️ Il enregistre aussi les requêtes ENVOYÉES. C'est la moitié la plus utile :
 * vérifier que l'historique part bien au modèle, que la trousse d'outils
 * transmise est celle du rôle, et que les adresses des fiches ne sortent
 * jamais du serveur, ne peut se faire qu'en inspectant ce qui a été émis.
 */
class FakeAnthropicTransport implements ClientInterface
{
    /**
     * Réponses à servir, dans l'ordre.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $queue = [];

    /**
     * Corps des requêtes reçues, décodés.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $requests = [];

    /**
     * Si vrai, toute requête échoue — pour éprouver le repli.
     */
    private bool $broken = false;

    /**
     * Empile une réponse du modèle : du texte, et c'est fini.
     */
    public function willAnswer(string $text): self
    {
        return $this->willReturn([['type' => 'text', 'text' => $text]], 'end_turn');
    }

    /**
     * Empile une demande d'outil de la part du modèle.
     *
     * @param  array<string, mixed>  $input
     */
    public function willCallTool(string $name, array $input = [], string $id = 'toolu_test'): self
    {
        return $this->willReturn([
            ['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => (object) $input],
        ], 'tool_use');
    }

    /**
     * Empile une réponse sans aucun texte (plafond de tokens atteint, refus…).
     */
    public function willAnswerNothing(): self
    {
        return $this->willReturn([], 'max_tokens');
    }

    /**
     * @param  array<int, array<string, mixed>>  $content
     */
    public function willReturn(array $content, string $stopReason): self
    {
        $this->queue[] = [
            'id' => 'msg_'.count($this->queue),
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-haiku-4-5',
            'content' => $content,
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
        ];

        return $this;
    }

    /**
     * Le fournisseur ne répond plus.
     */
    public function willFail(): self
    {
        $this->broken = true;

        return $this;
    }

    /**
     * Nombre d'appels réellement émis vers l'API.
     *
     * Sert à vérifier le plafond de tours : sans borne, un modèle qui boucle
     * sur un outil vide facturerait indéfiniment.
     */
    public function callCount(): int
    {
        return count($this->requests);
    }

    /**
     * Corps de la dernière requête émise.
     *
     * @return array<string, mixed>
     */
    public function lastRequest(): array
    {
        return $this->requests[array_key_last($this->requests)] ?? [];
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = json_decode((string) $request->getBody(), true) ?: [];

        if ($this->broken) {
            throw new RuntimeException('Fournisseur indisponible (simulé).');
        }

        // File épuisée : on répond une phrase neutre plutôt que d'échouer, pour
        // qu'un test qui compte les tours n'échoue pas pour la mauvaise raison.
        $payload = array_shift($this->queue) ?? [
            'id' => 'msg_fin',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-haiku-4-5',
            'content' => [['type' => 'text', 'text' => 'Terminé.']],
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
        ];

        return new Response(200, ['Content-Type' => 'application/json'], json_encode($payload));
    }
}
