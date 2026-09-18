import { AdminExperience } from '../../../models/experience.model';

/**
 * Le **programme** d'un circuit — lecture partagée par l'onglet Circuits et la
 * fiche du circuit (F8.2.c).
 *
 * Revu en F21 : le programme jour par jour (`itinerary`) remplace les 4
 * inclusions à cocher qui ne portaient pas vraiment de programme, seulement
 * ce qui était compris dans le prix (déplacé vers `included`/`excluded`).
 */

/** Les jours du programme, en libellés lisibles (« Jour 1 — Titre »). */
export function programmeOf(circuit: AdminExperience): string[] {
  return (circuit.itinerary ?? []).map((jour) =>
    jour.title ? `Jour ${jour.day} — ${jour.title}` : `Jour ${jour.day}`,
  );
}
