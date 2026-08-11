import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map } from 'rxjs/operators';

import { ContentService } from '../../../core/api/content.service';
import { Faq } from '../../../models/content.model';
import { PageHeroComponent } from '../../../shared/components/page-hero/page-hero';

/** État de chargement de la FAQ. */
type LoadState = 'loading' | 'ready' | 'empty' | 'failed';

/** Un groupe de questions partageant une même catégorie. */
interface FaqGroup {
  category: string;
  items: Faq[];
}

/**
 * Foire aux questions (F2.8) — route `/faqs`.
 *
 * Charge les entrées publiées via `ContentService.faqs()` puis les regroupe par
 * catégorie (en conservant l'ordre voulu par l'équipe éditoriale via `position`,
 * l'API renvoyant déjà les entrées triées). Chaque question est un accordéon
 * natif `<details>` (accessible, sans JavaScript). États : chargement, prêt,
 * vide (aucune entrée) et échec réseau — tous dégradés proprement.
 */
@Component({
  selector: 'app-faq-page',
  imports: [PageHeroComponent, RouterLink],
  templateUrl: './faq-page.html',
  styleUrl: './faq-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FaqPageComponent {
  private readonly content = inject(ContentService);

  /**
   * Résultat brut de l'appel : `undefined` tant que la requête n'a pas répondu,
   * `null` en cas d'échec réseau, sinon la liste (éventuellement vide).
   */
  private readonly result = toSignal(
    this.content.faqs().pipe(
      map((res) => res.data),
      catchError(() => of(null)),
    ),
  );

  /** État dérivé pour piloter l'affichage. */
  readonly state = computed<LoadState>(() => {
    const data = this.result();
    if (data === undefined) {
      return 'loading';
    }
    if (data === null) {
      return 'failed';
    }
    return data.length ? 'ready' : 'empty';
  });

  /**
   * Entrées regroupées par catégorie, dans l'ordre d'apparition (une entrée
   * sans catégorie est classée sous « Questions générales »).
   */
  readonly groups = computed<FaqGroup[]>(() => {
    const data = this.result();
    if (!data) {
      return [];
    }
    const groups: FaqGroup[] = [];
    const index = new Map<string, FaqGroup>();
    for (const item of data) {
      const category = item.category?.trim() || 'Questions générales';
      let group = index.get(category);
      if (!group) {
        group = { category, items: [] };
        index.set(category, group);
        groups.push(group);
      }
      group.items.push(item);
    }
    return groups;
  });
}
