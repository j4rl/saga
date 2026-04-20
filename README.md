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
- Elev: kan registrera sig, logga in efter godkännande, byta lösenord, skapa, redigera och lämna in sitt eget arbete med kategori och handledare från skolans registrerade lärare samt söka publika arbeten.
- Lärare: kan registrera sig, logga in efter godkännande, byta lösenord, se sina handledda arbeten direkt, se alla arbeten där de är handledare, filtrera fram inlämnade arbeten på den egna skolan och skriva ut en elev-/rubriklista.
- Skoladministratör: godkänner eller avvisar elev- och lärarregistreringar på sin egen skola samt ställer in skolans färger och logotyp.
- Superadmin: kan skapa och uppdatera skolor, skapa och uppdatera användare, sätta tillfälliga lösenord, hantera registreringar för alla skolor och se e-postnotislogg.

Ljus, auto och mörkt läge väljs av användaren i sidhuvudet och standardläget är auto.
Användaren behöver godkänna nödvändiga kakor. När det är gjort sparas godkännandet i en cookie och frågan visas inte igen.

## Databasschema

Databasen finns i `database/schema.sql`.

- `schools`: skolor.
- `schools`: innehåller även skolans eventuella egna temafärger och logotyp.
- `categories`: kategorier för gymnasiearbeten.
- `users`: användare med `password_hash`, roll och skolkoppling.
- `users.email`: valfri e-postadress för notifieringar.
- `users.approval_status`: styr om ett konto väntar, är godkänt eller avvisat.
- `projects`: metadata, kategori, handledarkoppling, synlighet, inlämningsstatus och aktuell PDF.
- `upload_versions`: historik för uppladdade PDF-versioner.
- `email_notifications`: logg över skickade eller misslyckade e-postnotiser.
- `audit_log`: enkel logg för viktiga händelser.

Sökningen använder MySQL FULLTEXT mot projektets titel, underrubrik, handledare, abstract och sammanfattning, kombinerat med smart synonym- och ungefärlig poängsättning i `includes/projects.php`.

## Filstruktur

```text
config/
  app.php                  Konstanter för app, databas och uppladdning
  database.php             MySQLi-anslutning
includes/
  bootstrap.php            Startar session, databas och gemensamma inkluderingsfiler
  functions.php            CSRF, escaping, flash, DB-hjälpare och rollkrav
  installer.php            Första-körningsinstallation från index.php
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
change_password.php        Lösenordsbyte för inloggade användare
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

## Installation på webbserver

1. Ladda upp filerna till webbservern.
2. Se till att PHP har `mysqli` aktiverat.
3. Se till att PHP kan skriva till `config/` under installationen och till `uploads/` vid drift.
4. Öppna webbplatsens `index.php` i webbläsaren.
5. Om `config/installed.php` saknas visas installationsformuläret automatiskt.
6. Ange databasserver, databasnamn, databasanvändare, tabellprefix och första superadmin-konto.
   Standardprefix för tabeller är `saga_`.
7. När installationen är klar skapas `config/installed.php`. Så länge filen finns kan installationen inte köras igen.

`config/installed.php` är miljöspecifik och ska inte commitas eller följa med till en nyinstallation.

## Manuell import för utveckling

`database/schema.sql` kan fortfarande importeras manuellt i en lokal utvecklingsmiljö. Från PowerShell kan den importeras utan att å/ä/ö förvanskas med:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' --default-character-set=utf8mb4 -u root --execute="SOURCE C:/xampp/htdocs/saga/database/schema.sql"
```

Databasen och tabellerna använder `utf8mb4_swedish_ci`.

## Testkonton

Den manuella utvecklingsimporten i `database/schema.sql` skapar följande testkonton:

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
- Inloggade användare kan byta lösenord med kontroll av nuvarande lösenord.
- PDF-uppladdning kontrollerar filstorlek, filändelse, MIME-typ och PDF-signatur.
- Uppladdade filer får slumpade filnamn och direktatkomst blockeras med `.htaccess`.
- `download.php` kontrollerar roll, ägarskap, skola och publik status innan PDF skickas.

## Vidareutveckling

Rimliga nästa steg är att lägga till rate limiting för inloggning, hårdare säkerhetsheaders, e-post via riktig SMTP-tjänst och mer avancerad rapportering.


