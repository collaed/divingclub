<?php

namespace App\Http\Controllers\Concerns;

trait PaginatesFromRequest
{
    protected function perPage(int $default = 30): int|string
    {
        $val = request('per_page', $default);

        return $val === 'all' ? 999999 : (int) min($val, 999999);
    }
}
