import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { WhatsAppButtonComponent } from '../../../shared/components/whatsapp-button/whatsapp-button';

/**
 * Page Contact (F2.8) — route `/contact`.
 *
 * Page de coordonnées, sans formulaire : le backend n'expose pas d'endpoint de
 * contact générique (décision produit). On présente donc les canaux réels —
 * WhatsApp (numéro officiel résolu par le backend, jamais codé en dur) et
 * e-mail — puis on oriente vers les parcours métier existants (déposer un bien,
 * devenir prestataire, FAQ). Aucun bouton mort.
 */
@Component({
  selector: 'app-contact-page',
  imports: [RouterLink, WhatsAppButtonComponent],
  templateUrl: './contact-page.html',
  styleUrl: './contact-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ContactPageComponent {
  /** Adresse d'appui affichée (miroir du réglage support.email du backend). */
  protected readonly supportEmail = 'support@kaikun360.sn';

  /** Raccourcis vers les parcours métier, pour orienter le visiteur. */
  protected readonly shortcuts = [
    {
      title: 'Déposer un bien',
      text: 'Vous êtes propriétaire ? Publiez votre bien vérifié en quelques minutes.',
      link: '/deposer-un-bien',
      cta: 'Déposer un bien',
    },
    {
      title: 'Devenir prestataire',
      text: 'Rejoignez la marketplace Kaikun Pro et développez votre activité.',
      link: '/pro/inscription',
      cta: "S'inscrire",
    },
    {
      title: 'Questions fréquentes',
      text: 'La réponse à votre question s\'y trouve peut-être déjà.',
      link: '/faqs',
      cta: 'Consulter la FAQ',
    },
  ];
}
