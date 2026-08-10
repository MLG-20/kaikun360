import {
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  HostListener,
  computed,
  inject,
  signal,
} from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink } from '@angular/router';
import { filter } from 'rxjs/operators';

import { AuthService } from '../../../core/auth/auth.service';
import { spaceHomeFor } from '../../../core/auth/space-home';
import { AssistantLauncherComponent } from '../assistant/assistant-launcher';

/** Clé d'icône SVG (rendue via un @switch dans le template). */
type MegaIcon =
  | 'home'
  | 'manage'
  | 'deposit'
  | 'globe'
  | 'bed'
  | 'compass'
  | 'team'
  | 'car'
  | 'route'
  | 'build'
  | 'calc';

/** Une entrée d'un méga-menu : libellé + description + icône + destination. */
interface MegaItem {
  label: string;
  description: string;
  /** Icône SVG affichée dans la pastille. */
  icon: MegaIcon;
  link: string;
  /** Ancre optionnelle sur la page cible (ex. le simulateur de construction). */
  fragment?: string;
}

/** Un groupe de navigation à méga-menu déroulant (univers). */
interface NavGroup {
  label: string;
  /** Page principale de l'univers : le libellé y navigue au clic. */
  home: string;
  items: MegaItem[];
}

/**
 * En-tête global (F0.3 → aligné sur le prototype client).
 *
 * Barre translucide **réellement fixe** (le `sticky` est porté par l'hôte du
 * composant, pas par un enfant, sinon il ne « voyage » pas). La navigation
 * desktop est organisée en **méga-menus par univers** (cartes icône + titre +
 * description) ; le mobile reprend la même structure en accordéons.
 *
 * Chaque lien pointe vers une page RÉELLE (aucun lien mort) : les univers
 * construits en F2.3 → F2.7. Un méga-menu s'ouvre au survol **et** au
 * clic/clavier (bouton `aria-expanded`) ; il se referme à la navigation, sur
 * Échap, ou au clic en dehors de l'en-tête.
 */
