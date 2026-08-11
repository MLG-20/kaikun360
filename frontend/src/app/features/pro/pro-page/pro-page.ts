import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { PageHeroComponent } from '../../../shared/components/page-hero/page-hero';

/**
 * Page univers Kaikun Pro (F2.5) — route `/pro`.
 *
 * Page de conversion à destination des **prestataires et entreprises** qui
 * veulent rejoindre le réseau Kaikun 360 (agences, artisans, chauffeurs,
 * guides, gestionnaires…). Contrairement aux autres pages de conversion, l'appel
 * à l'action n'est pas une demande de service mais une **inscription** : on
 * oriente donc vers `/auth/inscription` (profil prestataire/entreprise). La
 * gestion des missions et certifications vit ensuite dans l'espace connecté.
 */
@Component({
  selector: 'app-pro-page',
  imports: [PageHeroComponent, RouterLink],
  templateUrl: './pro-page.html',
  styleUrl: './pro-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProPageComponent {
  protected readonly highlights = [
    { title: 'Des demandes qualifiées', text: 'Recevez des demandes de clients vérifiés, prêts à réserver.' },
    { title: 'Un label de confiance', text: 'La vérification Kaikun 360 rassure et vous démarque.' },
    { title: 'Paiements sécurisés', text: 'Encaissements et reversements tracés en FCFA.' },
  ];

  protected readonly audiences = [
    {
      title: 'Agences & propriétaires',
      text: 'Publiez vos biens à la vente, à la location ou en nuitée auprès d’une audience nationale et diaspora.',
    },
    {
      title: 'Artisans & entreprises du bâtiment',
      text: 'Accédez à des projets de construction et de rénovation, avec un cadre de paiement par jalons.',
    },
    {
      title: 'Transport, tourisme & services',
      text: 'Chauffeurs, guides, prestataires du quotidien : mettez vos services devant plus de clients.',
    },
  ];

  protected readonly steps = [
    {
      num: '1',
      title: 'Vous créez votre compte pro',
      text: 'Inscription en quelques minutes avec le profil prestataire ou entreprise.',
    },
    {
      num: '2',
      title: 'Nous vérifions votre profil',
      text: 'Documents et certifications contrôlés : vous obtenez le label de confiance.',
    },
    {
      num: '3',
      title: 'Vous recevez des missions',
      text: 'Demandes qualifiées, gestion des devis et paiements sécurisés.',
    },
  ];
}
