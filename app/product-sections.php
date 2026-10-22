<?php
// Section order of the public product page (product-details.html), set per product in the admin.
// Keys are stable ids matching data-section="…" in product-details.html — never labels or positions.
// The hero is locked first: it is enforced here, on the server, not only by the admin UI.

const PRODUCT_SECTIONS = [
    'hero'           => 'Hero',
    'gallery'        => 'Media Gallery',
    'applications'   => 'Applications',
    'certifications' => 'Certifications',
    'whyChoose'      => 'Why Choose MarkEn',
    'documentation'  => 'Documentation',
    'faq'            => 'FAQ',
];
const LOCKED_SECTION = 'hero';

function defaultSectionOrder(): array {
    return array_keys(PRODUCT_SECTIONS);
}

/**
 * Stored/legacy value → a complete order. Unknown and duplicate keys are dropped, the hero is
 * forced first, and sections added to PRODUCT_SECTIONS later are slotted in after the key that
 * precedes them in the default order, so existing custom orders keep working without a write.
 */
function normalizeSectionOrder(array $stored): array {
    $order = [LOCKED_SECTION];
    foreach ($stored as $key) {
        if (is_string($key) && isset(PRODUCT_SECTIONS[$key]) && !in_array($key, $order, true)) $order[] = $key;
    }
    $default = defaultSectionOrder();
    foreach ($default as $i => $key) {
        if (in_array($key, $order, true)) continue;
        array_splice($order, array_search($default[$i - 1], $order, true) + 1, 0, [$key]);
    }
    return $order;
}

/**
 * Strict check for an order submitted by the admin: every section exactly once, hero first.
 * Returns the order, or null when the request is malformed or tries to move the hero.
 */
function parseSectionOrder(mixed $submitted): ?array {
    if (!is_array($submitted) || !array_is_list($submitted)) return null;
    if (($submitted[0] ?? null) !== LOCKED_SECTION) return null;
    foreach ($submitted as $key) if (!is_string($key)) return null;
    $expected = defaultSectionOrder();
    if (count($submitted) !== count($expected) || count(array_unique($submitted)) !== count($expected)
        || array_diff($submitted, $expected)) return null;
    return $submitted;
}

/** DB value: NULL while the order is the default, so products follow future default changes. */
function sectionOrderForDb(array $order): ?string {
    return $order === defaultSectionOrder() ? null : toJson($order);
}
