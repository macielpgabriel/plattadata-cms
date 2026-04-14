<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Logger;
use Throwable;

/**
 * Service for geocoding using OpenStreetMap Nominatim API.
 * Free alternative to Google Geocoding.
 */
final class NominatimService
{
    use HttpFetchTrait;

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = (string) config('app.nominatim.base_url', 'https://nominatim.openstreetmap.org');
        $this->timeout = (int) config('app.nominatim.timeout', 10);
    }

    /**
     * Search for a location and get coordinates.
     */
    public function search(string $query): ?array
    {
        $cacheKey = 'nominatim_' . md5($query);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $url = $this->baseUrl . '/search?' . http_build_query([
            'q' => $query . ', Brazil',
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1,
        ]);

        try {
            $data = $this->fetchJson($url);
            if ($data && !empty($data)) {
                $result = [
                    'lat' => (float) $data[0]['lat'],
                    'lon' => (float) $data[0]['lon'],
                    'display_name' => $data[0]['display_name'] ?? '',
                    'type' => $data[0]['type'] ?? '',
                ];
                Cache::set($cacheKey, $result, 86400);
                return $result;
            }
        } catch (Throwable $e) {
            Logger::error('NominatimService Error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Reverse geocode coordinates to address.
     */
    public function reverse(float $lat, float $lon): ?array
    {
        $cacheKey = "nominatim_rev_{$lat}_{$lon}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $url = $this->baseUrl . '/reverse?' . http_build_query([
            'lat' => $lat,
            'lon' => $lon,
            'format' => 'json',
            'addressdetails' => 1,
        ]);

        try {
            $data = $this->fetchJson($url);
            if ($data) {
                $result = [
                    'lat' => (float) $data['lat'],
                    'lon' => (float) $data['lon'],
                    'display_name' => $data['display_name'] ?? '',
                    'address' => $data['address'] ?? [],
                ];
                Cache::set($cacheKey, $result, 86400);
                return $result;
            }
        } catch (Throwable $e) {
            Logger::error('NominatimService Reverse Error: ' . $e->getMessage());
        }

        return null;
    }
}
