import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { AdminService, PartnerDossier } from '../../../core/api/admin.service';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';

/**
 * **Fiche d'un prestataire** (F8.2.c) — guide, restaurant, loueur, artisan.
 *
 * ⚠️ Cette fiche n'appartient à AUCUN écran : elle est ouverte depuis l'onglet
 * « Guides & restaurants » du Tourisme **et** depuis l'écran Avis & qualité, qui
 * parlent des mêmes prestataires sous deux angles. D'où sa place hors des
 * dossiers d'écran (`features/backoffice/providers/`) et sa route de premier
 * niveau `/back-office/prestataire/:id` — la ranger sous « tourisme » aurait
 * laissé croire que les guides sont une espèce à part.
 *
 * La liste affiche une note et un compteur d'avertissements. Deux chiffres qui
 * ne suffisent pas à décider : **3,2 sur quarante avis** ne dit pas la même
 * chose que 3,2 sur deux, et un avertissement sans son motif ne se défend pas
 * devant l'intéressé. La fiche donne donc les **avis en clair**, les
 * **certifications** déposées, le **compte** derrière l'enseigne — c'est lui
 * qu'on appelle — et le **journal**, où la sanction figure avec sa raison.
 *
 * Lecture seule : avertir ou suspendre reste à l'écran Avis & qualité, qui trace
 * chaque décision. La fiche sert à savoir **quoi** décider.
 */
@Component({
  selector: 'app-backoffice-partner-detail-page',
  imports: [FormsModule, RouterLink, BackLinkComponent],
  templateUrl: './backoffice-partner-detail-page.html',
  // Feuille COMMUNE à toutes les fiches du back-office (F8.2) : une fiche en
  // appelle une autre, elles doivent se ressembler.
  styleUrl: '../shared/dossier.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficePartnerDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);

  private readonly id = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly dossier = signal<PartnerDossier | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  /**
   * La note, avec le nombre d'avis qui la fonde.
   *
   * Une moyenne seule se lit mal : elle est affichée ici sur base combien, pour
   * que personne ne sanctionne sur deux avis comme sur quarante.
   */
  protected readonly rating = computed(() => {
    const p = this.dossier()?.provider;
    if (!p?.rating_count) return null;
    return { avg: Number(p.rating_avg ?? 0).toFixed(1), count: p.rating_count };
  });

  /** Avis publiés seulement — un avis en attente n'a pas encore été modéré. */
  protected readonly publishedReviews = computed(
    () => this.dossier()?.reviews.filter((r) => r.status === 'publie') ?? [],
  );

  /** Avis récents à un ou deux étoiles : ce qui motive une sanction. */
  protected readonly negativeReviews = computed(() =>
    this.publishedReviews().filter((r) => r.rating <= 2),
  );

  // --- Confier une mission (F8.15.d) ------------------------------------------
  //
  // ⚠️ `POST /providers/{id}/missions` était écrit depuis B10.3 et n'avait AUCUN
  // appelant : aucune mission n'était créable. L'espace prestataire (« Mes
  // missions », « Mes revenus ») ne pouvait donc afficher que des données de
  // seeder, et la commission de la marketplace Pro ne se déclenchait jamais.
  // C'était aussi le verrou qui rendait la NOTATION D'UN PRESTATAIRE
  // inatteignable : la policy exige une mission TERMINÉE comme preuve de
  // consommation — raison pour laquelle F8.15.a n'avait pu couvrir que les
  // réservations.
  //
  // ⚠️ Le geste est ici, et non à l'écran « Avis & qualité » où vivent les
  // sanctions : affecter n'est pas sanctionner. C'est un acte d'exploitation
  // quotidien, et il se décide là où l'on juge le prestataire — sur sa note,
  // ses avis et la charge qu'il a déjà.

  /** Le formulaire d'affectation est-il déplié ? */
  protected readonly assigning = signal(false);
  protected readonly saving = signal(false);
  protected readonly assignError = signal<string | null>(null);
  /** Référence de la mission créée (bandeau de succès). */
  protected readonly assigned = signal<string | null>(null);

  protected missionTitle = '';
  protected missionDescription = '';
  protected missionAmount = 0;
  protected missionScheduledAt = '';

  /** Missions en cours (ni terminées ni annulées) : la charge du prestataire. */
  protected readonly openMissions = computed(
    () =>
      this.dossier()?.missions.filter(
        (m) => m.status !== 'terminee' && m.status !== 'annulee',
      ) ?? [],
  );

  /** Un prestataire non validé ne peut pas recevoir de mission (règle serveur). */
  protected readonly canAssign = computed(
    () => this.dossier()?.provider.status === 'valide',
  );

  protected toggleAssign(): void {
    this.assigning.update((open) => !open);
    this.missionTitle = '';
    this.missionDescription = '';
    this.missionAmount = 0;
    this.missionScheduledAt = '';
    this.assignError.set(null);
    this.assigned.set(null);
  }

  protected submitMission(): void {
    if (this.saving() || !this.missionTitle.trim() || this.missionAmount <= 0) {
      return;
    }

    this.saving.set(true);
    this.assignError.set(null);

    this.admin
      .assignMission(this.id, {
        title: this.missionTitle.trim(),
        description: this.missionDescription.trim() || null,
        amount_xof: this.missionAmount,
        scheduled_at: this.missionScheduledAt || null,
      })
      .subscribe({
        next: (mission) => {
          this.saving.set(false);
          this.assigned.set(mission.reference);
          // On recharge la fiche entière : la mission neuve doit apparaître dans
          // la liste, et le prestataire vient de changer de charge.
          this.load();
        },
        error: (err: { error?: { errors?: Record<string, string[]>; message?: string } }) => {
          this.saving.set(false);
          const first = err?.error?.errors ? Object.values(err.error.errors)[0]?.[0] : null;
          this.assignError.set(
            first ?? err?.error?.message ?? "La mission n'a pas pu être confiée. Réessayez.",
          );
        },
      });
  }

  /** Montant formaté en FCFA. */
  protected xof(value: number | null): string {
    if (value === null || value === undefined) return '—';
    return `${value.toLocaleString('fr-FR')} F`;
  }

  constructor() {
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.error.set(false);

    this.admin.partnerDossier(this.id).subscribe({
      next: (dossier) => {
        this.dossier.set(dossier);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  /** Classe CSS du badge de statut d'un prestataire. */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'valide':
        return 'is-ok';
      case 'en_attente':
        return 'is-pending';
      case 'suspendu':
        return 'is-warn';
      case 'refuse':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Étoiles pleines d'une note (repère visuel, l'écrit reste à côté). */
  protected stars(rating: number): string {
    return '★'.repeat(Math.max(0, Math.min(5, rating)));
  }

  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  protected dateTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }
}
