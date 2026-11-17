# Upute za Claude sesije

Ovaj plugin mijenja više Claude sesija. One ne vide razgovore jedna
druge: zajedničko pamćenje su samo git povijest, README.md i memory
bilješke. Zato sve ide kroz git.

## Prije bilo kakve izmjene

1. `git pull`, pa `git log --oneline -10`.
2. Pročitaj odjeljak "Verzije" u README.md.
3. Usporedi plugin na serveru (ufokus.hr) s `git show HEAD:cjenik-automatizacija.php`.
   Ako se razlikuju, stani i pitaj korisnika prije bilo kakve objave.

## Nakon svake izmjene

1. Podigni `Version:` u zaglavlju plugina. Iza točke uvijek dvije
   znamenke (2.96 do 2.99, pa 3.0). Nikad 2.949 ili 2.100: WordPress
   uspoređuje dijelove kao cijele brojeve, pa bi starija verzija
   izgledala novije.
2. Upiši stavku u "Verzije" u README.md.
3. Pokreni lokalne testove (README.md, odjeljak "Testovi"), svi moraju proći.
4. Commit i push. Autor s GitHub noreply e-mailom, ne osobnim e-mailom
   (repo je javan).
5. Release: zip s mapom `cjenik-automatizacija` (plugin, README.md,
   LICENSE, assets/logo.svg), pa `gh release create vX.YZ cjenik-automatizacija.zip`.
   Ne koristiti zip koji GitHub sam nudi (krivi naziv mape).
6. Objavi na ufokus.hr i provjeri /objava-cjenika i naslovnicu. Pristup
   i postupak su u memory bilješci `cjenik-plugin-project`. Pristupni
   podaci nikad ne idu u ovaj repo.

## Ne raditi

- Ne mijenjati stupce Google tablice Udruge Fokus: to radi kolegica sa
  savjetnicima iz ministarstva.
- Ne postavljati `DISABLE_WP_CRON` u wp-config.php (zaustavlja backup i
  ostale zakazane zadatke).
