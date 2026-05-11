# SAGA

SAGA står för **Svenskt Arkiv för GymnasieArbeten**. Systemet är byggt för att skolor ska kunna samla in, hantera, söka och visa gymnasiearbeten på ett kontrollerat sätt.

Syftet är att ge elever en tydlig plats för sitt arbete, ge lärare stöd i handledning och uppföljning, och samtidigt göra färdiga arbeten sökbara när eleven själv har valt att de får vara publika.

## Varför SAGA finns

Gymnasiearbeten innehåller ofta både elevens egna resonemang och ibland personliga eller känsliga uppgifter. Ett arkiv för sådana arbeten behöver därför balansera tre saker:

- **Tillgänglighet:** färdiga arbeten ska kunna hittas och användas som inspiration.
- **Kontroll:** rätt person ska kunna se, ändra, lämna in, låsa upp eller administrera rätt sak.
- **Integritet:** elevens material ska inte visas bredare än nödvändigt och ska inte publiceras utan elevens val.

SAGA är därför inte bara ett uppladdningsformulär. Det är ett rollstyrt system där publicering, inlämning, handledning, administration och sökning hålls isär.

## Vad systemet gör

SAGA stödjer hela flödet från registrering till arkivering:

- Elever och lärare kan registrera sig.
- Skoladministratör eller superadmin godkänner konton innan de används.
- Elever kan skapa, redigera och lämna in gymnasiearbeten.
- Inlämnade arbeten låses så att innehållet inte ändras i efterhand utan upplåsning.
- Handledare kan följa och ge återkoppling på sina elevers arbeten.
- Lärare kan se relevanta arbeten inom sin skola.
- Eleven styr själv om ett slutligt inlämnat arbete ska vara publikt.
- Publika arbeten kan sökas av besökare.
- Superadmin kan hantera skolor, användare, kategorier, driftkontroll och auditlogg.

## Viktiga principer

### Eleven äger publiceringsvalet

Ett arbete kan vara färdigt och inlämnat utan att vara publikt. SAGA skiljer därför på **inlämning** och **publicering**.

Det är viktigt eftersom en elev kan behöva lämna in ett arbete för bedömning utan att samtidigt vilja göra det synligt för alla. Ett arbete kan inte vara publikt förrän slutlig inlämning är ikryssad. Lärare kan låsa upp handledda arbeten vid behov, men de kan inte göra elevens arbete publikt åt eleven.

### Roller begränsar åtkomsten

Alla användare ska inte kunna se allt. SAGA använder roller för att hålla ansvar och åtkomst separerade:

- **Besökare** ser endast publika arbeten.
- **Elev** hanterar sitt eget arbete.
- **Lärare** ser egna handledda arbeten och relevanta inlämnade arbeten på sin skola.
- **Skoladministratör** hanterar konton och skolinställningar för sin skola.
- **Superadmin** hanterar systemövergripande administration.

Detta minskar risken för att elevutkast, interna kommentarer eller skoladministration visas för fel person.

### Integritet går före bekvämlighet

SAGA försöker undvika att samla eller visa mer information än nödvändigt. Loggar finns för spårbarhet, men de ska inte bli en onödig kopia av personuppgifter. Därför minimeras exempelvis innehåll i e-postloggar och IP-adresser anonymiseras i auditloggen.

### Säkerhet är en del av produkten

Eftersom SAGA hanterar elever, skolor och uppladdade dokument behöver säkerhet finnas i grundflödena. Systemet har därför skydd för bland annat:

- inloggningsattacker,
- obehörig åtkomst,
- manipulering av formulär,
- felaktig filuppladdning,
- oavsiktlig publicering,
- spårbarhet vid adminändringar.

En mer detaljerad säkerhetsanalys finns i [security.md](security.md).

## Vad som ingår i nuvarande version

SAGA började som en **MVP**, en första användbar version med kärnfunktioner. Den nuvarande lösningen har därefter förstärkts med flera funktioner som behövs för praktisk användning i skolmiljö:

- rollbaserad behörighet,
- godkännande av nya konton,
- elevstyrd publicering,
- slutlig inlämning med låsning,
- återkoppling på projektsidan,
- PDF-uppladdning och PDF-historik,
- behörighetskontrollerad PDF-visning,
- sökning och kategorier,
- lösenordsåterställning,
- inloggningsbegränsning,
- sanerad bas-URL för återställningslänkar och e-postheaders,
- auditlogg,
- driftkontroll,
- gallring av äldre loggar och filversioner.

