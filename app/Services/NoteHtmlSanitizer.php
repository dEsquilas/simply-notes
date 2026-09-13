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
            ->allowAttribute('data-row', ['tr', 'td'])
            ->allowAttribute('contenteditable', 'span')
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
            // Base64 images make notes large: never truncate them
            ->withMaxInputLength(-1);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(?string $html): ?string
    {
        return $html === null ? null : $this->sanitizer->sanitize($html);
    }
}
