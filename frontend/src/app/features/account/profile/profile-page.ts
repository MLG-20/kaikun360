import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { AccountService } from '../../../core/api/account.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { AuthService } from '../../../core/auth/auth.service';
import { DOCUMENT_TYPES, UserDocument } from '../../../models/document.model';
import { User } from '../../../models/user.model';

/**
 * Écran « Mon profil » de l'espace client (F3.2), monté sous `/mon-espace/profil`.
 *
 * Trois blocs, branchés sur les endpoints existants du compte connecté :
 *
 *   1. **Identité** — nom + ville éditables (`PATCH /users/me`, erreurs 422 par
 *      champ) ; e-mail, téléphone, statut, rôle et date d'inscription en lecture
 *      seule (l'e-mail et le téléphone ne se changent pas ici : re-vérification
 *      dédiée à venir).
 *   2. **Pièces justificatives (KYC)** — liste (`GET /users/me/documents`) avec
 *      téléchargement par URL signée, et dépôt (`POST …`, PDF/JPG/PNG ≤ 5 Mo).
 *   3. **Zone de danger** — suppression du compte (`DELETE /users/me`, RGPD :
 *      anonymisation), derrière une confirmation, suivie de la déconnexion.
 *
 * Le profil est **rechargé frais** au montage (`GET /users/me`) plutôt que de se
 * fier à l'utilisateur en mémoire, pour disposer du profil complet et à jour.
 */
