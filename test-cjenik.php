<?php
// Pokretanje: wp eval-file test-cjenik.php <javni_google_csv_url> <url_koji_nije_csv>
// Briše zapise i brojače cjenika, koristiti samo lokalno.

function check($cond, $msg) {
    if (!$cond) { WP_CLI::error("FAIL: $msg"); }
    WP_CLI::log("ok - $msg");
}

[$google_url, $bad_url] = $args;
$oib = '12345678901';

// --- čista logika ---
check(cjenik_auto_key($oib, 'ZA_SVA_PRODAJNA_MJESTA', 'roba') === "{$oib}_ZA_SVA_PRODAJNA_MJESTA_ROBA", 'naziv za sva prodajna mjesta');
check(cjenik_auto_key($oib, 'Prodavaonica Kutnjački put 11 PM1', 'USLUGA') === "{$oib}_PRODAVAONICA_KUTNJACKI_PUT_11_PM1_USLUGA", 'naziv s prodajnim mjestom i dijakritikama');
check(cjenik_auto_build_filename("{$oib}_X_ROBA", 7, '20261117', '0600') === "{$oib}_X_ROBA_0007_20261117_0600.csv", 'broj pohrane i GGGGMMDD_SSMM');
check(!cjenik_auto_is_csv("<!DOCTYPE html><html>"), 'HTML login stranica se odbija');

$feeds = cjenik_auto_parse_feeds("# x\n\nA|$oib|ZA_SVA_PRODAJNA_MJESTA|roba|U\nStari|UDRUGA|Adresa|001|U\nB|$oib|M|ROBE|U");
check(count($feeds) === 3 && $feeds[0]['error'] === '' && $feeds[0]['vrsta'] === 'ROBA', 'ispravan redak');
check($feeds[1]['error'] !== '' && $feeds[2]['error'] !== '', 'stari format i kriva vrsta su greška');

$out = cjenik_auto_to_semicolon_csv("\xEF\xBB\xBFNaziv,\"Cijena\n10.9.2026.\",Opis\n\"Knjiga \"\"A\"\"\",\"10,00\",\"a; b\"\n,,\n");
check(substr_count($out, "\xEF\xBB\xBF") === 1, 'točno jedan UTF-8 BOM');
$rows = array_map(fn($l) => str_getcsv($l, ';', '"', ''), explode("\n", trim(substr($out, 3))));
check($rows === [['Naziv', 'Cijena 10.9.2026.', 'Opis'], ['Knjiga "A"', '10,00', 'a; b']], 'pretvorba u točku-zarez, bez prijeloma i praznih redaka');

// --- objava preko lokalne izvorne datoteke koju test mijenja ---
delete_option(CJENIK_OPTION_LOG);
delete_option(CJENIK_OPTION_COUNTERS);
$src = trailingslashit(wp_upload_dir()['basedir']) . 'cjenik-test-izvor.csv';
$src_url = trailingslashit(wp_upload_dir()['baseurl']) . 'cjenik-test-izvor.csv';
file_put_contents($src, "Naziv robe,Bazna cijena\nKnjiga,\"10,00\"\n");

update_option(CJENIK_OPTION_FEEDS, "Stari|UDRUGA|Adresa|001|$src_url");
[$errors, $n] = cjenik_auto_run();
check(count($errors) === 1 && $n === 0 && empty(get_option(CJENIK_OPTION_LOG)), 'stari format retka: greška, ništa objavljeno');

update_option(CJENIK_OPTION_FEEDS, "Privatna|$oib|ZA_SVA_PRODAJNA_MJESTA|ROBA|$bad_url");
[$errors, $n] = cjenik_auto_run();
check(count($errors) === 1 && $n === 0, 'privatna tablica: greška, ništa objavljeno');

