# PGB Portaal — Design Spec

## Overzicht

Een portaal voor PGB-budgethouders om het zorgbudget en de planning van hun cliënten te beheren. De budgethouder is de hoofdgebruiker; zorgverleners kunnen optioneel een read-only account krijgen om hun eigen rooster in te zien.

## Techstack

- **Backend**: Laravel
- **Frontend**: Inertia.js + React
- **Database**: PostgreSQL
- **Architectuur**: Monoliet — één codebase, één deployment

## Datamodel

### Users

Iedereen die inlogt op het portaal.

| Kolom      | Type                              | Beschrijving              |
|------------|-----------------------------------|---------------------------|
| id         | bigint (PK)                       |                           |
| name       | string                            |                           |
| email      | string (unique)                   |                           |
| password   | string                            |                           |
| role       | enum: budget_holder, caregiver    | Bepaalt toegangsniveau    |
| timestamps |                                   |                           |

### Clients

De personen die zorg ontvangen.

| Kolom            | Type        | Beschrijving                  |
|------------------|-------------|-------------------------------|
| id               | bigint (PK) |                               |
| budget_holder_id | FK → users  | De budgethouder die beheert   |
| name             | string      |                               |
| date_of_birth    | date        |                               |
| notes            | text (null) |                               |
| timestamps       |             |                               |

Een budgethouder kan meerdere cliënten beheren. Typisch is het er één.

### Caregivers

Zorgverleners gekoppeld aan een cliënt.

| Kolom       | Type                                                  | Beschrijving                              |
|-------------|-------------------------------------------------------|-------------------------------------------|
| id          | bigint (PK)                                           |                                           |
| client_id   | FK → clients                                          |                                           |
| user_id     | FK → users (nullable)                                 | Ingevuld als de zorgverlener een account heeft |
| name        | string                                                |                                           |
| type        | enum: parent, care_worker, day_care, zzp, other       |                                           |
| hourly_rate | decimal(8,2) (nullable)                               | Uurtarief voor budgetberekeningen         |
| timestamps  |                                                       |                                           |

### BudgetCategories

Deelbudgetten per cliënt (bijv. persoonlijke verzorging, begeleiding, dagbesteding).

| Kolom            | Type         | Beschrijving                          |
|------------------|--------------|---------------------------------------|
| id               | bigint (PK)  |                                       |
| client_id        | FK → clients |                                       |
| name             | string       | Bijv. "Persoonlijke verzorging"       |
| allocated_amount | decimal(10,2)| Toegewezen budget                     |
| spent_amount     | decimal(10,2)| Cached totaal, berekend uit expenses  |
| timestamps       |              |                                       |

### BudgetExpenses

Individuele kosten binnen een budgetcategorie.

| Kolom              | Type                  | Beschrijving |
|--------------------|-----------------------|--------------|
| id                 | bigint (PK)           |              |
| budget_category_id | FK → budget_categories|              |
| caregiver_id       | FK → caregivers (null)|              |
| description        | string                |              |
| amount             | decimal(10,2)         |              |
| date               | date                  |              |
| timestamps         |                       |              |

### Schedules

Terugkerend weekschema.

| Kolom        | Type              | Beschrijving       |
|--------------|-------------------|--------------------|
| id           | bigint (PK)       |                    |
| client_id    | FK → clients      |                    |
| caregiver_id | FK → caregivers   |                    |
| day_of_week  | tinyint (0-6)     | 0 = maandag        |
| start_time   | time              |                    |
| end_time     | time              |                    |
| notes        | text (nullable)   |                    |
| timestamps   |                   |                    |

### ScheduleExceptions

Afwijkingen op het weekschema en losse afspraken.

| Kolom        | Type                                 | Beschrijving                        |
|--------------|--------------------------------------|-------------------------------------|
| id           | bigint (PK)                          |                                     |
| schedule_id  | FK → schedules (nullable)            | Null = losse afspraak               |
| client_id    | FK → clients                         |                                     |
| caregiver_id | FK → caregivers                      |                                     |
| date         | date                                 |                                     |
| start_time   | time                                 |                                     |
| end_time     | time                                 |                                     |
| type         | enum: cancelled, modified, added     |                                     |
| notes        | text (nullable)                      |                                     |
| timestamps   |                                      |                                     |

## Pagina's & Navigatie

### Dashboard (`/dashboard`)

- Overzicht per cliënt: budgetvoortgang (staafdiagrammen per categorie), planning van vandaag/deze week
- Snelle acties: kosten toevoegen, afspraak inplannen

### Cliënten (`/clients`)

- Lijst van cliënten
- Cliënt detail (`/clients/{id}`) — profiel, gekoppelde zorgverleners, snel naar budget/planning

### Budget (`/clients/{id}/budget`)

- Overzicht per categorie: toegewezen vs. besteed met voortgangsbalk
- Kosten toevoegen/bewerken/verwijderen
- Filteren op categorie, zorgverlener, periode

### Planning (`/clients/{id}/schedule`)

- Weekoverzicht als kalenderweergave met de vaste planning
- Afwijkingen markeren (geannuleerd, gewijzigd, extra)
- Dag/week toggle

### Zorgverleners (`/clients/{id}/caregivers`)

- Lijst van zorgverleners per cliënt met type en uurtarief
- Toevoegen/bewerken/verwijderen
- Optioneel: uitnodigen om een account aan te maken

### Zorgverlener-view (als zorgverlener inlogt)

- Alleen eigen rooster inzien (read-only) over alle cliënten waar ze aan gekoppeld zijn

## Autorisatie & Rollen

### Budgethouder (`budget_holder`)

- Volledige toegang tot eigen cliënten en alles daaronder (budget, planning, zorgverleners)
- Kan zorgverleners uitnodigen voor een account
- Kan geen cliënten van andere budgethouders zien

### Zorgverlener (`caregiver`)

- Kan alleen eigen rooster inzien (read-only)
- Ziet een overzicht van alle cliënten waar ze aan gekoppeld zijn, met per cliënt hun eigen planning
- Geen toegang tot budgetinformatie

### Implementatie

- Simpele `role` kolom op de `users` tabel — geen pakket als Spatie Permissions nodig
- Laravel middleware om rolgebaseerde toegang af te dwingen
- Policy classes per model voor fijnmazige autorisatie (bijv. "mag deze user deze cliënt zien?")
- Scoping via Eloquent: budgethouder ziet alleen `Client::where('budget_holder_id', auth()->id())`
