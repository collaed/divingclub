<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Converts dd/mm/yyyy date fields to Y-m-d before validation.
 */
class ParseEuropeanDates
{
    protected array $dateFields = [
        'date_of_birth', 'brevet_date', 'event_date', 'end_date', 'date_established',
        'obtained_date', 'permissions_expire_date', 'start_date', 'end_date',
        'deposit_1_date', 'deposit_2_date', 'deposit_3_date',
        'licence_request_date',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        foreach ($this->dateFields as $field) {
            if ($request->has($field) && $request->input($field)) {
                $val = $request->input($field);
                // Only convert if it looks like dd/mm/yyyy
                if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $val, $m)) {
                    $request->merge([$field => "{$m[3]}-{$m[2]}-{$m[1]}"]);
                }
            }
        }

        return $next($request);
    }
}
