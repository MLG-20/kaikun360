/**
 * Tonalité d'affichage d'un statut de réservation — regroupe les variantes
 * d'annulation (client/prestataire/admin) sous une même tonalité `cancelled`
 * pour teinter la pastille via l'attribut `data-tone`. Partagé par la LISTE
 * « Mes réservations » et l'écran de DÉTAIL d'une réservation.
 */
export type BookingTone = 'pending' | 'ok' | 'active' | 'done' | 'cancelled';

export function bookingTone(status: string | null): BookingTone {
  switch (status) {
    case 'en_attente':
      return 'pending';
    case 'confirmee':
      return 'ok';
    case 'en_cours':
      return 'active';
    case 'terminee':
      return 'done';
    default:
      return 'cancelled'; // annulee_client / annulee_prestataire / annulee_admin
  }
}
