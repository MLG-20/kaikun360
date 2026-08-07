import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, of, switchMap } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Experience } from '../../models/experience.model';
import { MobilityService } from '../../models/mobility-service.model';
import { Vehicle } from '../../models/vehicle.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Type de véhicule — miroir EXACT de l'enum `VehicleType` backend (module
 * Mobility). `family` regroupe les types pour piloter les champs de conformité
 * du formulaire de dépôt :
 *   - `motorise` → assurance + identité chauffeur (voitures, navette, bus…) ;
 *   - `pirogue`  → gilets de sauvetage, conformité météo & prestataire.
 */
export type VehicleTypeValue =
  | 'voiture_particuliere'
  | 'voiture_touristique'
  | 'navette_aibd'
  | 'bus'
  | 'minibus'
  | 'quatre_quatre'
  | 'pirogue'
  | 'chauffeur';

/** Option du sélecteur de type de véhicule (valeur + libellé + famille). */
/**
 * Ce que le serveur répond au retrait d'une offre (F8.19).
 *
 * ⚠️ Deux issues, et l'écran doit dire laquelle a eu lieu : « supprimé » et
 * « retiré mais conservé pour l'historique de vos clients » ne veulent pas dire
 * la même chose pour un prestataire qui vient de cliquer.
 */
export interface OfferRemoval {
  /** Vrai si l'offre a réellement disparu ; faux si elle est seulement retirée. */
  deleted: boolean;
  /** Pourquoi elle a été conservée, `null` en cas de suppression réelle. */
  reason: string | null;
}

export interface VehicleTypeOption {
  value: VehicleTypeValue;
  label: string;
  family: 'motorise' | 'pirogue';
}

/**
 * Catalogue des types de véhicule proposés au dépôt (miroir de `VehicleType`).
 * Le CDC (§2.1 / §15) exige des catégories **distinctes** : voiture
 * particulière, voiture touristique et navette aéroportuaire notamment.
 */
export const VEHICLE_TYPES: readonly VehicleTypeOption[] = [
  { value: 'voiture_particuliere', label: 'Voiture particulière', family: 'motorise' },
  { value: 'voiture_touristique', label: 'Voiture touristique', family: 'motorise' },
  { value: 'navette_aibd', label: 'Navette aéroportuaire (AIBD)', family: 'motorise' },
  { value: 'bus', label: 'Bus', family: 'motorise' },
  { value: 'minibus', label: 'Minibus', family: 'motorise' },
  { value: 'quatre_quatre', label: '4x4', family: 'motorise' },
  { value: 'pirogue', label: 'Pirogue', family: 'pirogue' },
  { value: 'chauffeur', label: 'Chauffeur (mise à disposition)', family: 'motorise' },
];

/** Renvoie la famille (`motorise` / `pirogue`) d'un type de véhicule. */
export function vehicleFamily(type: string | null | undefined): 'motorise' | 'pirogue' | null {
  return VEHICLE_TYPES.find((t) => t.value === type)?.family ?? null;
}

/**
 * Corps de `POST /vehicles` / `PATCH /vehicles/{id}` — miroir de
 * `StoreVehicleRequest` / `UpdateVehicleRequest`. Les champs de conformité sont
 * facultatifs au dépôt mais conditionnent la **validation** back-office.
 */
export interface NewVehiclePayload {
  type: VehicleTypeValue;
  brand?: string | null;
  model?: string | null;
  capacity: number;
  price_per_day_xof: number;
  has_driver: boolean;
  caution_xof?: number | null;
  description?: string | null;
  // Conformité motorisé.
  insurance_ref?: string | null;
  driver_identity?: string | null;
  // Conformité pirogue.
  life_jackets_count?: number | null;
  weather_compliant?: boolean | null;
  provider_compliant?: boolean | null;
}

/**
 * Type de départ programmé — miroir EXACT de l'enum `MobilityServiceType`.
 *
 * ⚠️ **À ne pas confondre avec `VehicleTypeValue`** : celui-ci décrit le
 * VÉHICULE (minibus, pirogue…), celui-là la NATURE DU TRAJET (navette régulière,
 * transfert point à point…). Un même minibus assure une navette le lundi et une
 * liaison le mardi — c'est précisément pourquoi le départ est un objet à part.
 */
export type MobilityServiceTypeValue = 'navette' | 'transfert' | 'liaison' | 'excursion';

