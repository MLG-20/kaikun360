import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { RequestService } from '../../../core/api/request.service';
import { PageMeta } from '../../../core/api/pagination.model';
import { ServiceRequest } from '../../../models/service-request.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';

/**
 * Une étape de la chronologie d'une demande (miroir de l'enum `RequestStatus`
 * backend, machine à états STRICTE : recu → verification → visite → devis →
 * negociation → cloture). L'ordre de ce tableau EST l'ordre des étapes.
 */
interface RequestStep {
  value: string;
  label: string;
}

@Component({
  selector: 'app-requests-page',
  imports: [DatePipe, RouterLink],
  templateUrl: './requests-page.html',
  styleUrl: './requests-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Écran « Mes demandes » de l'espace client (F3.3), monté sous
 * `/mon-espace/demandes`. Liste en lecture seule des demandes de service du
 * client connecté (`GET /requests/my`, paginé 15/page, plus récentes d'abord).
 *
 * Chaque demande est présentée comme une carte : référence, univers, montant et
 * ville renseignés, message, puis une **chronologie de statut** matérialisant la
 * machine à états backend (étapes franchies / étape courante / à venir). Le
 * dépôt de demande se fait depuis les pages publiques (fiches de biens/services,
 * F2.3/F2.7) — cet écran ne fait que suivre l'avancement.
 */
export class RequestsPageComponent {
  private readonly requests = inject(RequestService);

  /** Étapes de la chronologie, dans l'ordre de la machine à états stricte. */
  protected readonly steps: readonly RequestStep[] = [
    { value: 'recu', label: 'Reçu' },
    { value: 'verification', label: 'Vérification' },
    { value: 'visite', label: 'Visite' },
    { value: 'devis', label: 'Devis' },
    { value: 'negociation', label: 'Négociation' },
    { value: 'cloture', label: 'Clôturé' },
  ];

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<ServiceRequest[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);

  /** Y a-t-il d'autres pages avant / après la page courante ? */
  protected readonly hasPrev = computed(() => (this.meta()?.current_page ?? 1) > 1);
  protected readonly hasNext = computed(() => {
    const m = this.meta();
    return !!m && m.current_page < m.last_page;
  });

  constructor() {
    this.load(1);
  }

  /** Charge une page de demandes (remplace la liste affichée). */
  protected load(page: number): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.requests.myRequests(page).subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.meta.set(res.meta);
        this.loading.set(false);
        // Ramène en haut de liste au changement de page (confort de lecture).
        if (typeof window !== 'undefined') {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      },
      error: () => {
        this.loading.set(false);
        this.loadError.set(true);
      },
    });
  }

  /** Page précédente / suivante (bornées par la pagination). */
  protected prev(): void {
    if (this.hasPrev()) {
      this.load((this.meta()?.current_page ?? 2) - 1);
    }
  }

  protected next(): void {
    if (this.hasNext()) {
      this.load((this.meta()?.current_page ?? 0) + 1);
    }
  }

  /** Montant formaté en FCFA (ou null si non renseigné). */
  protected budget(req: ServiceRequest): string | null {
    return formatFcfa(req.budget_xof);
  }

  /**
   * Indice de l'étape correspondant au statut d'une demande dans `steps`
   * (−1 si statut inconnu). Sert à teinter la chronologie.
   */
  protected stepIndex(status: string | null): number {
    return this.steps.findIndex((s) => s.value === status);
  }

  /** État d'une étape par rapport au statut courant de la demande. */
  protected stepState(req: ServiceRequest, i: number): 'done' | 'current' | 'todo' {
    const current = this.stepIndex(req.status);
    if (i < current) {
      return 'done';
    }
    if (i === current) {
      return 'current';
    }
    return 'todo';
  }
}
