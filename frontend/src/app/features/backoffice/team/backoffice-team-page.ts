import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { debounceTime } from 'rxjs';

import {
  AdminService,
  StaffRole,
  StaffStatus,
  TeamMember,
} from '../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { AuthService } from '../../../core/auth/auth.service';

/**
 * Écran **Équipe** du back-office (F7.1.f) — annuaire des employés + enrôlement.
 *
 * Le responsable voit tous les membres de l'équipe (agents / admins), les filtre,
 * en enrôle de nouveaux (code d'invitation e-mail) et pilote leur rôle / statut.
 * Les garde-fous de hiérarchie sont appliqués côté serveur : l'écran se contente
 * de refléter les autorisations (ex. seul un super_admin peut créer un admin) et
 * de rendre lisibles les refus (403 / 422).
 */
@Component({
  selector: 'app-backoffice-team-page',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './backoffice-team-page.html',
  styleUrl: './backoffice-team-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeTeamPageComponent {
  private readonly admin = inject(AdminService);
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  /** Liste courante + total (annuaire). */
  protected readonly members = signal<TeamMember[]>([]);
  protected readonly total = signal(0);
  protected readonly loading = signal(true);
  protected readonly listError = signal(false);

  /** Panneau d'enrôlement ouvert ? */
  protected readonly showCreate = signal(false);
  protected readonly createError = signal<string | null>(null);
  protected readonly createSuccess = signal<string | null>(null);
  protected readonly submitting = signal(false);

  /** Erreur d'une action de ligne (changement rôle/statut). */
  protected readonly actionError = signal<string | null>(null);

  /** Seul un super_admin peut créer/attribuer le rôle administrateur. */
  protected readonly isSuperAdmin = computed(() => this.auth.hasRole('super_admin'));
  /** Identifiant du compte connecté (pour masquer les actions sur soi-même). */
  protected readonly currentUserId = computed(() => this.auth.user()?.id ?? null);

  protected readonly filterForm = this.fb.nonNullable.group({
    q: '',
    role: '',
    status: '',
  });

  protected readonly createForm = this.fb.nonNullable.group({
    name: ['', [Validators.required]],
    email: ['', [Validators.required, Validators.email]],
    phone: [''],
    role: ['agent_kaikun' as StaffRole, [Validators.required]],
  });

  constructor() {
    // Rechargement au changement de filtre (léger debounce pour la recherche).
    this.filterForm.valueChanges
      .pipe(debounceTime(250), takeUntilDestroyed())
      .subscribe(() => this.load());

    this.load();
  }

  /** Charge l'annuaire selon les filtres courants. */
  protected load(): void {
    this.loading.set(true);
    this.listError.set(false);
    const { q, role, status } = this.filterForm.getRawValue();

    this.admin.team({ q, role, status }).subscribe({
      next: (page) => {
        this.members.set(page.data);
        this.total.set(page.meta.total);
        this.loading.set(false);
      },
      error: () => {
        this.listError.set(true);
        this.loading.set(false);
      },
    });
  }

  protected invalid(field: 'name' | 'email'): boolean {
    const control = this.createForm.controls[field];
    return control.invalid && control.touched;
  }

  /** Ouvre/ferme le panneau d'enrôlement (réinitialise les messages). */
  protected toggleCreate(): void {
    this.showCreate.update((open) => !open);
    this.createError.set(null);
    this.createSuccess.set(null);
  }

  /** Enrôle un nouveau membre de l'équipe. */
  protected create(): void {
    if (this.submitting()) return;
    if (this.createForm.invalid) {
      this.createForm.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.createError.set(null);
    this.createSuccess.set(null);

    const raw = this.createForm.getRawValue();

    this.admin
      .createTeamMember({
        name: raw.name,
        email: raw.email,
        phone: raw.phone || undefined,
        role: raw.role,
      })
      .subscribe({
        next: (member) => {
          this.submitting.set(false);
          this.createSuccess.set(
            `Invitation envoyée à ${member.name}. Un code de définition de mot de passe lui a été adressé par e-mail.`,
          );
          this.createForm.reset({ name: '', email: '', phone: '', role: 'agent_kaikun' });
          this.showCreate.set(false);
          this.load();
        },
        error: (error: HttpErrorResponse) => {
          this.submitting.set(false);
          this.createError.set(this.messageFor(error));
        },
      });
  }

  /** Change le statut d'un membre (suspendre / réactiver / désactiver). */
  protected setStatus(member: TeamMember, status: StaffStatus): void {
    this.actionError.set(null);
    this.admin.updateTeamMember(member.id, { status }).subscribe({
      next: (updated) => this.replaceMember(updated),
      error: (error: HttpErrorResponse) => this.actionError.set(this.messageFor(error)),
    });
  }

  /** Change le rôle d'un membre (agent ↔ admin). */
  protected setRole(member: TeamMember, role: StaffRole): void {
    this.actionError.set(null);
    this.admin.updateTeamMember(member.id, { role }).subscribe({
      next: (updated) => this.replaceMember(updated),
      error: (error: HttpErrorResponse) => this.actionError.set(this.messageFor(error)),
    });
  }

  /** Remplace un membre dans la liste après mise à jour. */
  private replaceMember(updated: TeamMember): void {
    this.members.update((list) => list.map((m) => (m.id === updated.id ? updated : m)));
  }

  /** Classe CSS du badge de statut. */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'actif':
        return 'is-ok';
      case 'suspendu':
        return 'is-warn';
      case 'desactive':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      return body?.message ?? 'Données invalides.';
    }
    if (error.status === 403) {
      const body = error.error as { message?: string } | null;
      return body?.message ?? 'Action non autorisée pour votre rôle.';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
