<?php

namespace App\Modules\Assistant\Tools\BackOffice;

use App\Models\Payment;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Assistant\Contracts\ProvidesInputSchema;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;

/**
 * « Ce règlement est-il passé ? » (phase F10.3).
 *
 * La question qui arrive par téléphone, toujours avec une référence à la main :
 * un client dit avoir payé, la réservation dit le contraire, et il faut trancher
 * tout de suite. L'écran Paiements sait répondre depuis B14.4 — encore
 * faut-il y arriver, filtrer, et reconnaître la bonne ligne.
 *
 * ── Lecture seule, et ici la règle a un coût réel ───────────────────────────
 * ⚠️ **Cet outil ne confirme ni ne rembourse rien**, alors que ce sont
 * précisément les deux gestes qu'on voudrait enchaîner après l'avoir consulté.
 * C'est délibéré et ce n'est pas négociable : confirmer crédite une réservation
 * jamais payée, rembourser sort de l'argent réel de la trésorerie de Kaikun.
 * La fiche du paiement (F8.2.d) réunit les éléments de PREUVE — signature
 * vérifiée, référence PSP, échéancier complet, journal — que l'agent doit avoir
 * sous les yeux avant de trancher. Une bulle de discussion ne les remplace pas,
 * et un bouton qui court-circuiterait cette lecture serait une régression de
 * sécurité, pas un gain de temps.
 *
 * ── Ce qui ne sort pas ──────────────────────────────────────────────────────
 * ⚠️ Ni `signature_verified`, ni `meta`, ni la preuve Wave/OM. Ce sont des
 * données de CONTRÔLE, que `PaymentResource` n'expose déjà pas (elle sert aussi
 * l'espace client) et que la fiche construit derrière `gerer:paiements`. On
 * renvoie l'état, le montant, la nature et le dossier — de quoi savoir s'il faut
 * ouvrir la fiche, pas de quoi s'en passer.
 */
class PaymentLookupTool extends BackOfficeTool implements ProvidesInputSchema
{
    /**
     * Longueur minimale de la référence cherchée.
     *
     * Les références internes ont la forme `PAY-XXXXXXXX` : trois caractères
     * suffisent à écarter la recherche vide sans imposer de citer la référence
     * entière (au téléphone, on lit souvent les derniers caractères).
     */
    private const MIN_REFERENCE = 3;

    public function name(): string
    {
        return 'suivre_paiement';
    }

    public function description(): string
    {
        return 'Retrouve un règlement à partir de sa référence interne (PAY-…) ou de la référence '
            .'de la transaction chez le prestataire de paiement, et renvoie son statut, son montant, '
            .'sa nature (intégral, acompte, solde) et la réservation concernée. À utiliser quand un '
            .'membre de l\'équipe demande si un paiement est passé, où en est une transaction ou '
            .'ce qu\'a payé un client. Paramètre obligatoire : `reference`. '
            .'⚠️ Lecture seule : cet outil ne confirme et ne rembourse RIEN.';
    }

    /**
     * Paramètres offerts au modèle (F10.4).
     *
     * ⚠️ La consigne « recopier la référence telle quelle » n'est pas
     * décorative : une référence interne comporte DEUX tirets
     * (`PAY-ACPT-6YRYXV`), et c'est un motif à segment unique qui l'avait
     * tronquée en F10.3 côté déterministe. Un modèle qui « normalise » en
     * majuscules, coupe un segment ou retire les tirets produirait exactement
     * la même panne, cette fois sans motif à corriger.
     */
    public function inputSchema(): array
    {
        return [
            'properties' => [
                'reference' => [
                    'type' => 'string',
                    'description' => 'Référence du règlement, recopiée EXACTEMENT telle que la '
                        ."personne l'a écrite, tirets compris (référence interne « PAY-ACPT-6YRYXV » "
                        .'ou référence du prestataire de paiement). Ne jamais la reformater ni la tronquer.',
                ],
            ],
            'required' => ['reference'],
        ];
    }

    protected function permission(): AdminPermission
    {
        return AdminPermission::GERER_PAIEMENTS;
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $url = $this->boUrl('paiements');
        $reference = trim((string) ($input['reference'] ?? ''));

        if (mb_strlen($reference) < self::MIN_REFERENCE) {
            return $this->nothing(
                'Donnez-moi la référence du règlement (PAY-…) ou celle de la transaction.',
                'Ouvrir l\'écran Paiements',
                $url,
            );
        }

        // Cloisonnement RECOPIÉ de `AdminPaymentController::index` : les deux
        // mêmes colonnes de référence. Un agent cite tantôt la nôtre (sur la
        // facture), tantôt celle du SMS Wave — ne chercher que sur l'une
        // donnerait un « introuvable » sur un paiement bien présent.
        $motif = '%'.$reference.'%';

        $reglements = Payment::query()
            ->where(fn ($requete) => $requete->where('reference', 'like', $motif)
                ->orWhere('provider_reference', 'like', $motif))
            ->with('booking')
            ->latest()
            ->limit($this->limit())
            ->get();

        if ($reglements->isEmpty()) {
            return $this->nothing(
                'Aucun règlement ne porte la référence « '.$reference.' ».',
                'Chercher dans les paiements',
                $url,
            );
        }

        return new ToolResult(
            summary: $reglements->count() === 1
                ? 'Voici le règlement « '.$reference.' » :'
                : $reglements->count().' règlements correspondent à « '.$reference.' » :',
            items: $reglements->map(fn (Payment $reglement) => array_filter([
                'titre' => $reglement->kind?->label() ?? 'Règlement',
                'statut' => $reglement->status?->label(),
                'detail' => $this->dossier($reglement),
                'montant' => $this->money($reglement->amount_xof),
                'reference' => $reglement->reference,
                'url' => $url.'/'.$reglement->id,
            ], fn ($valeur) => $valeur !== null))->all(),
            actions: [AssistantAction::link('Ouvrir l\'écran Paiements', $url)],
        );
    }

    /**
     * Le dossier que ce règlement paie.
     *
     * On cite la RÉSERVATION, jamais le client : identifier le payeur est le
     * travail de `rechercher_compte`, gardé par une autre permission. Un agent
     * chargé des paiements n'a pas besoin de l'annuaire pour faire son métier,
     * et le grant pur de F7.1.b n'aurait aucun sens si un outil recomposait par
     * la bande ce qu'une autre permission protège.
     */
    private function dossier(Payment $reglement): ?string
    {
        $reservation = $reglement->booking;

        if ($reservation === null) {
            return null;
        }

        return 'Réservation '.$reservation->reference;
    }
}
