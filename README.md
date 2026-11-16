# Cjenik Automatizacija (sidrene cijene)

WordPress plugin koji svaki dan automatski objavljuje cjenik iz Google
Sheeta na vašoj stranici: CSV datoteke s nazivom propisanog oblika,
povijest od 30 dana i stranica s popisom svih objava. Napravljen za
Udrugu Fokus (ufokus.hr), slobodan za korištenje pod licencom
GPL-2.0-or-later.

> **Nije pravni savjet.** Format naziva datoteke i stupce cjenika
> preuzeli smo iz vodiča za objavu cjenika, ne izravno iz propisa.
> Provjerite sami koja pravila vrijede za vas.

**Autor i održavanje:** Daniel Wurzberg, Udruga Fokus. Hosting i
suradnici na ufokus.hr: za bilo kakvo pitanje o ovom pluginu, cron jobu
ili datotekama u `wp-content/uploads/cjenici/` javite se njemu prije
bilo kakve izmjene.

## Instalacija

1. Na GitHubu: **Code → Download ZIP**, pa u WordPressu **Plugins → Add
   New → Upload Plugin** i aktivirajte.
2. Google Sheet s cjenikom podijelite kao "Anyone with the link → Viewer".
3. U **Settings → Cjenik Automatizacija** upišite svoj cjenik (vidi
   "Postavke") i kliknite "Objavi cjenik sada".
4. Napravite WordPress stranicu s adresom (slugom) `objava-cjenika` i
   stavite link na nju u footer.
5. U kontrolnoj ploči hostinga (npr. cPanel → Cron Jobs) postavite dnevni
   poziv u 6:00 (vidi "Pokretanje u 6:00").

## Što radi

1. Svaki dan u 6:00 preuzme cjenik iz Google Sheeta (CSV izvoz).
2. Spremi ga u `wp-content/uploads/cjenici/` pod zakonskim nazivom:
   `VRSTA_ADRESA_LOKACIJA_VERZIJA_DATUM_VRIJEME.csv`,
   npr. `UDRUGA_KUTNJACKIPUT11_001_0001_20261117_0600.csv`.
3. Čuva sve objave 30 dana, starije sam briše.
4. Na adresi **/objava-cjenika/** (na ufokus.hr:
   https://www.ufokus.hr/objava-cjenika/) prikazuje popis svih cjenika
   iz zadnjih 30 dana.

Ako preuzimanje ne uspije (npr. tablica više nije javna), ništa se ne
objavljuje, a dotadašnji cjenici ostaju na stranici.

## Postavke

WP admin → **Settings → Cjenik Automatizacija**. Jedan cjenik po retku:

```
Naziv lokacije|VRSTA_SUBJEKTA|ADRESA|SIFRA_LOKACIJE|CSV_LINK
```

- CSV link: `https://docs.google.com/spreadsheets/d/<ID>/export?format=csv`
- Za pojedini tab tablice dodaje se `&gid=<ID taba>` (vidi se u adresi
  kad je tab otvoren). Ne pisati `&gid=0` naslijepo.
- Tablica mora biti dijeljena kao "Anyone with the link → Viewer".
- Gumb **"Objavi cjenik sada"** odmah radi novu objavu (nova verzija
  istog dana). Koristiti samo kad se cijene stvarno promijene.

Promjena linka ne briše stare objave: one ostaju na popisu 30 dana.

## Pokretanje u 6:00 (važno za hosting)

Objavu pokreće **cron job u cPanelu**, svaki dan u 6:00 po hrvatskom
vremenu:

```
wget -q -O /dev/null "https://www.ufokus.hr/wp-cron.php?doing_wp_cron"
```

(Na drugoj stranici zamijenite `www.ufokus.hr` svojom domenom.)

WordPressov ugrađeni cron (pokreće se na posjete stranici) ostaje
uključen, cPanel cron samo jamči da se objava dogodi i kad nema posjeta.
**Ne postavljati** `DISABLE_WP_CRON` u `wp-config.php` dok cPanel cron
radi samo jednom dnevno: to zaustavlja zakazane zadatke svih pluginova,
npr. UpdraftPlus sigurnosne kopije (isprobano 2026-11-16, vraćeno).

## Zašto stranica /objava-cjenika ne koristi temu

Plugin na adresi `/objava-cjenika` sam ispisuje generirani popis
(`wp-content/uploads/cjenici/index.html`), neovisno o temi. Tako radi
na svakoj temi, i na onima koje ne ispisuju sadržaj stranica (kao tema
na ufokus.hr). Stranica "Objava cjenika" u WordPressu mora postojati i
imati upravo tu adresu (slug). Sadržaj stranice se ne koristi.

Stranica se namjerno ne sprema u cache (WP-Optimize i slični), inače
bi nakon jutarnje objave posjetitelji satima vidjeli stari popis.

Ako se CSV datoteka ručno obriše iz `uploads/cjenici/`, kod sljedeće
objave nestaje i s popisa (nema neispravnih linkova).

## Provjera rada

Svako jutro nakon 6:00 na /objava-cjenika treba biti novi cjenik s
današnjim datumom i vremenom oko 06:00. Ako ga nema, provjeriti
redom: cron job u cPanelu, dijeljenje Google tablice, poruku nakon
klika na "Objavi cjenik sada" u postavkama.

## Testovi (lokalno)

`test-cjenik.php` (nije na serveru) provjerava nazive datoteka, verzije,
čuvanje 30 dana i odbijanje privatne tablice:

```
wp eval-file test-cjenik.php <javni_csv_link> <link_koji_nije_csv>
```

Briše zapis objava, zato se pokreće samo na lokalnoj kopiji stranice.
