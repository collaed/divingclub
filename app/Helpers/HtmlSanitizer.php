<?php

declare(strict_types=1);

namespace App\Helpers;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizer
{
    private const PRESETS = [
        // [class] on the block-level elements lets the rich editor express
        // alignment/emphasis as Bootstrap utility classes (text-center, etc.)
        // instead of inline style="" — which this preset does NOT allow on
        // those elements. Without [class] here, that formatting is silently
        // dropped on save (the exact "why did my centering disappear" class
        // of bug this preset must not reintroduce). Only img/span carry an
        // inline style, and only for things a class can't express (e.g. a
        // pasted width/height).
        'rich' => 'h2[class],h3[class],h4[class],p[class],br,strong,b,em,i,u,s,a[href|target|class],ul[class],ol[class],li[class],blockquote[class],img[src|alt|style|class],span[style|class],table[class],thead,tbody,tr,th,td,div[class]',
        'basic' => 'p,br,strong,b,em,i,u,a[href|target],ul,ol,li,img[src|alt|style]',
        'comment' => 'p,br,strong,b,em,i,a[href]',
    ];

    /** @var array<string, HTMLPurifier> */
    private static array $instances = [];

    public static function clean(?string $html, string $preset = 'rich'): string
    {
        if (! $html) {
            return '';
        }

        if (! isset(self::$instances[$preset])) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', self::PRESETS[$preset] ?? self::PRESETS['rich']);
            $config->set('HTML.TargetBlank', true);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            self::$instances[$preset] = new HTMLPurifier($config);
        }

        return self::$instances[$preset]->purify($html);
    }
}
