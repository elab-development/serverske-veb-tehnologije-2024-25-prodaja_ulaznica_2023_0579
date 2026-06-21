<?php

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('event locations can be geocoded through nominatim', function () {
    Http::fake([
        'https://nominatim.openstreetmap.org/search*' => Http::response([
            [
                'display_name' => 'Belgrade Arena, Belgrade, Serbia',
                'lat' => '44.8149',
                'lon' => '20.4217',
            ],
        ]),
    ]);

    $response = $this->getJson('/api/external/geocode?address=Belgrade%20Arena&limit=1');

    $response
        ->assertOk()
        ->assertJsonPath('source', 'Nominatim / OpenStreetMap')
        ->assertJsonPath('address', 'Belgrade Arena')
        ->assertJsonPath('results.0.lat', '44.8149');

    Http::assertSent(fn ($request): bool =>
        str_starts_with($request->url(), 'https://nominatim.openstreetmap.org/search')
            && $request['q'] === 'Belgrade Arena'
            && $request['format'] === 'json'
            && $request['addressdetails'] === 1
            && $request['limit'] === 1
            && $request->hasHeader('User-Agent')
    );
});

test('weather can be fetched through open meteo', function () {
    Http::fake([
        'https://api.open-meteo.com/v1/forecast*' => Http::response([
            'current' => [
                'temperature_2m' => 24.5,
                'weather_code' => 1,
            ],
            'daily' => [
                'time' => ['2026-08-01'],
                'temperature_2m_max' => [30.1],
            ],
        ]),
    ]);

    $response = $this->getJson('/api/external/weather?latitude=44.8149&longitude=20.4217&forecast_days=3&timezone=Europe/Belgrade');

    $response
        ->assertOk()
        ->assertJsonPath('source', 'Open-Meteo')
        ->assertJsonPath('latitude', 44.8149)
        ->assertJsonPath('longitude', 20.4217)
        ->assertJsonPath('forecast_days', 3)
        ->assertJsonPath('weather.current.temperature_2m', 24.5);

    Http::assertSent(fn ($request): bool =>
        str_starts_with($request->url(), 'https://api.open-meteo.com/v1/forecast')
            && (float) $request['latitude'] === 44.8149
            && (float) $request['longitude'] === 20.4217
            && $request['forecast_days'] === 3
            && $request['timezone'] === 'Europe/Belgrade'
    );
});

test('event weather geocodes event location and fetches weather forecast', function () {
    $event = Event::factory()->create([
        'title' => 'Belgrade Music Festival',
        'location' => 'Kalemegdan Fortress, Belgrade',
        'starts_at' => '2026-08-24 20:00:00',
    ]);

    Http::fake([
        'https://nominatim.openstreetmap.org/search*' => Http::response([
            [
                'display_name' => 'Kalemegdan, Belgrade, Serbia',
                'lat' => '44.8230',
                'lon' => '20.4500',
            ],
        ]),
        'https://api.open-meteo.com/v1/forecast*' => Http::response([
            'current' => [
                'temperature_2m' => 26,
                'weather_code' => 0,
            ],
        ]),
    ]);

    $response = $this->getJson("/api/events/{$event->id}/weather?forecast_days=2");

    $response
        ->assertOk()
        ->assertJsonPath('source', 'Open-Meteo')
        ->assertJsonPath('event_id', $event->id)
        ->assertJsonPath('event', 'Belgrade Music Festival')
        ->assertJsonPath('address', 'Kalemegdan Fortress, Belgrade')
        ->assertJsonPath('location.lat', '44.8230')
        ->assertJsonPath('forecast_days', 2)
        ->assertJsonPath('weather.current.temperature_2m', 26);

    Http::assertSentCount(2);
});

test('event location returns not found when geocoding has no result', function () {
    $event = Event::factory()->create([
        'location' => 'Unknown Venue Without Coordinates',
    ]);

    Http::fake([
        'https://nominatim.openstreetmap.org/search*' => Http::response([]),
    ]);

    $this->getJson("/api/events/{$event->id}/weather")
        ->assertNotFound()
        ->assertJsonPath('message', 'Event location could not be found.');
});