@Component({
  selector: 'app-header',
  imports: [RouterLink, NgTemplateOutlet, AssistantLauncherComponent],
  templateUrl: './header.html',
  styleUrl: './header.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HeaderComponent {
  private readonly host = inject(ElementRef<HTMLElement>);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Session active ? (bascule « Connexion » ↔ « Mon espace »). */
  protected readonly isAuthenticated = this.auth.isAuthenticated;

  /**
   * Destination de « Mon espace » selon le rôle : un propriétaire est envoyé
   * dans `/espace-proprietaire`, un client dans `/mon-espace` (cf. spaceHomeFor).
   */
  protected readonly spaceHome = computed(() => spaceHomeFor(this.auth.user()));

  /** Univers à méga-menu (mappés sur les pages réelles F2.3 → F2.7). */
  protected readonly groups: NavGroup[] = [
    {
      label: 'Immobilier',
      home: '/immobilier',
      items: [
        { label: 'Acheter ou louer', description: 'Villas, appartements, terrains et locaux vérifiés.', icon: 'home', link: '/immobilier' },
        { label: 'Gestion locative', description: 'Loyers, quittances, maintenance et reporting.', icon: 'manage', link: '/gestion-locative' },
        { label: 'Déposer un bien', description: 'Propriétaires : mettez votre bien en ligne.', icon: 'deposit', link: '/deposer-un-bien' },
        { label: 'Diaspora', description: 'Acheter, construire et gérer à distance.', icon: 'globe', link: '/diaspora' },
      ],
    },
    {
      label: 'Séjours & Tourisme',
      home: '/tourisme',
      items: [
        { label: 'Hébergements & nuitées', description: 'Villas, meublés, campements et écolodges.', icon: 'bed', link: '/nuitees' },
        { label: 'Circuits & expériences', description: 'Saloum, Casamance, patrimoine et nature.', icon: 'compass', link: '/tourisme' },
        { label: 'Team building', description: 'Journées de cohésion et séminaires clé en main.', icon: 'team', link: '/team-building' },
      ],
    },
    {
      label: 'Transport',
      home: '/transport',
      items: [
        { label: 'Location de véhicules', description: 'Berlines, 4×4 et minibus, avec ou sans chauffeur.', icon: 'car', link: '/transport' },
        { label: 'Mobilité & navettes', description: 'Transferts AIBD, navettes et sorties de groupe.', icon: 'route', link: '/mobilite' },
      ],
    },
    {
      label: 'Construction',
      home: '/construction',
      items: [
        { label: 'Construire / rénover', description: 'Études, travaux, suivi filmé et remise des clés.', icon: 'build', link: '/construction' },
        { label: 'Simulateur de budget', description: 'Estimation par surface, gamme et objectif.', icon: 'calc', link: '/construction', fragment: 'simulateur' },
      ],
    },
  ];

  /** Lien plat (pas de méga-menu) vers la marketplace prestataires. */
  protected readonly proLink = '/pro';

  /** Groupe de méga-menu actuellement ouvert (libellé), ou null. */
  protected readonly openGroup = signal<string | null>(null);
  /** État du menu mobile. */
  protected readonly menuOpen = signal(false);

  /**
   * État du menu de compte (« Mon espace » / « Déconnexion »), sur grand écran.
   *
   * Au clic et non au survol, contrairement aux méga-menus des univers : on
   * survole une navigation pour explorer, on ouvre un menu de compte pour agir.
   * Un menu qui contient « Déconnexion » ne doit jamais se déplier tout seul
   * parce que la souris passait par là.
   */
  protected readonly compteOuvert = signal(false);

  constructor() {
    // Toute navigation referme les menus (méga-menu desktop + panneau mobile).
    inject(Router)
      .events.pipe(
        filter((e) => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => {
        this.openGroup.set(null);
        this.menuOpen.set(false);
        this.compteOuvert.set(false);
      });
  }

  /** Ouvre le méga-menu d'un groupe (survol desktop ou focus clavier). */
  protected openMega(label: string): void {
    this.openGroup.set(label);
  }

  /** Ferme le méga-menu (sortie de survol desktop). */
  protected closeMega(): void {
    this.openGroup.set(null);
  }

  /**
   * Referme le méga-menu quand le focus quitte réellement le groupe (le libellé
   * navigue au clic ; le menu, lui, s'ouvre au survol/focus et se referme dès
   * que le focus sort du groupe — accessibilité clavier).
   */
  protected onGroupBlur(event: FocusEvent): void {
    const group = event.currentTarget as HTMLElement;
    if (!group.contains(event.relatedTarget as Node)) {
      this.openGroup.set(null);
    }
  }

  /** Bascule le panneau mobile. */
  protected toggleMenu(): void {
    this.menuOpen.update((open) => !open);
  }

  /** Bascule le menu de compte (grand écran). */
  protected toggleCompte(): void {
    this.compteOuvert.update((ouvert) => !ouvert);
  }

  /**
   * Referme le menu de compte quand le focus en sort réellement.
   *
   * Même précaution que `onGroupBlur` : `relatedTarget` porte l'élément qui
   * REÇOIT le focus — s'il est encore dans le menu (on tabule de « Aller à mon
   * espace » vers « Déconnexion »), on ne referme pas sous les doigts.
   */
  protected onCompteBlur(event: FocusEvent): void {
    const conteneur = event.currentTarget as HTMLElement;
    if (!conteneur.contains(event.relatedTarget as Node)) {
      this.compteOuvert.set(false);
    }
  }

  /** Déconnexion depuis l'en-tête : vide la session puis renvoie à l'accueil. */
  protected logout(): void {
    this.menuOpen.set(false);
    this.compteOuvert.set(false);
    this.auth.logout().subscribe({
      next: () => this.router.navigate(['/']),
      error: () => this.router.navigate(['/']),
    });
  }

  /** Échap referme tout. */
  @HostListener('document:keydown.escape')
  protected onEscape(): void {
    this.openGroup.set(null);
    this.menuOpen.set(false);
    this.compteOuvert.set(false);
  }

  /** Un clic en dehors de l'en-tête referme les menus déroulants ouverts. */
  @HostListener('document:click', ['$event'])
  protected onDocumentClick(event: MouseEvent): void {
    if (this.host.nativeElement.contains(event.target)) {
      return;
    }

    this.openGroup.set(null);
    this.compteOuvert.set(false);
  }
}
