import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';

import { TrashItem, TrashService, TrashType } from '../../core/api/trash.service';

/**
 * Corbeille d'un espace connecté (F11.4).
 *
 * ⚠️ **Un seul écran pour les cinq types d'annonces**, et c'est le cœur de la
 * demande : le besoin exprimé était d'**alléger les onglets** sans rien perdre.
 * Une corbeille par onglet aurait remplacé une liste encombrée par cinq listes
 * encombrées.
 *
 * ⚠️ **On ne supprime rien depuis cet écran.** Ranger une annonce reste le
 * geste de son propre écran (« Mes biens », « Mes véhicules »…), qui connaît
 * ses règles et sait refuser — un bien sous mandat actif, une offre déjà
 * réservée. Ici on ne fait que regarder et restaurer.
 *
 * ⚠️ **Le composant est PARTAGÉ** entre l'espace propriétaire et l'espace
 * prestataire : la corbeille du serveur est déjà filtrée par la personne
 * connectée, elle n'a pas besoin de savoir dans quel espace elle s'affiche.
 */
@Component({
  selector: 'app-trash-page',
  templateUrl: './trash-page.html',
  styleUrl: './trash-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TrashPageComponent {
  private readonly api = inject(TrashService);

  protected readonly items = signal<TrashItem[]>([]);
  protected readonly retentionDays = signal(30);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

  /**
   * Identifiant de la ligne en cours de restauration (`type:id`), pour
   * n'endormir QUE son bouton. Un drapeau booléen global aurait figé toute la
   * liste pendant qu'une seule ligne travaille.
   */
  protected readonly restoring = signal<string | null>(null);

  /** Message de confirmation après une restauration réussie. */
  protected readonly done = signal<string | null>(null);

  protected readonly empty = computed(() => !this.loading() && this.items().length === 0);

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(null);

    this.api.contents().subscribe({
      next: (res) => {
        this.items.set(res.data.items);
        this.retentionDays.set(res.data.retention_days);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Impossible de charger votre corbeille pour le moment.');
        this.loading.set(false);
      },
    });
  }

  protected restore(item: TrashItem): void {
    const cle = this.key(item);
    this.restoring.set(cle);
    this.done.set(null);

    this.api.restore(item.type, item.id).subscribe({
      next: () => {
        // Retirée de la liste sans recharger : le serveur vient de confirmer,
        // une seconde requête ne dirait rien de plus.
        this.items.update((liste) => liste.filter((i) => this.key(i) !== cle));
        this.restoring.set(null);
        this.done.set(
          `« ${item.label} » est de retour dans votre liste, hors ligne. Republiez-le quand vous le souhaitez.`,
        );
      },
      error: () => {
        this.restoring.set(null);
        this.error.set('La restauration a échoué. Réessayez dans un instant.');
      },
    });
  }

  protected key(item: TrashItem): string {
    return `${item.type}:${item.id}`;
  }

  /** Libellé du type, pour l'étiquette de chaque ligne. */
  protected typeLabel(type: TrashType): string {
    return {
      property: 'Bien immobilier',
      stay: 'Offre de nuitée',
      vehicle: 'Véhicule',
      experience: 'Expérience',
      mobility: 'Trajet',
    }[type];
  }

  /**
   * Compte à rebours écrit en toutes lettres.
   *
   * ⚠️ `0` ne veut PAS dire « déjà supprimé » : la purge est une tâche
   * planifiée qui passe une fois par nuit. Dire « aujourd'hui » est donc la
   * seule formulation honnête.
   */
  protected countdown(days: number): string {
    if (days <= 0) return 'Supprimé définitivement aujourd’hui';
    if (days === 1) return 'Supprimé définitivement demain';

    return `Supprimé définitivement dans ${days} jours`;
  }

  /** Le compte à rebours devient alarmant sous une semaine. */
  protected urgent(days: number): boolean {
    return days <= 7;
  }
}
