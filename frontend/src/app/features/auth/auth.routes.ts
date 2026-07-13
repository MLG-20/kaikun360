import { Routes } from '@angular/router';

import { authGuard } from '../../core/guards/auth.guard';
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
      {
        // Vérification : l'utilisateur doit être connecté (jeton d'inscription).
        path: 'verification',
        canActivate: [authGuard],
        loadComponent: () =>
          import('./pages/verification/verification-page').then((m) => m.VerificationPageComponent),
        title: 'Vérifier mon compte — Kaikun 360',
      },
      {
        path: 'mot-de-passe-oublie',
        loadComponent: () =>
          import('./pages/forgot-password/forgot-password-page').then((m) => m.ForgotPasswordPageComponent),
        title: 'Mot de passe oublié — Kaikun 360',
      },
      { path: '', redirectTo: 'connexion', pathMatch: 'full' },
    ],
  },
];
