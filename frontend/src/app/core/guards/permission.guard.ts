import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { AuthService } from '../auth/auth.service';

/**
 * Cloisonne une rubrique du back-office par PERMISSION (F7.4.a).
 *
 * Les permissions autorisées se déclarent sur la route :
 *   `data: { permissions: ['gerer:paiements'] }`
 * La règle est un **OU** : détenir l'une d'elles suffit. Un écran qui agrège
 * plusieurs sources (Avis & qualité = avis + prestataires) en déclare donc
 * plusieurs, et s'ouvre dès qu'une des deux moitiés est accessible.
 *
 * Se pose EN PLUS du `roleGuard` de la route racine `/back-office`, qui a déjà
 * vérifié que la personne appartient à l'équipe. Ici on ne filtre plus l'accès
 * à la salle de contrôle, mais les portes qu'on y ouvre :
 *
 *   - `data.permissions` absent ou vide → rubrique ouverte à toute l'équipe
 *     (Vue d'ensemble, Pointeuse : périmètre personnel ou purement indicatif) ;
 *   - permission manquante → retour à la Vue d'ensemble, seul écran garanti à
 *     tout membre de l'équipe (jamais vers `/`, qui le sortirait du back-office
 *     alors qu'il y a parfaitement sa place).
 *
 * ⚠️ Ceci est une commodité d'INTERFACE, pas la sécurité : chaque route
 * `/admin/…` reste gardée par son `can:` côté Laravel, et le restera. Le but est
 * qu'un agent ne voie pas des rubriques qui lui répondraient 403 — le CDC §7
 * lui promettant un « accès financier limité », lui afficher Paiements et
 * l'Export comptable dans son menu était trompeur.
 */
export const permissionGuard: CanActivateFn = (route) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  const permissions = (route.data['permissions'] as string[] | undefined) ?? [];

  if (permissions.length === 0 || auth.hasAnyPermission(permissions)) {
    return true;
  }

  return router.createUrlTree(['/back-office'], { queryParams: { acces: 'refuse' } });
};
