<?php

namespace App\Support;

final class CanonicalUrl
{
    public static function base(): string
    {
        $appUrl = rtrim((string) config('app.url', url('/')), '/');

        return preg_replace('/^(https?:\/\/)www\./i', '$1', $appUrl) ?? $appUrl;
    }

    public static function absolute(string $path): string
    {
        return self::base().'/'.ltrim($path, '/');
    }
}
