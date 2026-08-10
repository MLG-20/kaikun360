import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';

import { TrashItem, TrashService, TrashType } from '../../core/api/trash.service';

/**
 * Corbeille d'un espace connecté (F11.4, étendue à l'espace client en F11.5).
 *
 * ⚠️ **Un seul écran pour tout ce qu'on range**, et c'est le cœur de la
 * demande : le besoin exprimé était d'**alléger les onglets** sans rien perdre.
 * Une corbeille par onglet aurait remplacé une liste encombrée par plusieurs
 * listes encombrées.
 *
 * ⚠️ **Deux familles y cohabitent, et elles ne promettent PAS la même chose** —
 * c'est la seule subtilité de cet écran :
 *   - `kind: 'listing'` → une **annonce** (bien, véhicule, nuitée…), supprimée
 *     pour de bon au bout de 30 jours, et qui revient **éteinte** ;
 *   - `kind: 'record'` → un **dossier** du client (demande, réservation,
 *     discussion, notification), **jamais** supprimé — il est partagé avec
 *     Kaikun — et qui revient **tel quel**.
 *
 * L'utilisateur n'a pas à connaître cette distinction : il lit un compte à
 * rebours quand il y en a un, et rien quand il n'y en a pas. Mais l'écran, lui,
 * ne doit jamais promettre une purge qui n'aura pas lieu ni un retour hors
 * ligne qui ne se produira pas.
 *
 * ⚠️ **On ne range rien depuis cet écran.** Ranger reste le geste de l'écran
 * d'origine (« Mes biens », « Mes demandes », « Messages »…), qui connaît ses
 * règles et sait refuser — un bien sous mandat actif, une réservation à venir,
 * un fil non lu. Ici on ne fait que regarder et restaurer.
 *
 * ⚠️ **Le composant est PARTAGÉ** par les espaces propriétaire, prestataire et
 * client : la corbeille du serveur est déjà filtrée par la personne connectée,
 * elle n'a pas besoin de savoir dans quel espace elle s'affiche.
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
  /** Le serveur a-t-il coupé la liste ? (et combien y a-t-il réellement ?) */
  protected readonly truncated = signal(false);
  protected readonly total = signal(0);
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

  /**
   * La corbeille contient-elle au moins une ANNONCE ?
   *
   * ⚠️ Sert à n'afficher la phrase sur la purge des 30 jours que lorsqu'elle
   * concerne réellement quelque chose. Dans l'espace client, qui n'a aucune
   * annonce, l'annoncer d'entrée ferait craindre une suppression qui n'arrivera
   * jamais — et la note sur la republication ne veut rien dire non plus.
   */
  protected readonly hasListings = computed(() =>
    this.items().some((item) => item.kind === 'listing'),
  );

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
        this.truncated.set(res.data.truncated);
        this.total.set(res.data.total);
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
        // ⚠️ Deux phrases distinctes, parce que les deux familles ne reviennent
        // pas dans le même état : promettre « hors ligne » sur une réservation
        // ferait croire à un changement de statut qui n'a pas eu lieu.
        this.done.set(
          item.kind === 'listing'
            ? `« ${item.label} » est de retour dans votre liste, hors ligne. Republiez-le quand vous le souhaitez.`
            : `« ${item.label} » est de retour dans votre liste, tel que vous l'aviez laissé.`,
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
      // Les cinq annonces (F11.4).
      property: 'Bien immobilier',
      stay: 'Offre de nuitée',
      vehicle: 'Véhicule',
      experience: 'Expérience',
      mobility: 'Trajet',
      // Les quatre dossiers du client (F11.5).
      request: 'Demande',
      booking: 'Réservation',
      conversation: 'Discussion',
      notification: 'Notification',
    }[type];
  }

  /**
   * Ce qu'il advient de la ligne, écrit en toutes lettres.
   *
   * ⚠️ **Le compte à rebours ne vaut QUE pour les annonces.** Un dossier masqué
   * n'est jamais supprimé — lui coller « supprimé dans 30 jours » serait une
   * promesse fausse dans les deux sens : elle inquiéterait pour rien, et elle
   * ferait croire à un ménage automatique qui n'aura jamais lieu.
   *
   * ⚠️ Pour une annonce, `0` ne veut PAS dire « déjà supprimée » : la purge est
   * une tâche planifiée qui passe une fois par nuit. « Aujourd'hui » est donc
   * la seule formulation honnête.
   */
  protected countdown(item: TrashItem): string {
    if (item.days_left === null) {
      return 'Conservé — rien ne sera supprimé';
    }

    if (item.days_left <= 0) return 'Supprimé définitivement aujourd’hui';
    if (item.days_left === 1) return 'Supprimé définitivement demain';

    return `Supprimé définitivement dans ${item.days_left} jours`;
  }

  /**
   * Le compte à rebours devient alarmant sous une semaine — jamais pour un
   * dossier, qui n'a rien à craindre du temps qui passe.
   */
  protected urgent(item: TrashItem): boolean {
    return item.days_left !== null && item.days_left <= 7;
  }
}
