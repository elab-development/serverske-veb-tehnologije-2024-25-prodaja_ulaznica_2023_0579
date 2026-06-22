# Tickets API

Laravel 12 API backend za aplikaciju za prodaju ulaznica. Projekat pokriva registraciju i prijavu korisnika, role korisnika, evente, tipove karata, porudzbine, javne eksterne API pozive, CSV eksport eventova i Swagger dokumentaciju.

## Tehnologije

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- MySQL
- Pest/PHPUnit testovi
- darkaonline/l5-swagger

## Povlacenje projekta

Kloniraj repozitorijum i udji u folder projekta:

```bash
git clone <repository-url>
cd tickets
```

Instaliraj PHP zavisnosti:

```bash
composer install
```

Ako zelis da koristis Vite/Laravel frontend alatke koje dolaze uz Laravel skeleton:

```bash
npm install
```

## Podesavanje lokalnog okruzenja

Kopiraj `.env.example` u `.env`:

```bash
cp .env.example .env
```

Na Windows PowerShell-u mozes koristiti:

```powershell
Copy-Item .env.example .env
```

Generisi aplikacioni kljuc:

```bash
php artisan key:generate
```

U `.env` podesi konekciju ka lokalnoj MySQL bazi. Podrazumevano mozes koristiti:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tickets
DB_USERNAME=root
DB_PASSWORD=
```

Pre migracija napravi bazu `tickets` u MySQL-u.

## Migracije i seed podaci

Pokreni migracije:

```bash
php artisan migrate
```

Popuni bazu pocetnim podacima:

```bash
php artisan db:seed
```

Ili sve odjednom za svezu lokalnu bazu:

```bash
php artisan migrate:fresh --seed
```

Seeder kreira admin korisnika, nekoliko obicnih korisnika, realne evente, realne tipove karata i realne porudzbine, a zatim dodaje jos podataka kroz factory-je.

Seed korisnici imaju lozinku:

```text
password
```

Primeri naloga:

```text
admin@tickets.test
marko.petrovic@example.com
jovana.ilic@example.com
nikola.savic@example.com
```

## Pokretanje aplikacije

Pokreni Laravel server:

```bash
php artisan serve
```

Aplikacija ce biti dostupna na:

```text
http://127.0.0.1:8000
```

Ako je port 8000 zauzet drugim projektom, koristi drugi port:

```bash
php artisan serve --port=8001
```

API rute su pod `/api`, na primer:

```text
http://127.0.0.1:8000/api/events
```

## Swagger dokumentacija

Projekat koristi `darkaonline/l5-swagger`.

Generisi OpenAPI dokumentaciju:

```bash
php artisan l5-swagger:generate
```

Swagger UI se otvara na:

```text
http://127.0.0.1:8000/api/documentation
```

Raw OpenAPI JSON je dostupan na:

```text
http://127.0.0.1:8000/docs
```

Za autorizovane rute prvo pozovi `/api/login` ili `/api/register`, kopiraj `access_token`, pa u Swagger UI klikni `Authorize` i unesi token u formatu:

```text
Bearer <token>
```

## Testovi

Pokretanje svih testova:

```bash
php artisan test
```

## Glavne funkcionalnosti

### Autentifikacija

- Registracija korisnika
- Login korisnika
- Logout korisnika
- Sanctum Bearer token autentifikacija
- Role korisnika: `admin`, `user`

Registracija moze dobiti role `admin` ili `user`. Ako role nije poslat, korisnik se kreira kao `user`.

### Eventi

- Javni pregled eventova
- Javni pregled jednog eventa
- Kreiranje, azuriranje i brisanje eventa samo za admin korisnika
- Pregled liste podrzava pretragu, filtere, sortiranje i paginaciju
- Event ima naslov, opis, lokaciju, datum/vreme pocetka, opcioni kraj i status

Statusi eventa:

```text
draft
published
cancelled
```

Podrzani filteri za listu eventova:

```text
search
status
location
starts_from
starts_until
ends_from
ends_until
sort_by
sort_direction
per_page
page
```

Podrzana polja za sortiranje:

```text
title
location
starts_at
ends_at
status
created_at
updated_at
```

### Tipovi karata

Tipovi karata se javno pregledaju samo u okviru konkretnog eventa:

```text
GET /api/events/{event}/ticket-types
GET /api/events/{event}/ticket-types/{ticketType}
```

Nema globalnog javnog pregleda svih tipova karata.

- Svi mogu da pregledaju tipove karata za event
- Samo admin moze da kreira, azurira i brise tipove karata
- Nema filtera, pretrage, sortiranja ni paginacije za pregled tipova karata
- `show` ruta je scoped na event: tip karte mora pripadati eventu iz URL-a

Tip karte ima:

```text
event_id
name
price
quantity_total
quantity_available
sale_starts_at
sale_ends_at
```

Admin rute za upravljanje tipovima karata:

```text
POST      /api/ticket-types
PUT/PATCH /api/ticket-types/{ticket_type}
DELETE    /api/ticket-types/{ticket_type}
```

### Porudzbine

Sve rute za porudzbine zahtevaju Sanctum token.

Korisnik pri kreiranju salje samo:

```text
ticket_type_id
quantity
pay_now
```

Backend automatski postavlja:

```text
user_id
total_price
status
purchased_at
```

Pravila:

- Proverava se da li ima dovoljno dostupnih karata
- Nakon kreiranja porudzbine smanjuje se `quantity_available` na tipu karte
- Ako je `pay_now=true`, porudzbina odmah dobija status `paid`
- Ako je `pay_now=false`, porudzbina dobija status `pending`
- Pending porudzbina moze kasnije da predje samo u `paid` ili `cancelled`
- Ako se pending porudzbina otkaze, karte se vracaju u `quantity_available`
- Finalne porudzbine `paid` i `cancelled` vise se ne menjaju
- Brisanje porudzbina nije predvidjeno

Statusi porudzbine:

```text
pending
paid
cancelled
```

Pregled porudzbina:

- Obican korisnik vidi samo svoje porudzbine
- Admin vidi sve porudzbine
- Admin moze da filtrira po korisniku
- Svi ulogovani korisnici mogu da filtriraju po eventu, ali obican korisnik i dalje vidi samo svoje rezultate

Podrzani filteri:

```text
event_id
user_id
per_page
page
```

Pregled jedne porudzbine radi po ID-ju, ali samo za admina ili vlasnika porudzbine.

### Javni eksterni API-jevi

Projekat ima javne rute koje ne zahtevaju autentifikaciju i pozivaju eksterne API servise bez API kljuca.

Geocoding:

```text
GET /api/external/geocode
GET /api/events/{event}/location
```

Ruta poziva Nominatim / OpenStreetMap API. Podrzani query parametri za direktan geocoding:

```text
address
limit
```

Primer:

```text
/api/external/geocode?address=Belgrade%20Arena&limit=1
```

Vremenska prognoza:

```text
GET /api/external/weather
GET /api/events/{event}/weather
```

Ruta poziva Open-Meteo API. Podrzani query parametri za direktnu prognozu:

```text
latitude
longitude
forecast_days
timezone
```

Primer:

```text
/api/external/weather?latitude=44.8149&longitude=20.4217&forecast_days=7
```

Za event weather rutu API prvo geocoduje `event.location`, pa zatim koristi dobijene koordinate za Open-Meteo prognozu.

### CSV eksport

Ruta:

```text
GET /api/events/export
```

Preuzima CSV fajl sa podacima o eventima. CSV sadrzi osnovne podatke eventa, status, datume, broj tipova karata, ukupan broj karata i broj dostupnih karata.

CSV kolone:

```text
id
title
description
location
starts_at
ends_at
status
ticket_types_count
tickets_total
tickets_available
created_at
updated_at
```

## Pregled glavnih ruta

```text
POST      /api/register
POST      /api/login
POST      /api/logout
GET       /api/user

GET       /api/external/geocode
GET       /api/external/weather

GET       /api/events
POST      /api/events
GET       /api/events/export
GET       /api/events/{event}
PUT/PATCH /api/events/{event}
DELETE    /api/events/{event}
GET       /api/events/{event}/location
GET       /api/events/{event}/weather

GET       /api/events/{event}/ticket-types
GET       /api/events/{event}/ticket-types/{ticketType}
POST      /api/ticket-types
PUT/PATCH /api/ticket-types/{ticket_type}
DELETE    /api/ticket-types/{ticket_type}

GET       /api/orders
POST      /api/orders
GET       /api/orders/{order}
PUT/PATCH /api/orders/{order}
```

## Korisne komande

```bash
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan l5-swagger:generate
php artisan serve
php artisan test
```
