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
 *   - `<h2 appReveal="title">` → un **balayage** (clip-path) révèle le titre de
 *     gauche à droite, comme un rideau qu'on ouvre — réservé aux titres de
 *     section, en plus (pas à la place) du `appReveal` du bloc qui les entoure.
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

  /**
   * `''` = l'élément apparaît ; `'group'` = ses enfants apparaissent en
   * cascade ; `'title'` = balayage (clip-path) de gauche à droite.
   */
  readonly appReveal = input<'' | 'group' | 'title'>('');

  constructor() {
    afterNextRender(() => {
      const el = this.host.nativeElement;
      const isTitle = this.appReveal() === 'title';
      const baseClass = this.appReveal() === 'group' ? 'reveal-group' : isTitle ? 'reveal-title' : 'reveal';
      el.classList.add(baseClass);

      // ⚠️ Le `clip-path` du balayage doit porter sur un span INTERNE, jamais
      // sur l'élément observé lui-même : Chrome considère qu'un élément
      // `clip-path: inset(0 100% 0 0)` (surface visible nulle) n'intersecte
      // JAMAIS le viewport — `isIntersecting` reste bloqué à `false` pour
      // toujours, et le titre restait invisible en permanence (bug constaté
      // au premier essai). L'IntersectionObserver observe donc `el` (jamais
      // découpé), tandis que le style de balayage s'applique à cet enfant.
      if (isTitle) {
        const inner = document.createElement('span');
        inner.className = 'reveal-title-inner';
        while (el.firstChild) {
          inner.appendChild(el.firstChild);
        }
        el.appendChild(inner);
      }

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
