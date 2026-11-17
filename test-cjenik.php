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
check(!cjenik_auto_due(5, '20261117', '') && cjenik_auto_due(6, '20261117', '20261116') && !cjenik_auto_due(9, '20261117', '20261117') && !cjenik_auto_due(11, '20261117', ''), 'objava jednom dnevno, 6-11 sati');
check(cjenik_auto_missed(14, '20261117', '20261116') && !cjenik_auto_missed(14, '20261117', '20261117') && !cjenik_auto_missed(10, '20261117', ''), 'propuštena jutarnja provjera se prepoznaje poslije 11');
check(cjenik_auto_to_semicolon_csv("CJENIK,,\nNAZIV,CIJENA,JM\n,,\nKnjiga,10,KOM\n") === "\xEF\xBB\xBFNAZIV;CIJENA;JM\nKnjiga;10;KOM\n", 'naslov iznad tablice i prazni redovi se preskaču');

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
update_option(CJENIK_OPTION_DAILY, '0'); // prvo način "samo kod promjene", dnevni se testira niže
$src =trailingslashit(wp_upload_dir()['basedir']) . 'cjenik-test-izvor.csv';
$src_url = trailingslashit(wp_upload_dir()['baseurl']) . 'cjenik-test-izvor.csv';
file_put_contents($src, "Naziv robe,Bazna cijena\nKnjiga,\"10,00\"\n");

update_option(CJENIK_OPTION_FEEDS, "Stari|UDRUGA|Adresa|001|$src_url");
[$errors, $n] = cjenik_auto_run(true);
check(count($errors) === 1 && $n === 0 && empty(get_option(CJENIK_OPTION_LOG)), 'stari format retka: greška, ništa objavljeno');

$mails = [];
add_filter('pre_wp_mail', function ($null, $atts) use (&$mails) { $mails[] = $atts; return true; }, 10, 2);
delete_option(CJENIK_OPTION_STATUS);

update_option(CJENIK_OPTION_FEEDS, "Privatna|$oib|ZA_SVA_PRODAJNA_MJESTA|ROBA|$bad_url");
[$errors, $n] = cjenik_auto_run(true);
check(count($errors) === 1 && $n === 0, 'privatna tablica: greška, ništa objavljeno');
check(count($mails) === 1 && $mails[0]['to'] === get_option('admin_email') && strpos($mails[0]['message'], 'Privatna') !== false, 'greška: e-mail adminu');
cjenik_auto_run(true);
check(count($mails) === 1, 'ista greška isti dan: nema drugog e-maila');
$status = get_option(CJENIK_OPTION_STATUS);
check(!empty($status['last_error']) && empty($status['last_ok']), 'status pamti zadnju grešku');
check(cjenik_auto_sanitize_emails('ja@primjer.hr, krivo, ona@primjer.hr') === 'ja@primjer.hr, ona@primjer.hr', 'e-mail polje: više adresa, neispravne izbačene');
update_option(CJENIK_OPTION_EMAIL, 'ja@primjer.hr, ona@primjer.hr');
check(cjenik_auto_alert_email() === 'ja@primjer.hr, ona@primjer.hr', 'upozorenje ide na adrese iz postavki');
delete_option(CJENIK_OPTION_EMAIL);
check(cjenik_auto_alert_email() === get_option('admin_email'), 'prazno polje: upozorenje ide administratoru');
$mails = array_slice($mails, 0, 1); // pozdravne mailove iz ovog bloka testiramo zasebno niže

update_option(CJENIK_OPTION_FEEDS, "Knjige|$oib|ZA_SVA_PRODAJNA_MJESTA|ROBA|$src_url");
[$errors, $n] = cjenik_auto_run(true);
$log = get_option(CJENIK_OPTION_LOG);
check($errors === [] && $n === 1 && strpos($log[0]['file'], "{$oib}_ZA_SVA_PRODAJNA_MJESTA_ROBA_0001_") === 0, 'prva objava, broj pohrane 0001');
check(!empty(get_option(CJENIK_OPTION_STATUS)['last_ok']) && count($mails) === 1, 'uspješna provjera: zabilježena, bez e-maila');
$path0 = trailingslashit(wp_upload_dir()['basedir']) . 'cjenici/' . $log[0]['file'];
check(strpos(file_get_contents($path0), 'Knjiga;10,00') !== false, 'objavljena datoteka koristi točku-zarez');
check(wp_remote_retrieve_response_code(wp_remote_get($log[0]['url'])) === 200, 'datoteka je javno dostupna na webu');

