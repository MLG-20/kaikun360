import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { AccountService } from '../../../core/api/account.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { Commune, Department, GeoService, Region } from '../../../core/api/geo.service';
import { AuthService } from '../../../core/auth/auth.service';
import { VerificationChannel } from '../../../core/auth/auth.types';
import { DOCUMENT_TYPES, UserDocument } from '../../../models/document.model';
import { User } from '../../../models/user.model';
import { PasswordRevealDirective } from '../../../shared/directives/password-reveal.directive';

/**
 * Écran « Mon profil » de l'espace client (F3.2, étendu F3.2b), monté sous
 * `/mon-espace/profil`. Branché sur les endpoints du compte connecté :
 *
 *   1. **Identité & coordonnées** — nom, e-mail et téléphone, adresse et
 *      localisation en cascade Région → Département → Commune (`PATCH /users/me`).
 *      **Changer l'e-mail ou le téléphone déclenche une re-vérification** : le
 *      backend renvoie un code au nouveau contact, saisi ici même.
 *   2. **Pièces justificatives (KYC)** — liste (`GET /users/me/documents`) +
 *      dépôt (`POST …`, PDF/JPG/PNG ≤ 5 Mo).
 *   3. **Sécurité** — changement de mot de passe (`PATCH /users/me/password`).
 *   4. **Zone de danger** — suppression du compte (`DELETE /users/me`, RGPD).
 */
