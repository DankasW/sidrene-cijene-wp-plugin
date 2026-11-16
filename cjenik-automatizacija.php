<?php
/**
 * Plugin Name: Cjenik Automatizacija (bazne cijene)
 * Description: Svaki sat provjerava cjenik u Google Sheetu i kad se promijeni objavljuje ga na /objava-cjenika prema Pravilniku o načinu objave cjenika proizvoda (NN 132/2026, https://narodne-novine.nn.hr/eli/sluzbeni/2026/132/1584/pdf), stanje propisa 16.11.2026. Upute: README.md u mapi plugina.
 * Version: 2.0
 * Author: Daniel Wurzberg (Udruga Fokus)
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

define('CJENIK_OPTION_FEEDS', 'cjenik_auto_feeds');
define('CJENIK_OPTION_LOG', 'cjenik_auto_log');
define('CJENIK_OPTION_COUNTERS', 'cjenik_auto_counters');
define('CJENIK_SUBDIR', 'cjenici');
define('CJENIK_RETENTION_DAYS', 30); // pravilnik traži najmanje 15 dana od zamjene
define('CJENIK_HOOK', 'cjenik_auto_daily_event');

// ---------- Cron: provjera svaki sat, objava samo kad se cjenik promijeni ----------

function cjenik_auto_ensure_schedule() {
    if (wp_get_schedule(CJENIK_HOOK) !== 'hourly') {
        wp_clear_scheduled_hook(CJENIK_HOOK);
        wp_schedule_event(time(), 'hourly', CJENIK_HOOK);
    }
}
register_activation_hook(__FILE__, 'cjenik_auto_ensure_schedule');
add_action('init', 'cjenik_auto_ensure_schedule');

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook(CJENIK_HOOK);
});

add_action(CJENIK_HOOK, 'cjenik_auto_run');

// ---------- Čista logika ----------

function cjenik_auto_sanitize($str) {
    $map = ['č' => 'c', 'ć' => 'c', 'đ' => 'd', 'š' => 's', 'ž' => 'z',
            'Č' => 'C', 'Ć' => 'C', 'Đ' => 'D', 'Š' => 'S', 'Ž' => 'Z'];
    $str = strtoupper(strtr(trim((string) $str), $map));
    $str = preg_replace('/[\s\-\/]+/', '_', $str);
    $str = preg_replace('/[^A-Z0-9_]/', '', $str);
    return trim(preg_replace('/_+/', '_', $str), '_');
}

// Dio naziva datoteke bez broja pohrane i vremena, npr. 12345678901_ZA_SVA_PRODAJNA_MJESTA_ROBA
function cjenik_auto_key($oib, $mjesto, $vrsta) {
    return implode('_', array_map('cjenik_auto_sanitize', [$oib, $mjesto, $vrsta]));
}

function cjenik_auto_build_filename($key, $broj, $date, $time) {
    return sprintf('%s_%04d_%s_%s.csv', $key, (int) $broj, $date, $time);
}

function cjenik_auto_parse_feeds($raw) {
    $feeds = [];
    foreach (explode("\n", (string) $raw) as $n => $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = array_map('trim', explode('|', $line));
        $feed = ['line' => $n + 1, 'error' => ''];
        if (count($parts) !== 5) {
            $feed['error'] = 'treba 5 dijelova odvojenih znakom |';
        } else {
            [$feed['label'], $feed['oib'], $feed['mjesto'], $vrsta, $feed['csv_url']] = $parts;
            $feed['vrsta'] = strtoupper($vrsta);
            if (!preg_match('/^\d{7,13}$/', $feed['oib'])) $feed['error'] = 'drugi dio mora biti OIB (ili matični broj), samo znamenke';
            elseif (!in_array($feed['vrsta'], ['ROBA', 'USLUGA'], true)) $feed['error'] = 'četvrti dio mora biti ROBA ili USLUGA';
        }
        $feeds[] = $feed;
    }
    return $feeds;
}

// Google za privatne tablice zna vratiti HTML stranicu za prijavu umjesto CSV-a
function cjenik_auto_is_csv($body) {
    $body = ltrim($body, "\xEF\xBB\xBF \t\r\n");
    return $body !== '' && $body[0] !== '<';
}

// Pravilnik: UTF-8, stupci odvojeni točkom-zarezom. Prijelomi reda unutar ćelija se uklanjaju.
function cjenik_auto_to_semicolon_csv($body) {
    $in = fopen('php://temp', 'r+');
    fwrite($in, preg_replace('/^\xEF\xBB\xBF/', '', $body));
    rewind($in);
    $out = fopen('php://temp', 'r+');
    while (($row = fgetcsv($in, 0, ',', '"', '')) !== false) {
        $row = array_map(fn($c) => trim(preg_replace('/\s*[\r\n]+\s*/', ' ', (string) $c)), $row);
        if (implode('', $row) === '') continue;
        fputcsv($out, $row, ';', '"', '', "\n");
    }
    rewind($out);
    // BOM da Excel ispravno prikaže č, ć, š, ž, đ
    return "\xEF\xBB\xBF" . stream_get_contents($out);
}

