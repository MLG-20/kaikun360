import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { environment } from '../../../environments/environment';
import { Experience } from '../../models/experience.model';
import { Payment } from '../../models/payment.model';
import { Property } from '../../models/property.model';
import { Provider } from '../../models/provider.model';
import { Review } from '../../models/review.model';
import { User } from '../../models/user.model';
import { Vehicle } from '../../models/vehicle.model';
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

// --- File de validation (F7.2.a) ---------------------------------------------

/** Types de ressources soumis à validation (miroir de `ValidatorRegistry`). */
export type ValidationType = 'property' | 'vehicle' | 'experience' | 'provider';

/**
 * Une entrée normalisée de la file de validation (miroir de
 * `ResourceValidator::toEntry()` — même forme pour tous les types).
 */
export interface QueueEntry {
  type: ValidationType;
  id: number;
  /** Référence lisible (véhicules, expériences) ; null pour biens/prestataires. */
  reference: string | null;
  label: string;
  owner_id: number;
  /** Déposant : identité + contact (null si le compte a été supprimé). */
  owner: QueueOwner | null;
  submitted_at: string | null;
}

/** Le déposant d'une ressource en attente (miroir de `OwnerEntry`). */
export interface QueueOwner {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
}

/** Un seau de la file d'ensemble : compteur + aperçu des éléments en attente. */
export interface QueueBucket {
  count: number;
  items: QueueEntry[];
}

/** Vue d'ensemble de la file de validation (GET /admin/queue, sans `type`). */
export interface ValidationQueueOverview {
  queue: Record<ValidationType, QueueBucket>;
  total_pending: number;
}

/** Décision de modération d'une ressource en attente. */
export type ValidationDecision = 'approve' | 'reject';

/** Filtres communs des catalogues de supervision (F7.2.b). */
export interface CatalogQuery {
  /** Statut exact (`en_attente_validation`, `publie`, `suspendu`, `rejete`, `archive`). */
  status?: string;
  /** Recherche plein-texte (titre / marque-modèle / destination selon le type). */
  q?: string;
  page?: number;
}

// --- Nuitées / exploitation (F7.2.c) ----------------------------------------

/** Statut du ménage d'une nuitée (miroir de `HousekeepingStatus`). */
export type HousekeepingStatus = 'a_faire' | 'en_cours' | 'fait';

/** Une réservation de nuitée dans le calendrier d'exploitation. */
export interface StayBooking {
  booking_id: number;
  reference: string;
  stay_id: number;
  property_title: string | null;
  start_date: string | null;
  end_date: string | null;
  guests: number;
  status: string;
  checked_in_at: string | null;
  checked_out_at: string | null;
  housekeeping_status: HousekeepingStatus | null;
}

/** Résumé renvoyé après une transition (check-in/out, ménage) — partiel. */
export interface StayBookingSummary {
  booking_id: number;
  reference: string;
  status: string;
  checked_in_at: string | null;
  checked_out_at: string | null;
  housekeeping_status: HousekeepingStatus | null;
}

/** Filtres du calendrier des nuitées (bornes sur la date d'arrivée). */
export interface StayCalendarQuery {
  from?: string;
  to?: string;
  page?: number;
}

// --- Paiements (F7.2.d) -----------------------------------------------------

/** Filtres de la supervision des paiements. */
export interface PaymentQuery {
  /** Statut exact (`initie`, `en_attente`, `autorise`, `complete`, `refuse`, `annule`, `rembourse`). */
  status?: string;
  /** Recherche par référence interne ou PSP. */
  reference?: string;
  booking_id?: number;
  page?: number;
}

// --- Dossiers de suivi (F7.2.e) ---------------------------------------------

/**
 * Une demande de construction en supervision (miroir de
 * `ConstructionRequestResource`, module Build). Lecture seule côté back-office.
 */
export interface ConstructionDossier {
  id: number;
  reference: string;
  objective: string | null;
  objective_label: string | null;
  city: string | null;
  surface_m2: number | null;
  budget_xof: number | null;
  finish_level: string | null;
  finish_level_label: string | null;
  description: string | null;
  estimated_cost_xof: number | null;
  status: string | null;
  status_label: string | null;
  /** Nombre de comptes rendus de chantier (photos/vidéos). */
  reports_count?: number;
  /** Nombre de jalons du planning. */
  milestones_count?: number;
}

/** Agrégats financiers d'un mandat (miroir du bloc `summary` de `MandateResource`). */
export interface MandateSummary {
  loyers_payes_xof: number;
  loyers_impayes_xof: number;
  loyers_payes_count: number;
  loyers_impayes_count: number;
  depenses_xof: number;
  reversements_xof: number;
  incidents_ouverts: number;
}

