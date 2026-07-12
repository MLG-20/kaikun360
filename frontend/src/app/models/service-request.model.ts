/**
 * Demande de service — miroir de `ServiceRequestResource` (couche transversale).
 * `allowed_transitions` liste les statuts atteignables (machine à états backend).
 */
export interface ServiceRequest {
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
  created_at: string | null;
  allowed_transitions: string[];
}
