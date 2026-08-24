import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { RequestService } from '../../../core/api/request.service';
import { PageMeta } from '../../../core/api/pagination.model';
import { ServiceRequest } from '../../../models/service-request.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { ConstructionQuotesComponent } from '../../../shared/components/construction-quotes/construction-quotes';
import { HideButtonComponent } from '../../../shared/components/hide-button/hide-button';
import { REQUEST_STEPS, RequestStep, stepState } from './request-timeline';

@Component({
  selector: 'app-requests-page',
  imports: [
    DatePipe,
    RouterLink,
    BackLinkComponent,
    HideButtonComponent,
    ConstructionQuotesComponent,
  ],
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
 *
 * ⚠️ **Accueille aussi le bloc « Mes chantiers & devis » (F3.9)** —
 * `app-construction-quotes`, où le client répond à un devis de construction.
 * Il a fait escale sur l'ancienne page diaspora, puis sur l'accueil de
 * l'espace client ; **rangé ici pour de bon** (2026-08-23) : un chantier EST
 * une demande de service, sa place naturelle est avec les autres, pas sur le
 * tableau de bord.
 */
export class RequestsPageComponent {
  private readonly requests = inject(RequestService);

  /** Étapes de la chronologie (partagées avec l'écran de détail). */
  protected readonly steps: readonly RequestStep[] = REQUEST_STEPS;

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<ServiceRequest[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);

  /**
   * Demande dont le rangement est en cours (requête en vol). ⚠️ Un identifiant
   * et non un booléen global : un drapeau unique figerait toute la liste
   * pendant qu'une seule carte travaille.
   */
  protected readonly hidingId = signal<number | null>(null);
  /** Confirmation de rangement, affichée en tête de liste. */
  protected readonly hidden = signal<string | null>(null);

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

  /**
   * Range une demande clôturée dans la corbeille (F11.5).
   *
   * ⚠️ **Rien n'est supprimé** : la demande quitte cette liste et reste dans le
   * dossier de Kaikun. On la retire de l'affichage sans recharger — le serveur
   * vient de confirmer, une seconde requête ne dirait rien de plus — et sans
   * décrémenter la pagination : la page suivante se recalculera d'elle-même au
   * prochain chargement.
   */
  protected hide(req: ServiceRequest): void {
    this.hidingId.set(req.id);
    this.hidden.set(null);

    this.requests.hide(req.id).subscribe({
      next: () => {
        this.items.update((liste) => liste.filter((r) => r.id !== req.id));
        this.hidingId.set(null);
        this.hidden.set(
          `Demande ${req.reference} rangée dans votre corbeille. Rien n'est supprimé : vous pouvez la restaurer.`,
        );
      },
      error: () => {
        this.hidingId.set(null);
        this.hidden.set(null);
        this.loadError.set(false);
        // ⚠️ Le refus est le cas NORMAL d'une demande encore en cours (422) :
        // on le dit sans dramatiser, et sans casser la liste affichée.
        this.hidden.set(
          'Cette demande ne peut pas être rangée pour le moment — elle est encore en cours de traitement.',
        );
      },
    });
  }

  /** Montant formaté en FCFA (ou null si non renseigné). */
  protected budget(req: ServiceRequest): string | null {
    return formatFcfa(req.budget_xof);
  }

  /** État d'une étape par rapport au statut courant de la demande. */
  protected stepState(req: ServiceRequest, i: number): 'done' | 'current' | 'todo' {
    return stepState(req, i);
  }
}
