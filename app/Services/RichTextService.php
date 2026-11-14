<?php

namespace App\Services;

use App\Support\EditorStyleSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class RichTextService
{
    public function sanitize(string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowElement('img', ['src', 'alt', 'width', 'height', 'data-align'])
            ->allowAttribute('style', ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])
            ->withAttributeSanitizer(new EditorStyleSanitizer)
            ->allowRelativeMedias()
            ->allowMediaSchemes(['https', 'http'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(100000);

        return (new HtmlSanitizer($config))->sanitize($html);
    }
}