## Vad som återstår inför bred produktion

SAGA är starkare än en enkel MVP, men vissa beslut hör hemma i drift och förvaltning innan systemet används brett:

- E-post bör skickas via SMTP med TLS i stället för PHP:s enklare `mail()`-funktion.
- Produktion bör sätta en fast `APP_BASE_URL`, så lösenordsåterställning inte behöver bygga länkar från aktuell request.
- Uppladdade filer bör helst lagras utanför webbroten om servermiljön tillåter det.
- PDF-filer bör virusskannas eller saneras i miljöer där många okända filer laddas upp.
- Skolan bör ta ställning till om publika arbeten ska granskas innan de blir synliga.
- Egna skolteman kontrastkontrolleras, men bör fortfarande granskas visuellt med skolans logotyp och faktiska innehåll.
- Backup, gallring och incidentrutiner bör dokumenteras för den miljö där SAGA körs.

## Teknisk översikt

SAGA är en webbapplikation byggd i PHP med MySQL som databas. Gränssnittet består av HTML, CSS och JavaScript utan tungt frontendramverk.

Den tekniska lösningen är vald för att vara enkel att installera på vanliga webbhotell och lokal XAMPP-liknande miljö, men ändå ha tydliga säkerhetsgränser för roller, filer och sessioner.

Viktiga delar:

- **PHP** hanterar inloggning, behörighet, formulär, filer och sidor.
- **MySQL** lagrar användare, skolor, projekt, återkoppling, loggar och inställningar.
- **PDF-filer** sparas med slumpade filnamn och hämtas genom behörighetskontroller.
- **Auditlogg** används för att kunna följa viktiga händelser.
- **Migreringar** används för att kunna uppdatera databasen när systemet utvecklas.
- **Verifiering** kan köras med `.\tools\verify.ps1`, som gör PHP-syntaxkontroll och kör säkerhetstesterna.

## Driftkonfiguration

Efter installation skapas `config/installed.php` lokalt med databasuppgifter. Den filen ska inte checkas in och bör skyddas med filrättigheter.

För produktion rekommenderas även att definiera:

```php
define('APP_BASE_URL', 'https://exempel.se/saga');
```

Om `APP_BASE_URL` saknas använder SAGA en validerad `Host`-header för återställningslänkar. Det fungerar i lokal utveckling, men en fast bas-URL är tydligare och säkrare bakom proxy, lastbalanserare eller CDN.

## Begrepp

### MVP

**MVP** betyder *Minimum Viable Product*. Det är den minsta version av en produkt som är tillräckligt användbar för att testas i verkligheten.

I SAGA betyder MVP inte att kvalitet eller säkerhet saknas. Det betyder att systemet först byggdes runt det viktigaste behovet: att elever ska kunna lämna in och skolor kunna arkivera gymnasiearbeten. Därefter kan systemet byggas ut med mer administration, bättre driftstöd och fler skydd.

### Frontend

**Frontend** är den del användaren möter i webbläsaren: sidor, formulär, knappar, färger, responsiv layout och interaktioner.

I SAGA är frontend viktigt eftersom elever och lärare måste förstå när ett arbete är utkast, inlämnat eller publikt. Otydliga gränssnitt kan leda till felaktiga beslut, särskilt vid publicering.

### Backend

**Backend** är serverdelen bakom gränssnittet. Den kontrollerar exempelvis vem som är inloggad, vilka rättigheter personen har, vilka projekt som får visas och hur filer får laddas upp.

I SAGA är backend särskilt viktig eftersom säkerhet inte kan bygga på vad som bara syns eller döljs i webbläsaren. Servern måste alltid kontrollera behörigheten.

### Databas

En **databas** är strukturerad lagring av information. SAGA använder databasen för skolor, användare, projekt, kategorier, inlämningar, återkoppling och loggar.

Databasen behövs för att systemet ska kunna hålla ordning på vem som äger vilket arbete, vilken skola användaren tillhör och vilka åtgärder som har gjorts.

### Rollbaserad åtkomst

