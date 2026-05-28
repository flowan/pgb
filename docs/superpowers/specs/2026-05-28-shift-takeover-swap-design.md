# Shift Takeover & Swap — Design Spec

## Overzicht

Zorgverleners kunnen collega's planning zien en op drie manieren wijzigingen voorstellen:

1. **Aanbieden voor overname** — Een eigen shift open zetten voor collega's. Eerste claimer krijgt de shift.
2. **Direct ruilen (1-op-1)** — Een specifieke collega vragen om te ruilen met een specifieke shift.
3. **Open ruilverzoek** — Eigen shift markeren als ruilbaar, collega's bieden eigen shifts aan, jij kiest welke ruil door gaat.

Goedkeuring is peer-to-peer: collega's regelen het onderling. Budgethouder ontvangt notificaties maar heeft geen veto.

## Scope

- Aanbod/verzoek mag alleen naar collega-caregivers van **dezelfde cliënt**.
- Notificaties via Laravel Notifications: in-app (database channel) + e-mail.
- Auto-expiratie via dagelijkse scheduled job zodra de shift-datum verstreken is.
- Eigen intrekken altijd mogelijk vóór acceptatie.

## Datamodel

### ShiftTakeoverOffer

Aanbod om een eigen shift te laten overnemen.

| Kolom | Type | Beschrijving |
|-------|------|-------------|
| id | bigint (PK) | |
| schedule_id | FK → schedules (nullable, nullOnDelete) | Bij recurring shift |
| schedule_exception_id | FK → schedule_exceptions (nullable, nullOnDelete) | Bij eenmalige shift |
| date | date | De specifieke datum die wordt aangeboden |
| offered_by_caregiver_id | FK → caregivers (cascadeOnDelete) | |
| status | enum: open, claimed, cancelled, expired | Default: open |
| claimed_by_caregiver_id | FK → caregivers (nullable, nullOnDelete) | |
| claimed_at | datetime (nullable) | |
| resulting_exception_id | FK → schedule_exceptions (nullable, nullOnDelete) | De aangemaakte modified-exception bij claim |
| notes | text (nullable) | |
| timestamps | | |

**Validatie:** precies één van `schedule_id` of `schedule_exception_id` is gevuld.

### ShiftSwapRequest

1-op-1 directe ruil tussen twee specifieke caregivers.

| Kolom | Type | Beschrijving |
|-------|------|-------------|
| id | bigint (PK) | |
| requester_caregiver_id | FK → caregivers (cascadeOnDelete) | |
| requester_schedule_id | FK → schedules (nullable, nullOnDelete) | |
| requester_schedule_exception_id | FK → schedule_exceptions (nullable, nullOnDelete) | |
| requester_date | date | |
| target_caregiver_id | FK → caregivers (cascadeOnDelete) | |
| target_schedule_id | FK → schedules (nullable, nullOnDelete) | |
| target_schedule_exception_id | FK → schedule_exceptions (nullable, nullOnDelete) | |
| target_date | date | |
| status | enum: pending, accepted, declined, cancelled, expired | Default: pending |
| responded_at | datetime (nullable) | |
| decline_reason | text (nullable) | |
| resulting_exception_ids | jsonb (nullable) | De twee aangemaakte exceptions |
| timestamps | | |

**Validatie:**
- Precies één van `requester_schedule_id` of `requester_schedule_exception_id` is gevuld
- Precies één van `target_schedule_id` of `target_schedule_exception_id` is gevuld
- requester en target caregiver moeten bij dezelfde cliënt horen

### OpenSwapRequest

Open ruilverzoek: jouw shift staat ruilbaar, collega's mogen bieden.

| Kolom | Type | Beschrijving |
|-------|------|-------------|
| id | bigint (PK) | |
| schedule_id | FK → schedules (nullable, nullOnDelete) | |
| schedule_exception_id | FK → schedule_exceptions (nullable, nullOnDelete) | |
| date | date | |
| requester_caregiver_id | FK → caregivers (cascadeOnDelete) | |
| status | enum: open, fulfilled, cancelled, expired | Default: open |
| selected_offer_id | FK → open_swap_offers (nullable, nullOnDelete) | |
| fulfilled_at | datetime (nullable) | |
| resulting_exception_ids | jsonb (nullable) | |
| notes | text (nullable) | |
| timestamps | | |

