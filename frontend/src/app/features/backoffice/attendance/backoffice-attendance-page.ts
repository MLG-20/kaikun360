import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';

import {
  AdminService,
  AttendanceDetail,
  AttendanceEmployee,
  AttendanceSummary,
  MyAttendance,
} from '../../../core/api/admin.service';
import { AuthService } from '../../../core/auth/auth.service';

/**
 * Écran **Pointeuse** du back-office (F7.1.h).
 *
 * Deux périmètres, comme côté API :
 *   - **Ma présence** (tout membre) : pointer son entrée / sa sortie et voir son
 *     cumul du mois ;
 *   - **Feuille de l'équipe** (admin/super_admin) : récapitulatif mensuel par
 *     employé, détail jour par jour et export CSV.
 */
@Component({
  selector: 'app-backoffice-attendance-page',
  imports: [],
  templateUrl: './backoffice-attendance-page.html',
  styleUrl: './backoffice-attendance-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeAttendancePageComponent {
  private readonly admin = inject(AdminService);
  private readonly auth = inject(AuthService);

  // --- Ma présence ---
  protected readonly me = signal<MyAttendance | null>(null);
  protected readonly loadingMe = signal(true);
  protected readonly actionBusy = signal(false);
  protected readonly actionError = signal<string | null>(null);

  // --- Feuille d'équipe (supervision) ---
  protected readonly isSupervisor = computed(() =>
    this.auth.hasAnyRole(['admin', 'super_admin']),
  );
  protected readonly month = signal<string>(new Date().toISOString().slice(0, 7));
  protected readonly summary = signal<AttendanceSummary | null>(null);
  protected readonly loadingSummary = signal(false);
  protected readonly detail = signal<AttendanceDetail | null>(null);

  constructor() {
    this.loadMe();
    if (this.isSupervisor()) {
      this.loadSummary();
    }
  }

  private loadMe(): void {
    this.loadingMe.set(true);
    this.admin.myAttendance().subscribe({
      next: (data) => {
        this.me.set(data);
        this.loadingMe.set(false);
      },
      error: () => this.loadingMe.set(false),
    });
  }

  /** Pointer l'entrée ou la sortie selon l'état courant. */
  protected togglePunch(): void {
    if (this.actionBusy()) return;
    this.actionBusy.set(true);
    this.actionError.set(null);

    const onDuty = this.me()?.on_duty ?? false;
    const call = onDuty ? this.admin.clockOut() : this.admin.clockIn();

    call.subscribe({
      next: () => {
        this.actionBusy.set(false);
        this.loadMe();
        if (this.isSupervisor()) this.loadSummary();
      },
      error: (error: HttpErrorResponse) => {
        this.actionBusy.set(false);
        const body = error.error as { message?: string } | null;
        this.actionError.set(body?.message ?? 'Pointage impossible. Réessayez.');
      },
    });
  }

  private loadSummary(): void {
    this.loadingSummary.set(true);
    this.detail.set(null);
    this.admin.attendanceSummary(this.month()).subscribe({
      next: (data) => {
        this.summary.set(data);
        this.loadingSummary.set(false);
      },
      error: () => this.loadingSummary.set(false),
    });
  }

  /** Changement de mois (input type=month). */
  protected onMonthChange(value: string): void {
    if (!value) return;
    this.month.set(value);
    this.loadSummary();
  }

  /** Affiche le détail jour par jour d'un employé. */
  protected showDetail(emp: AttendanceEmployee): void {
    this.admin.attendanceDetail(emp.user.id, this.month()).subscribe({
      next: (data) => this.detail.set(data),
    });
  }

  protected closeDetail(): void {
    this.detail.set(null);
  }

  /** Exporte la feuille du mois en CSV (téléchargement). */
  protected exportCsv(): void {
    this.admin.attendanceCsv(this.month()).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `presence-equipe-${this.month()}.csv`;
        a.click();
        URL.revokeObjectURL(url);
      },
    });
  }

  /** Minutes → « HhMM » lisible. */
  protected toHours(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${h}h${String(m).padStart(2, '0')}`;
  }

  /** Heure « HH:MM » d'un horodatage ISO. */
  protected timeOf(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }
}
