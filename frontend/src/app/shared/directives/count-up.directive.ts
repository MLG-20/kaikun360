import { Directive, ElementRef, afterNextRender, inject, input } from '@angular/core';

/**
 * Anime un nombre de 0 jusqu'à sa valeur finale **quand il entre à l'écran**
 * (bandeau de statistiques de l'accueil). Conserve tout habillage non chiffré
 * de la valeur (ex. « 100 % » compte jusqu'à 100 en gardant le « % »).
 *
 * Usage : `<span [appCountUp]="stat.value">{{ stat.value }}</span>`
 * — l'interpolation sert de **repli SSR / sans-JS** (valeur finale rendue par le
 * serveur) ; dans le navigateur, la directive reprend la main et anime.
 *
 * ⚠️ SSR : mise en place dans `afterNextRender` (navigateur uniquement).
 * ♿ `prefers-reduced-motion` : affiche directement la valeur finale.
 */
@Directive({
  selector: '[appCountUp]',
})
export class CountUpDirective {
  private readonly host: ElementRef<HTMLElement> = inject(ElementRef);

  /** Valeur finale telle qu'affichée, ex. `'14'`, `'100 %'`, `'5'`. */
  readonly appCountUp = input.required<string>();

  constructor() {
    afterNextRender(() => {
      const el = this.host.nativeElement;
      const raw = this.appCountUp();

      // Isole la 1re séquence de chiffres (espaces internes de milliers admis,
      // mais PAS l'espace final : « 100 % » → « 100 », pour garder le « % »).
      const match = raw.match(/\d+(?:\s\d+)*/);
      const target = match ? parseInt(match[0].replace(/\s/g, ''), 10) : NaN;
      const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;

      // Rien à animer (pas de nombre), animations réduites ou API absente : on
      // laisse la valeur finale (déjà rendue par l'interpolation).
      if (!match || Number.isNaN(target) || reduce || typeof IntersectionObserver === 'undefined') {
        return;
      }

      const render = (value: number): string => raw.replace(match[0], String(value));
      el.textContent = render(0); // point de départ

      const observer = new IntersectionObserver(
        (entries, obs) => {
          for (const entry of entries) {
            if (entry.isIntersecting) {
              this.animate(el, target, render);
              obs.unobserve(entry.target);
            }
          }
        },
        { threshold: 0.6 },
      );
      observer.observe(el);
    });
  }

  /** Compte de 0 à `target` sur ~1,4 s, avec un ralenti en fin (ease-out cubique). */
  private animate(el: HTMLElement, target: number, render: (v: number) => string): void {
    const duration = 1400;
    const start = performance.now();
    const tick = (now: number): void => {
      const progress = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = render(Math.round(target * eased));
      if (progress < 1) {
        requestAnimationFrame(tick);
      }
    };
    requestAnimationFrame(tick);
  }
}
