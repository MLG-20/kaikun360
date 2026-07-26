import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { LeadFormComponent } from '../../../shared/components/lead-form/lead-form';
import { WhatsAppButtonComponent } from '../../../shared/components/whatsapp-button/whatsapp-button';

/**
 * Page univers Diaspora (F2.5) — route `/diaspora`.
 *
 * Page de conversion « Kaikun Diaspora », cœur du positionnement anti-arnaque :
 * un référent unique et un suivi documenté (filmé, daté, numéroté) pour piloter
 * un projet au Sénégal depuis l'étranger. Elle décline le protocole de confiance
 * de l'accueil en bénéfices concrets, puis propose un formulaire de prise de
 * contact (`service_type = diaspora`).
 */
@Component({
  selector: 'app-diaspora-page',
  imports: [LeadFormComponent, WhatsAppButtonComponent, RouterLink],
  templateUrl: './diaspora-page.html',
  styleUrl: './diaspora-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DiasporaPageComponent {
  /** Les 3 garanties du protocole de confiance (reprises de l'accueil). */
  protected readonly guarantees = [
    {
      title: 'Vérification documentée',
      text: 'Titres de propriété, notaire et géomètre contrôlés avant tout engagement.',
    },
    {
      title: 'Tout est filmé et daté',
      text: 'Visites, chantiers et livraisons archivés : vous voyez l’avancement réel, pas des promesses.',
    },
    {
      title: 'Numéro de suivi unique',
      text: 'Chaque projet a sa référence : un reporting clair, accessible où que vous soyez.',
    },
  ];

  protected readonly steps = [
    {
      num: '1',
      title: 'Vous exposez votre projet',
      text: 'Achat, construction, gestion locative… on cadre votre besoin depuis l’étranger.',
    },
    {
      num: '2',
      title: 'Un référent unique prend le relais',
      text: 'Une seule personne coordonne tous les intervenants sur place, en votre nom.',
    },
    {
      num: '3',
      title: 'Vous suivez à distance',
      text: 'Rapports photo/vidéo horodatés et numéro de suivi, à chaque étape.',
    },
  ];

  protected readonly benefits = [
    'Un référent unique qui coordonne tout sur place',
    'Reporting photo/vidéo horodaté à chaque étape',
    'Décaissements sécurisés par jalons validés',
    'Paiements en FCFA (Wave, Orange Money, virement)',
  ];
}