[$errors, $n] = cjenik_auto_run(true);
check($n === 0 && count(get_option(CJENIK_OPTION_LOG)) === 1, 'nepromijenjen cjenik: nema nove objave');

file_put_contents($src, "Naziv robe,Bazna cijena\nKnjiga,\"12,00\"\n");
[$errors, $n] = cjenik_auto_run(true);
$log = get_option(CJENIK_OPTION_LOG);
check($n === 1 && count($log) === 2 && !empty($log[0]['replaced']) && empty($log[1]['replaced']) && strpos($log[1]['file'], '_0002_') !== false, 'promjena: nova objava 0002, stara označena kao zamijenjena');

// zamijenjen prije 14 dana ostaje, prije 16 dana nestaje; važeći ostaje i kad je star
$log[0]['replaced'] -= 14 * DAY_IN_SECONDS;
update_option(CJENIK_OPTION_LOG, $log);
cjenik_auto_run(true);
check(file_exists($path0) && count(get_option(CJENIK_OPTION_LOG)) === 2, 'arhiva zamijenjena prije 14 dana još je dostupna');
$log = get_option(CJENIK_OPTION_LOG);
$log[0]['replaced'] -= 2 * DAY_IN_SECONDS;
$log[1]['timestamp'] -= 60 * DAY_IN_SECONDS;
update_option(CJENIK_OPTION_LOG, $log);
cjenik_auto_run(true);
$log = get_option(CJENIK_OPTION_LOG);
check(!file_exists($path0) && count($log) === 1 && strpos($log[0]['file'], '_0002_') !== false, 'arhiva briše se nakon roka, važeći ostaje');

// broj pohrane ne kreće ispočetka nakon brisanja arhive
file_put_contents($src, "Naziv robe,Bazna cijena\nKnjiga,\"13,00\"\n");
cjenik_auto_run(true);
$files = array_column(get_option(CJENIK_OPTION_LOG), 'file');
check(strpos(end($files), '_0003_') !== false, 'broj pohrane nastavlja se (0003)');

// ručno obrisana datoteka nestaje s popisa
unlink(trailingslashit(wp_upload_dir()['basedir']) . 'cjenici/' . $files[0]);
cjenik_auto_run(true);
check(!in_array($files[0], array_column(get_option(CJENIK_OPTION_LOG), 'file'), true), 'ručno obrisan cjenik nestaje s popisa');

// roba i usluge istog mjesta su dva odvojena cjenika
update_option(CJENIK_OPTION_FEEDS, "Knjige|$oib|ZA_SVA_PRODAJNA_MJESTA|ROBA|$src_url\nUsluge|$oib|ZA_SVA_PRODAJNA_MJESTA|USLUGA|$src_url");
[$errors, $n] = cjenik_auto_run(true);
check($n === 1 && count(array_unique(array_column(get_option(CJENIK_OPTION_LOG), 'key'))) === 2, 'roba i usluge: zasebni cjenici');

// dnevni način: isti cjenik objavljuje se ponovno svaki novi dan, ali ne dvaput isti dan
update_option(CJENIK_OPTION_DAILY, '1');
update_option(CJENIK_OPTION_FEEDS, "Knjige|$oib|ZA_SVA_PRODAJNA_MJESTA|ROBA|$src_url");
[$errors, $n] = cjenik_auto_run(true);
check($n === 0, 'dnevni način: danas već objavljen, nema nove objave');
$log = get_option(CJENIK_OPTION_LOG);
foreach ($log as &$e) {
    if (($e['key'] ?? '') === "{$oib}_ZA_SVA_PRODAJNA_MJESTA_ROBA" && empty($e['replaced'])) $e['timestamp'] -= DAY_IN_SECONDS;
}
unset($e);
update_option(CJENIK_OPTION_LOG, $log);
[$errors, $n] = cjenik_auto_run(true);
$files = array_column(get_option(CJENIK_OPTION_LOG), 'file');
check($errors === [] && $n === 1 && strpos(end($files), '_0004_') !== false, 'dnevni način: isti cjenik sljedeći dan objavljen kao 0004');
update_option(CJENIK_OPTION_DAILY, '0');