// ---------- Glavni posao ----------

function cjenik_auto_upload_dir() {
    $upload = wp_upload_dir();
    $dir = trailingslashit($upload['basedir']) . CJENIK_SUBDIR;
    $url = trailingslashit($upload['baseurl']) . CJENIK_SUBDIR;
    if (!file_exists($dir)) wp_mkdir_p($dir);
    return [$dir, $url];
}

// Vraća [greške, broj novih objava]
function cjenik_auto_run() {
    $feeds = cjenik_auto_parse_feeds(get_option(CJENIK_OPTION_FEEDS, ''));
    if (empty($feeds)) return [['Nema unesenih cjenika u postavkama.'], 0];

    [$dir, $base_url] = cjenik_auto_upload_dir();
    $log = get_option(CJENIK_OPTION_LOG, []);
    $counters = get_option(CJENIK_OPTION_COUNTERS, []);
    $now = current_time('timestamp');
    $errors = [];
    $published = 0;

    foreach ($feeds as $feed) {
        if ($feed['error']) {
            $errors[] = sprintf('Redak %d u postavkama nije ispravan: %s. Ništa nije objavljeno.', $feed['line'], $feed['error']);
            continue;
        }

        $response = wp_remote_get($feed['csv_url'], ['timeout' => 30]);
        $code = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response);
        $body = is_wp_error($response) ? '' : wp_remote_retrieve_body($response);
        if ($code !== 200 || !cjenik_auto_is_csv($body)) {
            $errors[] = sprintf('%s: dohvat nije uspio (%s). Je li tablica dijeljena kao "Anyone with the link"?', $feed['label'], $code);
            continue; // dosadašnji cjenik ostaje važeći
        }

        $body = cjenik_auto_to_semicolon_csv($body);
        $hash = md5($body);
        $key = cjenik_auto_key($feed['oib'], $feed['mjesto'], $feed['vrsta']);

        $current = null;
        foreach ($log as $i => $e) {
            if (($e['key'] ?? '') === $key && empty($e['replaced'])) $current = $i;
        }
        if ($current !== null && $log[$current]['hash'] === $hash) continue; // nema promjene, nema nove objave

        $broj = ($counters[$key] ?? 0) + 1;
        $counters[$key] = $broj;
        $filename = cjenik_auto_build_filename($key, $broj, current_time('Ymd'), current_time('Hi'));
        file_put_contents(trailingslashit($dir) . $filename, $body);

        if ($current !== null) $log[$current]['replaced'] = $now;
        $log[] = [
            'key' => $key,
            'label' => $feed['label'],
            'file' => $filename,
            'url' => trailingslashit($base_url) . $filename,
            'hash' => $hash,
            'timestamp' => $now,
        ];
        $published++;
    }
    update_option(CJENIK_OPTION_COUNTERS, $counters);

    // Važeći cjenik ostaje uvijek, zamijenjeni (i stari zapisi bez ključa) CJENIK_RETENTION_DAYS dana
    $cutoff = $now - (CJENIK_RETENTION_DAYS * DAY_IN_SECONDS);
    $kept = [];
    foreach ($log as $entry) {
        $path = trailingslashit($dir) . $entry['file'];
        $since = isset($entry['key']) ? ($entry['replaced'] ?? null) : $entry['timestamp'];
        if ($since !== null && $since < $cutoff) {
            if (file_exists($path)) unlink($path);
        } elseif (file_exists($path)) {
            $kept[] = $entry; // ručno obrisana datoteka nestaje i s popisa
        }
    }
    update_option(CJENIK_OPTION_LOG, $kept);

    file_put_contents(trailingslashit($dir) . 'index.html', sprintf(
        '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Objava cjenika – %1$s</title><style>body{font-family:system-ui,sans-serif;max-width:720px;margin:2rem auto;padding:0 16px;line-height:1.7;color:#222}a{color:#0a58ca}small{color:#666}</style></head>'
        . '<body><h1>Objava cjenika</h1><p>Važeći cjenici i arhiva zamijenjenih cjenika (CSV, UTF-8, stupci odvojeni znakom ;).</p>%2$s<p><a href="%3$s">← %1$s</a></p></body></html>',
        esc_html(get_bloginfo('name')),
        cjenik_auto_render_list(),
        esc_url(home_url('/'))
    ));

    return [$errors, $published];
}

// Tema ne ispisuje sadržaj stranica, pa na "objava-cjenika" prikazujemo popis koji plugin sam generira (adresa ostaje ista)
add_action('template_redirect', function () {
    if (!is_page('objava-cjenika')) return;
    // WP-Optimize i slični cache pluginovi inače poslužuju stari popis
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    nocache_headers();
    $index = trailingslashit(wp_upload_dir()['basedir']) . CJENIK_SUBDIR . '/index.html';
    header('Content-Type: text/html; charset=utf-8');
    if (file_exists($index)) {
        readfile($index);
    } else {
        echo '<!doctype html><meta charset="utf-8"><p>Cjenik još nije objavljen.</p>';
    }
    exit;
});

