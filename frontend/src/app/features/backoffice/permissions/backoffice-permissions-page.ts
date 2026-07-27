import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';

import {
  AdminService,
  PermissionCatalogItem,
  TeamMember,
} from '../../../core/api/admin.service';
import { AuthService } from '../../../core/auth/auth.service';

/** Un groupe de la matrice (Validation / Exploitation / Gouvernance). */
interface PermissionGroup {
  name: string;
  items: PermissionCatalogItem[];
}

/**
 * Écran **Permissions** du back-office (F7.1.g) — délégation « grant pur ».
 *
 * C'est ici que le super administrateur décide, **agent par agent**, des dossiers
 * qu'il a le droit de traiter (les 12 permissions délégables, cochées une par
 * une). On choisit un agent à gauche, on coche/décoche sa matrice à droite, on
 * enregistre : le back remplace l'ensemble de ses permissions directes.
 *
 * Garde-fous reflétés : les permissions de **gouvernance** ne sont modifiables
 * que par un super_admin (sinon cases verrouillées) ; seuls les **agents** sont
 * concernés (un admin a déjà tout).
 */
@Component({
  selector: 'app-backoffice-permissions-page',
  imports: [],
  templateUrl: './backoffice-permissions-page.html',
  styleUrl: './backoffice-permissions-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficePermissionsPageComponent {
  private readonly admin = inject(AdminService);
  private readonly auth = inject(AuthService);
  private readonly route = inject(ActivatedRoute);

  /** Agents à qui déléguer des dossiers. */
  protected readonly agents = signal<TeamMember[]>([]);
  protected readonly loadingAgents = signal(true);

  /** Agent sélectionné + état de sa matrice. */
  protected readonly selected = signal<TeamMember | null>(null);
  protected readonly catalog = signal<PermissionCatalogItem[]>([]);
  protected readonly granted = signal<string[]>([]);
  protected readonly loadingMatrix = signal(false);

  protected readonly saving = signal(false);
  protected readonly saveSuccess = signal<string | null>(null);
  protected readonly error = signal<string | null>(null);

  protected readonly isSuperAdmin = computed(() => this.auth.hasRole('super_admin'));

  /** Catalogue regroupé par groupe d'affichage. */
  protected readonly groups = computed<PermissionGroup[]>(() => {
    const byGroup = new Map<string, PermissionCatalogItem[]>();
    for (const item of this.catalog()) {
      const list = byGroup.get(item.group) ?? [];
      list.push(item);
      byGroup.set(item.group, list);
    }
    return Array.from(byGroup, ([name, items]) => ({ name, items }));
  });

  constructor() {
    this.admin.team({ role: 'agent_kaikun' }).subscribe({
      next: (page) => {
        this.agents.set(page.data);
        this.loadingAgents.set(false);
        // Présélection éventuelle via ?member=<id> (lien depuis l'écran Équipe).
        const memberId = Number(this.route.snapshot.queryParamMap.get('member'));
        if (memberId) {
          const agent = page.data.find((a) => a.id === memberId);
          if (agent) this.select(agent);
        }
      },
      error: () => this.loadingAgents.set(false),
    });
  }

  /** Sélectionne un agent et charge sa matrice de délégation. */
  protected select(agent: TeamMember): void {
    this.selected.set(agent);
    this.saveSuccess.set(null);
    this.error.set(null);
    this.loadingMatrix.set(true);

    this.admin.teamPermissions(agent.id).subscribe({
      next: (state) => {
        this.catalog.set(state.catalog);
        this.granted.set(state.granted);
        this.loadingMatrix.set(false);
      },
      error: () => {
        this.error.set('Impossible de charger la matrice de cet agent.');
        this.loadingMatrix.set(false);
      },
    });
  }

  protected isGranted(value: string): boolean {
    return this.granted().includes(value);
  }

  /** Une case est-elle verrouillée pour l'acteur courant ? (gouvernance ⇒ super_admin) */
  protected isLocked(item: PermissionCatalogItem): boolean {
    return item.requires_super_admin && !this.isSuperAdmin();
  }

  /** Coche/décoche un dossier (sans enregistrer tout de suite). */
  protected toggle(item: PermissionCatalogItem): void {
    if (this.isLocked(item)) return;
    this.saveSuccess.set(null);
    this.granted.update((list) =>
      list.includes(item.value) ? list.filter((v) => v !== item.value) : [...list, item.value],
    );
  }

  /** Enregistre la délégation (remplace l'ensemble des dossiers de l'agent). */
  protected save(): void {
    const agent = this.selected();
    if (!agent || this.saving()) return;

    this.saving.set(true);
    this.saveSuccess.set(null);
    this.error.set(null);

    this.admin.syncTeamPermissions(agent.id, this.granted()).subscribe({
      next: (member) => {
        this.saving.set(false);
        this.saveSuccess.set(`Dossiers de ${member.name} mis à jour.`);
      },
      error: (err: HttpErrorResponse) => {
        this.saving.set(false);
        const body = err.error as { message?: string } | null;
        this.error.set(
          err.status === 403
            ? (body?.message ?? 'Seul un super administrateur peut déléguer ces dossiers.')
            : (body?.message ?? 'Enregistrement impossible. Réessayez.'),
        );
      },
    });
  }
}
