import { deciderDefilement } from './scroll-behavior';

/**
 * La politique de défilement (F8.20) tient en quatre cas, et c'est le dernier
 * qui a motivé le correctif : **filtrer un catalogue ne doit pas remonter la
 * page**. Les filtres vivant dans l'URL, chaque saisie était une navigation, et
 * Angular remontait en haut à chaque fois — le visiteur devait redéfiler
 * jusqu'aux résultats à chaque essai.
 */
describe('politique de défilement', () => {
  it('restaure la position mémorisée au retour du navigateur', () => {
    expect(
      deciderDefilement({ position: [0, 820], ancre: null, memePage: false }),
    ).toBe('position');
  });

  it('privilégie la position mémorisée sur tout le reste', () => {
    // Retour arrière vers une URL à ancre : c'est l'écran quitté qu'on veut
    // retrouver, pas le début de la section.
    expect(
      deciderDefilement({ position: [0, 400], ancre: 'nuitees', memePage: false }),
    ).toBe('position');
  });

  it('va à l’ancre demandée (tuiles d’univers de l’accueil)', () => {
    expect(
      deciderDefilement({ position: null, ancre: 'nuitees', memePage: false }),
    ).toBe('ancre');
  });

  it('remonte en haut sur un vrai changement de page', () => {
    expect(deciderDefilement({ position: null, ancre: null, memePage: false })).toBe('haut');
  });

  it('NE TOUCHE À RIEN quand seuls les paramètres d’URL changent', () => {
    // Le cœur du correctif : filtre, tri ou pagination sur la même page.
    expect(deciderDefilement({ position: null, ancre: null, memePage: true })).toBe('rien');
  });

  it('respecte une ancre même sur la page courante', () => {
    // Un lien « aller à la section » depuis la page elle-même reste légitime.
    expect(deciderDefilement({ position: null, ancre: 'contact', memePage: true })).toBe('ancre');
  });
});
