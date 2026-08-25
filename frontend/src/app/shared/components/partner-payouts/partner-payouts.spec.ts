import { registerLocaleData } from '@angular/common';
import localeFr from '@angular/common/locales/fr';
import { TestBed } from '@angular/core/testing';
import { Observable, of, throwError } from 'rxjs';

// Le pipe `date` du gabarit est posé en locale 'fr' (comme partout ailleurs
// dans le projet) ; `app.config.ts` l'enregistre au bootstrap, que ce test
// unitaire ne charge pas.
registerLocaleData(localeFr);

import { PartnerPayoutsComponent } from './partner-payouts';
import { PayoutService } from '../../../core/api/payout.service';
import { PartnerDueSelf, PartnerPayoutSelf } from '../../../models/payout.model';
import { Paginated } from '../../../core/api/pagination.model';

/**
 * « Mes reversements » — self-service (après F8.16.a).
 *
 * L'enjeu du composant tient en une phrase : c'est une CONSULTATION, jamais
 * une action (préparer un lot, constater un virement restent des gestes
 * d'agent). Les tests vérifient donc surtout que les deux listes (dû /
 * historique) s'affichent correctement, sans rien y ajouter d'exécutable.
 */
describe('PartnerPayoutsComponent', () => {
  const paginated = <T>(data: T[]): Paginated<T> => ({
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: 1,
      from: null,
      last_page: 1,
      path: '',
      per_page: 25,
      to: null,
      total: data.length,
    },
  });

  class FakePayoutService {
    duesResult: Observable<Paginated<PartnerDueSelf>> = of(paginated([]));
    payoutsResult: Observable<Paginated<PartnerPayoutSelf>> = of(paginated([]));

    mine(): Observable<Paginated<PartnerDueSelf>> {
      return this.duesResult;
    }

    minePayouts(): Observable<Paginated<PartnerPayoutSelf>> {
      return this.payoutsResult;
    }
  }

  let payouts: FakePayoutService;

  beforeEach(async () => {
    payouts = new FakePayoutService();

    await TestBed.configureTestingModule({
      imports: [PartnerPayoutsComponent],
      providers: [{ provide: PayoutService, useValue: payouts }],
    }).compileComponents();
  });

  async function render() {
    const fixture = TestBed.createComponent(PartnerPayoutsComponent);
    await fixture.whenStable();
    fixture.detectChanges();
    return fixture.nativeElement as HTMLElement;
  }

  it('affiche les deux états vides quand rien n’est dû ni versé', async () => {
    const el = await render();

    const empties = el.querySelectorAll('.pf-state');
    expect(Array.from(empties).some((n) => n.textContent?.includes('Rien en attente'))).toBe(true);
    expect(
      Array.from(empties).some((n) => n.textContent?.includes('Aucun versement reçu')),
    ).toBe(true);
  });

  it('affiche une dette en attente avec son libellé et son montant', async () => {
    const due: PartnerDueSelf = {
      id: 1,
      reference: 'DUE-1',
      source: { type: 'Booking', label: 'Villa Almadies' },
      gross_xof: 200_000,
      net_xof: 176_000,
      status: 'exigible',
      status_label: 'Exigible',
      eligible_at: '2026-08-01T00:00:00Z',
      created_at: '2026-08-01T00:00:00Z',
    };
    payouts.duesResult = of(paginated([due]));

    const el = await render();

    expect(el.textContent).toContain('Villa Almadies');
    expect(el.textContent).toContain('176');
  });

  it('affiche l’historique avec un lien de justificatif quand il existe', async () => {
    const payout: PartnerPayoutSelf = {
      id: 9,
      reference: 'PAY-9',
      amount_xof: 176_000,
      status: 'paye',
      status_label: 'Payé',
      method: 'wave',
      paid_at: '2026-08-05T00:00:00Z',
      has_proof: true,
      proof_original_name: 'recu.jpg',
      proof_url: 'https://api.test/payouts/9/proof/mine?signature=abc',
      created_at: '2026-08-05T00:00:00Z',
    };
    payouts.payoutsResult = of(paginated([payout]));

    const el = await render();

    const lien = el.querySelector('.pp-proof') as HTMLAnchorElement | null;
    expect(lien).not.toBeNull();
    expect(lien?.getAttribute('href')).toBe(payout.proof_url);
  });

  it('affiche une erreur récupérable si le chargement échoue', async () => {
    payouts.duesResult = throwError(() => new Error('panne'));

    const el = await render();

    expect(el.textContent).toContain('Impossible de charger vos reversements');
  });
});