/**
 * Un mandat de gestion locative en supervision (miroir de `MandateResource`,
 * module Manage). Lecture seule côté back-office.
 */
export interface MandateDossier {
  id: number;
  reference: string;
  commission_rate: number | string | null;
  status: string | null;
  status_label: string | null;
  start_date: string | null;
  end_date: string | null;
  summary: MandateSummary;
  /** Bien géré (titre, localisation, propriétaire) — réutilise le modèle Property. */
  property: Property;
  /** Compteurs bruts de supervision (surfacés par le contrôleur admin). */
  rents_count?: number;
  incidents_count?: number;
  expenses_count?: number;
  payouts_count?: number;
}

/** Filtres des demandes de construction (supervision). */
export interface ConstructionQuery {
  /** Statut exact (`soumise`, `en_etude`, `devis_envoye`, `acceptee`, `en_chantier`, `terminee`, `annulee`). */
  status?: string;
  /** Filtre par ville. */
  city?: string;
  page?: number;
}

/** Filtres des mandats de gestion locative (supervision). */
export interface MandateQuery {
  /** Statut exact (`en_attente`, `actif`, `suspendu`, `termine`). */
  status?: string;
  page?: number;
}

// --- Comptes & documents (F7.2.f) -------------------------------------------

/** Filtres de l'annuaire des comptes utilisateurs (supervision). */
export interface AccountQuery {
  /** Rôle Spatie (`client`, `proprietaire`, `prestataire`, `entreprise`, `agent_kaikun`…). */
  role?: string;
  /** Statut du compte (`actif`, `suspendu`, `desactive`, `en_attente_verification`). */
  status?: string;
  /** Recherche plein-texte (nom / e-mail / téléphone). */
  q?: string;
  page?: number;
}

/** Corps de mise à jour d'un compte (PATCH /admin/users/{id}). Au moins l'un des deux. */
export interface UpdateUserPayload {
  role?: string;
  status?: string;
}

/** Une pièce justificative (KYC) déposée par un utilisateur (fiche compte). */
export interface AccountDocument {
  id: number;
  type: string;
  type_label: string;
  original_name: string | null;
  status: string | null;
  created_at: string | null;
}

/**
 * Une entrée du journal d'audit concernant un compte (exigence CDC §6
 * « historique » du module Utilisateurs).
 */
export interface AccountActivity {
  id: number;
  description: string | null;
  log_name: string | null;
  /** Nom de l'acteur qui a réalisé l'action (null si automatique/système). */
  causer_name: string | null;
  /** Propriétés tracées (ex. { role, status }). */
  properties: Record<string, unknown> | null;
  created_at: string | null;
}

/** Fiche complète d'un compte (GET /admin/users/{id}) : identité + pièces + historique. */
export interface AccountDetail {
  user: User;
  documents: AccountDocument[];
  activity: AccountActivity[];
}

// --- Avis & qualité (F7.2.g) ------------------------------------------------

/**
 * Un avis dans la file de modération (miroir de la forme normalisée de
 * `AdminReviewController::index`). Ajoute à l'avis la ressource notée
 * (`resource_type` + `resource_label`) pour l'affichage.
 */
export interface AdminReview {
  id: number;
  reference: string;
  rating: number;
  comment: string | null;
  status: string | null;
  status_label: string | null;
  author: { id: number; name: string } | null;
  /** Type de ressource notée : `stay` / `vehicle` / `experience` / `provider`. */
  resource_type: string;
  /** Intitulé lisible de la ressource notée. */
  resource_label: string;
  created_at: string | null;
}

/** Filtres de la file de modération des avis. */
export interface ReviewQuery {
  /** Statut (`en_attente` par défaut côté serveur, `publie`, `rejete`). */
  status?: string;
  /** Recherche dans le commentaire. */
  q?: string;
  page?: number;
}

/** Décision de modération d'un avis. */
export type ReviewModeration = 'publie' | 'rejete';

/** Filtres de la supervision des prestataires. */
export interface ProviderQuery {
  /** Statut (`en_attente`, `valide`, `refuse`, `suspendu`). */
  status?: string;
  /** Recherche sur le nom commercial. */
  q?: string;
  page?: number;
}

/**
 * Familles de pièces exposées par la vue documentaire transverse
 * (miroir de `AdminDocumentController::TYPES`).
 */
export type DocumentType = 'kyc' | 'property' | 'certification' | 'payout_proof';

