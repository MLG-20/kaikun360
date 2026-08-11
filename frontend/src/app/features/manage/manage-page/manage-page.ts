import { ChangeDetectionStrategy, Component } from '@angular/core';

import { LeadFormComponent } from '../../../shared/components/lead-form/lead-form';
import { PageHeroComponent } from '../../../shared/components/page-hero/page-hero';

/**
 * Page univers Gestion locative (F2.5) — route `/gestion-locative`.
 *
 * Page de conversion « Kaikun Manage » : confier la gestion de ses biens
 * (locataires, loyers, entretien, quittances) et tout suivre à distance. Elle
 * expose la promesse, les étapes et les bénéfices, puis un formulaire de mise
 * en relation (`service_type = manage`). La gestion opérationnelle (mandats,
 * quittances, décaissements) vit dans l'espace connecté / back-office.
 */
@Component({
  selector: 'app-manage-page',
  imports: [PageHeroComponent, LeadFormComponent],
  templateUrl: './manage-page.html',
  styleUrl: './manage-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ManagePageComponent {
  protected readonly highlights = [
    { title: 'Locataires vérifiés', text: 'Sélection et suivi des locataires, avec dossiers contrôlés.' },
    { title: 'Loyers & quittances', text: 'Encaissements, relances et quittances automatisés en FCFA.' },
    { title: 'Reporting à distance', text: 'Tableau de bord, décaissements et incidents visibles où que vous soyez.' },
  ];

  protected readonly steps = [
    {
      num: '1',
      title: 'Vous nous confiez un mandat',
      text: 'On établit un mandat de gestion clair pour chacun de vos biens.',
    },
    {
      num: '2',
      title: 'Nous gérons le quotidien',
      text: 'Locataires, loyers, entretien et incidents : tout est pris en charge et tracé.',
    },
    {
      num: '3',
      title: 'Vous recevez vos décaissements',
      text: 'Reversements réguliers et rapport détaillé, accessible à distance.',
    },
  ];

  protected readonly benefits = [
    'Un référent unique pour tous vos biens',
    'Quittances et rapports mensuels horodatés',
    'Gestion des incidents et de l’entretien tracée',
    'Reversements en FCFA (Wave, Orange Money, virement)',
  ];
}
