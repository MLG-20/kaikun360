import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';

import { ValidationErrorBody } from '../../../../core/api/api-response.model';
import { AuthService } from '../../../../core/auth/auth.service';
import { CodeDelivery, VerificationChannel } from '../../../../core/auth/auth.types';

/**
 * Page de vérification de compte (F1.3). Protégée par `authGuard` : l'utilisateur
 * dispose déjà d'un jeton (reçu à l'inscription/connexion).
 *
 * Flux : choisir le canal (e-mail, ou téléphone s'il est renseigné) → envoyer un
 * code → le saisir → vérifier. Au succès, le compte passe ACTIF et l'utilisateur
 * en session est mis à jour ; on redirige vers l'accueil.
 */
@Component({
  selector: 'app-verification-page',
  imports: [ReactiveFormsModule],
  templateUrl: './verification-page.html',
  styleUrl: './verification-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VerificationPageComponent {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Utilisateur en session (pour afficher e-mail / téléphone). */
  protected readonly user = this.auth.user;
  /** Le compte a-t-il un téléphone (sinon le canal SMS est indisponible) ? */
  protected readonly hasPhone = computed(() => !!this.user()?.phone);

  /** Canal choisi. */
  protected readonly channel = signal<VerificationChannel>('email');
  /** Un code a-t-il été envoyé (bascule l'affichage vers la saisie) ? */
  protected readonly codeSent = signal(false);
  /**
   * Média par lequel le dernier code est réellement parti (renseigné par le
   * backend à l'envoi). Sert à libeller correctement le bouton « SMS », qui
   * mentirait tant que le SMS n'est pas branché.
   */
  protected readonly delivery = signal<CodeDelivery | null>(null);
  protected readonly sending = signal(false);
  protected readonly verifying = signal(false);
  protected readonly info = signal<string | null>(null);
  protected readonly formError = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    code: ['', [Validators.required]],
  });

  protected chooseChannel(channel: VerificationChannel): void {
    this.channel.set(channel);
    this.codeSent.set(false);
    this.delivery.set(null);
    this.info.set(null);
    this.formError.set(null);
  }

  /** Demande l'envoi d'un code sur le canal courant. */
  protected sendCode(): void {
    if (this.sending()) {
      return;
    }
    this.sending.set(true);
    this.formError.set(null);
    this.info.set(null);

    this.auth.sendVerificationCode(this.channel()).subscribe({
      // `delivery` est le média RÉELLEMENT employé, pas le canal demandé : un
      // code destiné au téléphone part par e-mail tant que le SMS n'est pas
      // branché. Annoncer « par SMS » à tort ferait attendre l'utilisateur
      // devant un téléphone muet.
      next: (delivery) => {
        this.sending.set(false);
        this.codeSent.set(true);
        this.delivery.set(delivery);
        this.info.set(
          delivery === 'mail'
            ? this.channel() === 'phone'
              ? 'Un code vous a été envoyé par e-mail (et non par SMS).'
              : 'Un code vous a été envoyé par e-mail.'
            : 'Un code vous a été envoyé par SMS.',
        );
      },
      error: (error: HttpErrorResponse) => {
        this.sending.set(false);
        this.formError.set(this.messageFor(error));
      },
    });
  }

  /** Vérifie le code saisi. */
  protected submit(): void {
    if (this.verifying()) {
      return;
    }
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.verifying.set(true);
    this.formError.set(null);

    this.auth.verify(this.channel(), this.form.getRawValue().code).subscribe({
      next: () => void this.router.navigateByUrl('/'),
      error: (error: HttpErrorResponse) => {
        this.verifying.set(false);
        this.formError.set(this.messageFor(error));
      },
    });
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | { message?: string } | null;
      return (body as { message?: string })?.message ?? 'Code invalide ou expiré.';
    }
    return 'Action impossible pour le moment. Réessayez dans un instant.';
  }
}
