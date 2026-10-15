# Finance App

Osobisty tracker finansowy do kontroli domowych kont (Symfony 8.1 / PHP 8.5, PostgreSQL, Docker) — napisany jako pełnoprawna aplikacja z API, a nie skrypt na kolanie.

Projekt zaczął się jako "domowy Excel", ale po drodze zamienił się w okazję do poćwiczenia architektury, jaką stosuję zawodowo: czyste rozdzielenie warstw, pełne pokrycie testami i REST API zgodne z OpenAPI.

## Co tu jest warte pokazania

- **Architektura API Platform** oparta o wzorzec Resource / Provider / Processor — logika odczytu i zapisu jest rozdzielona i niezależnie testowalna, bez tuczenia kontrolerów.
- **PHPStan na poziomie 9** (najwyższym) — zero błędów.
- **~650 testów PHPUnit** (jednostkowe + warstwa Provider/Processor) pokrywających logikę biznesową: wyliczanie sald, kursy walut, operacje na kontach, złoto, emeryturę.
- **PHP CS Fixer** wymuszający spójny styl kodu.
- **Pełna dokumentacja API** (OpenAPI/Swagger) pod `/api/docs` — auto-generowana z adnotacji, zawsze aktualna.
- **Świadomy model bezpieczeństwa** — hasła hashowane, throttling logowania, jawnie opisane granice (patrz sekcja [Bezpieczeństwo](#bezpieczeństwo)).
- Kod (nazwy, komentarze, docbloki, komunikaty wyjątków) — polski tylko w warstwie tłumaczeń/UI.

## Stack

- Symfony 8.1 / PHP 8.5, Doctrine ORM, API Platform
- PostgreSQL
- Symfony Security (form login do widoku Twig) + JWT (LexikJWTAuthenticationBundle) dla API
- PHPUnit, PHPStan (poziom 9), PHP CS Fixer
- Bootstrap 5, Stimulus (klasyczny widok Twig)

## Uruchomienie

```bash
git clone https://github.com/021800rr/Finance-App.git
cd Finance-App

make build       # zbuduj i uruchom kontenery
make install     # composer install
make migrate     # migracje bazy dev
make fixtures    # przykładowe dane demo

make test        # baza testowa + phpunit + phpstan + cs-fixer

docker compose exec php php bin/console app:create-user twoj@email.pl haslo123
```

## Logowanie

Domyślny user: `twoj@email.pl` / `haslo123`

Aplikacja wymaga zalogowania (Symfony Security).

Appka dostępna pod http://localhost:8081

API docs: http://localhost:8081/api/docs
API login: http://localhost:8081/api/login_check

```bash
curl -X POST http://localhost:8081/api/login_check \
-H "Content-Type: application/json" \
-d '{"email": "twoj@email.pl", "password": "haslo123"}'
```

## Bezpieczeństwo

Appka jest jednoosobowa, więc model bezpieczeństwa jest celowo prosty.

**Co jest zrobione:**

- **Konta zakłada się z CLI**, nie ma publicznej rejestracji: `php bin/console app:create-user <email> <hasło>`.

- **E-mail jest normalizowany** (trim + małe litery) przy zapisie i przy logowaniu, więc `Jan@Example.com` i `jan@example.com` to ten sam użytkownik.
- **Throttling logowania** (`login_throttling`) na formularzu **i** na `POST /api/login_check`: 5 nieudanych prób na parę login + IP oraz 25 na sam IP w oknie 15 minut. Po przekroczeniu limitu logowanie jest odrzucane (API: `401`), nawet z poprawnym hasłem. Stan limitera leży w pamięci podręcznej (`cache.rate_limiter`), więc przeżywa restart PHP-FPM.
- **JWT żyje 8 godzin** (`token_ttl: 28800`).

**Świadome ograniczenia:**

- Tokeny JWT **nie są odwoływalne** — wylogowanie nie unieważnia tokena, wygasa on dopiero po TTL. Przy jednym użytkowniku to akceptowalne; przy wielu trzeba by dodać refresh tokeny i blacklistę.
- Brak resetu hasła, zmiany hasła, 2FA i blokady konta.
- `/api/docs` jest publiczne.
- **Brak izolacji danych per użytkownik** — appka jest jednoosobowa.

## Zasady wprowadzania danych

Nowe operacje na koncie dodajemy bez znaku, jeśli to **przychód** na koncie: "309 zł refundacja /zwrot"
lub ze znakiem `-` jeżeli to **wydatek** z konta: "-309 zł zakup okularów"

**Przelewy / transfery / "kantor"** — jeżeli robisz przelew między kontami o tej samej walucie,
wtedy nie ma potrzeby dodawanie drugiej kwoty.
Jeżeli robisz przelew między kontami o różnych walutach,
musisz określić jaką kwotę zdejmujesz z pierwszego konta i jaka wejdzie na drugie konto: "100 PLN / 25 EUR".
Tutaj NIE podajemy żadnych znaków `+/-`.

Dla poprawnego wyliczenia podsumowań **musisz** dodać kursy walut,
przynajmniej dla tych banków, w których trzymasz walutę.

**Opłaty stałe / planowanie przyszłych cyklicznych wydatków** (czynsz, raty itp).
Wystarczy dodać opłaty stałe do systemu i zanim zacznie się nowy miesiąc dorzucić je do konta głównego ("Zaplanuj wydatki").
Konto główne oznacza się gwiazdką (☆) na liście kont — klik ustawia je jako główne (`Account.isMain`, wymuszone unikalnym indeksem w bazie).

**Gospodarstwa** (`household`) — to tylko etykieta do rozdzielenia sald na dwie kieszenie, nie mechanizm dostępu:
- **Nasze** — ja i partnerka
- **Matka** — gospodarstwo matki, którym się opiekuję

Appka jest **tylko dla mnie** (ewentualnie partnerki) — jeden zalogowany user widzi i edytuje wszystko,
bez podziału po właścicielu. To świadoma decyzja, nie dziura do załatania: nikogo trzeciego tu nie
wpuszczam i nie planuję. Gdyby to się kiedyś zmieniło, `household` w obecnej postaci nic by nie chronił —
trzeba by dopiero dobudować izolację per-użytkownik (patrz [Bezpieczeństwo](#bezpieczeństwo)). Na razie to i tak się nie zmieni — YAGNI.