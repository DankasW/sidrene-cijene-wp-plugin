<?php
// Pokretanje: wp eval-file test-cjenik.php <ispravan_csv_url> <privatni_url>
// Briše postojeći log cjenika, koristiti samo lokalno.

function check($cond, $msg) {
    if (!$cond) { WP_CLI::error("FAIL: $msg"); }
    WP_CLI::log("ok - $msg");
}

[$good_url, $bad_url] = $args;

check(cjenik_auto_build_filename('Obrt', 'Matije Gupca 10a', '001', 1, '20261001', '0750') === 'OBRT_MATIJEGUPCA10A_001_0001_20261001_0750.csv', 'naziv kao u primjeru iz vodiča');
check(cjenik_auto_build_filename('Udruga', 'Kutnjački put 11', '001', 12, '20261117', '0600') === 'UDRUGA_KUTNJACKIPUT11_001_0012_20261117_0600.csv', 'dijakritike i verzija');
check(count(cjenik_auto_parse_feeds("# x\n\nA|B|C|D|E\nlose|dva")) === 1, 'parsiranje postavki');
check(!cjenik_auto_is_csv("<!DOCTYPE html><html>"), 'HTML login stranica se odbija');

delete_option(CJENIK_OPTION_LOG);

update_option(CJENIK_OPTION_FEEDS, "Privatna|UDRUGA|Kutnjački put 11|002|$bad_url");
$errors = cjenik_auto_run();
check(count($errors) === 1 && empty(get_option(CJENIK_OPTION_LOG)), 'privatna tablica: greška, ništa objavljeno');

update_option(CJENIK_OPTION_FEEDS, "Kutnjački put 11|UDRUGA|Kutnjački put 11|001|$good_url");
check(cjenik_auto_run() === [], 'prvo objavljivanje bez greške');
cjenik_auto_run();
$log = get_option(CJENIK_OPTION_LOG);
check(count($log) === 2 && substr($log[1]['file'], 0, 31) === 'UDRUGA_KUTNJACKIPUT11_001_0002_', 'druga objava istog dana = verzija 0002');

$path = trailingslashit(wp_upload_dir()['basedir']) . 'cjenici/' . $log[0]['file'];
$body = file_get_contents($path);
check(strncmp($body, "\xEF\xBB\xBF", 3) === 0 && substr_count($body, "\xEF\xBB\xBF") === 1, 'datoteka ima točno jedan UTF-8 BOM');
check(wp_remote_retrieve_response_code(wp_remote_get($log[0]['url'])) === 200, 'datoteka je javno dostupna na webu');

// star zapis (31 dan) mora nestati zajedno s datotekom
$log[0]['timestamp'] -= 31 * DAY_IN_SECONDS;
update_option(CJENIK_OPTION_LOG, $log);
cjenik_auto_run();
check(!file_exists($path) && count(get_option(CJENIK_OPTION_LOG)) === 2, 'cjenik stariji od 30 dana obrisan');

// ručno obrisana datoteka mora nestati s popisa kod sljedeće objave
$log = get_option(CJENIK_OPTION_LOG);
unlink(trailingslashit(wp_upload_dir()['basedir']) . 'cjenici/' . $log[0]['file']);
cjenik_auto_run();
$files = array_column(get_option(CJENIK_OPTION_LOG), 'file');
check(!in_array($log[0]['file'], $files, true) && count($files) === 2, 'ručno obrisan cjenik nestaje s popisa');

// dva taba iste lokacije u istoj minuti ne smiju dobiti isto ime datoteke
delete_option(CJENIK_OPTION_LOG);
update_option(CJENIK_OPTION_FEEDS, "Proizvodi|UDRUGA|Kutnjački put 11|001|$good_url\nUsluge|UDRUGA|Kutnjački put 11|001|$good_url");
cjenik_auto_run();
$files = array_column(get_option(CJENIK_OPTION_LOG), 'file');
check(count(array_unique($files)) === 2 && strpos($files[1], '_001_0002_') !== false, 'dva taba iste lokacije: verzije 0001 i 0002, bez prepisivanja');
delete_option(CJENIK_OPTION_LOG);
update_option(CJENIK_OPTION_FEEDS, "Kutnjački put 11|UDRUGA|Kutnjački put 11|001|$good_url");
cjenik_auto_run();
cjenik_auto_run();

$html = do_shortcode('[cjenik_link]');
check(substr_count($html, '<li>') === 2 && strpos($html, 'Kutnjački put 11') !== false, 'shortcode ispisuje sve linkove');

WP_CLI::success('Svi testovi prolaze');
