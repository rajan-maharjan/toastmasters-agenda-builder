# Toastmasters Meeting Agenda PDF Generator

A lightweight, web-based meeting agenda management and publishing application designed specifically for Toastmasters clubs. The system enables meeting organizers and club officers to dynamically create, edit, preview, and generate professionally formatted, publication-ready A4 PDF meeting agendas adhering to official Toastmasters Area C1 standards.

## Tech Stack
- PHP 8.x (Core PHP, no framework)
- MySQL via PDO
- TCPDF (via Composer)
- Bootstrap 5.3
- Laragon (Windows local dev environment)

## Installation

Anyone can create, edit, delete, view and download agendas without signing in. Saves and deletions require session-bound CSRF tokens. The application requires a dedicated database account with a password; see [SECURITY_REVIEW.md](SECURITY_REVIEW.md) for injection defenses and parent-site isolation requirements.

### Prerequisites
- Laragon installed with Apache + MySQL
- PHP 8.0+
- Composer installed

### Steps
1. Clone/copy this folder to `D:\laragon\www\TIAgendaCreator`
2. Open Laragon and start Apache + MySQL
3. Open Terminal in project root
4. Run: `composer install`
5. Import the database schema:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create database: `toasmaster_agenda`
   - Import `sql/schema.sql`
   OR run:
   ```bash
   mysql -u root toasmaster_agenda < sql/schema.sql
   ```
6. (Optional) Add the Toastmasters logo:
   - Place `toastmasters_logo.png` in `assets/img/`
   - Image should be approximately 150x100 pixels PNG
   - Download from https://www.toastmasters.org
7. Open browser: http://localhost/TIAgendaCreator/

## Directory Structure

```
TIAgendaCreator/
├── index.php          # List all saved agendas
├── form.php           # Create/Edit agenda form
├── save.php           # Form POST handler
├── view.php           # HTML preview of agenda
├── generate_pdf.php   # TCPDF PDF generation
├── delete.php         # Delete agenda
├── config/
│   └── db.php         # PDO connection & helper functions
├── includes/
│   ├── header.php     # HTML header & navbar
│   └── footer.php     # HTML footer
├── assets/
│   ├── css/style.css  # Application styles
│   ├── js/app.js      # Dynamic form JS
│   └── img/           # Logo images
├── sql/
│   └── schema.sql     # Database schema
├── vendor/            # Composer packages (TCPDF)
├── composer.json
└── README.md
```

## Database
Database name: `toasmaster_agenda`
Connection: dedicated application account configured through `config/database.local.php` or `AGENDA_DB_*` environment variables. Do not use MySQL root for web requests.

Tables:
- `meetings` - Main meeting header data
- `officers` - Club officers for sidebar
- `functional_roles` - TMoE, GE, Ah-counter, etc.
- `tt_speakers` - Table Topics speakers
- `prepared_speakers` - Prepared speech speakers
- `evaluators` - Speech evaluators
- `app_settings` - Default values

## Usage

The builder uses an editable agenda sheet with an officers sidebar. Change a duration
on the right to recalculate every following start time and the meeting end time.
All generated times use 24-hour `HH:MM` format. Speaker slots reserve the maximum
speaking time plus one extra minute only for ranges: `1-2` reserves 3 minutes,
`5-7` reserves 8, and `2-3` reserves 4. Single values stay unchanged: `6` reserves 6.
Thus `7-9` plus `6` totals 16, and `2-3` plus `5` totals 9. Speech and evaluation inputs accept minutes or ascending
ranges with a maximum of 1–240; ordinary agenda items accept whole numbers 0–240.
The extra minute is calculated separately, so saving/reopening never adds it twice.
The end time shows a day offset when the meeting crosses midnight.

Table Topic speakers use a fixed `1-2 min` range and a 3-minute scheduled slot.
Evaluators select a speaker from Featured Speakers, with a default speaking time
of `2-3 min` and a 4-minute slot; entering just `3` reserves 3 minutes. The PDF displays
speaking durations; section totals, following start times and meeting end time add
the extra minute only for ranges. Ranges remain intact when reopening the editor.

