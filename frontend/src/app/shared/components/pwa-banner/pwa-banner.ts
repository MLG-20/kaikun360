import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';

import { PwaService } from '../../../core/pwa/pwa.service';

/** Clé de mémorisation d'un refus d'installation. */
const REFUS = 'kaikun.pwa.install.refuse';

/**
 * Bandeau **« Installer l'application »** / **« Nouvelle version »** (F9.0).
 *
 * Monté une seule fois dans la racine (`app.html`), comme `app-scroll-top` :
 * toutes les pages en héritent quel que soit leur layout.
 *
 * ⚠️ **Deux messages, jamais ensemble, et la mise à jour PASSE DEVANT** : elle
 * concerne quelqu'un qui utilise déjà l'application et peut buter sur un bug
 * déjà corrigé ; l'invitation à installer peut attendre le prochain passage.
 *
 * ⚠️ **Rien n'est rendu au premier affichage**, ni au serveur ni au client : les
 * deux signaux naissent à `false`, le DOM produit de part et d'autre est donc
 * identique et l'hydratation tient (la leçon de F8.7). Le bandeau n'apparaît
 * qu'après, quand le navigateur signale l'un ou l'autre événement.
 *
 * ⚠️ **Un refus d'installation est MÉMORISÉ** (`localStorage`) : reproposer
 * l'installation à chaque visite est le meilleur moyen de faire fuir quelqu'un.
 * Une mise à jour, elle, ne se refuse pas — on peut seulement la reporter le
 * temps de la session.
 */
@Component({
  selector: 'app-pwa-banner',
  templateUrl: './pwa-banner.html',
  styleUrl: './pwa-banner.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PwaBannerComponent {
  private readonly pwa = inject(PwaService);

  /** Report en cours (le bandeau est masqué jusqu'au prochain chargement). */
  private readonly reporte = signal(false);

  /** Une mise à jour attend d'être appliquée. */
  protected readonly miseAJour = computed(() => this.pwa.miseAJourPrete() && !this.reporte());

  /** Le navigateur accepterait d'installer l'application, et on n'a pas déjà refusé. */
  protected readonly installation = computed(
    () => this.pwa.installable() && !this.reporte() && !this.dejaRefuse(),
  );

  /** Le bandeau montre-t-il quelque chose ? */
  protected readonly visible = computed(() => this.miseAJour() || this.installation());

  protected installer(): void {
    void this.pwa.installer();
  }

  protected actualiser(): void {
    void this.pwa.appliquerLaMiseAJour();
  }

  /**
   * Ferme le bandeau. Un refus d'INSTALLATION est retenu durablement ; un report
   * de mise à jour ne vaut que pour la session en cours.
   */
  protected fermer(): void {
    if (this.installation()) {
      this.retenirLeRefus();
    }

    this.reporte.set(true);
  }

  /**
   * ⚠️ `localStorage` est lu dans un `try` : il lève en navigation privée sur
   * certains navigateurs, et un bandeau ne vaut pas de casser la page.
   */
  private dejaRefuse(): boolean {
    try {
      return localStorage.getItem(REFUS) === '1';
    } catch {
      return false;
    }
  }

  private retenirLeRefus(): void {
    try {
      localStorage.setItem(REFUS, '1');
    } catch {
      // Sans persistance, l'invitation reviendra : désagrément, pas panne.
    }
  }
}
