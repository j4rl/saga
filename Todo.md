# Todo för SAGA

Senast genomgånget: 2026-05-06.

## Hög prioritet

- [x] Ta bort testkonton från inloggningssidan innan produktion.
  - `login.php` visar inte längre testkonton.

- [x] Byt ut nuvarande inloggningsskydd mot riktig rate limiting.
  - `includes/auth.php` har databasbaserad spärr per användarnamn/IP och per IP.
  - `login_attempts` skapas vid installation och automatiskt vid första inloggningsförsök i befintlig miljö.

- [x] Lägg till globala säkerhetsheaders.
  - `send_security_headers()` skickar CSP, Referrer-Policy, Permissions-Policy, X-Frame-Options, X-Content-Type-Options och HSTS vid HTTPS.

- [x] Flytta uppladdade filer utanför webbroten eller dokumentera tvingande serverregler.
  - `uploads/.htaccess` skyddar Apache, men inte Nginx/IIS eller felkonfigurerad Apache.
  - `download.php` och `school_logo.php` ska fortsatt vara enda vägen till filer.
  - Dokumenterat i `security.md` och kontrolleras i `health.php`.

- [ ] Inför riktig SMTP-lösning för e-post.
  - `includes/functions.php` använder `mail()`, vilket ofta är opålitligt i produktion.
  - Lägg till SMTP-konfiguration, tydligare felhantering och gärna kö/retry för notifieringar.

- [x] Lägg till automatiska tester för behörigheter.
  - `tests/security_checks.php` testar centrala regler för elev/lärare, privata utkast, publika arbeten, redigering och upplåsning.
  - Körs via `tools/verify.ps1`.

## Mellanprioritet

- [x] Skapa en riktig migreringsstrategi för databasen.
  - `tools/migrate.php` kör versionshanterade SQL-filer från `database/migrations/`.
  - `schema_migrations` spårar applicerade migreringar och stödjer tabellprefix via `{{prefix}}`.

- [x] Förbättra sökprestanda för större datamängder.
  - `search_projects()` begränsar kandidatmängden med FULLTEXT/LIKE-villkor innan PHP-poängsättning.
  - Kandidatlistan begränsas till 1000 rader per sökning.

- [x] Lägg till paginering och filter i superadminens användarlista.
  - `dashboard_admin.php` har sök, filter på skola/roll/status och paginering.

- [x] Bygg en vy för auditloggen.
  - `audit.php` visar auditlogg med filter på användare, händelsetyp, datum och entitet.

- [x] Lägg till export för adminloggar och e-postnotiser.
  - `audit.php` exporterar auditlogg och e-postnotiser som CSV.

- [x] Förbättra hantering av tillfälliga lösenord.
  - Adminskapade och adminåterställda lösenord flaggas med `must_change_password`.
  - Användaren tvingas till profilsidan för lösenordsbyte innan fortsatt användning.

- [x] Lägg till lösenordsåterställning.
  - `forgot_password.php` och `reset_password.php` använder engångstoken med utgångstid.
  - Svarstexten är neutral så konton inte kan räknas upp.

- [x] Lägg till serverloggning för viktiga fel.
  - `log_app_error()` loggar tekniska fel server-side utan att visa detaljer för användaren.
  - E-postfel och hälsokontrollfel loggas.

- [x] Se över kategorihantering.
  - `categories.php` låter superadmin byta namn på kategorier och slå ihop dubbletter.

- [x] Lägg till lagringspolicy för PDF-versioner och logotyper.
  - Retention-konstanter finns i `config/app.php`.
  - `tools/cleanup_storage.php` kör torrkörning som standard och kan rensa med `--apply`.

## Lägre prioritet

- [x] Hindra publicering innan slutlig inlämning.
  - Servern sparar aldrig `is_public = 1` när `is_submitted = 0`.
  - Publika sökningar och direktvisning kräver både publik och slutligt inlämnad status.
  - Upplåsning av slutlig inlämning gör arbetet icke-publikt igen.

- [x] Förbättra elevens inlämningsflöde.
  - `project_edit.php` visar checklista inför slutlig inlämning.
  - Servern kräver bekräftelse innan en ny slutlig inlämning sparas.

- [x] Lägg till kommentars- eller återkopplingsflöde mellan lärare och elev.
  - `project_view.php` har återkopplingsflöde för elev, handledare och adminroller.

- [x] Lägg till förhandsvisning av PDF i projektsidan.
  - `project_view.php` bäddar in PDF via `download.php` efter behörighetskontroll.

- [x] Förbättra utskrift/export för lärarlistor.
  - `teacher_project_list.php` är utskriftsvänlig HTML.
  - CSV-export finns via lärarpanelen och `teacher_project_list.php?format=csv`.

- [x] Gör cookie- och integritetstexten mer komplett.
  - `privacy.php` dokumenterar kakor, `localStorage`, personuppgifter och driftrekommendationer.
  - Cookie-bannern och sidfoten länkar till sidan.

- [x] Lägg till installations-/driftchecklista.
  - `health.php` visar driftchecklista och varningar för superadmin.
  - `dashboard_admin.php` länkar till driftkontrollen och visar varning vid avvikelser.

- [x] Lägg till enkel hälsokontroll.
  - Kontrollerar databas, `mysqli`, uppladdningsmapp, uppladdningsskydd, installeringslås, `config/`-skrivbarhet och HTTPS.

- [x] Gå igenom tillgänglighet.
  - Sidhuvudet har skip-link till huvudinnehåll.
  - Fokusmarkeringar har förstärkts och ikonbaserad utloggning är en riktig knapp med CSRF-formulär.

## Teknisk verifiering

- [x] PHP-syntaxkontroll körd med `C:\xampp\php\php.exe -l` på alla PHP-filer.
- [x] Lägg till ett repeterbart testkommando eller script så detta kan köras utan manuell PowerShell-loop.
  - Kör `.\tools\verify.ps1`.
