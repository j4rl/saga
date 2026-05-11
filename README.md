# SAGA

> **Svenskt Arkiv för GymnasieArbeten**
>
> Ett rollstyrt system för inlämning, hantering, arkivering och publicering av gymnasiearbeten.

SAGA hjälper skolor att samla in och hantera gymnasiearbeten på ett kontrollerat sätt. Elever får en tydlig plats för sitt arbete, lärare får stöd i handledning och uppföljning, och färdiga arbeten kan göras sökbara när eleven själv väljer att de får vara publika.

## Innehåll

- [Snabb överblick](#snabb-överblick)
- [Varför SAGA finns](#varför-saga-finns)
- [Vad systemet gör](#vad-systemet-gör)
- [Roller och åtkomst](#roller-och-åtkomst)
- [Arbetsflöde](#arbetsflöde)
- [Säkerhet och integritet](#säkerhet-och-integritet)
- [Nuvarande funktioner](#nuvarande-funktioner)
- [Inför bred produktion](#inför-bred-produktion)
- [Teknisk översikt](#teknisk-översikt)
- [Installation och drift](#installation-och-drift)
- [Begrepp](#begrepp)
- [Relaterad dokumentation](#relaterad-dokumentation)

## Snabb överblick

| Område | Beskrivning |
| --- | --- |
| Syfte | Arkivera, hantera och söka gymnasiearbeten |
| Målgrupper | Elever, lärare, skoladministratörer och superadmin |
| Publicering | Eleven styr själv om ett slutligt inlämnat arbete ska vara publikt |
| Teknik | PHP, MySQL, HTML, CSS och JavaScript |
| Verifiering | `.\tools\verify.ps1` kör PHP-syntaxkontroll och säkerhetstester |
| Säkerhet | Rollbaserad åtkomst, CSRF-skydd, filvalidering, samtycke, auditlogg och inloggningsbegränsning |

## Varför SAGA finns

Gymnasiearbeten innehåller ofta elevens egna resonemang och kan ibland innehålla personliga eller känsliga uppgifter. Ett arkiv för sådana arbeten behöver därför balansera tre behov:

| Behov | Vad det betyder i SAGA |
| --- | --- |
| **Tillgänglighet** | Färdiga publika arbeten ska kunna hittas och användas som inspiration. |
| **Kontroll** | Rätt person ska kunna se, ändra, lämna in, låsa upp eller administrera rätt sak. |
| **Integritet** | Elevens material ska inte visas bredare än nödvändigt och ska inte publiceras utan elevens val. |

SAGA är därför mer än ett uppladdningsformulär. Systemet håller isär publicering, inlämning, handledning, administration och sökning.

## Vad systemet gör

SAGA stödjer hela flödet från registrering till arkivering:

1. Elever och lärare registrerar sig.
2. Skoladministratör eller superadmin godkänner konton innan de används.
3. Skoladministratör kan tilldela väntande elevregistreringar till en lärare på skolan.
4. Eleven skapar, redigerar och lämnar in sitt gymnasiearbete.
5. Inlämnade arbeten låses så att innehållet inte ändras i efterhand utan upplåsning.
6. Handledare följer arbetet och kan ge återkoppling innan slutlig inlämning.
7. Eleven kan läsa återkoppling och ändra arbetet innan det lämnas in slutligt.
8. Eleven väljer själv om ett slutligt inlämnat arbete ska vara publikt och samtycker då till sökbar publicering.
9. Publika arbeten blir sökbara för besökare.
10. Superadmin hanterar skolor, användare, kategorier, driftkontroll och auditlogg.

> Ett arbete kan vara färdigt och inlämnat utan att vara publikt. SAGA skiljer därför tydligt på **inlämning** och **publicering**.

## Roller och åtkomst

| Roll | Kan göra |
| --- | --- |
| **Besökare** | Söka och läsa publika arbeten. |
| **Elev** | Skapa, redigera, lämna in och styra publicering av sitt eget arbete. |
| **Lärare** | Följa handledda arbeten, ge återkoppling och se relevanta inlämnade arbeten på sin skola. |
| **Skoladministratör** | Hantera konton, skolinställningar och elevregistreringar för sin skola. |
| **Superadmin** | Hantera systemövergripande administration, skolor, kategorier, driftkontroll och auditlogg. |

Om en tidigare handledare inte längre arbetar på skolan kan eleven ange handledarens namn manuellt. Godkännandet läggs då på den aktiva lärare som har flest handledda arbeten i samma kategori.

## Arbetsflöde

```text
Registrering
    -> konto godkänns
    -> arbete skapas
    -> handledning och återkoppling
    -> elevens justeringar
    -> slutlig inlämning
    -> eventuell publicering
    -> sökbart arkiv
```

## Säkerhet och integritet

SAGA försöker undvika att samla eller visa mer information än nödvändigt. Loggar finns för spårbarhet, men ska inte bli en onödig kopia av personuppgifter. Därför minimeras exempelvis innehåll i e-postloggar och IP-adresser anonymiseras i auditloggen.

Systemet har skydd för bland annat:

- inloggningsattacker,
- obehörig åtkomst,
- manipulering av formulär,
- felaktig filuppladdning,
- oavsiktlig publicering,
- spårbarhet vid adminändringar.

En mer detaljerad säkerhetsanalys finns i [security.md](security.md).

## Nuvarande funktioner

| Område | Funktioner |
| --- | --- |
| Konton och roller | Rollbaserad behörighet, godkännande av nya konton och tilldelning av elevregistreringar till lärare. |
| Projekt | Återkoppling före slutlig inlämning, elevstyrd publicering och slutlig inlämning med låsning. |
| Filer | PDF-uppladdning, PDF-historik och behörighetskontrollerad PDF-visning. |
| Sökning | Sökning bland publika arbeten och kategoribaserad struktur. |
| Säkerhet | Lösenordsåterställning, inloggningsbegränsning, sanerad bas-URL och säkrare e-postheaders. |
| Integritet | Samtycke vid registrering, separat publiceringssamtycke, dataexport och kontoradering. |
| Drift | Auditlogg, driftkontroll samt gallring av äldre loggar och filversioner. |

## Inför bred produktion

Innan systemet används brett bör drift och förvaltning ta ställning till följande:

- [ ] Skicka e-post via SMTP med TLS i stället för PHP:s enklare `mail()`-funktion.
- [ ] Sätt en fast `APP_BASE_URL` i produktion.
- [ ] Lagra uppladdade filer utanför webbroten om servermiljön tillåter det.
- [ ] Lägg till virusskanning eller PDF-sanering i miljöer där många okända filer laddas upp.
- [ ] Bestäm om publika arbeten ska granskas innan de blir synliga.
- [ ] Granska egna skolteman visuellt med skolans logotyp och faktiska innehåll.
- [ ] Dokumentera backup, gallring och incidentrutiner för den miljö där SAGA körs.

## Teknisk översikt

SAGA är en webbapplikation byggd i PHP med MySQL som databas. Gränssnittet består av HTML, CSS och JavaScript utan tungt frontendramverk.

Den tekniska lösningen är vald för att vara enkel att installera på vanliga webbhotell och lokal XAMPP-liknande miljö, men ändå ha tydliga säkerhetsgränser för roller, filer och sessioner.

| Del | Ansvar |
| --- | --- |
| **PHP** | Inloggning, behörighet, formulär, filer och sidor. |
| **MySQL** | Användare, skolor, projekt, återkoppling, loggar och inställningar. |
| **PDF-filer** | Sparas med slumpade filnamn och hämtas genom behörighetskontroller. |
| **Auditlogg** | Gör viktiga händelser spårbara. |
| **Migreringar** | Uppdaterar databasen kontrollerat när systemet utvecklas. |
| **Verifiering** | Körs med `.\tools\verify.ps1`. |

## Installation och drift

### Lokal installation

1. Lägg projektet i en PHP-kompatibel webbmiljö, till exempel XAMPP.
2. Skapa en MySQL-databas för SAGA.
3. Öppna `index.php` i webbläsaren.
4. Fyll i installerarens databasuppgifter, tabellprefix och första superadmin-konto.
5. När installationen är klar skapas `config/installed.php`.

> `config/installed.php` innehåller lokala databasuppgifter. Den ska inte checkas in och bör skyddas med filrättigheter.

### Uppdateringar

Kör migreringar efter uppdateringar som innehåller nya databasändringar:

```powershell
php tools/migrate.php
```

Kör verifieringen vid ändringar:

```powershell
.\tools\verify.ps1
```

### Bas-URL

För produktion rekommenderas en fast publik adress. Den kan sättas i `config/installed.php`:

```php
define('APP_BASE_URL', 'https://exempel.se/saga');
```

Alternativt kan den sättas via miljövariabeln `SAGA_APP_BASE_URL` eller `APP_BASE_URL`.

Om `APP_BASE_URL` saknas använder SAGA en validerad `Host`-header för återställningslänkar. Det fungerar i lokal utveckling, men en fast bas-URL är tydligare och säkrare bakom proxy, lastbalanserare eller CDN.

### Driftkontroll

Öppna `health.php` för att kontrollera installation, filskydd, uppladdningsmapp och grundläggande produktionskrav.

## Begrepp

| Begrepp | Kort förklaring |
| --- | --- |
| **Frontend** | Den del användaren möter i webbläsaren: sidor, formulär, knappar, färger, responsiv layout och interaktioner. |
| **Backend** | Serverdelen som kontrollerar inloggning, behörighet, projektvisning och filuppladdning. |
| **Databas** | Strukturerad lagring av skolor, användare, projekt, kategorier, inlämningar, återkoppling och loggar. |
| **Rollbaserad åtkomst** | Användarens roll avgör vad personen får se och göra. |
| **Publicering** | Ett arbete blir synligt för andra än de personer som behöver se det för handledning eller administration. |
| **CSRF** | En attack där en extern sida försöker få en redan inloggad användare att skicka en ändring till systemet. |
| **XSS** | Ett försök att få skadlig kod att köras i en annan användares webbläsare. |
| **SQL-injektion** | Ett försök att påverka databasfrågor genom inmatning i exempelvis formulär eller sökfält. |
| **Session** | Serverns sätt att komma ihåg att en användare är inloggad mellan sidvisningar. |
| **Cookie** | En uppgift som webbläsaren sparar för en webbplats, ofta för att koppla webbläsaren till rätt session. |
| **Rate limiting** | Begränsar hur många försök som får göras under en viss tid, till exempel vid inloggning. |
| **Auditlogg** | En spårbar logg över viktiga händelser, till exempel inloggningar och administrativa åtgärder. |
| **Retention** | Hur länge information sparas innan den bör gallras. |
| **Migration** | En kontrollerad förändring av databasen när systemet uppdateras. |
| **SMTP** | Ett standardiserat sätt att skicka e-post via en e-postserver. |
| **TLS** | Kryptering för trafik mellan system, till exempel mellan webbläsaren och SAGA. |
| **CSP** | Content Security Policy, ett webbläsarskydd som begränsar vilka resurser en sida får ladda. |
| **HSTS** | Ett webbläsarskydd som säger åt webbläsaren att fortsätta använda HTTPS för webbplatsen. |

## Relaterad dokumentation

- [security.md](security.md) beskriver säkerhetsanalysen av SAGA.
- [Todo.md](Todo.md) visar åtgärder som har identifierats och bockats av under arbetet.