/** Types de départ proposés à la programmation (miroir de `MobilityServiceType`). */
export const MOBILITY_SERVICE_TYPES: readonly {
  value: MobilityServiceTypeValue;
  label: string;
  hint: string;
}[] = [
  {
    value: 'navette',
    label: 'Navette régulière',
    hint: 'Trajet répété à horaire fixe, par exemple vers l’aéroport AIBD.',
  },
  {
    value: 'transfert',
    label: 'Transfert point à point',
    hint: 'Une course d’un lieu précis à un autre, à une date donnée.',
  },
  {
    value: 'liaison',
    label: 'Liaison interurbaine',
    hint: 'Trajet entre deux villes, par exemple Dakar – Saint-Louis.',
  },
  {
    value: 'excursion',
    label: 'Excursion en transport',
    hint: 'Sortie à la journée avec transport inclus.',
  },
];

/**
 * Corps de `POST /mobility-services` / `PATCH /mobility-services/{id}` — miroir
 * de `StoreMobilityServiceRequest` / `UpdateMobilityServiceRequest`.
 */
export interface NewMobilityServicePayload {
  type: MobilityServiceTypeValue;
  departure: string;
  destination: string;
  /** Date-heure du départ, au format `YYYY-MM-DD HH:mm:ss`. Doit être future. */
  departure_at: string;
  capacity: number;
  price_xof: number;
  description?: string | null;
  /**
   * Véhicule opérant le départ. Facultatif, mais **le serveur exige qu'il soit
   * le vôtre** et que la capacité vendue n'excède pas la sienne.
   */
  vehicle_id?: number | null;
}

/** Une inclusion structurée d'un circuit (clé backend + libellé affiché). */
export interface ExperienceInclusionOption {
  key: string;
  label: string;
}

/**
 * Inclusions proposées à la composition d'un circuit (§4.1 tourisme :
 * programme, guide, restauration…). Stockées en `{ cle: booléen }`.
 */
export const EXPERIENCE_INCLUSIONS: readonly ExperienceInclusionOption[] = [
  { key: 'restauration', label: 'Restauration' },
  { key: 'guide', label: 'Guide' },
  { key: 'transport', label: 'Transport' },
  { key: 'hebergement', label: 'Hébergement' },
];

/** Corps de `POST /experiences` — miroir de `StoreExperienceRequest`. */
export interface NewExperiencePayload {
  title: string;
  destination: string;
  description?: string | null;
  duration_days: number;
  price_xof: number;
  capacity: number;
  /** Inclusions structurées : `{ restauration: true, guide: false, … }`. */
  inclusions?: Record<string, boolean>;
}

/**
 * Dépôt et gestion des **offres réservables** d'un prestataire (F5.6) :
 * véhicules (module Mobility) et expériences touristiques (module Explore).
 *
 * Comble l'exigence CDC §5.2 / §15 (« un prestataire peut proposer véhicule,
 * circuit, pirogue… ») restée sans interface : le backend l'expose déjà via
 * `POST /vehicles`, `GET /vehicles/mine`, `PATCH /vehicles/{id}` et
 * `POST /experiences`, `GET /experiences/mine`.
 *
 * Toutes les routes de dépôt exigent une session + un **compte vérifié**
 * (`verified.account`) et un **profil prestataire validé** (policy `create`) :
 * un 403 est possible et géré par les écrans appelants.
 */
