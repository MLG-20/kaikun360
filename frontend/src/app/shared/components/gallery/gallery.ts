import {
  ChangeDetectionStrategy,
  Component,
  HostListener,
  computed,
  input,
  signal,
} from '@angular/core';

/**
 * Galerie photo (F0.4, enrichie en F2.6) — image principale + bande de
 * miniatures cliquables, avec vue plein écran (« lightbox »).
 *
 * Parcours utilisateur :
 * - on voit une grande photo et, dessous, des vignettes ; cliquer une vignette
 *   change la grande photo ;
 * - des flèches ‹ › (et les touches ←/→ du clavier) permettent de feuilleter ;
 * - cliquer la grande photo l'ouvre en PLEIN ÉCRAN (fond assombri) ; on y
 *   navigue au clavier et on ferme avec Échap ou la croix ;
 * - si aucune photo n'est fournie, un encart neutre « Aucune photo disponible »
 *   s'affiche (dégradation gracieuse — les vraies photos arriveront quand les
 *   médias seront exposés par l'API).
 *
 * Reçoit une simple liste d'URLs (`images`). La sélection courante et l'état
 * d'ouverture du plein écran sont gérés par des signaux.
 */
@Component({
  selector: 'app-gallery',
  templateUrl: './gallery.html',
  styleUrl: './gallery.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class GalleryComponent {
  /** Liste des URLs d'images à afficher (peut être vide). */
  readonly images = input.required<string[]>();

  /** Texte alternatif de base (accessibilité). */
  readonly alt = input('Photo');

  /** Index de l'image actuellement affichée en grand. */
  protected readonly selected = signal(0);

  /** Vrai quand la vue plein écran est ouverte. */
  protected readonly lightboxOpen = signal(false);

  /** URL de l'image affichée en grand (ou null si la liste est vide). */
  protected readonly current = computed<string | null>(
    () => this.images()[this.selected()] ?? null,
  );

  /** Nombre total d'images (pour le compteur « i / n » et les bornes). */
  protected readonly count = computed(() => this.images().length);

  /** Vrai s'il y a plus d'une image (affiche flèches, compteur, miniatures). */
  protected readonly hasMany = computed(() => this.count() > 1);

  /** Sélectionne une image par son index (utilisé par les miniatures). */
  protected select(index: number): void {
    this.selected.set(index);
  }

  /** Passe à l'image suivante (boucle à la première après la dernière). */
  protected next(): void {
    const total = this.count();
    if (total > 0) {
      this.selected.update((i) => (i + 1) % total);
    }
  }

  /** Passe à l'image précédente (boucle à la dernière avant la première). */
  protected prev(): void {
    const total = this.count();
    if (total > 0) {
      this.selected.update((i) => (i - 1 + total) % total);
    }
  }

  /** Ouvre la vue plein écran (uniquement s'il y a au moins une image). */
  protected openLightbox(): void {
    if (this.current()) {
      this.lightboxOpen.set(true);
    }
  }

  /** Ferme la vue plein écran. */
  protected closeLightbox(): void {
    this.lightboxOpen.set(false);
  }

  /**
   * Raccourcis clavier, actifs SEULEMENT quand le plein écran est ouvert :
   * Échap ferme, ←/→ feuillettent. On écoute au niveau du document car
   * l'overlay couvre tout l'écran.
   */
  @HostListener('document:keydown', ['$event'])
  protected onKeydown(event: KeyboardEvent): void {
    if (!this.lightboxOpen()) {
      return;
    }
    switch (event.key) {
      case 'Escape':
        this.closeLightbox();
        break;
      case 'ArrowRight':
        event.preventDefault();
        this.next();
        break;
      case 'ArrowLeft':
        event.preventDefault();
        this.prev();
        break;
    }
  }
}
