import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { environment } from '../../../environments/environment';
import { AdminExperience } from '../../models/experience.model';
import { AdminMobilityService } from '../../models/mobility-service.model';
import { Payment } from '../../models/payment.model';
import { Property } from '../../models/property.model';
import { Provider } from '../../models/provider.model';
import { Review } from '../../models/review.model';
import { User } from '../../models/user.model';
import { AdminVehicle } from '../../models/vehicle.model';
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
  /**
   * Galerie de la ressource (F8.1) — miroir de `MediaEntry::summary()`.
   *
   * Toujours présente, même pour les prestataires qui n'ont pas de galerie
   * (total à 0), pour que la file ait la même forme d'un onglet à l'autre.
   */
  media: QueueMedia;
}

/** Un média normalisé pour le back-office (miroir de `MediaEntry::from()`). */
export interface QueueMediaItem {
  id: number;
  reference: string;
  type: 'image' | 'video';
  /** URL publique (fichier stocké) ou URL externe (vidéo). */
  url: string | null;
  original_name: string | null;
  mime_type: string | null;
  size_bytes: number | null;
  is_primary: boolean;
  position: number;
  status: 'actif' | 'masque';
  status_label: string;
  /** Masqué par la modération : reste visible en back-office, pas au public. */
  is_hidden: boolean;
}

/**
 * Résumé de galerie : compteurs + vignettes.
 *
 * Dans la file, `items` est borné à un aperçu ; sur le dossier complet, il
 * contient toute la galerie, médias masqués compris.
 */
export interface QueueMedia {
  total: number;
  images: number;
  videos: number;
  /** Nombre de médias écartés par un agent. */
  hidden: number;
  items: QueueMediaItem[];
}

/**
 * Dossier complet d'un élément à valider (F8.1) — `GET /admin/queue/{type}/{id}`.
 *
 * L'entrée de file, enrichie de la galerie ENTIÈRE et des caractéristiques
 * propres au type que l'agent doit contrôler avant publication.
 */
export interface QueueEntryDetail extends QueueEntry {
  /** Caractéristiques à contrôler, déjà libellées par le serveur. */
  fields: Record<string, string | number | boolean | null>;
}

/** Réponse du dossier complet : l'élément et son actionnabilité. */
export interface QueueDetailResponse {
  entry: QueueEntryDetail;
  /** Faux si l'élément a déjà été tranché : on affiche sans permettre d'agir. */
  is_pending: boolean;
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

/** Filtres communs des catalogues de supervision (F7.2.b, étendus en F7.2.j). */
export interface CatalogQuery {
  /** Statut exact (`en_attente_validation`, `publie`, `suspendu`, `rejete`, `archive`). */
  status?: string;
  /** Recherche plein-texte (titre / marque-modèle / destination selon le type). */
  q?: string;
  /** Type de ressource (catégorie de véhicule, nature de trajet…). — F7.2.j */
  type?: string;
  /** Véhicules AVEC (`true`) ou SANS (`false`) chauffeur. — F7.2.j */
  driver?: boolean;
  /** Bornes de période sur la date de départ (trajets, `AAAA-MM-JJ`). — F7.2.j */
  from?: string;
  to?: string;
  /** Destination exacte (circuits touristiques). — F7.2.k */
  destination?: string;
  page?: number;
}

// --- Fiches Mobilité (F8.2.b) -----------------------------------------------

/** Une location de véhicule, dans la fiche du véhicule. */
export interface VehicleDossierBooking {
  booking_id: number;
  reference: string;
  client_name: string | null;
  start_date: string | null;
  end_date: string | null;
  guests: number;
  amount_xof: number | null;
  status: string;
}

/**
 * Un départ programmé porté par un véhicule, dans sa fiche.
 *
 * `is_upcoming` sépare ce qui est **engagé** (départs à venir, qu'une
 * suspension mettrait par terre) de l'historique.
 */
export interface VehicleDossierTrip {
  id: number;
  reference: string;
  departure: string;
  destination: string;
  departure_at: string | null;
  capacity: number;
  seats_taken: number;
  seats_left: number;
  status: string | null;
  status_label: string | null;
  is_upcoming: boolean;
}

/** Fiche d'un véhicule (F8.2.b) — `GET /admin/vehicles/{id}`. */
export interface VehicleDossier {
  vehicle: AdminVehicle;
  /** Galerie complète (lecture : la modération reste au dossier de validation). */
  media: QueueMedia;
  bookings: VehicleDossierBooking[];
  trips: VehicleDossierTrip[];
  activity: AccountActivity[];
}

/**
 * Un passager d'un départ programmé (fiche trajet, F8.2.b).
 *
 * Les réservations annulées sont **listées** (`is_cancelled`) sans compter dans
 * le remplissage : une annulation de la veille explique un départ à moitié vide.
 */
export interface TripDossierPassenger {
  booking_id: number;
  reference: string;
  client_name: string | null;
  client_email: string | null;
  client_phone: string | null;
  guests: number;
  amount_xof: number | null;
  paid_xof: number;
  remaining_xof: number;
  status: string;
  is_cancelled: boolean;
  created_at: string | null;
}

/** Fiche d'un départ programmé (F8.2.b) — `GET /admin/mobility-services/{id}`. */
export interface TripDossier {
  trip: AdminMobilityService;
  passengers: TripDossierPassenger[];
  activity: AccountActivity[];
}

// --- Fiches Tourisme (F8.2.c) -----------------------------------------------

/**
 * Un participant à un circuit (fiche circuit, F8.2.c).
 *
 * Comme pour un départ de mobilité, les réservations annulées restent listées
 * (`is_cancelled`) sans compter dans le remplissage.
 */
export interface CircuitParticipant {
  booking_id: number;
  reference: string;
  client_name: string | null;
  client_email: string | null;
  client_phone: string | null;
  guests: number;
  start_date: string | null;
  amount_xof: number | null;
  paid_xof: number;
  remaining_xof: number;
  status: string;
  is_cancelled: boolean;
}

/** Fiche d'un circuit (F8.2.c) — `GET /admin/experiences/{id}`. */
export interface CircuitDossier {
  experience: AdminExperience;
  media: QueueMedia;
  participants: CircuitParticipant[];
  activity: AccountActivity[];
}

/** Un avis reçu par un prestataire, en clair (fiche partenaire, F8.2.c). */
export interface PartnerReview {
  id: number;
  rating: number;
  comment: string | null;
  author_name: string | null;
  status: string | null;
  created_at: string | null;
}

/**
 * Fiche d'un partenaire (F8.2.c) — `GET /admin/providers/{id}`.
 *
 * `account` est le compte utilisateur derrière l'enseigne : c'est lui qu'on
 * appelle quand la note se dégrade.
 */
export interface PartnerDossier {
  provider: Provider;
  account: QueueOwner | null;
  reviews: PartnerReview[];
  activity: AccountActivity[];
}

/**
 * Couverture touristique d'une destination (miroir de
 * `GET /admin/tourism/destinations` — F7.2.k).
 *
 * Les destinations ne sont **pas** une entité en base : c'est une colonne de
 * `tourism_experiences`, restituée ici par agrégation. D'où l'absence d'`id` —
 * la clé métier est le libellé lui-même.
 */
export interface TourismDestination {
  destination: string;
  circuits_count: number;
  published_count: number;
  pending_count: number;
  capacity_total: number;
  seats_taken: number;
  seats_left: number;
  price_min: number;
  price_max: number;
}

// --- Nuitées / exploitation (F7.2.c) ----------------------------------------

/** Statut du ménage d'une nuitée (miroir de `HousekeepingStatus`). */
export type HousekeepingStatus = 'a_faire' | 'en_cours' | 'fait';

/** Une réservation de nuitée dans le calendrier d'exploitation. */
/**
 * Sort de la caution d'une réservation (miroir de `CautionStatus`) — F7.3.f.
 * Transversal : la location de véhicule l'utilise depuis B7.4.
 */
export type CautionStatus = 'retenue' | 'restituee' | 'perdue';

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
  /** Montant de la caution demandée par le logement (0 si aucune). */
  caution_xof: number | null;
  /** `null` = pas de caution ; sinon retenue → restituée | perdue (F7.3.f). */
  caution_status: CautionStatus | null;
}

/** Résumé renvoyé après une transition (check-in/out, ménage, caution) — partiel. */
export interface StayBookingSummary {
  booking_id: number;
  reference: string;
  status: string;
  checked_in_at: string | null;
  checked_out_at: string | null;
  housekeeping_status: HousekeepingStatus | null;
  caution_xof: number | null;
  caution_status: CautionStatus | null;
}

/**
 * Champs corrigeables d'un bien depuis le back-office (F7.3.g).
 *
 * Sous-ensemble volontaire de ce que le propriétaire peut éditer : ce que
 * l'équipe corrige en pratique (intitulé public, prix, description). La
 * localisation, les médias et le reste restent au formulaire du propriétaire.
 * ⚠️ Le **statut** n'en fait pas partie — il relève de la file de validation et
 * de l'archivage, qui tracent chacun leur décision.
 */
export interface AdminPropertyPatch {
  title?: string;
  price_xof?: number | null;
  description?: string | null;
}

/**
 * Le séjour lui-même, dans sa fiche (F8.2.a) — `GET /admin/stay-bookings/{id}`.
 *
 * Sur-ensemble de `StayBooking` : la ligne de calendrier ne portait ni l'argent,
 * ni les horodatages de création/annulation, que la fiche affiche.
 */
export interface StayDossierBooking {
  booking_id: number;
  reference: string;
  status: string;
  start_date: string | null;
  end_date: string | null;
  /** Nombre de nuits, déduit des bornes par le serveur (unité de facturation). */
  nights: number | null;
  guests: number;
  amount_xof: number | null;
  commission_xof: number | null;
  /** Total réellement encaissé (acomptes compris). */
  paid_xof: number;
  /** Ce qu'il reste à verser (0 si soldé). */
  remaining_xof: number;
  created_at: string | null;
  cancelled_at: string | null;
  checked_in_at: string | null;
  checked_out_at: string | null;
  housekeeping_status: HousekeepingStatus | null;
  caution_xof: number | null;
  caution_status: CautionStatus | null;
}

/** Le logement réservé et son hôte, dans la fiche de séjour (F8.2.a). */
export interface StayDossierStay {
  stay_id: number;
  property_id: number | null;
  property_title: string | null;
  property_type: string | null;
  address: string | null;
  commune: string | null;
  department: string | null;
  region: string | null;
  capacity: number | null;
  price_per_night_xof: number | null;
  check_in_time: string | null;
  check_out_time: string | null;
  is_active: boolean;
  /** Le propriétaire du bien — joignable depuis la fiche. */
  host: QueueOwner | null;
  /** Galerie du bien (lecture : la modération se fait au dossier de validation). */
  media: QueueMedia | null;
}

