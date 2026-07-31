import { HttpErrorResponse } from '@angular/common/http';
import {
  ChangeDetectionStrategy,
  Component,
  HostListener,
  computed,
  inject,
  input,
  linkedSignal,
  output,
  signal,
} from '@angular/core';

import { AdminService, QueueMedia, QueueMediaItem } from '../../../../core/api/admin.service';

/**
 * Revue des médias d'une ressource en back-office (F8.1).
 *
 * Répond à un manque de fond du poste de commandement : un agent validait une
 * annonce sans jamais voir ses photos, donc publiait sur le site vitrine à
 * l'aveugle. Ce composant montre la galerie et permet d'**écarter une photo**
 * plutôt que de refuser toute l'annonce.
 *
 * Deux usages :
 * - **compact** (`compact` à vrai) : bande de vignettes dans une file, pour
 *   juger d'un coup d'œil sans quitter l'écran ;
 * - **complet** : galerie entière avec boutons de modération, sur le dossier.
 *
 * Un média masqué reste AFFICHÉ ici, grisé et étiqueté : sans ça, un agent ne
 * pourrait jamais rétablir une photo qu'il vient d'écarter.
 */
@Component({
  selector: 'app-media-review',
  templateUrl: './media-review.html',
  styleUrl: './media-review.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MediaReviewComponent {
  private readonly admin = inject(AdminService);

  /** Galerie à examiner (compteurs + vignettes). */
  readonly media = input.required<QueueMedia>();

  /** Nom de la ressource, pour les textes alternatifs. */
  readonly label = input('');

  /** Bande de vignettes réduite (file) plutôt que galerie complète (dossier). */
  readonly compact = input(false);

  /**
   * Autorise les boutons masquer/réafficher. À laisser à faux partout où
   * l'agent n'a qu'à consulter (catalogue) : l'API refuserait de toute façon
   * sans la permission du type parent, autant ne pas proposer le geste.
   */
  readonly canModerate = input(false);

  /** Émis après un masquage/réaffichage réussi, pour rafraîchir les compteurs. */
  readonly moderated = output<QueueMediaItem>();

  /**
   * Liste locale : repart de l'entrée à chaque changement, et absorbe les
   * modifications de statut sans attendre un rechargement complet de la file.
   */
  protected readonly items = linkedSignal<QueueMedia, QueueMediaItem[]>({
    source: this.media,
    computation: (media) => media.items,
  });

  /** Index de la vignette ouverte en plein écran, ou null si fermé. */
  protected readonly openedAt = signal<number | null>(null);

  /** Média en cours de modération (verrouille son bouton). */
  protected readonly moderatingId = signal<number | null>(null);

  /** Message d'erreur d'une action de modération. */
  protected readonly error = signal<string | null>(null);

  /** Média actuellement affiché en plein écran. */
  protected readonly opened = computed<QueueMediaItem | null>(() => {
    const index = this.openedAt();
    return index === null ? null : (this.items()[index] ?? null);
  });

  /** Vrai si la ressource n'a aucun média — en soi un motif de vigilance. */
  protected readonly isEmpty = computed(() => this.media().total === 0);

  /**
   * Nombre de médias non montrés par l'aperçu de la file (« +3 »).
   * Toujours 0 sur le dossier complet, qui renvoie toute la galerie.
   */
  protected readonly overflow = computed(() =>
    Math.max(0, this.media().total - this.items().length),
  );

  /** Ouvre le plein écran sur une vignette. */
  protected open(index: number): void {
    this.openedAt.set(index);
  }

  protected close(): void {
    this.openedAt.set(null);
  }

  /** Feuillette le plein écran (bornes circulaires). */
  protected step(delta: number): void {
    const index = this.openedAt();
    const total = this.items().length;
    if (index === null || total === 0) return;
    this.openedAt.set((index + delta + total) % total);
  }

  /** Raccourcis clavier du plein écran : flèches pour circuler, Échap pour fermer. */
  @HostListener('document:keydown', ['$event'])
  protected onKeydown(event: KeyboardEvent): void {
    if (this.openedAt() === null) return;

    if (event.key === 'Escape') {
      this.close();
    } else if (event.key === 'ArrowRight') {
      this.step(1);
    } else if (event.key === 'ArrowLeft') {
      this.step(-1);
    }
  }

  /** Masque ou réaffiche un média. */
  protected toggleHidden(item: QueueMediaItem): void {
    if (this.moderatingId() !== null) return;

    this.moderatingId.set(item.id);
    this.error.set(null);

    this.admin.moderateMedia(item.id, !item.is_hidden).subscribe({
      next: (updated) => {
        this.moderatingId.set(null);
        this.items.update((list) => list.map((m) => (m.id === updated.id ? updated : m)));
        this.moderated.emit(updated);
      },
      error: (error: HttpErrorResponse) => {
        this.moderatingId.set(null);
        this.error.set(
          error.status === 403
            ? "Vous n'avez pas le droit de modérer les médias de ce type."
            : 'Modération impossible pour le moment. Réessayez.',
        );
      },
    });
  }

  /** Poids d'un fichier en unité lisible. */
  protected weight(bytes: number | null): string {
    if (!bytes) return '';
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} Ko`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
  }

  /** Texte alternatif d'une vignette. */
  protected altFor(index: number): string {
    const base = this.label() || 'Ressource';
    return `${base} — média ${index + 1} sur ${this.media().total}`;
  }
}
