import { isPlatformBrowser } from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  ElementRef,
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
 * Le panneau de l'**assistant Kaikun 360** (F10.1) : une bulle flottante qui
 * déploie une conversation.
 *
 * ── Ce qu'il remplace ───────────────────────────────────────────────────────
 * Le prototype du client avait un bouton « Téranga IA » qui ouvrait six phrases
 * écrites en dur. Celui-ci parle au vrai catalogue, à la vraie FAQ (éditée au
 * back-office) et au vrai support — et il n'invente rien : tout ce qu'il montre
 * a traversé un outil serveur.
 *
 * ── Où il apparaît, et où il n'apparaît PAS ─────────────────────────────────
 * Monté dans le layout public (`main-layout`) et dans le shell des quatre
 * espaces connectés (`space-layout`). **Pas dans le back-office** : ses outils
 * de gouvernance n'existent qu'en F10.3, une bulle qui ne saurait rien faire y
 * serait un décor. **Pas dans le parcours d'authentification** non plus : on
 * n'interrompt pas quelqu'un qui saisit un mot de passe.
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
  }

  protected basculer(): void {
    this.store.basculer();
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
