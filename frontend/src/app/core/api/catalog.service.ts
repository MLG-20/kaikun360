import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ApiEnvelope } from './api-response.model';
import { Experience, ExperienceAvailability } from '../../models/experience.model';
import { MobilityService } from '../../models/mobility-service.model';
import { Property } from '../../models/property.model';
import { Stay, StayAvailability } from '../../models/stay.model';
import { Vehicle } from '../../models/vehicle.model';
import { Paginated } from './pagination.model';

/**
 * Filtres communs aux catalogues paginés (F2.1).
 * `page`/`per_page` pilotent la pagination, `q` la recherche plein-texte.
 */
export interface BaseCatalogFilters {
  q?: string;
  page?: number;
  per_page?: number;
  [key: string]: string | number | boolean | null | undefined;
}

/** Filtres du catalogue immobilier (miroir de PropertyCatalogController@index). */
export interface PropertyFilters extends BaseCatalogFilters {
  region_id?: number;
  department_id?: number;
  commune_id?: number;
  type?: string;
  tourist_zone?: string;
  price_min?: number;
  price_max?: number;
  verification_level?: string;
  sort?: 'recent' | 'price_asc' | 'price_desc';
}

/** Filtres du catalogue des nuitées (miroir de StayCatalogController@index). */
export interface StayFilters extends BaseCatalogFilters {
  region_id?: number;
  department_id?: number;
  commune_id?: number;
  capacity?: number;
  price_min?: number;
  price_max?: number;
}

/** Filtres du catalogue des véhicules (miroir de VehicleCatalogController@index). */
export interface VehicleFilters extends BaseCatalogFilters {
  type?: string;
  capacity_min?: number;
  price_max?: number;
  has_driver?: boolean;
  sort?: 'recent' | 'price_asc' | 'price_desc';
}

/** Filtres du catalogue des expériences (miroir de ExperienceCatalogController@index). */
export interface ExperienceFilters extends BaseCatalogFilters {
  destination?: string;
  price_min?: number;
  price_max?: number;
  duration_max?: number;
  sort?: 'recent' | 'price_asc' | 'price_desc';
}

/** Filtres des services de mobilité (miroir de MobilityServiceController@index). */
export interface MobilityFilters extends BaseCatalogFilters {
  type?: string;
  departure?: string;
  destination?: string;
  date?: string;
}

/**
 * Accès en LECTURE au catalogue public (F2.1).
 *
 * Un service unique par-dessus les 5 index publics du backend
 * (`/properties`, `/stays`, `/vehicles`, `/experiences`, `/mobility-services`).
 * Aucune authentification requise ; le `tokenInterceptor` n'ajoute un jeton que
 * s'il existe. Chaque méthode renvoie l'enveloppe paginée telle quelle
 * (`{ data, links, meta }`), consommée par le composant catalogue.
 *
 * Les clés de filtre correspondent exactement à ce que valide chaque contrôleur :
 * une clé inconnue serait simplement ignorée côté serveur.
 */
@Injectable({ providedIn: 'root' })
export class CatalogService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /properties — biens immobiliers publiés. */
  properties(filters: PropertyFilters = {}): Observable<Paginated<Property>> {
    return this.http.get<Paginated<Property>>(`${this.api}/properties`, {
      params: this.toParams(filters),
    });
  }

  /** GET /stays — nuitées réservables. */
  stays(filters: StayFilters = {}): Observable<Paginated<Stay>> {
    return this.http.get<Paginated<Stay>>(`${this.api}/stays`, {
      params: this.toParams(filters),
    });
  }

  /**
   * GET /properties/{id} — détail d'un bien publié (fiche immobilier, F2.3).
   * Le backend enveloppe la ressource dans `{ data }` ; on renvoie l'Observable
   * de l'enveloppe pour que la page décide de son état (chargement/erreur).
   */
  property(id: number | string): Observable<ApiEnvelope<Property>> {
    return this.http.get<ApiEnvelope<Property>>(`${this.api}/properties/${id}`);
  }

  /** GET /stays/{id} — détail d'une nuitée publiée (fiche nuitée, F2.3). */
  stay(id: number | string): Observable<ApiEnvelope<Stay>> {
    return this.http.get<ApiEnvelope<Stay>>(`${this.api}/stays/${id}`);
  }

  /**
   * GET /stays/{id}/availability — intervalles déjà réservés d'une nuitée.
   * Alimente le calendrier de disponibilité de la fiche (F2.3).
   */
  stayAvailability(id: number | string): Observable<ApiEnvelope<StayAvailability>> {
    return this.http.get<ApiEnvelope<StayAvailability>>(
      `${this.api}/stays/${id}/availability`,
    );
  }

  /** GET /vehicles — véhicules publiés. */
  vehicles(filters: VehicleFilters = {}): Observable<Paginated<Vehicle>> {
    return this.http.get<Paginated<Vehicle>>(`${this.api}/vehicles`, {
      params: this.toParams(filters),
    });
  }

  /** GET /vehicles/{id} — détail d'un véhicule publié (fiche transport, F2.4). */
  vehicle(id: number | string): Observable<ApiEnvelope<Vehicle>> {
    return this.http.get<ApiEnvelope<Vehicle>>(`${this.api}/vehicles/${id}`);
  }

  /** GET /experiences — expériences touristiques publiées. */
  experiences(filters: ExperienceFilters = {}): Observable<Paginated<Experience>> {
    return this.http.get<Paginated<Experience>>(`${this.api}/experiences`, {
      params: this.toParams(filters),
    });
  }

  /** GET /experiences/{id} — détail d'une expérience publiée (fiche tourisme, F2.4). */
  experience(id: number | string): Observable<ApiEnvelope<Experience>> {
    return this.http.get<ApiEnvelope<Experience>>(`${this.api}/experiences/${id}`);
  }

  /**
   * GET /experiences/{id}/availability — places restantes d'une expérience.
   * Alimente l'indicateur de disponibilité de la fiche (F2.4).
   */
  experienceAvailability(
    id: number | string,
  ): Observable<ApiEnvelope<ExperienceAvailability>> {
    return this.http.get<ApiEnvelope<ExperienceAvailability>>(
      `${this.api}/experiences/${id}/availability`,
    );
  }

  /** GET /mobility-services — services de mobilité publiés. */
  mobilityServices(filters: MobilityFilters = {}): Observable<Paginated<MobilityService>> {
    return this.http.get<Paginated<MobilityService>>(`${this.api}/mobility-services`, {
      params: this.toParams(filters),
    });
  }

  /**
   * Convertit un objet de filtres en `HttpParams`, en ignorant les valeurs
   * vides (null, undefined, chaîne vide) pour ne pas polluer l'URL ni la clé
   * de cache serveur. Les booléens sont sérialisés en « true »/« false ».
   */
  private toParams(filters: BaseCatalogFilters): HttpParams {
    let params = new HttpParams();
    for (const [key, value] of Object.entries(filters)) {
      if (value === null || value === undefined || value === '') {
        continue;
      }
      params = params.set(key, String(value));
    }
    return params;
  }
}
