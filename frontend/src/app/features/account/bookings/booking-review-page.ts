import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { forkJoin, of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { BookingService } from '../../../core/api/booking.service';
import { MyReview, ReviewService } from '../../../core/api/review.service';
import { SPACE_CONFIG } from '../../../layouts/space-layout/space.config';
import { Booking } from '../../../models/booking.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';

/**
 * État de l'écran. `done` couvre les deux façons d'avoir déjà donné son avis :
 * on vient de l'écrire, ou on l'avait écrit lors d'une visite précédente.
 */
type LoadState = 'loading' | 'ready' | 'done' | 'ineligible' | 'notfound' | 'forbidden' | 'failed';

@Component({
  selector: 'app-booking-review-page',
  imports: [FormsModule, RouterLink, BackLinkComponent],
  templateUrl: './booking-review-page.html',
  styleUrl: './booking-review-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Dépôt d'un avis sur une réservation terminée (F8.15.a), monté sous
 * `<espace>/reservations/:id/avis`.
 *
 * POURQUOI CET ÉCRAN EXISTE
 * -------------------------
 * `POST /reviews` était écrit depuis B12.2 et **n'avait aucun appelant** : le
 * client n'avait nulle part où noter, le back-office modérait une file que rien
 * n'alimentait, et la note des prestataires ne pouvait jamais monter. Le cahier
 * des charges demande pourtant des avis sur les fiches (§4.2), la notation des
 * prestataires (Kaikun Pro) et leur modération (§6 « Avis et qualité »).
 *
 * ⚠️ **On note la chose réservée, pas la réservation.** La cible d'un avis est
 * le logement, le véhicule ou l'expérience (`reviewable_type`/`reviewable_id`,
 * servis par `BookingResource`) : deux séjours dans le même logement visent donc
 * le même avis, et le serveur n'en accepte qu'un. D'où l'état `done`, qui
 * s'appuie sur `GET /reviews/mine` — et non sur `GET /reviews`, aveugle aux avis
 * encore en modération (cf. `ReviewService`).
 *
 * Écran dédié plutôt qu'un formulaire inline dans la liste : écrire un avis
 * demande de se souvenir du séjour, ce qu'un champ coincé entre deux cartes
 * n'invite pas à faire. Le récapitulatif de la réservation est donc rappelé
 * au-dessus du formulaire.
 */
export class BookingReviewPageComponent {
  private readonly bookings = inject(BookingService);
  private readonly reviews = inject(ReviewService);
  private readonly route = inject(ActivatedRoute);
  /** Espace où l'écran est monté : aucun lien n'est écrit en dur sur `/mon-espace`. */
  protected readonly space = inject(SPACE_CONFIG);
  protected readonly bookingsBase = this.space.basePath + '/reservations';

  // — État de l'écran —
  protected readonly state = signal<LoadState>('loading');
  protected readonly booking = signal<Booking | null>(null);
  /** L'avis déjà déposé sur cette cible, quand il y en a un (état `done`). */
  protected readonly existing = signal<MyReview | null>(null);

  // — Saisie —
  /** Note choisie, de 1 à 5. 0 = rien de choisi (le bouton reste inerte). */
  protected rating = 0;
  protected comment = '';
  protected readonly sending = signal(false);
  protected readonly error = signal<string | null>(null);

  /** Gabarit fixe des 5 étoiles (évite de recréer un tableau à chaque rendu). */
  protected readonly stars = [1, 2, 3, 4, 5];

  /**
   * Charge la réservation ET mes avis en parallèle : le second dit si la cible a
   * déjà été notée. Les deux sont nécessaires avant de décider quoi afficher —
   * ouvrir le formulaire pour le refermer une seconde plus tard serait pire que
   * d'attendre.
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((params) => params.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.booking.set(null);
        this.existing.set(null);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        return forkJoin({
          booking: this.bookings.get(id),
          // Un échec de « mes avis » ne doit pas condamner l'écran : sans lui on
          // affiche le formulaire, et le serveur reste le dernier juge (422).
          mine: this.reviews.mine().pipe(catchError(() => of(null))),
        }).pipe(
          tap(({ booking, mine }) => {
            const bk = booking.data.booking;
            this.booking.set(bk);
            this.state.set(this.decide(bk, mine?.data.reviews ?? []));
          }),
          catchError((err: { status?: number }) => {
            this.state.set(this.stateForError(err?.status));
            return of(null);
          }),
        );
      }),
    ),
  );

  /**
   * Décide de l'état d'affichage : avis déjà donné, cible non notable ou service
   * non terminé, ou formulaire ouvert.
   */
  private decide(booking: Booking, mine: MyReview[]): LoadState {
    const already = mine.find(
      (r) =>
        r.reviewable_type === booking.reviewable_type &&
        r.reviewable_id === booking.reviewable_id,
    );

    if (already) {
      this.existing.set(already);
      return 'done';
    }

    // `can_review` est le miroir exact de la policy serveur : ne pas le
    // redériver ici, sous peine de proposer un formulaire voué au 403.
    return booking.can_review ? 'ready' : 'ineligible';
  }

  /** Enregistre la note (les étoiles sont des boutons, pas un `input range`). */
  protected choose(value: number): void {
    this.rating = value;
    this.error.set(null);
  }

  /** Envoie l'avis. Le serveur reste juge de l'éligibilité et de l'unicité. */
  protected submit(): void {
    const bk = this.booking();
    if (!bk || !bk.reviewable_type || !bk.reviewable_id || this.rating < 1) {
      return;
    }

    this.sending.set(true);
    this.error.set(null);

    this.reviews
      .submit({
        reviewable_type: bk.reviewable_type,
        reviewable_id: bk.reviewable_id,
        comment: this.comment.trim() || null,
        rating: this.rating,
      })
      .subscribe({
        next: (res) => {
          this.sending.set(false);
          // On rebascule sur l'état « déjà donné » avec l'avis tout frais : le
          // client voit ce qu'il a écrit et qu'il attend la modération.
          this.existing.set({
            ...res.data.review,
            reviewable_type: bk.reviewable_type,
            reviewable_id: bk.reviewable_id,
          });
          this.state.set('done');
        },
        error: (err: { status?: number; error?: { message?: string } }) => {
          this.sending.set(false);
          this.error.set(this.messageForError(err));
        },
      });
  }

  /**
   * Message d'échec du dépôt. Les deux refus attendus (403 « vous n'avez pas
   * consommé », 422 « déjà noté ») méritent d'être dits en clair : « une erreur
   * est survenue » laisserait le client réessayer indéfiniment.
   */
  private messageForError(err: { status?: number; error?: { message?: string } }): string {
    if (err?.status === 403) {
      return "Vous ne pouvez donner votre avis qu'après un service terminé.";
    }
    if (err?.status === 422) {
      return err.error?.message ?? 'Vous avez déjà laissé un avis sur cet élément.';
    }
    return "Votre avis n'a pas pu être enregistré. Merci de réessayer plus tard.";
  }

  /** Traduit un code HTTP d'erreur de chargement en état d'affichage. */
  private stateForError(status?: number): LoadState {
    if (status === 404) {
      return 'notfound';
    }
    if (status === 403) {
      return 'forbidden';
    }
    return 'failed';
  }
}
