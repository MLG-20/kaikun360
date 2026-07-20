import { Observable } from 'rxjs';

import { Paginated } from '../../../core/api/pagination.model';
import { CatalogService } from '../../../core/api/catalog.service';
import { Experience } from '../../../models/experience.model';
import { FavoritableRef, FavoritableType } from '../../../models/favorite.model';
import { MobilityService } from '../../../models/mobility-service.model';
import { Property } from '../../../models/property.model';
import { Stay } from '../../../models/stay.model';
import { Vehicle } from '../../../models/vehicle.model';

/**
 * Configuration du catalogue par UNIVERS (F2.1).
 *
 * Le composant `app-catalog` est générique : c'est ce registre qui décrit, pour
 * chaque univers, comment charger les données (`fetch`), quels filtres afficher
 * (`filters`), et comment transformer un élément d'API en carte d'affichage
 * (`toCard`). Ajouter un univers = ajouter une entrée ici, sans toucher au
 * composant. Les clés de filtre correspondent 1:1 aux paramètres validés par les
 * contrôleurs de catalogue du backend.
 */
export type Universe = 'immobilier' | 'nuitees' | 'transport' | 'tourisme' | 'mobilite';

/** Type de contrôle de filtre à rendre dans la barre de filtres. */
export type FilterKind = 'text' | 'number' | 'select' | 'boolean' | 'date';

/** Descripteur d'un champ de filtre (clé = paramètre backend). */
export interface FilterField {
  key: string;
  label: string;
  kind: FilterKind;
  placeholder?: string;
  /** Options pour les champs `select` (valeur envoyée + libellé affiché). */
  options?: { value: string; label: string }[];
}

/** Valeurs de filtre transmises au service (déjà nettoyées des vides). */
export type FilterValues = Record<string, string | number | boolean>;

/** Modèle de vue d'une carte, aligné sur les `input()` de `app-listing-card`. */
export interface CatalogCard {
  id: number;
  title: string;
  location: string | null;
  price: string | null;
  priceUnit: string | null;
  badge: string | null;
  image: string | null;
  /**
   * Cible `routerLink` de la fiche détaillée (ex. `['/immobilier', 12]`).
   * `null` = carte non cliquable (univers dont la fiche n'existe pas encore).
   */
  link: (string | number)[] | null;
  /**
   * Référence favorisable ({ type, id }) : active le cœur de favori sur la carte.
   * `null` = pas de favori possible pour cet élément.
   */
  favoritable: FavoritableRef | null;
}

/** Correspondance type favorisable → univers de catalogue (réutilise `toCard`). */
export const UNIVERSE_FOR_FAVORITABLE: Record<FavoritableType, Universe> = {
  property: 'immobilier',
  stay: 'nuitees',
  vehicle: 'transport',
  experience: 'tourisme',
  mobility: 'mobilite',
};

/** Contrat d'un univers de catalogue. */
export interface UniverseConfig {
  key: Universe;
  label: string;
  /** Tri disponible côté backend (faux = ordre fixe imposé par l'API). */
  hasSort: boolean;
  filters: FilterField[];
  fetch(service: CatalogService, filters: FilterValues): Observable<Paginated<unknown>>;
  toCard(item: unknown): CatalogCard;
}

/** Formate un montant FCFA en entier lisible : 150000 → « 150 000 F ». */
export function formatFcfa(value: number | null | undefined): string | null {
  if (value === null || value === undefined) {
    return null;
  }
  // Espace fine insécable comme séparateur de milliers (convention FR).
  return `${value.toLocaleString('fr-FR').replace(/ /g, ' ')} F`;
}

/** Assemble une localisation lisible à partir de fragments (ignore les vides). */
function joinLocation(...parts: (string | null | undefined)[]): string | null {
  const kept = parts.filter((p): p is string => !!p);
  return kept.length ? kept.join(' · ') : null;
}