/** Un règlement rattaché au séjour (acompte, solde, remboursement). */
export interface StayDossierPayment {
  id: number;
  reference: string;
  amount_xof: number;
  kind: string | null;
  kind_label: string | null;
  status: string;
  status_label: string;
  mode: string | null;
  provider: string | null;
  created_at: string | null;
}

/**
 * Dossier complet d'un séjour (F8.2.a) — `GET /admin/stay-bookings/{id}`.
 *
 * `stay` vaut `null` quand le bien a été retiré depuis : le séjour a bien eu
 * lieu, sa fiche reste consultable.
 */
export interface StayDossier {
  booking: StayDossierBooking;
  client: QueueOwner | null;
  stay: StayDossierStay | null;
  payments: StayDossierPayment[];
  /** Journal d'audit du séjour (motif d'une caution conservée, notamment). */
  activity: AccountActivity[];
}

/** Filtres du calendrier des nuitées (bornes sur la date d'arrivée). */
export interface StayCalendarQuery {
  from?: string;
  to?: string;
  page?: number;
}

// --- Fiche paiement (F8.2.d) ------------------------------------------------

/**
 * La transaction, dans sa fiche — `GET /admin/payments/{id}`.
 *
 * Sur-ensemble de `Payment` : porte les **éléments de preuve** que la Resource
 * publique n'expose pas (elle sert aussi l'espace client) et les deux drapeaux
 * d'action calculés par le serveur.
 */
export interface PaymentDossierPayment {
  id: number;
  reference: string;
  booking_id: number | null;
  amount_xof: number;
  commission_xof: number | null;
  kind: string | null;
  kind_label: string | null;
  status: string;
  status_label: string;
  mode: string | null;
  provider: string | null;
  created_at: string | null;
  updated_at: string | null;
  /** Référence de la transaction chez le PSP. */
  provider_reference: string | null;
  /** Signature du webhook PSP vérifiée : sans elle, l'encaissement n'est pas prouvé. */
  signature_verified: boolean;
  /** Référence Wave/OM saisie lors d'une confirmation manuelle (preuve). */
  manual_proof_reference: string | null;
  /** Montant déjà remboursé, s'il y en a un. */
  refunded_amount_xof: number | null;
  /**
   * Ce que l'API accepterait, décidé par le SERVEUR. L'écran n'a pas à
   * réinventer ces règles : il affiche les boutons que l'API honorerait.
   */
  can_confirm: boolean;
  can_refund: boolean;
}

/** La réservation payée, dans la fiche du paiement. */
export interface PaymentDossierBooking {
  id: number;
  reference: string;
  /** Nom court du modèle réservé (`Stay`, `Vehicle`, `TourismExperience`…). */
  resource_type: string;
  resource_label: string;
  start_date: string | null;
  end_date: string | null;
  guests: number;
  status: string;
  amount_xof: number | null;
  paid_xof: number;
  remaining_xof: number;
  client: QueueOwner | null;
}

/** Un règlement de la même réservation (échéancier). */
export interface PaymentDossierSibling {
  id: number;
  reference: string;
  amount_xof: number;
  kind_label: string | null;
  status: string;
  status_label: string;
  mode: string | null;
  created_at: string | null;
  /** Vrai pour le règlement dont on consulte la fiche. */
  is_current: boolean;
}

/** Dossier complet d'un règlement (F8.2.d) — `GET /admin/payments/{id}`. */
export interface PaymentDossier {
  payment: PaymentDossierPayment;
  booking: PaymentDossierBooking | null;
  /** Tous les règlements de la réservation, celui-ci compris. */
  siblings: PaymentDossierSibling[];
  activity: AccountActivity[];
}

// --- Fiche avis (F8.2.d) ----------------------------------------------------

/** Un avis publié sur la même ressource (contexte de modération). */
export interface ReviewContextEntry {
  id: number;
  rating: number;
  comment: string | null;
  author_name: string | null;
  created_at: string | null;
}

/**
 * Dossier d'un avis (F8.2.d) — `GET /admin/reviews/{id}`.
 *
 * `context` est ce qui manque à la file pour trancher : une plainte isolée au
 * milieu de quinze avis à cinq étoiles n'est pas un signal, la troisième
 * plainte identique du mois en est un.
 */
