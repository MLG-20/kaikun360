import { isPlatformBrowser } from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  ElementRef,
  NgZone,
  PLATFORM_ID,
  computed,
  effect,
  inject,
  input,
  signal,
  viewChild,
} from '@angular/core';

import { AssistantAction, AssistantItem } from '../../../core/api/assistant.service';
import { ASSISTANT_MAX_LENGTH, AssistantStore } from '../../../core/state/assistant-store';
// ⚠️ Importé de `shared/format/`, PAS de `catalog/catalog.config` où la fonction
// vivait : ce fichier-là tire `CatalogService` et le registre des cinq univers,
// et ce panneau est monté dans un layout — l'import aurait embarqué tout le
// registre du catalogue dans le paquet initial du site.
import { formatFcfa } from '../../format/fcfa';

/**
 * Le **tiroir** de l'assistant Kaikun 360 : une conversation qui glisse depuis le
 * bord droit de l'écran, sur toute la hauteur.
 *
 * ── Il ne s'ouvre pas lui-même ──────────────────────────────────────────────
 * Le bouton qui l'ouvre est ailleurs — `AssistantLauncherComponent`, posé dans
 * les en-têtes. Les deux ne se connaissent pas : ils partagent l'état `estOuvert`
 * du `AssistantStore`. La bulle flottante qui tenait ce rôle a disparu, elle
 * recouvrait le contenu de chaque page sans qu'on le lui demande.
 *
 * ── Ce qu'il remplace ───────────────────────────────────────────────────────
 * Le prototype du client avait un bouton « Téranga IA » qui ouvrait six phrases
 * écrites en dur. Celui-ci parle au vrai catalogue, à la vraie FAQ (éditée au
 * back-office) et au vrai support — et il n'invente rien : tout ce qu'il montre
 * a traversé un outil serveur.
 *
 * ── Où il apparaît, et où il n'apparaît PAS ─────────────────────────────────
 * Monté dans le layout public (`main-layout`), dans le shell des quatre espaces
 * connectés (`space-layout`) et au back-office (`backoffice-layout`, avec
 * `variante="back-office"` depuis que ses outils de gouvernance existent).
 * **Pas dans le parcours d'authentification** : on n'interrompt pas quelqu'un
 * qui saisit un mot de passe.
 *
 * ── Ce qui n'est pas ici ────────────────────────────────────────────────────
 * Ni la conversation, ni l'appel réseau, ni l'exécution des gestes : tout cela
 * vit dans `AssistantStore` (`core/state/`). Le composant est **l'écran**, le
 * store est la mémoire — c'est ce qui permet à la conversation de survivre au
 * passage du site public à un espace connecté, qui détruit ce composant.
 */
