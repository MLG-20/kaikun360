import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs/operators';

import { AuthService } from '../../core/auth/auth.service';
import { permissionsFor } from '../../features/backoffice/backoffice-permissions';

/** Clé d'icône du rail (rendue en SVG inline dans le template). */
type BoIcon =
  | 'grid'
  | 'check'
  | 'layers'
  | 'car'
  | 'compass'
  | 'calendar'
  | 'card'
  | 'hammer'
  | 'key'
  | 'id'
  | 'star'
  | 'briefcase'
  | 'globe'
  | 'users'
  | 'shield'
  | 'clock'
  | 'sliders';

/** Une rubrique de navigation du poste de commandement. */
interface BoNavItem {
  label: string;
  /** Chemin relatif à `/back-office` (`''` = accueil). */
  path: string;
  icon: BoIcon;
  /** L'écran est-il construit ? (sinon « Bientôt », non cliquable). */
  ready: boolean;
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
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './backoffice-layout.html',
  styleUrl: './backoffice-layout.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeLayoutComponent {
  private readonly auth = inject(AuthService);
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

  /** Tiroir ouvert ? (petit écran uniquement ; rail permanent en desktop). */
  protected readonly sidebarOpen = signal(false);

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

  /** Déconnexion : révoque le jeton puis renvoie vers la connexion. */
  protected logout(): void {
    this.auth.logout().subscribe({
      next: () => void this.router.navigateByUrl('/auth/connexion'),
      error: () => void this.router.navigateByUrl('/auth/connexion'),
    });
  }
}