export interface ReviewDossier {
  review: {
    id: number;
    reference: string | null;
    rating: number;
    comment: string | null;
    status: string | null;
    status_label: string | null;
    created_at: string | null;
    author: QueueOwner | null;
  };
  resource: {
    type: string;
    label: string;
    id: number;
    /** Un avis sur un prestataire ouvre sa fiche : c'est là que la sanction se décide. */
    is_provider: boolean;
  };
  context: {
    published_count: number;
    average: number | null;
    negative_count: number;
    reviews: ReviewContextEntry[];
  };
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

// --- Export comptable (F7.3.d) ----------------------------------------------

/**
 * Totaux consolidés de la période (miroir de `AccountingReporter::report`).
 *
 * ⚠️ Les montants n'agrègent que les réservations **non annulées** : d'où l'écart
 * volontaire entre `bookings_count` (toutes les lignes du grand livre) et
 * `active_bookings_count` (celles qui portent les montants).
 */
export interface AccountingSummary {
  bookings_count: number;
  active_bookings_count: number;
  gross_volume_xof: number;
  commission_xof: number;
  payouts_count: number;
  payouts_total_xof: number;
}

/** Une ligne du grand livre des réservations. */
export interface AccountingBookingLine {
  reference: string;
  date: string | null;
  /** Nom court du modèle réservé (`Stay`, `Vehicle`, `Experience`, `Trip`…). */
  type: string;
  amount_xof: number;
  commission_xof: number;
  status: string;
}

/** Un reversement propriétaire effectué sur la période. */
export interface AccountingPayoutLine {
  reference: string;
  paid_at: string | null;
  owner_id: number;
  period_label: string | null;
  amount_xof: number;
}

/** Rapport comptable complet renvoyé en JSON. */
export interface AccountingReport {
  period: { from: string | null; to: string | null };
  summary: AccountingSummary;
  bookings: AccountingBookingLine[];
  payouts: AccountingPayoutLine[];
}

/** Bornes de période de l'export (facultatives : absentes = pas de borne). */
export interface AccountingQuery {
  /** Date de début incluse, au format `YYYY-MM-DD`. */
  from?: string;
  /** Date de fin incluse, au format `YYYY-MM-DD`. */
  to?: string;
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
  /** Date de dépôt de la demande. */
  created_at?: string | null;
  /** Le demandeur (nom + contact) — exposé depuis F7.3.b, liste ET fiche. */
  client?: { id: number; name: string; email: string | null; phone: string | null } | null;
  /** Jalons du chantier, triés par position — présents sur la FICHE seulement. */
  milestones?: ConstructionMilestone[];
}

/** Un jalon de chantier (miroir de `ConstructionMilestoneResource`). */
export interface ConstructionMilestone {
  id: number;
  name: string;
  position: number;
  status: string | null;
  status_label: string | null;
  planned_date: string | null;
  actual_date: string | null;
}

/** Un lot (corps d'état) d'un devis de chantier — miroir de `ConstructionLot`. */
export type ConstructionLot =
  | 'etudes'
  | 'terrassement'
  | 'fondations'
  | 'gros_oeuvre'
  | 'charpente_couverture'
  | 'menuiserie'
  | 'plomberie'
  | 'electricite'
  | 'finitions'
  | 'amenagements_exterieurs'
  | 'main_oeuvre'
  | 'divers';

/**
 * Une ligne de devis, telle que FIGÉE par le serveur à la composition (F7.3.e2).
 * Le libellé du lot y est inclus : un devis est un document, pas une vue
 * recalculée à l'affichage.
 */
export interface ConstructionQuoteLine {
  lot: ConstructionLot;
  lot_label: string;
  label: string;
  unit: string | null;
  quantity: number;
  unit_price_xof: number;
  amount_xof: number;
}

/** Un devis de chantier (miroir de `ConstructionQuoteResource`). */
export interface ConstructionQuote {
  id: number;
  reference: string;
  construction_request_id: number;
  lines: ConstructionQuoteLine[];
  subtotal_xof: number;
  margin_rate: number;
  margin_xof: number;
  total_xof: number;
  valid_until: string | null;
  status: 'brouillon' | 'envoye' | 'accepte' | 'refuse' | null;
  status_label: string | null;
  sent_at: string | null;
  accepted_at: string | null;
  created_at: string | null;
  author?: { id: number | null; name: string | null };
}

/** Ligne saisie à la composition (le serveur calcule le montant et l'ordre). */
export interface ComposeQuoteLine {
  lot: ConstructionLot;
  label?: string;
  unit?: string;
  quantity: number;
  unit_price_xof: number;
}

/** Corps d'affectation d'un prestataire BTP à un lot du chantier (F7.3.e3). */
export interface AssignConstructionProviderPayload {
  provider_id: number;
  lot: ConstructionLot;
  amount_xof: number;
  title?: string;
  scheduled_at?: string;
}

/** Corps de la composition d'un devis de chantier. */
export interface ComposeConstructionQuotePayload {
  lines: ComposeQuoteLine[];
  /** Omis → le réglage `build.margin_rate` du back-office s'applique. */
  margin_rate?: number;
  valid_until?: string;
}

/**
 * Champs modifiables d'un jalon (F7.3.e1). Tous facultatifs : le même type sert
 * l'ajout (où seul `name` est requis côté serveur) et le pilotage, qui exige au
 * moins un champ — un PATCH vide est refusé (422).
 */
export interface MilestonePayload {
  name?: string;
  status?: 'a_venir' | 'en_cours' | 'termine';
  position?: number;
  planned_date?: string | null;
  actual_date?: string | null;
}

/**
 * Un compte rendu de chantier (miroir de `ReportResource`).
 *
 * Le modèle `Report` est **polymorphe** et partagé avec la diaspora (B5/B8) :
 * même forme, même formulaire de dépôt.
 */
export interface ConstructionReport {
  id: number;
  reference: string;
  type: string | null;
  type_label: string | null;
  photos: string[];
  video_url: string | null;
  comment: string | null;
  reported_at: string | null;
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
  /** Clauses du mandat = les « contrats » du CDC. Présent sur la fiche (F7.3.a). */
  terms?: string | null;
  summary: MandateSummary;
  /** Bien géré (titre, localisation, propriétaire) — réutilise le modèle Property. */
  property: Property;
  /** Compteurs bruts de supervision (surfacés par le contrôleur admin). */
  rents_count?: number;
  incidents_count?: number;
  expenses_count?: number;
  payouts_count?: number;
  // --- Lignes détaillées : présentes UNIQUEMENT sur la fiche (F7.3.a), --------
  // bornées aux 12 dernières côté serveur.
  rents?: MandateRent[];
  payouts?: MandatePayout[];
  incidents?: MandateIncident[];
  expenses?: MandateExpense[];
}

/** Une échéance de loyer (miroir de `RentResource`). */
export interface MandateRent {
  id: number;
  mandate_id: number;
  tenant_id: number | null;
  tenant_name: string | null;
  period_label: string | null;
  due_date: string | null;
  amount_xof: number;
  status: string | null;
  status_label: string | null;
  paid_at: string | null;
}

/** Un reversement au propriétaire (miroir de `OwnerPayoutResource`). */
export interface MandatePayout {
  id: number;
  reference: string;
  mandate_id: number;
  owner_id: number;
  period_label: string | null;
  amount_xof: number;
  status: string | null;
  status_label: string | null;
  paid_at: string | null;
}

/** Un incident sur le bien géré (miroir de `IncidentResource`). */
export interface MandateIncident {
  id: number;
  reference: string;
  property_id: number;
  reported_by: number | null;
  title: string;
  description: string | null;
  priority: string | null;
  status: string | null;
  status_label: string | null;
  resolved_at: string | null;
}

/** Une dépense engagée sur le bien géré (miroir de `ExpenseResource`). */
export interface MandateExpense {
  id: number;
  property_id: number;
  incident_id: number | null;
  label: string;
  category: string | null;
  category_label: string | null;
  amount_xof: number;
  spent_at: string | null;
}

/** Corps de création d'une échéance de loyer (`StoreRentRequest`). */
export interface RentPayload {
  due_date: string;
  amount_xof: number;
  tenant_name?: string | null;
  period_label?: string | null;
}

/** Corps de signalement d'un incident (`StoreIncidentRequest`). */
export interface IncidentPayload {
  title: string;
  description?: string | null;
  priority?: string | null;
}

/** Corps d'enregistrement d'une dépense (`StoreExpenseRequest`). */
export interface ExpensePayload {
  label: string;
  category: string;
  amount_xof: number;
  spent_at: string;
  /** Dépense rattachée à un incident précis (facultatif). */
  incident_id?: number | null;
}

/** Corps de création d'un reversement (`StorePayoutRequest`). */
export interface PayoutPayload {
  amount_xof: number;
  period_label?: string | null;
}

/**
 * Rapport mensuel d'un mandat — miroir exact de
 * `ManagementReportService::forMandate()`.
 *
 * ⚠️ `net_owner_xof` = loyers encaissés − commission Kaikun − dépenses du mois.
 * Il peut donc être NÉGATIF (mois de gros travaux) : l'écran doit le montrer
 * tel quel, pas l'écrêter à zéro.
 */
export interface MandateReport {
  mandate: { id: number; reference: string; commission_rate: number | string };
  period: { month: string; label: string };
  rents: { paid_xof: number; unpaid_xof: number; count: number };
  expenses: { total_xof: number; count: number };
  commission_xof: number;
  net_owner_xof: number;
  payouts: { total_xof: number; count: number };
  incidents: { opened: number; resolved: number };
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

// --- File de traitement des demandes (F8.9) ---------------------------------

/** Une étape atteignable depuis le statut courant (machine à états serveur). */
export interface RequestTransition {
  value: string;
  label: string;
}

/**
 * Une demande client dans la file de traitement du back-office
 * (miroir de `AdminServiceRequestResource`).
 *
 * ⚠️ Distincte de la demande vue par le client (`ServiceRequest` de
 * `RequestsService`) : celle-ci porte le **demandeur** (identité + contact),
 * que la ressource publique n'expose pas.
 */
export interface RequestQueueEntry {
  id: number;
  reference: string;
  service_type: string | null;
  service_type_label: string | null;
  message: string | null;
  budget_xof: number | null;
  city: string | null;
  status: string | null;
  status_label: string | null;
  priority: string | null;
  priority_label: string | null;
  created_at: string | null;
  updated_at: string | null;
  /**
   * Les seules étapes que le serveur acceptera. L'écran n'invente aucun
   * bouton : proposer une transition refusée en 422 serait un faux espoir.
   */
  allowed_transitions: RequestTransition[];
  requester?: {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    city: string | null;
  } | null;
  quotes_count?: number;
}

/** Un devis rattaché à une demande (miroir de `QuoteResource`). */
export interface RequestQuote {
  id: number;
  reference: string;
  request_id: number;
  amount_xof: number | null;
  details: unknown[] | Record<string, unknown>;
  valid_until: string | null;
  status: string | null;
  status_label: string | null;
}

/** Fiche d'une demande (GET /admin/requests/{id}) : dossier + devis + historique. */
export interface RequestQueueDetail {
  request: RequestQueueEntry;
  quotes: RequestQuote[];
  activity: AccountActivity[];
}

/** Référentiels de filtrage, servis par les enums PHP. */
export interface RequestQueueFilters {
  statuses: RequestTransition[];
  service_types: RequestTransition[];
  priorities: RequestTransition[];
}

/** Filtres de la file de traitement. */
export interface RequestQueueQuery {
  status?: string;
  service_type?: string;
  priority?: string;
  search?: string;
  page?: number;
}


// --- Messagerie du support (F8.12) ------------------------------------------

/** Un participant d'un fil, vu du back-office. */
export interface SupportParticipant {
  id: number;
  name: string;
  role?: string | null;
  is_team?: boolean;
}

/** L'interlocuteur côté public : joignable, c'est le point de l'écran. */
export interface SupportRequester extends SupportParticipant {
  email: string | null;
  phone: string | null;
}

/** Un message du fil (miroir de `MessageResource`). */
export interface SupportMessage {
  id: number;
  body: string;
  sender: { id: number; name: string | null };
  is_mine: boolean;
  created_at: string | null;
}

/**
 * Un fil de support (miroir de `AdminConversationResource`).
 *
 * ⚠️ `awaiting_reply` est LE chiffre qui gouverne le travail : un fil dont le
 * dernier message vient du client n'est pas « lu », il est **dû**.
 */
export interface SupportThread {
  id: number;
  subject: string | null;
  context_label: string | null;
  context_type: string | null;
  context_id: number | null;
  is_closed: boolean;
  closed_at: string | null;
  last_message_at: string | null;
  created_at: string | null;
  assigned_agent: { id: number; name: string } | null;
  is_mine: boolean;
  requester: SupportRequester | null;
  others: SupportParticipant[];
  last_message: { body: string; from_team: boolean; created_at: string | null } | null;
  awaiting_reply: boolean;
  messages?: SupportMessage[];
}

/**
 * Une personne que l'agent peut faire entrer dans un fil (F8.12.c).
 *
 * `from_context` dit POURQUOI elle est proposée (« Bien immobilier »,
 * « Nuitée »…) : c'est la personne rattachée au dossier cité. Les résultats de
 * recherche, eux, n'ont pas d'origine.
 */
export interface SupportCandidate {
  id: number;
  name: string;
  email: string | null;
  role: string | null;
  from_context: string | null;
}

/** Candidats à l'ajout : la personne du dossier, puis la recherche. */
export interface SupportCandidates {
  dossier: SupportCandidate | null;
  results: SupportCandidate[];
}

/** Fiche d'un fil : l'échange + le vivier pour le sélecteur de réassignation. */
export interface SupportThreadDetail {
  conversation: SupportThread;
  agents: { id: number; name: string }[];
}

/** Filtres de la boîte de réception. */
export interface SupportInboxQuery {
  /** `mine` (défaut) · `unassigned` · `all`. */
  scope?: 'mine' | 'unassigned' | 'all';
  /** `true` = l'archive des fils clos. */
  closed?: boolean;
  search?: string;
  page?: number;
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
  /**
   * Catégories de prestataires, séparées par des virgules
   * (`guide,restauration`). — F7.2.k
   *
   * C'est le mécanisme par lequel l'écran Tourisme restitue les **guides** et
   * les **restaurants** du cahier des charges : ce ne sont pas des entités du
   * module Explore mais des catégories de la marketplace Pro.
   */
  category?: string;
  page?: number;
}

/**
 * Familles de pièces exposées par la vue documentaire transverse
 * (miroir de `AdminDocumentController::TYPES`).
 */
export type DocumentType =
  | 'kyc'
  | 'property'
  | 'certification'
  | 'payout_proof'
  // F7.4.c — les deux familles qui manquaient à la ligne CDC §6 « Documents »
  // (« Mandats, contrats, … rapports ») : elles existaient en base sans être
  // rattachées à la vue documentaire.
  | 'mandate'
  | 'report';

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

// --- Team building (F7.2.h) --------------------------------------------------

/** Statut d'une demande de team building (miroir de `TeamBuildingRequestStatus`). */
export type TeamBuildingStatus = 'nouveau' | 'en_etude' | 'devis_envoye' | 'accepte' | 'annule';

/** Statut d'un devis team building (miroir de `TeamBuildingQuoteStatus`). */
export type TeamBuildingQuoteStatus = 'brouillon' | 'envoye' | 'accepte' | 'refuse';

/**
 * Brique du pack couverte par une ligne de devis / une affectation prestataire
 * (miroir de `QuoteLineCategory`). Chaque catégorie renvoie à un module fournisseur.
 */
export type PackCategory = 'lieu' | 'hebergement' | 'restauration' | 'activite' | 'mobilite' | 'animation';

/** Statut d'une mission prestataire affectée (miroir de `MissionStatus`). */
export type MissionStatus = 'affectee' | 'acceptee' | 'en_cours' | 'terminee' | 'refusee' | 'annulee';

/** Une ligne de devis composée (brique du pack). */
export interface QuoteLine {
  category: PackCategory;
  label?: string | null;
  module?: string | null;
  quantity: number;
  unit_price_xof: number;
  /** Total de la ligne (quantité × PU), calculé côté serveur. */
  line_total_xof?: number;
}

/** Composant d'entrée pour composer un devis (POST .../quotes). */
export interface QuoteComponent {
  category: PackCategory;
  label?: string;
  module?: string;
  quantity: number;
  unit_price_xof: number;
}

/** Un devis team building (miroir de `TeamBuildingQuoteResource`). */
export interface TeamBuildingQuote {
  id: number;
  reference: string;
  request_id: number;
  lines: QuoteLine[];
  subtotal_xof: number;
  margin_rate: number | string | null;
  margin_xof: number;
  total_xof: number;
  status: TeamBuildingQuoteStatus | null;
  status_label: string | null;
  sent_at: string | null;
  accepted_at: string | null;
}

/** L'entreprise à l'origine d'une demande (bloc `company` de la resource). */
export interface TeamBuildingCompany {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
}

/**
 * Une mission prestataire affectée à une demande TB (miroir de
 * `ProviderMissionResource` rattaché) — réalise l'« affectation prestataires ».
 */
export interface ProviderMissionItem {
  id: number;
  reference: string;
  provider_id: number;
  team_building_request_id: number | null;
  /** Chantier d'origine (F7.3.e3) : null pour une mission ordinaire ou TB. */
  construction_request_id: number | null;
  /**
   * ⚠️ Colonne PARTAGÉE côté serveur : une brique de pack pour une mission team
   * building, un **lot BTP** pour une mission de chantier. C'est la clé étrangère
   * renseignée (`team_building_request_id` / `construction_request_id`) qui dit
   * lequel des deux vocabulaires lire.
   */
  category: PackCategory | ConstructionLot | null;
  title: string;
  description: string | null;
  amount_xof: number;
  commission_xof: number;
  net_xof: number;
  status: MissionStatus | null;
  status_label: string | null;
  scheduled_at: string | null;
  /** Prestataire affecté (nom commercial…), chargé dans la fiche TB. */
  provider?: {
    id: number;
    business_name: string;
    category_label: string | null;
    status: string | null;
  };
}

/** Une demande de team building en file back-office (miroir de `TeamBuildingRequestResource`). */
export interface TeamBuildingRequestItem {
  id: number;
  reference: string;
  participants: number;
  city: string | null;
  start_date: string | null;
  end_date: string | null;
  budget_xof: number | null;
  /** Besoins déclarés (map clé→valeur, ex. { hebergement: true }). */
  needs: Record<string, unknown>;
  description: string | null;
  status: TeamBuildingStatus | null;
  status_label: string | null;
  quotes_count?: number;
  company?: TeamBuildingCompany;
}

/** Fiche détaillée d'une demande TB : la demande + devis + prestataires affectés. */
export interface TeamBuildingRequestDetail extends TeamBuildingRequestItem {
  quotes: TeamBuildingQuote[];
  provider_missions: ProviderMissionItem[];
}

/** Filtres de la file des demandes de team building. */
export interface TeamBuildingQuery {
  status?: string;
  q?: string;
  page?: number;
}

/** Corps d'affectation d'un prestataire à une demande (POST .../assignments). */
export interface AssignProviderPayload {
  provider_id: number;
  category: PackCategory;
  title?: string;
  amount_xof: number;
  scheduled_at?: string;
}

// --- Diaspora (F7.2.i) -------------------------------------------------------

/** Statut d'un projet diaspora (miroir de `DiasporaProjectStatus`). */
export type DiasporaStatus = 'nouveau' | 'en_cours' | 'termine' | 'annule';

/** Priorité d'un dossier diaspora (miroir de `DiasporaPriority`). */
export type DiasporaPriority = 'normale' | 'haute' | 'strategique';

/** Nature d'un projet diaspora (miroir de `DiasporaProjectType`). */
export type DiasporaProjectType = 'achat' | 'construction' | 'gestion_locative';

/** Nature d'un rapport de suivi (miroir de `ReportType`, module Build). */
export type ReportType = 'photo' | 'video' | 'mixte';

/** Un rapport de suivi (miroir de `ReportResource`). */
export interface DiasporaReport {
  id: number;
  reference: string;
  type: ReportType | null;
  type_label: string | null;
  photos: string[];
  video_url: string | null;
  comment: string | null;
  reported_at: string | null;
}

/** Un projet diaspora en supervision (miroir de `DiasporaProjectResource`). */
export interface DiasporaProject {
  id: number;
  reference: string;
  project_type: DiasporaProjectType | null;
  project_type_label: string | null;
  residence_country: string | null;
  budget_xof: number | null;
  description: string | null;
  priority: DiasporaPriority | null;
  priority_label: string | null;
  status: DiasporaStatus | null;
  status_label: string | null;
  agent_id: number | null;
  reports_count?: number;
  /** Client à l'origine du dossier (chargé côté back-office). */
  client?: { id: number; name: string; email: string | null; phone: string | null };
  /** Agent dédié affecté (null tant que non affecté). */
  agent?: { id: number; name: string; email: string | null } | null;
}

/** Filtres de la file des dossiers diaspora. */
export interface DiasporaQuery {
  status?: string;
  priority?: string;
  q?: string;
  page?: number;
}

/** Corps de pilotage d'un dossier (PATCH /diaspora-projects/{id}). Au moins l'un des deux. */
export interface UpdateDiasporaPayload {
  status?: DiasporaStatus;
  priority?: DiasporaPriority;
}

/** Corps d'affectation d'un agent (PATCH …/assign). `agent_id` absent = auto (moins chargé). */
export interface AssignAgentPayload {
  agent_id?: number;
  priority?: DiasporaPriority;
}

/** Corps d'ajout d'un rapport de suivi (POST …/reports). */
export interface CreateReportPayload {
  type: ReportType;
  reported_at: string;
  comment?: string;
  video_url?: string;
  photos?: string[];
}

// --- Paramètres & contenu (F7.2.l) -------------------------------------------

/** Type logique d'un réglage global (miroir de `SettingsRepository::DEFAULTS`). */
export type SettingType = 'string' | 'float' | 'integer' | 'boolean' | 'json';

/** Un réglage de la plateforme, avec sa valeur EFFECTIVE (défaut ou surcharge). */
export interface PlatformSetting {
  key: string;
  value: unknown;
  type: SettingType;
  /** Regroupement d'affichage : general / commissions / construction / notifications. */
  group: string | null;
  /** `true` si une surcharge est enregistrée en base (sinon = valeur par défaut du code). */
  overridden: boolean;
}

/** Un événement notifiable pilotable depuis les paramètres (miroir de `NotificationEvent`). */
export interface NotificationEventOption {
  value: string;
  label: string;
  description: string;
  /** « Clients & partenaires » ou « Équipe Kaikun » — sert à grouper les interrupteurs. */
  audience: string;
  enabled: boolean;
}

/** Réponse de `GET /admin/settings` : réglages + catalogue des événements. */
export interface SettingsSnapshot {
  settings: PlatformSetting[];
  notification_events: NotificationEventOption[];
}

/** Une entrée de FAQ (miroir de `FaqResource`). */
export interface FaqEntry {
  id: number;
  question: string;
  answer: string;
  category: string | null;
  position: number | null;
  is_published: boolean;
  updated_at: string | null;
}

/** Corps de création / mise à jour d'une entrée de FAQ. */
export interface FaqPayload {
  question?: string;
  answer?: string;
  category?: string | null;
  position?: number;
  is_published?: boolean;
}

/** Une page de contenu éditorial (miroir de `PageResource`). */
export interface ContentPage {
  id: number;
  slug: string;
  title: string;
  body: string;
  is_published: boolean;
  updated_at: string | null;
}

/** Corps de création / mise à jour d'une page. */
export interface PagePayload {
  slug?: string;
  title?: string;
  body?: string;
  is_published?: boolean;
}

/** Un département dans l'arborescence géographique. */
export interface GeoDepartment {
  id: number;
  region_id: number;
  name: string;
  communes_count: number;
}

/** Une région et ses départements (`GET /admin/geography`). */
export interface GeoRegion {
  id: number;
  name: string;
  departments_count: number;
  communes_count: number;
  departments: GeoDepartment[];
}

/** Une commune administrable, avec son usage réel (`GET /admin/communes`). */
export interface AdminCommune {
  id: number;
  name: string;
  type: string | null;
  department_id: number;
  department_name: string | null;
  region_id: number | null;
  region_name: string | null;
  /** Biens localisés dans cette commune. */
  properties_count: number;
  /** Comptes utilisateurs la déclarant. */
  users_count: number;
  /** Calculé côté serveur : `false` dès qu'un objet y est rattaché. */
  deletable: boolean;
}

/** Filtres de la liste des communes. */
export interface CommuneQuery {
  department_id?: number;
  region_id?: number;
  q?: string;
  page?: number;
}

/** Corps de création / mise à jour d'une commune. */
export interface CommunePayload {
  department_id?: number;
  name?: string;
  type?: string | null;
}

/** Corps de création / mise à jour d'un département. */
export interface DepartmentPayload {
  region_id?: number;
  name?: string;
}

/** Une valeur de nomenclature (catégorie) en lecture seule. */
export interface ReferenceItem {
  value: string;
  label: string;
}

/**
 * Nomenclatures de référence (`GET /admin/reference`).
 *
 * ⚠️ Les catégories sont des **enums PHP** côté serveur : elles pilotent la
 * logique métier (validation, commissions, filtres) et ne sont donc PAS
 * éditables — l'écran Paramètres les affiche en lecture seule et le signale.
 */
export interface ReferenceCatalog {
  categories: {
    provider: ReferenceItem[];
    property_type: ReferenceItem[];
    service_type: ReferenceItem[];
    vehicle_type: ReferenceItem[];
  };
  geography: {
    regions: { id: number; name: string }[];
  };
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
   * Dossier complet d'un élément à valider (F8.1).
   * GET /admin/queue/{type}/{id}
   *
   * Renvoie la galerie ENTIÈRE (médias masqués compris) et les caractéristiques
   * du type : c'est l'écran où l'agent vérifie ce qu'il s'apprête à publier sur
   * le site vitrine. Consultable même après décision, pour revoir son geste.
   */
  validationDetail(type: ValidationType, id: number): Observable<QueueDetailResponse> {
    return this.http
      .get<ApiEnvelope<QueueDetailResponse>>(`${this.api}/admin/queue/${type}/${id}`)
      .pipe(map((response) => response.data));
  }

