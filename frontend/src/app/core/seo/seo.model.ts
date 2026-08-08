/**
 * Types du référencement (F9.1).
 *
 * Deux objets seulement, à ne pas confondre :
 *
 *   - `RouteSeo` — ce qu'une **route** déclare *avant* d'avoir chargé quoi que
 *     ce soit (`data: { seo: … }`). C'est le texte de repli, connu à la
 *     compilation, servi immédiatement au robot.
 *   - `SeoTags` — ce qu'une **page** applique *après* avoir reçu ses données
 *     (le titre réel d'un bien, sa photo de couverture…). Il écrase le repli.
 *
 * Cette séparation existe parce qu'une navigation et un chargement de données
 * ne sont pas le même instant : sans repli de route, une fiche resterait sans
 * description le temps de l'appel HTTP — et un robot qui n'exécute pas le
 * JavaScript ne verrait jamais l'autre.
 */

/** Nature de la page au sens OpenGraph (ce que Facebook/WhatsApp affichent). */
export type SeoOgType = 'website' | 'article' | 'product' | 'profile';

/**
 * Repli déclaré par une route, dans `data: { seo: … }`.
 *
 * ⚠️ **Son absence signifie « ne pas indexer »** — voir `SeoTitleStrategy`.
 * C'est un choix de sécurité : les écrans connectés (les 4 espaces, le
 * back-office, l'authentification) sont bien plus nombreux que les pages
 * publiques, et un écran privé oublié dans l'index de Google est une fuite,
 * pas une imprécision.
 */
export interface RouteSeo {
  /**
   * Description de la page, 120 à 160 caractères environ. Au-delà, Google la
   * tronque ; en deçà, il la remplace souvent par un extrait de son choix.
   */
  description: string;

  /**
   * Page publique mais volontairement **hors index** (`false`).
   *
   * Sert aux pages publiques qui n'ont rien à apporter à un moteur : les
   * résultats de recherche filtrés (contenu dupliqué, combinatoire infinie de
   * paramètres) et les retours de paiement. Elles restent « follow » : leurs
   * liens continuent de mener le robot au catalogue.
   */
  index?: boolean;

  /** Nature OpenGraph ; `website` par défaut. */
  type?: SeoOgType;
}

/**
 * Jeu complet de balises appliqué à la page courante.
 *
 * ⚠️ Il est **toujours écrit en entier**, jamais par différence : c'est ce qui
 * garantit qu'aucune balise d'une page précédente ne survit à une navigation.
 * Une application à page unique ne recharge pas le document — sans réécriture
 * complète, la photo d'un bien resterait en `og:image` sur la page Contact.
 */
export interface SeoTags {
  /** Titre complet de l'onglet ET du partage. Le suffixe de marque est ajouté si absent. */
  title: string;
  description: string;
  /** Nature OpenGraph ; `website` par défaut. */
  type?: SeoOgType;
  /**
   * Chemin canonique (ex. `/immobilier/12`), **sans le domaine**.
   * Omis : le chemin de l'URL courante, paramètres de requête retirés.
   */
  canonicalPath?: string;
  /**
   * Image de partage. URL absolue attendue (celles de l'API le sont).
   * Omise ou nulle : l'image de marque du site.
   */
  image?: string | null;
  /** Faux pour tenir la page hors de l'index (`noindex, follow`). */
  index?: boolean;
}
