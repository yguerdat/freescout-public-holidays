# Public Holidays — FreeScout Module

Region-aware **public holidays** for [FreeScout](https://freescout.net/). Tell your customers — and your other software — when the office is closed.

Built originally for a help desk based in the **Canton of Jura (Switzerland)**, whose public holidays differ from (and outnumber) those of clients elsewhere in French-speaking Switzerland. Fully configurable for any Swiss canton.

![License](https://img.shields.io/badge/license-AGPL--3.0-blue)
![FreeScout](https://img.shields.io/badge/FreeScout-1.8.58+-green)

## What it does

- **Holiday-aware auto-reply** — When a customer opens a ticket on a public holiday, the ticket-received confirmation email shows a "we are closed today" notice with the holiday name and the next working day. On a normal day, nothing changes.
- **Offline holiday computation** — Swiss public holidays are computed locally (Easter algorithm + cantonal matrix). No network call when an email is sent. Cantonal holidays such as the **23 June Jura plebiscite** are included.
- **REST API** — Your other apps (e.g. a customer platform creating tickets through the FreeScout API) can ask whether the office is open today and when it reopens, then show their own message.
- **Agent banner** — Agents creating a conversation on a holiday see a discreet reminder that the office is officially closed.
- **Fully editable** — Generated holidays are stored in the database; an admin can add custom days or remove any entry. Multi-canton supported for offices with several locations.
- **Trilingual** — Admin UI and holiday notices in French, English and German. Add a language by dropping a JSON file in `Resources/lang/`.

## Requirements

- [FreeScout](https://github.com/freescout-help-desk/freescout) >= 1.8.58
- Optional: the **API & Webhooks** module (to reuse its API key for the REST endpoints)

## Installation

1. Copy the `PublicHolidays` folder into your FreeScout `Modules/` directory:
   ```bash
   cp -r PublicHolidays /path/to/freescout/Modules/
   ```
2. Run the database migration:
   ```bash
   cd /path/to/freescout
   php artisan migrate
   ```
3. Rebuild module assets:
   ```bash
   php artisan freescout:module-build
   ```
4. Go to **Manage → Modules** and activate **Public Holidays**.
5. Open **Manage → Public Holidays**, select your canton(s), and click **Generate / refresh** for the current (and next) year.

## Configuration

Under **Manage → Public Holidays**:

| Setting | Description |
|---|---|
| **Observed cantons** | The canton(s) your office observes. Default: **Jura (CH-JU)**. Drives the "closed today" logic. |
| **Weekends** | Whether Saturdays/Sundays count as closed when computing the next working day. |
| **Auto-reply subject** | Optional prefix added to the auto-reply subject on holidays (e.g. `[Fermé aujourd'hui]`). |
| **Holiday notice** | The HTML inserted into the auto-reply on holidays (per language). |
| **API token** | Optional dedicated token; the FreeScout API key also works. |

### Making the auto-reply holiday-aware

The module exposes template variables you can drop into your mailbox auto-reply (**Mailbox → Settings → Auto Reply**). On a working day they are empty, so nothing shows.

| Variable | On a holiday | On a working day |
|---|---|---|
| `{%holiday.notice%}` | Configured HTML notice block | *(empty)* |
| `{%holiday.name%}` | e.g. `Fête-Dieu` | *(empty)* |
| `{%holiday.date%}` | e.g. `04.06.2026` | *(empty)* |
| `{%holiday.is_holiday%}` | `1` | *(empty)* |
| `{%holiday.next_working_day%}` | e.g. `05.06.2026` | next working day |

Example auto-reply template:

```
{%holiday.notice%}
Bonjour {%customer.firstName%},

Nous avons bien reçu votre demande (ticket #{%conversation.number%}) et reviendrons vers vous au plus vite.
```

## REST API

All endpoints are authenticated with the header `X-FreeScout-API-Key: <key>` (the FreeScout *API & Webhooks* key, or the module's own token).

### `GET /api/publicholidays/status`

Is the office open today (or on `?date=YYYY-MM-DD`)?

```json
{
  "date": "2026-06-23",
  "cantons": ["CH-JU"],
  "office_open": false,
  "is_holiday": true,
  "is_weekend": false,
  "holiday": {
    "date": "2026-06-23",
    "name": "Commémoration du plébiscite jurassien",
    "key": "jura_plebiscite",
    "canton": "CH-JU",
    "type": "cantonal"
  },
  "next_working_day": "2026-06-24"
}
```

Add `?locale=fr|en|de` to localize names.

### `GET /api/publicholidays?year=2026[&canton=CH-JU]`

List all holidays for a year.

### `GET /api/publicholidays/upcoming?limit=5`

The next holidays starting today.

## How holidays are computed

Movable feasts (Good Friday, Easter Monday, Ascension, Whit Monday, Corpus Christi) are derived from Easter Sunday using the Meeus/Jones/Butcher algorithm — no PHP calendar extension and no network required. Fixed dates and special days (Geneva Fast, Federal Fast Monday) are added per the cantonal matrix in `Services/HolidayCalculator.php`.

The canton coverage matrix is a best-effort reflection of the commonly published Swiss public-holiday list. **Jura (CH-JU)**, the default, is verified. Because generated holidays live in the database and are editable, any local particularity can be corrected in the admin UI without touching code.

## File structure

```
PublicHolidays/
├── Config/config.php
├── Database/Migrations/                 # public_holidays table
├── Entities/PublicHoliday.php           # Eloquent model + queries
├── Services/
│   ├── HolidayCalculator.php            # offline Swiss/cantonal computation
│   └── HolidayService.php               # status, next working day, notice
├── Http/
│   ├── Controllers/PublicHolidaysController.php   # admin
│   ├── Controllers/ApiController.php              # REST API
│   ├── Middleware/ApiAuth.php
│   └── routes.php
├── Providers/PublicHolidaysServiceProvider.php    # hooks: mail vars, subject, menu, banner
├── Resources/
│   ├── views/settings/section.blade.php
│   └── lang/                            # fr/en/de (UI + holiday names)
├── Public/{css,js,img}
├── module.json
└── start.php
```

## Security

- REST endpoints require a valid API key (`hash_equals` comparison); CORS preflight handled.
- Admin routes require authentication + the admin role, with CSRF protection.
- The holiday logic never throws into the mail pipeline — failures are logged and the email is sent unchanged.

## License

[AGPL-3.0](https://www.gnu.org/licenses/agpl-3.0.en.html) — same as FreeScout.

## Credits

- **Yannick Guerdat** — Module author ([GitHub](https://github.com/yguerdat))
- [FreeScout](https://freescout.net/) — Free open-source help desk
- Built with the assistance of [Claude Code](https://claude.com/claude-code) by Anthropic
