# Finance App

[![CI](https://github.com/021800rr/Finance-App/actions/workflows/ci.yml/badge.svg)](https://github.com/021800rr/Finance-App/actions/workflows/ci.yml)

Osobisty tracker finansowy do kontroli domowych kont (Symfony 8.1 / PHP 8.5, PostgreSQL, Docker) — napisany jako pełnoprawna aplikacja z API, a nie skrypt na kolanie.

Projekt zaczął się jako "domowy Excel", ale po drodze zamienił się w okazję do poćwiczenia architektury, jaką stosuję zawodowo: czyste rozdzielenie warstw, pełne pokrycie testami i REST API zgodne z OpenAPI.

## Co tu jest warte pokazania

- **Architektura API Platform** oparta o wzorzec Resource / Provider / Processor — logika odczytu i zapisu jest rozdzielona i niezależnie testowalna, bez tuczenia kontrolerów.
- **~1440 testów PHPUnit** (jednostkowe + warstwa Provider/Processor) pokrywających logikę biznesową: wyliczanie sald, kursy walut, operacje na kontach, złoto, emeryturę.
- **PHPStan na najwyższym poziomie (`max`)** — dla `src`, `tests`, `config`, `bin` i `public`.
- **PHP CS Fixer** wymuszający spójny styl kodu.
- **Pełna dokumentacja API** (OpenAPI/Swagger) pod `/api/docs` — auto-generowana z adnotacji, zawsze aktualna.
- **Przewidywalne błędy API** (RFC 9457 Problem Details) — każdy błąd ma stały `errorCode`, parametry
  i angielski opis; szczegóły w sekcji [Błędy API](#błędy-api).
- **Świadomy model bezpieczeństwa** — hasła hashowane, throttling logowania, jawnie opisane granice (patrz sekcja [Bezpieczeństwo](#bezpieczeństwo)).

## Stack

- Symfony 8.1 / PHP 8.5, Doctrine ORM, API Platform
- PostgreSQL
- Symfony Security (form login do widoku Twig) + JWT (LexikJWTAuthenticationBundle) dla API
- PHPUnit, PHPStan (poziom `max`), PHP CS Fixer
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
make user        # załóż konto demo
make token       # pobierz token JWT konta demo
```

## Logowanie

Demo user: `twoj@email.pl` / `haslo123`

web: http://localhost:8081  
swagger: http://localhost:8081/api/docs  
openapi: http://localhost:8081/api/docs.jsonopenapi

## Bezpieczeństwo

Appka jest jednoosobowa, więc model bezpieczeństwa jest celowo prosty.

**Co jest zrobione:**

- **Konta zakłada się z CLI**, nie ma publicznej rejestracji: `php bin/console app:create-user <email> <hasło>`.

- **E-mail jest normalizowany** (trim + małe litery) przy zapisie i przy logowaniu, więc `Jan@Example.com` i `jan@example.com` to ten sam użytkownik.
- **Throttling logowania** (`login_throttling`) na formularzu **i** na `POST /api/login_check`: 5 nieudanych prób na parę login + IP oraz 25 na sam IP w oknie 15 minut. Po przekroczeniu limitu logowanie jest odrzucane (API: `401`, `errorCode: auth.too_many_login_attempts`, nagłówek `Retry-After`), nawet z poprawnym hasłem. Stan limitera leży w pamięci podręcznej (`cache.rate_limiter`), więc przeżywa restart PHP-FPM.
- **JWT żyje 8 godzin** (`token_ttl: 28800`).

**Świadome ograniczenia:**

- Tokeny JWT **nie są odwoływalne** — wylogowanie nie unieważnia tokena, wygasa on dopiero po TTL. Przy jednym użytkowniku to akceptowalne; przy wielu trzeba by dodać refresh tokeny i blacklistę.
- Brak resetu hasła, zmiany hasła, 2FA i blokady konta.
- `/api/docs` jest publiczne.
- **Brak izolacji danych per użytkownik** — appka jest jednoosobowa.

## Błędy API

Każdy błąd API to [RFC 9457 Problem Details](https://www.rfc-editor.org/rfc/rfc9457.html)
(`Content-Type: application/problem+json`) z trzema polami, na które można liczyć:

- **`errorCode`** — stały kod błędu; po nim klient rozpoznaje, co się stało,
- **`params`** — dane do komunikatu (zawsze obiekt, wartości jako tekst; `{}` gdy brak),
- **`detail`** — zwykłe angielskie zdanie dla curla / Postmana; jego treść może się zmieniać, nie parsować.

`type` to `urn:finance-app:problem:` + `errorCode`, a `title` to nazwa statusu HTTP.
Komunikaty API są zawsze po angielsku (niezależnie od `Accept-Language`); widok Twig zostaje po polsku.

Przykład: przelew z konta PLN na konto CHF bez `amountTo` (ID kont zależą od danych; token z `make token`):

```bash
$ curl -s -X POST http://localhost:8081/api/transfers -H "Authorization: Bearer $TOKEN" \
    -H 'Content-Type: application/ld+json' \
    -d '{"fromAccountId": 1, "toAccountId": 5, "operationDate": "2026-01-15", "amountFrom": "100.00"}'
```

```json
{
  "type": "urn:finance-app:problem:transfer.amount_to_required_for_currency_exchange",
  "title": "Unprocessable Content",
  "status": 422,
  "detail": "For different currencies amountTo is required.",
  "errorCode": "transfer.amount_to_required_for_currency_exchange",
  "params": { "fromCurrency": "PLN", "toCurrency": "CHF" }
}
```

(Skrócone: błędy zasobów API Platform zawierają też pola JSON-LD `@context`, `@id`, `@type` i `description`.)

Rodzaje kodów:

- **walidacja (`422`)** — `errorCode: validation.failed` i lista `violations`; każde naruszenie ma `propertyPath`
  (pole JSON), własny `code` (ten sam klucz co komunikat formularza, np. `transfer.to_account_required`), `params`
  (tylko `limit`, `min`, `max`, `type` — nigdy wysłana wartość) i angielski `message`,
- **reguły biznesowe** — np. `transfer.accounts_must_differ` (`422`), `fixed_expense.plan_duplicate` (`409`),
  `bank.not_found` (`404`, rekord z adresu nie istnieje; nieznany adres ma `http.not_found`);
  ten sam problem ma ten sam kod w formularzu Twig i w API; dane odrzucone dopiero przy zapisie (np. kwota
  w zapisie `1e2`) mają ogólny `http.unprocessable_content`,
- **uwierzytelnianie (`401`)** — `auth.token_missing`, `auth.token_invalid`, `auth.token_expired`,
  `auth.invalid_credentials`, `auth.too_many_login_attempts`,
- **pozostałe** — `http.` + stała nazwa statusu, np. `http.not_found`, `http.bad_request`, `http.internal_server_error`
  (status bez nazwy w tabeli dostaje numer, np. `http.418`);
  dotyczy też `/api/login_check` (np. `400` przy zepsutym JSON, `415` gdy body nie jest JSON-em) i nieznanych
  ścieżek `/api`.

Błąd serwera (`5xx`) ma zawsze kod `http.*` (np. `http.internal_server_error`) i puste `params`, a `detail`
poza trybem debug nie zdradza szczegółów; sam wyjątek trafia do logów (`docker compose logs php`).

Pełna lista kodów z parametrami jest w opisie API pod `/api/docs` (sekcja „Errors”); test pilnuje, żeby żaden
kod nie zniknął z dokumentacji. Kody są stabilne — można zmieniać treść `detail`, ale nie `errorCode` ani nazw `params`.

## Zasady wprowadzania danych

**Daty operacji, przelewów, cen złota i kursów walut** muszą mieścić się w zakresie
**2025-01-01 – 2070-12-31 (włącznie)**. To stała reguła biznesowa obowiązująca przy dodawaniu
i edycji danych, również dla operacji generowanych z opłat stałych.
Formularze pokazują błąd przy polu daty, a encje pilnują zakresu także przy zapisie z kodu.
API przyjmuje wyłącznie istniejące daty w formacie `YYYY-MM-DD` (np. `2028-02-29`),
bez godziny i określeń względnych takich jak `tomorrow`. Nieistniejące daty (np. `2026-02-31`)
oraz daty poza zakresem są odrzucane z kodem `422`, bez zapisywania zmian.

**Najmniejszą jednostką czasu dla dat biznesowych jest dzień.** Dotyczy to operacji na koncie
(także przelewów i zaplanowanych opłat stałych), cen złota oraz kursów walut.
W bazie `operation_date`, `price_date` i `rate_date` mają typ `DATE`, a encje zerują czas przekazany z kodu PHP.
Saldo do dziś obejmuje wszystkie operacje z datą dzisiejszą i wcześniejszą.
Czasy utworzenia i modyfikacji rekordów (`created_at`, `updated_at`) są przechowywane z godziną.

**Wybór cen złota i kursów walut jest wspólny dla wykresów i podsumowań:**
dla danego dnia wygrywa rekord z najwyższym ID, osobno dla aktywa złota lub pary bank–waluta.
Wykres przedstawia dzienne notowania chronologicznie, a bieżąca wycena korzysta z najnowszego dnia.
Dopisanie starszego notowania z wyższym ID nie zmienia bieżącej wyceny.
Każde aktywo złota ma własną serię na wykresie, również gdy ma tę samą formę, masę lub opis co inne aktywo.
Listy cen i kursów pokazują wszystkie wpisy od najnowszej daty, a w obrębie dnia od najwyższego ID.

Nowe operacje na koncie dodajemy bez znaku, jeśli to **przychód** na koncie: "309 zł refundacja /zwrot"
lub ze znakiem `-` jeżeli to **wydatek** z konta: "-309 zł zakup okularów"

**Przelewy / transfery / "kantor"** — jeżeli robisz przelew między kontami o tej samej walucie,
wtedy druga kwota (`amountTo`) jest generowana automatycznie: na konto docelowe wchodzi kwota z pierwszego pola.
W formularzu to pole jest wtedy zablokowane, a w API nie wolno go wysyłać: każda wartość, także `null`, daje `422`
z kodem `transfer.amount_to_not_allowed_for_same_currency`.
Jeżeli robisz przelew między kontami o różnych walutach,
musisz określić jaką kwotę zdejmujesz z pierwszego konta i jaka wejdzie na drugie konto: "100 PLN / 25 EUR".
Tutaj NIE podajemy żadnych znaków `+/-`.

Dla poprawnego wyliczenia podsumowań **musisz** dodać kursy walut,
przynajmniej dla tych banków, w których trzymasz walutę.

**Opłaty stałe / planowanie przyszłych cyklicznych wydatków** (czynsz, raty itp).
Wystarczy dodać opłaty stałe do systemu i zanim zacznie się nowy miesiąc dorzucić je do konta głównego ("Zaplanuj wydatki").
Konto główne oznacza się gwiazdką (☆) na liście kont — klik ustawia je jako główne (`Account.isMain`, wymuszone unikalnym indeksem w bazie).
Ukrytego konta głównego nie da się użyć do planowania (tak jak ukrytego konta nie można wybrać do nowej operacji
ani przelewu): planowanie kończy się komunikatem, a API odpowiada `409` z `errorCode: fixed_expense.plan_main_hidden`.

**Gospodarstwa** (`household`) — to tylko etykieta do rozdzielenia sald na dwie kieszenie, nie mechanizm dostępu:
- **Nasze** — ja i partnerka
- **Matka** — gospodarstwo matki, którym się opiekuję

Appka jest **tylko dla mnie** (ewentualnie partnerki) — jeden zalogowany user widzi i edytuje wszystko,
bez podziału po właścicielu. To świadoma decyzja, nie dziura do załatania: nikogo trzeciego tu nie
wpuszczam i nie planuję. Gdyby to się kiedyś zmieniło, `household` w obecnej postaci nic by nie chronił —
trzeba by dopiero dobudować izolację per-użytkownik (patrz [Bezpieczeństwo](#bezpieczeństwo)). Na razie to i tak się nie zmieni — YAGNI.
