/**
 * Formatage d'un montant en francs CFA — **la seule écriture d'un prix du site**.
 *
 * Extrait de `shared/components/catalog/catalog.config.ts` en F10.1, où il
 * vivait depuis F2.1. La raison n'est pas cosmétique : ce fichier de
 * configuration importe `CatalogService` et le registre des cinq univers, et il
 * n'est chargé que par les pages de catalogue (paresseuses). En l'important
 * depuis un composant monté dans un *layout* — comme le panneau de l'assistant —
 * on aurait tiré tout ce registre dans le **paquet initial**, chargé par
 * quiconque ouvre la page d'accueil, pour une fonction de trois lignes.
 *
 * `catalog.config.ts` la réexporte : aucun appelant existant n'a changé.
 */
export function formatFcfa(value: number | null | undefined): string | null {
  if (value === null || value === undefined) {
    return null;
  }

  // `toLocaleString('fr-FR')` sépare les milliers par une espace INSÉCABLE
  // (U+00A0) ; on la remplace par une espace fine insécable (U+202F), la
  // convention typographique française. ⚠️ Ces deux caractères sont invisibles
  // à la relecture : les retaper à la main casse silencieusement le rendu.
  return `${value.toLocaleString('fr-FR').replace(/ /g, ' ')} F`;
}