/**
 * Renvoie le libellé de badge « Vérifié » uniquement si le niveau de
 * vérification est réellement positif. La valeur par défaut backend
 * (`unverified`) ne doit PAS afficher de badge.
 */
function verifiedBadge(level: string | null | undefined): string | null {
  return level && level !== 'unverified' && level !== 'aucun' ? 'Vérifié' : null;
}

/** Options de tri communes aux univers qui le supportent. */
export const SORT_OPTIONS: { value: string; label: string }[] = [
  { value: 'recent', label: 'Plus récents' },
  { value: 'price_asc', label: 'Prix croissant' },
  { value: 'price_desc', label: 'Prix décroissant' },
];

const PROPERTY_TYPES: { value: string; label: string }[] = [
  { value: 'appartement', label: 'Appartement' },
  { value: 'maison', label: 'Maison' },
  { value: 'villa', label: 'Villa' },
  { value: 'studio', label: 'Studio' },
  { value: 'terrain', label: 'Terrain' },
  { value: 'bureau', label: 'Bureau' },
  { value: 'commerce', label: 'Local commercial' },
  { value: 'autre', label: 'Autre' },
];

const VEHICLE_TYPES: { value: string; label: string }[] = [
  { value: 'voiture_particuliere', label: 'Voiture particulière' },
  { value: 'voiture_touristique', label: 'Voiture touristique' },
  { value: 'navette_aibd', label: 'Navette aéroportuaire (AIBD)' },
  { value: 'bus', label: 'Bus' },
  { value: 'minibus', label: 'Minibus' },
  { value: 'quatre_quatre', label: '4x4' },
  { value: 'pirogue', label: 'Pirogue' },
  { value: 'chauffeur', label: 'Chauffeur' },
];

const MOBILITY_TYPES: { value: string; label: string }[] = [
  { value: 'navette', label: 'Navette' },
  { value: 'transfert', label: 'Transfert' },
  { value: 'liaison', label: 'Liaison interurbaine' },
  { value: 'excursion', label: 'Excursion' },
];

