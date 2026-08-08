import { environment } from '../../../environments/environment';

/**
 * Constructeurs de **données structurées** schema.org (F9.1).
 *
 * ## À quoi ça sert, concrètement
 *
 * Une balise `<meta>` dit à un robot *comment présenter un lien*. Le JSON-LD lui
 * dit *ce que la page contient* : que ceci est une offre, à tel prix, en telle
 * devise, dans telle ville. C'est ce qui produit les « résultats enrichis » —
 * prix et note affichés directement dans la page de résultats — et ce qui rend
 * une offre lisible par un agrégateur.
 *
 * ## Deux règles à ne pas enfreindre
 *
 * ⚠️ **Ne jamais décrire ce que la page n'affiche pas.** Google traite l'écart
 * entre les données structurées et le contenu visible comme une manipulation, et
 * la sanction porte sur tout le domaine. D'où l'absence délibérée
 * d'`aggregateRating` ici : les avis existent (B12.2) mais aucune fiche publique
 * n'affiche encore de note moyenne. À rebrancher **le jour où l'écran le
 * montre**, pas avant.
 *
 * ⚠️ **La devise est le franc CFA (`XOF`)** et les montants du projet sont des
 * entiers — aucun centime. Déclarer `EUR` ou diviser par 100 « pour faire
 * propre » afficherait des prix faux dans Google.
 */

/** Racine publique du site, sans barre oblique finale. */
const SITE = environment.siteUrl.replace(/\/+$/, '');

/** Rend une URL absolue à partir d'un chemin applicatif. */
export function urlAbsolue(chemin: string): string {
  if (/^https?:\/\//i.test(chemin)) {
    return chemin;
  }
  return `${SITE}${chemin.startsWith('/') ? '' : '/'}${chemin}`;
}

/**
 * L'entreprise (`Organization`).
 *
 * Posé sur l'accueil uniquement : répété sur chaque page, il n'apporte rien et
 * alourdit le document. Il alimente le « panneau de connaissance » de Google
 * (logo, coordonnées) — ce qui suppose que les valeurs soient **exactes**.
 */
export function schemaOrganisation(): object {
  return {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'Kaikun 360',
    url: SITE,
    logo: urlAbsolue('/icons/icon-512.png'),
    description:
      'Plateforme sénégalaise réunissant immobilier, construction, gestion locative, nuitées, tourisme et mobilité.',
    address: {
      '@type': 'PostalAddress',
      addressLocality: 'Dakar',
      addressCountry: 'SN',
    },
    areaServed: { '@type': 'Country', name: 'Sénégal' },
  };
}

/**
 * Le site et son moteur de recherche (`WebSite` + `SearchAction`).
 *
 * ⚠️ Le gabarit doit correspondre à une URL que le site sait **réellement**
 * servir : `/recherche?q=…` est branché sur le moteur depuis F2.1. Un gabarit
 * qui mènerait à une page vide ferait plus de mal que son absence.
 */
export function schemaSite(): object {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: 'Kaikun 360',
    url: SITE,
    inLanguage: 'fr-SN',
    potentialAction: {
      '@type': 'SearchAction',
      target: {
        '@type': 'EntryPoint',
        urlTemplate: `${SITE}/recherche?q={search_term_string}`,
      },
      'query-input': 'required name=search_term_string',
    },
  };
}

/** Une étape du fil d'Ariane. */
export interface EtapeAriane {
  nom: string;
  chemin: string;
}

/**
 * Fil d'Ariane (`BreadcrumbList`).
 *
 * Remplace l'URL brute par un chemin lisible sous le résultat Google
 * (« Kaikun 360 › Immobilier › Villa à Ngor »). Les étapes doivent refléter la
 * navigation réelle du site, pas une hiérarchie inventée pour l'occasion.
 */
export function schemaFilAriane(etapes: readonly EtapeAriane[]): object {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: etapes.map((etape, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name: etape.nom,
      item: urlAbsolue(etape.chemin),
    })),
  };
}

/** Description d'une offre du catalogue, quel que soit l'univers. */
export interface OffreStructuree {
  nom: string;
  description: string | null;
  /** URL absolue de la photo de couverture, ou `null`. */
  image?: string | null;
  /** Chemin applicatif de la fiche (ex. `/immobilier/12`). */
  chemin: string;
  /** Montant en FCFA, entier. `null` si le prix se négocie (sur-mesure). */
  prixXof?: number | null;
  /**
   * Unité du prix, telle qu'elle est **affichée sur la fiche** : `nuit`, `jour`,
   * `personne`, `place`… `undefined` pour un prix global (un bien à la vente).
   */
  unite?: string;
  /** Ville ou commune, si la fiche l'affiche. */
  lieu?: string | null;
  /** Offre encore réservable (`false` pour un départ passé, un bien retiré). */
  disponible?: boolean;
}

/**
 * Une offre du catalogue (`Product` + `Offer`).
 *
 * ⚠️ `Product` et pas `RealEstateListing`/`Vehicle`/`TouristTrip` : les cinq
 * univers passent par ce seul constructeur. Les types spécialisés exigent des
 * propriétés que le projet ne collecte pas toutes (surface habitable certifiée,
 * numéro de série d'un véhicule) et un type mal renseigné est refusé en bloc,
 * là où un `Product` complet est toujours accepté. À spécialiser univers par
 * univers le jour où les données existent.
 */
export function schemaOffre(offre: OffreStructuree): object {
  const url = urlAbsolue(offre.chemin);
  const schema: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: offre.nom,
    url,
  };

  if (offre.description) {
    schema['description'] = offre.description.replace(/\s+/g, ' ').trim();
  }
  if (offre.image) {
    schema['image'] = urlAbsolue(offre.image);
  }
  if (offre.lieu) {
    // `Product` n'a pas de champ de lieu : la ville passe par une propriété
    // additionnelle, que Google lit et affiche.
    schema['additionalProperty'] = {
      '@type': 'PropertyValue',
      name: 'Localisation',
      value: offre.lieu,
    };
  }

  // ⚠️ Un `Offer` sans prix est une donnée structurée invalide. Une offre au
  // prix négocié n'en porte donc **pas** — mieux vaut un schéma partiel et
  // valide qu'un schéma complet et rejeté.
  if (offre.prixXof != null) {
    schema['offers'] = {
      '@type': 'Offer',
      price: offre.prixXof,
      priceCurrency: 'XOF',
      url,
      availability: `https://schema.org/${
        offre.disponible === false ? 'SoldOut' : 'InStock'
      }`,
      ...(offre.unite ? { description: `Prix par ${offre.unite}` } : {}),
    };
  }

  return schema;
}
