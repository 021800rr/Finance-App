# Finance App

[![CI](https://github.com/021800rr/Finance-App/actions/workflows/ci.yml/badge.svg)](https://github.com/021800rr/Finance-App/actions/workflows/ci.yml)

Osobisty tracker finansowy do kontroli domowych kont.

## Stack

- Symfony 8.1, API Platform 5.0, PHP 8.5, Doctrine ORM
- PostgreSQL
- Symfony Security, JWT
- PHPUnit, PHPStan, PHP CS Fixer

## Uruchomienie

```bash
git clone https://github.com/021800rr/Finance-App.git
cd Finance-App

make build       # zbuduj i uruchom kontenery
make install     # composer install
make migrate     # migracja
make fixtures    # dane demo
make test        # phpunit + phpstan + cs-fixer etc...
make user        # załóż konto demo
make token       # pobierz token konta demo
```

## Logowanie

Demo user: `twoj@email.pl` / `haslo123`

| Usługa| Adres|
|:---|:---|
| react | http://localhost:5173 |
| twig| http://localhost:8081|
| swagger | http://localhost:8081/api/docs |
| openapi| http://localhost:8081/api/docs.jsonopenapi |


## Bezpieczeństwo

- Aplikacja jest jednoosobowa.
- Throttling logowania 5 nieudanych prób na 15 minut.
- JWT żyje 15 minut `token_ttl: 900`, refresh token 30 dni.
- Blacklista JWT.
- Nie ma resetu hasła, zmiany hasła, 2FA ani blokady konta.

Endpointy logowania:

- `POST /api/login_check` — logowanie (`email`, `password`), zwraca JWT i refresh token,
- `POST /api/token/refresh` — nowa para tokenów w zamian za refresh token,
- `POST /api/token/invalidate` — wylogowanie: usuwa refresh token i blokuje JWT.

Tokeny trafiają do ciasteczek, więc przeglądarka wysyła je sama, a JavaScript ich nie odczyta. Curl 
i Swagger biorą tokeny z treści odpowiedzi i wysyłają JWT jako `Authorization: Bearer <token>`.



## Błędy API

Każdy błąd API to [RFC 9457 Problem Details](https://www.rfc-editor.org/rfc/rfc9457.html) z trzema polami:

- `errorCode` — stały kod błędu; po nim klient rozpoznaje, co się stało,
- `params` — dane do komunikatu (zawsze obiekt, wartości jako tekst; `{}` gdy brak),
- `detail` — prosty tekst dla curla / Postmana; jego treść może się zmieniać, nie parsować.

Przykład:

```bash
$ curl -s -X POST http://localhost:8081/api/transfers -H "Authorization: Bearer $TOKEN" \
    -H 'Content-Type: application/ld+json' \
    -d '{
    "fromAccountId": 1, 
    "toAccountId": 3, 
    "operationDate": "2026-01-15", 
    "amountFrom": "100.00"
  }'
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

Daty operacji muszą mieścić się w zakresie 2025-01-01 – 2070-12-31.

Najmniejszą jednostką czasu jest doba. 

Operacje na koncie dodajemy:  
**bez znaku**, jeśli to **przychód**: "309 zł refundacja/zwrot" lub  
**ze znakiem** `-` jeżeli to **wydatek**: "-309 zł zakup okularów"

Jeżeli robisz przelew między kontami o tej samej walucie, wtedy kwota na koncie docelowym jest uzupełniana automatycznie.  
Jeżeli robisz przelew między kontami o różnych walutach, musisz podać obie kwoty: "100 PLN / 25 EUR". 
**NIE** podajemy żadnych znaków `+/-`.

Opłaty stałe (czynsz, subskrypcje, abonamenty ...): dodaj opłaty stałe do systemu i zanim zacznie się nowy 
miesiąc dorzucić je do konta głównego ("Zaplanuj wydatki").
