import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';

import {
  DIASPORA_PRIORITIES,
  DIASPORA_PROJECT_TYPES,
  DiasporaPriorityValue,
  DiasporaProjectTypeValue,
  DiasporaService,
  NewDiasporaProjectPayload,
} from '../../../core/api/diaspora.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';

/**
 * Formulaire de lancement d'un **projet diaspora** (F3.8), monté sous
 * `/mon-espace/diaspora/nouveau`.
 *
 * Miroir de `StoreDiasporaProjectRequest` : type de projet et pays de résidence
 * requis ; budget, description et priorité facultatifs. À la création, le projet
 * démarre au statut « nouveau » ; un agent Kaikun est ensuite affecté par le
 * back-office et dépose les rapports de suivi.
 */
@Component({
  selector: 'app-diaspora-project-form-page',
  imports: [ReactiveFormsModule, BackLinkComponent],
  templateUrl: './diaspora-project-form-page.html',
  styleUrl: './diaspora-project-form-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DiasporaProjectFormPageComponent {
  private readonly diaspora = inject(DiasporaService);
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);

  protected readonly projectTypes = DIASPORA_PROJECT_TYPES;
  protected readonly priorities = DIASPORA_PRIORITIES;

  protected readonly submitting = signal(false);
  protected readonly formError = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    project_type: ['' as DiasporaProjectTypeValue | '', [Validators.required]],
    residence_country: ['', [Validators.required, Validators.maxLength(255)]],
    budget_xof: [null as number | null, [Validators.min(0)]],
    priority: ['normale' as DiasporaPriorityValue, [Validators.required]],
    description: [''],
  });

  /** Crée le projet (POST /diaspora-projects). */
  protected submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();
    const payload: NewDiasporaProjectPayload = {
      project_type: raw.project_type as DiasporaProjectTypeValue,
      residence_country: raw.residence_country,
      budget_xof: raw.budget_xof,
      priority: raw.priority,
      description: raw.description || null,
    };

    this.submitting.set(true);
    this.formError.set(null);

    this.diaspora.createProject(payload).subscribe({
      next: (env) => {
        this.submitting.set(false);
        // On ouvre directement le détail du projet créé.
        this.router.navigate(['/mon-espace/diaspora', env.data.project.id]);
      },
      error: (err: { error?: ValidationErrorBody }) => {
        this.submitting.set(false);
        const firstError = err?.error?.errors ? Object.values(err.error.errors)[0]?.[0] : null;
        this.formError.set(firstError ?? "Votre projet n'a pas pu être enregistré. Réessayez.");
      },
    });
  }
}
