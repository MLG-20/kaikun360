import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { PartnerDueSelf, PartnerPayoutSelf } from '../../models/payout.model';
import { Paginated } from './pagination.model';

/**
 * « Mes reversements » — self-service partenaire, appelé depuis les espaces
 * propriétaire et prestataire (ce sont les DEUX seuls bénéficiaires possibles
 * du registre, cf. `backend/app/Models/PartnerDue.php`). Le registre est
 * transversal côté serveur (`routes/transversal.php`), donc ce service l'est
 * aussi plutôt que de le dupliquer dans `owner.service.ts`/`provider.service.ts`.
 */
@Injectable({ providedIn: 'root' })
export class PayoutService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** GET /reversements/mine — mon dû (par défaut : ce qui reste vivant). */
  mine(status?: string): Observable<Paginated<PartnerDueSelf>> {
    return this.http.get<Paginated<PartnerDueSelf>>(`${this.api}/reversements/mine`, {
      params: status ? { status } : {},
    });
  }

  /** GET /reversements/mine/payouts — l'historique de mes versements. */
  minePayouts(): Observable<Paginated<PartnerPayoutSelf>> {
    return this.http.get<Paginated<PartnerPayoutSelf>>(`${this.api}/reversements/mine/payouts`);
  }
}
