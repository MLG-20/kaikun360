// =============================================================================
// Assainissement du HTML éditorial (F8.3)
//
// L'éditeur riche produit du HTML, et ce HTML finit dans `[innerHTML]` sur le
// site public (page `/pages/:slug`). Angular assainit déjà au rendu — mais
// assainir *à la source* règle trois problèmes qu'Angular ne règle pas :
//
//   1. **le collé depuis Word / Google Docs** : un copier-coller apporte des
//      `<span style="mso-…">`, des `<font>`, des tableaux de mise en page. Le
//      site les affiche fidèlement… et la page ne ressemble plus à Kaikun ;
//   2. **la stabilité du stockage** : ce qu'on relit dans l'éditeur doit être
//      exactement ce qu'on avait enregistré, sinon chaque ouverture de la fiche
//      dégrade un peu le contenu ;
//   3. **la charte** : n'autoriser que les balises que la feuille de style
//      publique sait présenter (`.content-prose`) garantit qu'une page saisie
//      par un agent reste lisible sans qu'il connaisse le HTML.
//
// La liste est donc une **liste blanche stricte** : tout ce qui n'y figure pas
// est « déballé » (on garde le texte, on jette la balise) plutôt que supprimé —
// on ne perd jamais le travail de rédaction de l'agent.
// =============================================================================

/** Balises conservées telles quelles. Tout le reste est déballé. */
const ALLOWED = new Set([
  'P',
  'BR',
  'STRONG',
  'EM',
  'U',
  'H2',
  'H3',
  'H4',
  'UL',
  'OL',
  'LI',
  'A',
  'BLOCKQUOTE',
]);

/**
 * Équivalences : ce que les navigateurs produisent → ce qu'on stocke.
 *
 * `document.execCommand` génère encore `<b>` / `<i>` (et parfois `<div>` en
 * guise de paragraphe). On normalise à l'écriture pour ne pas stocker deux
 * balisages différents selon le navigateur de l'agent.
 */
const ALIASES: Record<string, string> = {
  B: 'STRONG',
  I: 'EM',
  DIV: 'P',
  H1: 'H2', // le titre de la page est déjà un h1 : jamais deux h1 sur une page
  H5: 'H4',
  H6: 'H4',
};

/** Balises dont on jette *aussi* le contenu (il n'est pas du texte lisible). */
const DROPPED_WITH_CONTENT = new Set(['SCRIPT', 'STYLE', 'HEAD', 'TITLE', 'NOSCRIPT', 'IFRAME', 'OBJECT']);

/** Balises de bloc : servent à détecter un `<p>` qui en contiendrait un autre. */
const BLOCKS = new Set(['P', 'H2', 'H3', 'H4', 'UL', 'OL', 'LI', 'BLOCKQUOTE']);

/** Balises qui ne peuvent contenir que du bloc (un `<ul>` n'a que des `<li>`). */
const LIST_CONTAINERS = new Set(['UL', 'OL']);

/**
 * Valide une adresse de lien.
 *
 * On refuse `javascript:` et `data:` (vecteurs d'exécution), et on accepte les
 * liens internes relatifs — un agent qui renvoie vers `/nuitees` depuis les CGU
 * fait exactement ce qu'on attend de lui.
 */
function safeHref(raw: string | null): string | null {
  const href = (raw ?? '').trim();
  if (!href) {
    return null;
  }
  if (href.startsWith('/') || href.startsWith('#')) {
    return href;
  }
  return /^(https?:|mailto:|tel:)/i.test(href) ? href : null;
}

/** Un nœud ne portant que des espaces n'apporte rien au document. */
function isBlank(node: Node): boolean {
  return node.nodeType === Node.TEXT_NODE && !(node.nodeValue ?? '').trim();
}

/**
 * Reconstruit récursivement un arbre propre à partir d'un arbre quelconque.
 *
 * On **reconstruit** au lieu de retirer les attributs sur place : c'est la
 * seule façon d'être sûr qu'aucun attribut exotique (gestionnaire d'événement,
 * `style`, attribut `data-*` d'un traitement de texte) ne survit à l'opération.
 */
