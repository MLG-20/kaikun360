import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Property, PropertyDocument } from '../../models/property.model';
import { Stay } from '../../models/stay.model';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Types de pièces justificatives d'un bien — miroir de l'allow-list du serveur
 * (`StorePropertyDocumentRequest` : titre_foncier / bail / plan / autre).
 */
export type PropertyDocumentType = 'titre_foncier' | 'bail' | 'plan' | 'autre';

/**
 * Nature d'un bien — miroir de l'enum `PropertyType` backend
 * (colonne `properties.type`). Alimente le sélecteur de type du formulaire.
 */
export type PropertyType =
  | 'appartement'
  | 'maison'
  | 'villa'
  | 'studio'
  | 'terrain'
  | 'bureau'
  | 'commerce'
  | 'autre';

/**
 * Corps de `POST /properties` — miroir EXACT de `StorePropertyRequest`.
 *
 * `type`, `title`, `region_id` et `department_id` sont requis ; le département
 * doit appartenir à la région et la commune au département (cohérence vérifiée
 * côté serveur → les sélecteurs en cascade garantissent des valeurs valides).
 */
export interface CreatePropertyPayload {
  type: PropertyType;
  title: string;
  region_id: number;
  department_id: number;
  description?: string | null;
  price_xof?: number | null;
  commune_id?: number | null;
  tourist_zone?: string | null;
  address?: string | null;
  latitude?: number | null;
  longitude?: number | null;
}

/**
 * Corps de `PATCH /properties/{id}` — miroir de `UpdatePropertyRequest`.
 *
 * Mise à jour PARTIELLE : tous les champs sont optionnels (seuls ceux fournis
 * sont modifiés). Le STATUT n'est volontairement pas modifiable ici (réservé au
 * circuit de validation par les agents).
 */
export type UpdatePropertyPayload = Partial<CreatePropertyPayload>;

/**
 * Corps de `PUT /properties/{id}/stay` — miroir de `UpsertStayRequest` (F4.3).
 *
 * Active/paramètre le mode « nuitées » d'un bien. Seul le prix par nuit est
 * obligatoire ; les autres champs prennent des valeurs par défaut en base à la
 * création. Les règles/équipements (JSON libre) sont hors périmètre du formulaire.
 */
export interface UpsertStayPayload {
  price_per_night_xof: number;
  caution_xof?: number | null;
  capacity?: number | null;
  min_nights?: number | null;
  max_nights?: number | null;
  check_in_time?: string | null;
  check_out_time?: string | null;
}

/**
 * Dépôt de bien par un propriétaire (F2.7).
 *
 * `POST /properties` exige une session ET un **compte vérifié** (middleware
 * `verified.account` → 403 sinon) ; la page gère ce cas en amont. Le bien créé
 * part en file de validation (statut `en_attente_validation`) : il n'est pas
 * publié tant qu'un agent ne l'a pas approuvé (B4). Les photos/documents sont
 * ajoutés séparément (endpoints médias) — hors périmètre de ce formulaire.
 */