@Injectable({ providedIn: 'root' })
export class OfferService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  // --- Véhicules (module Mobility) -------------------------------------------

  /** GET /vehicles/mine — mes véhicules, tous statuts (paginé, 15/page). */
  myVehicles(page = 1): Observable<Paginated<Vehicle>> {
    return this.http.get<Paginated<Vehicle>>(`${this.api}/vehicles/mine`, {
      params: { page: String(page) },
    });
  }

  /**
   * Retrouve un de mes véhicules par son id, tous statuts confondus, en
   * parcourant `GET /vehicles/mine` page par page. Nécessaire pour l'édition :
   * le détail public `GET /vehicles/{id}` ne renvoie que les véhicules publiés,
   * or on édite justement des véhicules « en attente » ou « rejetés ».
   * Renvoie `null` si aucun véhicule ne correspond (id inconnu ou pas à moi).
   */
  findMyVehicle(id: number): Observable<Vehicle | null> {
    const search = (page: number): Observable<Vehicle | null> =>
      this.myVehicles(page).pipe(
        switchMap((res) => {
          const found = res.data.find((v) => v.id === id);
          if (found) return of(found);
          if (res.meta.current_page < res.meta.last_page) return search(page + 1);
          return of(null);
        }),
      );
    return search(1);
  }

  /** POST /vehicles — dépose un véhicule (créé « en attente de validation »). */
  createVehicle(payload: NewVehiclePayload): Observable<ApiEnvelope<{ vehicle: Vehicle }>> {
    return this.http.post<ApiEnvelope<{ vehicle: Vehicle }>>(
      `${this.api}/vehicles`,
      this.cleanVehicle(payload),
    );
  }

  /** PATCH /vehicles/{id} — met à jour un véhicule (le statut n'est pas modifiable ici). */
  updateVehicle(
    id: number,
    payload: NewVehiclePayload,
  ): Observable<ApiEnvelope<{ vehicle: Vehicle }>> {
    return this.http.patch<ApiEnvelope<{ vehicle: Vehicle }>>(
      `${this.api}/vehicles/${id}`,
      this.cleanVehicle(payload),
    );
  }

  // --- Expériences (module Explore) ------------------------------------------

  /** GET /experiences/mine — mes expériences, tous statuts (paginé, 15/page). */
  myExperiences(page = 1): Observable<Paginated<Experience>> {
    return this.http.get<Paginated<Experience>>(`${this.api}/experiences/mine`, {
      params: { page: String(page) },
    });
  }

  /** POST /experiences — publie une expérience (créée « en attente de validation »). */
  createExperience(
    payload: NewExperiencePayload,
  ): Observable<ApiEnvelope<{ experience: Experience }>> {
    return this.http.post<ApiEnvelope<{ experience: Experience }>>(
      `${this.api}/experiences`,
      this.cleanExperience(payload),
    );
  }

  /**
   * Retrouve un de mes circuits par son id, tous statuts confondus (F8.19).
   *
   * Même contrainte que `findMyVehicle` : le détail public ne renvoie que les
   * circuits **publiés**, or on édite justement ceux qui attendent leur
   * validation. Un circuit **retiré** se relit donc aussi par ce chemin.
   */
  findMyExperience(id: number): Observable<Experience | null> {
    const search = (page: number): Observable<Experience | null> =>
      this.myExperiences(page).pipe(
        switchMap((res) => {
          const found = res.data.find((x) => x.id === id);
          if (found) return of(found);
          if (res.meta.current_page < res.meta.last_page) return search(page + 1);
          return of(null);
        }),
      );
    return search(1);
  }

  /**
   * PATCH /experiences/{id} — met à jour un circuit (F8.19).
   *
   * ⚠️ Cette route **n'existait pas** côté serveur : un circuit déposé était
   * définitif, et ne pouvait donc jamais être illustré après coup.
   */
  updateExperience(
    id: number,
    payload: NewExperiencePayload,
  ): Observable<ApiEnvelope<{ experience: Experience }>> {
    return this.http.patch<ApiEnvelope<{ experience: Experience }>>(
      `${this.api}/experiences/${id}`,
      this.cleanExperience(payload),
    );
  }

  /**
   * DELETE /vehicles/{id} — retire un véhicule du catalogue (F8.19).
   *
   * ⚠️ **Le serveur décide s'il supprime ou s'il retire** : une offre déjà
   * réservée n'est jamais supprimée (les réservations la désignent sans clé
   * étrangère). La réponse porte `deleted` et, le cas échéant, la `reason` à
   * afficher au prestataire — l'écran ne rejoue pas la règle.
   */
  deleteVehicle(id: number): Observable<ApiEnvelope<OfferRemoval>> {
    return this.http.delete<ApiEnvelope<OfferRemoval>>(`${this.api}/vehicles/${id}`);
  }

  /** DELETE /experiences/{id} — retire un circuit du catalogue (F8.19). */
  deleteExperience(id: number): Observable<ApiEnvelope<OfferRemoval>> {
    return this.http.delete<ApiEnvelope<OfferRemoval>>(`${this.api}/experiences/${id}`);
  }

  // --- Départs programmés (module Mobility, F8.23) ---------------------------
  //
  // ⚠️ `mobility_services` était en LECTURE SEULE côté serveur : le catalogue
  // public `/mobilite` ne pouvait être alimenté que par le seeder. Ces quatre
  // méthodes appellent les routes qui manquaient.

  /** GET /mobility-services/mine — mes départs, tous statuts (paginé, 15/page). */
  myDepartures(page = 1): Observable<Paginated<MobilityService>> {
    return this.http.get<Paginated<MobilityService>>(`${this.api}/mobility-services/mine`, {
      params: { page: String(page) },
    });
  }

  /**
   * Retrouve un de mes départs par son id, tous statuts confondus.
   *
   * Même contrainte que `findMyVehicle` / `findMyExperience` : la fiche publique
   * ne sert que les départs **publiés**, or on corrige justement ceux qui
   * attendent leur validation — ou qui ont été retirés.
   */
  findMyDeparture(id: number): Observable<MobilityService | null> {
    const search = (page: number): Observable<MobilityService | null> =>
      this.myDepartures(page).pipe(
        switchMap((res) => {
          const found = res.data.find((d) => d.id === id);
          if (found) return of(found);
          if (res.meta.current_page < res.meta.last_page) return search(page + 1);
          return of(null);
        }),
      );
    return search(1);
  }

  /** POST /mobility-services — programme un départ (créé « en attente de validation »). */
  createDeparture(
    payload: NewMobilityServicePayload,
  ): Observable<ApiEnvelope<{ mobility_service: MobilityService }>> {
    return this.http.post<ApiEnvelope<{ mobility_service: MobilityService }>>(
      `${this.api}/mobility-services`,
      this.cleanDeparture(payload),
    );
  }

  /** PATCH /mobility-services/{id} — corrige un départ (le statut n'est pas modifiable ici). */
  updateDeparture(
    id: number,
    payload: NewMobilityServicePayload,
  ): Observable<ApiEnvelope<{ mobility_service: MobilityService }>> {
    return this.http.patch<ApiEnvelope<{ mobility_service: MobilityService }>>(
      `${this.api}/mobility-services/${id}`,
      this.cleanDeparture(payload),
    );
  }

  /** DELETE /mobility-services/{id} — retire un départ du catalogue. */
  deleteDeparture(id: number): Observable<ApiEnvelope<OfferRemoval>> {
    return this.http.delete<ApiEnvelope<OfferRemoval>>(`${this.api}/mobility-services/${id}`);
  }

  // --- Préparation des corps -------------------------------------------------

  /**
   * Prépare le corps d'un véhicule : n'envoie que les champs de conformité
   * pertinents pour la famille du type (assurance/chauffeur pour un motorisé,
   * gilets/météo pour une pirogue) et omet les valeurs vides.
   */
  private cleanVehicle(p: NewVehiclePayload): Record<string, unknown> {
    const body: Record<string, unknown> = {
      type: p.type,
      capacity: p.capacity,
      price_per_day_xof: p.price_per_day_xof,
      has_driver: p.has_driver,
    };
    if (p.brand && p.brand.trim() !== '') body['brand'] = p.brand.trim();
    if (p.model && p.model.trim() !== '') body['model'] = p.model.trim();
    if (p.description && p.description.trim() !== '') body['description'] = p.description.trim();
    if (p.caution_xof != null) body['caution_xof'] = p.caution_xof;

    if (vehicleFamily(p.type) === 'pirogue') {
      if (p.life_jackets_count != null) body['life_jackets_count'] = p.life_jackets_count;
      if (p.weather_compliant != null) body['weather_compliant'] = p.weather_compliant;
      if (p.provider_compliant != null) body['provider_compliant'] = p.provider_compliant;
    } else {
      if (p.insurance_ref && p.insurance_ref.trim() !== '')
        body['insurance_ref'] = p.insurance_ref.trim();
      if (p.driver_identity && p.driver_identity.trim() !== '')
        body['driver_identity'] = p.driver_identity.trim();
    }
    return body;
  }

  /**
   * Prépare le corps d'un départ : omet la description vide, et envoie
   * `vehicle_id` **explicitement à `null`** quand le prestataire détache son
   * véhicule.
   *
   * ⚠️ Le détachement est le seul cas où une valeur vide doit VOYAGER : omettre
   * la clé, sur un `PATCH` en `sometimes`, laisserait le véhicule en place et le
   * prestataire croirait l'avoir retiré.
   */
  private cleanDeparture(p: NewMobilityServicePayload): Record<string, unknown> {
    const body: Record<string, unknown> = {
      type: p.type,
      departure: p.departure.trim(),
      destination: p.destination.trim(),
      departure_at: p.departure_at,
      capacity: p.capacity,
      price_xof: p.price_xof,
      vehicle_id: p.vehicle_id ?? null,
    };
    if (p.description && p.description.trim() !== '') body['description'] = p.description.trim();
    return body;
  }

  /** Prépare le corps d'une expérience (omet description vide, garde les inclusions). */
  private cleanExperience(p: NewExperiencePayload): Record<string, unknown> {
    const body: Record<string, unknown> = {
      title: p.title.trim(),
      destination: p.destination.trim(),
      duration_days: p.duration_days,
      price_xof: p.price_xof,
      capacity: p.capacity,
    };
    if (p.description && p.description.trim() !== '') body['description'] = p.description.trim();
    if (p.inclusions && Object.keys(p.inclusions).length) body['inclusions'] = p.inclusions;
    return body;
  }
}
