# SAGA

SAGA, Svenskt Arkiv för GymnasieArbeten, är en enkel MVP för att lagra, söka och hantera gymnasiearbeten med PDF-uppladdning.

## Systemarkitektur

Applikationen är byggd utan ramverk:

- Frontend: HTML, CSS och vanilla JavaScript.
- Backend: PHP med sessionsbaserad inloggning.
- Databas: MySQL.
- Databasåtkomst: MySQLi med prepared statements.
- PDF-filer sparas i `uploads/` med slumpade filnamn och hämtas genom `download.php`, där behörighet kontrolleras innan filen skickas.

Rollerna är:

- Oinloggad besökare: kan söka och visa publika arbeten.
- Elev: kan skapa, redigera och lämna in sitt eget arbete samt söka publika arbeten.
- Lärare: kan se alla arbeten på sin egen skola, även icke-publika.
- Admin: kan skapa skolor och användare samt se alla arbeten via sökningen.

## Databasschema

Databasen finns i `database/schema.sql`.

- `schools`: skolor.
- `users`: användare med `password_hash`, roll och skolkoppling.
- `projects`: metadata, synlighet, inlämningsstatus och aktuell PDF.
- `upload_versions`: historik för uppladdade PDF-versioner.
- `audit_log`: enkel logg för viktiga händelser.

Sökningen använder SQL `LIKE` mot `title`, `subtitle`, `abstract_text` och `summary_text`. Den är samlad i `includes/projects.php` för att senare kunna bytas till fulltextsökning.

## Filstruktur

```text
config/
  app.php                  Konstanter för app, databas och uppladdning
  database.php             MySQLi-anslutning
includes/
  bootstrap.php            Startar session, databas och gemensamma inkluderingsfiler
  functions.php            CSRF, escaping, flash, DB-hjälpare och rollkrav
  auth.php                 Inloggning och utloggning
  projects.php             Sökanrop, behörighet och PDF-validering
  project_form_handler.php Sparar elevens projekt och PDF
  header.php               Gemensam sidhuvud/navigation
  footer.php               Gemensam sidfot
database/
  schema.sql               Databas, tabeller och testdata
uploads/
  .htaccess                Blockerar direktatkomst i Apache
assets/
  css/style.css            Responsiv layout
  js/app.js                Formulärhjälp för PDF och teckenräknare
index.php                  Startsida med sök och senaste publika arbeten
search.php                 Sökresultat med skolfilter och paginering
login.php                  Inloggning
logout.php                 Utloggning
dashboard_student.php      Elevpanel
dashboard_teacher.php      Lärarpanel
dashboard_admin.php        Enkel adminpanel
project_view.php           Metadata och PDF-länkar
project_edit.php           Elevens uppladdning/redigering
upload_project.php         Vidarebefordrar till redigeringsformuläret
download.php               Säker PDF-servering
```

## Installation med XAMPP

1. Placera projektmappen i XAMPP:s webbrot, till exempel `C:\xampp\htdocs\saga`.
2. Starta Apache och MySQL i XAMPP Control Panel.
3. Öppna phpMyAdmin och importera `database/schema.sql`.
4. Kontrollera att PHP-tillägget `mysqli` är aktiverat i XAMPP:s `php.ini`.
5. Kontrollera databasinställningarna i `config/app.php`.
   Standard är:

```php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'saga_gymnasiearbeten');
```

6. Se till att PHP/Apache kan skriva i `uploads/`.
7. Öppna `http://localhost/saga/`.

## Testkonton

`database/schema.sql` skapar följande testkonton:

| Roll | Användarnamn | Lösenord |
| ---- | ------------ | -------- |
| Admin | `admin` | `admin123` |
| Elev | `elev` | `elev123` |
| Lärare | `larare` | `larare123` |

Byt lösenord innan systemet används i en riktig miljö.

## Säkerhet i MVP:n

- Lösenord lagras med `password_hash()` och verifieras med `password_verify()`.
- Alla SQL-frågor använder MySQLi prepared statements.
- Output escape:as med `htmlspecialchars()`.
- Alla skrivande formulär skyddas med CSRF-token.
- PDF-uppladdning kontrollerar filstorlek, filändelse, MIME-typ och PDF-signatur.
- Uppladdade filer får slumpade filnamn och direktatkomst blockeras med `.htaccess`.
- `download.php` kontrollerar roll, ägarskap, skola och publik status innan PDF skickas.

## Vidareutveckling

Rimliga nästa steg är att lägga till lösenordsbyte, mer komplett adminhantering, fulltextsökning, e-postnotiser och versionsvisning för PDF-historik.


