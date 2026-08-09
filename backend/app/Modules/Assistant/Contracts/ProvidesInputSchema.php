<?php

namespace App\Modules\Assistant\Contracts;

/**
 * Outil qui accepte des PARAMÈTRES, et sait les décrire (phase F10.4).
 *
 * Interface volontairement SÉPARÉE de `AssistantTool`, et non fusionnée avec
 * elle : sur les 14 outils du module, 11 ne prennent aucun paramètre (« mes
 * réservations », « la file de validation »… n'ont rien à recevoir, le
 * contexte suffit). Les obliger tous à déclarer un schéma vide aurait ajouté
 * une méthode inerte à onze fichiers pour n'en servir que trois.
 *
 * ── Pourquoi ce schéma existe seulement maintenant ──────────────────────────
 * Le cerveau déterministe (F10.0) extrait les paramètres du message par
 * heuristique : il devine l'univers d'une recherche à partir de mots-clés, une
 * référence de paiement à partir d'un motif. C'est lui qui remplit `$input`, il
 * sait donc déjà ce qu'il y met.
 *
 * `ClaudeBrain` (F10.4) inverse la charge : c'est le MODÈLE qui remplit
 * `$input`, et il ne peut le faire que si on lui décrit la forme attendue. Ce
 * schéma est cette description — un JSON Schema, envoyé tel quel à l'API.
 *
 * ⚠️ Le schéma n'est PAS une validation. Un modèle peut renvoyer un champ hors
 * énumération ou omettre un champ requis ; les outils continuent donc de
 * vérifier eux-mêmes ce qu'ils reçoivent (`SearchCatalogTool` refuse un univers
 * inconnu, `PaymentLookupTool` une référence vide). Le schéma réduit les
 * erreurs, il ne remplace aucun garde-fou.
 */
interface ProvidesInputSchema
{
    /**
     * Forme des paramètres acceptés, au format JSON Schema (draft 2020-12).
     *
     * Seules les clés `properties` et `required` sont attendues : le type
     * racine est toujours `object`, imposé par l'API.
     *
     * @return array{properties: array<string, mixed>, required?: array<int, string>}
     */
    public function inputSchema(): array;
}