/** Compteurs de la vue d'ensemble documentaire (GET /admin/documents sans `type`). */
export type DocumentsOverview = Record<DocumentType, number>;

/**
 * Une pièce normalisée de la vue documentaire (miroir de la forme renvoyée par
 * `AdminDocumentController::index` avec `?type=`). Le sujet (`subject_type` +
 * `subject_id`) rattache la pièce à un utilisateur, un bien, un prestataire ou
 * un propriétaire selon la famille.
 */
export interface AdminDocument {
  doc_type: DocumentType;
  id: number;
  subject_type: string;
  subject_id: number;
  /** Intitulé lisible (type de pièce, nom de certification, référence…). */
  label: string | null;
  /** Nom d'origine du fichier ou chemin de stockage. */
  original_name: string | null;
  /** Statut de la pièce (validation KYC, vérification certif, statut reversement…). */
  status: string | null;
  created_at: string | null;
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

  // --- File de validation (F7.2.a) --------------------------------------------

  /** Vue d'ensemble de la file : compteur + aperçu par type. GET /admin/queue */
  validationQueue(): Observable<ValidationQueueOverview> {
    return this.http
      .get<ApiEnvelope<ValidationQueueOverview>>(`${this.api}/admin/queue`)
      .pipe(map((response) => response.data));
  }

  /** Liste paginée des éléments en attente d'un type. GET /admin/queue?type= */
  validationQueueByType(
    type: ValidationType,
    page = 1,
    perPage = 20,
  ): Observable<Paginated<QueueEntry>> {
    const params = new HttpParams()
      .set('type', type)
      .set('page', String(page))
      .set('per_page', String(perPage));
    return this.http.get<Paginated<QueueEntry>>(`${this.api}/admin/queue`, { params });
  }

  /**
   * Valide ou refuse une ressource en attente.
   * PATCH /admin/validate/{type}/{id}
   *
   * Un refus peut être motivé (tracé dans le journal d'activité). L'API vérifie
   * la permission fine propre au type (`valider:bien`, `valider:vehicule`…) →
   * un agent sans le droit reçoit un 403.
   */
  decide(
    type: ValidationType,
    id: number,
    decision: ValidationDecision,
    reason?: string,
  ): Observable<void> {
    const body: { decision: ValidationDecision; reason?: string } = { decision };
    if (reason) body.reason = reason;
    return this.http
      .patch<ApiEnvelope<unknown>>(`${this.api}/admin/validate/${type}/${id}`, body)
      .pipe(map(() => undefined));
  }

  // --- Catalogues (F7.2.b) ----------------------------------------------------

  /** Biens, TOUS statuts (supervision). GET /admin/properties */
  adminProperties(query: CatalogQuery = {}): Observable<Paginated<Property>> {
    return this.http.get<Paginated<Property>>(`${this.api}/admin/properties`, {
      params: this.catalogParams(query),
    });
  }

  /** Véhicules, TOUS statuts (supervision). GET /admin/vehicles */
  adminVehicles(query: CatalogQuery = {}): Observable<Paginated<Vehicle>> {
    return this.http.get<Paginated<Vehicle>>(`${this.api}/admin/vehicles`, {
      params: this.catalogParams(query),
    });
  }

  /** Expériences, TOUS statuts (supervision). GET /admin/experiences */
  adminExperiences(query: CatalogQuery = {}): Observable<Paginated<Experience>> {
    return this.http.get<Paginated<Experience>>(`${this.api}/admin/experiences`, {
      params: this.catalogParams(query),
    });
  }

  /** Construit les query params communs des catalogues (statut, recherche, page). */
  private catalogParams(query: CatalogQuery): HttpParams {
    let params = new HttpParams();
    if (query.status) params = params.set('status', query.status);
    if (query.q) params = params.set('q', query.q);
    if (query.page) params = params.set('page', String(query.page));
    return params;
  }

  // --- Nuitées / exploitation (F7.2.c) --------------------------------------

  /** Calendrier des séjours (paginé). GET /admin/stays/calendar */
  staysCalendar(query: StayCalendarQuery = {}): Observable<Paginated<StayBooking>> {
    let params = new HttpParams();
    if (query.from) params = params.set('from', query.from);
    if (query.to) params = params.set('to', query.to);
    if (query.page) params = params.set('page', String(query.page));
    return this.http.get<Paginated<StayBooking>>(`${this.api}/admin/stays/calendar`, { params });
  }

