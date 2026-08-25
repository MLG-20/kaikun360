import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';

import { PayoutService } from '../../../core/api/payout.service';
import { PartnerDueSelf, PartnerPayoutSelf } from '../../../models/payout.model';
import { formatFcfa } from '../../format/fcfa';

/** Tonalité de la pastille globale `.bk-status[data-tone]` pour une DETTE. */
function dueTone(status: string): 'active' | 'pending' | 'done' {
  switch (status) {
    case 'exigible':
      return 'pending'; // bientôt payée — à surveiller, pas encore un problème.
    case 'payee':
      return 'active';
    default:
      return 'done'; // en_attente (délai) ou annulee.
  }
}

/** Tonalité de la pastille pour un VERSEMENT. */
function payoutTone(status: string): 'active' | 'pending' | 'done' {
  switch (status) {
    case 'paye':
      return 'active';
    case 'en_attente':
      return 'pending';
    default:
      return 'done'; // echoue.
  }
}

/**
 * « Mes reversements » — ce que Kaikun doit ou a déjà versé au partenaire
 * connecté, en lecture seule.
 *
 * Composant **partagé** entre l'espace propriétaire et l'espace prestataire
 * (les deux seuls bénéficiaires possibles du registre F8.16.a), sur le modèle
 * de `shared/components/contact-support/` : un seul écran, monté dans les deux
 * jeux de routes plutôt que dupliqué. Aucune action ici — préparer un lot ou
 * constater un virement restent des gestes d'agent, réservés au back-office.
 */
@Component({
  selector: 'app-partner-payouts',
  imports: [DatePipe],
  templateUrl: './partner-payouts.html',
  styleUrl: './partner-payouts.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PartnerPayoutsComponent {
  private readonly payouts = inject(PayoutService);

  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly dues = signal<PartnerDueSelf[]>([]);
  protected readonly history = signal<PartnerPayoutSelf[]>([]);

  protected readonly fcfa = formatFcfa;
  protected readonly dueTone = dueTone;
  protected readonly payoutTone = payoutTone;

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.loadError.set(false);

    let pending = 2;
    const done = () => {
      pending -= 1;
      if (pending === 0) {
        this.loading.set(false);
      }
    };

    this.payouts.mine().subscribe({
      next: (res) => {
        this.dues.set(res.data);
        done();
      },
      error: () => {
        this.loadError.set(true);
        done();
      },
    });

    this.payouts.minePayouts().subscribe({
      next: (res) => {
        this.history.set(res.data);
        done();
      },
      error: () => {
        this.loadError.set(true);
        done();
      },
    });
  }
}
