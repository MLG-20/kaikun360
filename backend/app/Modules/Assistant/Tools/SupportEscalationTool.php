<?php

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Contracts\AssistantTool;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;
use App\Support\Mail\SpaceLink;

/**
 * Passage de relais à un humain (phase F10.0).
 *
 * Un assistant sans porte de sortie est un piège : l'utilisateur tourne en
 * rond et finit par quitter le site. Cet outil est la sortie.
 *
 * ── Il n'écrit RIEN ─────────────────────────────────────────────────────────
 * Décision de conception importante : l'outil ne crée pas la conversation. Il
 * renvoie une ACTION que le panneau affiche en bouton ; c'est le clic de
 * l'utilisateur qui appelle `POST /api/v1/messages/support`
 * (MessageController::startWithSupport), lequel sait déjà :
 *   - choisir l'agent de permanence,
 *   - reprendre le fil ouvert sur le même dossier au lieu d'en empiler un second,
 *   - rouvrir un fil clos.
 *
 * Réimplémenter tout cela ici, c'était garantir la divergence des deux
 * chemins à la première évolution — et créer un second endroit où une erreur
 * d'autorisation peut se glisser. Ici, l'assistant ne fait que montrer la porte.
 *
 * Corollaire : une mauvaise compréhension de l'assistant ne produit jamais
 * qu'un bouton inutile, jamais un fil de support fantôme en base.
 */
class SupportEscalationTool implements AssistantTool
{
    public function name(): string
    {
        return 'contacter_support';
    }

    public function description(): string
    {
        return "Propose à la personne de joindre l'équipe Kaikun 360. À utiliser lorsque la demande "
            ."sort de ce que les autres outils savent traiter, lorsqu'elle concerne un dossier "
            ."personnel en cours, ou lorsque la personne demande explicitement à parler à quelqu'un. "
            .'Paramètre facultatif : `sujet`.';
    }

    /**
     * Ouvert à tous, mais l'issue proposée diffère : un compte permet d'ouvrir
     * un fil de messagerie suivi ; un visiteur anonyme n'en a pas, on le
     * dirige donc vers le formulaire de contact public.
     */
    public function isAvailableFor(AssistantContext $context): bool
    {
        return true;
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $raw = is_string($input['sujet'] ?? null) ? trim($input['sujet']) : '';
        $subject = $this->cleanSubject($raw);

        // Le message d'origine devient le CORPS du futur fil : l'agent doit
        // lire ce que la personne a réellement écrit, pas un sujet tronqué.
        // `body` est obligatoire côté StartSupportConversationRequest.
        $body = $raw !== '' ? $raw : 'Demande transmise depuis l\'assistant Kaikun.';

        if ($context->isAuthenticated()) {
            return ToolResult::empty(
                'Je préfère passer la main à un conseiller Kaikun sur ce point. '
                .'Voulez-vous que je lui ouvre un fil de discussion ?',
                [
                    AssistantAction::support('Écrire à un conseiller', $subject, $body),
                    // ⚠️ Le site a QUATRE espaces connectés : « /mon-espace » en dur
                    // (état de F10.0) envoyait un propriétaire, un prestataire ou une
                    // entreprise sur une adresse gardée par le rôle `client`, donc sur
                    // un refoulement. `SpaceLink` est la résolution déjà éprouvée par
                    // les 20 e-mails transactionnels (F8.8) : on la réutilise plutôt
                    // que d'en écrire une seconde, vouée à diverger.
                    AssistantAction::link(
                        'Voir mes messages',
                        SpaceLink::to($context->user, 'messages'),
                    ),
                ],
            );
        }

        return ToolResult::empty(
            'Je préfère passer la main à un conseiller Kaikun sur ce point. '
            .'Laissez-nous un message et l\'équipe vous répondra.',
            [
                AssistantAction::contact('Nous écrire'),
                AssistantAction::link('Créer un compte pour suivre vos échanges', '/auth/inscription'),
            ],
        );
    }

    /**
     * Préfixe du sujet, qui EST la journalisation de l'assistant (F10.2).
     *
     * Arbitrage produit : on ne stocke aucune conversation. Ce qui remonte au
     * back-office, ce sont les seules escalades — et elles y sont déjà, puisque
     * le fil de support porte le message d'origine dans son corps. Il manquait
     * une chose : que l'agent SACHE d'où vient la demande. Deux fils identiques,
     * l'un tapé dans la messagerie et l'autre passé par l'assistant, ne
     * s'instruisent pas pareil — le second signale au passage une question que
     * l'assistant n'a pas su traiter, donc une FAQ à compléter.
     *
     * Un préfixe de sujet suffit : aucune table, aucune donnée personnelle
     * conservée en plus, rien à purger au RGPD.
     */
    private const PREFIXE = 'Assistant — ';

    /**
     * Sujet du futur fil : préfixé, nettoyé et borné, car il sera repris tel
     * quel dans l'appel à `POST /messages/support` et affiché au back-office.
     * On refuse les sauts de ligne (un sujet reste une ligne).
     *
     * ⚠️ Le corps du fil, lui, n'est JAMAIS retouché : l'agent doit lire les
     * mots exacts de la personne. On habille l'étiquette, pas le contenu.
     */
    private function cleanSubject(mixed $value): string
    {
        $default = self::PREFIXE.'demande transmise depuis le panneau';

        if (! is_string($value)) {
            return $default;
        }

        $clean = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        // 120 caractères de message + le préfixe : on reste très en deçà des
        // 255 acceptés par StartSupportConversationRequest.
        return $clean === '' ? $default : self::PREFIXE.mb_substr($clean, 0, 120);
    }
}
