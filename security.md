# Säkerhetsanalys av SAGA

Analysdatum: 2026-05-11  
System: SAGA, Svenskt Arkiv för GymnasieArbeten

## Sammanfattning

SAGA hanterar elevkonton, lärarkonton, skoladministration, gymnasiearbeten, PDF-uppladdningar och sökning. Säkerhetsanalysen har därför fokuserat på personlig integritet, behörighetsgränser mellan roller, skydd av uppladdade filer, inloggning, sessionssäkerhet, loggning och drift.

Den viktigaste principen är att elevens material inte ska exponeras bredare än nödvändigt. Systemet skiljer därför på privata utkast, slutgiltigt inlämnade arbeten och publika arbeten. Lärare, skoladministratörer och superadmin har olika behörigheter, och publicering ska styras av eleven själv.

## Granskade säkerhetsaspekter

Följande områden har granskats:

- Autentisering och lösenord
- Sessionshantering
- Roll- och behörighetskontroll
- Elevintegritet och publicering
- Filuppladdning och filnedladdning
- CSRF-skydd och formulärsäkerhet
- SQL-injektion och databasanrop
- XSS och säker output
- Browser-säkerhetsheaders
- Auditloggning och personuppgiftsminimering
- E-postnotiser och lösenordsåterställning
- Host-header och externa länkar
- Driftkontroller, migrationer och retention
- Tillgänglighet kopplad till säker användning

## Autentisering och lösenord

**Risker som granskats**

- Lösenordsattacker mot inloggningen.
- Exponerade testkonton.
- Tillfälliga lösenord som inte byts.
- Återställningsflöde som kan användas för att räkna upp konton.

**Lösning i SAGA**

- Lösenord lagras med `password_hash()` och verifieras med `password_verify()`.
- Testkonton visas inte längre på inloggningssidan.
- Inloggningen har databasbaserad rate limiting i `login_attempts`, med spärr per användarnamn/IP och per IP.
- Adminskapade och adminåterställda lösenord markeras med `must_change_password`.
- Användare med tillfälligt lösenord tvingas till profilsidan och måste byta lösenord innan fortsatt användning.
- Lösenordsåterställning sker via engångstoken i `password_resets`.
- Återställningssidan svarar neutralt: den avslöjar inte om ett konto eller en e-postadress finns.
- Återställningstoken lagras som hash och har utgångstid.
- Begäran om lösenordsåterställning begränsas i `password_reset_attempts`, både per identifierare/IP och per IP.
- Återställningslänkar kan använda fast `APP_BASE_URL`. Om den saknas används en validerad `Host`-header.

**Kvarstående risk**

SAGA har tekniska spärrar mot massutskick av återställningsmail, men e-postleveransen bör ändå flyttas till SMTP med tydlig leveransloggning och felhantering inför bred produktion.

## Sessionshantering

**Risker som granskats**

- Sessionsfixering.
- För långa sessioner på delade datorer.
- Konton som ändrats eller avvisats men fortfarande har aktiv session.

**Lösning i SAGA**

- Session-ID regenereras vid inloggning.
- Sessionskakan sätts med `HttpOnly`, `SameSite=Lax` och `Secure` när HTTPS används.
- Sessioner får inaktivitetstimeout via `SESSION_IDLE_TIMEOUT_SECONDS`.
- Aktiv session synkas mot databasen på varje request.
- Om kontot inte längre finns eller inte är godkänt avslutas sessionen.
- Utloggning kräver POST och CSRF-token, inte GET-länk.

## Roll- och behörighetskontroll

**Risker som granskats**

- Lärare som kan se elevers utkast utan att vara handledare.
- Lärare som kan låsa upp inlämningar de inte ansvarar för.
- Namnbaserad handledarmatchning mellan skolor.
- Admin- eller lärarroll som kan publicera elevens arbete utan elevens val.

**Lösning i SAGA**

- Behörigheten centraliseras i funktioner som `can_view_project()`, `can_edit_project_content()`, `can_unlock_project_submission()` och `teacher_is_project_supervisor()`.
- Lärare får se:
  - publika arbeten,
  - egna handledda arbeten,
  - slutgiltigt inlämnade arbeten på den egna skolan.
- Lärare får inte se andra lärares icke-inlämnade elevutkast.
- Lärare får bara låsa upp slutlig inlämning om de är handledare för arbetet.
- Om tidigare handledare inte längre finns som aktiv användare kan eleven ange handledarnamn manuellt.
- Sådana inlämnade arbeten kan godkännas av den aktiva lärare på samma skola som har flest handledda arbeten i samma kategori. Vid lika antal väljer systemet deterministiskt en lärare.
- Namnbaserad fallback för handledare gäller bara inom lärarens egen skola.
- Skoladministratör är begränsad till den egna skolan.
- Skoladministratör kan godkänna väntande lärare och elever på den egna skolan.
- Skoladministratör kan tilldela väntande elevregistreringar till en godkänd lärare på samma skola.
- Lärare kan bara godkänna eller avvisa elevkonton som uttryckligen har tilldelats till dem.
- Superadmin har systemövergripande behörighet.