/** Registre des univers de catalogue, indexé par clé d'URL. */
export const UNIVERSES: Record<Universe, UniverseConfig> = {
  immobilier: {
    key: 'immobilier',
    label: 'Immobilier',
    hasSort: true,
    filters: [
      { key: 'type', label: 'Type de bien', kind: 'select', options: PROPERTY_TYPES },
      { key: 'price_min', label: 'Prix min', kind: 'number', placeholder: 'FCFA' },
      { key: 'price_max', label: 'Prix max', kind: 'number', placeholder: 'FCFA' },
      { key: 'q', label: 'Mots-clés', kind: 'text', placeholder: 'Titre du bien…' },
    ],
    fetch: (service, filters) => service.properties(filters),
    toCard: (item) => {
      const p = item as Property;
      return {
        id: p.id,
        title: p.title,
        location: joinLocation(p.location?.commune, p.location?.region),
        price: formatFcfa(p.price_xof),
        priceUnit: null,
        badge: verifiedBadge(p.verification_level),
        // Photo de couverture du bien (principale). `null` → vignette dégradée.
        image: p.photo_url ?? null,
        link: ['/immobilier', p.id],
        favoritable: { type: 'property', id: p.id },
      };
    },
  },

  nuitees: {
    key: 'nuitees',
    label: 'Nuitées',
    hasSort: false,
    filters: [
      { key: 'capacity', label: 'Voyageurs', kind: 'number', placeholder: 'Nb' },
      { key: 'price_min', label: 'Prix min / nuit', kind: 'number', placeholder: 'FCFA' },
      { key: 'price_max', label: 'Prix max / nuit', kind: 'number', placeholder: 'FCFA' },
      { key: 'q', label: 'Mots-clés', kind: 'text', placeholder: 'Titre du bien…' },
    ],
    fetch: (service, filters) => service.stays(filters),
    toCard: (item) => {
      const s = item as Stay;
      return {
        id: s.id,
        title: s.property?.title ?? 'Nuitée',
        location: joinLocation(s.property?.location?.commune, s.property?.location?.region),
        price: formatFcfa(s.price_per_night_xof),
        priceUnit: '/ nuit',
        badge: verifiedBadge(s.property?.verification_level),
        // Une nuitée est illustrée par les photos de SON bien.
        image: s.property?.photo_url ?? null,
        link: ['/nuitees', s.id],
        favoritable: { type: 'stay', id: s.id },
      };
    },
  },

  transport: {
    key: 'transport',
    label: 'Transport',
    hasSort: true,
    filters: [
      { key: 'type', label: 'Type de véhicule', kind: 'select', options: VEHICLE_TYPES },
      { key: 'capacity_min', label: 'Places min', kind: 'number', placeholder: 'Nb' },
      { key: 'price_max', label: 'Prix max / jour', kind: 'number', placeholder: 'FCFA' },
      { key: 'has_driver', label: 'Avec chauffeur', kind: 'boolean' },
      { key: 'q', label: 'Marque', kind: 'text', placeholder: 'Toyota…' },
    ],
    fetch: (service, filters) => service.vehicles(filters),
    toCard: (item) => {
      const v = item as Vehicle;
      const name = [v.brand, v.model].filter(Boolean).join(' ');
      return {
        id: v.id,
        title: name || v.type_label || 'Véhicule',
        location: joinLocation(v.type_label, `${v.capacity} places`),
        price: formatFcfa(v.price_per_day_xof),
        priceUnit: '/ jour',
        badge: v.has_driver ? 'Avec chauffeur' : null,
        image: null,
        link: ['/transport', v.id],
        favoritable: { type: 'vehicle', id: v.id },
      };
    },
  },

  tourisme: {
    key: 'tourisme',
    label: 'Tourisme',
    hasSort: true,
    filters: [
      { key: 'destination', label: 'Destination', kind: 'text', placeholder: 'Saly…' },
      { key: 'duration_max', label: 'Durée max (jours)', kind: 'number', placeholder: 'Nb' },
      { key: 'price_min', label: 'Prix min', kind: 'number', placeholder: 'FCFA' },
      { key: 'price_max', label: 'Prix max', kind: 'number', placeholder: 'FCFA' },
      { key: 'q', label: 'Mots-clés', kind: 'text', placeholder: 'Titre…' },
    ],
    fetch: (service, filters) => service.experiences(filters),
    toCard: (item) => {
      const e = item as Experience;
      return {
        id: e.id,
        title: e.title,
        location: joinLocation(e.destination, e.duration_days ? `${e.duration_days} j` : null),
        price: formatFcfa(e.price_xof),
        priceUnit: '/ pers.',
        badge: null,
        image: null,
        link: ['/tourisme', e.id],
        favoritable: { type: 'experience', id: e.id },
      };
    },
  },

  mobilite: {
    key: 'mobilite',
    label: 'Mobilité',
    hasSort: false,
    filters: [
      { key: 'type', label: 'Type de service', kind: 'select', options: MOBILITY_TYPES },
      { key: 'departure', label: 'Départ', kind: 'text', placeholder: 'Dakar…' },
      { key: 'destination', label: 'Destination', kind: 'text', placeholder: 'Saly…' },
      { key: 'date', label: 'Date', kind: 'date' },
    ],
    fetch: (service, filters) => service.mobilityServices(filters),
    toCard: (item) => {
      const m = item as MobilityService;
      return {
        id: m.id,
        title: m.type_label ?? 'Trajet',
        location: joinLocation(`${m.departure} → ${m.destination}`),
        price: formatFcfa(m.price_xof),
        priceUnit: '/ trajet',
        badge: null,
        image: null,
        // Pas d'endpoint de détail côté backend pour un service de mobilité
        // (index + réservation seulement) → carte non cliquable, réservation
        // gérée depuis la vitrine `/mobilite`.
        link: null,
        favoritable: { type: 'mobility', id: m.id },
      };
    },
  },
};
