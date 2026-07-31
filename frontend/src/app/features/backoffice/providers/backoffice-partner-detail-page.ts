import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
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
  imports: [RouterLink, BackLinkComponent],
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
