<?php

namespace App\Helpers;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizer
{
    private const PRESETS = [
        'rich' => 'h2,h3,h4,p,br,strong,b,em,i,u,s,a[href|target|class],ul,ol,li,blockquote,img[src|alt|style|class],span[style],table[class],thead,tbody,tr,th,td,div[class]',
        'basic' => 'p,br,strong,b,em,i,u,a[href|target],ul,ol,li,img[src|alt|style]',
        'comment' => 'p,br,strong,b,em,i,a[href]',
    ];

    public static function clean(?string $html, string $preset = 'rich'): string
    {
        if (! $html) {
            return '';
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', self::PRESETS[$preset] ?? self::PRESETS['rich']);
        $config->set('HTML.TargetBlank', true);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);

        return (new HTMLPurifier($config))->purify($html);
    }
}
