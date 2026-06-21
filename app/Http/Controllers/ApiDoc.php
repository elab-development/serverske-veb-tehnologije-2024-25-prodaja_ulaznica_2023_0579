<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Tickets API",
 *     version="1.0.0",
 *     description="REST API za aplikaciju za prodaju ulaznica. API koristi JSON odgovore, Sanctum Bearer tokene za zasticene rute, javne eksterne API pozive za lokaciju i vremensku prognozu eventa i CSV export eventova."
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API base path"
 * )
 *
 * @OA\Tag(name="Auth", description="Registracija, login i logout")
 * @OA\Tag(name="Users", description="Podaci o trenutno ulogovanom korisniku")
 * @OA\Tag(name="Events", description="Eventi, javni pregled i admin upravljanje")
 * @OA\Tag(name="Ticket Types", description="Tipovi karata za evente")
 * @OA\Tag(name="Orders", description="Porudzbine karata")
 * @OA\Tag(name="External", description="Javne rute koje pozivaju eksterne API-jeve")
 * @OA\Tag(name="Exports", description="CSV eksport podataka")
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Sanctum token",
 *     description="Uneti token dobijen kroz /register ili /login. Format u Authorization headeru: Bearer {token}"
 * )
 *
 * @OA\Schema(
 *     schema="ErrorMessage",
 *     type="object",
 *     @OA\Property(property="message", type="string", example="Unauthorized")
 * )
 *
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     example={"message":"The given data was invalid.","errors":{"email":{"The email has already been taken."}}}
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Marko Petrovic"),
 *     @OA\Property(property="email", type="string", format="email", example="marko.petrovic@example.com"),
 *     @OA\Property(property="role", type="string", enum={"user","admin"}, example="user"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Event",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Belgrade Music Festival"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Open-air evening with regional pop and rock performers."),
 *     @OA\Property(property="location", type="string", example="Kalemegdan Fortress, Belgrade"),
 *     @OA\Property(property="starts_at", type="string", format="date-time", example="2026-08-24T20:00:00.000000Z"),
 *     @OA\Property(property="ends_at", type="string", format="date-time", nullable=true, example="2026-08-24T23:30:00.000000Z"),
 *     @OA\Property(property="status", type="string", enum={"draft","published","cancelled"}, example="published"),
 *     @OA\Property(property="ticket_types", type="array", @OA\Items(ref="#/components/schemas/TicketType")),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="TicketType",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="event_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Regular"),
 *     @OA\Property(property="price", type="string", example="35.00"),
 *     @OA\Property(property="quantity_total", type="integer", example=800),
 *     @OA\Property(property="quantity_available", type="integer", example=620),
 *     @OA\Property(property="sale_starts_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="sale_ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="event", ref="#/components/schemas/Event", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Order",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=2),
 *     @OA\Property(property="ticket_type_id", type="integer", example=1),
 *     @OA\Property(property="quantity", type="integer", example=2),
 *     @OA\Property(property="total_price", type="string", example="70.00"),
 *     @OA\Property(property="status", type="string", enum={"pending","paid","cancelled"}, example="paid"),
 *     @OA\Property(property="purchased_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="user", ref="#/components/schemas/User", nullable=true),
 *     @OA\Property(property="ticket_type", ref="#/components/schemas/TicketType", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Post(
 *     path="/register",
 *     tags={"Auth"},
 *     summary="Registracija korisnika",
 *     description="Kreira user ili admin nalog i vraca Sanctum Bearer token. Ako role nije poslat, podrazumevano se koristi user.",
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"name","email","password"},
 *         @OA\Property(property="name", type="string", maxLength=255, example="Petar Petrovic"),
 *         @OA\Property(property="email", type="string", format="email", example="petar@example.com"),
 *         @OA\Property(property="password", type="string", minLength=8, example="password123"),
 *         @OA\Property(property="role", type="string", enum={"user","admin"}, example="user")
 *     )),
 *     @OA\Response(response=201, description="User registered", @OA\JsonContent(
 *         @OA\Property(property="data", ref="#/components/schemas/User"),
 *         @OA\Property(property="access_token", type="string", example="1|plain-text-token"),
 *         @OA\Property(property="token_type", type="string", example="Bearer")
 *     )),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Post(
 *     path="/login",
 *     tags={"Auth"},
 *     summary="Login korisnika",
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"email","password"},
 *         @OA\Property(property="email", type="string", format="email", example="petar@example.com"),
 *         @OA\Property(property="password", type="string", example="password123")
 *     )),
 *     @OA\Response(response=200, description="User logged in", @OA\JsonContent(
 *         @OA\Property(property="message", type="string", example="Petar Petrovic logged in"),
 *         @OA\Property(property="data", ref="#/components/schemas/User"),
 *         @OA\Property(property="access_token", type="string", example="1|plain-text-token"),
 *         @OA\Property(property="token_type", type="string", example="Bearer")
 *     )),
 *     @OA\Response(response=401, description="Wrong credentials", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Post(
 *     path="/logout",
 *     tags={"Auth"},
 *     summary="Logout korisnika",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Logged out", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
 * )
 *
 * @OA\Get(
 *     path="/user",
 *     tags={"Users"},
 *     summary="Trenutno ulogovan korisnik",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Authenticated user", @OA\JsonContent(ref="#/components/schemas/User")),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
 * )
 *
 * @OA\Get(
 *     path="/external/geocode",
 *     tags={"External"},
 *     summary="Geocoding adrese",
 *     description="Poziva Nominatim / OpenStreetMap API. Ruta ne zahteva autentikaciju.",
 *     @OA\Parameter(name="address", in="query", required=true, @OA\Schema(type="string", maxLength=255, example="Belgrade Arena")),
 *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=5, example=1)),
 *     @OA\Response(response=200, description="Geocoding results"),
 *     @OA\Response(response=502, description="External service error", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Get(
 *     path="/external/weather",
 *     tags={"External"},
 *     summary="Vremenska prognoza za koordinate",
 *     description="Poziva Open-Meteo API. Ruta ne zahteva autentikaciju.",
 *     @OA\Parameter(name="latitude", in="query", required=true, @OA\Schema(type="number", format="float", minimum=-90, maximum=90, example=44.8149)),
 *     @OA\Parameter(name="longitude", in="query", required=true, @OA\Schema(type="number", format="float", minimum=-180, maximum=180, example=20.4217)),
 *     @OA\Parameter(name="forecast_days", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=16, example=7)),
 *     @OA\Parameter(name="timezone", in="query", required=false, @OA\Schema(type="string", example="Europe/Belgrade")),
 *     @OA\Response(response=200, description="Weather data"),
 *     @OA\Response(response=502, description="External service error", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Get(
 *     path="/events",
 *     tags={"Events"},
 *     summary="Lista eventova",
 *     description="Javna ruta sa pretragom, filterima, sortiranjem i paginacijom.",
 *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", maxLength=255, example="music")),
 *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"draft","published","cancelled"})),
 *     @OA\Parameter(name="location", in="query", required=false, @OA\Schema(type="string", example="Belgrade")),
 *     @OA\Parameter(name="starts_from", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="starts_until", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="ends_from", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="ends_until", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="sort_by", in="query", required=false, @OA\Schema(type="string", enum={"title","location","starts_at","ends_at","status","created_at","updated_at"})),
 *     @OA\Parameter(name="sort_direction", in="query", required=false, @OA\Schema(type="string", enum={"asc","desc"})),
 *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=50)),
 *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1)),
 *     @OA\Response(response=200, description="Paginated events"),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Get(
 *     path="/events/export",
 *     tags={"Events","Exports"},
 *     summary="CSV eksport eventova",
 *     @OA\Response(response=200, description="CSV file", @OA\MediaType(mediaType="text/csv", @OA\Schema(type="string", example="id,title,description,location,starts_at,ends_at,status,ticket_types_count,tickets_total,tickets_available,created_at,updated_at")))
 * )
 *
 * @OA\Post(
 *     path="/events",
 *     tags={"Events"},
 *     summary="Kreiranje eventa",
 *     description="Event moze kreirati samo administrator.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"title","location","starts_at"},
 *         @OA\Property(property="title", type="string", maxLength=255, example="Belgrade Music Festival"),
 *         @OA\Property(property="description", type="string", nullable=true, example="Open-air evening with regional performers."),
 *         @OA\Property(property="location", type="string", maxLength=255, example="Kalemegdan Fortress, Belgrade"),
 *         @OA\Property(property="starts_at", type="string", format="date-time", example="2026-08-24 20:00:00"),
 *         @OA\Property(property="ends_at", type="string", format="date-time", nullable=true, example="2026-08-24 23:30:00"),
 *         @OA\Property(property="status", type="string", enum={"draft","published","cancelled"}, example="published")
 *     )),
 *     @OA\Response(response=201, description="Event created"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=403, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Get(
 *     path="/events/{event}",
 *     tags={"Events"},
 *     summary="Pregled jednog eventa",
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Event details", @OA\JsonContent(@OA\Property(property="event", ref="#/components/schemas/Event"))),
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
 * )
 *
 * @OA\Put(
 *     path="/events/{event}",
 *     tags={"Events"},
 *     summary="Azuriranje eventa",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/Event")),
 *     @OA\Response(response=200, description="Event updated"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=403, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Patch(
 *     path="/events/{event}",
 *     tags={"Events"},
 *     summary="Delimicno azuriranje eventa",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\RequestBody(required=false, @OA\JsonContent(@OA\Property(property="status", type="string", enum={"draft","published","cancelled"}, example="cancelled"))),
 *     @OA\Response(response=200, description="Event updated"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=403, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Delete(
 *     path="/events/{event}",
 *     tags={"Events"},
 *     summary="Brisanje eventa",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Event deleted", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=403, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
 * )
 *
 * @OA\Get(
 *     path="/events/{event}/location",
 *     tags={"Events","External"},
 *     summary="Geocoding lokacije eventa",
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Event location data"),
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=502, description="External service error", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
 * )
 *
 * @OA\Get(
 *     path="/events/{event}/weather",
 *     tags={"Events","External"},
 *     summary="Vremenska prognoza za lokaciju eventa",
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Parameter(name="forecast_days", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=16, example=7)),
 *     @OA\Parameter(name="timezone", in="query", required=false, @OA\Schema(type="string", example="Europe/Belgrade")),
 *     @OA\Response(response=200, description="Event weather data"),
 *     @OA\Response(response=404, description="Event or location not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=502, description="External service error", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Get(
 *     path="/events/{event}/ticket-types",
 *     tags={"Ticket Types"},
 *     summary="Tipovi karata jednog eventa",
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Ticket types list"),
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
 * )
 *
 * @OA\Get(
 *     path="/events/{event}/ticket-types/{ticketType}",
 *     tags={"Ticket Types"},
 *     summary="Pregled jednog tipa karte u okviru eventa",
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Parameter(name="ticketType", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Ticket type details"),
 *     @OA\Response(response=404, description="Event or ticket type not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
 * )
 *
 * @OA\Post(
 *     path="/ticket-types",
 *     tags={"Ticket Types"},
 *     summary="Kreiranje tipa karte",
 *     description="Tip karte moze kreirati samo administrator.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"event_id","name","price","quantity_total","quantity_available"},
 *         @OA\Property(property="event_id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Regular"),
 *         @OA\Property(property="price", type="number", format="float", example=35),
 *         @OA\Property(property="quantity_total", type="integer", example=800),
 *         @OA\Property(property="quantity_available", type="integer", example=620),
 *         @OA\Property(property="sale_starts_at", type="string", format="date-time", nullable=true),
 *         @OA\Property(property="sale_ends_at", type="string", format="date-time", nullable=true)
 *     )),
 *     @OA\Response(response=201, description="Ticket type created"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=403, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Put(
 *     path="/ticket-types/{ticket_type}",
 *     tags={"Ticket Types"},
 *     summary="Azuriranje tipa karte",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="ticket_type", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/TicketType")),
 *     @OA\Response(response=200, description="Ticket type updated"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=403, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=404, description="Ticket type not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Patch(
 *     path="/ticket-types/{ticket_type}",
 *     tags={"Ticket Types"},
 *     summary="Delimicno azuriranje tipa karte",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="ticket_type", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\RequestBody(required=false, @OA\JsonContent(@OA\Property(property="quantity_available", type="integer", example=120))),
 *     @OA\Response(response=200, description="Ticket type updated"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=403, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=404, description="Ticket type not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Delete(
 *     path="/ticket-types/{ticket_type}",
 *     tags={"Ticket Types"},
 *     summary="Brisanje tipa karte",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="ticket_type", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Ticket type deleted", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=403, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=404, description="Ticket type not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
 * )
 *
 * @OA\Get(
 *     path="/orders",
 *     tags={"Orders"},
 *     summary="Lista porudzbina",
 *     description="Korisnik vidi svoje porudzbine. Admin vidi sve i moze filtrirati po eventu i korisniku.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="event_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
 *     @OA\Parameter(name="user_id", in="query", required=false, @OA\Schema(type="integer", example=2)),
 *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=50)),
 *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1)),
 *     @OA\Response(response=200, description="Paginated orders"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=403, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Post(
 *     path="/orders",
 *     tags={"Orders"},
 *     summary="Kreiranje porudzbine",
 *     description="Korisnik salje ticket_type_id, quantity i pay_now. API racuna cenu, status i smanjuje dostupnu kolicinu karata.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"ticket_type_id","quantity","pay_now"},
 *         @OA\Property(property="ticket_type_id", type="integer", example=1),
 *         @OA\Property(property="quantity", type="integer", minimum=1, example=2),
 *         @OA\Property(property="pay_now", type="boolean", example=true)
 *     )),
 *     @OA\Response(response=201, description="Order created"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error or not enough tickets", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Get(
 *     path="/orders/{order}",
 *     tags={"Orders"},
 *     summary="Pregled jedne porudzbine",
 *     description="Porudzbinu vidi administrator ili korisnik cija je porudzbina.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Order details", @OA\JsonContent(@OA\Property(property="order", ref="#/components/schemas/Order"))),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=403, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=404, description="Order not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage"))
 * )
 *
 * @OA\Put(
 *     path="/orders/{order}",
 *     tags={"Orders"},
 *     summary="Azuriranje statusa porudzbine",
 *     description="Moze se menjati samo status pending porudzbine u paid ili cancelled.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"status"}, @OA\Property(property="status", type="string", enum={"paid","cancelled"}, example="paid"))),
 *     @OA\Response(response=200, description="Order updated"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=403, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=404, description="Order not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Patch(
 *     path="/orders/{order}",
 *     tags={"Orders"},
 *     summary="Delimicno azuriranje statusa porudzbine",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"status"}, @OA\Property(property="status", type="string", enum={"paid","cancelled"}, example="cancelled"))),
 *     @OA\Response(response=200, description="Order updated"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=403, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=404, description="Order not found", @OA\JsonContent(ref="#/components/schemas/ErrorMessage")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 */
class ApiDoc extends Controller
{
}