### OpenSwapOffer

Een aanbod van een collega op een OpenSwapRequest.

| Kolom | Type | Beschrijving |
|-------|------|-------------|
| id | bigint (PK) | |
| open_swap_request_id | FK → open_swap_requests (cascadeOnDelete) | |
| offered_by_caregiver_id | FK → caregivers (cascadeOnDelete) | |
| offered_schedule_id | FK → schedules (nullable, nullOnDelete) | |
| offered_schedule_exception_id | FK → schedule_exceptions (nullable, nullOnDelete) | |
| offered_date | date | |
| status | enum: pending, accepted, declined, withdrawn | Default: pending |
| timestamps | | |

### Enums

```php
enum ShiftTakeoverOfferStatus: string {
    case Open = 'open';
    case Claimed = 'claimed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}

enum ShiftSwapRequestStatus: string {
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}

enum OpenSwapRequestStatus: string {
    case Open = 'open';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}

enum OpenSwapOfferStatus: string {
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}
```

## Acceptatieflows

Alle drie de flows passen de planning aan door **`ScheduleException`s aan te maken met type `modified`**. De bestaande WeekView/MonthView logica toont die exceptions automatisch in plaats van de originele schedule op die datum.

### Takeover-claim

1. Caregiver B claimt aanbod van A
2. Status → `claimed`
3. Aanmaken: `ScheduleException` (type `modified`, caregiver_id = B, date + tijden van de aangeboden shift, schedule_id = originele schedule_id of null)
4. `resulting_exception_id` opgeslagen op de takeover offer
5. Notificaties: `ShiftTakenOver` naar A + budgethouder

### Direct swap accept

1. Caregiver B accepteert het ruilverzoek van A
2. Status → `accepted`
3. Twee `ScheduleException`s aangemaakt:
   - Voor A's shift: caregiver_id = B
   - Voor B's shift: caregiver_id = A
4. Beide exception IDs opgeslagen in `resulting_exception_ids` JSON
5. Notificaties: `ShiftSwapResponded` (accept) naar A + budgethouder

### Direct swap decline

1. Caregiver B wijst het ruilverzoek af, optioneel met reden
2. Status → `declined`
3. Notificatie: `ShiftSwapResponded` (decline) naar A

### Open swap accept

1. A kiest een specifieke `OpenSwapOffer` van collega B
2. OpenSwapRequest status → `fulfilled`, `selected_offer_id` = B's offer
3. Gekozen offer status → `accepted`
4. Andere offers status → `declined`
5. Twee `ScheduleException`s aangemaakt (zoals bij direct swap)
6. Notificaties: `OpenSwapAccepted` naar B + budgethouder; `OpenSwapDeclined` naar andere bieders

## Annulering en expiratie

**Eigen intrekken:**
- Alleen vóór acceptatie, status → `cancelled`
- Caregiver intrekt eigen takeover offer / direct swap / open swap
- Caregiver kan eigen `OpenSwapOffer` intrekken (status → `withdrawn`)

**Auto-expiratie** via dagelijkse scheduled job (`php artisan schedule:run`):
- ShiftTakeoverOffer met `date < today` en status `open` → `expired`
- ShiftSwapRequest met beide dates `< today` en status `pending` → `expired`
- OpenSwapRequest met `date < today` en status `open` → `expired`

**Observer cleanup** (bij verwijdering van een Schedule of ScheduleException):
- Bestaande observers worden uitgebreid: bij delete van Schedule/ScheduleException worden alle open ShiftTakeoverOffer, ShiftSwapRequest, OpenSwapRequest en OpenSwapOffer met FK naar dat record automatisch geannuleerd (status `cancelled`)
- Notificaties bij auto-cancel: naar de betrokken partijen

**Single open request constraint:** een caregiver kan voor dezelfde shift (zelfde schedule_id+date of zelfde exception_id) maar één openstaand verzoek tegelijk hebben. Validatie in de FormRequest, niet via database constraint.

