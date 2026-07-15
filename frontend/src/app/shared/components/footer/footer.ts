import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

/** Un lien de pied de page (libellé + route interne). */
interface FooterLink {
  label: string;
  link: string;
}

/**
 * Pied de page global (F0.3, liens câblés en F2.8) : marque, colonnes de liens
 * routés et mention légale.
 */
@Component({
  selector: 'app-footer',
  imports: [RouterLink],
  templateUrl: './footer.html',
  styleUrl: './footer.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FooterComponent {
  protected readonly year = new Date().getFullYear();

  protected readonly columns: { title: string; links: FooterLink[] }[] = [
    {
      title: 'Univers',
      links: [
        { label: 'Immobilier', link: '/immobilier' },
        { label: 'Tourisme', link: '/tourisme' },
        { label: 'Transport', link: '/transport' },
        { label: 'Construction', link: '/construction' },
        { label: 'Kaikun Pro', link: '/pro' },
      ],
    },
    {
      title: 'Entreprise',
      links: [
        { label: 'À propos', link: '/pages/a-propos' },
        { label: 'Diaspora', link: '/diaspora' },
        { label: 'Team building', link: '/team-building' },
        { label: 'Nous contacter', link: '/contact' },
      ],
    },
    {
      title: 'Aide',
      links: [
        { label: 'FAQ', link: '/faqs' },
        { label: 'Confidentialité', link: '/pages/politique-confidentialite' },
        { label: "Conditions d'utilisation", link: '/pages/cgu' },
        { label: 'Mentions légales', link: '/pages/mentions-legales' },
      ],
    },
  ];
}
