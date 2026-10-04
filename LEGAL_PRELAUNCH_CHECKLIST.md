# GEHWOL Latvia: pirms publicēšanas apstiprināmā informācija

Pārbaudes datums: 2026-09-28

Šajā sarakstā ir jautājumi, kurus nevar droši noteikt no repozitorija. Tie jāapstiprina vietnes īpašniekam un, kur nepieciešams, pakalpojumu sniedzējiem. Neapstiprinātie fakti nav pievienoti vietnei kā noteikti apgalvojumi.

## Uzņēmuma rekvizīti

- Apstiprināt, ka `Dzērbenes 14, 306.C, Rīga, LV-1006` ir uzņēmuma aktuālā juridiskā adrese, nevis tikai biroja vai korespondences adrese.
- Apstiprināt oficiālo vispārējo e-pasta adresi. Vietnē kā galvenā izmantota `versia@load.lv`; publicēta arī `versia.inese@load.lv`.
- Apstiprināt abus publicētos tālruņa numurus: `+371 27055700` un `+371 67663333`.
- Pārbaudīt PVN maksātāja statusu. PVN numurs juridiskajā lapā un footer nav publicēts, jo repozitorijs to nepierāda. Ja statuss ir spēkā, pievienot atsevišķu rindu `PVN maksātāja Nr. LV40003535545`.

## Mitināšana, e-pasts un personas dati

- Norādīt faktiskā hosting provider juridisko nosaukumu un apstrādes valsti.
- Noskaidrot servera, piekļuves un drošības žurnālu faktisko saturu un glabāšanas termiņus.
- Norādīt faktiskā e-pasta pakalpojuma sniedzēja juridisko nosaukumu, datu glabāšanas kārtību un apstrādes valstis.
- Pārbaudīt CDN, WAF, DDoS aizsardzības un citu starpniekpakalpojumu esamību production vidē, kā arī to sīkdatnes un žurnālus.
- Apstiprināt, vai kāds pakalpojuma sniedzējs nosūta personas datus ārpus EEZ; ja jā, dokumentēt nodošanas valsti, pamatu un aizsardzības pasākumus.
- ~~Pārbaudīt PHP sesijas sīkdatnes faktisko nosaukumu, `Secure`, `HttpOnly`, `SameSite` parametrus un darbības laiku production konfigurācijā.~~ Pārbaudīts 2026-10-04: `gehwol_admin`, `path=/php/admin/`, `Secure`, `HttpOnly`, `SameSite=Strict`, sesijas sīkdatne, 30 min neaktivitātes limits; publiskajās lapās sīkdatņu nav. Aprakstīts sīkdatņu politikā.
- Noteikt un dokumentēt saprātīgus faktiskos glabāšanas termiņus e-pasta sarakstei, administratoru datiem un drošības informācijai.
- Noslēgt un pārbaudīt nepieciešamos datu apstrādes līgumus ar apstrādātājiem.

## GEHWOL statuss un intelektuālais īpašums

- Dokumentāri apstiprināt Versija Intersource, SIA tiesības saukt sevi par GEHWOL oficiālo vai autorizēto izplatītāju Latvijā. Līdz apstiprināšanai šāds statuss nav pievienots juridiskajam paziņojumam.
- Apstiprināt tiesības izmantot GEHWOL preču zīmes, logotipus, produktu attēlus, tekstus un citus ražotāja materiālus.
- Precizēt preču zīmju īpašnieku un apstiprināto attribution formulējumu, ja tiesību īpašnieks pieprasa to publicēt.

## Produktu apgalvojumi

- Izskatīt visus ierakstus failā `LEGAL_CLAIMS_AUDIT.md`.
- Katram produktam apstiprināt regulatīvo kategoriju un tieši Latvijā izplatītās versijas dokumentāciju.
- Saņemt rakstisku ražotāja vai atbildīgās personas apstiprinājumu medicīniskiem un kosmētiskiem apgalvojumiem.
- Pārbaudīt, vai `gehwol.de` atrodamais teksts attiecas uz identisku produkta sastāvu, versiju un paredzēto tirgu. Vācijas vietne izmantojama salīdzināšanai, bet neaizstāj Latvijas produkta marķējumu un dokumentāciju.

## Production pārbaude

- Pēc izvietošanas pārlūkā un izstrādātāju rīkos pārbaudīt visas atbildes `Set-Cookie`, tīkla pieprasījumus, storage un trešo pušu resursus.
- Pārbaudīt faktiskās HTTP drošības galvenes un HTTPS konfigurāciju.
- Ja tiek atrastas izvēles sīkdatnes vai tracking, pirms to turpmākas izmantošanas ieviest piekrišanas pārvaldību un atjaunināt politikas.