@Component({
  selector: 'app-assistant-panel',
  templateUrl: './assistant-panel.html',
  styleUrl: './assistant-panel.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AssistantPanelComponent {
  private readonly store = inject(AssistantStore);
  private readonly zone = inject(NgZone);
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  /**
   * Décor du panneau selon l'endroit où il est monté (F10.3).
   *
   * ⚠️ **Ce réglage ne change RIEN à ce que l'assistant sait faire.** La trousse
   * à outils est composée côté serveur à partir du JETON (`ToolRegistry`), pas
   * de la page d'où part le message : un administrateur obtient ses outils de
   * back-office depuis le site public, et un visiteur n'en obtiendrait aucun
   * même en ouvrant cette variante. Ce qui se joue ici est uniquement le
   * vocabulaire affiché — sous-titre, exemple de question, mention légale — car
   * un agent à qui l'on propose « une villa à Saly » ne devine pas que la bulle
   * connaît sa file de validation.
   *
   * Le faire porter par le layout (et non déduire du rôle connecté) est
   * délibéré : le rôle dit ce que l'on PEUT faire, la page dit ce que l'on est
   * en train de faire — et c'est la seconde qui règle une invite.
   */
  readonly variante = input<'public' | 'back-office'>('public');

  /** Sous-titre de l'en-tête. */
  protected readonly sousTitre = computed(() =>
    this.variante() === 'back-office'
      ? 'File, demandes, comptes, règlements'
      : 'Immobilier, séjours, tourisme, transport',
  );

  /** Exemple de question, dans le champ de saisie. */
  protected readonly exemple = computed(() =>
    this.variante() === 'back-office'
      ? 'Ex. : que reste-t-il à valider ?'
      : 'Ex. : une villa à Saly sous 60 millions',
  );

  /**
   * Mention de pied, **non négociable dans les deux cas** mais pas pour la même
   * raison : au public, elle dit que l'assistant n'engage pas Kaikun 360 ; à
   * l'équipe, elle dit qu'il est en LECTURE SEULE. Un agent qui croirait pouvoir
   * valider ou rembourser d'une phrase perdrait un temps précieux à essayer —
   * ou, pire, croirait l'avoir fait.
   */
  protected readonly mention = computed(() =>
    this.variante() === 'back-office'
      ? 'Assistant automatique, en lecture seule : il ne valide, ne confirme et ne rembourse rien. '
        + 'Les gestes se prennent sur l’écran du dossier.'
      : 'Assistant automatique — les informations affichées proviennent du catalogue publié. '
        + 'Pour un engagement ferme, un conseiller prend le relais.',
  );

  /** Longueur maximale, affichée au compteur (miroir du serveur). */
  protected readonly maxLength = ASSISTANT_MAX_LENGTH;

  protected readonly bulles = this.store.bulles;
  protected readonly ouvert = this.store.estOuvert;
  protected readonly attente = this.store.attenteReponse;
  protected readonly indisponible = this.store.indisponible;

  /** Saisie en cours (le store ne la connaît pas : elle n'est pas partagée). */
  protected readonly brouillon = signal('');

  /** Envoi possible ? (rien à dire, ou une réponse déjà en route) */
  protected readonly peutEnvoyer = computed(
    () => this.brouillon().trim().length > 0 && !this.attente(),
  );

  private readonly fil = viewChild<ElementRef<HTMLElement>>('fil');
  private readonly champ = viewChild<ElementRef<HTMLTextAreaElement>>('champ');

  constructor() {
    // Le fil suit la conversation : sans cela, la réponse arrive hors de vue et
    // l'on croit que rien ne s'est passé.
    effect(() => {
      // Dépendances explicites : nombre de bulles ET attente (l'indicateur de
      // saisie s'ajoute au bas du fil, il pousse lui aussi le contenu).
      this.bulles().length;
      this.attente();

      if (!this.isBrowser || !this.ouvert()) {
        return;
      }

      // Après le rendu de la bulle : `requestAnimationFrame` évite de mesurer
      // une hauteur qui n'inclut pas encore le nouveau contenu.
      requestAnimationFrame(() => {
        const element = this.fil()?.nativeElement;
        if (element) {
          element.scrollTop = element.scrollHeight;
        }
      });
    });

    // À l'ouverture, le curseur est dans le champ : le geste attendu ensuite
    // est d'écrire, pas de viser une zone de saisie à la souris.
    effect(() => {
      if (this.isBrowser && this.ouvert()) {
        requestAnimationFrame(() => this.champ()?.nativeElement.focus());
      }
    });

    // Rangées de résultats : chaque réponse neuve amène ses pistes, qu'il faut
    // mesurer (les flèches ne s'affichent que si quelque chose dépasse) et
    // écouter. `requestAnimationFrame` pour la même raison qu'au-dessus : avant
    // le rendu, `scrollWidth` vaut celui d'une piste vide.
    effect(() => {
      this.bulles().length;

      if (!this.isBrowser || !this.ouvert()) {
        return;
      }

      requestAnimationFrame(() => this.reglerRangees());
    });

    // Le panneau change de largeur (rotation du téléphone, fenêtre redimensionnée)
    // sans qu'une bulle arrive : sans cette veille, une flèche resterait affichée
    // alors que plus rien ne dépasse — ou l'inverse, plus gênant encore.
    if (this.isBrowser && typeof ResizeObserver !== 'undefined') {
      // Hors zone : redimensionner ne change aucun signal, seulement des classes.
      const veille = this.zone.runOutsideAngular(
        () => new ResizeObserver(() => this.reglerRangees()),
      );

      effect(() => {
        const element = this.fil()?.nativeElement;
        veille.disconnect(); // le fil précédent a été détruit avec le panneau
        if (element) {
          veille.observe(element);
        }
      });

      inject(DestroyRef).onDestroy(() => veille.disconnect());
    }
  }

  protected fermer(): void {
    this.store.fermer();
  }

  protected envoyer(): void {
    if (!this.peutEnvoyer()) {
      return;
    }

    this.store.envoyer(this.brouillon());
    this.brouillon.set('');
  }

  /**
   * Entrée clavier dans le champ.
   *
   * `Entrée` envoie, `Maj+Entrée` passe à la ligne : la convention de toutes
   * les messageries, et celle que les gens essaient d'instinct.
   */
  protected touche(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      this.envoyer();
    }
  }

  /** `Échap` referme le panneau, où que soit le curseur à l'intérieur. */
  protected echap(): void {
    if (this.ouvert()) {
      this.fermer();
    }
  }

  protected agir(action: AssistantAction): void {
    this.store.executer(action);
  }

  /**
   * Ouvre la fiche d'un résultat.
   *
   * Passe par le store — donc par son contrôle « chemin interne uniquement » —
   * plutôt que par un `routerLink` construit à partir d'une donnée reçue du
   * serveur. Même précaution que pour les boutons d'action.
   */
  protected ouvrirFiche(item: AssistantItem): void {
    this.store.executer({ kind: 'link', label: '', payload: { url: item['url'] } });
  }

  // ==========================================================================
  // Rangées de résultats (carrousel)
  //
  // Les annonces défilent horizontalement plutôt que de s'empiler : cinq villas
  // empilées occupaient toute la hauteur du fil et poussaient la conversation
  // hors de vue. Le défilement lui-même est celui du navigateur (`overflow-x` +
  // `scroll-snap`), pas une mécanique maison : le glissement au doigt, la molette
  // inclinée et le déplacement au clavier — quand la tabulation atteint une carte
  // hors cadre, le navigateur l'amène — fonctionnent alors sans une ligne de JS.
  // Ne restent ici que les deux choses que CSS ne sait pas faire : savoir si
  // quelque chose dépasse, et avancer d'une carte au clic.
  // ==========================================================================

  /** Pistes déjà écoutées — une seule écoute par rangée, même après un redessin. */
  private readonly pistesEcoutees = new WeakSet<HTMLElement>();

  /**
   * Ces résultats se parcourent-ils en rangée ?
   *
   * Non pour la FAQ : une question et sa réponse se LISENT, les couper en cartes
   * de 190 px les rendrait illisibles. Non plus pour un résultat unique — un
   * carrousel d'un seul élément n'est qu'une carte étroite sans raison.
   */
  protected enRangee(items: AssistantItem[]): boolean {
    return items.length > 1 && !this.estFaq(items[0]);
  }

  /**
   * Avance (ou recule) d'une carte.
   *
   * Appelé depuis le gabarit avec la référence de la piste : c'est la rangée
   * cliquée qui bouge, pas la première du fil.
   */
  protected glisser(piste: HTMLElement, sens: 1 | -1): void {
    const carte = piste.querySelector<HTMLElement>('.ia-fiche');

    // Un pas = une carte + l'espace qui la sépare de la suivante (0.5 rem, soit
    // 8 px — miroir du `gap` de la feuille de style). Sans carte à mesurer, on
    // avance d'une fenêtre presque pleine, en laissant un repère visible.
    const pas = carte ? carte.getBoundingClientRect().width + 8 : piste.clientWidth * 0.8;

    piste.scrollBy({
      left: sens * pas,
      behavior: this.animationReduite() ? 'auto' : 'smooth',
    });
  }

  /**
   * Mesure toutes les rangées du fil, et pose l'écoute du défilement sur celles
   * qui viennent d'apparaître.
   *
   * ⚠️ Le DOM est parcouru à la main plutôt que par des `viewChildren` : les
   * pistes naissent à l'intérieur d'une double boucle `@for`, et l'on n'a besoin
   * que de leur élément — un signal de plus par bulle coûterait plus qu'il ne
   * rapporte.
   */
  private reglerRangees(): void {
    const racine = this.fil()?.nativeElement;
    if (!racine) {
      return;
    }

    for (const cadre of Array.from(racine.querySelectorAll<HTMLElement>('.ia-carrousel'))) {
      const piste = cadre.querySelector<HTMLElement>('.ia-fiches');
      if (!piste) {
        continue;
      }

      if (!this.pistesEcoutees.has(piste)) {
        this.pistesEcoutees.add(piste);

        // `passive` : on ne bloquera jamais le défilement, autant le promettre au
        // navigateur. Hors zone Angular : voir le commentaire du gabarit.
        this.zone.runOutsideAngular(() =>
          piste.addEventListener('scroll', () => this.majBords(cadre, piste), { passive: true }),
        );
      }

      this.majBords(cadre, piste);
    }
  }

  /**
   * Pose sur le cadre l'état de sa piste, que la feuille de style traduit en
   * flèches visibles ou non.
   *
   * Écrit des classes directement, sans passer par un signal : rien de ce qui est
   * décidé ici ne concerne le gabarit, et un cycle de détection par image de
   * défilement se paierait cher sur un téléphone modeste.
   */
  private majBords(cadre: HTMLElement, piste: HTMLElement): void {
    // Tolérance : les largeurs de cartes sont fractionnaires, et `scrollLeft`
    // n'atteint pas toujours exactement le maximum théorique.
    const marge = 2;

    cadre.classList.toggle('is-deborde', piste.scrollWidth - piste.clientWidth > marge);
    cadre.classList.toggle('is-debut', piste.scrollLeft <= marge);
    cadre.classList.toggle(
      'is-fin',
      piste.scrollLeft + piste.clientWidth >= piste.scrollWidth - marge,
    );
  }

  /** L'utilisateur a-t-il demandé moins d'animation ? (réglage du système) */
  private animationReduite(): boolean {
    return this.isBrowser && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  // ==========================================================================
  // Lecture des fiches
  //
  // ⚠️ Les outils renvoient des formes DIFFÉRENTES (une annonce, une entrée de
  // FAQ, et d'autres à venir en F10.2/F10.3). Plutôt que d'imposer un type
  // fermé au serveur — qu'il faudrait élargir à chaque outil neuf — le panneau
  // reconnaît ce qu'il sait afficher et ignore le reste.
  // ==========================================================================

  /** La fiche est-elle une entrée de FAQ (`question`/`reponse`) ? */
  protected estFaq(item: AssistantItem): boolean {
    return typeof item['question'] === 'string';
  }

  protected texte(item: AssistantItem, cle: string): string | null {
    const valeur = item[cle];
    return typeof valeur === 'string' && valeur.trim() !== '' ? valeur : null;
  }

  /**
   * Montant affiché sur une fiche.
   *
   * ⚠️ Deux origines, dans cet ordre. Les outils personnels (F10.2) renvoient un
   * `montant` **déjà mis en forme par le serveur** — c'est lui qui fait foi, un
   * second formatage local risquerait de diverger. Les outils du catalogue
   * (F10.0) renvoient un `prix_xof` brut, mis en forme ici. Ne lire que le
   * second laisserait les réservations, missions et projets sans leur montant.
   */
  protected prix(item: AssistantItem): string | null {
    const prepare = item['montant'];
    if (typeof prepare === 'string' && prepare.trim() !== '') {
      return prepare;
    }

    const valeur = item['prix_xof'];
    return typeof valeur === 'number' ? formatFcfa(valeur) : null;
  }

  /** Une fiche n'est cliquable que si elle porte un chemin interne. */
  protected cliquable(item: AssistantItem): boolean {
    const url = item['url'];
    return typeof url === 'string' && url.startsWith('/') && !url.startsWith('//');
  }
}
