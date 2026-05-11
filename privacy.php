<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Integritet och kakor';
require_once __DIR__ . '/includes/header.php';
?>

<section class="section section-tight">
    <p class="eyebrow">Integritet</p>
    <h1>Integritet och kakor</h1>
    <p class="muted">SAGA ska samla in så lite persondata som möjligt och bara använda data för inloggning, behörighet, elevarbeten och drift.</p>
</section>

<section class="section">
    <h2>Kakor och lokal lagring</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Namn</th>
                <th>Typ</th>
                <th>Syfte</th>
                <th>Lagring</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><code><?= h(SESSION_NAME) ?></code></td>
                <td>Nödvändig kaka</td>
                <td>Håller användaren inloggad och kopplar sessionen till CSRF-skydd.</td>
                <td>Tills webbläsarsessionen avslutas eller sessionen löper ut.</td>
            </tr>
            <tr>
                <td><code>saga_cookie_consent</code></td>
                <td>Nödvändig kaka</td>
                <td>Kommer ihåg att användaren sett informationen om nödvändiga kakor.</td>
                <td>Upp till 10 år eller tills användaren rensar kakor.</td>
            </tr>
            <tr>
                <td><code>saga_theme_mode</code></td>
                <td>Inställningskaka</td>
                <td>Sparar valt tema: ljust, mörkt eller automatiskt.</td>
                <td>Upp till 1 år eller tills användaren rensar kakor.</td>
            </tr>
            <tr>
                <td><code>localStorage: saga.themeMode</code></td>
                <td>Lokal webblagring</td>
                <td>Gör att temat kan tillämpas direkt innan sidan är färdigladdad.</td>
                <td>Tills användaren rensar webbplatsdata i webbläsaren.</td>
            </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="section">
    <h2>Personuppgifter</h2>
    <ul class="checklist">
        <li>Användarkonton innehåller namn, användarnamn, roll, skola och valfri e-postadress.</li>
        <li>Gymnasiearbeten innehåller elevens namn, titel, handledare, kategori, abstract, sammanfattning och uppladdad PDF.</li>
        <li>Privata arbeten visas bara enligt behörighetsreglerna. Publika arbeten visas för alla.</li>
        <li>Vid registrering samtycker användaren till att SAGA behandlar kontouppgifter i relation till vald skola.</li>
        <li>Om en elev väljer publik synlighet samtycker eleven separat till att namn och gymnasiearbete blir sökbart i SAGA.</li>
        <li>Auditloggen sparar händelser med anonymiserad IP-prefixinformation, inte full IP-adress.</li>
        <li>E-postloggen sparar mottagare, ämne, status och fel, men inte brödtexten i nya notiser.</li>
    </ul>
</section>

<section class="section">
    <h2>Dina rättigheter</h2>
    <ul class="checklist">
        <li>Du kan ladda ned en kopia av dina uppgifter från profilsidan.</li>
        <li>Du kan ta tillbaka publik synlighet för ett arbete genom att göra arbetet privat igen.</li>
        <li>Du kan begära radering direkt från profilsidan. Då tas kontot, personuppgifter, egna arbeten, uppladdade filer och återkoppling kopplad till kontot bort från SAGA.</li>
        <li>Vissa tekniska driftloggar kan behöva finnas kvar en kort tid, men kopplingen till kontot tas bort där SAGA kan göra det automatiskt.</li>
    </ul>
</section>

<section class="section">
    <h2>Rekommenderad drift</h2>
    <ul class="checklist">
        <li>Använd HTTPS.</li>
        <li>Håll uppladdade filer skyddade så de bara nås via behörighetskontrollerade PHP-filer.</li>
        <li>Rensa gamla loggar och PDF-versioner enligt skolans gallringsbeslut.</li>
        <li>Använd inte testkonton eller testlösenord i produktion.</li>
    </ul>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
