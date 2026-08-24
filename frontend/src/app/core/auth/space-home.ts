import { User } from '../../models/user.model';

/**
 * Espace personnel **principal** d'un utilisateur selon son rôle.
 *
 * Chaque profil a son propre espace connecté ; « Mon espace » (en-tête) et la
 * redirection après connexion doivent mener l'utilisateur au SIEN, pas
 * systématiquement à l'espace client. Un propriétaire qui se connecte atterrit
 * ainsi dans `/espace-proprietaire`, et non plus dans `/mon-espace`.
 *
 * L'ordre de priorité gère les comptes **multi-rôles** (un propriétaire est
 * aussi client au sens technique) : on renvoie l'espace le plus spécifique.
 * L'espace client est le repli par défaut (tout compte authentifié y a accès).
 */
export function spaceHomeFor(user: User | null): string {
  // Membres de l'équipe back-office (agent / admin / super_admin) : leur espace
  // est le poste de commandement, prioritaire sur tout rôle « public ».
  const staff = ['agent_kaikun', 'admin', 'super_admin'];
  if (user?.roles?.some((role) => staff.includes(role))) {
    return '/back-office';
  }
  if (user?.roles?.includes('proprietaire')) {
    return '/espace-proprietaire';
  }
  if (user?.roles?.includes('prestataire')) {
    return '/espace-prestataire';
  }
  if (user?.roles?.includes('entreprise')) {
    return '/espace-entreprise';
  }
  if (user?.roles?.includes('diaspora')) {
    return '/espace-diaspora';
  }
  return '/mon-espace';
}
