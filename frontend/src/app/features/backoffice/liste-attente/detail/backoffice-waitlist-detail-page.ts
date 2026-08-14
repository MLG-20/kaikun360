import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';

import { AdminService, AdminWaitlistEntry } from '../../../../core/api/admin.service';

/** Libellés français des champs `details`, propres à chaque catégorie. */
const DETAIL_LABELS: Record<string, string> = {
  type_bien: 'Type de bien',
  nb_biens: 'Nombre de biens',
  type_service: 'Type de service',
  univers: 'Univers',
  taille_equipe: "Taille de l'équipe",
  budget_xof: 'Budget',
  pays_residence: 'Pays de résidence',
  type_projet: 'Type de projet',
};

/**
 * **Fiche d'une inscription à la liste d'attente** — `/back-office/liste-attente/:id`.
 *
 * Alimentée par `GET /admin/waitlist/{id}`. La liste ne montre que ce qui
 * tient sur une ligne ; cette fiche restitue **tout ce que le prospect a
 * saisi** dans le formulaire public, champs `details` de sa catégorie et
 * précisions libres compris — c'est sa seule raison d'être.
 */
@Component({
  selector: 'app-backoffice-waitlist-detail-page',
  imports: [],
  templateUrl: './backoffice-waitlist-detail-page.html',
  styleUrl: './backoffice-waitlist-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeWaitlistDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  private readonly entryId = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly entry = signal<AdminWaitlistEntry | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);
  protected readonly busy = signal(false);

  constructor() {
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.error.set(false);

    this.admin.waitlistEntry(this.entryId).subscribe({
      next: (entry) => {
        this.entry.set(entry);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  /** Marque traitée / rouvre, comme depuis la liste. */
  protected toggleHandled(): void {
    const current = this.entry();
    if (!current || this.busy()) return;

    const cible = current.status === 'traite' ? 'nouveau' : 'traite';
    this.busy.set(true);

    this.admin.setWaitlistEntryStatus(current.id, cible).subscribe({
      next: (fresh) => {
        this.entry.set(fresh);
        this.busy.set(false);
      },
      error: () => {
        this.busy.set(false);
        this.error.set(true);
      },
    });
  }

  protected back(): void {
    void this.router.navigate(['/back-office', 'liste-attente']);
  }

  // --- Présentation -----------------------------------------------------------

  /** Tous les champs `details` en clair, propres à la catégorie de l'inscrit. */
  protected detailLines(details: Record<string, unknown> | null | undefined): { label: string; value: string }[] {
    return Object.entries(details || {})
      .filter(([, value]) => value !== null && value !== undefined && value !== '')
      .map(([key, value]) => ({
        label: DETAIL_LABELS[key] ?? key,
        value: key === 'budget_xof' ? this.xof(value as number) : String(value),
      }));
  }

  protected xof(value: number): string {
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  protected dateTime(iso: string | null | undefined): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }
}
