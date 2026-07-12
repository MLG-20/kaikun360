import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Pied de page global (F0.3) : marque, colonnes de liens (placeholders) et
 * mention légale. Les liens seront routés au fil des fonctionnalités.
 */
@Component({
  selector: 'app-footer',
  templateUrl: './footer.html',
  styleUrl: './footer.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FooterComponent {
  protected readonly year = new Date().getFullYear();

  protected readonly columns = [
    { title: 'Univers', links: ['Immobilier', 'Tourisme', 'Transport', 'Construction', 'Services'] },
    { title: 'Entreprise', links: ['À propos', 'Diaspora', 'Team building', 'Nous contacter'] },
    { title: 'Aide', links: ['FAQ', 'Confidentialité', "Conditions d'utilisation"] },
  ];
}