update_option(CJENIK_OPTION_FEEDS, "Knjige|$oib|ZA_SVA_PRODAJNA_MJESTA|ROBA|$src_url");
[$errors, $n] = cjenik_auto_run();
$log = get_option(CJENIK_OPTION_LOG);
check($errors === [] && $n === 1 && strpos($log[0]['file'], "{$oib}_ZA_SVA_PRODAJNA_MJESTA_ROBA_0001_") === 0, 'prva objava, broj pohrane 0001');
$path0 = trailingslashit(wp_upload_dir()['basedir']) . 'cjenici/' . $log[0]['file'];
check(strpos(file_get_contents($path0), 'Knjiga;10,00') !== false, 'objavljena datoteka koristi točku-zarez');
check(wp_remote_retrieve_response_code(wp_remote_get($log[0]['url'])) === 200, 'datoteka je javno dostupna na webu');

[$errors, $n] = cjenik_auto_run();
check($n === 0 && count(get_option(CJENIK_OPTION_LOG)) === 1, 'nepromijenjen cjenik: nema nove objave');

file_put_contents($src, "Naziv robe,Bazna cijena\nKnjiga,\"12,00\"\n");
[$errors, $n] = cjenik_auto_run();
$log = get_option(CJENIK_OPTION_LOG);
check($n === 1 && count($log) === 2 && !empty($log[0]['replaced']) && empty($log[1]['replaced']) && strpos($log[1]['file'], '_0002_') !== false, 'promjena: nova objava 0002, stara označena kao zamijenjena');

// zamijenjen prije 31 dan nestaje, važeći ostaje i kad je star
$log[0]['replaced'] -= 31 * DAY_IN_SECONDS;
$log[1]['timestamp'] -= 60 * DAY_IN_SECONDS;
update_option(CJENIK_OPTION_LOG, $log);
cjenik_auto_run();
$log = get_option(CJENIK_OPTION_LOG);
check(!file_exists($path0) && count($log) === 1 && strpos($log[0]['file'], '_0002_') !== false, 'arhiva briše se nakon roka, važeći ostaje');

// broj pohrane ne kreće ispočetka nakon brisanja arhive
file_put_contents($src, "Naziv robe,Bazna cijena\nKnjiga,\"13,00\"\n");
cjenik_auto_run();
$files = array_column(get_option(CJENIK_OPTION_LOG), 'file');
check(strpos(end($files), '_0003_') !== false, 'broj pohrane nastavlja se (0003)');

// ručno obrisana datoteka nestaje s popisa
unlink(trailingslashit(wp_upload_dir()['basedir']) . 'cjenici/' . $files[0]);
cjenik_auto_run();
check(!in_array($files[0], array_column(get_option(CJENIK_OPTION_LOG), 'file'), true), 'ručno obrisan cjenik nestaje s popisa');

// roba i usluge istog mjesta su dva odvojena cjenika
update_option(CJENIK_OPTION_FEEDS, "Knjige|$oib|ZA_SVA_PRODAJNA_MJESTA|ROBA|$src_url\nUsluge|$oib|ZA_SVA_PRODAJNA_MJESTA|USLUGA|$src_url");
[$errors, $n] = cjenik_auto_run();
check($n === 1 && count(array_unique(array_column(get_option(CJENIK_OPTION_LOG), 'key'))) === 2, 'roba i usluge: zasebni cjenici');

// stvarna Google tablica
update_option(CJENIK_OPTION_FEEDS, "Google|$oib|ZA_SVA_PRODAJNA_MJESTA|ROBA|$google_url");
[$errors, $n] = cjenik_auto_run();
check($errors === [] && $n === 1, 'stvarna Google tablica se objavljuje');

$html = cjenik_auto_render_list();
check(strpos($html, 'važeći') !== false && strpos($html, 'Knjige') !== false && strpos($html, 'Usluge') !== false, 'popis prikazuje sve cjenike i oznaku važeći');

unlink($src);
WP_CLI::success('Svi testovi prolaze');
