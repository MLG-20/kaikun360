import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';

import { HeroService } from '../../../core/api/hero.service';
import { RevealDirective } from '../../directives/reveal.directive';

/**
 * Bandeau d'en-tête d'une page publique (F12).
 *
 * Remplace le bloc `<section class="uni-hero">` que chaque grande page
 * recopiait, et lui ajoute deux choses :
 *
 *  - une **image de fond** chargée depuis le back-office, héritée par les pages
 *    filles (l'héritage est résolu côté serveur, cf. `HeroCatalog`) ;
 *  - la possibilité, pour l'équipe, de **retoucher les mots** sans passer par
 *    un développeur.
 *
 * ⚠️ **Les textes passés en `input()` restent la vérité par défaut.** Ils
 * vivent dans le gabarit de chaque page, comme avant ; la personnalisation
 * back-office ne fait que les *remplacer quand elle existe*. Vider un champ au
 * back-office rend donc à la page son libellé d'origine — il n'y a aucun état
 * dans lequel un bandeau se retrouve sans titre.
 *
 * Le contenu supplémentaire de chaque page (liste de points forts, renvoi vers
 * un univers voisin, bouton d'action) est **projeté** : le composant ne connaît
 * que l'ossature, pas ce que chaque univers a de particulier à dire.
 *
 * Le titre porte `appReveal="title"` (balayage gauche→droite, cf. l'accueil) —
 * un seul endroit à modifier pour les 18 bandeaux F12 (les 5 pages d'univers
 * du catalogue, `/recherche`, contact, FAQ…), cohérent avec les titres de
 * l'accueil sans dupliquer la directive partout.
 */
@Component({
  selector: 'app-page-hero',
  imports: [RevealDirective],
  templateUrl: './page-hero.html',
  styleUrl: './page-hero.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PageHeroComponent {
  private readonly heroes = inject(HeroService);

  /**
   * Clé du bandeau au back-office (`immobilier`, `recherche.nuitees`…).
   *
   * Doit exister dans `HeroCatalog::BANNERS` côté serveur, sinon la page ne
   * sera jamais personnalisable — elle continuera simplement d'afficher ses
   * textes d'origine sur le dégradé de marque.
   */
  readonly heroKey = input.required<string>();

  /** Surtitre par défaut (ex. « Univers Immobilier »). */
  readonly eyebrow = input<string | null>(null);

  /** Titre par défaut de la page. */
  readonly title = input<string>('');

  /** Accroche par défaut. */
  readonly lead = input<string | null>(null);

  /** Personnalisation back-office pour cette clé, si elle existe. */
  private readonly banner = computed(() => this.heroes.banner(this.heroKey()));

  protected readonly resolvedEyebrow = computed(() => this.banner()?.eyebrow ?? this.eyebrow());
  protected readonly resolvedTitle = computed(() => this.banner()?.title ?? this.title());
  protected readonly resolvedLead = computed(() => this.banner()?.lead ?? this.lead());

  /** Image de fond effective (propre à la page ou héritée), `null` si aucune. */
  protected readonly image = computed(() => this.banner()?.image ?? null);

  /**
   * Fond du bandeau : **la photo seule**.
   *
   * ⚠️ Le voile qui garde le texte lisible n'est PLUS empilé ici. Il l'a été,
   * et c'était une erreur d'emplacement : un dégradé écrit en style en ligne ne
   * peut pas être réglé par une requête média, si bien que le même voile —
   * pensé pour un large écran, où le texte n'occupe que la gauche — s'appliquait
   * aussi au téléphone, où le texte couvre toute la largeur. Le voile vit
   * désormais dans `.uni-hero--image::before` (`styles/_universe.scss`), où il
   * peut s'adapter ; ne pas le réintroduire ici, on le paierait deux fois et la
   * photo redeviendrait sourde.
   *
   * Sans image, on renvoie `null` : la règle CSS d'origine s'applique telle
   * quelle et la page est strictement identique à ce qu'elle était.
   */
  protected readonly background = computed(() => {
    const url = this.image();

    return url ? `url("${encodeURI(url)}")` : null;
  });
}
