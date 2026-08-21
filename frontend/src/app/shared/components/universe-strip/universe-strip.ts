import { isPlatformBrowser } from '@angular/common';
import { ChangeDetectionStrategy, Component, PLATFORM_ID, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';

import { UniverseStripService } from '../../../core/api/universe-strip.service';

/** Durée plancher d'affichage d'un message (secondes) — même un message très court reste lisible un minimum de temps. */
const DUREE_MIN_S = 4;
/** Secondes accordées par caractère, en plus du plancher — un message plus long reste affiché plus longtemps. */
const SECONDES_PAR_CARACTERE = 0.07;

/**
 * Bande des univers, posée juste sous le héros de l'accueil (F16.2).
 *
 * ⚠️ **Un seul message affiché à la fois, immobile** (F17.3, 2026-08-21,
 * demande explicite du client) — remplace un défilement continu en boucle
 * (marquee classique) : le client a jugé qu'un bandeau où plusieurs messages
 * restent visibles en même temps, tronqués aux deux bords, donnait
 * l'impression que « le texte ne finit jamais de défiler ». Chaque message
 * glisse depuis la DROITE puis reste fixe le temps d'être lu, disparaît, et
 * le suivant entre à son tour — même patron que les pastilles de la vitrine
 * du catalogue (`home-page.ts::demarrerLeTour`), pas un marquee.
 *
 * Composant à part, avec SA PROPRE feuille de style : `home-page.scss` est
 * déjà au maximum de son budget de build (F13.5/F16.1), un composant neuf
 * repart avec un budget vierge plutôt que de faire déborder l'existant.
 */
@Component({
  selector: 'app-universe-strip',
  imports: [],
  templateUrl: './universe-strip.html',
  styleUrl: './universe-strip.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UniverseStripComponent {
  private readonly strip = inject(UniverseStripService);
  private readonly estNavigateur = isPlatformBrowser(inject(PLATFORM_ID));

  /** Noms publiés (l'équipe peut en masquer certains au back-office). */
  protected readonly names = toSignal(this.strip.list(), { initialValue: [] as string[] });

  /** Index du message actuellement affiché. */
  private readonly index = signal(0);

  /** Le message courant, `null` tant qu'aucun n'est publié. */
  protected readonly messageCourant = computed(() => {
    const liste = this.names();

    return liste.length ? liste[this.index() % liste.length] : null;
  });

  private minuteur: ReturnType<typeof setTimeout> | null = null;

  constructor() {
    if (!this.estNavigateur) return;

    // Un minuteur par message plutôt qu'un `setInterval` fixe : sa durée
    // dépend de la longueur du message courant (voir les constantes plus
    // haut), donc elle change à chaque tour — un `setInterval` figerait la
    // première durée pour tous les messages suivants.
    const planifierProchain = (): void => {
      const texte = this.messageCourant();
      const dureeMs = Math.max(DUREE_MIN_S, (texte?.length ?? 0) * SECONDES_PAR_CARACTERE) * 1000;

      this.minuteur = setTimeout(() => {
        this.index.update((i) => i + 1);
        planifierProchain();
      }, dureeMs);
    };

    planifierProchain();
  }

  ngOnDestroy(): void {
    if (this.minuteur) clearTimeout(this.minuteur);
  }
}
