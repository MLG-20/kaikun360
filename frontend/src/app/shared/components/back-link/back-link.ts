import { Location } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, input } from '@angular/core';
import { Router } from '@angular/router';

/**
 * Bouton « ← Retour » réutilisable — revient à la **page précédente**.
 *
 * Contrairement à un lien fixe, il suit l'historique de navigation : posé sur un
 * écran atteint depuis plusieurs endroits (p. ex. une **liste de l'espace
 * client** ouverte tantôt depuis le menu, tantôt depuis une **notification**),
 * il ramène là d'où l'on vient réellement (les notifications, le tableau de
 * bord…). En l'absence d'historique exploitable (accès direct par URL,
 * rechargement, rendu serveur), il **retombe** sur `fallback` (défaut :
 * l'accueil de l'espace) pour ne jamais laisser l'utilisateur bloqué.
 */
@Component({
  selector: 'app-back-link',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <button type="button" class="back-link" (click)="goBack()">
      ← {{ label() }}
    </button>
  `,
  styles: [
    `
      .back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0;
        border: 0;
        background: none;
        cursor: pointer;
        font: inherit;
        font-size: 0.9rem;
        color: var(--k-brand-600);

        &:hover {
          text-decoration: underline;
        }
      }
    `,
  ],
})
export class BackLinkComponent {
  private readonly location = inject(Location);
  private readonly router = inject(Router);

  /** Libellé affiché après la flèche (défaut : « Retour »). */
  readonly label = input('Retour');

  /** Destination de repli si l'historique n'est pas exploitable. */
  readonly fallback = input('/mon-espace');

  /** Revient à la page précédente, ou au repli s'il n'y a pas d'historique. */
  goBack(): void {
    // `history.length > 1` = il existe une entrée précédente dans cet onglet.
    // Garde SSR : `history` n'existe pas côté serveur.
    if (typeof window !== 'undefined' && window.history.length > 1) {
      this.location.back();
    } else {
      this.router.navigateByUrl(this.fallback());
    }
  }
}
