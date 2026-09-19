import { isPlatformBrowser } from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  HostListener,
  OnDestroy,
  PLATFORM_ID,
  computed,
  effect,
  inject,
  input,
  signal,
} from '@angular/core';

/** Délai entre deux images en défilement automatique (F21.1, retour client). */
const DELAI_DEFILEMENT_MS = 4000;

/**
 * Galerie photo (F0.4, enrichie en F2.6) — image principale + bande de
 * miniatures cliquables, avec vue plein écran (« lightbox »).
 *
 * Parcours utilisateur :
 * - on voit une grande photo et, dessous, des vignettes ; cliquer une vignette
 *   change la grande photo ;
 * - des flèches ‹ › (et les touches ←/→ du clavier) permettent de feuilleter ;
 * - les photos défilent aussi TOUTES SEULES (F21.1), en pause dès que la
 *   souris survole la galerie ou qu'on ouvre le plein écran — un visiteur qui
 *   regarde une photo ne doit pas la voir changer sous ses yeux ;
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
export class GalleryComponent implements OnDestroy {
  private readonly estNavigateur = isPlatformBrowser(inject(PLATFORM_ID));
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

  /** Vrai tant que la souris survole la galerie (met le défilement en pause). */
  private readonly survolee = signal(false);

  private minuteur: ReturnType<typeof setInterval> | null = null;

  constructor() {
    // Démarre le défilement au premier rendu, puis le recale dès que la liste
    // de photos, l'état du plein écran ou le survol changent.
    effect(() => {
      this.count();
      this.survolee();
      this.lightboxOpen();
      this.planifierDefilement();
    });
  }

  /**
   * Défilement automatique (F21.1) : un `setInterval`, jamais côté serveur (le
   * SSR n'a pas de souris pour le mettre en pause, et un minuteur qui tourne
   * sans jamais être nettoyé fuirait à chaque rendu). Redémarré à chaque
   * bascule de `images()` (miniatures cliquées, plein écran ouvert/fermé, ou
   * survol) plutôt que planifié une fois pour toutes.
   */
  private planifierDefilement(): void {
    this.arreterDefilement();
    if (!this.estNavigateur || !this.hasMany() || this.survolee() || this.lightboxOpen()) {
      return;
    }
    this.minuteur = setInterval(() => this.next(), DELAI_DEFILEMENT_MS);
  }

  private arreterDefilement(): void {
    if (this.minuteur) {
      clearInterval(this.minuteur);
      this.minuteur = null;
    }
  }

  /** Met le défilement en pause : le visiteur regarde une photo précise. */
  @HostListener('mouseenter')
  @HostListener('focusin')
  protected pauseDefilement(): void {
    this.survolee.set(true);
  }

  /** Reprend le défilement quand la souris (ou le focus) quitte la galerie. */
  @HostListener('mouseleave')
  @HostListener('focusout')
  protected reprendreDefilement(): void {
    this.survolee.set(false);
  }

  ngOnDestroy(): void {
    this.arreterDefilement();
  }

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
