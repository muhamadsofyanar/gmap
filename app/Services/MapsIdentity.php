<?php
namespace App\Services;

class MapsIdentity
{
    /** Match only a Maps place identity, never a name or search position. */
    public static function key(?string $url): ?string
    {
        if (!$url) return null;
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (!in_array($host, ['google.com', 'www.google.com', 'maps.google.com'], true)) return null;
        if (preg_match('/!1s(0x[0-9a-f]+:0x[0-9a-f]+)/i', rawurldecode($url), $m)) {
            return strtolower($m[1]);
        }
        parse_str($parts['query'] ?? '', $query);
        if (!empty($query['query_place_id']) && is_string($query['query_place_id'])) {
            return 'place:' . $query['query_place_id'];
        }
        if (preg_match('/!1s(ChI[A-Za-z0-9_-]+)/', rawurldecode($url), $m)) {
            return 'place:' . $m[1];
        }
        return null;
    }

    public static function index(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $key = self::key($item['gmap_url'] ?? null);
            if ($key === null) continue;
            // Exclude duplicate identities instead of guessing.
            $indexed[$key] = array_key_exists($key, $indexed) ? null : $item;
        }
        return $indexed;
    }
}
