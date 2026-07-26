import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import {
  AbstractControl,
  FormBuilder,
  ReactiveFormsModule,
  ValidationErrors,
  Validators,
} from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { ValidationErrorBody } from '../../../core/api/api-response.model';
import {
  CreateTeamBuildingRequestPayload,
  TeamBuildingService,
} from '../../../core/api/team-building.service';
import { TeamBuildingNeeds } from '../../../models/team-building.model';
import { ENTERPRISE_SPACE } from '../enterprise-space';
import { NEEDS_OPTIONS } from './team-building-status';

/**
 * Valideur de groupe : la date de fin doit être postérieure ou égale à la date
 * de début (miroir de la règle backend `after_or_equal:start_date`).
 */
function dateRangeValidator(group: AbstractControl): ValidationErrors | null {
  const start = group.get('start_date')?.value;
  const end = group.get('end_date')?.value;
  if (start && end && end < start) {
    return { dateRange: true };
  }
  return null;
}

/**
 * Écran **Nouvelle demande** de team building (F6) — route
 * `/espace-entreprise/demandes/nouvelle`.
 *
 * Formulaire réactif reprenant exactement les informations attendues au cahier
 * §9.4 : nombre de participants, ville, dates, budget indicatif, besoins
 * (hébergement / restauration / activités / transport / animation) et un
 * descriptif libre. Dépose la demande via `POST /team-building-requests`, puis
 * redirige vers le suivi de la demande créée.
 */
@Component({
  selector: 'app-enterprise-request-form-page',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './enterprise-request-form-page.html',
  styleUrl: './enterprise-request-form-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class EnterpriseRequestFormPageComponent {
  private readonly teamBuilding = inject(TeamBuildingService);
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);

  /** Préfixe d'URL de l'espace (liens). */
  protected readonly base = ENTERPRISE_SPACE.basePath;

  /** Cases « besoins » proposées (libellés partagés avec l'affichage). */
  protected readonly needsOptions = NEEDS_OPTIONS;

  protected readonly form = this.fb.nonNullable.group(
    {
      participants: [10, [Validators.required, Validators.min(1)]],
      city: ['', [Validators.required, Validators.maxLength(255)]],
      start_date: ['', [Validators.required]],
      end_date: ['', [Validators.required]],
      budget_xof: [null as number | null],
      description: [''],
      // Un contrôle booléen par besoin (regroupés dans `needs`).
      needs: this.fb.nonNullable.group({
        hebergement: [false],
        restauration: [false],
        activite: [false],
        mobilite: [false],
        animation: [false],
      }),
    },
    { validators: dateRangeValidator },
  );

  protected readonly submitting = signal(false);
  protected readonly formError = signal<string | null>(null);

  /** Dépose la demande (POST /team-building-requests). */
  protected submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();

    // Ne transmet que les besoins cochés (objet `needs` compact).
    const needs: TeamBuildingNeeds = {};
    for (const [key, checked] of Object.entries(raw.needs)) {
      if (checked) {
        needs[key as keyof TeamBuildingNeeds] = true;
      }
    }

    const payload: CreateTeamBuildingRequestPayload = {
      participants: raw.participants,
      city: raw.city,
      start_date: raw.start_date,
      end_date: raw.end_date,
      budget_xof: raw.budget_xof || null,
      description: raw.description || null,
      needs: Object.keys(needs).length ? needs : null,
    };

    this.submitting.set(true);
    this.formError.set(null);

    this.teamBuilding.create(payload).subscribe({
      next: (env) => {
        this.submitting.set(false);
        // Redirige vers le suivi de la demande fraîchement créée.
        this.router.navigate([this.base, 'demandes', env.data.request.id]);
      },
      error: (err: { status?: number; error?: ValidationErrorBody }) => {
        this.submitting.set(false);
        const firstError = err?.error?.errors ? Object.values(err.error.errors)[0]?.[0] : null;
        this.formError.set(
          firstError ?? "Votre demande n'a pas pu être envoyée. Réessayez.",
        );
      },
    });
  }
}
