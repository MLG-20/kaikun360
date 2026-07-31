import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { AdminService, ReviewDossier, ReviewModeration } from '../../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../../core/api/api-response.model';

/**
 * **Dossier d'un avis** (F8.2.d) — modérer, c'est arbitrer, pas trier.
 *
 * La file de modération affiche un commentaire tronqué dans une cellule et deux
 * boutons. Publier ou rejeter sur cette base revient à jouer à pile ou face :
 * il manque le **contexte**, et c'est lui qui tranche.
 *
 * Cette fiche apporte trois choses :
 *   - le **commentaire entier**, avec son auteur joignable ;
 *   - les **autres avis publiés** de la même ressource, sa moyenne et le nombre
 *     de plaintes déjà publiées. Une plainte isolée au milieu de quinze avis à
 *     cinq étoiles n'est pas un signal ; la troisième plainte identique en un
 *     mois en est un — et relève alors de la sanction, pas de la modération ;
 *   - quand la ressource notée est un **prestataire**, le lien vers sa fiche :
 *     c'est là que la sanction se décide.
 *
 * La décision (publier / rejeter) se prend ici comme depuis la file : c'est le
 * même appel, aux mêmes règles serveur.
 */
@Component({
  selector: 'app-backoffice-review-detail-page',
  imports: [RouterLink],
  templateUrl: './backoffice-review-detail-page.html',
  // Feuille COMMUNE à toutes les fiches du back-office (F8.2) : une fiche en
  // appelle une autre, elles doivent se ressembler.
  styleUrl: '../../shared/dossier.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeReviewDetailPageComponent {
  private readonly admin = inject(AdminService);
  private readonly route = inject(ActivatedRoute);

  private readonly id = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly dossier = signal<ReviewDossier | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  protected readonly processing = signal(false);
  protected readonly actionError = signal<string | null>(null);

  constructor() {
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.error.set(false);

    this.admin.reviewDossier(this.id).subscribe({
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

  /**
   * Publie ou rejette l'avis.
   *
   * On recharge plutôt que de rediriger : l'agent qui vient de publier veut
   * souvent enchaîner sur la fiche du prestataire, désormais un cran plus
   * chargée. Le renvoyer d'office à la file lui ferait refaire le chemin.
   */
  protected moderate(status: ReviewModeration): void {
    if (this.processing()) return;

    this.processing.set(true);
    this.actionError.set(null);

    this.admin.moderateReview(this.id, status).subscribe({
      next: () => {
        this.processing.set(false);
        this.load();
      },
      error: (error: HttpErrorResponse) => {
        this.processing.set(false);
        this.actionError.set(this.messageFor(error));
      },
    });
  }

  /** Classe CSS du badge de statut d'un avis. */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'publie':
        return 'is-ok';
      case 'rejete':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Type de ressource notée, en clair. */
  protected resourceTypeLabel(type: string): string {
    switch (type) {
      case 'stay':
        return 'Nuitée';
      case 'vehicle':
        return 'Véhicule';
      case 'experience':
        return 'Circuit touristique';
      case 'provider':
        return 'Prestataire';
      default:
        return type;
    }
  }

  /** Étoiles pleines (repère visuel ; la note chiffrée reste à côté). */
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

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 403) {
      return "Vous n'avez pas le droit de modérer les avis. Demandez la délégation à un administrateur.";
    }
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      return body?.message ?? 'Cet avis a déjà été tranché.';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
