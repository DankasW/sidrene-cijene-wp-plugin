<img src="assets/logo.svg" width="96" height="96" alt="Sidrene cijene logo">

# Sidrene cijene: WordPress plugin za objavu cjenika

WordPress plugin koji cjenik iz Google Sheeta objavljuje na vašoj
stranici prema **Pravilniku o načinu objave cjenika proizvoda**
(NN 132/2026, na snazi od 17. studenoga 2026.): CSV datoteka s
propisanim nazivom, stupci odvojeni znakom `;`, nova objava kad se
cjenik promijeni i arhiva zamijenjenih cjenika. Napravljen za Udrugu
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

1. Na GitHubu: **Code → Download ZIP**, pa u WordPressu **Plugins → Add
   New → Upload Plugin** i aktivirajte. Na popisu pluginova zove se
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

1. Jednom dnevno, u 6 sati, preuzme cjenik iz Google Sheeta (CSV izvoz).
   Ako preuzimanje ne uspije, pokušava ponovno svaki sat do 11 sati.
2. Objavi novu datoteku u `wp-content/uploads/cjenici/`. Po zadanom
   **svaki dan**, i kad je cjenik isti kao jučer, pa se na stranici uvijek
   vidi današnja objava. Ako u postavkama maknete kvačicu "Objavi link svaki
   dan", nova datoteka objavljuje se samo kad se cjenik promijeni (to je
   minimum koji traži pravilnik).
3. Datoteku pretvara u oblik iz pravilnika: UTF-8, stupci odvojeni
   točkom-zarezom, bez prijeloma reda unutar ćelija.
4. Naziv datoteke (čl. 6. pravilnika):
   `OIB_PRODAJNOMJESTO_ROBA_BROJPOHRANE_GGGGMMDD_SSMM.csv`, npr.
   `12345678901_ZA_SVA_PRODAJNA_MJESTA_ROBA_0001_20261117_0600.csv`.
   Broj pohrane raste sa svakom objavom tog cjenika.
5. Važeći cjenik ostaje objavljen dok ga ne zamijeni novi. Zamijenjeni
   cjenici ostaju dostupni 15 dana od zamjene, kako traži pravilnik
   (čl. 3. st. 6.), a zatim ih plugin sam briše.
