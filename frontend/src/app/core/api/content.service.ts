import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import { environment } from '../../../environments/environment';
import { ContentPage, Faq } from '../../models/content.model';

/**
 * Accès en LECTURE au contenu éditorial public (F2.8).
 *
 * Deux ressources gérées par le back-office (B13.4) mais exposées publiquement :
 *   - la FAQ publiée (`GET /faqs`) ;
 *   - les pages de contenu adressées par slug (`GET /pages/{slug}`) — À propos,
 *     mentions légales, CGU, politique de confidentialité…
 *
 * Le contenu appartient au backend (source de vérité, éditable par l'admin) :
 * on ne code donc jamais ces textes en dur côté frontend.
 */
@Injectable({ providedIn: 'root' })
export class ContentService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /**
   * GET /faqs — liste des entrées de FAQ publiées.
   *
   * L'enveloppe est un simple `{ data: Faq[] }` (collection non paginée) ;
   * l'ordre d'affichage voulu par l'équipe est porté par `position`.
   */
  faqs(): Observable<{ data: Faq[] }> {
    return this.http.get<{ data: Faq[] }>(`${this.api}/faqs`);
  }

  /**
   * GET /pages/{slug} — page de contenu publiée, résolue par son slug.
   *
   * Le backend enveloppe la ressource sous `data.page` (et non directement dans
   * `data`) : on l'aplatit ici pour renvoyer directement la `ContentPage`.
   * Renvoie 404 si la page est absente ou non publiée (à traiter côté appelant
   * comme un état « introuvable »).
   */
  page(slug: string): Observable<ContentPage> {
    return this.http
      .get<{ data: { page: ContentPage } }>(`${this.api}/pages/${slug}`)
      .pipe(map((res) => res.data.page));
  }
}