## Elevintegritet och publicering

**Risker som granskats**

- Att elevens arbete blir publikt utan elevens avsikt.
- Att elevutkast blir sökbara för fler än nödvändigt.
- Att en elev laddar upp känsligt eller olämpligt innehåll.

**Lösning i SAGA**

- Endast eleven som äger arbetet kan ändra `is_public`.
- Ett arbete kan bara behandlas som publikt om `is_public = 1`, `is_submitted = 1` och `is_approved = 1`.
- Lärare, skoladmin och superadmin kan inte göra ett elevägt arbete publikt via redigeringsflödet.
- Icke-publika utkast visas inte för obehöriga roller.
- Slutlig inlämning kräver explicit bekräftelse.
- Om en inlämning låses upp sätts publicering samtidigt till icke-publik.
- Inlämningsflödet visar checklista innan arbetet låses.
- Systemet teknikkontrollerar PDF-filen, men gör inte innehållsmoderering.

**Kvarstående risk**

En elev kan fortfarande själv göra ett arbete publikt utan separat granskningsflöde. För starkare publiceringskontroll bör SAGA införa en status som `publication_status = requested|approved|rejected`, där handledare eller skoladmin godkänner publicering.

## Filuppladdning och filnedladdning

**Risker som granskats**

- Direktåtkomst till filer i `uploads/`.
- Uppladdning av fel filtyp.
- Filnamnsmanipulation och path traversal.
- Nedladdning utan behörighet.

**Lösning i SAGA**

- PDF-uppladdning kontrollerar:
  - filstorlek,
  - filändelse,
  - MIME-typ,
  - PDF-signatur.
- Uppladdade PDF-filer får slumpade filnamn.
- Originalfilnamn används inte som lagringsnamn.
- `download.php` kontrollerar behörighet innan PDF skickas.
- `school_logo.php` serverar logotyper via kontrollerad PHP-kod.
- Direktåtkomst till `uploads/` blockeras med `.htaccess` för Apache.
- Driftkontrollen i `health.php` varnar om uppladdningar ligger i webbroten utan Apache-regel.

**Kvarstående risk**

`.htaccess` skyddar bara Apache. Vid Nginx/IIS eller felkonfigurerad Apache måste motsvarande serverregel finnas. Det säkraste är att flytta `UPLOAD_DIR` utanför webbroten.

## CSRF och formulärsäkerhet

**Risker som granskats**

- Att externa webbplatser kan trigga skrivande åtgärder.
- Att utloggning eller ändringar sker via enkel länk.

**Lösning i SAGA**

- Alla skrivande formulär använder CSRF-token.
- `verify_csrf()` används på POST-flöden.
- Utloggning sker via POST-formulär med CSRF-token.
- Viktiga adminåtgärder loggas i auditloggen.

## SQL-injektion och databasanrop

**Risker som granskats**

- SQL-injektion via formulär, filter eller sökningar.
- Felaktig hantering av tabellprefix.

**Lösning i SAGA**

- Databasanrop använder MySQLi prepared statements via gemensamma hjälpfunktioner.
- Tabellprefix appliceras centralt av `prefix_sql_tables()`.
- Dynamiska filter byggs med parameterbindning.
- Sorteringsvärden och roller valideras mot allowlists.

## XSS och output

**Risker som granskats**

- Att användarinmatad text renderas som HTML.
- Att filnamn, titlar, kommentarer eller namn injicerar script.

**Lösning i SAGA**

- Output escape:as med `h()`, baserat på `htmlspecialchars()`.
- Längre text som abstract, sammanfattning och kommentarer escape:as innan `nl2br()`.
- Filnamn i `Content-Disposition` saneras.
- Kategorier, namn, titlar och feedback renderas escape:ade.

## Browser-säkerhetsheaders

**Risker som granskats**

- Clickjacking.
- MIME-sniffning.
- Referrer-läckage.
- Onödiga browser-API:er.
- Grundläggande XSS-skadebegränsning.

**Lösning i SAGA**

`send_security_headers()` skickar:

- `Content-Security-Policy`
- `X-Frame-Options`
- `X-Content-Type-Options`
- `Referrer-Policy`
- `Permissions-Policy`
- `Strict-Transport-Security` vid HTTPS

För vanlig HTML blockeras inbäddning med `frame-ancestors 'none'`. `download.php` tillåter inbäddning från samma origin så PDF-förhandsvisningen på projektsidan fungerar.

**Kvarstående risk**

CSP tillåter fortfarande `'unsafe-inline'` för script/style på grund av befintliga inline-script och dynamisk CSS. Nästa steg är nonce-baserad CSP eller att flytta inline-kod till externa filer.

## Auditloggning och personuppgifter

**Risker som granskats**

- För lite spårbarhet vid adminåtgärder.
- För mycket persondata i loggar.
- Full IP-adress i auditlogg.

**Lösning i SAGA**

