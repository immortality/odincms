<?php

namespace App\Cms\Support;

use DOMComment;
use DOMElement;
use DOMNode;
use Masterminds\HTML5;

/**
 * Cleans HTML from the editor on save, keeping the design: every element and attribute stays —
 * classes, ids, data-*, inline styles, <style>, inline SVG (case kept: viewBox, linearGradient),
 * empty icon tags (<i class="bi bi-star"></i>), buttons, layout blocks, embeds — and only active content
 * that could run script is removed:
 *
 *  - elements: script, object, embed, applet, base, meta, link, frame(set), noscript, SVG foreignObject
 *    and SVG animations that target links (<set>, <animate attributeName="href">); comments;
 *  - attributes: on* event handlers, srcdoc;
 *  - URLs (href, src, action, formaction, xlink:href, poster, …) other than http(s), mailto, tel,
 *    relative and #anchors (src may also be a data:image of a bitmap type); iframes whose src does not
 *    match config('cms.safe_iframe') (YouTube, Vimeo, Google Maps embeds by default);
 *  - CSS (style attributes and <style>) with javascript:, expression(), behavior or -moz-binding.
 *
 * HtmlSanitizer::clean($html) — a string, or an array of strings (translations).
 */
class HtmlSanitizer
{
    private const REMOVED_ELEMENTS = [
        'script', 'object', 'embed', 'applet', 'base', 'meta', 'link', 'frame', 'frameset', 'noscript',
        'foreignobject', 'set',
    ];

    private const ANIMATIONS = ['animate', 'animatemotion', 'animatetransform'];

    private const URL_ATTRIBUTES = [
        'href', 'src', 'action', 'formaction', 'xlink:href', 'poster', 'background', 'cite', 'longdesc', 'data', 'ping', 'manifest',
    ];

    private const DANGEROUS_CSS = '/javascript\s*:|expression\s*\(|behavior\s*:|-moz-binding/i';

    /**
     * @param  string|array<array-key, mixed>|null  $html
     * @return ($html is string ? string : ($html is array ? array<array-key, mixed> : null))
     */
    public static function clean(string|array|null $html): string|array|null
    {
        if (is_array($html)) {
            return array_map(fn ($value) => is_string($value) || is_array($value) ? self::clean($value) : $value, $html);
        }

        if ($html === null || trim($html) === '') {
            return $html;
        }

        $html5 = new HTML5(['disable_html_ns' => true]);
        $fragment = $html5->loadHTMLFragment($html);

        foreach (iterator_to_array($fragment->childNodes) as $node) {
            self::cleanNode($node);
        }

        return $html5->saveHTML($fragment);
    }

    private static function cleanNode(DOMNode $node): void
    {
        if ($node instanceof DOMComment) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (! $node instanceof DOMElement) {
            return;
        }

        $name = strtolower($node->nodeName);

        if (in_array($name, self::REMOVED_ELEMENTS, true) || self::isLinkAnimation($node, $name)
            || ($name === 'style' && preg_match(self::DANGEROUS_CSS, (string) $node->textContent))
            || ($name === 'iframe' && ! self::isSafeIframe($node->getAttribute('src')))) {
            $node->parentNode?->removeChild($node);

            return;
        }

        foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
            $attr = strtolower($attribute->nodeName);
            $value = (string) $attribute->nodeValue;

            $remove = str_starts_with($attr, 'on')
                || $attr === 'srcdoc'
                || ($attr === 'style' && preg_match(self::DANGEROUS_CSS, $value))
                || (in_array($attr, self::URL_ATTRIBUTES, true) && ! self::isSafeUrl($value, $attr === 'src'));

            if ($remove) {
                $node->removeAttributeNode($attribute);
            }
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            self::cleanNode($child);
        }
    }

    /**
     * An SVG animation that changes a link (it could set href to javascript:).
     */
    private static function isLinkAnimation(DOMElement $node, string $name): bool
    {
        return in_array($name, self::ANIMATIONS, true) && str_contains(strtolower($node->getAttribute('attributeName')), 'href');
    }

    private static function isSafeUrl(string $url, bool $isSource): bool
    {
        // Browsers ignore control characters and spaces inside the scheme ("java\tscript:").
        $url = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $url));

        if ($url === '' || ! preg_match('/^([a-z][a-z0-9+.-]*):/', $url, $match)) {
            return true; // relative, /path, #anchor, ?query
        }

        return in_array($match[1], ['http', 'https', 'mailto', 'tel'], true)
            || ($isSource && preg_match('#^data:image/(png|jpe?g|gif|webp|avif);#', $url) === 1);
    }

    /**
     * An iframe src allowed by config('cms.safe_iframe') (YouTube, Vimeo, Google Maps by default).
     */
    private static function isSafeIframe(string $url): bool
    {
        return preg_match((string) config('cms.safe_iframe'), trim($url)) === 1;
    }
}