Ballot category checkboxes default to selected and are saved with the agenda.
Unchecked categories are omitted from the PDF; unchecking all omits the ballot sheet.
The order is Better Role Takers, Better Evaluator, Better Featured Speaker, and
Better Table Topic Speaker. Table Topics ballot entries use TT Speaker 1, 2, etc.
Existing installations must import `sql/ballot_categories.sql` once (applied locally).

For an existing installation, import `sql/agenda_durations.sql` into
`toasmaster_agenda` once before using the updated builder. This adds duration storage
without changing existing agendas. New installations include it in `sql/schema.sql`.
The upgrade has already been applied to this local installation.

Run timing regression checks with `php tests/agenda_test.php`.

The Word of the Day section below the Grammarian introduction includes optional
Word, Meaning, Synonyms, and Example fields. These are saved with the agenda and
included in the preview and PDF without adding extra meeting time. Each field
accepts up to 2000 characters. Existing installations must import
`sql/word_of_the_day.sql` once; fresh installations include the fields in
`sql/schema.sql`.

Use **Add Club** to include multiple clubs, each with its own meeting number.
Select an active club from the database dropdown. The meeting title combines the
club names and numbers automatically. Each of the eight fixed officer positions
has a separate name field for each club. Names and club associations are retained
when editing and included in the PDF.

Existing installations also need `sql/meeting_clubs.sql`; this upgrade is already
applied locally. Fresh installations include both upgrades in `sql/schema.sql`.

The dropdown reads `tin_clubs` joined to `tin_fy_club_detail` in the agenda database,
filtering both tables to `is_active = '1'`. It displays `club_name` and stores `club_id`.
District, Division, and Area are editable header fields. Their entered values are
saved and retained when changing clubs. Sidebar club labels are fixed beside officer name fields.
Existing installations need `sql/club_catalogue.sql` once after `sql/meeting_clubs.sql`
(already applied locally). Provide the two catalogue tables when installing elsewhere.

Use the start-time picker to choose hours and minutes. Calculated agenda times and
saved values use 24-hour `HH:MM` format; the picker's display follows the browser locale.

1. Click 'Create New Agenda'
2. Fill in Meeting Information (date auto-defaults to next Thursday, times auto-populate)
3. Add Club Officers for the sidebar
4. Assign Functional Roles (TMoE, GE, Ah-counter, etc.)
5. Add Table Topic Speakers (dynamic - add as many as needed)
6. Add Prepared Speakers (dynamic)
7. Add Evaluators (dynamic, match to prepared speakers)
8. Click 'Save Agenda'
9. View the HTML preview, then click 'Download PDF'
10. The PDF downloads with the same calculated times shown in the preview

## PDF Output
The generated PDF matches the official Toastmasters Area C1 agenda format:
- A4 Portrait
- Left sidebar: officers list, mission statement, quote, venue details
- Header: District/Division/Area, meeting number, date, time
- Theme banner
- Full agenda with auto-calculated running times
- Prepared speakers sub-table
- Evaluators sub-table (alternating green rows)
- One ballot row at the bottom, with Featured Speaker, Table Topic Speaker, Evaluator, and Role Takers categories

## Customization
Selecting a club fills the ExCom names from `tin_members.full_name`, joined through
`tin_members_club_role.member_id = tin_members.member_id` and the selected
`club_id`, with `fy_id = 5`. Role codes PREZ, VPE, VPM, VPPR, SECR, TRES, SAA,
and IPP map to the corresponding officer fields. Missing assignments remain blank;
multiple matching names are comma-separated. Names remain editable and are saved
with the agenda. Changing clubs loads that club's officers; unrelated changes retain
manual edits. Reopening an agenda retains saved names and fills any empty officer fields.

Default values (district, mission, quote, venue) are stored in the `app_settings` table.
Update them directly in the database or modify `sql/schema.sql` defaults.
