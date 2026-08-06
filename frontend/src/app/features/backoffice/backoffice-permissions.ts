/**
 * Cloisonnement des rubriques du back-office par permission (F7.4.a).
 *
 * SOURCE DE VÉRITÉ UNIQUE, partagée par :
 *   - `backoffice.routes.ts` (la garde `permissionGuard` de chaque route) ;
 *   - `backoffice-layout.ts` (le filtrage du rail de navigation).
 *
 * Les deux DOIVENT lire la même table : un rail qui masque une rubrique que la
 * route laisse passer (ou l'inverse) produit exactement le genre d'incohérence
 * qu'on cherche à supprimer — un lien invisible mais atteignable à l'URL, ou un
 * lien cliquable qui rebondit.
 *
 * Règle de lecture : tableau **vide = rubrique ouverte à toute l'équipe** ;
 * sinon il suffit de détenir **une** des permissions listées.
 *
 * Le découpage suit une ligne simple :
 *   - les écrans de SUPERVISION en lecture seule (Catalogues, Mobilité,
 *     Tourisme) restent ouverts à toute l'équipe — leurs endpoints sont gardés
 *     par `consulter:dashboard-admin`, que porte le rôle ; voir sans pouvoir
 *     agir est précisément le métier d'un agent qui prépare un dossier ;
 *   - les écrans qui portent des GESTES exigent la permission de ce geste,
 *     celle-là même que le serveur vérifiera.
 *
 * ⚠️ Rappel : ceci ne remplace aucune sécurité. Chaque route `/admin/…` reste
 * gardée par son `can:` côté Laravel. Masquer le rail évite le mur de 403 promis
 * par le CDC §7 à l'agent (« accès financier limité »), il ne le fabrique pas.
 */
export const BO_PERMISSIONS: Readonly<Record<string, readonly string[]>> = {
  // Vue d'ensemble : indicateurs agrégés, servis par `consulter:dashboard-admin`
  // que tout membre de l'équipe possède. C'est aussi l'écran de repli du guard,
  // il ne doit donc JAMAIS être fermé, sous peine de boucle de redirection.
  '': [],

  // File de traitement des demandes clients (F8.9) : l'écran EST un geste —
  // on y fait avancer un dossier. Il porte donc la permission que le serveur
  // vérifiera, la même qui garde déjà `PATCH /requests/{id}/status` et la file
  // team building depuis F7.4.b.
  demandes: ['traiter:demandes'],

  // Messagerie du support (F8.12) : l'écran EST un geste — on y répond au
  // client. Permission dédiée, qui sert AUSSI de vivier d'assignation côté
  // serveur : ne la voient que ceux qui sont réellement de permanence.
  messages: ['repondre:messages'],

  // File d'approbation : la décision exige une permission fine par type. Sans
  // aucune des quatre, l'écran ne serait qu'une liste de boutons en 403.
  validation: ['valider:bien', 'valider:vehicule', 'valider:experience', 'valider:prestataire'],

  // Supervision en lecture seule (cf. en-tête) : ouvertes à toute l'équipe.
  catalogues: [],
  mobilite: [],
  tourisme: [],

  // Exploitation : chaque écran demande la permission de son geste.
  nuitees: ['gerer:nuitees'],
  construction: ['gerer:chantiers'],
  'gestion-locative': ['gerer:gestion-locative'],

  // Avis & qualité agrège deux moitiés (modération des avis / sanctions
  // prestataires) servies par deux permissions distinctes : l'une suffit,
  // l'écran s'ouvre alors sur l'onglet accessible.
  qualite: ['moderer:avis', 'valider:prestataire'],

  // Team building : depuis F7.4.b la fiche est gardée par `traiter:demandes`
  // (et non plus par le rôle admin), conformément au CDC §7.
  'team-building': ['traiter:demandes'],

  // Diaspora reste ouverte à toute l'équipe : la fiche est gardée côté serveur
  // par « l'agent AFFECTÉ au dossier ou un admin ». Exiger ici une permission
  // fermerait la porte à l'agent dédié, qui est justement celui qui doit entrer.
  diaspora: [],

  // Gouvernance : les trois leviers sensibles du CDC §7.
  comptes: ['gerer:utilisateurs'],
  equipe: ['gerer:utilisateurs'],
  permissions: ['gerer:utilisateurs'],
  paiements: ['gerer:paiements'],
  // Reversements (F8.16.a) : MÊME permission que Paiements, volontairement.
  // Reverser, c'est sortir de l'argent — exactement la nature d'acte que garde
  // déjà `gerer:paiements`. Un droit distinct aurait dispersé la décision
  // financière sur deux permissions qu'on aurait de toute façon accordées
  // ensemble, et fabriqué un agent qui peut virer sans pouvoir rembourser.
  reversements: ['gerer:paiements'],
  parametres: ['gerer:parametres'],

  // Pointeuse : périmètre PERSONNEL (on pointe pour soi). Toute l'équipe y a
  // droit — la feuille d'équipe, elle, est filtrée dans l'écran.
  pointeuse: [],
};

/**
 * Permissions exigées par une rubrique, à partir de son chemin relatif.
 * Les fiches de détail (`construction/:id`) héritent de leur liste.
 */
export function permissionsFor(path: string): readonly string[] {
  return BO_PERMISSIONS[path] ?? [];
}
