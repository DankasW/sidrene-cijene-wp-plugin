<?php
/**
 * Plugin Name: Cjenik Automatizacija (Sidrene cijene)
 * Description: Svaki dan u 6:00 povlači cjenik s Google Sheeta i objavljuje ga na /objava-cjenika (zakonski naziv datoteke, čuvanje 30 dana). Odgovorna osoba: Daniel Wurzberg, Udruga Fokus. Upute: README.md u mapi plugina.
 * Version: 1.6
 * Author: Daniel Wurzberg (Udruga Fokus)
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

define('CJENIK_OPTION_FEEDS', 'cjenik_auto_feeds');
define('CJENIK_OPTION_LOG', 'cjenik_auto_log');
define('CJENIK_SUBDIR', 'cjenici');
define('CJENIK_RETENTION_DAYS', 30);

// ---------- Cron ----------

register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('cjenik_auto_daily_event')) {
        // 05:55 lokalno, da ga cPanel cron u 06:00 sigurno zatekne kao "due"
        $first_run = (new DateTime('tomorrow 05:55', wp_timezone()))->getTimestamp();
        wp_schedule_event($first_run, 'daily', 'cjenik_auto_daily_event');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('cjenik_auto_daily_event');
});

add_action('cjenik_auto_daily_event', 'cjenik_auto_run');

// ---------- Čista logika ----------

function cjenik_auto_sanitize($str) {
    $map = ['č' => 'c', 'ć' => 'c', 'đ' => 'd', 'š' => 's', 'ž' => 'z',
            'Č' => 'C', 'Ć' => 'C', 'Đ' => 'D', 'Š' => 'S', 'Ž' => 'Z'];
    $str = strtr((string) $str, $map);
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $str));
}

function cjenik_auto_build_filename($business_type, $address, $location_code, $version, $date, $time) {
    return sprintf(
        '%s_%s_%s_%04d_%s_%s.csv',
        cjenik_auto_sanitize($business_type),
        cjenik_auto_sanitize($address),
        cjenik_auto_sanitize($location_code),
        (int) $version,
        $date,
        $time
    );
}

function cjenik_auto_parse_feeds($raw) {
    $feeds = [];
    foreach (explode("\n", (string) $raw) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) !== 5) continue;
        [$label, $business_type, $address, $location_code, $csv_url] = $parts;
        $feeds[] = compact('label', 'business_type', 'address', 'location_code', 'csv_url');
    }
    return $feeds;
}

// Google za privatne tablice zna vratiti HTML stranicu za prijavu umjesto CSV-a
function cjenik_auto_is_csv($body) {
    $body = ltrim($body, "\xEF\xBB\xBF \t\r\n");
    return $body !== '' && $body[0] !== '<';
}

// ---------- Glavni posao ----------

function cjenik_auto_upload_dir() {
    $upload = wp_upload_dir();
    $dir = trailingslashit($upload['basedir']) . CJENIK_SUBDIR;
    $url = trailingslashit($upload['baseurl']) . CJENIK_SUBDIR;
    if (!file_exists($dir)) wp_mkdir_p($dir);
    return [$dir, $url];
}

function cjenik_auto_run() {
    $feeds = cjenik_auto_parse_feeds(get_option(CJENIK_OPTION_FEEDS, ''));
    $errors = [];
    if (empty($feeds)) return ['Nema unesenih cjenika u postavkama.'];

    [$dir, $base_url] = cjenik_auto_upload_dir();
    $log = get_option(CJENIK_OPTION_LOG, []);
    $today = current_time('Ymd');
    $time = current_time('Hi');

    foreach ($feeds as $i => $feed) {
        $response = wp_remote_get($feed['csv_url'], ['timeout' => 30]);
        $code = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response);
        $body = is_wp_error($response) ? '' : wp_remote_retrieve_body($response);

        if ($code !== 200 || !cjenik_auto_is_csv($body)) {
            $errors[] = sprintf('%s: dohvat nije uspio (%s). Je li tablica dijeljena kao "Svatko s linkom"?', $feed['label'], $code);
            continue; // stari cjenici ostaju objavljeni
        }

        // BOM da Excel ispravno prikaže č, ć, š, ž, đ
        if (strncmp($body, "\xEF\xBB\xBF", 3) !== 0) $body = "\xEF\xBB\xBF" . $body;

        // Verzija ide po lokaciji, ne po retku, da se dva taba iste lokacije ne prepišu
        $prefix = implode('_', array_map('cjenik_auto_sanitize', [$feed['business_type'], $feed['address'], $feed['location_code']])) . '_';
        $version = 1 + count(array_filter($log, function ($e) use ($prefix, $today) {
            return $e['date'] === $today && strpos($e['file'], $prefix) === 0;
        }));

        $filename = cjenik_auto_build_filename(
            $feed['business_type'], $feed['address'], $feed['location_code'], $version, $today, $time
        );
        file_put_contents(trailingslashit($dir) . $filename, $body);

        $log[] = [
            'feed' => $i,
            'label' => $feed['label'],
            'file' => $filename,
            'url' => trailingslashit($base_url) . $filename,
            'date' => $today,
            'timestamp' => current_time('timestamp'),
        ];
    }

    $cutoff = current_time('timestamp') - (CJENIK_RETENTION_DAYS * DAY_IN_SECONDS);
    $kept = [];
    foreach ($log as $entry) {
        $path = trailingslashit($dir) . $entry['file'];
        if ($entry['timestamp'] < $cutoff) {
            if (file_exists($path)) unlink($path);
        } elseif (file_exists($path)) {
            $kept[] = $entry; // ručno obrisana datoteka nestaje i s popisa
        }
    }
    update_option(CJENIK_OPTION_LOG, $kept);

    file_put_contents(trailingslashit($dir) . 'index.html', sprintf(
        '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Objava cjenika – %1$s</title><style>body{font-family:system-ui,sans-serif;max-width:720px;margin:2rem auto;padding:0 16px;line-height:1.7;color:#222}a{color:#0a58ca}</style></head>'
        . '<body><h1>Objava cjenika</h1><p>Cjenici objavljeni u zadnjih 30 dana.</p>%2$s<p><a href="%3$s">← %1$s</a></p></body></html>',
        esc_html(get_bloginfo('name')),
        cjenik_auto_render_list(),
        esc_url(home_url('/'))
    ));

    return $errors;
}

// Tema ne ispisuje sadržaj stranica, pa na "objava-cjenika" prikazujemo popis koji plugin sam generira (adresa ostaje ista)
add_action('template_redirect', function () {
    if (!is_page('objava-cjenika')) return;
    // WP-Optimize i slični cache pluginovi inače poslužuju jučerašnji popis
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

// ---------- Popis svih cjenika zadnjih 30 dana, po lokaciji ----------

add_shortcode('cjenik_link', 'cjenik_auto_render_list');

function cjenik_auto_render_list() {
    $log = get_option(CJENIK_OPTION_LOG, []);
    if (empty($log)) return '<p>Cjenik još nije objavljen.</p>';

    $by_feed = [];
    foreach ($log as $entry) {
        $by_feed[$entry['feed']][] = $entry;
    }

    $out = '';
    foreach ($by_feed as $entries) {
        usort($entries, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        $out .= '<h3>' . esc_html($entries[0]['label']) . '</h3><ul class="cjenik-lista">';
        foreach ($entries as $entry) {
            $out .= sprintf(
                '<li><a href="%s">Cjenik – %s</a></li>',
                esc_url($entry['url']),
                esc_html(date_i18n('d.m.Y. H:i', $entry['timestamp']))
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
        $errors = cjenik_auto_run();
        if ($errors) {
            foreach ($errors as $e) echo '<div class="notice notice-error"><p>' . esc_html($e) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>Cjenik objavljen.</p></div>';
        }
    }

    $next = wp_next_scheduled('cjenik_auto_daily_event');
    ?>
    <div class="wrap">
        <h1>Cjenik Automatizacija</h1>
        <form method="post" action="options.php">
            <?php settings_fields('cjenik_auto_group'); ?>
            <p>Jedan cjenik po retku:<br>
                <code>Naziv lokacije|VRSTA_SUBJEKTA|ADRESA|SIFRA_LOKACIJE|CSV_LINK</code></p>
            <p>Primjer:<br>
                <code>Kutnjački put 11|UDRUGA|Kutnjački put 11|001|https://docs.google.com/spreadsheets/d/ID/export?format=csv</code></p>
            <textarea name="<?php echo esc_attr(CJENIK_OPTION_FEEDS); ?>" rows="6" cols="110"><?php
                echo esc_textarea(get_option(CJENIK_OPTION_FEEDS, ''));
            ?></textarea>
            <?php submit_button('Spremi postavke'); ?>
        </form>

        <form method="post">
            <?php wp_nonce_field('cjenik_auto_run_now_action'); ?>
            <?php submit_button('Objavi cjenik sada', 'secondary', 'cjenik_auto_run_now'); ?>
        </form>

        <p>Sljedeće automatsko objavljivanje:
            <strong><?php echo $next ? esc_html(wp_date('d.m.Y. H:i', $next)) : 'nije zakazano (deaktiviraj i aktiviraj plugin)'; ?></strong></p>

        <h2>Objavljeni cjenici (zadnjih 30 dana)</h2>
        <?php echo do_shortcode('[cjenik_link]'); ?>
    </div>
    <?php
}
