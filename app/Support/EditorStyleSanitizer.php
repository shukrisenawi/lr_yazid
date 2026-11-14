<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

class EditorStyleSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return preg_match('/(?:^|;)\s*text-align:\s*(left|center|right|justify)\s*(?:;|$)/i', $value, $matches)
            ? 'text-align: '.strtolower($matches[1]).';'
            : null;
    }
}
