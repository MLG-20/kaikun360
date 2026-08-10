import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';

/**
 * Bouton « Ranger » — le geste qui envoie un dossier à la corbeille (F11.5).
 *
 * ⚠️ **Purement présentationnel** : il ne connaît ni l'objet rangé, ni la route
 * à appeler. Chaque écran garde son propre appel (« Mes demandes » appelle
 * `RequestService.hide`, « Messages » appelle `MessageService.hide`…), parce que
 * ranger reste le geste de l'écran d'origine. Ce composant n'existe que pour que
 * les quatre écrans disent la MÊME chose de la même façon.
 *
 * ⚠️ **Le mot est « Ranger », pas « Supprimer », et ce n'est pas de la
 * politesse** : rien n'est supprimé. Un client qui lit « Supprimer » sur sa
 * réservation croirait effacer un contrat, hésiterait — ou pire, se croirait
 * autorisé à faire disparaître une preuve avant un litige.
 *
 * ⚠️ **Il ne s'affiche que si le SERVEUR l'autorise** (`hideable`), jamais sur
 * une règle rejouée côté écran : une demande en cours, une réservation à venir,
 * un fil non lu ou une notification non ouverte n'ont pas de bouton du tout —
 * plutôt qu'un bouton qui échoue en 422.
 */
@Component({
  selector: 'app-hide-button',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (hideable()) {
      <button
        type="button"
        class="k-btn k-btn--ghost hide-btn"
        [disabled]="busy()"
        [attr.title]="title()"
        [attr.aria-label]="title()"
        (click)="ranger.emit()">
        @if (busy()) {
          Rangement…
        } @else {
          Ranger
        }
      </button>
    }
  `,
  styles: `
    .hide-btn {
      /* Discret : c'est une action de ménage, pas l'action principale de la
         carte. Elle ne doit jamais concurrencer « Payer » ou « Voir le
         détail ». */
      font-size: 0.85rem;
      padding-inline: 0.75rem;
      white-space: nowrap;
    }
  `,
})
export class HideButtonComponent {
  /** Le serveur autorise-t-il le rangement ? Sinon, aucun bouton n'est rendu. */
  readonly hideable = input(false);

  /** Un appel est-il en vol pour CET élément ? (endort ce bouton, pas la liste). */
  readonly busy = input(false);

  /**
   * Ce que l'infobulle promet. Le défaut convient aux quatre écrans ; on ne le
   * change que pour préciser un cas particulier.
   */
  readonly title = input('Ranger dans la corbeille. Rien n’est supprimé : vous pourrez le restaurer.');

  /** L'utilisateur demande le rangement. */
  readonly ranger = output<void>();
}
