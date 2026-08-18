import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { ContactService } from '../../../core/api/contact.service';
import { ScrollTopComponent } from '../scroll-top/scroll-top';

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
 *
 * Monte aussi le lien **« revenir en haut »** (`app-scroll-top`) — déplacé ici
 * depuis la racine (`app.html`), où c'était un bouton flottant global : il
 * n'apparaît donc plus que sur les pages qui ont un pied de page (le site
 * public), et la bulle assistant occupe seule le coin flottant partout.
 */
@Component({
  selector: 'app-footer',
  imports: [RouterLink, ScrollTopComponent],
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
      ],
    },
    {
      title: 'Aide',
      links: [
        { label: 'FAQ', link: '/faqs' },
        { label: 'Nous contacter', link: '/contact' },
        { label: 'Annulation & remboursement', link: '/pages/politique-annulation-remboursement' },
        { label: 'Conditions de mandat', link: '/pages/conditions-de-mandat' },
      ],
    },
  ];

  /**
   * Bandeau légal du bas de page (F8.15.e).
   *
   * Le CDC §4.2 impose six pages légales. Les entasser dans la colonne « Aide »
   * l'aurait portée à huit entrées, sans hiérarchie : on ne cherche pas les CGV
   * comme on cherche la FAQ. D'où la répartition par INTENTION — la politique
   * d'annulation et les conditions de mandat répondent à une question qu'on se
   * pose (« qu'est-ce qui s'applique à moi ? ») et restent dans « Aide », les
   * textes de cadre vont dans ce bandeau, à l'endroit où l'usage les attend.
   *
   * ⚠️ Chacun de ces liens suppose une page en base : elles sont posées par
   * `PublicPagesSeeder` côté backend, à rejouer après déploiement. Un slug
   * modifié ici sans l'être là-bas produit un 404 sur une page obligatoire.
   */
  protected readonly legalLinks: FooterLink[] = [
    { label: 'Mentions légales', link: '/pages/mentions-legales' },
    { label: "Conditions d'utilisation", link: '/pages/cgu' },
    { label: 'Conditions de vente', link: '/pages/cgv' },
    { label: 'Confidentialité', link: '/pages/politique-confidentialite' },
    { label: 'Cookies', link: '/pages/politique-cookies' },
  ];

  constructor() {
    this.contact.info().subscribe({
      next: (info) => this.social.set(info.social ?? {}),
      // Échec silencieux : le pied de page ne doit jamais casser pour ça.
      error: () => this.social.set({}),
    });
  }
}
