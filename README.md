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
- Elev: kan registrera sig, logga in efter godkännande, skapa, redigera och lämna in sitt eget arbete med kategori och handledare från skolans registrerade lärare samt söka publika arbeten.
- Lärare: kan registrera sig, logga in efter godkännande, se sina handledda arbeten direkt, se alla arbeten där de är handledare, filtrera fram inlämnade arbeten på den egna skolan och skriva ut en elev-/rubriklista.
- Skoladministratör: godkänner eller avvisar elev- och lärarregistreringar på sin egen skola samt ställer in skolans färger och logotyp.
- Superadmin: kan skapa skolor med minst en skoladministratör, skapa användare direkt som godkända och hantera registreringar för alla skolor.

## Databasschema

Databasen finns i `database/schema.sql`.

- `schools`: skolor.
- `schools`: innehåller även skolans eventuella egna temafärger och logotyp.
- `categories`: kategorier för gymnasiearbeten.
- `users`: användare med `password_hash`, roll och skolkoppling.
- `users.approval_status`: styr om ett konto väntar, är godkänt eller avvisat.
- `projects`: metadata, kategori, handledarkoppling, synlighet, inlämningsstatus och aktuell PDF.
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
dashboard_school_admin.php Skoladministratörens godkännandepanel
dashboard_admin.php        Superadminpanel
register.php               Självregistrering för elever och lärare
teacher_project_list.php   Utskriftsvänlig lärarlista
project_view.php           Metadata och PDF-länkar
project_edit.php           Elevens uppladdning/redigering
upload_project.php         Vidarebefordrar till redigeringsformuläret
download.php               Säker PDF-servering
school_logo.php            Säker servering av skolans logotyp
```

## Installation med XAMPP

1. Placera projektmappen i XAMPP:s webbrot, till exempel `C:\xampp\htdocs\saga`.
2. Starta Apache och MySQL i XAMPP Control Panel.
3. Öppna phpMyAdmin och importera `database/schema.sql`.
   Alternativt kan schemat importeras från PowerShell utan att å/ä/ö förvanskas:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' --default-character-set=utf8mb4 -u root --execute="SOURCE C:/xampp/htdocs/saga/database/schema.sql"
```

   Databasen och tabellerna använder `utf8mb4_swedish_ci`.
4. Kontrollera att PHP-tillägget `mysqli` är aktiverat i XAMPP:s `php.ini`.
5. Kontrollera databasinställningarna i `config/app.php`.
   Standard är:

```php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'saga');
```

6. Se till att PHP/Apache kan skriva i `uploads/`.
7. Öppna `http://localhost/saga/`.

## Testkonton

`database/schema.sql` skapar följande testkonton:

| Roll | Användarnamn | Lösenord |
| ---- | ------------ | -------- |
| Superadmin | `admin` | `admin123` |
| Skoladministratör | `skoladmin` | `skoladmin123` |
| Skoladministratör | `skoladmin_sodra` | `skoladmin123` |
| Elev | `elev` | `elev123` |
| Lärare | `larare` | `larare123` |

Byt lösenord innan systemet används i en riktig miljö.

## Säkerhet i MVP:n

- Lösenord lagras med `password_hash()` och verifieras med `password_verify()`.
- Alla SQL-frågor använder MySQLi prepared statements.
- Output escape:as med `htmlspecialchars()`.
- Alla skrivande formulär skyddas med CSRF-token.
- Självregistrerade konton får inte logga in förrän de har godkänts av skoladministratör eller superadmin.
- Slutgiltigt inlämnade arbeten låses för fortsatt elevredigering och får automatiskt inlämningsdatum.
- PDF-uppladdning kontrollerar filstorlek, filändelse, MIME-typ och PDF-signatur.
- Uppladdade filer får slumpade filnamn och direktatkomst blockeras med `.htaccess`.
- `download.php` kontrollerar roll, ägarskap, skola och publik status innan PDF skickas.

## Vidareutveckling

Rimliga nästa steg är att lägga till lösenordsbyte, mer komplett adminhantering, fulltextsökning, e-postnotiser och versionsvisning för PDF-historik.