  /**
   * Masque ou réaffiche un média (F8.1).
   * PATCH /admin/media/{id}/status
   *
   * Permet d'écarter une photo floue ou hors sujet et de publier le reste de
   * l'annonce, plutôt que de tout refuser et renvoyer le déposant à zéro. Le
   * média n'est pas supprimé : il sort des annonces publiques. L'API exige la
   * permission de validation du type parent → 403 sans mandat.
   */
  moderateMedia(id: number, hidden: boolean): Observable<QueueMediaItem> {
    return this.http
      .patch<ApiEnvelope<{ media: QueueMediaItem }>>(`${this.api}/admin/media/${id}/status`, {
        status: hidden ? 'masque' : 'actif',
      })
      .pipe(map((response) => response.data.media));
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

  // --- Correction & archivage d'un bien (F7.3.g) ------------------------------
  //
  // Dette CDC §15 « un admin peut modifier » : valider et publier existaient
  // depuis B2.4, corriger et archiver n'avaient aucune route back-office.
  // Garde `valider:bien`. PAS de création ni de réattribution : le bien reste à
  // son propriétaire (périmètre arbitré).

  /** Corrige les champs d'un bien. PATCH /admin/properties/{id} */
  adminUpdateProperty(id: number, payload: AdminPropertyPatch): Observable<Property> {
    return this.http
      .patch<ApiEnvelope<{ property: Property }>>(`${this.api}/admin/properties/${id}`, payload)
      .pipe(map((response) => response.data.property));
  }

  /** Sort une annonce du catalogue sans rien supprimer. PATCH …/archive */
  adminArchiveProperty(id: number, reason?: string): Observable<Property> {
    return this.http
      .patch<ApiEnvelope<{ property: Property }>>(`${this.api}/admin/properties/${id}/archive`, {
        reason,
      })
      .pipe(map((response) => response.data.property));
  }

  /**
   * Sort un bien de l'archive. PATCH …/restore
   *
   * ⚠️ Le serveur le renvoie **en attente de validation**, jamais directement
   * publié : un bien archivé pour contenu problématique ne doit pas revenir en
   * ligne d'un clic.
   */
  adminRestoreProperty(id: number): Observable<Property> {
    return this.http
      .patch<ApiEnvelope<{ property: Property }>>(`${this.api}/admin/properties/${id}/restore`, {})
      .pipe(map((response) => response.data.property));
  }

  // --- Catalogues (F7.2.b) ----------------------------------------------------

  /** Biens, TOUS statuts (supervision). GET /admin/properties */
  adminProperties(query: CatalogQuery = {}): Observable<Paginated<Property>> {
    return this.http.get<Paginated<Property>>(`${this.api}/admin/properties`, {
      params: this.catalogParams(query),
    });
  }

  /**
   * Véhicules, TOUS statuts (supervision). GET /admin/vehicles
   *
   * Sert l'écran Catalogues (F7.2.b) et l'onglet « Flotte » de l'écran Mobilité
   * (F7.2.j) : le format renvoyé est `AdminVehicle`, sur-ensemble de `Vehicle`
   * incluant les champs de conformité — Catalogues ignore simplement ceux
   * qu'il n'affiche pas.
   */
  adminVehicles(query: CatalogQuery = {}): Observable<Paginated<AdminVehicle>> {
    return this.http.get<Paginated<AdminVehicle>>(`${this.api}/admin/vehicles`, {
      params: this.catalogParams(query),
    });
  }

  /**
   * Trajets programmés, TOUS statuts, avec le remplissage de chaque départ.
   * GET /admin/mobility-services — onglet « Trajets » de l'écran Mobilité (F7.2.j).
   */
  adminMobilityServices(query: CatalogQuery = {}): Observable<Paginated<AdminMobilityService>> {
    return this.http.get<Paginated<AdminMobilityService>>(`${this.api}/admin/mobility-services`, {
      params: this.catalogParams(query),
    });
  }

  /**
   * Fiche d'un véhicule (F8.2.b). GET /admin/vehicles/{id}
   *
   * La liste dit qu'un véhicule n'est pas conforme ; la fiche dit ce qu'il
   * engage — locations en cours, départs programmés à venir — et qui joindre.
   */
  vehicleDossier(id: number): Observable<VehicleDossier> {
    return this.http
      .get<ApiEnvelope<VehicleDossier>>(`${this.api}/admin/vehicles/${id}`)
      .pipe(map((response) => response.data));
  }

  /**
   * Fiche d'un départ programmé (F8.2.b). GET /admin/mobility-services/{id}
   *
   * La liste donne le remplissage (« 12 / 15 ») ; la fiche donne **qui** sont
   * ces douze, avec de quoi les joindre et ce qu'ils doivent encore.
   */
  tripDossier(id: number): Observable<TripDossier> {
    return this.http
      .get<ApiEnvelope<TripDossier>>(`${this.api}/admin/mobility-services/${id}`)
      .pipe(map((response) => response.data));
  }

  /**
   * Circuits, TOUS statuts (supervision). GET /admin/experiences
   *
   * Sert l'écran Catalogues (F7.2.b) et l'onglet « Circuits » de l'écran
   * Tourisme (F7.2.k) : format `AdminExperience`, sur-ensemble d'`Experience`
   * avec le remplissage et le prestataire.
   */
  adminExperiences(query: CatalogQuery = {}): Observable<Paginated<AdminExperience>> {
    return this.http.get<Paginated<AdminExperience>>(`${this.api}/admin/experiences`, {
      params: this.catalogParams(query),
    });
  }

  /**
   * Fiche d'un circuit (F8.2.c). GET /admin/experiences/{id}
   *
   * Le tableau dit qu'un circuit est rempli à 12/15 ; la fiche dit **qui part**
   * et ce que le circuit promet (son programme).
   */
  circuitDossier(id: number): Observable<CircuitDossier> {
    return this.http
      .get<ApiEnvelope<CircuitDossier>>(`${this.api}/admin/experiences/${id}`)
      .pipe(map((response) => response.data));
  }

  /**
   * Fiche d'un partenaire (F8.2.c). GET /admin/providers/{id}
   *
   * Une note et un compteur d'avertissements ne suffisent pas à décider : la
   * fiche donne les avis en clair, les certifications et le motif des sanctions.
   * Garde serveur `valider:prestataire`.
   */
  partnerDossier(id: number): Observable<PartnerDossier> {
    return this.http
      .get<ApiEnvelope<PartnerDossier>>(`${this.api}/admin/providers/${id}`)
      .pipe(map((response) => response.data));
  }

  /**
   * Couverture par destination (agrégat, non paginé).
   * GET /admin/tourism/destinations — onglet « Destinations » (F7.2.k).
   */
  adminTourismDestinations(q?: string): Observable<TourismDestination[]> {
    let params = new HttpParams();
    if (q) params = params.set('q', q);
    return this.http
      .get<ApiEnvelope<{ destinations: TourismDestination[] }>>(
        `${this.api}/admin/tourism/destinations`,
        { params },
      )
      .pipe(map((r) => r.data.destinations));
  }

  /**
   * Construit les query params communs des catalogues (statut, recherche,
   * page) et ceux propres à la Mobilité (type, chauffeur, période).
   *
   * `driver` est un booléen : on teste explicitement `undefined` pour que
   * « sans chauffeur » (`false`) parte bien dans l'URL au lieu d'être ignoré.
   */
  private catalogParams(query: CatalogQuery): HttpParams {
    let params = new HttpParams();
    if (query.status) params = params.set('status', query.status);
    if (query.q) params = params.set('q', query.q);
    if (query.type) params = params.set('type', query.type);
    if (query.driver !== undefined) params = params.set('driver', query.driver ? '1' : '0');
    if (query.from) params = params.set('from', query.from);
    if (query.to) params = params.set('to', query.to);
    if (query.destination) params = params.set('destination', query.destination);
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

  /**
   * Dossier complet d'un séjour (F8.2.a). GET /admin/stay-bookings/{id}
   *
   * Le calendrier reste la vue d'exploitation ; cette fiche répond aux questions
   * qui obligeaient l'agent à changer d'écran (l'argent, le client, l'hôte, le
   * motif d'une caution conservée). Même garde serveur que la liste.
   */
  stayDossier(bookingId: number): Observable<StayDossier> {
    return this.http
      .get<ApiEnvelope<StayDossier>>(`${this.api}/admin/stay-bookings/${bookingId}`)
      .pipe(map((response) => response.data));
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

  /**
   * Tranche le sort de la caution après le départ.
   * PATCH /admin/stay-bookings/{id}/caution
   *
   * Le serveur exige un départ enregistré, une caution encore retenue, et un
   * **motif** pour la conserver (une caution perdue se justifie).
   */
  stayCaution(
    bookingId: number,
    status: 'restituee' | 'perdue',
    reason?: string,
  ): Observable<StayBookingSummary> {
    return this.http
      .patch<ApiEnvelope<{ booking: StayBookingSummary }>>(
        `${this.api}/admin/stay-bookings/${bookingId}/caution`,
        { status, reason },
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
   * Dossier complet d'un règlement (F8.2.d). GET /admin/payments/{id}
   *
   * L'écran le plus sensible du back-office : confirmer à tort crédite une
   * réservation jamais payée, rembourser à tort sort de l'argent réel. La fiche
   * transporte les **preuves** (référence PSP, signature vérifiée, preuve
   * Wave/OM), la réservation payée, l'**échéancier complet** et le journal.
   */
  paymentDossier(id: number): Observable<PaymentDossier> {
    return this.http
      .get<ApiEnvelope<PaymentDossier>>(`${this.api}/admin/payments/${id}`)
      .pipe(map((response) => response.data));
  }

  /**
   * Dossier d'un avis (F8.2.d). GET /admin/reviews/{id}
   *
   * Apporte le commentaire entier et surtout le **contexte** : les autres avis
   * publiés de la même ressource, sans lesquels modérer revient à trier.
   */
  reviewDossier(id: number): Observable<ReviewDossier> {
    return this.http
      .get<ApiEnvelope<ReviewDossier>>(`${this.api}/admin/reviews/${id}`)
      .pipe(map((response) => response.data));
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

  // --- Export comptable (F7.3.d) ---------------------------------------------

  /**
   * Rapport comptable consolidé de la période (affichage à l'écran).
   * GET /admin/reports/export (format JSON par défaut)
   *
   * Garde serveur : permission `gerer:paiements`.
   */
  accountingReport(query: AccountingQuery = {}): Observable<AccountingReport> {
    return this.http
      .get<ApiEnvelope<AccountingReport>>(`${this.api}/admin/reports/export`, {
        params: this.accountingParams(query),
      })
      .pipe(map((response) => response.data));
  }

  /**
   * Grand livre des réservations en CSV téléchargeable.
   * GET /admin/reports/export?format=csv
   *
   * ⚠️ Le CSV serveur ne contient que les **réservations** ; les reversements ne
   * sont lisibles qu'à l'écran (via {@link accountingReport}).
   */
  accountingCsv(query: AccountingQuery = {}): Observable<Blob> {
    return this.http.get(`${this.api}/admin/reports/export`, {
      params: this.accountingParams(query).set('format', 'csv'),
      responseType: 'blob',
    });
  }

  /** Bornes de période communes aux deux formats d'export. */
  private accountingParams(query: AccountingQuery): HttpParams {
    let params = new HttpParams();
    if (query.from) params = params.set('from', query.from);
    if (query.to) params = params.set('to', query.to);
    return params;
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


  // --- Messagerie du support (F8.12) -----------------------------------------
  //
  // ⚠️ Sans ces quatre appels, la messagerie n'existe pas côté équipe : depuis
  // F3.7, un client pouvait lire et répondre dans un fil, mais personne au
  // back-office n'avait de vue sur ces fils. Permission dédiée
  // `repondre:messages`, qui sert AUSSI de vivier d'assignation côté serveur.

  /** Boîte de réception (par défaut : mes fils ouverts). GET /admin/conversations */
  supportInbox(query: SupportInboxQuery = {}): Observable<Paginated<SupportThread>> {
    let params = new HttpParams();
    if (query.scope) params = params.set('scope', query.scope);
    if (query.closed) params = params.set('closed', '1');
    if (query.search) params = params.set('search', query.search);
    if (query.page) params = params.set('page', String(query.page));
    return this.http.get<Paginated<SupportThread>>(`${this.api}/admin/conversations`, { params });
  }

  /**
   * Fiche d'un fil (échange complet + vivier). GET /admin/conversations/{id}
   *
   * `afterId` : **relève périodique** (F8.12.a) — ne redemande que les messages
   * postérieurs à celui déjà affiché, et n'écrit pas le marquage de lecture pour
   * une relève à vide.
   */
  supportThread(id: number, afterId?: number): Observable<SupportThreadDetail> {
    return this.http
      .get<ApiEnvelope<SupportThreadDetail>>(`${this.api}/admin/conversations/${id}`, {
        params: afterId ? new HttpParams().set('after', String(afterId)) : undefined,
      })
      .pipe(map((response) => response.data));
  }

  /**
   * Répondre au client. POST /admin/conversations/{id}/messages
   *
   * ⚠️ Deux effets voulus côté serveur : répondre **prend** le dossier s'il
   * n'avait pas de responsable, et **rouvre** le fil s'il était clos.
   */
  replyToSupportThread(id: number, body: string): Observable<SupportThread> {
    return this.http
      .post<ApiEnvelope<{ conversation: SupportThread }>>(
        `${this.api}/admin/conversations/${id}/messages`,
        { body },
      )
      .pipe(map((response) => response.data.conversation));
  }

  /**
   * Piloter un fil : réassigner et/ou clore. PATCH /admin/conversations/{id}
   *
   * `assigned_agent_id: null` remet le fil dans « Non assignés ».
   */
  updateSupportThread(
    id: number,
    changes: { assigned_agent_id?: number | null; closed?: boolean },
  ): Observable<SupportThread> {
    return this.http
      .patch<ApiEnvelope<{ conversation: SupportThread }>>(
        `${this.api}/admin/conversations/${id}`,
        changes,
      )
      .pipe(map((response) => response.data.conversation));
  }

  /**
   * Qui peut entrer dans ce fil. GET /admin/conversations/{id}/candidates
   *
   * Sans `search`, on ne reçoit que la personne du dossier (le cas courant, en
   * un clic). Avec, une recherche **restreinte aux comptes propriétaire et
   * prestataire** — ajouter un client tiers dans la conversation d'un autre
   * client n'aurait aucun sens.
   */
  supportCandidates(id: number, search?: string): Observable<SupportCandidates> {
    return this.http
      .get<ApiEnvelope<SupportCandidates>>(`${this.api}/admin/conversations/${id}/candidates`, {
        params: search ? new HttpParams().set('search', search) : undefined,
      })
      .pipe(map((response) => response.data));
  }

  /**
   * Fait entrer un tiers dans le fil. POST /admin/conversations/{id}/participants
   *
   * ⚠️ Le tiers voit **tout l'historique** de la conversation, et il est
   * notifié. C'est un geste vers l'extérieur : l'écran l'annonce avant.
   */
  addSupportParticipant(id: number, userId: number): Observable<SupportThread> {
    return this.http
      .post<ApiEnvelope<{ conversation: SupportThread }>>(
        `${this.api}/admin/conversations/${id}/participants`,
        { user_id: userId },
      )
      .pipe(map((response) => response.data.conversation));
  }

  /**
   * Sort un tiers du fil. DELETE /admin/conversations/{id}/participants/{userId}
   *
   * Ses messages déjà écrits restent : on le sort de la suite de l'échange, on
   * ne réécrit pas l'histoire. Le demandeur et l'agent responsable, eux, ne
   * peuvent pas être retirés (422).
   */
  removeSupportParticipant(id: number, userId: number): Observable<SupportThread> {
    return this.http
      .delete<ApiEnvelope<{ conversation: SupportThread }>>(
        `${this.api}/admin/conversations/${id}/participants/${userId}`,
      )
      .pipe(map((response) => response.data.conversation));
  }

  // --- File de traitement des demandes (F8.9) --------------------------------
  //
  // ⚠️ La LECTURE est sous `/admin` (garde `traiter:demandes`), mais le
  // PILOTAGE reste sur la route transversale historique
  // `PATCH /requests/{id}/status`, gardée par la même permission : la machine à
  // états n'existe qu'à un seul endroit et n'a pas à être dupliquée.

  /** File de traitement (urgences d'abord). GET /admin/requests */
  requestQueue(query: RequestQueueQuery = {}): Observable<Paginated<RequestQueueEntry>> {
    let params = new HttpParams();
    if (query.status) params = params.set('status', query.status);
    if (query.service_type) params = params.set('service_type', query.service_type);
    if (query.priority) params = params.set('priority', query.priority);
    if (query.search) params = params.set('search', query.search);
    if (query.page) params = params.set('page', String(query.page));
    return this.http.get<Paginated<RequestQueueEntry>>(`${this.api}/admin/requests`, { params });
  }

  /** Fiche d'une demande (demandeur, devis, historique). GET /admin/requests/{id} */
  requestDossier(id: number): Observable<RequestQueueDetail> {
    return this.http
      .get<ApiEnvelope<RequestQueueDetail>>(`${this.api}/admin/requests/${id}`)
      .pipe(map((response) => response.data));
  }

  /** Statuts / services / priorités, tels que les définit le backend. */
  requestQueueFilters(): Observable<RequestQueueFilters> {
    return this.http
      .get<ApiEnvelope<RequestQueueFilters>>(`${this.api}/admin/requests/filters`)
      .pipe(map((response) => response.data));
  }

  /**
   * Fait avancer une demande. PATCH /requests/{id}/status
   *
   * Le serveur refuse (422) toute transition qui n'est pas dans
   * `allowed_transitions` — l'écran ne propose donc que celles-là.
   */
  advanceRequest(id: number, status: string): Observable<RequestQueueEntry> {
    return this.http
      .patch<ApiEnvelope<{ request: RequestQueueEntry }>>(`${this.api}/requests/${id}/status`, {
        status,
      })
      .pipe(map((response) => response.data.request));
  }

  /**
   * Chiffre une demande. `POST /requests/{id}/quotes` (garde `traiter:demandes`)
   *
   * ⚠️ **Cette route existait depuis B11.3 et aucun écran ne l'appelait** — la
   * file de traitement (F8.9) savait lister et faire avancer les demandes, mais
   * pas les chiffrer. Le client, lui, savait déjà répondre à un devis : il
   * pouvait accepter ce que personne ne pouvait émettre. Tous les devis en base
   * venaient du seeder.
   *
   * Le devis part **directement au statut « envoyé »** : le serveur ne connaît
   * pas de brouillon sur cette route, composer c'est envoyer. Le client en est
   * notifié dans la foulée (`QuoteReceivedNotification`).
   *
   * @param details Postes libres du chiffrage (`{ poste: valeur }`), affichés
   *                tels quels au client — d'où le passage par une saisie
   *                ligne à ligne plutôt qu'un JSON tapé à la main.
   */
  composeQuote(
    requestId: number,
    payload: { amount_xof: number; valid_until?: string | null; details?: Record<string, string> },
  ): Observable<RequestQuote> {
    return this.http
      .post<ApiEnvelope<{ quote: RequestQuote }>>(
        `${this.api}/requests/${requestId}/quotes`,
        payload,
      )
      .pipe(map((response) => response.data.quote));
  }

  // --- Dossier de construction (F7.3.b) --------------------------------------
  //
  // Routes du module Build (hors préfixe /admin) : la lecture passe par la
  // policy `view` (client propriétaire OU agent/admin), le dépôt d'un compte
  // rendu exige la permission `gerer:chantiers`.

  /** Fiche d'une demande de construction (jalons + demandeur). GET /construction-requests/{id} */
  constructionRequest(id: number): Observable<ConstructionDossier> {
    return this.http
      .get<ApiEnvelope<{ construction_request: ConstructionDossier }>>(
        `${this.api}/construction-requests/${id}`,
      )
      .pipe(map((response) => response.data.construction_request));
  }

  /** Comptes rendus de chantier (paginés). GET /construction-requests/{id}/reports */
  constructionReports(id: number, page = 1): Observable<Paginated<ConstructionReport>> {
    const params = new HttpParams().set('page', String(page));
    return this.http.get<Paginated<ConstructionReport>>(
      `${this.api}/construction-requests/${id}/reports`,
      { params },
    );
  }

  /** Publie un compte rendu de chantier. POST /construction-requests/{id}/reports */
  addConstructionReport(id: number, payload: CreateReportPayload): Observable<ConstructionReport> {
    return this.http
      .post<ApiEnvelope<{ report: ConstructionReport }>>(
        `${this.api}/construction-requests/${id}/reports`,
        payload,
      )
      .pipe(map((response) => response.data.report));
  }

  // --- Pilotage des jalons de chantier (F7.3.e1) ------------------------------
  //
  // ⚠️ Routes du module Build (pas /admin), gardées par `gerer:chantiers` —
  // comme la publication des comptes rendus. Elles n'existaient pas avant
  // F7.3.e1 : les jalons étaient semés au dépôt puis figés.

  /** Ajoute un jalon (position omise = fin de planning). POST …/milestones */
  addMilestone(requestId: number, payload: MilestonePayload): Observable<ConstructionMilestone> {
    return this.http
      .post<ApiEnvelope<{ milestone: ConstructionMilestone }>>(
        `${this.api}/construction-requests/${requestId}/milestones`,
        payload,
      )
      .pipe(map((response) => response.data.milestone));
  }

  /**
   * Fait avancer ou replanifie un jalon. PATCH /construction-milestones/{id}
   *
   * Le serveur maintient la cohérence statut ↔ date réelle (terminé sans date =
   * daté du jour ; réouverture = date effacée) : inutile de la gérer côté écran.
   */
  updateMilestone(
    milestoneId: number,
    payload: MilestonePayload,
  ): Observable<ConstructionMilestone> {
    return this.http
      .patch<ApiEnvelope<{ milestone: ConstructionMilestone }>>(
        `${this.api}/construction-milestones/${milestoneId}`,
        payload,
      )
      .pipe(map((response) => response.data.milestone));
  }

  /** Retire un jalon du planning. DELETE /construction-milestones/{id} */
  deleteMilestone(milestoneId: number): Observable<void> {
    return this.http
      .delete<ApiEnvelope<{ deleted: boolean }>>(`${this.api}/construction-milestones/${milestoneId}`)
      .pipe(map(() => undefined));
  }

  /**
   * Réécrit l'ordre du planning. PUT …/milestones/reorder
   *
   * On envoie la liste ORDONNÉE des identifiants : un échange de deux positions
   * en deux requêtes produirait un doublon transitoire, et un ordre indéterminé
   * si la seconde échouait.
   */
  reorderMilestones(requestId: number, orderedIds: number[]): Observable<ConstructionMilestone[]> {
    return this.http
      .put<ApiEnvelope<{ milestones: ConstructionMilestone[] }>>(
        `${this.api}/construction-requests/${requestId}/milestones/reorder`,
        { milestones: orderedIds },
      )
      .pipe(map((response) => response.data.milestones));
  }

  // --- Devis de chantier (F7.3.e2) --------------------------------------------

  /** Devis d'un chantier, du plus récent au plus ancien. GET …/quotes */
  constructionQuotes(requestId: number): Observable<ConstructionQuote[]> {
    return this.http
      .get<{ data: ConstructionQuote[] }>(`${this.api}/construction-requests/${requestId}/quotes`)
      .pipe(map((response) => response.data));
  }

  /** Chiffre un devis ventilé par lot (garde `gerer:chantiers`). POST …/quotes */
  composeConstructionQuote(
    requestId: number,
    payload: ComposeConstructionQuotePayload,
  ): Observable<ConstructionQuote> {
    return this.http
      .post<ApiEnvelope<{ quote: ConstructionQuote }>>(
        `${this.api}/construction-requests/${requestId}/quotes`,
        payload,
      )
      .pipe(map((response) => response.data.quote));
  }

  /**
   * Envoie le devis au client. PATCH /construction-quotes/{id}/send
   *
   * Seul un brouillon est envoyable (422 sinon) : un second envoi écraserait en
   * silence la réponse déjà donnée par le client.
   */
  sendConstructionQuote(quoteId: number): Observable<ConstructionQuote> {
    return this.http
      .patch<ApiEnvelope<{ quote: ConstructionQuote }>>(
        `${this.api}/construction-quotes/${quoteId}/send`,
        {},
      )
      .pipe(map((response) => response.data.quote));
  }

  // ⚠️ Accepter / refuser un devis n'est PAS exposé ici : c'est un geste du
  // CLIENT (policy `respond`, côté espace client), pas du back-office.

  // --- Prestataires BTP affectés au chantier (F7.3.e3) ------------------------

  /** Missions rattachées au chantier. GET …/assignments */
  constructionAssignments(requestId: number): Observable<ProviderMissionItem[]> {
    return this.http
      .get<{ data: ProviderMissionItem[] }>(
        `${this.api}/construction-requests/${requestId}/assignments`,
      )
      .pipe(map((response) => response.data));
  }

  /**
   * Affecte un prestataire validé à un LOT du chantier (garde `gerer:chantiers`).
   * POST …/assignments
   *
   * Crée une vraie mission Pro : cycle standard, commission figée, visible dans
   * les revenus du prestataire — comme l'affectation team building (F7.2.h).
   */
  assignConstructionProvider(
    requestId: number,
    payload: AssignConstructionProviderPayload,
  ): Observable<ProviderMissionItem> {
    return this.http
      .post<ApiEnvelope<{ mission: ProviderMissionItem }>>(
        `${this.api}/construction-requests/${requestId}/assignments`,
        payload,
      )
      .pipe(map((response) => response.data.mission));
  }

  // --- Pilotage de la gestion locative (F7.3.a) ------------------------------
  //
  // ⚠️ Ces routes ne sont PAS sous /admin : elles vivent dans le module Manage
  // et sont gardées par la permission `gerer:gestion-locative` (lecture de la
  // fiche = policy `view`, qui autorise le propriétaire OU un agent/admin).

  /** Fiche complète d'un mandat (lignes + agrégats). GET /manage/mandates/{id} */
  mandate(id: number): Observable<MandateDossier> {
    return this.http
      .get<ApiEnvelope<{ mandate: MandateDossier }>>(`${this.api}/manage/mandates/${id}`)
      .pipe(map((response) => response.data.mandate));
  }

  /**
   * Rapport mensuel d'un mandat. GET /manage/mandates/{id}/report?month=YYYY-MM
   * Sans `month`, le serveur prend le mois courant.
   */
  mandateReport(id: number, month?: string): Observable<MandateReport> {
    let params = new HttpParams();
    if (month) params = params.set('month', month);
    return this.http
      .get<ApiEnvelope<{ report: MandateReport }>>(`${this.api}/manage/mandates/${id}/report`, {
        params,
      })
      .pipe(map((response) => response.data.report));
  }

  /** Ajoute une échéance de loyer (créée « impayée »). POST /manage/mandates/{id}/rents */
  addRent(mandateId: number, payload: RentPayload): Observable<MandateRent> {
    return this.http
      .post<ApiEnvelope<{ rent: MandateRent }>>(`${this.api}/manage/mandates/${mandateId}/rents`, payload)
      .pipe(map((response) => response.data.rent));
  }

  /** Marque une échéance comme encaissée. PATCH /manage/rents/{id}/pay */
  markRentPaid(rentId: number): Observable<MandateRent> {
    return this.http
      .patch<ApiEnvelope<{ rent: MandateRent }>>(`${this.api}/manage/rents/${rentId}/pay`, {})
      .pipe(map((response) => response.data.rent));
  }

  /** Signale un incident sur le bien géré. POST /manage/mandates/{id}/incidents */
  addIncident(mandateId: number, payload: IncidentPayload): Observable<MandateIncident> {
    return this.http
      .post<ApiEnvelope<{ incident: MandateIncident }>>(
        `${this.api}/manage/mandates/${mandateId}/incidents`,
        payload,
      )
      .pipe(map((response) => response.data.incident));
  }

  /** Clôt un incident. PATCH /manage/incidents/{id}/resolve */
  resolveIncident(incidentId: number): Observable<MandateIncident> {
    return this.http
      .patch<ApiEnvelope<{ incident: MandateIncident }>>(
        `${this.api}/manage/incidents/${incidentId}/resolve`,
        {},
      )
      .pipe(map((response) => response.data.incident));
  }

  /** Enregistre une dépense sur le bien géré. POST /manage/mandates/{id}/expenses */
  addExpense(mandateId: number, payload: ExpensePayload): Observable<MandateExpense> {
    return this.http
      .post<ApiEnvelope<{ expense: MandateExpense }>>(
        `${this.api}/manage/mandates/${mandateId}/expenses`,
        payload,
      )
      .pipe(map((response) => response.data.expense));
  }

  /** Prépare un reversement au propriétaire. POST /manage/mandates/{id}/payouts */
  addPayout(mandateId: number, payload: PayoutPayload): Observable<MandatePayout> {
    return this.http
      .post<ApiEnvelope<{ payout: MandatePayout }>>(
        `${this.api}/manage/mandates/${mandateId}/payouts`,
        payload,
      )
      .pipe(map((response) => response.data.payout));
  }

  /** Marque un reversement comme effectué. PATCH /manage/payouts/{id}/pay */
  markPayoutPaid(payoutId: number): Observable<MandatePayout> {
    return this.http
      .patch<ApiEnvelope<{ payout: MandatePayout }>>(`${this.api}/manage/payouts/${payoutId}/pay`, {})
      .pipe(map((response) => response.data.payout));
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
    if (query.category) params = params.set('category', query.category);
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

  // --- Team building (F7.2.h) ------------------------------------------------
  //
  // Endpoints au niveau racine (hors préfixe /admin) : la file back-office est
  // gardée par `consulter:dashboard-admin`, la composition de devis et
  // l'affectation de prestataires par la policy `manage` (rôle admin).

  /** File des demandes de team building (paginée, filtrable). GET /team-building-requests */
  teamBuildingRequests(query: TeamBuildingQuery = {}): Observable<Paginated<TeamBuildingRequestItem>> {
    let params = new HttpParams();
    if (query.status) params = params.set('status', query.status);
    if (query.q) params = params.set('q', query.q);
    if (query.page) params = params.set('page', String(query.page));
    return this.http.get<Paginated<TeamBuildingRequestItem>>(`${this.api}/team-building-requests`, {
      params,
    });
  }

  /** Fiche d'une demande (devis + prestataires affectés). GET /team-building-requests/{id} */
  teamBuildingRequest(id: number): Observable<TeamBuildingRequestDetail> {
    return this.http
      .get<ApiEnvelope<{ request: TeamBuildingRequestDetail }>>(`${this.api}/team-building-requests/${id}`)
      .pipe(map((response) => response.data.request));
  }

  /**
   * Compose un devis (pack multi-modules) pour une demande.
   * POST /team-building-requests/{id}/quotes
   *
   * `components` = les briques du pack (catégorie, quantité, prix unitaire) ;
   * `marginRate` = marge plateforme en % (défaut serveur 15 %).
   */
  composeTeamBuildingQuote(
    id: number,
    components: QuoteComponent[],
    marginRate?: number,
  ): Observable<TeamBuildingQuote> {
    const body: { components: QuoteComponent[]; margin_rate?: number } = { components };
    if (marginRate !== undefined && marginRate !== null) body.margin_rate = marginRate;
    return this.http
      .post<ApiEnvelope<{ quote: TeamBuildingQuote }>>(
        `${this.api}/team-building-requests/${id}/quotes`,
        body,
      )
      .pipe(map((response) => response.data.quote));
  }

  /** Envoie un devis à l'entreprise. PATCH /team-building-quotes/{id}/send */
  sendTeamBuildingQuote(quoteId: number): Observable<TeamBuildingQuote> {
    return this.http
      .patch<ApiEnvelope<{ quote: TeamBuildingQuote }>>(
        `${this.api}/team-building-quotes/${quoteId}/send`,
        {},
      )
      .pipe(map((response) => response.data.quote));
  }

  /** Prestataires affectés à une demande. GET /team-building-requests/{id}/assignments */
  teamBuildingAssignments(id: number): Observable<ProviderMissionItem[]> {
    return this.http
      .get<ApiEnvelope<ProviderMissionItem[]>>(`${this.api}/team-building-requests/${id}/assignments`)
      .pipe(map((response) => response.data));
  }

  /**
   * Affecte un prestataire validé à une brique du pack (crée une mission Pro).
   * POST /team-building-requests/{id}/assignments
   */
  assignTeamBuildingProvider(id: number, payload: AssignProviderPayload): Observable<ProviderMissionItem> {
    return this.http
      .post<ApiEnvelope<{ mission: ProviderMissionItem }>>(
        `${this.api}/team-building-requests/${id}/assignments`,
        payload,
      )
      .pipe(map((response) => response.data.mission));
  }

  // --- Diaspora (F7.2.i) -----------------------------------------------------
  //
  // Endpoints au niveau racine (`/diaspora-projects`, hors préfixe /admin) : la
  // file priorisée est gardée par `consulter:dashboard-admin` ; le pilotage
  // (statut/priorité) et les rapports par la policy `update` (agent affecté ou
  // admin) ; l'affectation d'un agent par la policy `assign` (admin).

  /** File priorisée des dossiers diaspora (paginée, filtrable). GET /diaspora-projects */
  diasporaProjects(query: DiasporaQuery = {}): Observable<Paginated<DiasporaProject>> {
    let params = new HttpParams();
    if (query.status) params = params.set('status', query.status);
    if (query.priority) params = params.set('priority', query.priority);
    if (query.q) params = params.set('q', query.q);
    if (query.page) params = params.set('page', String(query.page));
    return this.http.get<Paginated<DiasporaProject>>(`${this.api}/diaspora-projects`, { params });
  }

  /** Fiche d'un dossier diaspora. GET /diaspora-projects/{id} */
  diasporaProject(id: number): Observable<DiasporaProject> {
    return this.http
      .get<ApiEnvelope<{ project: DiasporaProject }>>(`${this.api}/diaspora-projects/${id}`)
      .pipe(map((response) => response.data.project));
  }

  /** Pilote un dossier : statut et/ou priorité (sans effet de bord). PATCH /diaspora-projects/{id} */
  updateDiasporaProject(id: number, payload: UpdateDiasporaPayload): Observable<DiasporaProject> {
    return this.http
      .patch<ApiEnvelope<{ project: DiasporaProject }>>(`${this.api}/diaspora-projects/${id}`, payload)
      .pipe(map((response) => response.data.project));
  }

  /**
   * Affecte un agent dédié (explicite ou auto = le moins chargé) — bascule le
   * dossier « en cours ». PATCH /diaspora-projects/{id}/assign
   */
  assignDiasporaAgent(id: number, payload: AssignAgentPayload = {}): Observable<DiasporaProject> {
    return this.http
      .patch<ApiEnvelope<{ project: DiasporaProject }>>(`${this.api}/diaspora-projects/${id}/assign`, payload)
      .pipe(map((response) => response.data.project));
  }

  /** Rapports de suivi d'un dossier (paginés). GET /diaspora-projects/{id}/reports */
  diasporaReports(id: number, page = 1): Observable<Paginated<DiasporaReport>> {
    const params = new HttpParams().set('page', String(page));
    return this.http.get<Paginated<DiasporaReport>>(`${this.api}/diaspora-projects/${id}/reports`, {
      params,
    });
  }

  /** Ajoute un rapport de suivi (vérification / chantier). POST /diaspora-projects/{id}/reports */
  addDiasporaReport(id: number, payload: CreateReportPayload): Observable<DiasporaReport> {
    return this.http
      .post<ApiEnvelope<{ report: DiasporaReport }>>(`${this.api}/diaspora-projects/${id}/reports`, payload)
      .pipe(map((response) => response.data.report));
  }

  // --- Paramètres & contenu (F7.2.l) -----------------------------------------
  //
  // Tout ce bloc est gardé côté serveur par la permission `gerer:parametres`
  // (gouvernance : déléguée par un super_admin uniquement), à l'exception de
  // `reference()` qui se contente de `consulter:dashboard-admin`.

  /** Réglages effectifs + catalogue des événements notifiables. GET /admin/settings */
  settings(): Observable<SettingsSnapshot> {
    return this.http
      .get<ApiEnvelope<SettingsSnapshot>>(`${this.api}/admin/settings`)
      .pipe(map((response) => response.data));
  }

  /**
   * Enregistre un LOT de réglages. PATCH /admin/settings
   *
   * On n'envoie que les clés réellement modifiées : le serveur refuse toute clé
   * inconnue (422) et ne touche pas aux autres.
   */
  updateSettings(settings: Record<string, unknown>): Observable<SettingsSnapshot> {
    return this.http
      .patch<ApiEnvelope<SettingsSnapshot>>(`${this.api}/admin/settings`, { settings })
      .pipe(map((response) => response.data));
  }

  /** Nomenclatures de référence (catégories, régions), lecture seule. GET /admin/reference */
  reference(): Observable<ReferenceCatalog> {
    return this.http
      .get<ApiEnvelope<ReferenceCatalog>>(`${this.api}/admin/reference`)
      .pipe(map((response) => response.data));
  }

  // --- Contenu éditorial : FAQ ------------------------------------------------

  /** Toutes les entrées de FAQ (publiées ET masquées). GET /admin/faqs */
  faqs(): Observable<FaqEntry[]> {
    return this.http
      .get<ApiEnvelope<FaqEntry[]>>(`${this.api}/admin/faqs`)
      .pipe(map((response) => response.data));
  }

  /** Crée une entrée de FAQ. POST /admin/faqs */
  createFaq(payload: FaqPayload): Observable<FaqEntry> {
    return this.http
      .post<ApiEnvelope<{ faq: FaqEntry }>>(`${this.api}/admin/faqs`, payload)
      .pipe(map((response) => response.data.faq));
  }

  /** Met à jour une entrée de FAQ. PATCH /admin/faqs/{id} */
  updateFaq(id: number, payload: FaqPayload): Observable<FaqEntry> {
    return this.http
      .patch<ApiEnvelope<{ faq: FaqEntry }>>(`${this.api}/admin/faqs/${id}`, payload)
      .pipe(map((response) => response.data.faq));
  }

  /** Supprime une entrée de FAQ. DELETE /admin/faqs/{id} */
  deleteFaq(id: number): Observable<void> {
    return this.http.delete<void>(`${this.api}/admin/faqs/${id}`);
  }

  // --- Contenu éditorial : pages ---------------------------------------------

  /** Toutes les pages de contenu. GET /admin/pages */
  pages(): Observable<ContentPage[]> {
    return this.http
      .get<ApiEnvelope<ContentPage[]>>(`${this.api}/admin/pages`)
      .pipe(map((response) => response.data));
  }

  /** Crée une page. POST /admin/pages */
  createPage(payload: PagePayload): Observable<ContentPage> {
    return this.http
      .post<ApiEnvelope<{ page: ContentPage }>>(`${this.api}/admin/pages`, payload)
      .pipe(map((response) => response.data.page));
  }

  /**
   * Met à jour une page. PATCH /admin/pages/{slug}
   *
   * ⚠️ Les pages sont résolues **par slug** côté serveur (`getRouteKeyName`) :
   * on adresse donc toujours l'ANCIEN slug, y compris quand on le renomme.
   */
  updatePage(slug: string, payload: PagePayload): Observable<ContentPage> {
    return this.http
      .patch<ApiEnvelope<{ page: ContentPage }>>(`${this.api}/admin/pages/${slug}`, payload)
      .pipe(map((response) => response.data.page));
  }

  /** Supprime une page. DELETE /admin/pages/{slug} */
  deletePage(slug: string): Observable<void> {
    return this.http.delete<void>(`${this.api}/admin/pages/${slug}`);
  }

  // --- Référentiel géographique (« villes ») ---------------------------------

  /** Arborescence régions → départements (+ compteurs). GET /admin/geography */
  geography(): Observable<GeoRegion[]> {
    return this.http
      .get<ApiEnvelope<{ regions: GeoRegion[] }>>(`${this.api}/admin/geography`)
      .pipe(map((response) => response.data.regions));
  }

  /** Communes d'un département (ou recherche transverse). GET /admin/communes */
  communes(query: CommuneQuery = {}): Observable<Paginated<AdminCommune>> {
    let params = new HttpParams();
    if (query.department_id) params = params.set('department_id', String(query.department_id));
    if (query.region_id) params = params.set('region_id', String(query.region_id));
    if (query.q) params = params.set('q', query.q);
    if (query.page) params = params.set('page', String(query.page));
    return this.http.get<Paginated<AdminCommune>>(`${this.api}/admin/communes`, { params });
  }

  /** Crée une commune. POST /admin/communes */
  createCommune(payload: CommunePayload): Observable<AdminCommune> {
    return this.http
      .post<ApiEnvelope<{ commune: AdminCommune }>>(`${this.api}/admin/communes`, payload)
      .pipe(map((response) => response.data.commune));
  }

  /** Renomme / reclasse une commune. PATCH /admin/communes/{id} */
  updateCommune(id: number, payload: CommunePayload): Observable<AdminCommune> {
    return this.http
      .patch<ApiEnvelope<{ commune: AdminCommune }>>(`${this.api}/admin/communes/${id}`, payload)
      .pipe(map((response) => response.data.commune));
  }

  /**
   * Supprime une commune. DELETE /admin/communes/{id}
   *
   * Renvoie **409** si des biens ou des comptes y sont encore rattachés (la
   * suppression effacerait leur localisation).
   */
  deleteCommune(id: number): Observable<void> {
    return this.http.delete<void>(`${this.api}/admin/communes/${id}`);
  }

  /** Crée un département. POST /admin/departments */
  createDepartment(payload: DepartmentPayload): Observable<GeoDepartment> {
    return this.http
      .post<ApiEnvelope<{ department: GeoDepartment }>>(`${this.api}/admin/departments`, payload)
      .pipe(map((response) => response.data.department));
  }

  /** Renomme / rattache un département. PATCH /admin/departments/{id} */
  updateDepartment(id: number, payload: DepartmentPayload): Observable<GeoDepartment> {
    return this.http
      .patch<ApiEnvelope<{ department: GeoDepartment }>>(`${this.api}/admin/departments/${id}`, payload)
      .pipe(map((response) => response.data.department));
  }

  /** Supprime un département VIDE et inutilisé (409 sinon). DELETE /admin/departments/{id} */
  deleteDepartment(id: number): Observable<void> {
    return this.http.delete<void>(`${this.api}/admin/departments/${id}`);
  }
}
