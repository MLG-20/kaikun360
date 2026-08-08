import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { SeoService } from './seo.service';

/**
 * Le référencement (F9.1) échoue toujours en silence : aucun écran ne montre
 * une balise manquante, aucune erreur n'est levée, et le symptôme n'apparaît
 * que des semaines plus tard dans un moteur ou dans un aperçu WhatsApp raté.
 * Ces tests verrouillent donc les comportements qu'aucune vérification au
 * navigateur ne rattraperait :
 *
 * - **les balises sont réécrites EN ENTIER** à chaque page (une application à
 *   page unique ne recharge pas le document : sans cela, la photo d'un bien
 *   resterait affichée en aperçu de la page Contact) ;
 * - **les URL sont absolues** (un robot n'a aucun contexte pour résoudre un
 *   chemin relatif) ;
 * - **le JSON-LD ne peut pas refermer sa propre balise** (la description d'une
 *   annonce est saisie par un propriétaire : c'est une entrée non fiable) ;
 * - **`clearJsonLd` nettoie sans emporter les balises d'`index.html`**.
 */
describe('SeoService', () => {
  const creer = () => {
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    return TestBed.inject(SeoService);
  };

  /** Contenu d'une balise `<meta>`, adressée par `name` ou par `property`. */
  const balise = (selecteur: string) =>
    document.head.querySelector<HTMLMetaElement>(`meta[${selecteur}]`)?.content ?? null;

  beforeEach(() => {
    document.head.querySelectorAll('meta[data-seo], link[rel="canonical"]').forEach((n) => n.remove());
    document.head.querySelectorAll('script[type="application/ld+json"]').forEach((n) => n.remove());
  });

  it('pose le jeu complet de balises, avec le suffixe de marque', () => {
    creer().apply({ title: 'Villa à Ngor', description: 'Une villa les pieds dans l\'eau.' });

    expect(document.title).toBe('Villa à Ngor — Kaikun 360');
    expect(balise("name='description'")).toBe('Une villa les pieds dans l\'eau.');
    expect(balise("property='og:title'")).toBe('Villa à Ngor — Kaikun 360');
    expect(balise("property='og:site_name'")).toBe('Kaikun 360');
    // Sans cette balise, X/Twitter affiche une vignette carrée illisible.
    expect(balise("name='twitter:card'")).toBe('summary_large_image');
  });

  it('ne double jamais le suffixe de marque quand le titre le porte déjà', () => {
    creer().apply({ title: 'Contact — Kaikun 360', description: 'Nous joindre.' });

    expect(document.title).toBe('Contact — Kaikun 360');
  });

  it('rend les URL absolues, et laisse intactes celles qui le sont déjà', () => {
    const seo = creer();

    // Image de l'API : déjà absolue, elle doit passer telle quelle.
    seo.apply({
      title: 'Bien',
      description: 'Description.',
      canonicalPath: '/immobilier/12',
      image: 'https://api.exemple.test/storage/photo.jpg',
    });
    expect(balise("property='og:image'")).toBe('https://api.exemple.test/storage/photo.jpg');
    expect(balise("property='og:url'")).toMatch(/^https?:\/\/.+\/immobilier\/12$/);

    // Image par défaut : chemin local, il doit être préfixé du domaine du site.
    seo.apply({ title: 'Autre', description: 'Description.' });
    expect(balise("property='og:image'")).toMatch(/^https?:\/\/.+\/og-image\.png$/);
  });

  it('remplace TOUTES les balises d\'une page à l\'autre, sans rien laisser traîner', () => {
    const seo = creer();

    seo.apply({
      title: 'Villa à Ngor',
      description: 'Une villa.',
      image: 'https://api.exemple.test/villa.jpg',
      type: 'product',
    });
    seo.apply({ title: 'Contact', description: 'Nous joindre.' });

    // ⚠️ Le défaut que ce test empêche : la photo de la villa restée en aperçu
    // de la page Contact, parce que les balises auraient été fusionnées au lieu
    // d'être réécrites.
    expect(balise("property='og:image'")).not.toContain('villa.jpg');
    expect(balise("property='og:type'")).toBe('website');
    expect(balise("name='description'")).toBe('Nous joindre.');
  });

  it('met hors index sur demande, mais laisse toujours suivre les liens', () => {
    const seo = creer();

    seo.apply({ title: 'Recherche', description: 'Résultats.', index: false });
    expect(balise("name='robots'")).toBe('noindex, follow');

    // La page suivante doit repasser en indexable : sans réécriture, tout le
    // site deviendrait invisible après un simple passage par la recherche.
    seo.apply({ title: 'Immobilier', description: 'Catalogue.' });
    expect(balise("name='robots'")).toBe('index, follow');
  });

  it('déplace le lien canonique au lieu d\'en accumuler', () => {
    const seo = creer();

    seo.apply({ title: 'A', description: 'A.', canonicalPath: '/a' });
    seo.apply({ title: 'B', description: 'B.', canonicalPath: '/b' });

    const liens = document.head.querySelectorAll('link[rel="canonical"]');
    expect(liens.length).toBe(1);
    expect(liens[0].getAttribute('href')).toMatch(/\/b$/);
  });

  it('tronque une description trop longue sur un mot entier', () => {
    const original = 'Lorem ipsum dolor '.repeat(30).trim();
    creer().apply({ title: 'Bien', description: original });

    const description = balise("name='description'") ?? '';
    expect(description.length).toBeLessThanOrEqual(161);
    expect(description.endsWith('…')).toBe(true);

    // ⚠️ La vraie propriété n'est pas « ça ne finit pas par une lettre » (un mot
    // entier finit forcément par une lettre) mais **« la coupe tombe sur une
    // frontière de mot »** : le texte conservé est un préfixe de l'original,
    // suivi dans l'original par une espace. Sans quoi Google afficherait
    // « … dolor Lor… ».
    const conserve = description.slice(0, -1);
    expect(original.startsWith(conserve)).toBe(true);
    expect(original.charAt(conserve.length)).toBe(' ');
  });

  it('neutralise une balise fermante glissée dans le JSON-LD', () => {
    const seo = creer();

    // Une description d'annonce est saisie par un propriétaire : c'est une
    // entrée non fiable, et `JSON.stringify` n'échappe PAS `</script>`.
    seo.setJsonLd('offre', { description: '</script><script>alert(1)</script>' });

    const script = document.head.querySelector('script[type="application/ld+json"]');
    expect(script?.textContent).not.toContain('</script>');
    expect(document.head.querySelectorAll('script').length).toBe(1);
  });

  it('remplace un bloc JSON-LD de même clé, et en cumule de clés différentes', () => {
    const seo = creer();

    seo.setJsonLd('offre', { name: 'premier' });
    seo.setJsonLd('offre', { name: 'second' });
    seo.setJsonLd('ariane', { name: 'fil' });

    const scripts = document.head.querySelectorAll('script[type="application/ld+json"]');
    expect(scripts.length).toBe(2);
    expect(scripts[0].textContent).toContain('second');
  });

  it('nettoie ses blocs JSON-LD sans toucher à ceux qu\'il n\'a pas posés', () => {
    const seo = creer();
    const etranger = document.createElement('script');
    etranger.type = 'application/ld+json';
    etranger.textContent = '{"@type":"PoseParUnTiers"}';
    document.head.appendChild(etranger);

    seo.setJsonLd('offre', { name: 'offre' });
    seo.clearJsonLd();

    const restants = document.head.querySelectorAll('script[type="application/ld+json"]');
    expect(restants.length).toBe(1);
    expect(restants[0].textContent).toContain('PoseParUnTiers');
    etranger.remove();
  });
});
