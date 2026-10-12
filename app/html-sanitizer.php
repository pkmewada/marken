<?php
// Allowlist sanitizer for blog rich text. Runs when the admin saves a post AND when api/blog.php
// serves it, so content edited directly in the DB still cannot run script on the public site.

const SANITIZE_ALLOWED = [
    'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
    'u' => [], 's' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [],
    'figure' => [], 'figcaption' => [], 'a' => ['href', 'title'], 'img' => ['src', 'alt'],
];
// Removed together with everything inside them (other unknown tags are unwrapped, keeping their text).
const SANITIZE_DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea',
    'select', 'svg', 'math', 'template', 'noscript', 'head', 'title', 'meta', 'link', 'video', 'audio', 'canvas'];

/** http(s)/mailto/tel, in-page anchors, or a scheme-less site-relative path (uploads/…, products/…). */
function isSafeUrl(string $url, bool $forImage = false): bool {
    $url = trim($url);
    if ($url === '' || preg_match('/[\s<>"\'`\\\\]/', $url)) return false;
    if (preg_match('#^https?://#i', $url)) return true;
    if ($forImage) return !preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $url);
    return (bool)preg_match('#^(mailto:|tel:|\#)#i', $url) || !preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $url);
}

function sanitizeHtml(string $html): string {
    if (trim($html) === '') return '';
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="sanitize-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $doc->getElementById('sanitize-root');
    if (!$root) return '';
    sanitizeChildren($root);

    $out = '';
    foreach ($root->childNodes as $child) $out .= $doc->saveHTML($child);
    return trim($out);
}

function sanitizeChildren(DOMNode $parent): void {
    foreach (iterator_to_array($parent->childNodes) as $node) {
        if ($node instanceof DOMText) continue;
        if (!$node instanceof DOMElement) { $parent->removeChild($node); continue; }   // comments, PIs

        $tag = strtolower($node->tagName);
        if (in_array($tag, SANITIZE_DROP, true)) { $parent->removeChild($node); continue; }

        // contenteditable sometimes produces <div> lines; keep them as paragraphs. The page title is the
        // only <h1>, so pasted h1s become section headings.
        $rename = match (true) {
            $tag === 'div' && !$node->getElementsByTagName('p')->length && !$node->getElementsByTagName('div')->length => 'p',
            $tag === 'h1' => 'h2',
            default => null,
        };
        if ($rename) {
            $el = $node->ownerDocument->createElement($rename);
            while ($node->firstChild) $el->appendChild($node->firstChild);
            $parent->replaceChild($el, $node);
            $node = $el;
            $tag = $rename;
        }

        sanitizeChildren($node);

        if (!isset(SANITIZE_ALLOWED[$tag])) {          // span, font, div, h1, table… → keep the text only
            while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
            $parent->removeChild($node);
            continue;
        }

        foreach (iterator_to_array($node->attributes) as $attr) {
            if (!in_array(strtolower($attr->name), SANITIZE_ALLOWED[$tag], true)) $node->removeAttribute($attr->name);
        }
        if ($tag === 'a') {
            $href = $node->getAttribute('href');
            if ($href !== '' && !isSafeUrl($href)) $node->removeAttribute('href');
            if (preg_match('#^https?://#i', $node->getAttribute('href'))) {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
        if ($tag === 'img') {
            if (!isSafeUrl($node->getAttribute('src'), true)) { $parent->removeChild($node); continue; }
            $node->setAttribute('loading', 'lazy');
        }
    }
}
