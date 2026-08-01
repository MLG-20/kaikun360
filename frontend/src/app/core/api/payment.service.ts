import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ApiEnvelope } from './api-response.model';

/** Moyen de règlement choisi par le client. */
export type PaymentMode = 'paytech' | 'manuel';

/** Nature d'un règlement, DÉDUITE du montant par le serveur. */
export type PaymentKind = 'acompte' | 'solde' | 'integral';

/** Une transaction, telle que l'API la renvoie. */
export interface Payment {
  id: number;
  reference: string;
  booking_id: number | null;
  amount_xof: number;
  status: string | null;
  status_label?: string | null;
  kind?: PaymentKind | null;
  mode?: string | null;
  created_at?: string | null;
}

/**
 * Marche à suivre d'un règlement Wave / Orange Money, composée par le serveur.
 *
 * Le numéro n'est **jamais** écrit en dur dans le frontend : il vient du
 * paramétrage back-office, pour qu'un changement de numéro n'exige pas un
 * redéploiement du site.
 */
export interface ManualInstructions {
  method: string;
  pay_to: string;
  reference: string;
  message: string;
  kind: PaymentKind | null;
  remaining_after_xof: number;
}

/**
 * Réponse d'une initiation de paiement.
 *
 * Deux formes selon le moyen choisi : en ligne, une **URL de redirection** vers
 * PayTech ; en manuel, des **instructions** à suivre au téléphone.
 */
export interface PaymentIntent {
  payment: Payment;
  redirect_url?: string;
  instructions?: ManualInstructions;
}

/**
 * Règlement d'une réservation par le client (F8.6).
 *
 * ⚠️ **Ce service comblait un trou de bout en bout.** Le backend savait initier
 * un paiement depuis B14 et le back-office savait les superviser depuis F7.2 —
 * mais aucun écran du site n'appelait jamais `POST /payments/initiate`. Un
 * client pouvait réserver, puis n'avait aucun moyen de payer. Le cycle était
 * complet des deux côtés sauf en son milieu.
 *
 * ⚠️ **Un paiement n'est JAMAIS confirmé par le retour du client.** PayTech
 * renvoie l'utilisateur sur le site, mais seul l'IPN signé, reçu de serveur à
 * serveur, fait passer la réservation à « confirmée ». Les écrans doivent donc
 * parler d'un paiement « en cours de confirmation », jamais d'un paiement
 * acquis — un client qui reviendrait à la main sur l'URL de succès ne doit
 * rien pouvoir déclencher.
 */
@Injectable({ providedIn: 'root' })
export class PaymentService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /**
   * Ouvre un règlement pour une réservation. `POST /payments/initiate`
   *
   * @param bookingId Réservation à régler (le serveur vérifie qu'on en est le titulaire).
   * @param amountXof Montant partiel (acompte). Omis = tout le reste dû.
   * @param mode      `paytech` (en ligne) ou `manuel` (Wave / Orange Money).
   */
  initiate(bookingId: number, amountXof?: number, mode: PaymentMode = 'paytech'): Observable<PaymentIntent> {
    const body: Record<string, unknown> = { booking_id: bookingId, mode };

    // On n'envoie le montant que s'il est fourni : le serveur interprète son
    // absence comme « le client solde tout ce qui reste dû ».
    if (amountXof !== undefined && amountXof !== null) {
      body['amount_xof'] = amountXof;
    }

    return this.http
      .post<ApiEnvelope<PaymentIntent>>(`${this.api}/payments/initiate`, body)
      .pipe(map((response) => response.data));
  }
}