6. Na adresi **/objava-cjenika/** (na ufokus.hr:
   https://www.ufokus.hr/objava-cjenika/) prikazuje važeće cjenike i
   arhivu.

Ako preuzimanje ne uspije (npr. tablica više nije javna), ništa se ne
objavljuje, a dotadašnji cjenik ostaje važeći.

## Postavke

WP admin → **Settings → Sidrene cijene** (hrvatski WordPress: Postavke → Sidrene cijene). Jedan cjenik po retku:

```
Naslov na stranici|OIB|PRODAJNO MJESTO|ROBA ili USLUGA|CSV_LINK
```

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

### Roba i usluge: dva cjenika

Ako nudite i robu i usluge, pravilnik traži **dva odvojena cjenika**
(dvije datoteke s različitim stupcima). U postavkama su to dva retka,
jedan s `ROBA`, drugi s `USLUGA`. Podatke možete držati na dva načina:

- **Dvije zasebne Google tablice**: svaki redak ima link svoje tablice.
- **Jedna tablica s dva taba**: oba retka imaju isti link, ali svaki
  s `&gid=<ID taba>` na kraju. ID taba vidi se u adresnoj traci kad
  je tab otvoren (`...#gid=123456789`).

Link **bez** `gid` uvijek uzima **prvi** tab. Ako tablica ima više
tabova, a `gid` izostavite, oba retka objavila bi isti (prvi) tab.

```
Knjige|12345678901|ZA_SVA_PRODAJNA_MJESTA|ROBA|https://docs.google.com/spreadsheets/d/ID/export?format=csv&gid=0
Seminari|12345678901|ZA_SVA_PRODAJNA_MJESTA|USLUGA|https://docs.google.com/spreadsheets/d/ID/export?format=csv&gid=987654321
```

(`gid=0` u primjeru vrijedi samo ako prvi tab stvarno ima ID 0, što
nije uvijek slučaj. Uvijek ga prepišite iz adresne trake.)

Neispravan redak plugin prijavi na stranici postavki i za njega ništa
ne objavljuje. Gumb **"Provjeri i objavi sada"** odmah provjeri tablicu
i objavi ako se cjenik promijenio, ili (uz dnevnu objavu) ako danas još
nije objavljen. Isti, već objavljeni cjenik ne objavljuje dvaput isti dan.

### Upozorenja e-mailom

Ako provjera ne uspije (tablica više nije javna, Google ne odgovara,
neispravan redak u postavkama) ili jutarnja provjera izostane, a
tablica ima neobjavljene promjene (vidi "Pokretanje"), plugin šalje
e-mail. Adrese se upisuju
u polje **"E-mail za upozorenja"**, više njih odvojenih zarezom (npr.
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

### Kada mijenjati tablicu

Tablica uvijek sadrži **cijene od sutra ujutro**. Plugin jednom dnevno,
u 6 sati, objavi stanje tablice (uz dnevnu objavu uvijek, inače samo ako
se promijenilo od zadnje objave).
Sve izmjene napravljene tijekom dana (npr. jedna osoba ujutro, druga u
popodnevnoj smjeni) skupe se i objave zajedno sljedećeg jutra, prije
roka od 11 sati koji traži pravilnik. Na webu do tada stoji dosadašnji
cjenik.

- Nove cijene upišite **dan prije** nego što vrijede, bilo kad tijekom
  dana.
- Ako cijena mora vrijediti **odmah, istog dana**, upišite je pa u
  postavkama kliknite **"Provjeri i objavi sada"**: gumb objavljuje
  odmah, bez čekanja jutra.
- Ispravak tipfelera je također promjena, pa ulazi u sljedeću objavu.

Vrijeme se mijenja u kodu (`CJENIK_FROM_HOUR`, `CJENIK_UNTIL_HOUR`).

## Pokretanje

Plugin radi i **samo s ugrađenim WordPress cronom**: provjera se
pokreće pri posjetima stranici, svaki puni sat, a objavljuje između 6
i 11 sati. Ako između 6 i 11 nitko ne posjeti stranicu, objava tog
dana izostane i promjene čekaju sljedeće jutro.

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

Ako jutarnja provjera izostane, a tablica se nije mijenjala, uz dnevnu
objavu isti cjenik objavi se kod prvog posjeta nakon 11 sati, a bez nje
ništa se ne događa (stari cjenik i dalje vrijedi). Ako je tablica promijenjena,
plugin kod prvog posjeta nakon 11 sati ništa ne objavljuje, nego pošalje
upozorenje e-mailom i prikaže ga u postavkama. Tada sami odlučite hoćete
li kliknuti "Provjeri i objavi sada" ili pričekati sljedeće jutro.

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

Stranica se namjerno ne sprema u cache (WP-Optimize i slični), inače
bi posjetitelji i programi za prikupljanje cijena vidjeli stari popis.
Pravilnik (čl. 5.) traži da automatizirani programi mogu slobodno
dohvaćati cjenik, pa stranicu i mapu `cjenici` ne treba blokirati.

Ako se CSV datoteka ručno obriše iz `uploads/cjenici/`, kod sljedeće
provjere nestaje i s popisa (nema neispravnih linkova).

## Testovi (lokalno)

`test-cjenik.php` (nije na serveru) provjerava nazive datoteka,
pretvorbu u točku-zarez, preskakanje naslova iznad tablice, dnevni
prozor objave (6 do 11 sati), dnevnu objavu i objavu samo kod promjene, broj pohrane,
arhivu i odbijanje privatne tablice:

```
wp eval-file test-cjenik.php <javni_google_csv_link> <link_koji_nije_csv>
```

Briše zapise objava, zato se pokreće samo na lokalnoj kopiji stranice.

## Verzije

Manje izmjene dižu broj za mali korak (2.9, 2.91, 2.92...). Verzija 3.0
dolazi tek uz veću promjenu u radu plugina.

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
