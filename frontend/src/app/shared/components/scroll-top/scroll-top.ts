import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Lien **« revenir en haut »**, posé dans le pied de page (F10.6).
 *
 * ⚠️ Anciennement un bouton flottant global (anneau de progression, apparition
 * au défilement) — ramené à un simple lien statique DANS `app-footer` à la
 * demande : on ne le voit qu'en arrivant en bas de page, ce qui rend inutiles
 * le seuil d'apparition et l'anneau de progression (toujours proche de 100 %
 * à cet endroit). Le clic reste le seul comportement qui compte.
 *
 * ♿ Respecte `prefers-reduced-motion` (défilement instantané plutôt qu'animé).
 */
@Component({
  selector: 'app-scroll-top',
  templateUrl: './scroll-top.html',
  styleUrl: './scroll-top.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ScrollTopComponent {
  /** Remonte en haut de page (animé, sauf préférence de mouvement réduit). */
  protected toTop(): void {
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
    window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
  }
}
