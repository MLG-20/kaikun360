import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormArray, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../../core/auth/auth.service';
import {
  ProviderCategory,
  ProviderService,
  RegisterProviderPayload,
} from '../../../core/api/provider.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { Provider } from '../../../models/provider.model';

/** Étape d'affichage de la page. */
type PageState = 'loading' | 'form' | 'registered' | 'failed';

/** Option du sélecteur de catégorie (valeur = enum backend). */
interface CategoryOption {
  value: ProviderCategory;
  label: string;
}

/**
 * Inscription prestataire dédiée (F2.7) — route `/pro/inscription`.
 *
 * Complète la page vitrine `/pro` (F2.5, purement éditoriale) par le vrai
 * formulaire d'adhésion à la marketplace (`POST /providers`). Le parcours :
 *   - non connecté → invitation à se connecter (retour ici) ;
 *   - connecté mais **non vérifié** → invitation à vérifier le compte
 *     (l'endpoint exige `verified.account`, 403 sinon) ;
 *   - déjà inscrit (`GET /providers/mine` = 200) → rappel du statut de
 *     validation (en attente / validé / refusé / suspendu) ;
 *   - éligible et sans profil (`mine` = 404) → formulaire d'inscription.
 *
 * Le profil créé est « en attente » : la validation reste une action
 * back-office (B10.2). Les certifications sont facultatives et répétables.
 */
@Component({
  selector: 'app-provider-registration-page',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './provider-registration-page.html',
  styleUrl: './provider-registration-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderRegistrationPageComponent {
  private readonly providers = inject(ProviderService);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  /** Catégories proposées (miroir de l'enum `ProviderCategory`). */
  readonly categories: CategoryOption[] = [
    { value: 'restauration', label: 'Restauration' },
    { value: 'animation', label: 'Animation' },
    { value: 'guide', label: 'Guide touristique' },
    { value: 'transport', label: 'Transport' },
    { value: 'evenementiel', label: 'Événementiel' },
    { value: 'artisanat', label: 'Artisanat' },
    { value: 'autre', label: 'Autre' },
  ];

  readonly state = signal<PageState>('loading');

  /** Profil prestataire existant (si déjà inscrit). */
  readonly provider = signal<Provider | null>(null);

  /** Vrai si un utilisateur est connecté. */
  readonly isAuthenticated = this.auth.isAuthenticated;

  /**
   * Vrai si le compte est vérifié (e-mail OU téléphone). L'inscription en dépend
   * (middleware `verified.account`), on gate donc le formulaire en amont.
   */
  readonly isVerified = computed(() => {
    const user = this.auth.user();
    return !!user && (user.email_verified_at !== null || user.phone_verified_at !== null);
  });

  readonly form = this.fb.nonNullable.group({
    business_name: ['', [Validators.required, Validators.maxLength(255)]],
    category: ['' as ProviderCategory | '', [Validators.required]],
    bio: [''],
    certifications: this.fb.array<
      ReturnType<ProviderRegistrationPageComponent['newCertification']>
    >([]),
  });

  readonly submitting = signal(false);
  /** Message d'erreur global du formulaire. */
  readonly formError = signal<string | null>(null);

  constructor() {
    // Si l'utilisateur est connecté, on vérifie s'il a déjà un profil prestataire.
    // Un anonyme n'appelle rien (la vue l'invite à se connecter).
    if (!this.isAuthenticated()) {
      this.state.set('form');
      return;
    }
    this.providers.mine().subscribe({
      next: (env) => {
        this.provider.set(env.data.provider);
        this.state.set('registered');
      },
      error: (err: { status?: number }) => {
        // 404 = pas encore de profil → on présente le formulaire.
        this.state.set(err?.status === 404 ? 'form' : 'failed');
      },
    });
  }

  /** Accès typé au tableau de certifications. */
  get certifications(): FormArray {
    return this.form.controls.certifications;
  }

  /** Fabrique une ligne de certification (nom requis, organisme facultatif). */
  private newCertification() {
    return this.fb.nonNullable.group({
      name: ['', [Validators.required, Validators.maxLength(255)]],
      issuer: [''],
    });
  }

  /** Ajoute une ligne de certification. */
  addCertification(): void {
    this.certifications.push(this.newCertification());
  }

  /** Retire la ligne de certification à l'index donné. */
  removeCertification(index: number): void {
    this.certifications.removeAt(index);
  }

  /** Dépose l'inscription (POST /providers). */
  submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();
    const payload: RegisterProviderPayload = {
      business_name: raw.business_name,
      category: raw.category as ProviderCategory,
      bio: raw.bio || null,
      certifications: raw.certifications,
    };

    this.submitting.set(true);
    this.formError.set(null);

    this.providers.register(payload).subscribe({
      next: (env) => {
        this.submitting.set(false);
        this.provider.set(env.data.provider);
        this.state.set('registered');
      },
      error: (err: { status?: number; error?: ValidationErrorBody }) => {
        this.submitting.set(false);
        const firstError = err?.error?.errors
          ? Object.values(err.error.errors)[0]?.[0]
          : null;
        this.formError.set(
          firstError ?? "Votre inscription n'a pas pu être enregistrée. Réessayez.",
        );
      },
    });
  }
}
