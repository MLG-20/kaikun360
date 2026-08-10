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
  /**
   * F11.5 — le client peut-il ranger cette demande dans sa corbeille ?
   * ⚠️ Décidé par le SERVEUR (seule une demande clôturée se range) : ne jamais
   * rejouer la règle ici, l'écran proposerait un bouton qui échoue.
   */
  hideable?: boolean;
}
