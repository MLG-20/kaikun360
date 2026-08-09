import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs/operators';

import { AuthService } from '../../core/auth/auth.service';
import { AssistantPanelComponent } from '../../shared/components/assistant/assistant-panel';
import { UnreadStore } from '../../core/state/unread-store';
import { permissionsFor } from '../../features/backoffice/backoffice-permissions';

/**
 * Clé de mémorisation du rail replié (F11.1).
 *
 * Le pli est une préférence de POSTE, pas de session : l'agent qui travaille sur
 * un petit portable replie une fois, pas à chaque connexion. On la garde donc en
 * `localStorage` (survit à la fermeture du navigateur) et non en `sessionStorage`.
 */
const RAIL_REPLIE_KEY = 'k360.bo.rail-replie';

/** Clé d'icône du rail (rendue en SVG inline dans le template). */
type BoIcon =
  | 'grid'
  | 'check'
  | 'layers'
  | 'car'
  | 'compass'
  | 'calendar'
  | 'card'
  | 'payout'
  | 'hammer'
  | 'key'
  | 'id'
  | 'star'
  | 'briefcase'
  | 'globe'
  | 'users'
  | 'shield'
  | 'clock'
  | 'sliders'
  | 'inbox'
  | 'chat';

/** Une rubrique de navigation du poste de commandement. */
interface BoNavItem {
  label: string;
  /** Chemin relatif à `/back-office` (`''` = accueil). */
  path: string;
  icon: BoIcon;
  /** L'écran est-il construit ? (sinon « Bientôt », non cliquable). */
  ready: boolean;
  /**
   * Pastille de non-lus au bout de la rubrique (F8.13). Une seule rubrique en
   * porte une : l'agent est le destinataire des messages clients, et jusqu'ici
   * rien ne le lui disait — il fallait ouvrir la boîte pour l'apprendre.
   */
  badge?: 'messages';
}

/**
 * Shell **dédié et indépendant** du back-office (F7.1.e).
 *
 * ⚠️ Ce n'est PAS le `SpaceLayoutComponent` des espaces utilisateurs : décision
 * produit d'un habillage séparé (« salle de commande ») pour bien distinguer le
 * poste de commandement — là où « tout passe » — des espaces clients/pro, et
 * pour lui appliquer un niveau de sécurité propre (guard de rôle strict sur la
 * route racine, session courte + 2FA côté back).
 *
 * Structure : rail sombre « graphite » à gauche (marque + navigation + pied
 * utilisateur/déconnexion), colonne contenu à droite (barre supérieure + outlet).
 * Sur petit écran, le rail devient un tiroir (hamburger de la barre).
 */