// ---------- Popis: važeći cjenik + arhiva, po cjeniku ----------

add_shortcode('cjenik_link', 'cjenik_auto_render_list');

function cjenik_auto_render_list() {
    $log = get_option(CJENIK_OPTION_LOG, []);
    if (empty($log)) return '<p>Cjenik još nije objavljen.</p>';

    $groups = [];
    foreach ($log as $entry) {
        $groups[$entry['key'] ?? 'stari:' . ($entry['feed'] ?? 0)][] = $entry;
    }
    uasort($groups, fn($a, $b) => max(array_column($b, 'timestamp')) <=> max(array_column($a, 'timestamp')));

    $out = '';
    foreach ($groups as $entries) {
        usort($entries, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        $out .= '<h3>' . esc_html($entries[0]['label']) . '</h3><ul class="cjenik-lista">';
        foreach ($entries as $entry) {
            if (!isset($entry['key'])) {
                $note = 'objavljen prije 17.11.2026.';
            } elseif (empty($entry['replaced'])) {
                $note = '<strong>važeći</strong>';
            } else {
                $note = 'zamijenjen ' . esc_html(date_i18n('d.m.Y. H:i', $entry['replaced']));
            }
            $out .= sprintf(
                '<li><a href="%s">Cjenik – %s</a> <small>(%s)</small></li>',
                esc_url($entry['url']),
                esc_html(date_i18n('d.m.Y. H:i', $entry['timestamp'])),
                $note
            );
        }
        $out .= '</ul>';
    }
    return $out;
}

// ---------- Admin ----------

add_action('admin_menu', function () {
    add_options_page('Cjenik Automatizacija', 'Cjenik Automatizacija', 'manage_options', 'cjenik-auto', 'cjenik_auto_settings_page');
});

add_action('admin_init', function () {
    register_setting('cjenik_auto_group', CJENIK_OPTION_FEEDS, ['sanitize_callback' => 'sanitize_textarea_field']);
});

function cjenik_auto_settings_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['cjenik_auto_run_now']) && check_admin_referer('cjenik_auto_run_now_action')) {
        [$errors, $published] = cjenik_auto_run();
        foreach ($errors as $e) echo '<div class="notice notice-error"><p>' . esc_html($e) . '</p></div>';
        if (!$errors || $published) {
            $msg = $published ? sprintf('Objavljeno novih cjenika: %d.', $published) : 'Cjenik se nije promijenio, nova objava nije potrebna.';
            echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
        }
    }

    foreach (cjenik_auto_parse_feeds(get_option(CJENIK_OPTION_FEEDS, '')) as $f) {
        if ($f['error']) echo '<div class="notice notice-warning"><p>' . esc_html(sprintf('Redak %d nije ispravan: %s.', $f['line'], $f['error'])) . '</p></div>';
    }

    $next = wp_next_scheduled(CJENIK_HOOK);
    ?>
    <div class="wrap">
        <h1>Cjenik Automatizacija</h1>
        <form method="post" action="options.php">
            <?php settings_fields('cjenik_auto_group'); ?>
            <p>Jedan cjenik po retku:<br>
                <code>Naslov na stranici|OIB|PRODAJNO MJESTO|ROBA ili USLUGA|CSV_LINK</code></p>
            <p>PRODAJNO MJESTO je vrsta, adresa i oznaka prodajnog mjesta (npr. <code>Prodavaonica Kutnjački put 11 PM1</code>),
                ili <code>ZA_SVA_PRODAJNA_MJESTA</code> ako je cjenik isti na svim mjestima.</p>
            <p>Primjer:<br>
                <code>Knjige i priručnici|12345678901|ZA_SVA_PRODAJNA_MJESTA|ROBA|https://docs.google.com/spreadsheets/d/ID/export?format=csv</code></p>
            <textarea name="<?php echo esc_attr(CJENIK_OPTION_FEEDS); ?>" rows="6" cols="110"><?php
                echo esc_textarea(get_option(CJENIK_OPTION_FEEDS, ''));
            ?></textarea>
            <?php submit_button('Spremi postavke'); ?>
        </form>

        <form method="post">
            <?php wp_nonce_field('cjenik_auto_run_now_action'); ?>
            <?php submit_button('Provjeri i objavi sada', 'secondary', 'cjenik_auto_run_now'); ?>
        </form>

        <p>Tablica se provjerava svaki sat, a nova datoteka objavljuje se samo kad se cjenik promijeni.
            Sljedeća provjera: <strong><?php echo $next ? esc_html(wp_date('d.m.Y. H:i', $next)) : 'nije zakazana'; ?></strong></p>

        <h2>Objavljeni cjenici</h2>
        <?php echo cjenik_auto_render_list(); ?>
    </div>
    <?php
}
