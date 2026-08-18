import { Directive, ElementRef, afterNextRender, inject, input } from '@angular/core';

// Horodatage du chargement du module (une fois, au démarrage de l'app côté
// navigateur) — sert à distinguer le tout premier affichage de la page d'une
// recréation plus tardive du même élément (ex. la bande « Sélection du
// moment » qui recrée sa grille à chaque changement d'onglet pour REJOUER
// le fondu, voir `home-page.html`). Voir commentaire dans le constructeur.
const demarrageApplication = typeof performance !== 'undefined' ? performance.now() : 0;

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

      // Déjà dans le champ visible, ET dans les tout premiers instants de vie
      // de l'app (typiquement tout ce qui tient au-dessus de la ligne de
      // flottaison au chargement) : le rendu initial l'a déjà peint pleinement
      // visible, le masquer maintenant pour le ré-afficher une fraction de
      // seconde plus tard créerait un clignotement perçu comme « la page se
      // charge deux fois ». On saute alors directement à l'état final.
      //
      // ⚠️ La fenêtre temporelle est ESSENTIELLE : sans elle, une recréation
      // plus tardive du même élément pendant qu'il est déjà à l'écran (ex. la
      // bande « Sélection du moment » qui recrée sa grille à chaque clic sur
      // un onglet, précisément pour REJOUER le fondu, voir `home-page.html`)
      // sauterait aussi l'animation — la transition entre onglets devenait
      // brutale (bug constaté juste après l'introduction de ce raccourci).
      const rect = el.getBoundingClientRect();
      const dansLeChamp = rect.top < window.innerHeight * 0.94 && rect.bottom > 0;
      const toutDebutDeVie = typeof performance !== 'undefined' && performance.now() - demarrageApplication < 1500;
      const dejaVisible = dansLeChamp && toutDebutDeVie;

      el.classList.add(baseClass);
      if (dejaVisible) {
        el.classList.add('is-in');
      }

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

      // Animations réduites, API absente (très vieux navigateurs), ou déjà
      // révélé ci-dessus : rien de plus à observer.
      const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
      if (reduce || dejaVisible || typeof IntersectionObserver === 'undefined') {
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
