# Beschikbaarheid & Claim Systeem — Design Spec

## Overzicht

Budgethouders kunnen beschikbare tijdslots plaatsen op de planning van een cliënt. Alle gekoppelde zorgverleners zien deze slots en kunnen ze claimen (wie het eerst komt). Na het claimen wordt het slot een normale afspraak. Bij annulering komt het slot terug als beschikbaar.

## Datamodel

### AvailabilitySlot

Beschikbare tijdslots die geclaimd kunnen worden door zorgverleners.

| Kolom | Type | Beschrijving |
|-------|------|-------------|
| id | bigint (PK) | |
| client_id | FK → clients, cascadeOnDelete | |
| day_of_week | tinyint (nullable) | 0-6, ingevuld bij terugkerend slot |
| date | date (nullable) | Ingevuld bij eenmalig slot |
| start_time | time | |
| end_time | time | |
| status | enum: open, claimed | Default: open |
| claimed_by | FK → caregivers (nullable, nullOnDelete) | |
| claimed_at | datetime (nullable) | |
| schedule_id | FK → schedules (nullable, nullOnDelete) | Aangemaakte Schedule bij terugkerend claim |
| schedule_exception_id | FK → schedule_exceptions (nullable, nullOnDelete) | Aangemaakte ScheduleException bij eenmalig claim |
| notes | text (nullable) | |
| timestamps | | |

Constraint: `day_of_week` OF `date` is ingevuld, nooit beide. Dit wordt afgedwongen via validatie in de FormRequest, niet via database constraint.

### Nieuw Enum: AvailabilitySlotStatus

```php
enum AvailabilitySlotStatus: string
{
    case Open = 'open';
    case Claimed = 'claimed';
}
```

## Claim Flow

### Claimen (zorgverlener)

1. Zorgverlener ziet open slots in "Mijn rooster"
2. Klikt "Claimen" op een slot
3. Backend:
   - Controleert dat slot status `open` is
   - Zet status → `claimed`, `claimed_by` → caregiver id, `claimed_at` → now
   - Als terugkerend slot (`day_of_week` ingevuld): maakt Schedule aan, slaat `schedule_id` op
   - Als eenmalig slot (`date` ingevuld): maakt ScheduleException (type `added`) aan, slaat `schedule_exception_id` op
4. Slot verdwijnt als beschikbaarheid, verschijnt als normale afspraak

### Annuleren (de gekoppelde afspraak wordt verwijderd)

1. Schedule of ScheduleException wordt verwijderd via bestaande delete flow
2. Een Eloquent model observer op Schedule en ScheduleException controleert bij delete of er een AvailabilitySlot aan gekoppeld is
3. Zo ja: reset slot naar `open` — `claimed_by`, `claimed_at`, `schedule_id`/`schedule_exception_id` worden null
4. Het slot verschijnt weer als beschikbaar

### Beschikbaarheid verwijderen (budgethouder)

- Slot is `open`: gewoon verwijderen
- Slot is `claimed`: gekoppelde Schedule of ScheduleException wordt ook verwijderd, de afspraak verdwijnt

## Weergave in de Agenda

### Budgethouder-view (planning pagina)

- Open slots: **paarse** blokken met stippellijn-border, label "Beschikbaar" + tijden
- Geclaimde slots: niet zichtbaar als beschikbaarheid — ze zijn gewone blauwe afspraken geworden
- Nieuwe knop: "+ Beschikbaarheid" naast bestaande knoppen
- Formulier: keuze terugkerend (dag selecteren + tijden) of eenmalig (datum + tijden), optionele notitie

### Zorgverlener-view (mijn rooster)

- Open slots van alle gekoppelde cliënten worden getoond als paarse blokken met een "Claimen" knop
- Na claimen wordt het een normaal rooster-item (blauw blok)

## Autorisatie

- **Budgethouder**: kan AvailabilitySlots aanmaken, bewerken en verwijderen voor eigen cliënten
- **Zorgverlener**: kan alleen open slots claimen (POST naar claim endpoint) voor cliënten waar ze aan gekoppeld zijn. Kan niet aanmaken, bewerken of verwijderen.

## Routes

```
POST   /clients/{client}/availability-slots              → store (budgethouder)
DELETE /clients/{client}/availability-slots/{slot}        → destroy (budgethouder)
POST   /availability-slots/{slot}/claim                   → claim (zorgverlener)
```

## Controllers

### AvailabilitySlotController (budgethouder)

- `store(AvailabilitySlotRequest, Client $client)`: authorize view op client, maakt slot aan met status `open`
- `destroy(Client $client, AvailabilitySlot $slot)`: authorize view op client, als claimed → verwijder gekoppelde Schedule/ScheduleException ook, verwijder slot

### AvailabilityClaimController (zorgverlener)

- `store(AvailabilitySlot $slot)`: controleert dat user een caregiver is met user_id gekoppeld, dat caregiver bij de juiste client hoort, dat slot status `open` is. Voert de claim uit.

## Observers

### ScheduleObserver (deleted event)

- Check of er een AvailabilitySlot bestaat met `schedule_id` = dit schedule
- Zo ja: reset slot naar open

### ScheduleExceptionObserver (deleted event)

- Check of er een AvailabilitySlot bestaat met `schedule_exception_id` = deze exception
- Zo ja: reset slot naar open
