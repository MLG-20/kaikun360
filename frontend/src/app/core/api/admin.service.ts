import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ApiEnvelope } from './api-response.model';
import { Paginated } from './pagination.model';

/**
 * Photographie agrégée du tableau de bord back-office (miroir de
 * `DashboardAggregator::snapshot()` côté Laravel — `GET /admin/dashboard`).
 */
export interface DashboardSnapshot {
  /** Files de validation en attente. */
  queues: {
    properties_pending: number;
    vehicles_pending: number;
    experiences_pending: number;
    providers_pending: number;
  };
  /** Activité du jour (date serveur). */
  today: {
    requests: number;
    bookings: number;
  };
  /** Estimation des revenus (encaissement réel = PayTech). */
  revenue: {
    gross_volume_xof: number;
    commission_xof: number;
  };
  /** Alertes qualité / incidents. */
  alerts: {
    reviews_to_moderate: number;
    open_incidents: number;
  };
  /** Indicateurs cumulés. */
  kpi: {
    users_total: number;
    providers_validated: number;
    properties_published: number;
    bookings_total: number;
  };
}

/** Rôle d'un membre de l'équipe attribuable via le back-office (F7.1.a). */
export type StaffRole = 'agent_kaikun' | 'admin';

/** Statut pilotable d'un membre de l'équipe (F7.1.a). */
export type StaffStatus = 'actif' | 'suspendu' | 'desactive';

/** Un membre de l'équipe back-office (miroir de `TeamMemberResource`). */
export interface TeamMember {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  role: string | null;
  role_label: string | null;
  status: string | null;
  status_label: string | null;
  /** Permissions effectives (rôle + délégations directes). */
  permissions: string[];
  /** Dossiers délégués à cette personne (cases cochées) — quand chargé. */
  direct_permissions?: string[];
  email_verified_at: string | null;
  created_at: string | null;
}

/** Filtres de l'annuaire de l'équipe. */
export interface TeamQuery {
  role?: string;
  status?: string;
  q?: string;
  page?: number;
}

/** Corps d'enrôlement d'un membre (POST /admin/team). */
export interface CreateTeamMemberPayload {
  name: string;
  email: string;
  phone?: string;
  role: StaffRole;
}

/** Corps de mise à jour d'un membre (PATCH /admin/team/{id}). */
export interface UpdateTeamMemberPayload {
  role?: StaffRole;
  status?: StaffStatus;
}

/** Une permission délégable (entrée du catalogue, miroir de `AdminPermission::catalog()`). */
export interface PermissionCatalogItem {
  value: string;
  label: string;
  /** Groupe d'affichage : Validation / Exploitation / Gouvernance. */
  group: string;
  /** La déléguer exige d'être super_admin (permission de gouvernance). */
  requires_super_admin: boolean;
}

/** Matrice de délégation d'un agent : catalogue + dossiers actuellement cochés. */
export interface PermissionsState {
  catalog: PermissionCatalogItem[];
  granted: string[];
}

/** Une session de présence (pointeuse, F7.1.c). */
export interface AttendanceSession {
  id: number;
  started_at: string;
  ended_at: string | null;
  duration_minutes: number | null;
  is_open: boolean;
}

/** Un jour de la feuille de présence : ses sessions + le cumul. */
export interface AttendanceDay {
  date: string;
  sessions: AttendanceSession[];
  total_minutes: number;
}

/** Détail mensuel d'un employé. */
export interface AttendanceDetail {
  user: { id: number; name: string; email: string | null };
  month: string;
  days: AttendanceDay[];
  total_minutes: number;
}

/** Mon pointage : état courant + mon détail du mois. */
export interface MyAttendance {
  on_duty: boolean;
  current: AttendanceSession | null;
  month: AttendanceDetail;
}

/** Ligne de récapitulatif d'équipe (un employé sur le mois). */
export interface AttendanceEmployee {
  user: { id: number; name: string; email: string | null };
  total_minutes: number;
  days_present: number;
  currently_on_duty: boolean;
}

/** Récapitulatif mensuel de toute l'équipe. */
export interface AttendanceSummary {
  month: string;
  employees: AttendanceEmployee[];
}

/**
 * Service d'accès à l'API du **back-office** (F7).
 *
 * Regroupe les appels du poste de commandement : tableau de bord (F7.1.e),
 * gestion de l'équipe (F7.1.f). Permissions et pointeuse s'ajouteront avec leurs
 * écrans. Toutes les routes visées sont sous `/admin` et protégées côté serveur
 * par les permissions back-office ; le jeton est ajouté par le `tokenInterceptor`.
 */