- Viktiga händelser loggas i `audit_log`.
- `audit.php` ger superadmin filter på användare, åtgärd, entitet och datum.
- Auditlogg kan exporteras som CSV.
- IP-adresser anonymiseras innan de sparas.
- E-postnotiser loggas utan brödtext för att minska persondatamängden.
- `tools/cleanup_storage.php` stödjer gallring av auditloggar, e-postnotiser och gamla PDF-versioner.

## E-post och notifieringar

**Risker som granskats**

- Onödig persondata i notifieringsloggar.
- Opålitlig e-postleverans.
- Återställningslänkar som exponeras eller återanvänds.

**Lösning i SAGA**

- Notisloggar sparar mottagare, ämne, status och fel, men inte brödtext.
- Lösenordsåterställning använder engångstoken med hashad lagring och utgångstid.
- Återställda lösenord rensar kravet på tillfälligt lösenordsbyte.
- E-postämnen och avsändardomän saneras innan `mail()` används.

**Kvarstående risk**

SAGA använder fortfarande PHP `mail()`. För produktion bör SMTP med TLS, autentisering och kontrollerad avsändare införas.

## Host-header och externa länkar

**Risker som granskats**

- Återställningslänkar som får fel domän om servern accepterar manipulerad `Host`-header.
- Header injection via rå host eller ämnesrad i e-post.

**Lösning i SAGA**

- `app_base_url()` använder `APP_BASE_URL` när den är definierad.
- Installeraren kan skriva `APP_BASE_URL` till `config/installed.php`.
- `config/app.php` kan läsa `SAGA_APP_BASE_URL` eller `APP_BASE_URL` från miljön.
- Om `APP_BASE_URL` saknas valideras requestens host strikt innan den används.
- Ogiltig eller radbruten host faller tillbaka till `localhost`.
- E-postavsändarens domän hämtas från `APP_BASE_URL` eller sanerad host.
- `health.php` varnar om `APP_BASE_URL` saknas.

**Kvarstående risk**

I produktion bör `APP_BASE_URL` alltid sättas explicit. Fallback till sanerad host är främst avsedd för lokal utveckling och enkla installationer.

## Migrationer och drift

**Risker som granskats**

- Databasstruktur som glider isär mellan installation och uppgradering.
- Produktionsmiljö med felaktiga filrättigheter.
- Avsaknad av kontroll för uppladdningsskydd och HTTPS.

**Lösning i SAGA**

- `tools/migrate.php` kör versionshanterade SQL-migreringar från `database/migrations/`.
- `schema_migrations` spårar applicerade migreringar.
- Migreringar stödjer tabellprefix via `{{prefix}}`.
- Nya installationer och migreringar skapar även `password_reset_attempts` för spärr av återställningsbegäran.
- `health.php` visar driftkontroller för superadmin:
  - databas,
  - PHP `mysqli`,
  - uppladdningsmapp,
  - direktåtkomstskydd,
  - installeringslås,
  - skrivbarhet i `config/`,
  - HTTPS.
- `tools/verify.ps1` kör syntaxkontroll och säkerhetstester.
- Driftkontrollen varnar om `APP_BASE_URL` saknas.

## Tillgänglighet som säkerhetsaspekt

**Risker som granskats**

- Att användare missar viktig information eller trycker fel på säkerhetskritiska kontroller.
- Dålig tangentbordsnavigering.
- Otydliga fokusmarkeringar.

**Lösning i SAGA**

- Sidhuvudet har skip-link till huvudinnehåll.
- Fokusmarkeringar är förstärkta.
- Ikonbaserad utloggning är en riktig knapp i ett POST-formulär.
- Formulär använder label-element och tydliga felmeddelanden.
- Skolans anpassade tema räknas fram från två valda färger.
- Beräknade ljusa och mörka paletter valideras med minst 4.5:1 kontrast för text och länkar.
- Svåra färgval justeras till läsbara text- och länkfärger i stället för att skoladmin behöver välja varje färg manuellt.
- Uppladdad skollogotyp visas i header och förhandsvisning, men begränsas till fasta ytor med `object-fit: contain`.

## Kvarstående rekommendationer

Följande förbättringar bör prioriteras innan bred produktion:

- Inför SMTP med TLS och autentisering i stället för PHP `mail()`.
- Sätt ett produktionsvärde för `APP_BASE_URL` via installeraren, `config/installed.php` eller miljövariabel.
- Flytta `UPLOAD_DIR` utanför webbroten om servermiljön tillåter det.
- Lägg till virusskanning eller PDF-sanitization för uppladdade filer.
- Inför granskningsflöde innan arbeten blir publika.
- Ersätt inline-script/style med nonce-baserad CSP.
- Överväg fyra-ögon-princip för särskilt känsliga adminåtgärder, till exempel rollbyte till superadmin.

## Slutsats

SAGA har skydd för de viktigaste riskerna i en skolmiljö: rollbaserad åtkomst, elevens kontroll över publicering, teknisk filvalidering, CSRF-skydd, serverbaserad inloggningsbegränsning, sessions-timeout, auditloggning och personuppgiftsminimering.

Produktion kräver fortfarande driftbeslut kring SMTP, serverregler för uppladdningar, backup, gallring och eventuell manuell granskning av publika arbeten.