function cleanChildren(source: Node, doc: Document): DocumentFragment {
  const fragment = doc.createDocumentFragment();

  source.childNodes.forEach((child) => {
    if (child.nodeType === Node.TEXT_NODE) {
      fragment.appendChild(doc.createTextNode(child.nodeValue ?? ''));
      return;
    }
    if (child.nodeType !== Node.ELEMENT_NODE) {
      return; // commentaires, instructions de traitement : rien à conserver
    }

    const element = child as Element;
    const original = element.tagName.toUpperCase();

    if (DROPPED_WITH_CONTENT.has(original)) {
      return;
    }

    const inner = cleanChildren(element, doc);
    const tag = ALIASES[original] ?? original;

    // Balise inconnue (span, font, table…) : on garde le texte, pas la balise.
    if (!ALLOWED.has(tag)) {
      fragment.appendChild(inner);
      return;
    }

    // Un lien sans adresse exploitable n'est plus un lien, juste du texte.
    if (tag === 'A') {
      const href = safeHref(element.getAttribute('href'));
      if (!href) {
        fragment.appendChild(inner);
        return;
      }
      const anchor = doc.createElement('a');
      anchor.setAttribute('href', href);
      // Un lien sortant s'ouvre dans un onglet neuf ; `noopener` évite que la
      // page ouverte puisse manipuler la nôtre.
      if (/^https?:/i.test(href)) {
        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener noreferrer');
      }
      anchor.appendChild(inner);
      fragment.appendChild(anchor);
      return;
    }

    // `<p><p>texte</p></p>` est invalide et casse la mise en page publique.
    // Cas typique : un `<div>` de mise en page contenant de vrais paragraphes.
    // On déballe le conteneur et on laisse remonter ses blocs.
    if (BLOCKS.has(tag) && !LIST_CONTAINERS.has(tag) && containsBlock(inner)) {
      fragment.appendChild(inner);
      return;
    }

    const clean = doc.createElement(tag.toLowerCase());
    clean.appendChild(inner);

    // Une liste vide, un titre vide : bruit visuel laissé par un collé.
    if (tag !== 'BR' && !clean.textContent?.trim()) {
      return;
    }

    fragment.appendChild(clean);
  });

  return fragment;
}

/** Le fragment contient-il un élément de bloc à son premier niveau ? */
function containsBlock(fragment: DocumentFragment): boolean {
  return Array.from(fragment.childNodes).some(
    (node) => node.nodeType === Node.ELEMENT_NODE && BLOCKS.has((node as Element).tagName.toUpperCase()),
  );
}

/**
 * Enveloppe dans un `<p>` le texte resté à la racine.
 *
 * Sans cela, un agent qui tape trois lignes sans jamais cliquer sur un bouton
 * obtiendrait un pavé sans paragraphes sur le site public — l'un des symptômes
 * qu'on cherche précisément à faire disparaître.
 */
function wrapLooseText(fragment: DocumentFragment, doc: Document): DocumentFragment {
  const result = doc.createDocumentFragment();
  let paragraph: HTMLElement | null = null;

  const flush = (): void => {
    if (paragraph && paragraph.textContent?.trim()) {
      result.appendChild(paragraph);
    }
    paragraph = null;
  };

  Array.from(fragment.childNodes).forEach((node) => {
    const isBlockElement =
      node.nodeType === Node.ELEMENT_NODE && BLOCKS.has((node as Element).tagName.toUpperCase());

    if (isBlockElement) {
      flush();
      result.appendChild(node);
      return;
    }
    if (isBlank(node) && !paragraph) {
      return;
    }
    paragraph ??= doc.createElement('p');
    paragraph.appendChild(node);
  });

  flush();
  return result;
}

/**
 * Assainit un fragment HTML éditorial.
 *
 * Sans DOM (rendu serveur), on renvoie la valeur telle quelle : l'assainissement
 * a lieu à la saisie et à l'enregistrement, tous deux côté navigateur, et le
 * rendu public passe de toute façon par l'assainisseur d'Angular.
 */
export function sanitizeRichText(html: string): string {
  if (typeof document === 'undefined') {
    return html;
  }

  const parsed = new DOMParser().parseFromString(`<body>${html ?? ''}</body>`, 'text/html');
  const cleaned = wrapLooseText(cleanChildren(parsed.body, document), document);

  const host = document.createElement('div');
  host.appendChild(cleaned);
  return host.innerHTML.trim();
}

/**
 * Rend un fragment HTML lisible pour l'œil humain (vue « code HTML »).
 *
 * Un `innerHTML` arrive sur une seule ligne interminable ; on met un bloc par
 * ligne. Purement cosmétique — la valeur enregistrée est réassainie ensuite.
 */
export function formatRichText(html: string): string {
  return sanitizeRichText(html)
    .replace(/></g, '>\n<')
    .replace(/\n<(\/?(strong|em|u|a|br)\b)/gi, '<$1')
    .trim();
}
