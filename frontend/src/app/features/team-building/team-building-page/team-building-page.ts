import { ChangeDetectionStrategy, Component } from '@angular/core';

import { LeadFormComponent } from '../../../shared/components/lead-form/lead-form';

/**
 * Page univers Team building (F2.5) — route `/team-building`.
 *
 * Page de conversion « Kaikun Team » : séminaires et activités de cohésion clés
 * en main pour les entreprises et institutions. Présente les formules et le
 * déroulé, puis un formulaire de demande de devis (`service_type = team_building`).
 * La composition fine des devis vit dans l'espace connecté / back-office.
 */
@Component({
  selector: 'app-team-building-page',
  imports: [LeadFormComponent],
  templateUrl: './team-building-page.html',
  styleUrl: './team-building-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TeamBuildingPageComponent {
  protected readonly highlights = [
    { title: 'Clés en main', text: 'Lieu, activités, restauration et logistique organisés de bout en bout.' },
    { title: 'Sur mesure', text: 'Séminaires, journées cohésion, incentives adaptés à vos objectifs.' },
    { title: 'Partout au Sénégal', text: 'Dakar, Saly, Sine-Saloum, Casamance… avec des prestataires vérifiés.' },
  ];

  /** Formules types présentées (contenus de présentation). */
  protected readonly formulas = [
    {
      title: 'Journée cohésion',
      text: 'Une journée d’activités et d’ateliers pour resserrer les liens de l’équipe.',
    },
    {
      title: 'Séminaire résidentiel',
      text: 'Plusieurs jours mêlant travail, hébergement et activités, tout inclus.',
    },
    {
      title: 'Incentive & événement',
      text: 'Récompensez vos équipes avec une expérience marquante, sur mesure.',
    },
  ];

  protected readonly steps = [
    {
      num: '1',
      title: 'Vous décrivez votre besoin',
      text: 'Effectif, dates, objectifs et budget : on cadre votre événement.',
    },
    {
      num: '2',
      title: 'Nous composons une formule',
      text: 'Un devis détaillé avec lieux, activités et logistique vérifiés.',
    },
    {
      num: '3',
      title: 'Vous vivez l’expérience',
      text: 'Coordination sur place le jour J, sans mauvaise surprise.',
    },
  ];
}
