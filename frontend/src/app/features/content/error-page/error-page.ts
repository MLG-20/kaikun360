import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { map } from 'rxjs/operators';

/** Ce qui a mal tourné. Vient des `data` de la route, jamais de l'URL. */
export type ErrorKind = 'serveur' | 'introuvable';

/**
 * Les deux pages de secours du site (F10.1.a) : **`/erreur`** et le **404**.
 *
 * ── Le trou qu'elles comblent ───────────────────────────────────────────────
 * `errorInterceptor` renvoyait vers `/erreur` **depuis F0**… une route qui
 * n'avait jamais existé, et aucune route « attrape-tout » ne couvrait les
 * adresses inconnues. Conséquences, invisibles tant qu'on ne regardait pas les
 * journaux :
 *
 *   - au **rendu serveur**, chaque cas levait `NG04002: 'erreur'` — une
 *     exception non rattrapée dans le processus Node ;
 *   - au **navigateur**, le routeur annulait simplement la navigation : la
 *     personne restait sur la page précédente, **sans un mot d'explication**,
 *     à se demander pourquoi son clic n'avait rien fait.
 *
 * Un lien périmé partagé sur WhatsApp — le canal de conversion principal —
 * tombait ainsi dans le vide.
 *
 * ── Deux règles à ne pas défaire ────────────────────────────────────────────
 * 1. **Cette page n'appelle AUCUNE API.** Elle est précisément celle qu'on
 *    atteint quand le serveur ne répond plus : un appel de plus produirait une
 *    seconde erreur, donc une nouvelle redirection vers elle-même.
 * 2. **Elle n'est jamais indexée** (`seo.index: false` sur les deux routes).
 *    Une page d'erreur dans les résultats Google abîme la réputation du domaine
 *    entier.
 *
 * ⚠️ **Limite assumée : le 404 répond en HTTP 200** (« soft 404 »). Le code de
 * statut se règle dans `app.routes.server.ts`, dont la règle `**` couvre aussi
 * *toutes* les pages publiques légitimes — y poser `status: 404` les marquerait
 * introuvables. Il faudrait énumérer chaque route publique côté serveur, une
 * liste qui divergerait au premier écran ajouté. Le `noindex` suffit à écarter
 * la page des résultats de recherche, qui est le vrai risque ; à rouvrir si le
 * suivi Search Console signale un jour des soft 404.
 */
@Component({
  selector: 'app-error-page',
  imports: [RouterLink],
  templateUrl: './error-page.html',
  styleUrl: './error-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ErrorPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  /** Nature du problème, portée par la route (une route par cas). */
  protected readonly kind = toSignal(
    this.route.data.pipe(map((data) => (data['kind'] ?? 'serveur') as ErrorKind)),
    { initialValue: 'serveur' as ErrorKind },
  );

  private readonly params = toSignal(this.route.queryParamMap, { initialValue: null });

  /**
   * Adresse que la personne essayait d'atteindre, transmise par
   * `errorInterceptor` (`?depuis=`).
   *
   * C'est ce qui rend le bouton « Réessayer » honnête : sans elle, on ne saurait
   * proposer qu'un retour à l'accueil, c'est-à-dire abandonner ce qu'on faisait.
   *
   * ⚠️ **Chemin interne uniquement.** Le paramètre vient de l'URL, donc de
   * n'importe qui : un lien `…/erreur?depuis=https://evil.test` transformerait
   * notre page d'erreur en tremplin d'hameçonnage — le genre de lien qu'on
   * envoie justement à quelqu'un d'inquiet. Un seul slash initial est accepté,
   * ce qui écarte aussi la forme protocole-relative `//evil.test`.
   */
  protected readonly retour = computed(() => {
    const cible = this.params()?.get('depuis') ?? null;

    if (cible === null || !cible.startsWith('/') || cible.startsWith('//')) {
      return null;
    }

    // Se renvoyer sur soi-même ne réessaierait rien.
    return cible.startsWith('/erreur') ? null : cible;
  });

  /** Relance la page d'origine. */
  protected reessayer(): void {
    const cible = this.retour();

    if (cible !== null) {
      void this.router.navigateByUrl(cible);
    }
  }
}
