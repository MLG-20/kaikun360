import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';

import { TrashItem, TrashListingType, TrashService, TrashType } from '../../../core/api/trash.service';

/**
 * Corbeille du back-office (F21.1) : tout ce que l'utilisateur connecté a
 * supprimé de SES propres offres (biens, nuitées, véhicules, circuits, départs).
 *
 * Il peut les **restaurer** (elles reviennent hors ligne), les **supprimer
 * définitivement** une à une, ou **vider** la corbeille. Sans geste de sa part,
 * la purge nocturne les efface au bout de `retention_days` jours.
 *
 * C'est la corbeille personnelle du serveur (`/me/trash`) : on n'y voit jamais ce
 * que quelqu'un d'autre a supprimé. Les dossiers masqués d'un client n'y figurent
 * pas — ils ne sont jamais supprimés.
 */
@Component({
  selector: 'app-backoffice-trash-page',
  templateUrl: './backoffice-trash-page.html',
  styleUrl: './backoffice-trash-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeTrashPageComponent {
  private readonly api = inject(TrashService);

  private readonly all = signal<TrashItem[]>([]);
  protected readonly items = computed(() => this.all().filter((i) => i.kind === 'listing'));
  protected readonly retentionDays = signal(30);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly done = signal<string | null>(null);

  /** Clé de la ligne en cours de traitement (verrouille ses boutons). */
  protected readonly busy = signal<string | null>(null);
  /** Ligne dont la suppression définitive attend confirmation. */
  protected readonly confirmingKey = signal<string | null>(null);
  protected readonly confirmingEmpty = signal(false);
  protected readonly emptying = signal(false);

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(null);
    this.api.contents().subscribe({
      next: (res) => {
        this.all.set(res.data.items);
        this.retentionDays.set(res.data.retention_days);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Impossible de charger la corbeille pour le moment.');
        this.loading.set(false);
      },
    });
  }

  protected restore(item: TrashItem): void {
    const key = this.key(item);
    this.busy.set(key);
    this.done.set(null);
    this.api.restore(item.type, item.id).subscribe({
      next: () => {
        this.all.update((list) => list.filter((i) => this.key(i) !== key));
        this.busy.set(null);
        this.done.set(`« ${item.label} » est restauré, hors ligne. Republiez-le quand vous le souhaitez.`);
      },
      error: () => {
        this.busy.set(null);
        this.error.set('La restauration a échoué. Réessayez dans un instant.');
      },
    });
  }

  protected purge(item: TrashItem): void {
    const key = this.key(item);
    this.busy.set(key);
    this.done.set(null);
    this.api.purge(item.type as TrashListingType, item.id).subscribe({
      next: () => {
        this.all.update((list) => list.filter((i) => this.key(i) !== key));
        this.busy.set(null);
        this.confirmingKey.set(null);
        this.done.set(`« ${item.label} » est supprimé définitivement.`);
      },
      error: () => {
        this.busy.set(null);
        this.error.set('La suppression a échoué. Réessayez dans un instant.');
      },
    });
  }

  protected emptyTrash(): void {
    this.emptying.set(true);
    this.done.set(null);
    this.api.empty().subscribe({
      next: (res) => {
        this.all.update((list) => list.filter((i) => i.kind !== 'listing'));
        this.emptying.set(false);
        this.confirmingEmpty.set(false);
        this.done.set(`Corbeille vidée : ${res.data.deleted} élément(s) supprimé(s) définitivement.`);
      },
      error: () => {
        this.emptying.set(false);
        this.error.set('Le vidage a échoué. Réessayez dans un instant.');
      },
    });
  }

  protected key(item: TrashItem): string {
    return `${item.type}:${item.id}`;
  }

  protected typeLabel(type: TrashType): string {
    return (
      {
        property: 'Bien immobilier',
        stay: 'Offre de nuitée',
        vehicle: 'Véhicule',
        experience: 'Circuit',
        mobility: 'Départ programmé',
      } as Partial<Record<TrashType, string>>
    )[type] ?? type;
  }

  protected countdown(item: TrashItem): string {
    if (item.days_left === null) return '';
    if (item.days_left <= 0) return 'Supprimé définitivement aujourd’hui';
    if (item.days_left === 1) return 'Supprimé définitivement demain';
    return `Supprimé définitivement dans ${item.days_left} jours`;
  }

  protected urgent(item: TrashItem): boolean {
    return item.days_left !== null && item.days_left <= 7;
  }

  protected shortDate(iso: string): string {
    const date = new Date(iso.replace(' ', 'T'));
    return Number.isNaN(date.getTime())
      ? '—'
      : date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
  }
}
