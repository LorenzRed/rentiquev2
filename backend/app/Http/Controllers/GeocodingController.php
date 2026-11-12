<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeocodingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'required|string|min:3|max:200']);
        $provider = config('services.geocoding.url');
        abort_unless($provider, 503, 'Address search is unavailable. Select your location on the map.');
        $response = Http::timeout(10)->acceptJson()->get($provider, ['q' => $data['q'], 'limit' => 5]);
        abort_unless($response->successful(), 502, 'Address search is temporarily unavailable.');
        $features = collect($response->json('features', []))->take(5)->filter(fn ($feature) => ($feature['geometry']['type'] ?? null) === 'Point')->map(fn ($feature) => ['latitude' => $feature['geometry']['coordinates'][1], 'longitude' => $feature['geometry']['coordinates'][0], 'label' => implode(', ', array_filter([$feature['properties']['name'] ?? null, $feature['properties']['street'] ?? null, $feature['properties']['city'] ?? null, $feature['properties']['state'] ?? null, $feature['properties']['country'] ?? null]))])->values();

        return response()->json(['data' => $features]);
    }
}
