import {
  ChangeDetectionStrategy,
  Component,
  effect,
  inject,
  input,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../../core/auth/auth.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import {
  CreateRequestPayload,
  RequestService,
  ServiceType,
} from '../../../core/api/request.service';

/**
 * Formulaire de demande de contact réutilisable (F2.5).
 *
 * C'est l'appel à l'action commun aux pages de conversion (Construction,
 * Gestion locative, Diaspora, Team building…). Il dépose une demande générique
 * via `POST /requests` avec le bon `service_type`, exactement comme les
 * formulaires contextuels des fiches (F2.3), mais factorisé pour éviter de
 * réécrire la même logique sur chaque page.
 *
 * Ce dépôt exige une session : si le visiteur n'est pas connecté, le composant
 * l'invite à se connecter (avec retour sur la page courante via `redirectPath`)
 * au lieu d'afficher le formulaire. Un envoi réussi affiche la référence de la
 * demande créée.
 *
 * Les champs facultatifs (ville, budget) ne s'affichent que si la page le
 * demande (`showCity` / `showBudget`) — le simulateur de construction, par
 * exemple, injecte un budget estimé.
 */
@Component({
  selector: 'app-lead-form',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './lead-form.html',
  styleUrl: './lead-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LeadFormComponent {
  private readonly requests = inject(RequestService);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  /** Univers de la demande (miroir de l'enum `ServiceType` backend). */
  readonly serviceType = input.required<ServiceType>();
  /** Chemin de retour après connexion (ex. `/construction`). */
  readonly redirectPath = input.required<string>();
  /** Titre de la carte-formulaire. */
  readonly heading = input('Parlez-nous de votre projet');
  /** Phrase d'introduction au-dessus du formulaire. */
  readonly intro = input('');
  /** Libellé du bouton d'envoi. */
  readonly ctaLabel = input('Envoyer ma demande');
  /**
   * Message pré-rempli. C'est une entrée signal : une page peut la faire varier
   * en direct (le simulateur de construction met à jour le message avec
   * l'estimation). Tant que l'utilisateur n'a pas édité le champ, on suit cette
   * valeur ; dès qu'il tape, on cesse de l'écraser (voir l'`effect`).
   */
  readonly defaultMessage = input('');
  /** Affiche un champ « Ville » facultatif. */
  readonly showCity = input(false);
  /** Affiche un champ « Budget indicatif » facultatif. */
  readonly showBudget = input(false);

  /** Vrai si un utilisateur est connecté (autorise le dépôt de demande). */
  readonly isAuthenticated = this.auth.isAuthenticated;

  readonly form = this.fb.nonNullable.group({
    message: ['', [Validators.required, Validators.maxLength(2000)]],
    city: [''],
    budget_xof: [null as number | null],
  });

  readonly submitting = signal(false);
  /** Référence de la demande créée (bandeau de succès). */
  readonly createdReference = signal<string | null>(null);
  /** Message d'erreur global du formulaire. */
  readonly formError = signal<string | null>(null);

  constructor() {
    // Suit le message pré-rempli tant que l'utilisateur n'a pas modifié le champ.
    // Cela permet au simulateur de construction d'injecter une estimation vivante
    // sans écraser ce que le visiteur aurait déjà saisi.
    effect(() => {
      const preset = this.defaultMessage();
      if (!this.form.controls.message.dirty) {
        this.form.controls.message.setValue(preset);
      }
    });
  }

  /** Dépose la demande (POST /requests) avec le service_type de la page. */
  submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();
    const payload: CreateRequestPayload = {
      service_type: this.serviceType(),
      message: raw.message,
      city: this.showCity() ? raw.city || null : null,
      budget_xof: this.showBudget() ? raw.budget_xof : null,
    };

    this.submitting.set(true);
    this.formError.set(null);

    this.requests.create(payload).subscribe({
      next: (env) => {
        this.submitting.set(false);
        this.createdReference.set(env.data.request.reference);
      },
      error: (err: { status?: number; error?: ValidationErrorBody }) => {
        this.submitting.set(false);
        // Le 401 est déjà détourné vers la connexion par l'errorInterceptor ;
        // ici on ne gère que les échecs de validation/serveur.
        const firstError = err?.error?.errors
          ? Object.values(err.error.errors)[0]?.[0]
          : null;
        this.formError.set(
          firstError ?? "Votre demande n'a pas pu être envoyée. Réessayez.",
        );
      },
    });
  }
}
