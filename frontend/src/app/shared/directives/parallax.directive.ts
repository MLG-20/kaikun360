import { Directive, ElementRef, OnDestroy, afterNextRender, inject, input } from '@angular/core';

/**
 * Léger déplacement vertical d'une couche de fond au défilement (F16.1).
 *
 * Posée sur `.k-photo-layer` (voir `styles/_universe.scss`), la couche qui
 * porte l'image de fond d'une section — jamais sur la section elle-même, qui
 * doit rester à sa place dans la mise en page.
 *
 * ⚠️ Volontairement PAS `background-attachment: fixed` : ce raccourci CSS est
 * connu pour se désactiver silencieusement sur iOS Safari. On translate donc
 * un élément normal, borné à quelques pourcents, et seulement pendant que sa
 * section est réellement visible — un `IntersectionObserver` coupe l'écouteur
 * de défilement le reste du temps, pour ne rien faire tourner en arrière-plan
 * sur une page qui a neuf sections.
 *
 * ⚠️ **SSR** : posé dans `afterNextRender`, comme `RevealDirective` — jamais
 * exécuté côté serveur, aucun mismatch d'hydratation possible.
 *
 * ♿ Coupé sous `prefers-reduced-motion: reduce` : la couche reste immobile,
 * la photo elle-même ne change pas.
 */
@Directive({
  selector: '[appParallax]',
})
export class ParallaxDirective implements OnDestroy {
  private readonly host: ElementRef<HTMLElement> = inject(ElementRef);

  /** Amplitude du déplacement, en pourcentage de la hauteur de la couche. */
  readonly appParallaxStrength = input(8);

  private observer?: IntersectionObserver;
  private ticking = false;
  private readonly onScroll = () => this.requestTick();

  constructor() {
    afterNextRender(() => {
      const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
      if (reduce || typeof IntersectionObserver === 'undefined') return;

      this.observer = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting) {
          window.addEventListener('scroll', this.onScroll, { passive: true });
          this.update();
        } else {
          window.removeEventListener('scroll', this.onScroll);
        }
      });
      this.observer.observe(this.host.nativeElement.parentElement ?? this.host.nativeElement);
    });
  }

  ngOnDestroy(): void {
    this.observer?.disconnect();
    window.removeEventListener('scroll', this.onScroll);
  }

  private requestTick(): void {
    if (this.ticking) return;
    this.ticking = true;
    requestAnimationFrame(() => {
      this.update();
      this.ticking = false;
    });
  }

  /**
   * Position de la couche : `0` centrée dans l'écran, jusqu'à `±force` quand
   * sa section touche le haut ou le bas de la fenêtre.
   */
  private update(): void {
    const section = this.host.nativeElement.parentElement;
    if (!section) return;

    const rect = section.getBoundingClientRect();
    const vh = window.innerHeight;
    const progress = (rect.top + rect.height / 2 - vh / 2) / (vh / 2 + rect.height / 2);
    const clamped = Math.max(-1, Math.min(1, progress));

    this.host.nativeElement.style.transform = `translateY(${(-clamped * this.appParallaxStrength()).toFixed(2)}%)`;
  }
}