## Notificaties

Laravel's ingebouwde `Notification` class met channels `database` en `mail`. Notificaties worden naar de **User** verstuurd (niet de Caregiver) — Caregivers zonder gekoppelde user krijgen geen notificatie.

| Notification class | Wanneer | Naar wie |
|---|---|---|
| `ShiftTakeoverOffered` | A biedt shift aan | Alle collega-caregivers met user_id voor dezelfde cliënt |
| `ShiftTakenOver` | B claimt A's aanbod | A + budgethouder |
| `ShiftSwapRequested` | A vraagt directe ruil aan B | B |
| `ShiftSwapResponded` | B accepteert/wijst af | A + budgethouder (alleen bij accept) |
| `OpenSwapRequested` | A markeert shift als ruilbaar | Alle collega-caregivers met user_id voor dezelfde cliënt |
| `OpenSwapOfferReceived` | B doet aanbod op A's open verzoek | A |
| `OpenSwapAccepted` | A kiest een offer | B (de gekozen offerer) + budgethouder |
| `OpenSwapDeclined` | A kiest een ander offer | Andere bieders |
| `ShiftRequestAutoCancelled` | Onderliggende shift verwijderd | Betrokken partijen |

**Channels per notificatie:** standaard beide (`['database', 'mail']`).

**E-mail templates:** simpele Blade markdown templates met:
- Korte omschrijving (wie/wat/wanneer)
- Datum + tijden van de shift(s)
- Link naar `/my-requests` (caregiver) of `/clients/{id}/schedule` (budgethouder)

**Development:** Laravel gebruikt standaard `MAIL_MAILER=log` — e-mails komen in `storage/logs/laravel.log`. Geen extra setup nodig.

## UI-wijzigingen

### Caregiver view (`/my-schedule`)

**Backend wijziging:** `CaregiverScheduleController` laadt **alle** schedules en exceptions van de cliënten waar de ingelogde caregiver aan gekoppeld is, niet alleen die van henzelf. Tevens worden de openstaande takeover offers en open swap requests meegestuurd.

**WeekView/MonthView wijziging:** een nieuwe variant `other-caregiver` (lichter grijs, dunne rand) voor shifts die niet van de ingelogde caregiver zijn. Eigen shifts blijven blauw.

**Click op een eigen shift** → dropdown menu:
- "Aanbieden voor overname" → confirm dialog → POST
- "Direct ruilen met collega…" → modal met select voor caregiver + zijn/haar shifts → POST
- "Open ruilverzoek plaatsen" → confirm dialog → POST

**Click op een collega's shift** → popover met info. Als er een open swap request op die shift staat, knop "Bied jouw shift aan voor ruil" → modal kies eigen shift.

**Notificatie-belletje** in de header (linksboven naast cliënten-link of rechtsboven naast avatar — locatie te bepalen tijdens implementatie): rood bolletje met aantal unread + dropdown met laatste 10 notificaties.

### Nieuwe pagina `/my-requests`

Tabs:
- **Mijn aanbiedingen** — open takeover offers + open swap requests die ik heb geplaatst (incl. verlopen/geannuleerd via filter)
- **Mijn ruilverzoeken** — uitgestuurde direct swap requests
- **Verzoeken aan mij** — direct swap requests waar ik op antwoord wacht
- **Aanbiedingen die ik kan doen** — open swap requests van collega's waar ik op kan bieden

Per tab: lijst met cards. Acties: bekijken, intrekken (eigen), accepteren/afwijzen (inkomend).

### Budgethouder view (`/clients/{id}/schedule`)

**Indicator** op shifts met een openstaand verzoek (klein icoontje op het event-blok). Filter-toggle "Overnames/ruilen tonen" boven het filter.

**Notificaties** ontvangt de budgethouder bij: takeover claimed, direct swap accepted, open swap fulfilled.

## Authorisatie