@Injectable({ providedIn: 'root' })
export class PropertyManagementService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /**
   * GET /properties/mine — mes biens, **tous statuts confondus** (F4.2).
   *
   * Contrairement au catalogue public (biens publiés seulement), cette liste
   * montre aussi les biens en attente de validation ou rejetés. Paginée
   * (15/page, plus récents d'abord), forme standard Laravel `{ data, links, meta }`.
   * Alimente l'écran « Mes biens » de l'espace propriétaire. Auth requise.
   */
  mine(page = 1): Observable<Paginated<Property>> {
    return this.http.get<Paginated<Property>>(`${this.api}/properties/mine`, {
      params: { page: String(page) },
    });
  }

  /**
   * GET /properties/mine/{id} — fiche d'UN de mes biens (F4.2).
   *
   * Réservé au propriétaire du bien (404 sinon). Expose le bien quel que soit son
   * statut. Alimente la fiche atteinte en cliquant une carte depuis « Mes biens ».
   */
  get(id: number | string): Observable<ApiEnvelope<Property>> {
    return this.http.get<ApiEnvelope<Property>>(`${this.api}/properties/mine/${id}`);
  }

  /** POST /properties — dépose un bien (auth + vérifié). */
  create(payload: CreatePropertyPayload): Observable<ApiEnvelope<{ property: Property }>> {
    return this.http.post<ApiEnvelope<{ property: Property }>>(
      `${this.api}/properties`,
      this.clean(payload),
    );
  }

  /**
   * PATCH /properties/{id} — met à jour un bien (F4.3).
   *
   * Mise à jour partielle : seules les clés non vides sont envoyées. Réservé au
   * propriétaire du bien (403 sinon). Le statut n'est pas modifiable ici.
   */
  update(
    id: number | string,
    payload: UpdatePropertyPayload,
  ): Observable<ApiEnvelope<{ property: Property }>> {
    // On envoie le corps TEL QUEL (sans retirer les null) : en PATCH, un champ à
    // `null` signifie « vider cette valeur » (ex. supprimer le loyer mensuel quand
    // on bascule un bien en nuitées seules). Laravel convertit les `''` en null.
    return this.http.patch<ApiEnvelope<{ property: Property }>>(
      `${this.api}/properties/${id}`,
      payload,
    );
  }

  /**
   * PUT /properties/{id}/stay — active/paramètre le mode « nuitées » (F4.3).
   *
   * Upsert : crée la config si le bien n'en a pas, la met à jour sinon. Exige un
   * compte vérifié. Réservé au propriétaire du bien (403 sinon).
   */
  upsertStay(
    id: number | string,
    payload: UpsertStayPayload,
  ): Observable<ApiEnvelope<{ stay: Stay }>> {
    return this.http.put<ApiEnvelope<{ stay: Stay }>>(
      `${this.api}/properties/${id}/stay`,
      this.clean(payload),
    );
  }

  /**
   * DELETE /properties/{id}/stay — retire le mode « nuitées » (F4.3).
   *
   * Supprime la config, ou la désactive si des réservations existent (le serveur
   * choisit). Réservé au propriétaire du bien.
   */
  removeStay(id: number | string): Observable<ApiEnvelope<{ message: string }>> {
    return this.http.delete<ApiEnvelope<{ message: string }>>(
      `${this.api}/properties/${id}/stay`,
    );
  }

  /**
   * GET /properties/{id}/documents — pièces justificatives d'un bien (F4.5).
   *
   * Réservé au propriétaire du bien (403 sinon). Chaque document porte un lien
   * de téléchargement **signé et temporaire** (le chemin n'est jamais exposé).
   */
  documents(id: number | string): Observable<ApiEnvelope<PropertyDocument[]>> {
    return this.http.get<ApiEnvelope<PropertyDocument[]>>(
      `${this.api}/properties/${id}/documents`,
    );
  }

  /**
   * POST /properties/{id}/documents — dépose une pièce justificative (F4.5).
   *
   * Envoi **multipart** : le serveur attend `type` (titre_foncier/bail/plan/autre)
   * et `file` (PDF/JPG/PNG ≤ 5 Mo, contrôlé aussi en amont). Compte vérifié requis.
   */
  uploadDocument(
    id: number | string,
    type: PropertyDocumentType,
    file: File,
  ): Observable<ApiEnvelope<{ document: PropertyDocument }>> {
    const body = new FormData();
    body.append('type', type);
    body.append('file', file);

    return this.http.post<ApiEnvelope<{ document: PropertyDocument }>>(
      `${this.api}/properties/${id}/documents`,
      body,
    );
  }

  /**
   * DELETE /properties/{id}/documents/{documentId} — retire une pièce (F4.5).
   *
   * Efface la métadonnée ET le fichier sur disque. Réservé au propriétaire du bien.
   */
  removeDocument(
    id: number | string,
    documentId: number,
  ): Observable<ApiEnvelope<{ deleted: boolean }>> {
    return this.http.delete<ApiEnvelope<{ deleted: boolean }>>(
      `${this.api}/properties/${id}/documents/${documentId}`,
    );
  }

  /** Extensions acceptées par le serveur, pour l'attribut `accept` du sélecteur. */
  static readonly DOC_ACCEPT = 'application/pdf,image/jpeg,image/png';
  /** Taille maximale d'un document (5 Mo), pour un contrôle en amont. */
  static readonly DOC_MAX_BYTES = 5 * 1024 * 1024;

  /** Retire les clés optionnelles vides (null/undefined/chaîne vide). */
  private clean<T extends object>(payload: T): Record<string, unknown> {
    const body: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(payload)) {
      if (value === null || value === undefined || value === '') {
        continue;
      }
      body[key] = value;
    }
    return body;
  }
}
