import { ChangeDetectionStrategy, Component, effect, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { ValidationErrorBody } from '../../../core/api/api-response.model';
import {
  ConstructionObjective,
  ConstructionService,
  FinishLevel,
} from '../../../core/api/construction.service';
import { AuthService } from '../../../core/auth/auth.service';

/**
 * Dépôt d'une demande de chantier depuis la page publique `/construction`
 * (F8.15.b).
 *
 * POURQUOI CE COMPOSANT REMPLACE `app-lead-form` ICI
 * --------------------------------------------------
 * La page envoyait jusqu'ici un `POST /requests` **générique** dont le corps
 * était un message en TEXTE récapitulant le simulateur. La demande atterrissait
 * donc dans `requests`, pas dans `construction_requests` — la table que lit
 * l'écran back-office « Construction ». `POST /construction-requests` existait
 * depuis B5.5 et **n'avait aucun appelant** : personne ne pouvait déposer de
 * chantier, alors que tout l'aval était construit (jalons, rapports photo/vidéo,
 * devis ventilé par lot, acceptation par le client en F3.9, conversion en
 * réservation payable en F8.14). Un cycle entier partait d'une porte murée.
 *
 * ⚠️ **Les champs structurés ne sont pas ressaisis** : objectif, ville, surface
 * et finition viennent du **simulateur** juste au-dessus, que le visiteur a déjà
 * réglé. Les redemander serait lui faire dire deux fois la même chose. Le
 * formulaire ne porte donc qu'une chose : ce que le simulateur ne sait pas, le
 * contexte du projet.
 *
 * Le récapitulatif du simulateur reste envoyé en `description` : il porte ce
 * qu'aucune colonne ne stocke (niveaux, foncier, estimation affichée, projection
 * locative). L'agent doit voir ce que le client a vu.
 */
@Component({
  selector: 'app-construction-request-form',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './construction-request-form.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConstructionRequestFormComponent {
  private readonly construction = inject(ConstructionService);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  // --- Ce que le simulateur a déjà établi (aucune ressaisie) ----------------
  readonly objective = input.required<ConstructionObjective>();
  readonly finishLevel = input.required<FinishLevel>();
  readonly surfaceM2 = input.required<number>();
  /** Nom de la région choisie — `city` est une chaîne libre côté serveur. */
  readonly city = input.required<string>();
  /** Budget saisi par le visiteur ; 0 = non renseigné, envoyé en `null`. */
  readonly budget = input(0);

  /**
   * Récapitulatif vivant du simulateur, pré-rempli dans le champ de description.
   * Tant que le visiteur n'a pas édité le champ, on suit cette valeur ; dès
   * qu'il tape, on cesse de l'écraser (même règle que `app-lead-form`, sans
   * quoi bouger un curseur effacerait sa phrase).
   */
  readonly defaultDescription = input('');

  /** Vrai si un utilisateur est connecté (le dépôt exige une session). */
  readonly isAuthenticated = this.auth.isAuthenticated;

  readonly form = this.fb.nonNullable.group({
    description: ['', [Validators.required, Validators.maxLength(4000)]],
  });

  readonly submitting = signal(false);
  /** Référence `CST-…` du dossier créé (bandeau de succès). */
  readonly createdReference = signal<string | null>(null);
  readonly formError = signal<string | null>(null);

  constructor() {
    effect(() => {
      const preset = this.defaultDescription();
      if (!this.form.controls.description.dirty) {
        this.form.controls.description.setValue(preset);
      }
    });
  }

  /** Dépose le dossier de chantier (POST /construction-requests). */
  submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.formError.set(null);

    this.construction
      .create({
        objective: this.objective(),
        city: this.city(),
        surface_m2: this.surfaceM2(),
        finish_level: this.finishLevel(),
        budget_xof: this.budget() > 0 ? this.budget() : null,
        description: this.form.getRawValue().description,
      })
      .subscribe({
        next: (env) => {
          this.submitting.set(false);
          this.createdReference.set(env.data.construction_request.reference);
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
