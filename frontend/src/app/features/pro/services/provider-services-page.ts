import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { ProviderCategory, ProviderService } from '../../../core/api/provider.service';
import { Provider, ProviderCertification } from '../../../models/provider.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';

/** Option du sélecteur de catégorie (valeur = enum backend). */
interface CategoryOption {
  value: ProviderCategory;
  label: string;
}

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

  /** Catégories proposées (miroir de l'enum `ProviderCategory`). */
  protected readonly categories: CategoryOption[] = [
    { value: 'restauration', label: 'Restauration' },
    { value: 'animation', label: 'Animation' },
    { value: 'guide', label: 'Guide touristique' },
    { value: 'transport', label: 'Transport' },
    { value: 'evenementiel', label: 'Événementiel' },
    { value: 'artisanat', label: 'Artisanat' },
    { value: 'autre', label: 'Autre' },
  ];

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
