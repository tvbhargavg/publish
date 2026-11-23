<?php

namespace App\Support;

/** Small helpers shared by the dashboard list pages (search, filters, paging). */
class Listing
{
    /** Case-insensitive "contains" search across several columns. */
    public static function search(mixed $q, ?string $term, array $columns): void
    {
        $term = trim((string) $term);
        if ($term === '' || mb_strlen($term) > 100) {
            return;
        }
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
        $q->where(function ($w) use ($columns, $like) {
            foreach ($columns as $c) {
                $w->orWhereRaw("lower({$c}) like ? escape '!'", [$like]);
            }
        });
    }

    /** Rows per page chosen by the user (?per_page=), limited to a safe list. */
    public static function perPage(): int
    {
        $n = (int) request()->query('per_page', config('tunnel.per_page'));

        return in_array($n, [10, 25, 50], true) ? $n : (int) config('tunnel.per_page');
    }

    /** Only accept a filter value from a known list. */
    public static function pick(?string $value, array $allowed): ?string
    {
        return in_array($value, $allowed, true) ? $value : null;
    }
}