// stvarna Google tablica
update_option(CJENIK_OPTION_FEEDS, "Google|$oib|ZA_SVA_PRODAJNA_MJESTA|ROBA|$google_url");
[$errors, $n] = cjenik_auto_run(true);
check($errors === [] && $n === 1, 'stvarna Google tablica se objavljuje');

// pozdravni mail samo novim adresama, sa stanjem objave
delete_option(CJENIK_OPTION_EMAIL);
$mails = [];
update_option(CJENIK_OPTION_EMAIL, 'ja@primjer.hr');
check(count($mails) === 1 && $mails[0]['to'] === 'ja@primjer.hr' && strpos($mails[0]['message'], 'VAŽEĆI') !== false && strpos($mails[0]['message'], '/objava-cjenika/') !== false, 'nova adresa: pozdravni mail sa stanjem');
update_option(CJENIK_OPTION_EMAIL, 'ja@primjer.hr, ona@primjer.hr');
check(count($mails) === 2 && $mails[1]['to'] === 'ona@primjer.hr', 'dodana druga adresa: mail samo njoj');
update_option(CJENIK_OPTION_EMAIL, 'ona@primjer.hr');
check(count($mails) === 2, 'uklanjanje adrese: nema maila');
delete_option(CJENIK_OPTION_EMAIL);
check(!empty(get_option(CJENIK_OPTION_STATUS)['mail_ok']), 'uspješno slanje se bilježi');

// stranica ne može slati e-mail: to se mora zabilježiti da bi se prikazalo upozorenje
remove_all_filters('pre_wp_mail');
add_filter('pre_wp_mail', '__return_false');
update_option(CJENIK_OPTION_EMAIL, 'netko@primjer.hr');
$status = get_option(CJENIK_OPTION_STATUS);
check(!empty($status['mail_failed']) && $status['mail_failed'] >= $status['mail_ok'], 'neuspjelo slanje se bilježi');
delete_option(CJENIK_OPTION_EMAIL);
remove_all_filters('pre_wp_mail');

$html = cjenik_auto_render_list();
check(strpos($html, 'važeći') !== false && strpos($html, 'Cjenik roba – ') !== false && strpos($html, 'Cjenik usluga – ') !== false
    && strpos($html, 'Knjige') === false && strpos($html, '<h3') === false && strpos($html, 'Cjenik roba') < strpos($html, 'Cjenik usluga'),
    'popis: "Cjenik roba/usluga – datum", bez naslova, roba prva');

// dva prodajna mjesta s robom: tada se uz naziv dodaje naslov iz postavki
$saved = get_option(CJENIK_OPTION_LOG);
update_option(CJENIK_OPTION_LOG, [
    ['key' => "{$oib}_MJESTO_A_ROBA", 'label' => 'Trgovina A', 'file' => 'a.csv', 'url' => 'http://x/a.csv', 'hash' => 'a', 'timestamp' => time()],
    ['key' => "{$oib}_MJESTO_B_ROBA", 'label' => 'Trgovina B', 'file' => 'b.csv', 'url' => 'http://x/b.csv', 'hash' => 'b', 'timestamp' => time()],
]);
$html = cjenik_auto_render_list();
check(strpos($html, 'Cjenik roba, Trgovina A – ') !== false && strpos($html, 'Cjenik roba, Trgovina B – ') !== false, 'više mjesta s robom: naziv mjesta u linku');
update_option(CJENIK_OPTION_LOG, $saved);

unlink($src);
WP_CLI::success('Svi testovi prolaze');