@Component({
  selector: 'app-profile-page',
  imports: [ReactiveFormsModule, RouterLink, DatePipe],
  templateUrl: './profile-page.html',
  styleUrl: './profile-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProfilePageComponent {
  private readonly fb = inject(FormBuilder);
  private readonly account = inject(AccountService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Types de pièces déposables (menu déroulant). */
  protected readonly documentTypes = DOCUMENT_TYPES;

  // — État du chargement initial du profil —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  /** Utilisateur frais renvoyé par le backend (avec profil chargé). */
  protected readonly user = signal<User | null>(null);

  /** Rôle principal, mis en forme (première casquette). */
  protected readonly roleLabel = computed(() => this.user()?.roles?.[0] ?? null);

  // — Bloc Identité (édition) —
  protected readonly form = this.fb.nonNullable.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    city: ['', [Validators.maxLength(120)]],
  });
  protected readonly saving = signal(false);
  protected readonly saved = signal(false);
  protected readonly formError = signal<string | null>(null);
  protected readonly fieldErrors = signal<Record<string, string>>({});

  // — Bloc Documents —
  protected readonly documents = signal<UserDocument[]>([]);
  protected readonly docsLoading = signal(true);
  protected readonly docsError = signal(false);

  // — Bloc Dépôt d'une pièce —
  protected readonly uploadType = signal(DOCUMENT_TYPES[0].value);
  protected readonly selectedFile = signal<File | null>(null);
  protected readonly uploading = signal(false);
  protected readonly uploadError = signal<string | null>(null);
  protected readonly uploadDone = signal(false);

  // — Bloc Zone de danger —
  protected readonly confirmingDelete = signal(false);
  protected readonly deleting = signal(false);
  protected readonly deleteError = signal<string | null>(null);

  constructor() {
    this.load();
  }

  /** Charge le profil frais puis la liste des documents. */
  private load(): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.account.me().subscribe({
      next: (user) => {
        this.user.set(user);
        this.form.patchValue({ name: user.name ?? '', city: user.city ?? '' });
        this.loading.set(false);
        // Garde la session (en-tête) alignée sur le profil frais.
        this.auth.setCurrentUser(user);
      },
      error: () => {
        this.loading.set(false);
        this.loadError.set(true);
      },
    });
    this.loadDocuments();
  }

  private loadDocuments(): void {
    this.docsLoading.set(true);
    this.docsError.set(false);
    this.account.documents().subscribe({
      next: (docs) => {
        this.documents.set(docs);
        this.docsLoading.set(false);
      },
      error: () => {
        this.docsLoading.set(false);
        this.docsError.set(true);
      },
    });
  }

  /** Le champ est-il invalide et touché (validation locale) ? */
  protected invalid(field: 'name' | 'city'): boolean {
    const control = this.form.controls[field];
    return control.invalid && control.touched;
  }

  /** Message d'erreur serveur (422) pour un champ donné, ou null. */
  protected serverError(field: string): string | null {
    return this.fieldErrors()[field] ?? null;
  }

  /** Enregistre les modifications d'identité (nom, ville). */
  protected saveProfile(): void {
    if (this.saving()) {
      return;
    }
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.saved.set(false);
    this.formError.set(null);
    this.fieldErrors.set({});

    const { name, city } = this.form.getRawValue();
    this.account.updateProfile({ name, city: city.trim() === '' ? null : city }).subscribe({
      next: (user) => {
        this.saving.set(false);
        this.saved.set(true);
        this.user.set(user);
        this.form.markAsPristine();
        // Met à jour le nom affiché dans l'en-tête sans recharger la page.
        this.auth.setCurrentUser(user);
      },
      error: (error: HttpErrorResponse) => {
        this.saving.set(false);
        this.applyProfileError(error);
      },
    });
  }

  /** Répartit une erreur HTTP entre erreurs de champ et bandeau global. */
  private applyProfileError(error: HttpErrorResponse): void {
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
    this.formError.set('Une erreur est survenue. Merci de réessayer.');
  }

  /** Le fichier choisi dans l'input file (première pièce). */
  protected onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.selectedFile.set(input.files?.[0] ?? null);
    this.uploadError.set(null);
    this.uploadDone.set(false);
  }

  /** Dépose la pièce sélectionnée (type + fichier). */
  protected upload(): void {
    if (this.uploading()) {
      return;
    }
    const file = this.selectedFile();
    if (!file) {
      this.uploadError.set('Veuillez choisir un fichier (PDF, JPG ou PNG, 5 Mo maximum).');
      return;
    }

    this.uploading.set(true);
    this.uploadError.set(null);
    this.uploadDone.set(false);

    this.account.uploadDocument(this.uploadType(), file).subscribe({
      next: (doc) => {
        this.uploading.set(false);
        this.uploadDone.set(true);
        this.selectedFile.set(null);
        // Ajoute la nouvelle pièce en tête de liste (comme le tri backend).
        this.documents.update((list) => [doc, ...list]);
      },
      error: (error: HttpErrorResponse) => {
        this.uploading.set(false);
        this.applyUploadError(error);
      },
    });
  }

  private applyUploadError(error: HttpErrorResponse): void {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      // Un seul champ de fichier : on remonte le premier message pertinent.
      const first =
        body?.errors?.['file']?.[0] ?? body?.errors?.['type']?.[0] ?? body?.message ?? null;
      this.uploadError.set(first ?? 'Le dépôt a échoué. Vérifiez le fichier.');
      return;
    }
    this.uploadError.set('Le dépôt a échoué. Merci de réessayer.');
  }

  /** Ouvre / referme la confirmation de suppression du compte. */
  protected toggleDeleteConfirm(open: boolean): void {
    this.confirmingDelete.set(open);
    this.deleteError.set(null);
  }

  /** Supprime (anonymise) le compte puis déconnecte et renvoie à l'accueil. */
  protected deleteAccount(): void {
    if (this.deleting()) {
      return;
    }
    this.deleting.set(true);
    this.deleteError.set(null);

    this.account.deleteAccount().subscribe({
      next: () => {
        // Le jeton est déjà révoqué côté serveur : on vide la session locale.
        this.auth.clearSession();
        void this.router.navigate(['/']);
      },
      error: () => {
        this.deleting.set(false);
        this.deleteError.set('La suppression a échoué. Merci de réessayer.');
      },
    });
  }

  /** Libellé lisible d'un statut de pièce (fallback : valeur brute). */
  protected statusLabel(status: string | null): string {
    switch (status) {
      case 'pending':
        return 'En attente de vérification';
      case 'approved':
      case 'valide':
      case 'verifie':
        return 'Vérifiée';
      case 'rejected':
      case 'refuse':
        return 'Refusée';
      default:
        return status ?? '—';
    }
  }

  /** Classe de teinte associée au statut (pastille). */
  protected statusTone(status: string | null): string {
    switch (status) {
      case 'approved':
      case 'valide':
      case 'verifie':
        return 'is-ok';
      case 'rejected':
      case 'refuse':
        return 'is-ko';
      default:
        return 'is-wait';
    }
  }

  /** Taille de fichier lisible (Ko / Mo). */
  protected fileSize(bytes: number | null): string {
    if (!bytes || bytes <= 0) {
      return '';
    }
    if (bytes < 1024 * 1024) {
      return `${Math.round(bytes / 1024)} Ko`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
  }
}
