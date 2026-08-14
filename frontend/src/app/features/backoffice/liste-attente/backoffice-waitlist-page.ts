import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { AdminService, AdminWaitlistEntry } from '../../../core/api/admin.service';

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
 * Écran **Liste d'attente** du back-office (2026-08-14).
 *
 * ⚠️ **Cet écran comble un trou.** Depuis le lancement de la liste d'attente
 * publique (`/liste-attente`), une inscription n'était visible NULLE PART au
 * back-office — pas dans l'annuaire des comptes (un inscrit n'a pas de
 * compte, c'est un prospect anonyme), aucun autre écran. La seule trace était
 * l'e-mail d'alerte (`NewWaitlistEntryNotification`), qui reste inchangé.
 *
 * Même patron que l'onglet « Messages de contact » (F8.15.c) : filtrable par
 * statut, changement de statut qui enregistre l'agent et l'horodatage. Ajout
 * du filtre par catégorie, propre à la liste d'attente (5 catégories).
 * Aucune fiche séparée : les champs `details` tiennent en quelques lignes,
 * contrairement au texte libre d'un message de contact.
 */
@Component({
  selector: 'app-backoffice-waitlist-page',
  imports: [FormsModule, RouterLink],
  templateUrl: './backoffice-waitlist-page.html',
  styleUrl: './backoffice-waitlist-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeWaitlistPageComponent {
  private readonly admin = inject(AdminService);

  protected readonly rows = signal<AdminWaitlistEntry[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);
  /** Nombre d'inscriptions NON TRAITÉES, quel que soit le filtre. */
  protected readonly pending = signal(0);

  protected category = '';
  /** Défaut : « à traiter » — l'écran s'ouvre sur ce qui reste à faire. */
  protected status = 'nouveau';

  /** Inscription dont le changement de statut est en vol (bouton neutralisé). */
  protected readonly busyId = signal<number | null>(null);

  protected readonly categories = [
    { value: 'proprietaire', label: 'Propriétaire' },
    { value: 'prestataire', label: 'Prestataire' },
    { value: 'client', label: 'Client intéressé' },
    { value: 'team_building', label: 'Team building' },
    { value: 'diaspora', label: 'Diaspora' },
  ];

  constructor() {
    this.load();
  }

  protected applyFilters(): void {
    this.page.set(1);
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(false);

    this.admin
      .waitlistEntries({
        category: this.category || undefined,
        status: this.status || undefined,
        page: this.page(),
      })
      .subscribe({
        next: (paginated) => {
          this.rows.set(paginated.data);
          this.total.set(paginated.meta.total);
          this.lastPage.set(paginated.meta.last_page);
          this.pending.set(paginated.meta.pending ?? 0);
          this.loading.set(false);
        },
        error: () => {
          this.error.set(true);
          this.loading.set(false);
        },
      });
  }

  protected goTo(page: number): void {
    if (page < 1 || page > this.lastPage() || page === this.page()) return;
    this.page.set(page);
    this.load();
  }

  /**
   * Marque traité / rouvre. Le serveur enregistre l'agent et l'horodatage — la
   * ligne est donc remplacée par sa version fraîche plutôt qu'une bascule
   * locale, qui afficherait « traité par personne ».
   */
  protected toggleHandled(entry: AdminWaitlistEntry): void {
    const cible = entry.status === 'traite' ? 'nouveau' : 'traite';
    this.busyId.set(entry.id);

    this.admin.setWaitlistEntryStatus(entry.id, cible).subscribe({
      next: (fresh) => {
        this.busyId.set(null);
        this.pending.update((n) => (cible === 'traite' ? Math.max(0, n - 1) : n + 1));
        // Sous filtre de statut, la ligne quitte la vue : la garder afficherait
        // une inscription « traitée » dans une liste intitulée « à traiter ».
        if (this.status !== '' && this.status !== cible) {
          this.rows.update((rows) => rows.filter((r) => r.id !== entry.id));
        } else {
          this.rows.update((rows) => rows.map((r) => (r.id === fresh.id ? fresh : r)));
        }
      },
      error: () => {
        this.busyId.set(null);
        this.error.set(true);
      },
    });
  }

  // --- Présentation -----------------------------------------------------------

  /** Les champs `details` en clair, propres à la catégorie de l'inscrit. */
  protected detailLines(details: Record<string, unknown>): { label: string; value: string }[] {
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

  /** Ancienneté en clair, même formule que les autres files du back-office. */
  protected age(iso: string | null): string {
    if (!iso) return '—';
    const jours = Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000);
    if (jours <= 0) return "aujourd'hui";
    if (jours === 1) return 'hier';
    return `il y a ${jours} jours`;
  }
}
