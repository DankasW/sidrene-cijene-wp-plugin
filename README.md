<img src="assets/logo.svg" width="96" height="96" alt="Sidrene cijene logo">

# Sidrene cijene: WordPress plugin za objavu cjenika

WordPress plugin koji cjenik iz Google Sheeta objavljuje na vašoj
stranici prema **Pravilniku o načinu objave cjenika proizvoda**
(NN 132/2026, na snazi od 17. studenoga 2026.): CSV datoteka s
propisanim nazivom, stupci odvojeni znakom `;`, objava svako jutro (ili
samo kad se cjenik promijeni) i arhiva zamijenjenih cjenika. Napravljen za Udrugu
Fokus (ufokus.hr), slobodan za korištenje pod licencom GPL-2.0-or-later.

## Pravni okvir

Plugin je usklađen s propisima objavljenim u Narodnim novinama, stanje
na dan 16. studenoga 2026.:

- **Pravilnik o načinu objave cjenika proizvoda**, NN 132/2026 (1584),
  na snazi od 17.11.2026.:
  [PDF](https://narodne-novine.nn.hr/eli/sluzbeni/2026/132/1584/pdf),
  [web](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_11_132_1584.html)
- **Pravilnik o isticanju bazne cijene proizvoda**, NN 132/2026 (1583),
  na snazi od 17.11.2026.:
  [PDF](https://narodne-novine.nn.hr/eli/sluzbeni/2026/132/1583/pdf),
  [web](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_11_132_1583.html)
- Oba su donesena na temelju **Zakona o zaštiti potrošača**
  (NN 19/22, 59/23, 59/26).
- Objašnjenje Ministarstva gospodarstva:
  [Pravilnici o baznim cijenama i objavi cjenika](https://mingo.gov.hr/vijesti/pravilnici-o-baznim-cijenama-i-objavi-cjenika-veca-transparentnost-za-potrosace-provediva-pravila-za-gospodarstvo/10490)

Ako se propisi izmijene, plugin treba ponovno provjeriti.

> **Nije pravni savjet.** Plugin smo uskladili s tekstom pravilnika
> onako kako ga razumijemo. Provjerite sami odnosi li se pravilnik na
> vas i jesu li vaši podaci (stupci, cijene, prodajna mjesta) ispravni.

**Autor i održavanje:** Daniel Wurzberg, Udruga Fokus. Hosting i
suradnici na ufokus.hr: za bilo kakvo pitanje o ovom pluginu, cron jobu
ili datotekama u `wp-content/uploads/cjenici/` javite se njemu prije
bilo kakve izmjene.

## Instalacija

1. Na GitHubu otvorite [zadnji Release](https://github.com/DankasW/sidrene-cijene-wp-plugin/releases/latest)
   i preuzmite **`cjenik-automatizacija.zip`** (ne "Source code" niti
   "Code → Download ZIP": ti zipovi imaju drugi naziv mape, pa bi kasnija
   ažuriranja plugin instalirala kao drugi, zasebni plugin). Zatim u
   WordPressu **Plugins → Add New → Upload Plugin** i aktivirajte.
   Potrebni su WordPress 5.3 i PHP 8.1 ili noviji. Na popisu pluginova zove se
   "Sidrene cijene (objava cjenika)".
2. Google Sheet s cjenikom podijelite kao "Anyone with the link → Viewer".
3. Nakon aktivacije postavke pluginu su u lijevom izborniku WP admina:
   **Settings → Sidrene cijene** (hrvatski WordPress: **Postavke →
   Sidrene cijene**). Tamo upišite svoj cjenik (vidi "Postavke") i
   kliknite "Provjeri i objavi sada".
4. Napravite WordPress stranicu s adresom (slugom) `objava-cjenika` i
   stavite link na nju u footer.
5. Preporučeno: postavite serverski ili besplatni vanjski cron (vidi
   "Pokretanje"), da jutarnja objava ne ovisi o posjetima stranici.

## Što radi

1. **Svako jutro u 6 sati** preuzme svaki cjenik iz Google Sheeta (CSV
   izvoz). Ako to ne uspije, pokušava ponovno svaki sat do 11 sati (rok iz
   pravilnika za objavu izmjene).
2. **Objavi novu datoteku** u `wp-content/uploads/cjenici/`. Po zadanom
   svaki dan, i kad je cjenik isti kao jučer, pa se na stranici uvijek
   vidi današnja objava. Bez kvačice "Objavi link svaki dan" nova datoteka
   nastaje samo kad se cjenik promijeni (minimum koji traži pravilnik).
3. Datoteku pretvara u oblik iz pravilnika: UTF-8, stupci odvojeni
   točkom-zarezom, bez prijeloma reda unutar ćelija.
4. Naziv datoteke (čl. 6. pravilnika):
   `OIB_PRODAJNOMJESTO_ROBA_BROJPOHRANE_GGGGMMDD_SSMM.csv`, npr.
   `12345678901_ZA_SVA_PRODAJNA_MJESTA_ROBA_0001_20261117_0600.csv`.
   Broj pohrane raste sa svakom objavom tog cjenika.
5. **Arhiva:** važeći cjenik ostaje objavljen dok ga ne zamijeni novi.
   Zamijenjeni cjenici ostaju dostupni 15 dana od zamjene (čl. 3. st. 6.),
   a zatim ih plugin sam briše. Isto vrijedi za cjenik čiji redak maknete
   iz postavki: prestaje biti važeći i nakon 15 dana nestaje.
6. Na adresi **/objava-cjenika/** (na ufokus.hr:
   https://www.ufokus.hr/objava-cjenika/) prikazuje važeće cjenike i
   arhivu.

**Izmjene tijekom dana** (npr. jedna osoba ujutro, druga popodne) skupe
se i objave zajedno sljedećeg jutra u 6. Tablica zato uvijek sadrži
cijene od sutra ujutro. Ako cijena mora vrijediti odmah, istog dana,
kliknite u postavkama **"Provjeri i objavi sada"**: gumb objavljuje
odmah ako se cjenik promijenio ili danas još nije objavljen, a isti
cjenik ne objavljuje dvaput u istom danu.

**Kad nešto ne uspije, dosadašnji cjenik uvijek ostaje važeći** i
stiže upozorenje e-mailom. To vrijedi kad tablica nije javna ili Google
ne odgovara, kad tablica nema nijednog retka s podacima (npr. slučajno
obrisani reci), kad se datoteka ne može spremiti na server i kad je redak
u postavkama neispravan.

**Propuštena jutarnja provjera** (između 6 i 11 nitko nije otvorio
stranicu, a nema serverskog crona, vidi "Pokretanje"): ako se tablica
nije mijenjala, uz dnevnu objavu isti cjenik objavi se kod prvog posjeta
nakon 11 sati. Ako je promijenjena, plugin ništa ne objavljuje, nego
pošalje upozorenje, a vi odlučite hoćete li kliknuti "Provjeri i objavi
sada" ili pričekati sljedeće jutro.

Vrijeme objave mijenja se u kodu (`CJENIK_FROM_HOUR`, `CJENIK_UNTIL_HOUR`).

## Postavke

WP admin → **Settings → Sidrene cijene** (hrvatski WordPress: Postavke → Sidrene cijene). Jedan cjenik po retku:

```
Naziv|OIB|PRODAJNO MJESTO|ROBA ili USLUGA|CSV_LINK
```

- **Naziv**: za vas, da znate koji je koji redak. Na stranici linkovi
  glase "Cjenik roba – datum" i "Cjenik usluga – datum". Naziv se
  dodaje u link samo ako imate više cjenika iste vrste (npr. više
  prodajnih mjesta), da se razlikuju.
- **OIB** za pravnu osobu, matični broj za fizičku osobu (samo znamenke).
- **PRODAJNO MJESTO**: vrsta, adresa i oznaka prodajnog mjesta, npr.
  `Prodavaonica Kutnjački put 11 PM1`. Ako je cjenik isti na svim
  prodajnim mjestima, upišite `ZA_SVA_PRODAJNA_MJESTA`.
- **ROBA** ili **USLUGA**: roba i usluge su odvojeni cjenici, svaki u
  svom retku (npr. dva taba iste tablice).
- **CSV link**: `https://docs.google.com/spreadsheets/d/<ID>/export?format=csv`.
  Za pojedini tab dodaje se `&gid=<ID taba>` (vidi se u adresi kad je
  tab otvoren). Ne pisati `&gid=0` naslijepo.

Primjer:
```
Knjige i priručnici|12345678901|ZA_SVA_PRODAJNA_MJESTA|ROBA|https://docs.google.com/spreadsheets/d/ID/export?format=csv
```

### Roba i usluge: jedna tablica s tabovima ili više tablica

Osnovno pravilo: **jedan redak u postavkama = jedan tab Google tablice
= jedan objavljeni cjenik.** Plugin uvijek čita samo jedan tab, onaj na
koji pokazuje link u retku. Ostale tabove iste tablice ne vidi.

Ako nudite i robu i usluge, pravilnik traži **dva odvojena cjenika**
(dvije datoteke, svaka sa svojim stupcima). U postavkama su to dakle
uvijek dva retka, jedan s `ROBA`, drugi s `USLUGA`, bez obzira na to
držite li podatke u jednoj tablici ili u dvije. Isto vrijedi za više
prodajnih mjesta s različitim cijenama: jedan redak po mjestu i vrsti.

**Način A: jedna Google tablica s više tabova** (npr. tab "Roba" i tab
"Usluge"). Sve je na jednom mjestu i tablica se dijeli samo jednom.

1. Tablicu podijelite kao "Anyone with the link → Viewer".
2. Otvorite tab s robom. U adresnoj traci na kraju piše `#gid=` i broj,
   npr. `...edit#gid=0`. To je ID tog taba.
3. Otvorite tab s uslugama i na isti način prepišite njegov ID, npr.
   `#gid=2113975497`.
4. Oba retka imaju **isti** ID tablice u linku, a razlikuju se samo po
   `&gid=` na kraju:

```
Roba|12345678901|ZA_SVA_PRODAJNA_MJESTA|ROBA|https://docs.google.com/spreadsheets/d/ID_TABLICE/export?format=csv&gid=0
Usluge|12345678901|ZA_SVA_PRODAJNA_MJESTA|USLUGA|https://docs.google.com/spreadsheets/d/ID_TABLICE/export?format=csv&gid=2113975497
```

**Način B: zasebna Google tablica za svaki cjenik.** Svaka tablica se
dijeli posebno, a link nema `&gid=` (dovoljan je prvi tab):

```
Roba|12345678901|ZA_SVA_PRODAJNA_MJESTA|ROBA|https://docs.google.com/spreadsheets/d/ID_TABLICE_ROBE/export?format=csv
Usluge|12345678901|ZA_SVA_PRODAJNA_MJESTA|USLUGA|https://docs.google.com/spreadsheets/d/ID_TABLICE_USLUGA/export?format=csv
```

ID tablice je dugi niz znakova u adresi između `/d/` i `/edit`.

Na stranici s cjenicima oba načina izgledaju jednako: "Cjenik roba –
datum" i "Cjenik usluga – datum".

**Česte greške:**

- **Link bez `&gid=` kad tablica ima više tabova** uvijek uzima
  **prvi** tab. Oba retka bi tada objavila isti tab.
- **`&gid=0` upisan naslijepo.** Prvi tab ima ID 0 samo ako je tablica
  napravljena s njim. Kod kopiranih ili preuređenih tablica ID je drugi
  broj, pa link vraća grešku. Uvijek prepišite broj iz adresne trake.
- **Nova tablica, a u postavkama stari link.** Ako se podaci presele u
  drugu tablicu, link u postavkama treba promijeniti. Plugin i dalje
  objavljuje onu tablicu na koju pokazuje link.
- **Novi tab dodan u tablicu.** Plugin ga neće objaviti dok za njega ne
  dodate redak u postavkama.

Neispravan redak plugin prijavi na stranici postavki i za njega ništa
ne objavljuje.

### Upozorenja e-mailom

Za svaki slučaj iz "Kad nešto ne uspije" i za propuštenu jutarnju
provjeru s promjenama (oboje u "Što radi") plugin šalje e-mail. Adrese
se upisuju u polje **"E-mail za upozorenja"**, više njih odvojenih zarezom (npr.
vi, web administrator i netko iz financija). Ako je polje prazno, poruka
ide na e-mail administratora stranice (Settings → General). Ista greška
javlja se najviše jednom dnevno, a dosadašnji cjenik za to vrijeme
ostaje objavljen. Na stranici postavki vidi se i **zadnja provjera bez
grešaka** te zadnja greška, ako je bilo.

Kad se u polje doda i spremi **nova** adresa, samo ona odmah dobije
pozdravni e-mail s trenutnim stanjem: važeći i arhivski cjenici s
linkovima te zadnja provjera. Tako se odmah vidi da poruke stižu.
Postojeće adrese pri spremanju ne dobivaju ništa.

Za slanje se koristi WordPressov e-mail (`wp_mail`), pa poruke stižu
samo ako stranica uopće može slati e-mail (to je isti put kojim stižu
npr. poruke za reset lozinke). Ako je instaliran plugin poput WP Mail
SMTP, poruke idu preko pošiljatelja postavljenog u njemu. Plugin nema
vlastiti SMTP, jer je slanje e-maila posao cijele stranice. Ako slanje
ne uspije, na stranici postavki pojavi se crveno upozorenje "Zadnji
e-mail nije poslan", pa se zna da upozorenja ne stižu.

## Stupci u Google tablici (čl. 3. pravilnika)

Plugin stupce prenosi kakvi jesu, pa redoslijed mora biti točan već u
tablici. Prvi red s barem dva popunjena stupca su naslovi stupaca.
Naslov iznad tablice (npr. samo "CJENIK" u ćeliji A1) i prazni redovi
se preskaču.

**Roba:**
`Naziv robe` | `Šifra robe` | `Marka robe` | `Neto količina` |
`Jedinica mjere` | `Maloprodajna cijena` | `Posebni oblik prodaje (DA/NE)` |
`Cijena za jedinicu mjere` | `Barkod` | `EAN barkod` | `Bazna cijena`

**Usluge:**
`Naziv usluge` | `Maloprodajna cijena` | `Posebni oblik prodaje (DA/NE)` |
`Bazna cijena`

Stupci koji za vas nisu primjenjivi ostaju prazni. Bazna cijena je
cijena od 2. svibnja 2025. za hranu, piće, kozmetiku, sredstva za
čišćenje, toaletne potrepštine i proizvode za kućanstvo, a od 10. rujna
2026. za ostalu robu i usluge (Pravilnik o isticanju bazne cijene
proizvoda, NN 132/2026). Cijene je najbolje pisati kao broj (`10,00`),
bez oznake valute, da ih programi lakše čitaju. Google u CSV šalje
ono što se vidi u ćeliji: ako je ćelija formatirana kao valuta, u
cjeniku piše npr. `€10.00`. Za čist broj: Format → Number → Number, a
za decimalni zarez File → Settings → Locale → Croatia.

Nove cijene upišite **dan prije** nego što vrijede. Ispravak tipfelera
je također promjena, pa ulazi u sljedeću objavu.

## Pokretanje

Plugin radi i **samo s ugrađenim WordPress cronom**, koji se pokreće
pri posjetima stranici. Ako između 6 i 11 nitko ne posjeti stranicu,
jutarnja provjera izostane (što se tada događa: "Što radi").

Za stranice s malo posjeta preporučujemo serverski cron (npr. cPanel →
Cron Jobs) koji svaki sat poziva WordPress cron:

```
wget -q -O /dev/null "https://www.ufokus.hr/wp-cron.php?doing_wp_cron"
```

(Na drugoj stranici zamijenite `www.ufokus.hr` svojom domenom.)

**Nemate cPanel?** Besplatni servis poput [cron-job.org](https://cron-job.org)
može svaki sat otvoriti adresu
`https://www.vasa-stranica.hr/wp-cron.php?doing_wp_cron`, bez pristupa
serveru. Dovoljno je i da netko između 6 i 11 sati otvori stranicu ili
se prijavi u WordPress admin, jer i to pokreće provjeru.

**Ne postavljati** `DISABLE_WP_CRON` u `wp-config.php` ako serverski
cron ne radi barem svakih nekoliko minuta: to zaustavlja zakazane
zadatke svih pluginova, npr. UpdraftPlus sigurnosne kopije (isprobano
na ufokus.hr 2026-11-16, vraćeno).

## Zašto stranica /objava-cjenika ne koristi temu

Plugin na adresi `/objava-cjenika` sam ispisuje generirani popis
(`wp-content/uploads/cjenici/index.html`), neovisno o temi. Tako radi
na svakoj temi, i na onima koje ne ispisuju sadržaj stranica (kao tema
na ufokus.hr). Stranica "Objava cjenika" u WordPressu mora postojati i
imati upravo tu adresu (slug). Sadržaj stranice se ne koristi.

**Popis na drugoj stranici: shortcode `[cjenik_link]`.** Ako vaša tema
normalno ispisuje sadržaj stranica, popis cjenika možete staviti na
bilo koju stranicu, s bilo kojom adresom (npr. "Cjenik" ili unutar
stranice "O nama"): u sadržaj stranice upišite `[cjenik_link]`. Popis
se tada prikazuje unutar vaše teme, s njezinim izgledom. Adresa
`/objava-cjenika` i dalje radi kao samostalna stranica. Ako je ne
trebate, stranicu s tom adresom jednostavno ne napravite.

Stranica se namjerno ne sprema u cache (WP-Optimize i slični), inače
bi posjetitelji i programi za prikupljanje cijena vidjeli stari popis.
Pravilnik (čl. 5.) traži da automatizirani programi mogu slobodno
dohvaćati cjenik, pa stranicu i mapu `cjenici` ne treba blokirati.

Ako se CSV datoteka ručno obriše iz `uploads/cjenici/`, kod sljedeće
provjere nestaje i s popisa (nema neispravnih linkova).

## Brisanje plugina

Plugins → deaktivirajte → Delete. Plugin pritom briše svoje postavke
(cjenike, e-mail adrese, brojače pohrane) i zakazanu provjeru.
Objavljene CSV datoteke u `wp-content/uploads/cjenici/` **ostaju**,
jer su javna arhiva cjenika. Ako ih ne trebate, obrišite tu mapu i
stranicu "Objava cjenika" ručno. Za privremeni prekid dovoljna je
deaktivacija: postavke tada ostaju.

## Testovi (lokalno)

`test-cjenik.php` (nije na serveru) provjerava sve opisano u "Što radi",
uz ažuriranja i shortcode. Svaka provjera je jedan redak `check(...)` s
opisom, pa je popis u samoj datoteci:

```
wp eval-file test-cjenik.php <javni_google_csv_link> <link_koji_nije_csv>
```

Briše zapise objava, zato se pokreće samo na lokalnoj kopiji stranice.

## Ažuriranja

Od verzije 2.95 plugin sam provjerava (dvaput dnevno) postoji li na
GitHubu novija verzija. Ako postoji, u WordPressu se na popisu
pluginova pojavi uobičajena obavijest **"Dostupna je nova verzija"**,
a ažurira se jednim klikom, kao svaki drugi plugin. Postavke i
objavljeni cjenici ostaju.

Za održavatelje: svaka verzija objavljuje se kao
[Release](https://github.com/DankasW/sidrene-cijene-wp-plugin/releases)
s oznakom `vX.YZ` i priloženom datotekom `cjenik-automatizacija.zip`, u
kojoj je mapa **`cjenik-automatizacija`** (ne zip koji GitHub sam
nudi, jer bi s drugim nazivom mape WordPress plugin instalirao kao novi
i stari deaktivirao). Broj u oznaci mora odgovarati `Version:` u
zaglavlju plugina.

## Verzije

Manje izmjene dižu broj za mali korak (2.9, 2.91, 2.92...). Verzija 3.0
dolazi tek uz veću promjenu u radu plugina.

**Pazite na brojeve:** WordPress verzije uspoređuje dio po dio, kao
cijele brojeve, a ne kao decimale. Za njega je 2.949 *veće* od 2.95
(949 > 95), pa korisnici ne bi dobili ponudu za ažuriranje. Zato iza
točke uvijek dvije znamenke (2.91 do 2.99), a nakon 2.99 ide 3.0
(pa 3.01, 3.02...), nikad 2.100 ili 2.991.

- **3.01**: oznaka "Nove cijene" uklonjena (pravilnik je ne traži). Za objavu
  samo kod promjene isključite "Objavi link svaki dan".
- **3.0**: oznaka "Nove cijene" (uklonjena u 3.01).
- **2.99**: kad spremanje datoteke ne uspije, upozorenje sadrži stvarni razlog
  koji javi server (npr. "Permission denied" ili "No space left on device").
- **2.98**: cjenik čiji je redak maknut iz postavki više ne ostaje
  "važeći" (arhivira se); tablica bez redaka s podacima se ne objavljuje;
  djelomično zapisana datoteka (pun disk) ne postaje važeća; brisanje
  plugina briše i njegove postavke (`uninstall.php`); testovi za
  ažuriranja i zaostalu bravu; sređen README.
- **2.97**: ako spremanje datoteke ne uspije, dosadašnji cjenik ostaje
  važeći (prije bi bio označen kao zamijenjen); brava protiv dvije
  istovremene objave; sigurniji dohvat linka (odbija unutarnje adrese
  poslužitelja); zaglavlje traži PHP 8.1 i WordPress 5.3; uklonjen kod
  za zapise od prije 17.11.2026.; dokumentiran shortcode `[cjenik_link]`.
- **2.96**: na stranici s cjenicima nema više naslova grupa, linkovi
  glase "Cjenik roba – datum" i "Cjenik usluga – datum" (roba prva).
- **2.95**: obavijest o novoj verziji i ažuriranje jednim klikom iz
  WordPressa (preko GitHub Releasea).
- **2.94**: kraći tekst kvačice "Objavi link svaki dan".
- **2.93**: broj verzije prikazan na stranici postavki.
- **2.92**: s javne stranice maknut tehnički opis formata (CSV, UTF-8, ;).
- **2.91**: kvačica "Objavi link svaki dan" (uključena po zadanom): isti
  cjenik objavljuje se svaki dan, a ne samo kod promjene. Jasnija poruka
  kad gumb nema što objaviti.
- **2.9**: upozorenje kad jutarnja provjera izostane, a tablica ima
  neobjavljene promjene. Bez promjena nema upozorenja.
- **2.8**: objava jednom dnevno u 6 sati (ponovni pokušaji do 11).
  Izmjene tijekom dana objavljuju se sljedeće jutro, gumb objavljuje
  odmah.
- **2.7**: naslov iznad tablice (npr. "CJENIK") se preskače.
- **2.6**: logo.
- **2.5**: upozorenje u postavkama kad stranica ne može slati e-mail.

Starije promjene: povijest commitova na GitHubu.
