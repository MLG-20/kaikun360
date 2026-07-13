import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink, RouterOutlet } from '@angular/router';

/**
 * Layout des pages d'authentification (F1.1).
 *
 * Écran scindé : à gauche la signature de marque Kaikun (fond navy, arguments de
 * confiance), à droite la carte de formulaire routée (connexion, inscription,
 * vérification, récupération). Sur mobile, le panneau de marque se réduit et le
 * formulaire occupe l'écran. Volontairement SANS l'en-tête/méga-nav du site.
 */
@Component({
  selector: 'app-auth-layout',
  imports: [RouterOutlet, RouterLink],
  templateUrl: './auth-layout.html',
  styleUrl: './auth-layout.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AuthLayoutComponent {
  /** Arguments de confiance affichés sur le panneau de marque. */
  protected readonly proofs = [
    'Biens et prestataires vérifiés',
    'Suivi de vos demandes de bout en bout',
    'Paiement encadré et sécurisé',
  ];
}
