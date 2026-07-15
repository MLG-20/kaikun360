// Modèles du contenu éditorial public (F2.8), miroir des Resources backend
// FaqResource et PageResource (module Admin, lecture publique B13.4).

/**
 * Entrée de FAQ publiée (miroir de `FaqResource`).
 *
 * Servie par `GET /faqs` (uniquement les entrées publiées). `category` permet
 * de regrouper les questions ; `position` donne l'ordre d'affichage voulu par
 * l'équipe éditoriale.
 */
export interface Faq {
  id: number;
  question: string;
  answer: string;
  category: string | null;
  position: number;
  is_published: boolean;
  updated_at: string | null;
}

/**
 * Page de contenu éditorial adressée par slug (miroir de `PageResource`).
 *
 * Servie par `GET /pages/{slug}` (pages publiées ; 404 sinon). Le champ `body`
 * est un fragment HTML rendu côté frontend via `[innerHTML]` (Angular assainit
 * automatiquement le balisage — scripts et attributs dangereux retirés).
 */
export interface ContentPage {
  id: number;
  slug: string;
  title: string;
  body: string;
  is_published: boolean;
  updated_at: string | null;
}
