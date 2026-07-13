import { Routes } from '@angular/router';

import { AuthLayoutComponent } from './auth-layout/auth-layout';

/**
 * Routes du domaine authentification (F1), toutes rendues dans le layout auth.
 *
 * `connexion` est livrée en F1.1 ; l'inscription, la vérification et la
 * récupération de mot de passe viendront en F1.2 / F1.3 (mêmes enfants).
 */
export const AUTH_ROUTES: Routes = [
  {
    path: '',
    component: AuthLayoutComponent,
    children: [
      {
        path: 'connexion',
        loadComponent: () => import('./pages/login/login-page').then((m) => m.LoginPageComponent),
        title: 'Connexion — Kaikun 360',
      },
      {
        path: 'inscription',
        loadComponent: () => import('./pages/register/register-page').then((m) => m.RegisterPageComponent),
        title: 'Créer un compte — Kaikun 360',
      },
      { path: '', redirectTo: 'connexion', pathMatch: 'full' },
    ],
  },
];
