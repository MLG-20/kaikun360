import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { PropertyPhoto } from '../../models/property.model';
import { ApiEnvelope } from './api-response.model';

/**
 * Types de ressources illustrables — miroir de l'allow-list `Media::TYPES`
 * côté serveur (jamais une classe libre : le serveur n'accepte que ces clés).
 */
export type MediableType = 'property' | 'vehicle' | 'experience';

/**
 * Médias (photos) des ressources — couche transversale.
 *
 * Des photos nettes conditionnent la confiance des clients : un bien illustré
 * se choisit, un bien sans photo est ignoré. Ce service porte le téléversement
 * et la suppression ; l'affichage passe par les ressources qui embarquent leurs
 * photos (ex. `PropertyResource.photos`).
 *
 * Contraintes du serveur (`StoreMediaRequest`) : image **jpeg/png/webp**,
 * **5 Mo maximum**. L'image est recompressée côté serveur après dépôt.
 * L'autorisation est celle de la ressource illustrée (le propriétaire d'un bien
 * peut illustrer son bien, personne d'autre).
 */
@Injectable({ providedIn: 'root' })
export class MediaService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** Extensions acceptées, pour l'attribut `accept` du sélecteur de fichier. */
  static readonly ACCEPT = 'image/jpeg,image/png,image/webp';
  /** Taille maximale acceptée par le serveur (5 Mo), pour un contrôle en amont. */
  static readonly MAX_BYTES = 5 * 1024 * 1024;

  /**
   * POST /media/upload — téléverse une photo et la rattache à une ressource.
   *
   * `isPrimary` désigne l'image de couverture (celle des cartes du catalogue).
   */
  upload(
    type: MediableType,
    id: number | string,
    file: File,
    options: { isPrimary?: boolean; position?: number } = {},
  ): Observable<ApiEnvelope<{ media: PropertyPhoto }>> {
    const body = new FormData();
    body.append('mediable_type', type);
    body.append('mediable_id', String(id));
    body.append('file', file);
    if (options.isPrimary !== undefined) {
      // FormData ne transporte que du texte : Laravel interprète '1'/'0' en booléen.
      body.append('is_primary', options.isPrimary ? '1' : '0');
    }
    if (options.position !== undefined) {
      body.append('position', String(options.position));
    }

    return this.http.post<ApiEnvelope<{ media: PropertyPhoto }>>(
      `${this.api}/media/upload`,
      body,
    );
  }

  /**
   * PATCH /media/{id}/primary — désigne cette photo comme image de couverture.
   * Les autres photos de la même ressource sont dépromues (une seule couverture).
   */
  setPrimary(mediaId: number): Observable<ApiEnvelope<{ media: PropertyPhoto }>> {
    return this.http.patch<ApiEnvelope<{ media: PropertyPhoto }>>(
      `${this.api}/media/${mediaId}/primary`,
      {},
    );
  }

  /** DELETE /media/{id} — retire une photo (réservé à qui peut éditer la ressource). */
  remove(mediaId: number): Observable<ApiEnvelope<{ message: string }>> {
    return this.http.delete<ApiEnvelope<{ message: string }>>(
      `${this.api}/media/${mediaId}`,
    );
  }
}