@Component({
  selector: 'app-profile-page',
  imports: [ReactiveFormsModule, RouterLink, DatePipe, PasswordRevealDirective],
  templateUrl: './profile-page.html',
  styleUrl: './profile-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProfilePageComponent {
  private readonly fb = inject(FormBuilder);
  private readonly account = inject(AccountService);
  private readonly geo = inject(GeoService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly documentTypes = DOCUMENT_TYPES;

  // — Chargement initial —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly user = signal<User | null>(null);
  protected readonly roleLabel = computed(() => this.user()?.roles?.[0] ?? null);

  // — Listes géo (cascade) —
  protected readonly regions = signal<Region[]>([]);
  protected readonly departments = signal<Department[]>([]);
  protected readonly communes = signal<Commune[]>([]);

  // — Bloc Identité & coordonnées —
  protected readonly form = this.fb.group({
    name: this.fb.nonNullable.control('', [Validators.required, Validators.maxLength(255)]),
    email: this.fb.nonNullable.control('', [Validators.required, Validators.email]),
    phone: this.fb.nonNullable.control(''),
    address: this.fb.nonNullable.control(''),
    region_id: this.fb.control<number | null>(null),
    department_id: this.fb.control<number | null>(null),
    commune_id: this.fb.control<number | null>(null),
  });
  protected readonly saving = signal(false);
  protected readonly saved = signal(false);
  protected readonly formError = signal<string | null>(null);
  protected readonly fieldErrors = signal<Record<string, string>>({});

  // — Re-vérification (e-mail / téléphone changés) —
  protected readonly pendingEmail = signal(false);
  protected readonly pendingPhone = signal(false);
  protected readonly emailCode = signal('');
  protected readonly phoneCode = signal('');
  protected readonly verifyingEmail = signal(false);
  protected readonly verifyingPhone = signal(false);
  protected readonly verifyEmailError = signal<string | null>(null);
  protected readonly verifyPhoneError = signal<string | null>(null);

  // — Documents —
  protected readonly documents = signal<UserDocument[]>([]);
  protected readonly docsLoading = signal(true);
  protected readonly docsError = signal(false);

  // — Dépôt d'une pièce —
  protected readonly uploadType = signal(DOCUMENT_TYPES[0].value);
  protected readonly selectedFile = signal<File | null>(null);
  protected readonly uploading = signal(false);
  protected readonly uploadError = signal<string | null>(null);
  protected readonly uploadDone = signal(false);

  // — Sécurité (mot de passe) —
  protected readonly passwordForm = this.fb.nonNullable.group({
    current_password: ['', [Validators.required]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', [Validators.required]],
  });
  protected readonly changingPassword = signal(false);
  protected readonly passwordDone = signal(false);
  protected readonly passwordError = signal<string | null>(null);
  protected readonly passwordFieldErrors = signal<Record<string, string>>({});

  // — Zone de danger —
  protected readonly confirmingDelete = signal(false);
  protected readonly deleting = signal(false);
  protected readonly deleteError = signal<string | null>(null);

  constructor() {
    this.load();
    this.wireLocationCascade();
  }

  // ─────────────────────────── Chargement ───────────────────────────

  private load(): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.account.me().subscribe({
      next: (user) => {
        this.user.set(user);
        this.form.patchValue(
          {
            name: user.name ?? '',
            email: user.email ?? '',
            phone: user.phone ?? '',
            address: user.address ?? '',
          },
          { emitEvent: false },
        );
        this.prefillLocation(user);
        this.loading.set(false);
        this.auth.setCurrentUser(user);
      },
      error: () => {
        this.loading.set(false);
        this.loadError.set(true);
      },
    });
    this.loadDocuments();

    // Régions (pour le premier menu déroulant).
    this.geo.regions().subscribe({ next: (res) => this.regions.set(res.data), error: () => {} });
  }

  /** Prépare la cascade avec les valeurs existantes SANS déclencher les resets. */
  private prefillLocation(u: User): void {
    if (!u.region_id) {
      return;
    }
    this.form.controls.region_id.setValue(u.region_id, { emitEvent: false });
    this.geo.departments(u.region_id).subscribe((res) => {
      this.departments.set(res.data);
      if (u.department_id) {
        this.form.controls.department_id.setValue(u.department_id, { emitEvent: false });
        this.geo.communes(u.department_id).subscribe((cres) => {
          this.communes.set(cres.data);
          if (u.commune_id) {
            this.form.controls.commune_id.setValue(u.commune_id, { emitEvent: false });
          }
        });
      }
    });
  }

  /** Cascade : un changement de région recharge les départements, etc. */
  private wireLocationCascade(): void {
    this.form.controls.region_id.valueChanges.pipe(takeUntilDestroyed()).subscribe((regionId) => {
      this.form.controls.department_id.setValue(null, { emitEvent: false });
      this.form.controls.commune_id.setValue(null, { emitEvent: false });
      this.departments.set([]);
      this.communes.set([]);
      if (regionId) {
        this.geo.departments(regionId).subscribe((res) => this.departments.set(res.data));
      }
    });

    this.form.controls.department_id.valueChanges.pipe(takeUntilDestroyed()).subscribe((departmentId) => {
      this.form.controls.commune_id.setValue(null, { emitEvent: false });
      this.communes.set([]);
      if (departmentId) {
        this.geo.communes(departmentId).subscribe((res) => this.communes.set(res.data));
      }
    });
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

  // ─────────────────────────── Identité ───────────────────────────

  protected invalid(field: 'name' | 'email'): boolean {
    const control = this.form.controls[field];
    return control.invalid && control.touched;
  }

  protected serverError(field: string): string | null {
    return this.fieldErrors()[field] ?? null;
  }

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

    const raw = this.form.getRawValue();
    const clean = (v: string) => (v.trim() === '' ? null : v.trim());

    this.account
      .updateProfile({
        name: raw.name,
        email: raw.email,
        phone: clean(raw.phone),
        address: clean(raw.address),
        region_id: raw.region_id,
        department_id: raw.department_id,
        commune_id: raw.commune_id,
      })
      .subscribe({
        next: (result) => {
          this.saving.set(false);
          this.saved.set(true);
          this.user.set(result.user);
          this.form.markAsPristine();
          this.auth.setCurrentUser(result.user);
          // Ouvre la saisie de code si l'e-mail / le téléphone a changé.
          if (result.emailVerificationRequired) {
            this.pendingEmail.set(true);
            this.emailCode.set('');
            this.verifyEmailError.set(null);
          }
          if (result.phoneVerificationRequired) {
            this.pendingPhone.set(true);
            this.phoneCode.set('');
            this.verifyPhoneError.set(null);
          }
        },
        error: (error: HttpErrorResponse) => {
          this.saving.set(false);
          this.applyProfileError(error);
        },
      });
  }

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

  // ─────────────────── Re-vérification e-mail / téléphone ───────────────────

  /** Confirme le code reçu sur le canal changé (email/phone). */
  protected confirmVerification(channel: VerificationChannel): void {
    const isEmail = channel === 'email';
    const code = (isEmail ? this.emailCode() : this.phoneCode()).trim();
    if (!code) {
      (isEmail ? this.verifyEmailError : this.verifyPhoneError).set('Saisissez le code reçu.');
      return;
    }
    (isEmail ? this.verifyingEmail : this.verifyingPhone).set(true);
    (isEmail ? this.verifyEmailError : this.verifyPhoneError).set(null);

    this.auth.verify(channel, code).subscribe({
      next: (user) => {
        (isEmail ? this.verifyingEmail : this.verifyingPhone).set(false);
        (isEmail ? this.pendingEmail : this.pendingPhone).set(false);
        this.user.set(user);
      },
      error: (error: HttpErrorResponse) => {
        (isEmail ? this.verifyingEmail : this.verifyingPhone).set(false);
        const msg = error.status === 422 ? 'Code invalide ou expiré.' : 'La vérification a échoué.';
        (isEmail ? this.verifyEmailError : this.verifyPhoneError).set(msg);
      },
    });
  }

  /** Renvoie un nouveau code sur le canal concerné. */
  protected resendCode(channel: VerificationChannel): void {
    const errSig = channel === 'email' ? this.verifyEmailError : this.verifyPhoneError;
    this.auth.sendVerificationCode(channel).subscribe({
      next: () => errSig.set('Un nouveau code a été envoyé.'),
      error: () => errSig.set("L'envoi du code a échoué."),
    });
  }

  // ─────────────────────────── Documents ───────────────────────────

  protected onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.selectedFile.set(input.files?.[0] ?? null);
    this.uploadError.set(null);
    this.uploadDone.set(false);
  }

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
      const first = body?.errors?.['file']?.[0] ?? body?.errors?.['type']?.[0] ?? body?.message ?? null;
      this.uploadError.set(first ?? 'Le dépôt a échoué. Vérifiez le fichier.');
      return;
    }
    this.uploadError.set('Le dépôt a échoué. Merci de réessayer.');
  }

  // ─────────────────────────── Sécurité (mot de passe) ───────────────────────────

  protected passwordInvalid(field: 'current_password' | 'password' | 'password_confirmation'): boolean {
    const control = this.passwordForm.controls[field];
    return control.invalid && control.touched;
  }

  protected passwordServerError(field: string): string | null {
    return this.passwordFieldErrors()[field] ?? null;
  }

  protected changePassword(): void {
    if (this.changingPassword()) {
      return;
    }
    const { password, password_confirmation } = this.passwordForm.getRawValue();
    if (this.passwordForm.invalid) {
      this.passwordForm.markAllAsTouched();
      return;
    }
    if (password !== password_confirmation) {
      this.passwordFieldErrors.set({ password_confirmation: 'La confirmation ne correspond pas.' });
      return;
    }

    this.changingPassword.set(true);
    this.passwordDone.set(false);
    this.passwordError.set(null);
    this.passwordFieldErrors.set({});

    this.account.updatePassword(this.passwordForm.getRawValue()).subscribe({
      next: () => {
        this.changingPassword.set(false);
        this.passwordDone.set(true);
        this.passwordForm.reset();
      },
      error: (error: HttpErrorResponse) => {
        this.changingPassword.set(false);
        if (error.status === 422) {
          const body = error.error as ValidationErrorBody | null;
          const flat: Record<string, string> = {};
          for (const [field, messages] of Object.entries(body?.errors ?? {})) {
            flat[field] = messages[0];
          }
          this.passwordFieldErrors.set(flat);
          this.passwordError.set(body?.message ?? 'Veuillez corriger les champs indiqués.');
        } else {
          this.passwordError.set('Une erreur est survenue. Merci de réessayer.');
        }
      },
    });
  }

  // ─────────────────────────── Zone de danger ───────────────────────────

  protected toggleDeleteConfirm(open: boolean): void {
    this.confirmingDelete.set(open);
    this.deleteError.set(null);
  }

  protected deleteAccount(): void {
    if (this.deleting()) {
      return;
    }
    this.deleting.set(true);
    this.deleteError.set(null);

    this.account.deleteAccount().subscribe({
      next: () => {
        this.auth.clearSession();
        void this.router.navigate(['/']);
      },
      error: () => {
        this.deleting.set(false);
        this.deleteError.set('La suppression a échoué. Merci de réessayer.');
      },
    });
  }

  // ─────────────────────────── Affichage ───────────────────────────

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