  /** Enregistre l'arrivée. PATCH /admin/stay-bookings/{id}/check-in */
  stayCheckIn(bookingId: number): Observable<StayBookingSummary> {
    return this.http
      .patch<ApiEnvelope<{ booking: StayBookingSummary }>>(
        `${this.api}/admin/stay-bookings/${bookingId}/check-in`,
        {},
      )
      .pipe(map((response) => response.data.booking));
  }

  /** Enregistre le départ (déclenche le ménage). PATCH /admin/stay-bookings/{id}/check-out */
  stayCheckOut(bookingId: number): Observable<StayBookingSummary> {
    return this.http
      .patch<ApiEnvelope<{ booking: StayBookingSummary }>>(
        `${this.api}/admin/stay-bookings/${bookingId}/check-out`,
        {},
      )
      .pipe(map((response) => response.data.booking));
  }

  /** Met à jour le statut de ménage. PATCH /admin/stay-bookings/{id}/housekeeping */
  stayHousekeeping(
    bookingId: number,
    status: HousekeepingStatus,
  ): Observable<StayBookingSummary> {
    return this.http
      .patch<ApiEnvelope<{ booking: StayBookingSummary }>>(
        `${this.api}/admin/stay-bookings/${bookingId}/housekeeping`,
        { status },
      )
      .pipe(map((response) => response.data.booking));
  }

  // --- Paiements (F7.2.d) ----------------------------------------------------

  /** Liste paginée des paiements (supervision). GET /admin/payments */
  payments(query: PaymentQuery = {}): Observable<Paginated<Payment>> {
    let params = new HttpParams();
    if (query.status) params = params.set('status', query.status);
    if (query.reference) params = params.set('reference', query.reference);
    if (query.booking_id) params = params.set('booking_id', String(query.booking_id));
    if (query.page) params = params.set('page', String(query.page));
    return this.http.get<Paginated<Payment>>(`${this.api}/admin/payments`, { params });
  }

  /**
   * Confirme manuellement un paiement Wave/OM (Phase 1 du CDC).
   * POST /admin/payments/{id}/confirm
   *
   * Réservé au mode `manuel` non encore encaissé. `providerReference` = preuve
   * (identifiant de la transaction Wave/OM), conservée pour la traçabilité.
   */
  confirmPayment(paymentId: number, providerReference?: string): Observable<Payment> {
    const body: { provider_reference?: string } = {};
    if (providerReference) body.provider_reference = providerReference;
    return this.http
      .post<ApiEnvelope<{ payment: Payment }>>(`${this.api}/admin/payments/${paymentId}/confirm`, body)
      .pipe(map((response) => response.data.payment));
  }

  /**
   * Rembourse tout ou partie d'un paiement encaissé.
   * POST /admin/payments/{id}/refund
   *
   * `amountXof` absent = remboursement total ; sinon montant partiel (≤ payé).
   * Seul un paiement au statut `complete` est remboursable.
   */
  refundPayment(paymentId: number, amountXof?: number): Observable<Payment> {
    const body: { amount_xof?: number } = {};
    if (amountXof) body.amount_xof = amountXof;
    return this.http
      .post<ApiEnvelope<{ payment: Payment }>>(`${this.api}/admin/payments/${paymentId}/refund`, body)
      .pipe(map((response) => response.data.payment));
  }

  // --- Dossiers de suivi (F7.2.e) --------------------------------------------

  /** Demandes de construction, toutes (supervision). GET /admin/construction-requests */
  adminConstructionRequests(query: ConstructionQuery = {}): Observable<Paginated<ConstructionDossier>> {
    let params = new HttpParams();
    if (query.status) params = params.set('status', query.status);
    if (query.city) params = params.set('city', query.city);
    if (query.page) params = params.set('page', String(query.page));
    return this.http.get<Paginated<ConstructionDossier>>(`${this.api}/admin/construction-requests`, {
      params,
    });
  }

  /** Mandats de gestion locative, tous (supervision). GET /admin/mandates */
  adminMandates(query: MandateQuery = {}): Observable<Paginated<MandateDossier>> {
    let params = new HttpParams();
    if (query.status) params = params.set('status', query.status);
    if (query.page) params = params.set('page', String(query.page));
    return this.http.get<Paginated<MandateDossier>>(`${this.api}/admin/mandates`, { params });
  }

  // --- Comptes & documents (F7.2.f) ------------------------------------------

  /** Annuaire paginé des comptes (supervision). GET /admin/users */
  users(query: AccountQuery = {}): Observable<Paginated<User>> {
    let params = new HttpParams();
    if (query.role) params = params.set('role', query.role);
    if (query.status) params = params.set('status', query.status);
    if (query.q) params = params.set('q', query.q);
    if (query.page) params = params.set('page', String(query.page));
    return this.http.get<Paginated<User>>(`${this.api}/admin/users`, { params });
  }