**Rollbaserad åtkomst** betyder att användarens roll avgör vad personen får göra. En elev, lärare, skoladministratör och superadmin har olika ansvar och därför olika rättigheter.

Det är centralt i SAGA eftersom skolmiljön innehåller flera nivåer av ansvar. En lärare behöver kunna handleda, men ska inte automatiskt kunna administrera hela systemet eller publicera elevens arbete.

### Publicering

**Publicering** betyder att ett arbete blir synligt för andra än de personer som behöver se det för handledning eller administration.

I SAGA kräver publicering att arbetet först är slutligt inlämnat. Publicering är ändå separerad från inlämning: ett inlämnat arbete kan vara privat, men ett utkast kan inte vara publikt.

### CSRF

**CSRF** betyder *Cross-Site Request Forgery*. Det är en attack där en extern sida försöker få en redan inloggad användare att råka skicka en ändring till systemet.

SAGA skyddar skrivande formulär med CSRF-token för att minska risken att någon ändrar uppgifter, loggar ut eller utför adminåtgärder via en manipulerad länk eller sida.

### XSS

**XSS** betyder *Cross-Site Scripting*. Det är när någon försöker få skadlig kod att köras i en annan användares webbläsare, ofta genom textfält eller uppladdat innehåll.

SAGA hanterar detta genom att användarinmatad text ska visas som text, inte som körbar kod.

### SQL-injektion

**SQL-injektion** är när någon försöker påverka databasfrågor genom att skriva in kod i exempelvis formulär eller sökfält.

Det är en allvarlig risk eftersom databasen innehåller användare, projekt och behörighetsinformation. SAGA använder säkrare databasanrop där inmatade värden hålls separerade från SQL-koden.

### Session

En **session** är serverns sätt att komma ihåg att en användare är inloggad när personen går mellan sidor.

Sessioner behöver skyddas eftersom ett kapat eller kvarlämnat inloggat konto på en delad dator kan ge åtkomst till elev- eller administratörsdata.

### Cookie

En **cookie** är en liten uppgift som webbläsaren sparar för en webbplats. Den kan till exempel användas för att koppla webbläsaren till rätt session.

Cookies behöver hanteras varsamt eftersom de kan påverka både integritet och inloggningssäkerhet.

### Rate limiting

**Rate limiting** betyder att systemet begränsar hur många försök som får göras under en viss tid.

I SAGA används det för inloggning, så att någon inte lika enkelt kan testa stora mängder lösenord.

### Auditlogg

En **auditlogg** är en spårbar logg över viktiga händelser, till exempel inloggningar, kontoändringar och administrativa åtgärder.

Auditlogg behövs för ansvar och felsökning, men den måste samtidigt begränsas så att den inte sparar mer persondata än nödvändigt.

### Retention

**Retention** betyder hur länge information sparas innan den bör gallras.

I SAGA är retention viktigt för att loggar och äldre filversioner inte ska ligga kvar längre än de behövs för drift, säkerhet och spårbarhet.

### Migration

En **migration** är en kontrollerad förändring av databasen när systemet uppdateras.

Migrationer behövs för att installationer ska kunna uppgraderas utan att varje databas ändras manuellt på olika sätt.

### SMTP

**SMTP** är ett standardiserat sätt att skicka e-post via en e-postserver.

För SAGA är SMTP viktigt eftersom notifieringar och lösenordsåterställning behöver vara tillförlitliga, spårbara och skickas från en kontrollerad avsändare.

### TLS

**TLS** är kryptering för trafik mellan system, till exempel mellan webbplatsen och en e-postserver eller mellan användarens webbläsare och SAGA.

TLS behövs för att minska risken att inloggningsuppgifter, återställningslänkar eller annan känslig information kan läsas på vägen.

### CSP och HSTS

**CSP** står för *Content Security Policy* och hjälper webbläsaren att begränsa vilka resurser en sida får ladda.  
**HSTS** säger åt webbläsaren att fortsätta använda HTTPS för webbplatsen.

Båda är exempel på webbläsarskydd som minskar konsekvenserna av vissa fel eller attacker.

## Relaterad dokumentation

- [security.md](security.md) beskriver säkerhetsanalysen av SAGA.
- [Todo.md](Todo.md) visar åtgärder som har identifierats och bockats av under arbetet.
