import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { environment } from '../../../environments/environment';

/**
 * Carte de la section « Location de véhicules » de l'accueil, tel que
 * renvoyé par `GET /vehicle-showcase-cards`. Remplace l'ancienne section
 * « Protocole de confiance » (demande client, 2026-09-07).
 */
export interface VehicleShowcaseCard {
  id: number;
  title: string;
  description: string | null;
  /**
   * Thème libre saisi par l'équipe (« Berline », « 4x4 », « Minibus »…) —
   * pas de liste fermée. N'est plus utilisé pour un découpage visuel sur
   * l'accueil (toutes les cartes s'affichent dans une seule grille, demande
   * client 2026-09-07) : reste une donnée de tri au back-office.
   */
  category: string | null;
  image: string;
  linkUrl: string;
  linkLabel: string | null;
}

interface VehicleShowcaseCardApi {
  id: number;
  title: string;
  description: string | null;
  category: string | null;
  image: string;
  link_url: string;
  link_label: string | null;
}

/** Accroche de la section, pilotable au back-office (réglages `home.vehicle_showcase_*`). */
export interface VehicleShowcaseHeading {
  eyebrow: string;
  title: string;
  lead: string;
}

/**
 * Lecture publique de la vitrine « Location de véhicules » (accueil).
 */
@Injectable({ providedIn: 'root' })
export class VehicleShowcaseService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  list(): Observable<{ cards: VehicleShowcaseCard[]; heading: VehicleShowcaseHeading }> {
    return this.http
      .get<{
        data: { cards: VehicleShowcaseCardApi[]; eyebrow: string; title: string; lead: string };
      }>(`${this.api}/vehicle-showcase-cards`)
      .pipe(
        map((res) => ({
          cards: (res.data.cards ?? []).map((c) => this.depuisApi(c)),
          heading: { eyebrow: res.data.eyebrow, title: res.data.title, lead: res.data.lead },
        })),
      );
  }

  private depuisApi(c: VehicleShowcaseCardApi): VehicleShowcaseCard {
    return {
      id: c.id,
      title: c.title,
      description: c.description,
      category: c.category,
      image: c.image,
      linkUrl: c.link_url,
      linkLabel: c.link_label,
    };
  }
}
