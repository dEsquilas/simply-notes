<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Cleans note HTML before it is stored: the editor renders it with innerHTML,
 * so scripts, event handlers and dangerous URLs must never reach the database.
 */
class NoteHtmlSanitizer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            // Quill video embeds
            ->allowElement('iframe', ['src', 'class', 'frameborder', 'allowfullscreen'])
            // Evernote and SVG elements are unwrapped instead of dropped, so notes never lose their text
            ->blockElement('en-codeblock')
            ->blockElement('en-note')
            ->blockElement('en-media')
            ->blockElement('en-crypt')
            ->blockElement('en-todo')
            ->blockElement('svg')
            // Quill markup: ql-* classes, list type, list UI spans and table rows
            ->allowAttribute('class', '*')
            ->allowAttribute('data-list', 'li')
            ->allowAttribute('data-row', ['tr', 'td', 'th'])
            // table-better's own cell identifier, used to track cells across merges/edits
            ->allowAttribute('data-cell', '*')
            ->allowAttribute('contenteditable', 'span')
            // quill-table-better cell/column/table sizing and borders/colors (width, height,
            // colspan and rowspan are already allowed globally by allowSafeElements())
            ->allowAttribute('style', ['table', 'td', 'th', 'col', 'colgroup'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            // Images are embedded as base64 data URIs
            ->allowMediaSchemes(['http', 'https', 'data'])
            ->withAttributeSanitizer(new class implements AttributeSanitizerInterface
            {
                public function getSupportedElements(): ?array
                {
                    return ['iframe'];
                }

                public function getSupportedAttributes(): ?array
                {
                    return ['src'];
                }

                // Media schemes allow data: for images, but an iframe must only load https pages.
                // The scheme is lowercased because the URL sanitizer that runs next only accepts it that way.
                // Returns '' instead of null: that URL sanitizer fails on null, then drops ''.
                public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
                {
                    $value = trim($value);

                    return str_starts_with(strtolower($value), 'https://') ? 'https://'.substr($value, 8) : '';
                }
            })
            ->withAttributeSanitizer(new class implements AttributeSanitizerInterface
            {
                // The only CSS quill-table-better writes: cell/column/table borders, background,
                // sizing and text alignment. Rebuilding the attribute from this whitelist means
                // nothing else (an "expression()", a "url()", a "position: fixed") ever gets through.
                private const ALLOWED_PROPERTIES = [
                    'border-style', 'border-color', 'border-width',
                    'background-color', 'width', 'height', 'padding',
                    'text-align', 'vertical-align',
                ];

                public function getSupportedElements(): ?array
                {
                    return ['table', 'td', 'th', 'col', 'colgroup'];
                }

                public function getSupportedAttributes(): ?array
                {
                    return ['style'];
                }

                public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
                {
                    $declarations = [];

                    foreach (explode(';', $value) as $declaration) {
                        if (! str_contains($declaration, ':')) {
                            continue;
                        }

                        [$property, $propertyValue] = array_map('trim', explode(':', $declaration, 2));
                        $property = strtolower($property);

                        if (! in_array($property, self::ALLOWED_PROPERTIES, true)) {
                            continue;
                        }

                        // No "(", "/" or ":" allowed in the value: enough to keep out url(), // comments and javascript:
                        if ($propertyValue === '' || ! preg_match('/^[a-z0-9#.,%\s-]+$/i', $propertyValue)) {
                            continue;
                        }

                        $declarations[] = "{$property}: {$propertyValue}";
                    }

                    return $declarations === [] ? null : implode('; ', $declarations);
                }
            })
            // Base64 images make notes large: never truncate them
            ->withMaxInputLength(-1);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(?string $html): ?string
    {
        return $html === null ? null : $this->sanitizer->sanitize($html);
    }
}
