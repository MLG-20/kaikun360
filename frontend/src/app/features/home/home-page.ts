import { ChangeDetectionStrategy, Component } from '@angular/core';

import { GalleryComponent } from '../../shared/components/gallery/gallery';
import { ListingCardComponent } from '../../shared/components/listing-card/listing-card';
import { OrbitHeroComponent } from '../../shared/components/orbit-hero/orbit-hero';
import { VerificationBadgeComponent } from '../../shared/components/verification-badge/verification-badge';

/**
 * Page d'accueil (aperçu provisoire, relocalisée depuis le shell en F1.1).
 *
 * Elle sert encore de vitrine du design system (hero orbital + cartes + galerie)
 * avec des données de démonstration. Elle sera remplacée par la vraie page
 * d'accueil branchée sur l'API en F2+.
 */
@Component({
  selector: 'app-home-page',
  imports: [OrbitHeroComponent, ListingCardComponent, GalleryComponent, VerificationBadgeComponent],
  templateUrl: './home-page.html',
  styleUrl: './home-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HomePageComponent {
  /** Données de démonstration des cartes (remplacées par l'API en F2+). */
  protected readonly demoCards = [
    { title: 'Villa 4 pièces à Saly', location: 'Saly, Mbour', price: '450 000 F', priceUnit: '/ mois', badge: 'Vérifié notaire' },
    { title: 'Écolodge au Saloum', location: 'Toubacouta', price: '35 000 F', priceUnit: '/ nuit', badge: 'Vérifié' },
    { title: '4×4 avec chauffeur', location: 'Dakar', price: '45 000 F', priceUnit: '/ jour', badge: null },
  ];

  /** Photos de démonstration (dégradés autonomes, sans dépendance réseau). */
  protected readonly demoGallery = [
    this.placeholder('#0348fb', '#03193f'),
    this.placeholder('#38a774', '#0e5a3c'),
    this.placeholder('#d3ae52', '#8a6a1f'),
    this.placeholder('#08265b', '#0348fb'),
  ];

  /** Génère une image de remplacement (dégradé) en data-URI SVG. */
  private placeholder(from: string, to: string): string {
    const svg =
      `<svg xmlns='http://www.w3.org/2000/svg' width='800' height='500'>` +
      `<defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'>` +
      `<stop offset='0' stop-color='${from}'/><stop offset='1' stop-color='${to}'/>` +
      `</linearGradient></defs><rect width='800' height='500' fill='url(#g)'/></svg>`;
    return `data:image/svg+xml,${encodeURIComponent(svg)}`;
  }
}
