import { Injectable, inject } from '@angular/core';
import { ActivatedRouteSnapshot, RouterStateSnapshot, TitleStrategy } from '@angular/router';

import { RouteSeo } from './seo.model';
import { SeoService } from './seo.service';

/**
 * Description servie aux écrans **sans** `data.seo` — c'est-à-dire à tout ce
 * qui est connecté. Ils sont `noindex` : cette phrase n'atterrira jamais dans
 * un moteur, elle n'est là que pour qu'aucune page ne parte sans description.
 */
const DESCRIPTION_ESPACE_PRIVE =
  'Espace personnel Kaikun 360 — accès réservé aux utilisateurs connectés.';

/**
 * Applique les balises de référencement **à chaque navigation** (F9.1).
 *
 * ## Pourquoi une `TitleStrategy` et pas un abonnement à `NavigationEnd`
 *
 * Le routeur pose déjà les titres de route par ce point d'extension. Un
 * abonnement concurrent à `NavigationEnd` créerait une **course** : selon
 * l'ordre d'enregistrement des abonnés, le titre écrit par le service serait
 * tantôt conservé, tantôt écrasé par la stratégie intégrée. En remplaçant la
 * stratégie, on récupère le seul point d'entrée qui compte — les 122 titres de
 * route déjà écrits continuent de vivre, avec tout le reste en plus.
 *
 * ## La règle de sécurité à ne pas défaire
 *
 * ⚠️ **Une route sans `data.seo` est `noindex`.** L'application compte quatre
 * espaces connectés et un back-office ; leurs écrans sont largement majoritaires
 * en nombre de routes. Si la règle était inversée (« indexable sauf mention
 * contraire »), le prochain écran privé ajouté partirait dans l'index de Google
 * par simple oubli. Ici, l'oubli fait perdre du référencement à une page
 * publique — visible, réparable — au lieu d'exposer un écran privé.
 *
 * ⚠️ `noindex` n'est **pas** une protection d'accès : il demande à un robot
 * poli de ne pas publier la page. La protection reste `authGuard` / `roleGuard`
 * côté route et les policies côté API.
 */
@Injectable()
export class SeoTitleStrategy extends TitleStrategy {
  private readonly seo = inject(SeoService);

  override updateTitle(snapshot: RouterStateSnapshot): void {
    // Les données de route s'héritent en descendant : une fiche déclare son
    // `seo`, mais si elle n'en a pas, celui de sa route parente s'applique.
    // D'où la lecture de la branche entière, du plus précis au plus général.
    const seo = this.chercherSeo(snapshot.root);

    // ⚠️ Le ménage des données structurées appartient à la navigation, pas aux
    // pages : une fiche détruite n'a aucune garantie de s'exécuter avant que la
    // suivante ait posé les siennes.
    this.seo.clearJsonLd();

    this.seo.apply({
      // `buildTitle` reconstitue le titre en remontant les routes, exactement
      // comme la stratégie intégrée — les `title:` de `app.routes.ts` restent
      // la source de vérité du titre.
      title: this.buildTitle(snapshot) ?? '',
      description: seo?.description ?? DESCRIPTION_ESPACE_PRIVE,
      type: seo?.type,
      // Absence de `seo` → écran privé → hors index. Présence → indexable, sauf
      // `index: false` explicite (recherche filtrée, retours de paiement).
      index: seo ? seo.index !== false : false,
    });
  }

  /** Descend la branche active et retient le `seo` le plus profond déclaré. */
  private chercherSeo(route: ActivatedRouteSnapshot): RouteSeo | undefined {
    let trouve = route.data['seo'] as RouteSeo | undefined;
    for (const enfant of route.children) {
      trouve = this.chercherSeo(enfant) ?? trouve;
    }
    return trouve;
  }
}