@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** Indicateurs agrégés du tableau de bord. GET /admin/dashboard */
  dashboard(): Observable<DashboardSnapshot> {
    return this.http
      .get<ApiEnvelope<DashboardSnapshot>>(`${this.api}/admin/dashboard`)
      .pipe(map((response) => response.data));
  }

  /** Annuaire paginé de l'équipe back-office. GET /admin/team */
  team(query: TeamQuery = {}): Observable<Paginated<TeamMember>> {
    let params = new HttpParams();
    if (query.role) params = params.set('role', query.role);
    if (query.status) params = params.set('status', query.status);
    if (query.q) params = params.set('q', query.q);
    if (query.page) params = params.set('page', String(query.page));

    return this.http.get<Paginated<TeamMember>>(`${this.api}/admin/team`, { params });
  }

  /** Enrôle (invite) un membre de l'équipe. POST /admin/team */
  createTeamMember(payload: CreateTeamMemberPayload): Observable<TeamMember> {
    return this.http
      .post<ApiEnvelope<{ member: TeamMember }>>(`${this.api}/admin/team`, payload)
      .pipe(map((response) => response.data.member));
  }

  /** Met à jour le rôle et/ou le statut d'un membre. PATCH /admin/team/{id} */
  updateTeamMember(id: number, payload: UpdateTeamMemberPayload): Observable<TeamMember> {
    return this.http
      .patch<ApiEnvelope<{ member: TeamMember }>>(`${this.api}/admin/team/${id}`, payload)
      .pipe(map((response) => response.data.member));
  }

  /** Matrice de délégation d'un agent. GET /admin/team/{id}/permissions */
  teamPermissions(id: number): Observable<PermissionsState> {
    return this.http
      .get<ApiEnvelope<PermissionsState>>(`${this.api}/admin/team/${id}/permissions`)
      .pipe(map((response) => response.data));
  }

  /** Remplace les dossiers délégués à un agent. PUT /admin/team/{id}/permissions */
  syncTeamPermissions(id: number, permissions: string[]): Observable<TeamMember> {
    return this.http
      .put<ApiEnvelope<{ member: TeamMember }>>(`${this.api}/admin/team/${id}/permissions`, {
        permissions,
      })
      .pipe(map((response) => response.data.member));
  }

  // --- Pointeuse (F7.1.c) -----------------------------------------------------

  /** Pointer mon entrée. POST /admin/attendance/clock-in */
  clockIn(): Observable<AttendanceSession> {
    return this.http
      .post<ApiEnvelope<{ attendance: AttendanceSession }>>(`${this.api}/admin/attendance/clock-in`, {})
      .pipe(map((response) => response.data.attendance));
  }

  /** Pointer ma sortie. POST /admin/attendance/clock-out */
  clockOut(): Observable<AttendanceSession> {
    return this.http
      .post<ApiEnvelope<{ attendance: AttendanceSession }>>(`${this.api}/admin/attendance/clock-out`, {})
      .pipe(map((response) => response.data.attendance));
  }

  /** Mon pointage (état + détail du mois). GET /admin/attendance/me */
  myAttendance(): Observable<MyAttendance> {
    return this.http
      .get<ApiEnvelope<MyAttendance>>(`${this.api}/admin/attendance/me`)
      .pipe(map((response) => response.data));
  }

  /** Récapitulatif d'équipe du mois. GET /admin/attendance?month= */
  attendanceSummary(month?: string): Observable<AttendanceSummary> {
    let params = new HttpParams();
    if (month) params = params.set('month', month);
    return this.http
      .get<ApiEnvelope<AttendanceSummary>>(`${this.api}/admin/attendance`, { params })
      .pipe(map((response) => response.data));
  }

  /** Détail mensuel d'un employé. GET /admin/attendance?user=&month= */
  attendanceDetail(userId: number, month?: string): Observable<AttendanceDetail> {
    let params = new HttpParams().set('user', String(userId));
    if (month) params = params.set('month', month);
    return this.http
      .get<ApiEnvelope<AttendanceDetail>>(`${this.api}/admin/attendance`, { params })
      .pipe(map((response) => response.data));
  }

  /** Export CSV de la feuille de présence. GET /admin/attendance?format=csv */
  attendanceCsv(month?: string, userId?: number): Observable<Blob> {
    let params = new HttpParams().set('format', 'csv');
    if (month) params = params.set('month', month);
    if (userId) params = params.set('user', String(userId));
    return this.http.get(`${this.api}/admin/attendance`, { params, responseType: 'blob' });
  }
}
