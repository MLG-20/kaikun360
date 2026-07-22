import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  afterNextRender,
  computed,
  inject,
  signal,
} from '@angular/core';

/** Rayon du cercle de progression (dans le viewBox 48×48 du SVG). */
const RING_RADIUS = 22;
/** Circonférence = 2πr — longueur totale du trait de progression. */
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;
/** Défilement (px) au-delà duquel le bouton apparaît. */
const SHOW_AFTER = 400;

/**
 * Bouton flottant **« retour en haut »**, global à toute l'application.
 *
 * Monté une seule fois dans la racine (`app.html`) : toutes les pages en
 * héritent, quel que soit leur layout (public, espaces connectés, auth). Il
 * n'apparaît qu'une fois la page suffisamment défilée, et un **anneau de
 * progression** matérialise la position de lecture — un détail premium.
 *
 * ⚠️ **SSR / hydratation** : l'écoute du défilement est posée dans
 * `afterNextRender` (navigateur uniquement) et retirée à la destruction.
 * ♿ Respecte `prefers-reduced-motion` (défilement instantané plutôt qu'animé).
 */
@Component({
  selector: 'app-scroll-top',
  templateUrl: './scroll-top.html',
  styleUrl: './scroll-top.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ScrollTopComponent {
  private readonly destroyRef = inject(DestroyRef);

  /** Le bouton est-il affiché (page assez défilée) ? */
  protected readonly visible = signal(false);
  /** Progression de lecture, de 0 (haut) à 1 (bas). */
  private readonly progress = signal(0);

  /** Longueur du dasharray (constante) — expose au template. */
  protected readonly circumference = RING_CIRCUMFERENCE;
  /** Décalage du trait : plein en bas de page, vide en haut. */
  protected readonly dashOffset = computed(() => RING_CIRCUMFERENCE * (1 - this.progress()));

  /** Garde anti-rafale : une seule mesure par frame d'animation. */
  private ticking = false;

  constructor() {
    afterNextRender(() => {
      this.measure();
      window.addEventListener('scroll', this.onScroll, { passive: true });
      window.addEventListener('resize', this.onScroll, { passive: true });
      this.destroyRef.onDestroy(() => {
        window.removeEventListener('scroll', this.onScroll);
        window.removeEventListener('resize', this.onScroll);
      });
    });
  }

  /** Handler léger : on ne mesure qu'une fois par frame (rAF). */
  private readonly onScroll = (): void => {
    if (this.ticking) {
      return;
    }
    this.ticking = true;
    requestAnimationFrame(() => {
      this.measure();
      this.ticking = false;
    });
  };

  /** Recalcule visibilité + progression à partir du défilement courant. */
  private measure(): void {
    const scrolled = window.scrollY;
    const scrollable = document.documentElement.scrollHeight - window.innerHeight;
    this.progress.set(scrollable > 0 ? Math.min(1, scrolled / scrollable) : 0);
    this.visible.set(scrolled > SHOW_AFTER);
  }

  /** Remonte en haut de page (animé, sauf préférence de mouvement réduit). */
  protected toTop(): void {
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
    window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
  }
}
