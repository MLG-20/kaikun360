import { formatRichText, sanitizeRichText } from './rich-text.sanitizer';

/**
 * L'assainisseur est la pièce la plus risquée de l'éditeur riche : il décide
 * de ce qui part sur le **site public**, et il est le seul endroit où l'on
 * peut perdre le travail d'un rédacteur. On l'éprouve donc sur les trois cas
 * réels : ce qu'on garde, ce qu'on jette, ce qu'on répare.
 */
describe('sanitizeRichText', () => {
  it('conserve le balisage éditorial autorisé', () => {
    const html = '<h2>Titre</h2><p>Un <strong>mot</strong> et un <em>autre</em>.</p><ul><li>Point</li></ul>';
    expect(sanitizeRichText(html)).toBe(html);
  });

  it('normalise les balises produites par les navigateurs', () => {
    // `execCommand` génère encore <b>/<i> ; on stocke du sémantique.
    expect(sanitizeRichText('<p><b>gras</b> <i>italique</i></p>')).toBe(
      '<p><strong>gras</strong> <em>italique</em></p>',
    );
  });

  it('ramène un h1 à un h2', () => {
    // Le titre de la page est déjà un h1 : jamais deux h1 sur une page.
    expect(sanitizeRichText('<h1>Mentions légales</h1>')).toBe('<h2>Mentions légales</h2>');
  });

  it('déballe les balises de mise en page sans perdre le texte', () => {
    // Cas du collé depuis Word : on garde la rédaction, pas l'habillage.
    const colle = '<span style="mso-fareast:EN">Texte <font color="red">important</font></span>';
    expect(sanitizeRichText(colle)).toBe('<p>Texte important</p>');
  });

  it('retire les attributs, y compris les gestionnaires d’événements', () => {
    expect(sanitizeRichText('<p onclick="alert(1)" class="x" style="color:red">Bonjour</p>')).toBe(
      '<p>Bonjour</p>',
    );
  });

  it('supprime les scripts et leur contenu', () => {
    expect(sanitizeRichText('<p>Avant</p><script>alert(1)</script><p>Après</p>')).toBe(
      '<p>Avant</p><p>Après</p>',
    );
  });

  it('refuse une adresse de lien exécutable, en gardant le libellé', () => {
    expect(sanitizeRichText('<p><a href="javascript:alert(1)">Cliquez</a></p>')).toBe('<p>Cliquez</p>');
  });

  it('accepte les liens internes relatifs sans les ouvrir dans un onglet neuf', () => {
    expect(sanitizeRichText('<p><a href="/nuitees">Nos nuitées</a></p>')).toBe(
      '<p><a href="/nuitees">Nos nuitées</a></p>',
    );
  });

  it('protège les liens sortants (onglet neuf + noopener)', () => {
    expect(sanitizeRichText('<p><a href="https://exemple.sn">Site</a></p>')).toBe(
      '<p><a href="https://exemple.sn" target="_blank" rel="noopener noreferrer">Site</a></p>',
    );
  });

  it('enveloppe dans un paragraphe le texte laissé à la racine', () => {
    // Sans cela, un agent qui tape sans cliquer sur un bouton obtiendrait un
    // pavé sans paragraphes sur le site public.
    expect(sanitizeRichText('Texte nu')).toBe('<p>Texte nu</p>');
  });

  it('déballe un bloc qui en contient un autre', () => {
    // `<p><p>…</p></p>` est invalide et casse la mise en page publique.
    expect(sanitizeRichText('<div><p>Un</p><p>Deux</p></div>')).toBe('<p>Un</p><p>Deux</p>');
  });

  it('jette les blocs vides laissés par un collé', () => {
    expect(sanitizeRichText('<p>Utile</p><p></p><h2>   </h2>')).toBe('<p>Utile</p>');
  });

  it('est idempotent : relire puis réenregistrer ne dégrade pas le contenu', () => {
    // Garantie indispensable : une page rouverte dix fois doit rester la même.
    const source = '<h2>Titre</h2><div>Texte <b>fort</b></div><a href="mailto:a@b.sn">écrire</a>';
    const once = sanitizeRichText(source);
    expect(sanitizeRichText(once)).toBe(once);
  });

  it('accepte une valeur vide sans lever', () => {
    expect(sanitizeRichText('')).toBe('');
  });
});

describe('formatRichText', () => {
  it('met un bloc par ligne pour la vue « code HTML »', () => {
    expect(formatRichText('<h2>Titre</h2><p>Texte</p>')).toBe('<h2>Titre</h2>\n<p>Texte</p>');
  });

  it('garde les balises de caractère sur la même ligne que leur texte', () => {
    expect(formatRichText('<p>Un <strong>mot</strong> fort</p>')).toBe(
      '<p>Un <strong>mot</strong> fort</p>',
    );
  });
});
