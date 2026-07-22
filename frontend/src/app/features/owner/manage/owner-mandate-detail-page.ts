import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { ManageService } from '../../../core/api/manage.service';
import { Mandate, MonthlyReport } from '../../../models/manage.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import {
  incidentTone,
  mandateLocality,
  mandatePropertyTitle,
  mandateTone,
  payoutTone,
  rentTone,
} from './mandate-status';

/** État de chargement de la fiche. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'failed';

/** Option du sélecteur de mois du rapport (valeur `YYYY-MM` + libellé français). */
interface MonthOption {
  value: string;
  label: string;
}

@Component({
  selector: 'app-owner-mandate-detail-page',
  imports: [DatePipe, BackLinkComponent],
  templateUrl: './owner-mandate-detail-page.html',
  styleUrl: './owner-mandate-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Fiche d'un mandat de gestion locative (F4.4), montée sous
 * `/espace-proprietaire/gestion-locative/:id`. Atteinte en cliquant une carte
 * depuis « Gestion locative ».
 *
 * Charge le mandat (`GET /manage/mandates/{id}`, réservé au propriétaire — 404
 * sinon) avec ses lignes détaillées (loyers, reversements, incidents) et en
 * présente : un résumé financier, les listes de lignes, et surtout un **rapport
 * mensuel** (`GET .../report?month=YYYY-MM`) recalculable via un sélecteur de
 * mois. Les mandats sont établis par les agents Kaikun ; le propriétaire
 * consulte (aucune action de modification ici).
 */
export class OwnerMandateDetailPageComponent {
  private readonly manage = inject(ManageService);
  private readonly route = inject(ActivatedRoute);

  // — État de la fiche (mandat) —
  protected readonly state = signal<LoadState>('loading');
  protected readonly mandate = signal<Mandate | null>(null);
  private readonly mandateId = signal<number | string | null>(null);

  // — État du rapport mensuel (chargé séparément, rechargé au changement de mois) —
  protected readonly report = signal<MonthlyReport | null>(null);
  protected readonly reportLoading = signal(false);
  protected readonly reportError = signal(false);
  /** Mois sélectionné, format `YYYY-MM` (mois courant par défaut). */
  protected readonly selectedMonth = signal<string>(currentMonth());
  /** Les 12 derniers mois, du plus récent au plus ancien (options du sélecteur). */
  protected readonly monthOptions: readonly MonthOption[] = lastMonths(12);

  // Helpers de présentation (tonalités des pastilles + titres).
  protected readonly titleOf = mandatePropertyTitle;
  protected readonly localityOf = mandateLocality;
  protected readonly mandateTone = mandateTone;
  protected readonly rentTone = rentTone;
  protected readonly payoutTone = payoutTone;
  protected readonly incidentTone = incidentTone;
  protected readonly fcfa = (n: number | null | undefined): string | null => formatFcfa(n ?? null);

  /** Le mandat porte-t-il au moins une ligne (loyer / reversement / incident) ? */
  protected readonly hasLines = computed(() => {
    const m = this.mandate();
    return !!(m?.rents?.length || m?.payouts?.length || m?.incidents?.length);
  });

  /**
   * Charge le mandat dès que l'identifiant de route est connu. `switchMap`
   * annule une requête précédente si l'on change de mandat.
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((params) => params.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.mandate.set(null);
        this.report.set(null);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        this.mandateId.set(id);
        return this.manage.getMandate(id).pipe(
          tap((env) => {
            this.mandate.set(env.data.mandate);
            this.state.set('ready');
            // Le mandat est chargé : on récupère le rapport du mois courant.
            this.loadReport();
          }),
          catchError((err: { status?: number }) => {
            this.state.set(err?.status === 404 ? 'notfound' : 'failed');
            return of(null);
          }),
        );
      }),
    ),
  );

  /** Recharge le rapport mensuel pour le mandat et le mois sélectionnés. */
  protected loadReport(): void {
    const id = this.mandateId();
    if (id == null) {
      return;
    }
    this.reportLoading.set(true);
    this.reportError.set(false);
    this.manage.mandateReport(id, this.selectedMonth()).subscribe({
      next: (env) => {
        this.report.set(env.data.report);
        this.reportLoading.set(false);
      },
      error: () => {
        this.reportLoading.set(false);
        this.reportError.set(true);
      },
    });
  }

  /** Change le mois du rapport (déclenché par le `<select>`) et le recharge. */
  protected onMonthChange(value: string): void {
    this.selectedMonth.set(value);
    this.loadReport();
  }
}

/** Mois courant au format `YYYY-MM`. */
function currentMonth(): string {
  return new Date().toISOString().slice(0, 7);
}

/**
 * Construit la liste des `count` derniers mois (mois courant inclus), du plus
 * récent au plus ancien, avec un libellé français (« juillet 2026 »). Sert au
 * sélecteur de mois du rapport.
 */
function lastMonths(count: number): MonthOption[] {
  const fmt = new Intl.DateTimeFormat('fr', { month: 'long', year: 'numeric' });
  const now = new Date();
  const options: MonthOption[] = [];
  for (let i = 0; i < count; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    options.push({ value, label: fmt.format(d) });
  }
  return options;
}
