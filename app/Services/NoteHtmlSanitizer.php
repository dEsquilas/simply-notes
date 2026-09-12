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
                // Returns '' instead of null: the URL sanitizer runs next and fails on null, then drops ''.
                public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
                {
                    return str_starts_with(strtolower(trim($value)), 'https://') ? $value : '';
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