| Actie | Wie |
|---|---|
| Maak takeover offer | Caregiver, voor eigen shift |
| Claim takeover offer | Andere caregiver, gekoppeld aan zelfde cliënt |
| Trek eigen takeover offer in | Caregiver die de offer maakte |
| Maak direct swap request | Caregiver, voor eigen shift + bestaande shift van target |
| Beantwoord direct swap | Target caregiver |
| Trek eigen direct swap in | Requester |
| Maak open swap request | Caregiver, voor eigen shift |
| Maak open swap offer | Andere caregiver, gekoppeld aan zelfde cliënt |
| Kies offer (accept) | OpenSwapRequest owner |
| Trek eigen offer in | Offerer |
| Markeer notificatie als gelezen | Notificatie-eigenaar |

Policies per model (`ShiftTakeoverOfferPolicy`, `ShiftSwapRequestPolicy`, `OpenSwapRequestPolicy`, `OpenSwapOfferPolicy`) — vergelijkbaar pattern als bestaande policies.

## Routes

```php
// Caregiver routes (role:caregiver)
Route::middleware(['auth', 'verified', 'role:caregiver'])->group(function () {
    Route::get('/my-requests', [MyRequestsController::class, 'index'])->name('my-requests.index');

    Route::post('/shift-takeover-offers', [ShiftTakeoverOfferController::class, 'store']);
    Route::delete('/shift-takeover-offers/{offer}', [ShiftTakeoverOfferController::class, 'destroy']);
    Route::post('/shift-takeover-offers/{offer}/claim', [ShiftTakeoverClaimController::class, 'store']);

    Route::post('/shift-swap-requests', [ShiftSwapRequestController::class, 'store']);
    Route::delete('/shift-swap-requests/{request}', [ShiftSwapRequestController::class, 'destroy']);
    Route::post('/shift-swap-requests/{request}/accept', [ShiftSwapResponseController::class, 'accept']);
    Route::post('/shift-swap-requests/{request}/decline', [ShiftSwapResponseController::class, 'decline']);

    Route::post('/open-swap-requests', [OpenSwapRequestController::class, 'store']);
    Route::delete('/open-swap-requests/{request}', [OpenSwapRequestController::class, 'destroy']);
    Route::post('/open-swap-requests/{request}/offers', [OpenSwapOfferController::class, 'store']);
    Route::delete('/open-swap-offers/{offer}', [OpenSwapOfferController::class, 'destroy']);
    Route::post('/open-swap-offers/{offer}/accept', [OpenSwapOfferController::class, 'accept']);
});

// Notifications (both roles)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
});
```

## Scheduled job (auto-expiratie)

Aanmaken `app/Console/Commands/ExpireShiftRequests.php`. Registreren in `app/Console/Kernel.php` (of `routes/console.php`) als dagelijkse taak om 01:00.

Logica: query alle 3 modellen met status `open`/`pending` waar de relevante datum(s) `< today`, update status naar `expired`.

## Frontend componenten

Nieuwe React-componenten:
- `components/notifications/notification-bell.tsx` — bell-icoon + dropdown, integratie met `<AppHeader>`
- `components/schedule/shift-action-menu.tsx` — dropdown menu op shift click
- `components/schedule/takeover-offer-dialog.tsx` — confirm dialog
- `components/schedule/direct-swap-dialog.tsx` — modal met caregiver + shift select
- `components/schedule/open-swap-dialog.tsx` — confirm dialog
- `components/schedule/swap-response-dialog.tsx` — accept/decline modal voor inkomende swap
- `components/schedule/offer-bid-dialog.tsx` — kies eigen shift om te bieden op een open swap
- `pages/my-requests/index.tsx` — tabs-pagina

WeekView/MonthView updates:
- Nieuwe variant `other-caregiver`
- `onShiftClick` callback voor eigen shifts
- Indicators voor shifts met openstaande verzoeken

## Tests

PHPUnit tests per controller + per scheduled-job:
- Model unit tests (relations, casts)
- Policy tests
- Controller feature tests (auth, validation, state transitions, schedule reassignment)
- Observer tests (auto-cancel on delete)
- Scheduled command test (expiratie)
- Notification tests (verifieer dat juiste notificaties verstuurd worden bij elke flow)
