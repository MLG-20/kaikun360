/**
 * Extrait le lien `src` d'une carte Google Maps intégrée (F5.10).
 *
 * Le propriétaire/prestataire copie depuis Google Maps « Partager » →
 * « Intégrer une carte » : soit le code `<iframe ...>` complet, soit
 * seulement le lien `src` qu'il contient. Les deux formes doivent être
 * acceptées — on ne peut pas prédire laquelle un utilisateur non technique
 * colle réellement.
 *
 * Renvoie `null` si la valeur est vide.
 */
export function extractGoogleMapsEmbedUrl(raw: string): string | null {
  const trimmed = raw.trim();
  if (!trimmed) {
    return null;
  }
  const match = trimmed.match(/src=["']([^"']+)["']/i);
  return match ? match[1] : trimmed;
}