  /** Fiche complète d'un compte (identité + profil + pièces). GET /admin/users/{id} */
  userDetail(id: number): Observable<AccountDetail> {
    return this.http
      .get<ApiEnvelope<AccountDetail>>(`${this.api}/admin/users/${id}`)
      .pipe(map((response) => response.data));
  }

  /**
   * Met à jour le rôle et/ou le statut d'un compte. PATCH /admin/users/{id}
   *
   * Les garde-fous de hiérarchie (pas d'auto-modification, escalade réservée au
   * super_admin, compte super_admin protégé) sont appliqués côté serveur ; un
   * refus remonte en 403.
   */
  updateUser(id: number, payload: UpdateUserPayload): Observable<User> {
    return this.http
      .patch<ApiEnvelope<{ user: User }>>(`${this.api}/admin/users/${id}`, payload)
      .pipe(map((response) => response.data.user));
  }

  /**
   * Demande une pièce à un utilisateur (notification + relais n8n/WhatsApp).
   * POST /admin/users/{id}/request-document
   */
  requestDocument(id: number, documentType: string, note?: string): Observable<string> {
    const body: { document_type: string; note?: string } = { document_type: documentType };
    if (note) body.note = note;
    return this.http
      .post<ApiEnvelope<{ message: string }>>(`${this.api}/admin/users/${id}/request-document`, body)
      .pipe(map((response) => response.data.message));
  }

  /** Compteurs de la vue documentaire transverse. GET /admin/documents */
  documentsOverview(): Observable<DocumentsOverview> {
    return this.http
      .get<ApiEnvelope<{ documents: DocumentsOverview }>>(`${this.api}/admin/documents`)
      .pipe(map((response) => response.data.documents));
  }

  /** Liste paginée normalisée d'une famille de pièces. GET /admin/documents?type= */
  documents(type: DocumentType, page = 1): Observable<Paginated<AdminDocument>> {
    const params = new HttpParams().set('type', type).set('page', String(page));
    return this.http.get<Paginated<AdminDocument>>(`${this.api}/admin/documents`, { params });
  }

  // --- Avis & qualité (F7.2.g) -----------------------------------------------

  /** File de modération des avis (par défaut `en_attente`). GET /admin/reviews */
  adminReviews(query: ReviewQuery = {}): Observable<Paginated<AdminReview>> {
    let params = new HttpParams();
    if (query.status) params = params.set('status', query.status);
    if (query.q) params = params.set('q', query.q);
    if (query.page) params = params.set('page', String(query.page));
    return this.http.get<Paginated<AdminReview>>(`${this.api}/admin/reviews`, { params });
  }

  /**
   * Publie ou rejette un avis en attente. PATCH /reviews/{id}/moderate
   *
   * Sert l'endpoint transversal de modération (hors préfixe `/admin`). Une
   * publication répercute la note sur le prestataire concerné côté serveur.
   */
  moderateReview(id: number, status: ReviewModeration): Observable<Review> {
    return this.http
      .patch<ApiEnvelope<{ review: Review }>>(`${this.api}/reviews/${id}/moderate`, { status })
      .pipe(map((response) => response.data.review));
  }

  /** Supervision des prestataires (note + sanctions). GET /admin/providers */
  adminProviders(query: ProviderQuery = {}): Observable<Paginated<Provider>> {
    let params = new HttpParams();
    if (query.status) params = params.set('status', query.status);
    if (query.q) params = params.set('q', query.q);
    if (query.page) params = params.set('page', String(query.page));
    return this.http.get<Paginated<Provider>>(`${this.api}/admin/providers`, { params });
  }

  /**
   * Émet un avertissement à un prestataire (charte qualité).
   * PATCH /providers/{id}/warn — au-delà du seuil, suspension d'office côté serveur.
   */
  warnProvider(id: number, reason: string): Observable<Provider> {
    return this.http
      .patch<ApiEnvelope<{ provider: Provider }>>(`${this.api}/providers/${id}/warn`, { reason })
      .pipe(map((response) => response.data.provider));
  }

  /** Suspend un prestataire (motif obligatoire). PATCH /providers/{id}/suspend */
  suspendProvider(id: number, reason: string): Observable<Provider> {
    return this.http
      .patch<ApiEnvelope<{ provider: Provider }>>(`${this.api}/providers/${id}/suspend`, { reason })
      .pipe(map((response) => response.data.provider));
  }
}
