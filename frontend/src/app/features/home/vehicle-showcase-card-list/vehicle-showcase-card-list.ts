import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { RouterLink } from '@angular/router';

/** Le sous-ensemble d'une carte véhicule dont l'affichage a besoin. */
export interface VehicleShowcaseCardView {
  id: number;
  title: string;
  description: string | null;
  image: string;
  linkUrl: string;
  linkLabel: string | null;
  category?: string | null;
}

/** Cible d'une carte : une route interne (avec filtre) ou un lien externe. */
interface CardTarget {
  internal: boolean;
  path: string;
  query: Record<string, string> | null;
  href: string;
}

/** Hôtes du site : un lien qui y pointe reste dans l'application (même en local). */
const SITE_HOSTS = ['kaikun360.com', 'www.kaikun360.com'];

/**
 * Type de véhicule du catalogue (`?type=`) déduit de la catégorie saisie au
 * back-office (texte libre, avec suggestions). Sans correspondance, la carte
 * mène à la liste complète.
 */
const TYPE_BY_KEYWORD: readonly [string, string][] = [
  ['minibus', 'minibus'],
  ['navette', 'navette_aibd'],
  ['aibd', 'navette_aibd'],
  ['pirogue', 'pirogue'],
  ['4x4', 'quatre_quatre'],
  ['quatre', 'quatre_quatre'],
  ['touristique', 'voiture_touristique'],
  ['chauffeur', 'chauffeur'],
  ['bus', 'bus'],
  ['autocar', 'bus'],
  ['berline', 'voiture_particuliere'],
  ['citadine', 'voiture_particuliere'],
  ['voiture', 'voiture_particuliere'],
];

function vehicleTypeFor(card: VehicleShowcaseCardView): string | null {
  const text = `${card.category ?? ''} ${card.title}`
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace('×', 'x');

  return TYPE_BY_KEYWORD.find(([keyword]) => text.includes(keyword))?.[1] ?? null;
}

function targetOf(card: VehicleShowcaseCardView): CardTarget {
  const external: CardTarget = { internal: false, path: '', query: null, href: card.linkUrl };
  let url: URL;
  try {
    url = new URL(card.linkUrl, 'https://kaikun360.com');
  } catch {
    return external;
  }
  if (!SITE_HOSTS.includes(url.hostname)) {
    return external;
  }
  const path = url.pathname.replace(/\/$/, '') || '/';
  const query = Object.fromEntries(url.searchParams.entries());
  // Lien nu vers le catalogue transport : on y ajoute le filtre de type.
  if (path === '/transport' && !('type' in query)) {
    const type = vehicleTypeFor(card);
    if (type) query['type'] = type;
  }
  return { internal: true, path, query: Object.keys(query).length ? query : null, href: card.linkUrl };
}

/**
 * Cartes de la section « Location de véhicules » de l'accueil (2026-09-07),
 * qui remplace l'ancienne section « Protocole de confiance ».
 *
 * Composant à part, avec sa propre feuille de style — même raison que
 * `NewsCardMiniListComponent` : pas de budget CSS à consommer sur
 * `home-page.scss`. Plus grandes que les cartes « À découvrir » et
 * affichant toujours leur description (demande client, 2026-09-07) : ce
 * n'est PAS le même composant que les actualités, dont l'absence de
 * description est un choix produit documenté à part.
 */
@Component({
  selector: 'app-vehicle-showcase-card-list',
  imports: [RouterLink, NgTemplateOutlet],
  templateUrl: './vehicle-showcase-card-list.html',
  styleUrl: './vehicle-showcase-card-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VehicleShowcaseCardListComponent {
  cards = input.required<VehicleShowcaseCardView[]>();

  protected readonly items = computed(() =>
    this.cards().map((card) => ({ card, target: targetOf(card) })),
  );
}
