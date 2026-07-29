import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { ContactService } from '../../../core/api/contact.service';

/** Un lien de pied de page (libellé + route interne). */
interface FooterLink {
  label: string;
  link: string;
}

/** Un réseau social affiché : clé technique, libellé accessible et URL. */
interface SocialLink {
  key: string;
  label: string;
  url: string;
}

/**
 * Pied de page global (F0.3, liens câblés en F2.8) : marque, colonnes de liens
 * routés, **réseaux sociaux** et mention légale.
 *
 * Les réseaux sociaux (F7.2.l) viennent des réglages back-office via
 * `GET /contact-info` — jamais codés en dur ici : l'équipe ouvre un compte
 * TikTok ou change d'URL LinkedIn sans redéploiement. Un réseau non renseigné
 * est absent de la réponse, donc absent du pied de page : pas de lien mort.
 * Si l'appel échoue, la rangée disparaît simplement — le pied de page reste
 * fonctionnel.
 */
@Component({
  selector: 'app-footer',
  imports: [RouterLink],
  templateUrl: './footer.html',
  styleUrl: './footer.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FooterComponent {
  private readonly contact = inject(ContactService);

  protected readonly year = new Date().getFullYear();

  /** Réseaux renvoyés par l'API, par clé. */
  private readonly social = signal<Record<string, string>>({});

  /**
   * Libellés et ordre d'affichage. L'ordre vient d'ici (et non de la réponse)
   * pour rester stable ; un réseau inconnu du frontend est ignoré plutôt que
   * rendu sans libellé accessible.
   */
  private readonly networkLabels: Record<string, string> = {
    facebook: 'Facebook',
    instagram: 'Instagram',
    tiktok: 'TikTok',
    linkedin: 'LinkedIn',
    youtube: 'YouTube',
  };

  protected readonly socialLinks = computed<SocialLink[]>(() => {
    const links = this.social();
    return Object.keys(this.networkLabels)
      .filter((key) => !!links[key])
      .map((key) => ({ key, label: this.networkLabels[key], url: links[key] }));
  });

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

  constructor() {
    this.contact.info().subscribe({
      next: (info) => this.social.set(info.social ?? {}),
      // Échec silencieux : le pied de page ne doit jamais casser pour ça.
      error: () => this.social.set({}),
    });
  }
}
