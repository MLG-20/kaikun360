import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { BookingService } from '../../../core/api/booking.service';
import { ManualInstructions, PaymentMode, PaymentService } from '../../../core/api/payment.service';
import { Booking } from '../../../models/booking.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';

/** État de chargement de l'écran. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'forbidden' | 'failed';

/**
 * **Régler une réservation** (F8.6) — `/mon-espace/reservations/:id/paiement`.
 *
 * ⚠️ **Le chaînon qui manquait.** Le backend savait initier un paiement depuis
 * B14, le back-office savait les superviser depuis F7.2, et le PSP a été
 * réaligné en F8.5 — mais **aucun écran du site n'appelait jamais
 * `POST /payments/initiate`**. Un client pouvait réserver une nuitée, recevoir
 * sa référence… et n'avoir aucun moyen de payer. Le cycle était complet des deux
 * côtés, sauf en son milieu.
 *
 * **Un écran dédié, pas un bouton dans une liste.** Payer engage de l'argent :
 * l'utilisateur doit voir ce qu'il doit, ce qu'il a déjà versé, et ce qu'il
 * s'apprête à envoyer, avant qu'on ne le sorte du site. Un bouton « Payer » posé
 * sur une carte de liste ferait partir un client d'un clic malheureux.
 *
 * **Deux moyens, deux logiques :**
 *  - **en ligne (PayTech)** : le serveur crée la demande et renvoie une URL ;
 *    on quitte le site vers la page de paiement du PSP ;
 *  - **Wave / Orange Money** : aucun appel au PSP. Le serveur renvoie la marche
 *    à suivre — numéro (paramétré au back-office, jamais écrit en dur) et
 *    référence à mentionner — et un agent confirmera l'encaissement à la main.
 *
 * **L'acompte** est proposé parce que le backend le gère (`natureDuReglement`) :
 * un séjour à 400 000 FCFA se règle souvent en deux fois. Le montant est plafonné
 * au reste dû côté serveur — encaisser au-delà créerait un trop-perçu à rembourser.
 *
 * ⚠️ **Rien ici ne confirme un paiement.** Seul l'IPN signé, reçu de serveur à
 * serveur, fait passer la réservation à « confirmée ». Cet écran ne fait
 * qu'ouvrir le règlement.
 */
@Component({
  selector: 'app-booking-payment-page',
  imports: [FormsModule, RouterLink, BackLinkComponent],
  templateUrl: './booking-payment-page.html',
  styleUrl: './booking-payment-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BookingPaymentPageComponent {
  private readonly bookings = inject(BookingService);
  private readonly payments = inject(PaymentService);
  private readonly route = inject(ActivatedRoute);

  protected readonly state = signal<LoadState>('loading');
  protected readonly booking = signal<Booking | null>(null);

  /** Moyen de règlement choisi. */
  protected mode: PaymentMode = 'paytech';

  /** Règle-t-on tout le reste dû, ou un acompte ? */
  protected partial = false;

  /** Montant de l'acompte, quand `partial` est vrai. */
  protected partialAmount = 0;

  /** Envoi en cours : verrouille le bouton (un double clic = deux règlements). */
  protected readonly sending = signal(false);
  protected readonly error = signal<string | null>(null);

  /** Marche à suivre Wave / OM, une fois le règlement manuel ouvert. */
  protected readonly instructions = signal<ManualInstructions | null>(null);

  /** Montant réellement envoyé au serveur. */
  protected readonly amountToPay = computed(() => {
    const remaining = this.booking()?.remaining_xof ?? 0;
    if (!this.partial) {
      return remaining;
    }
    return Math.min(Math.max(0, Math.round(this.partialAmount)), remaining);
  });

  constructor() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    if (Number.isNaN(id)) {
      this.state.set('notfound');
    } else {
      this.load(id);
    }
  }

  private load(id: number): void {
    this.bookings.get(id).subscribe({
      next: (envelope) => {
        const booking = envelope.data.booking;
        this.booking.set(booking);
        this.partialAmount = booking.remaining_xof;
        this.state.set('ready');
      },
      error: (err: { status?: number }) => {
        if (err?.status === 404) {
          this.state.set('notfound');
        } else if (err?.status === 403) {
          this.state.set('forbidden');
        } else {
          this.state.set('failed');
        }
      },
    });
  }

  /**
   * Ouvre le règlement. En ligne, on quitte le site vers PayTech ; en manuel, on
   * affiche la marche à suivre sans jamais contacter le PSP.
   */
  protected pay(): void {
    const booking = this.booking();
    if (!booking || this.sending()) {
      return;
    }

    const amount = this.amountToPay();
    if (amount <= 0) {
      this.error.set('Indiquez un montant supérieur à zéro.');
      return;
    }

    this.sending.set(true);
    this.error.set(null);

    // Montant omis quand on solde tout : le serveur calcule alors lui-même le
    // reste dû, ce qui évite qu'un écart de quelques francs (arrondi, paiement
    // arrivé entre-temps) ne fasse échouer le règlement.
    const requested = this.partial ? amount : undefined;

    this.payments.initiate(booking.id, requested, this.mode).subscribe({
      next: (intent) => {
        if (intent.redirect_url) {
          // On QUITTE l'application : pas de navigation Angular, c'est un autre
          // domaine. `assign` conserve l'historique — le client peut revenir.
          window.location.assign(intent.redirect_url);
          return;
        }

        this.sending.set(false);
        this.instructions.set(intent.instructions ?? null);
      },
      error: (err: { status?: number; error?: { message?: string } }) => {
        this.sending.set(false);
        this.error.set(this.messageFor(err));
      },
    });
  }

  /**
   * Message d'erreur lisible. On distingue la panne du PSP (502) du refus
   * métier : « réessayez » n'a aucun sens face à une réservation déjà payée.
   */
  private messageFor(err: { status?: number; error?: { message?: string } }): string {
    if (err?.status === 502) {
      return 'Le service de paiement est momentanément indisponible. Réessayez dans quelques minutes, ou réglez par Wave / Orange Money.';
    }
    if (err?.status === 422) {
      return err.error?.message ?? 'Ce règlement n’est pas possible en l’état.';
    }
    if (err?.status === 403) {
      return 'Cette réservation ne vous appartient pas.';
    }
    return 'Impossible d’ouvrir le règlement pour le moment. Merci de réessayer.';
  }

  protected money(value: number | null | undefined): string {
    return formatFcfa(value) ?? '—';
  }
}
