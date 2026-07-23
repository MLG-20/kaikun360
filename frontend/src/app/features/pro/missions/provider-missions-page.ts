import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';

import { ProviderService } from '../../../core/api/provider.service';
import { PageMeta } from '../../../core/api/pagination.model';
import {
  MissionAction,
  MissionStatusValue,
  ProviderMission,
} from '../../../models/provider.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';

/** Un bouton d'action proposé sur une mission (transition de statut). */
interface MissionActionButton {
  /** Action envoyée au backend. */
  action: MissionAction;
  /** Libellé du bouton. */
  label: string;
  /** Style : `primary` (avancer), `ghost` (neutre), `danger` (refuser). */
  kind: 'primary' | 'ghost' | 'danger';
}

/** Tonalité de la pastille `.bk-status` selon le statut de la mission. */
const STATUS_TONE: Record<MissionStatusValue, 'pending' | 'ok' | 'active' | 'done' | 'cancelled'> = {
  affectee: 'pending',
  acceptee: 'ok',
  en_cours: 'active',
  terminee: 'done',
  refusee: 'cancelled',
  annulee: 'done',
};

/** Actions disponibles selon le statut courant (miroir de la machine à états backend). */
const ACTIONS: Partial<Record<MissionStatusValue, MissionActionButton[]>> = {
  affectee: [
    { action: 'accept', label: 'Accepter', kind: 'primary' },
    { action: 'refuse', label: 'Refuser', kind: 'danger' },
  ],
  acceptee: [{ action: 'start', label: 'Démarrer la mission', kind: 'primary' }],
  en_cours: [{ action: 'complete', label: 'Marquer terminée', kind: 'primary' }],
};

/**
 * Écran « Missions reçues » de l'espace prestataire (F5.2), monté sous
 * `/espace-prestataire/missions`. Liste paginée des missions affectées au
 * prestataire connecté (`GET /provider-missions/mine`, 15/page).
 *
 * Chaque mission affiche son montant, la commission Kaikun et le **net**
 * revenant au prestataire, sa date prévue et son statut. Selon ce statut, des
 * **actions** font progresser la mission (`PATCH .../{action}`) : accepter /
 * refuser une mission affectée, la démarrer une fois acceptée, la marquer
 * terminée une fois en cours. Le backend valide la transition (422 si
 * impossible) ; on remplace alors la mission par sa version à jour.
 */
@Component({
  selector: 'app-provider-missions-page',
  imports: [BackLinkComponent],
  templateUrl: './provider-missions-page.html',
  styleUrl: './provider-missions-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderMissionsPageComponent {
  private readonly providers = inject(ProviderService);

  // — État de l'écran —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly items = signal<ProviderMission[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);

  /** Mission en cours de transition (boutons désactivés le temps de l'appel). */
  protected readonly busyId = signal<number | null>(null);
  /** Message d'erreur d'une transition refusée (422/403/réseau). */
  protected readonly actionError = signal<string | null>(null);

  /** Y a-t-il d'autres pages avant / après la page courante ? */
  protected readonly hasPrev = computed(() => (this.meta()?.current_page ?? 1) > 1);
  protected readonly hasNext = computed(() => {
    const m = this.meta();
    return !!m && m.current_page < m.last_page;
  });

  constructor() {
    this.load(1);
  }

  /** Charge une page de missions (remplace la liste affichée). */
  protected load(page: number): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.actionError.set(null);
    this.providers.myMissions(page).subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.meta.set(res.meta);
        this.loading.set(false);
        if (typeof window !== 'undefined') {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      },
      error: () => {
        this.loading.set(false);
        this.loadError.set(true);
      },
    });
  }

  protected prev(): void {
    if (this.hasPrev()) {
      this.load((this.meta()?.current_page ?? 2) - 1);
    }
  }

  protected next(): void {
    if (this.hasNext()) {
      this.load((this.meta()?.current_page ?? 0) + 1);
    }
  }

  /** Boutons d'action proposés pour le statut d'une mission (vide si clôturée). */
  protected actionsFor(status: MissionStatusValue): MissionActionButton[] {
    return ACTIONS[status] ?? [];
  }

  /** Tonalité de la pastille de statut. */
  protected toneOf(status: MissionStatusValue): string {
    return STATUS_TONE[status];
  }

  /**
   * Applique une transition à une mission. « Refuser » demande une confirmation
   * (irréversible). En cas de succès, la mission est remplacée par sa version à
   * jour dans la liste ; en cas d'échec, un message est affiché.
   */
  protected act(mission: ProviderMission, action: MissionAction): void {
    if (this.busyId() !== null) {
      return;
    }
    if (action === 'refuse' && typeof window !== 'undefined') {
      const ok = window.confirm(
        `Refuser la mission « ${mission.title} » ? Cette action est définitive.`,
      );
      if (!ok) {
        return;
      }
    }

    this.busyId.set(mission.id);
    this.actionError.set(null);
    this.providers.transitionMission(mission.id, action).subscribe({
      next: (res) => {
        const updated = res.data.mission;
        this.items.update((list) => list.map((m) => (m.id === updated.id ? updated : m)));
        this.busyId.set(null);
      },
      error: () => {
        this.actionError.set(
          "Cette action n'a pas pu être appliquée. La mission a peut-être changé de statut ; actualisez la page.",
        );
        this.busyId.set(null);
      },
    });
  }

  /** Net revenant au prestataire (montant − commission Kaikun). */
  protected net(mission: ProviderMission): string | null {
    return formatFcfa(mission.amount_xof - mission.commission_xof);
  }

  /** Formate un montant FCFA. */
  protected fcfa(value: number): string | null {
    return formatFcfa(value);
  }

  /** Formate une date/heure prévue (« 4 août 2026 à 14:00 »), ou null. */
  protected schedule(iso: string | null): string | null {
    if (!iso) {
      return null;
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
      return null;
    }
    return d.toLocaleString('fr-FR', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }
}