@Component({
  selector: 'app-backoffice-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, AssistantPanelComponent],
  templateUrl: './backoffice-layout.html',
  styleUrl: './backoffice-layout.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeLayoutComponent {
  private readonly auth = inject(AuthService);
  private readonly unread = inject(UnreadStore);
  private readonly router = inject(Router);

  /** Utilisateur connecté (nom, initiale, rôle affichés). */
  protected readonly user = this.auth.user;

  /** Libellé du rôle back-office principal (pour la barre + le pied de rail). */
  protected readonly roleLabel = computed(() => {
    const roles = this.user()?.roles ?? [];
    if (roles.includes('super_admin')) return 'Super administrateur';
    if (roles.includes('admin')) return 'Administrateur';
    if (roles.includes('agent_kaikun')) return 'Agent';
    return 'Équipe';
  });

  /**
   * Rubriques du poste de commandement, TOUTES rubriques confondues.
   *
   * **ORDRE = celui du tableau « Module admin » du CDC §6 (F7.4.d).** Le rail
   * suivait jusqu'ici l'ordre de CONSTRUCTION des tranches (F7.2.a, .b, .c…),
   * qui n'avait de sens que pour nous : à la lecture, l'équipe ne retrouvait
   * pas la liste du cahier des charges, et une recette module par module
   * obligeait à sauter d'un bout à l'autre du menu.
   *
   * ⚠️ En modifier l'ordre est un geste de RECETTE, pas de confort : le client
   * déroule ce menu en face du §6 de son cahier. Toute nouvelle rubrique se
   * range à la place de son module, pas en fin de liste.
   *
   * Deux correspondances ne sont pas une ligne pour une :
   *   - le module 2 *Utilisateurs* et le module 12 *Documents* partagent la
   *     rubrique **Comptes** (deux onglets d'un même écran) : les documents
   *     sont indexés par compte, les séparer obligerait à chercher deux fois ;
   *   - le module 3 *Biens immobiliers* est servi par **Catalogues** (voir,
   *     corriger, archiver), sa fonction « valider » étant portée par la file
   *     transverse **Validation**.
   *
   * Trois rubriques ne viennent pas du §6 mais du §7 (rôles et permissions) —
   * c'est le poste de commandement de l'équipe, livré en F7.1. Elles ferment
   * donc la liste, après les 14 modules métier.
   *
   * ⚠️ Ne pas lire directement dans le template : c'est `visibleNav()` qui est
   * affiché, filtré par les permissions de la personne connectée.
   */
  private readonly nav: readonly BoNavItem[] = [
    // CDC §6 — module 1 « Tableau de bord ».
    { label: 'Vue d’ensemble', path: '', icon: 'grid', ready: true },

    // Hors §6 : file d'approbation TRANSVERSE (biens, véhicules, circuits,
    // prestataires). Maintenue en tête malgré tout — c'est le premier écran
    // ouvert chaque matin, et la fonction « valider » de quatre modules à la
    // fois ; l'enterrer en bas de liste au nom de l'ordre du cahier coûterait
    // plus qu'il ne rapporterait.
    { label: 'Validation', path: 'validation', icon: 'check', ready: true },

    // Hors §6 également, mais promis par le §7 (« traitement demandes »
    // confié à l'agent Kaikun) : la file des demandes clients déposées depuis
    // le site. Elle n'avait aucun écran jusqu'en F8.9 — l'alerte interne
    // invitait à ouvrir une file qui n'existait pas. Placée juste après
    // Validation : ce sont les deux écrans où l'équipe prend en charge ce qui
    // arrive de l'extérieur.
    { label: 'Demandes', path: 'demandes', icon: 'inbox', ready: true },

    // CDC §5 — module « Messages » (« conversation avec support Kaikun ou
    // prestataire affecté », pour TOUS les profils). Contractuel, et pourtant
    // sans écran d'équipe jusqu'en F8.12 : le client écrivait dans le vide.
    // Placée avec Demandes — même métier, ce qui arrive de l'extérieur.
    { label: 'Messages', path: 'messages', icon: 'chat', ready: true, badge: 'messages' },

    // CDC §6 — modules 2 « Utilisateurs » et 12 « Documents » (deux onglets).
    { label: 'Comptes', path: 'comptes', icon: 'id', ready: true },
    // CDC §6 — module 3 « Biens immobiliers ».
    { label: 'Catalogues', path: 'catalogues', icon: 'layers', ready: true },
    // CDC §6 — module 4 « Nuitées ».
    { label: 'Nuitées', path: 'nuitees', icon: 'calendar', ready: true },
    // CDC §6 — module 5 « Gestion locative ».
    { label: 'Gestion locative', path: 'gestion-locative', icon: 'key', ready: true },
    // CDC §6 — module 6 « Construction ». (Scindé de « Dossiers » en F7.3.c :
    // construction et gestion locative sont deux métiers distincts.)
    { label: 'Construction', path: 'construction', icon: 'hammer', ready: true },
    // CDC §6 — module 7 « Mobilité ».
    { label: 'Mobilité', path: 'mobilite', icon: 'car', ready: true },
    // CDC §6 — module 8 « Tourisme ».
    { label: 'Tourisme', path: 'tourisme', icon: 'compass', ready: true },
    // CDC §6 — module 9 « Team building ».
    { label: 'Team building', path: 'team-building', icon: 'briefcase', ready: true },
    // CDC §6 — module 10 « Diaspora ».
    { label: 'Diaspora', path: 'diaspora', icon: 'globe', ready: true },
    // CDC §6 — module 11 « Paiements ».
    { label: 'Paiements', path: 'paiements', icon: 'card', ready: true },
    // F8.16.a — l'autre sens du flux : ce que Kaikun REVERSE aux partenaires.
    // Rubrique à part et non un onglet de Paiements : ce ne sont ni les mêmes
    // objets (une dette n'est pas un règlement), ni le même moment du métier.
    { label: 'Reversements', path: 'reversements', icon: 'payout', ready: true },
    // CDC §6 — module 13 « Avis et qualité ». (Le 12 est dans Comptes.)
    { label: 'Avis & qualité', path: 'qualite', icon: 'star', ready: true },
    // CDC §6 — module 14 « Paramètres ».
    { label: 'Paramètres', path: 'parametres', icon: 'sliders', ready: true },

    // CDC §7 — poste de commandement de l'équipe (F7.1), hors des 14 modules.
    { label: 'Équipe', path: 'equipe', icon: 'users', ready: true },
    { label: 'Permissions', path: 'permissions', icon: 'shield', ready: true },
    { label: 'Pointeuse', path: 'pointeuse', icon: 'clock', ready: true },
  ];

  /**
   * Rubriques réellement AFFICHÉES, filtrées par les permissions (F7.4.a).
   *
   * Le cahier des charges §7 limite l'agent Kaikun à un « accès financier
   * limité » : lui présenter Paiements, Comptes ou Paramètres dans son menu
   * pour qu'il s'y heurte à un 403 était trompeur. Le rail ne montre plus que
   * les portes qui s'ouvrent.
   *
   * La table de correspondance est **partagée avec les routes** (`permissionsFor`)
   * : une rubrique masquée est aussi une route gardée, jamais l'un sans l'autre.
   * Un `computed` suffit — le signal `user` porte les permissions, le rail se
   * recalcule donc tout seul si le compte change (déconnexion / re-connexion).
   */
  protected readonly visibleNav = computed(() =>
    this.nav.filter((item) => {
      const required = permissionsFor(item.path);
      return required.length === 0 || this.auth.hasAnyPermission([...required]);
    }),
  );

  /**
   * Nombre de non-lus à afficher au bout d'une rubrique, `0` sinon (F8.13).
   * Même source que les espaces utilisateurs (`UnreadStore`) : l'agent voit
   * arriver un message client sans avoir à ouvrir la boîte de réception.
   */
  protected badgeFor(item: BoNavItem): number {
    return item.badge === 'messages' ? this.unread.messages() : 0;
  }

  /** Tiroir ouvert ? (petit écran uniquement ; rail permanent en desktop). */
  protected readonly sidebarOpen = signal(false);

  /**
   * Rail replié en colonne d'icônes ? (grand écran uniquement) — F11.1.
   *
   * ⚠️ À ne pas confondre avec `sidebarOpen`, qui est l'affaire du **petit**
   * écran : là, le rail est un tiroir qu'on fait entrer et sortir. Ici, il reste
   * en place et ne fait que maigrir. Les deux ne se rencontrent jamais — sous
   * 900px la classe `--replie` est neutralisée par la media query.
   *
   * Vingt rubriques, c'est un rail qui descend bas : replié, il rend ~180px de
   * largeur au contenu (tableaux du back-office) sans rien retirer de la
   * navigation, les icônes restant cliquables et infobullées.
   */
  protected readonly railReplie = signal(this.lireRailReplie());

  constructor() {
    this.router.events
      .pipe(
        filter((e) => e instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => this.sidebarOpen.set(false));
  }

  /** `routerLink` d'une rubrique à partir de son chemin relatif. */
  protected linkFor(path: string): unknown[] {
    return path ? ['/back-office', path] : ['/back-office'];
  }

  protected toggleSidebar(): void {
    this.sidebarOpen.update((open) => !open);
  }

  protected closeSidebar(): void {
    this.sidebarOpen.set(false);
  }

  /** Replie / déplie le rail (grand écran) et mémorise le choix. */
  protected toggleRail(): void {
    this.railReplie.update((replie) => {
      this.ecrireRailReplie(!replie);
      return !replie;
    });
  }

  /**
   * Infobulle d'une rubrique — uniquement quand le rail est replié.
   *
   * Replié, l'icône seule ne dit pas où elle mène : le `title` redonne le
   * libellé au survol. Déplié, le libellé est déjà écrit à côté ; y ajouter une
   * infobulle qui le répète serait du bruit, d'où le `null`.
   */
  protected infobulle(item: BoNavItem): string | null {
    return this.railReplie() ? item.label : null;
  }

  /** Lit la préférence de pli (SSR-safe : rien à lire côté serveur). */
  private lireRailReplie(): boolean {
    if (typeof window === 'undefined') {
      return false;
    }
    try {
      return window.localStorage.getItem(RAIL_REPLIE_KEY) === '1';
    } catch {
      return false;
    }
  }

  /** Écrit la préférence de pli, sans casser si le stockage est indisponible. */
  private ecrireRailReplie(replie: boolean): void {
    if (typeof window === 'undefined') {
      return;
    }
    try {
      if (replie) {
        window.localStorage.setItem(RAIL_REPLIE_KEY, '1');
      } else {
        window.localStorage.removeItem(RAIL_REPLIE_KEY);
      }
    } catch {
      // Stockage indisponible (navigation privée, quota…) : le pli vaut alors
      // pour la seule visite en cours. Sans conséquence fonctionnelle.
    }
  }

  /** Déconnexion : révoque le jeton puis renvoie vers la connexion. */
  protected logout(): void {
    this.auth.logout().subscribe({
      next: () => void this.router.navigateByUrl('/auth/connexion'),
      error: () => void this.router.navigateByUrl('/auth/connexion'),
    });
  }
}
