import { Directive, ElementRef, afterNextRender, inject, input } from '@angular/core';

/**
 * Révèle un élément (ou les enfants d'une grille) **au défilement** : l'élément
 * démarre légèrement décalé et transparent, puis glisse/apparaît dès qu'il entre
 * dans la fenêtre. Donne à la page d'accueil un rythme vivant sans dépendance.
 *
 * Usage :
 *   - `<section appReveal>` → l'élément lui-même apparaît.
 *   - `<div class="grid" appReveal="group">` → ses enfants directs apparaissent
 *     **en cascade** (délais échelonnés définis en CSS, cf. `styles/_reveal.scss`).
 *
 * ⚠️ **SSR / hydratation** : l'état masqué initial et l'`IntersectionObserver`
 * sont posés dans **`afterNextRender`** — uniquement dans le navigateur, après
 * l'hydratation. Le serveur rend donc le contenu VISIBLE (aucun contenu blanc si
 * le JS tarde, aucun mismatch d'hydratation). Cible du bas de page → l'ajout de
 * la classe masquante se fait hors écran, sans clignotement perceptible.
 *
 * ♿ Respecte `prefers-reduced-motion` : si l'utilisateur a réduit les
 * animations, tout est affiché immédiatement, sans transition.
 */
@Directive({
  selector: '[appReveal]',
})
export class RevealDirective {
  private readonly host: ElementRef<HTMLElement> = inject(ElementRef);

  /** `''` = l'élément apparaît ; `'group'` = ses enfants apparaissent en cascade. */
  readonly appReveal = input<'' | 'group'>('');

  constructor() {
    afterNextRender(() => {
      const el = this.host.nativeElement;
      const baseClass = this.appReveal() === 'group' ? 'reveal-group' : 'reveal';
      el.classList.add(baseClass);

      // Animations réduites ou API absente (très vieux navigateurs) : on affiche.
      const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
      if (reduce || typeof IntersectionObserver === 'undefined') {
        el.classList.add('is-in');
        return;
      }

      const observer = new IntersectionObserver(
        (entries, obs) => {
          for (const entry of entries) {
            if (entry.isIntersecting) {
              entry.target.classList.add('is-in');
              obs.unobserve(entry.target); // une seule fois : pas de re-animation.
            }
          }
        },
        // Se déclenche un peu avant que l'élément soit pleinement visible.
        { threshold: 0.12, rootMargin: '0px 0px -6% 0px' },
      );
      observer.observe(el);
    });
  }
}
