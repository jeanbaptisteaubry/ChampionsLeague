<?php
declare(strict_types=1);

// Helpers globaux

if (!function_exists('sanitize_html')) {
    function sanitize_html(string $html): string
    {
        // Si DOM non disponible, fallback minimal
        if (!class_exists('\DOMDocument')) {
            $allowed = '<p><br><strong><b><em><i><u><a><ul><ol><li><h2><h3><blockquote>';
            $clean = strip_tags($html, $allowed);
            // Supprimer href dangereux
            $clean = preg_replace('/href\s*=\s*"(?!https?:|mailto:)[^"]*"/i', 'href="#"', (string)$clean);
            return (string)$clean;
        }

        $allowedTags = ['p','br','strong','b','em','i','u','a','ul','ol','li','h2','h3','blockquote'];
        $allowedAttrs = [ 'a' => ['href','title'] ];

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        // Charger HTML en UTF-8 sans injection de balises html/head/body ajoutées
        $markup = '<?xml encoding="utf-8" ?>' . $html;
        libxml_use_internal_errors(true);
        $dom->loadHTML($markup, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $walker = function(\DOMNode $node) use (&$walker, $allowedTags, $allowedAttrs): void {
            if ($node instanceof \DOMElement) {
                $tag = strtolower($node->tagName);
                if (!in_array($tag, $allowedTags, true)) {
                    // Remplacer le noeud par ses enfants (unwrap)
                    $parent = $node->parentNode;
                    if ($parent) {
                        while ($node->firstChild) { $parent->insertBefore($node->firstChild, $node); }
                        $parent->removeChild($node);
                    }
                    return; // enfants déjà déplacés
                }
                // Nettoyer attributs non autorisés
                if (!empty($node->attributes)) {
                    for ($i = $node->attributes->length - 1; $i >= 0; $i--) {
                        $attr = $node->attributes->item($i);
                        if (!$attr) continue;
                        $aname = strtolower($attr->name);
                        $keep = isset($allowedAttrs[$tag]) && in_array($aname, $allowedAttrs[$tag], true);
                        if (!$keep) {
                            $node->removeAttributeNode($attr);
                        }
                    }
                }
                if ($tag === 'a') {
                    $href = $node->getAttribute('href');
                    if ($href === '' || !preg_match('/^(https?:|mailto:)/i', $href)) {
                        $node->removeAttribute('href');
                    }
                }
            }
            // Parcourir une copie de la liste car on peut modifier la structure
            $children = [];
            foreach ($node->childNodes as $child) { $children[] = $child; }
            foreach ($children as $child) { $walker($child); }
        };

        $walker($dom);

        // Extraire HTML
        $htmlOut = '';
        foreach ($dom->childNodes as $child) {
            $htmlOut .= $dom->saveHTML($child);
        }
        return $htmlOut ?? '';
    }
}
