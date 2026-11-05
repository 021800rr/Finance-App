# Finance App

[![CI](https://github.com/021800rr/Finance-App/actions/workflows/ci.yml/badge.svg)](https://github.com/021800rr/Finance-App/actions/workflows/ci.yml)

Osobisty tracker finansowy do kontroli domowych kont.

## Co tu jest warte pokazania

- **Architektura API Platform** oparta o wzorzec Resource / Provider / Processor — logika odczytu i zapisu jest 
    rozdzielona i niezależnie testowalna, bez tuczenia kontrolerów.
- **~1630 testów PHPUnit**.
- **PHPStan na najwyższym poziomie (`max`)**.
- **PHP CS Fixer**.
- **Pełna dokumentacja API** (OpenAPI/Swagger) pod `/api/docs` — auto-generowana z adnotacji, zawsze aktualna.
- **Przewidywalne błędy API** (RFC 9457 Problem Details) — każdy błąd ma stały `errorCode`, parametry
    i opis; szczegóły w sekcji [Błędy API](#błędy-api).

## Stack

- Symfony 8.1 / PHP 8.5, Doctrine ORM, API Platform
- PostgreSQL
- Symfony Security (form login do widoku Twig) + JWT (LexikJWTAuthenticationBundle) dla API
- PHPUnit, PHPStan, PHP CS Fixer
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

- **Konta zakłada się z CLI**, `php bin/console app:create-user <email> <hasło>`.
- **E-mail jest normalizowany** (trim + małe litery) przy zapisie i przy logowaniu.
- **Throttling logowania** 5 nieudanych prób na 15 minut.
- **JWT żyje 8 godzin** `token_ttl: 28800`.

**Świadome ograniczenia:**

- Tokeny JWT **nie są odwoływalne** — wylogowanie nie unieważnia tokena. Nie ma tu odświeżania tokenów i blacklisty.
- Nie ma resetu hasła, zmiany hasła, 2FA ani blokady konta.
- `/api/docs` jest publiczne.
- Jeden user - **brak izolacji danych per użytkownik**.

## Błędy API

Każdy błąd API to [RFC 9457 Problem Details](https://www.rfc-editor.org/rfc/rfc9457.html) z trzema polami:

- **`errorCode`** — stały kod błędu; po nim klient rozpoznaje, co się stało,
- **`params`** — dane do komunikatu (zawsze obiekt, wartości jako tekst; `{}` gdy brak),
- **`detail`** — prosty tekst dla curla / Postmana; jego treść może się zmieniać, nie parsować.

`type` to `urn:finance-app:problem:` + `errorCode`, a `title` to nazwa statusu HTTP.

Przykład: przelew z konta PLN na konto CHF bez `amountTo` (ID kont zależą od danych; token z `make token`):

```bash
$ curl -s -X POST http://localhost:8081/api/transfers -H "Authorization: Bearer $TOKEN" \
    -H 'Content-Type: application/ld+json' \
    -d '{"fromAccountId": 1, "toAccountId": 3, "operationDate": "2026-01-15", "amountFrom": "100.00"}'
```

```json
{
  "title":     "Unprocessable Content",
  "detail":    "For different currencies amountTo is required.",
  "status":    422,
  "type":      "urn:finance-app:problem:transfer.amount_to_required_for_currency_exchange",
  "errorCode": "transfer.amount_to_required_for_currency_exchange",
  "params": {
    "fromCurrency": "PLN",
    "toCurrency":   "CHF"
  }
} 
```

## Zasady wprowadzania danych

**Daty operacji, przelewów, cen złota i kursów walut** muszą mieścić się w zakresie
**2025-01-01 – 2070-12-31 (włącznie)**.

**Najmniejszą jednostką czasu dla dat biznesowych jest doba.** 

Nowe operacje na koncie dodajemy bez znaku, jeśli to **przychód** na koncie: "309 zł refundacja/zwrot" lub ze znakiem 
`-` jeżeli to **wydatek** z konta: "-309 zł zakup okularów"

**Przelewy / transfery / "kantor"** — jeżeli robisz przelew między kontami o tej samej walucie, wtedy konto docelowe 
jest generowane automatycznie. W formularzu to pole jest zablokowane, a w API nie wolno go wysyłać.  
Jeżeli robisz przelew między kontami o różnych walutach, musisz określić jaką kwotę zdejmujesz z pierwszego konta i jaka 
wejdzie na drugie konto: "100 PLN / 25 EUR". NIE podajemy żadnych znaków `+/-`.

Dla poprawnego wyliczenia podsumowań **musisz** dodać kursy walut, przynajmniej dla tych banków, w których trzymasz 
walutę.

**Opłaty stałe / planowanie wydatków cyklicznych** (czynsz, subskrypcje, abonamenty itp). Wystarczy dodać opłaty stałe 
do systemu i zanim zacznie się nowy miesiąc dorzucić je do konta głównego ("Zaplanuj wydatki").

