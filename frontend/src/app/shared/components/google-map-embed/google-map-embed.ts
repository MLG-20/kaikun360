import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';

/**
 * Carte Google Maps intégrée sur une fiche détaillée (F5.10).
 *
 * Le propriétaire/prestataire colle, dans son formulaire, le lien `src`
 * obtenu via Google Maps « Partager » → « Intégrer une carte » (mode de
 * partage gratuit, sans clé API — la plateforme appartenant à un client,
 * aucune clé Maps facturable n'est disponible). Ce composant ne fait
 * qu'afficher ce lien en iframe ; il se masque de lui-même si aucun lien
 * n'est fourni.
 *
 * ⚠️ `bypassSecurityTrustResourceUrl` : le backend a déjà vérifié que le lien
 * pointe vers un domaine Google Maps connu (`App\Rules\GoogleMapsLink`) avant
 * de l'enregistrer — on ne fait ici que restituer une valeur de confiance,
 * exactement comme la carte du siège sur la page Contact.
 */
@Component({
  selector: 'app-google-map-embed',
  templateUrl: './google-map-embed.html',
  styleUrl: './google-map-embed.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class GoogleMapEmbedComponent {
  private readonly sanitizer = inject(DomSanitizer);

  /** Lien `src` collé par le propriétaire/prestataire, ou `null`. */
  readonly url = input<string | null>(null);

  protected readonly safeUrl = computed<SafeResourceUrl | null>(() => {
    const value = this.url();
    return value ? this.sanitizer.bypassSecurityTrustResourceUrl(value) : null;
  });
}
