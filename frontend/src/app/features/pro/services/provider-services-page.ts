import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';

import {
  ProviderCategory,
  ProviderCategoryOption,
  ProviderService,
} from '../../../core/api/provider.service';
import { Provider, ProviderCertification } from '../../../models/provider.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';

/** Valeur spéciale du select qui ouvre le champ « proposer une catégorie ». */
const PROPOSE_OPTION = '__propose__';

/**
 * Écran « Mes services » de l'espace prestataire (F5), monté sous
 * `/espace-prestataire/services`. Le prestataire y **édite le descriptif de son
 * service** (raison sociale, catégorie, présentation — `PUT /providers/mine`) et
 * **gère ses documents de certification** (ajout `POST` / suppression `DELETE`).
 *
 * Données chargées via `GET /providers/mine`. Trois cas particuliers : chargement,
 * échec réseau, et **404 « pas encore de profil »** (compte prestataire sans
 * dossier marketplace) → on renvoie vers l'inscription.
 *
 * ⚠️ Enregistrer une modification **ne repasse pas** le dossier en validation, et
 * une certification ajoutée reste « En vérification » jusqu'à revue back-office.
 */
@Component({
  selector: 'app-provider-services-page',
  imports: [ReactiveFormsModule, RouterLink, BackLinkComponent],
  templateUrl: './provider-services-page.html',
  styleUrl: './provider-services-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderServicesPageComponent {
  private readonly providers = inject(ProviderService);
  private readonly fb = inject(FormBuilder);

  /** Valeur spéciale qui ouvre le champ « proposer une catégorie » (template). */
  protected readonly proposeOptionValue = PROPOSE_OPTION;

  /** Catégories assignables, chargées depuis `GET /providers/categories`. */
  protected readonly categories = signal<ProviderCategoryOption[]>([]);

  /** Champ de saisie libre affiché quand « Proposer une catégorie… » est choisi. */
  protected readonly proposingCategory = signal(false);
  protected readonly newCategoryLabel = signal('');
  protected readonly proposingSaving = signal(false);
  protected readonly proposeError = signal<string | null>(null);

  // — État global —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly noProfile = signal(false);

  /** Statut de validation du profil (pour le rappel « ne relance pas la revue »). */
  protected readonly statusLabel = signal<string | null>(null);

  // — Profil (formulaire) —
  protected readonly savingProfile = signal(false);
  protected readonly profileSaved = signal(false);
  protected readonly profileError = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    business_name: ['', [Validators.required, Validators.maxLength(255)]],
    category: ['' as ProviderCategory | '', [Validators.required]],
    bio: [''],
  });

  // — Certifications —
  protected readonly certifications = signal<ProviderCertification[]>([]);
  protected readonly addingCert = signal(false);
  protected readonly certError = signal<string | null>(null);

  /** Formulaire d'ajout d'une certification (nom requis, organisme facultatif). */
  protected readonly certForm = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    issuer: [''],
  });

  /**
   * Justificatif joint (F8.0). Hors du `FormGroup` à dessein : un `<input
   * type="file">` ne se pilote pas par `formControlName` (sa valeur est un
   * chemin factice, pas le fichier), et le `File` doit voyager en `FormData`.
   */
  protected readonly certFile = signal<File | null>(null);

  /** Nombre de certifications vérifiées (pour un compteur d'aide). */
  protected readonly verifiedCount = computed(
    () => this.certifications().filter((c) => c.verified).length,
  );

  constructor() {
    this.load();
    this.loadCategories();
  }

  protected load(): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.noProfile.set(false);
    this.providers.mine().subscribe({
      next: (res) => {
        this.hydrate(res.data.provider);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        // 404 = compte prestataire sans profil marketplace (cas normal à gérer).
        if (err.status === 404) {
          this.noProfile.set(true);
        } else {
          this.loadError.set(true);
        }
        this.loading.set(false);
      },
    });
  }

  /** Charge les catégories assignables (les VALIDE + la mienne EN_ATTENTE). */
  private loadCategories(): void {
    this.providers.categories().subscribe({
      // `autre` reste en base pour les profils existants qui la portent déjà,
      // mais a disparu du sélecteur — remplacée par « Proposer une catégorie ».
      next: (res) => this.categories.set(res.data.filter((c) => c.key !== 'autre')),
      // Silencieux : le select reste vide, l'utilisateur peut réessayer via
      // « Enregistrer » qui rechargera implicitement au prochain `load()`.
      error: () => this.categories.set([]),
    });
  }

  /**
   * Réagit au choix du select : ouvre le champ de saisie libre si l'option
   * « Proposer une catégorie… » est choisie, sinon le referme.
   */
  protected onCategorySelected(): void {
    const value = this.form.controls.category.value;
    this.proposingCategory.set(value === PROPOSE_OPTION);
    if (value !== PROPOSE_OPTION) {
      this.onProfileEdited();
    }
  }

  /** Propose la nouvelle catégorie saisie, puis la sélectionne. */
  protected submitNewCategory(): void {
    const label = this.newCategoryLabel().trim();
    if (!label || this.proposingSaving()) {
      return;
    }
    this.proposingSaving.set(true);
    this.proposeError.set(null);

    this.providers.proposeCategory(label).subscribe({
      next: (res) => {
        const category = res.data.category;
        this.categories.update((list) =>
          list.some((c) => c.key === category.key) ? list : [...list, category],
        );
        this.form.controls.category.setValue(category.key);
        this.proposingCategory.set(false);
        this.newCategoryLabel.set('');
        this.proposingSaving.set(false);
        this.onProfileEdited();
      },
      error: () => {
        this.proposingSaving.set(false);
        this.proposeError.set("Cette catégorie n'a pas pu être proposée.");
      },
    });
  }

  /** Remplit le formulaire et la liste des certifications depuis le profil. */
  private hydrate(provider: Provider): void {
    this.form.reset({
      business_name: provider.business_name ?? '',
      category: (provider.category as ProviderCategory | null) ?? '',
      bio: provider.bio ?? '',
    });
    this.certifications.set(provider.certifications ?? []);
    this.statusLabel.set(provider.status_label);
  }

  /** Enregistre le profil (PUT /providers/mine). */
  protected saveProfile(): void {
    if (this.savingProfile() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();
    this.savingProfile.set(true);
    this.profileError.set(null);
    this.profileSaved.set(false);

    this.providers
      .updateProfile({
        business_name: raw.business_name,
        category: raw.category as ProviderCategory,
        bio: raw.bio.trim() || null,
      })
      .subscribe({
        next: (res) => {
          this.savingProfile.set(false);
          this.profileSaved.set(true);
          this.statusLabel.set(res.data.provider.status_label);
        },
        error: (err: HttpErrorResponse) => {
          this.savingProfile.set(false);
          const first = err?.error?.errors
            ? (Object.values(err.error.errors)[0] as string[])[0]
            : null;
          this.profileError.set(first ?? "Vos modifications n'ont pas pu être enregistrées.");
        },
      });
  }

  /** Marque le profil comme modifié (masque le message « enregistré »). */
  protected onProfileEdited(): void {
    if (this.profileSaved()) {
      this.profileSaved.set(false);
    }
  }

  /** Mémorise le justificatif choisi (envoyé à la validation du formulaire). */
  protected onCertFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.certFile.set(input.files?.[0] ?? null);
    this.certError.set(null);
  }

  /** Ajoute une certification (POST /providers/certifications). */
  protected addCert(): void {
    if (this.addingCert() || this.certForm.invalid) {
      this.certForm.markAllAsTouched();
      return;
    }
    const raw = this.certForm.getRawValue();
    this.addingCert.set(true);
    this.certError.set(null);

    this.providers
      .addCertification({
        name: raw.name ?? '',
        issuer: raw.issuer?.trim() || null,
        file: this.certFile(),
      })
      .subscribe({
        next: (res) => {
          this.certifications.update((list) => [...list, res.data.certification]);
          this.certForm.reset({ name: '', issuer: '' });
          this.certFile.set(null);
          this.resetCertFileInput();
          this.addingCert.set(false);
        },
        error: (err: HttpErrorResponse) => {
          this.addingCert.set(false);
          // Un 422 porte le vrai motif (format, taille) : le montrer plutôt
          // qu'un message générique qui laisserait le prestataire réessayer
          // le même fichier trop lourd en boucle.
          const first = err?.error?.errors
            ? (Object.values(err.error.errors)[0] as string[])[0]
            : null;
          this.certError.set(first ?? "La certification n'a pas pu être ajoutée.");
        },
      });
  }

  /**
   * Vide le champ fichier natif après un ajout réussi.
   *
   * `certForm.reset()` ne le touche pas (il n'y est pas), et sans ce nettoyage
   * le nom du fichier précédent resterait affiché par le navigateur, laissant
   * croire qu'il sera joint à la certification suivante.
   */
  private resetCertFileInput(): void {
    if (typeof document === 'undefined') {
      return;
    }
    const input = document.getElementById('cert-file') as HTMLInputElement | null;
    if (input) {
      input.value = '';
    }
  }

  /** Supprime une certification (avec confirmation). */
  protected removeCert(cert: ProviderCertification): void {
    if (typeof window !== 'undefined' && !window.confirm(`Supprimer « ${cert.name} » ?`)) {
      return;
    }
    this.providers.removeCertification(cert.id).subscribe({
      next: () => {
        this.certifications.update((list) => list.filter((c) => c.id !== cert.id));
      },
      error: () => {
        this.certError.set("La certification n'a pas pu être supprimée.");
      },
    });
  }
}
