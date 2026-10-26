# Finance App

[![CI](https://github.com/021800rr/Finance-App/actions/workflows/ci.yml/badge.svg)](https://github.com/021800rr/Finance-App/actions/workflows/ci.yml)

Osobisty tracker finansowy do kontroli domowych kont (Symfony 8.1 / PHP 8.5, PostgreSQL, Docker) — napisany jako pełnoprawna aplikacja z API, a nie skrypt na kolanie.

Projekt zaczął się jako "domowy Excel", ale po drodze zamienił się w okazję do poćwiczenia architektury, jaką stosuję zawodowo: czyste rozdzielenie warstw, pełne pokrycie testami i REST API zgodne z OpenAPI.

## Co tu jest warte pokazania

- **Architektura API Platform** oparta o wzorzec Resource / Provider / Processor — logika odczytu i zapisu jest rozdzielona i niezależnie testowalna, bez tuczenia kontrolerów.
- **PHPStan na poziomie 9**.
- **~900 testów PHPUnit** (jednostkowe + warstwa Provider/Processor) pokrywających logikę biznesową: wyliczanie sald, kursy walut, operacje na kontach, złoto, emeryturę.
- **PHP CS Fixer** wymuszający spójny styl kodu.
- **Pełna dokumentacja API** (OpenAPI/Swagger) pod `/api/docs` — auto-generowana z adnotacji, zawsze aktualna.
- **Świadomy model bezpieczeństwa** — hasła hashowane, throttling logowania, jawnie opisane granice (patrz sekcja [Bezpieczeństwo](#bezpieczeństwo)).

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
make user        # załóż konto demo
make token       # pobierz token JWT konta demo
```

## Logowanie

Demo user: `twoj@email.pl` / `haslo123`

web: http://localhost:8081  
api: http://localhost:8081/api/docs

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
