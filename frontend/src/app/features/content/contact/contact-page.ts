import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { ContactService } from '../../../core/api/contact.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { WhatsAppButtonComponent } from '../../../shared/components/whatsapp-button/whatsapp-button';

/**
 * Page Contact (F2.8, formulaire ajouté en F2.8.1) — route `/contact`.
 *
 * Deux façons de nous joindre :
 *   - les canaux directs (WhatsApp — numéro réel résolu par le backend — et
 *     e-mail) ;
 *   - un **formulaire public** (`POST /contact`) dont les messages sont traités
 *     par l'équipe depuis le back-office. Le formulaire n'exige PAS de compte
 *     (un prospect doit pouvoir écrire) ; le backend limite le débit (anti-spam).
 *
 * On oriente enfin vers les parcours métier existants. Aucun bouton mort.
 */
@Component({
  selector: 'app-contact-page',
  imports: [ReactiveFormsModule, RouterLink, WhatsAppButtonComponent],
  templateUrl: './contact-page.html',
  styleUrl: './contact-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ContactPageComponent {
  private readonly fb = inject(FormBuilder);
  private readonly contact = inject(ContactService);

  /** Adresse d'appui affichée (miroir du réglage support.email du backend). */
  protected readonly supportEmail = 'support@kaikun360.sn';

  /** Formulaire de contact (miroir de `StoreContactMessageRequest`). */
  protected readonly form = this.fb.nonNullable.group({
    name: ['', [Validators.required, Validators.maxLength(150)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(255)]],
    subject: ['', [Validators.maxLength(200)]],
    message: ['', [Validators.required, Validators.maxLength(5000)]],
  });

  protected readonly submitting = signal(false);
  /** Vrai une fois le message envoyé avec succès (affiche le bandeau de merci). */
  protected readonly sent = signal(false);
  /** Message d'erreur global du formulaire. */
  protected readonly formError = signal<string | null>(null);
  /** Erreurs par champ renvoyées par le backend (422). */
  protected readonly fieldErrors = signal<Record<string, string>>({});

  /** Erreur backend éventuelle pour un champ donné. */
  protected error(field: string): string | null {
    return this.fieldErrors()[field] ?? null;
  }

  /** Envoie le message. */
  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.formError.set(null);
    this.fieldErrors.set({});

    this.contact.send(this.form.getRawValue()).subscribe({
      next: () => {
        this.submitting.set(false);
        this.sent.set(true);
        this.form.reset();
      },
      error: (error: HttpErrorResponse) => {
        this.submitting.set(false);
        this.applyError(error);
      },
    });
  }

  /** Permet d'écrire un nouveau message après un envoi réussi. */
  protected reset(): void {
    this.sent.set(false);
  }

  /** Répartit une erreur HTTP entre erreurs de champ et bandeau global. */
  private applyError(error: HttpErrorResponse): void {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      const flat: Record<string, string> = {};
      for (const [field, messages] of Object.entries(body?.errors ?? {})) {
        flat[field] = messages[0];
      }
      this.fieldErrors.set(flat);
      this.formError.set(body?.message ?? 'Veuillez corriger les champs indiqués.');
      return;
    }
    this.formError.set("Envoi impossible pour le moment. Réessayez dans un instant.");
  }

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
