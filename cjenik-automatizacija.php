<?php
/**
 * Plugin Name: Sidrene cijene (objava cjenika)
 * Description: Jednom dnevno (od 6 sati, najkasnije do 11) provjerava cjenik u Google Sheetu i objavljuje ga na /objava-cjenika (svaki dan ili samo kad se promijeni, po izboru) prema Pravilniku o načinu objave cjenika proizvoda (NN 132/2026, https://narodne-novine.nn.hr/eli/sluzbeni/2026/132/1584/pdf), stanje propisa 16.11.2026. Upute: README.md u mapi plugina.
 * Version: 2.93
 * Author: Daniel Wurzberg (Udruga Fokus)
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

define('CJENIK_OPTION_FEEDS', 'cjenik_auto_feeds');
define('CJENIK_OPTION_LOG', 'cjenik_auto_log');
define('CJENIK_OPTION_COUNTERS', 'cjenik_auto_counters');
define('CJENIK_OPTION_STATUS', 'cjenik_auto_status');
define('CJENIK_OPTION_EMAIL', 'cjenik_auto_email');
define('CJENIK_OPTION_DAILY', 'cjenik_auto_daily'); // '1' = nova objava svaki dan i bez promjene (zadano)
define('CJENIK_SUBDIR', 'cjenici');
define('CJENIK_RETENTION_DAYS', 15); // pravilnik: arhivski cjenik dostupan 15 dana od zamjene
define('CJENIK_HOOK', 'cjenik_auto_daily_event');
define('CJENIK_OPTION_DONE', 'cjenik_auto_done');
// Objava jednom dnevno: prva uspješna provjera od FROM sati; ako ne uspije, ponavlja se svaki sat do UNTIL (rok iz pravilnika: 11 sati)
define('CJENIK_FROM_HOUR', 6);
define('CJENIK_UNTIL_HOUR', 11);

// ---------- Cron: svaki puni sat, a objava jednom dnevno i samo kad se cjenik promijeni ----------

function cjenik_auto_ensure_schedule() {
    $next = wp_next_scheduled(CJENIK_HOOK);
    if (wp_get_schedule(CJENIK_HOOK) !== 'hourly' || $next % HOUR_IN_SECONDS !== 0) {
        wp_clear_scheduled_hook(CJENIK_HOOK);
        // na puni sat, da ga serverski cron u 6:00 sigurno pokrene
        wp_schedule_event((int) ceil(time() / HOUR_IN_SECONDS) * HOUR_IN_SECONDS, 'hourly', CJENIK_HOOK);
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

// Treba li automatska provjera ovog cjenika sada: unutar dnevnog prozora i danas još nije uspjela.
// Promjene u tablici nakon današnje provjere čekaju sutrašnju objavu.
function cjenik_auto_due($hour, $today, $done) {
    return $hour >= CJENIK_FROM_HOUR && $hour < CJENIK_UNTIL_HOUR && $done !== $today;
}

// Jutarnja provjera danas nije pokrenuta (npr. nitko nije otvorio stranicu između FROM i UNTIL sati)
function cjenik_auto_missed($hour, $today, $done) {
    return $hour >= CJENIK_UNTIL_HOUR && $done !== $today;
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
    $header = false;
    while (($row = fgetcsv($in, 0, ',', '"', '')) !== false) {
        $row = array_map(fn($c) => trim(preg_replace('/\s*[\r\n]+\s*/', ' ', (string) $c)), $row);
        if (implode('', $row) === '') continue;
        // Naslov iznad tablice (npr. "CJENIK" u A1) se preskače: prvi red s barem dva stupca su naslovi stupaca.
        if (!$header && count(array_filter($row, 'strlen')) < 2) continue;
        $header = true;
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

// Vraća [greške, broj novih objava]. $manual = gumb "Provjeri i objavi sada": objavljuje odmah, bez dnevnog prozora.
function cjenik_auto_run($manual = false) {
    $feeds = cjenik_auto_parse_feeds(get_option(CJENIK_OPTION_FEEDS, ''));
    if (empty($feeds)) return [['Nema unesenih cjenika u postavkama.'], 0];

    [$dir, $base_url] = cjenik_auto_upload_dir();
    $log = get_option(CJENIK_OPTION_LOG, []);
    $counters = get_option(CJENIK_OPTION_COUNTERS, []);
    $now = current_time('timestamp');
    $errors = [];
    $published = 0;
    $done = get_option(CJENIK_OPTION_DONE, []);
    $today = current_time('Ymd');
    $hour = (int) current_time('G');
    $checked = 0;

    foreach ($feeds as $feed) {
        $key = $feed['error'] ? '' : cjenik_auto_key($feed['oib'], $feed['mjesto'], $feed['vrsta']);
        // Propuštena jutarnja provjera: samo usporedi tablicu i upozori ako ima neobjavljenih promjena, ne objavljuj
        // (tablica popodne već može imati cijene za sutra). Bez promjena stari cjenik i dalje vrijedi, pa nema upozorenja.
        $missed = !$manual && cjenik_auto_missed($hour, $today, $done[$key] ?? '');
        if (!$manual && !$missed && !cjenik_auto_due($hour, $today, $done[$key] ?? '')) continue;
        $checked++;
        if ($missed) $done[$key] = $today; // najviše jedna takva usporedba dnevno
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

        $done[$key] = $today; // današnja provjera uspjela, kasnije promjene idu u sutrašnju objavu
        $body = cjenik_auto_to_semicolon_csv($body);
        $hash = md5($body);

        $current = null;
        foreach ($log as $i => $e) {
            if (($e['key'] ?? '') === $key && empty($e['replaced'])) $current = $i;
        }
        if ($current !== null && $log[$current]['hash'] === $hash) {
            // timestampovi su current_time('timestamp'), pa gmdate daje lokalni datum
            $published_today = gmdate('Ymd', $log[$current]['timestamp']) === gmdate('Ymd', $now);
            $daily = get_option(CJENIK_OPTION_DAILY, '1') === '1';
            if (!$daily || $published_today) continue; // nema promjene i nije potrebna dnevna objava
            // dnevna objava istog sadržaja je sigurna i kad je jutarnja provjera propuštena
        } elseif ($missed) {
            $errors[] = sprintf(
                '%s: tablica ima neobjavljene promjene, a jutros između %d i %d sati provjera nije pokrenuta (stranicu nitko nije otvorio). '
                . 'Ako nove cijene vrijede već danas, kliknite "Provjeri i objavi sada" u postavkama. Inače se objavljuju sutra ujutro. '
                . 'Da se ovo ne ponavlja, postavite cron (upute u README.md).',
                $feed['label'], CJENIK_FROM_HOUR, CJENIK_UNTIL_HOUR
            );
            continue;
        }

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
    if (!$checked) return [[], 0]; // izvan prozora ili je danas već provjereno
    update_option(CJENIK_OPTION_DONE, $done);
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
        . '<body><h1>Objava cjenika</h1>%2$s<p><a href="%3$s">← %1$s</a></p></body></html>',
        esc_html(get_bloginfo('name')),
        cjenik_auto_render_list(),
        esc_url(home_url('/'))
    ));

    cjenik_auto_record_status($errors, $now);
    return [$errors, $published];
}

function cjenik_auto_sanitize_emails($raw) {
    $emails = array_filter(array_map('sanitize_email', explode(',', (string) $raw)), 'is_email');
    return implode(', ', $emails);
}

function cjenik_auto_alert_email() {
    return get_option(CJENIK_OPTION_EMAIL, '') ?: get_option('admin_email');
}

function cjenik_auto_status_text() {
    $status = get_option(CJENIK_OPTION_STATUS, []);
    $lines = [];
    foreach (get_option(CJENIK_OPTION_LOG, []) as $e) {
        $state = !isset($e['key']) ? 'stari format' : (empty($e['replaced']) ? 'VAŽEĆI' : 'zamijenjen ' . date_i18n('d.m.Y. H:i', $e['replaced']));
        $lines[] = sprintf("- %s, objavljen %s (%s)\n  %s", $e['label'], date_i18n('d.m.Y. H:i', $e['timestamp']), $state, $e['url']);
    }
    $text = "Stranica s cjenicima: " . home_url('/objava-cjenika/') . "\n\n"
        . ($lines ? "Objavljeni cjenici:\n" . implode("\n", $lines) : 'Još nema objavljenih cjenika.') . "\n\n"
        . 'Zadnja provjera bez grešaka: ' . (!empty($status['last_ok']) ? date_i18n('d.m.Y. H:i', $status['last_ok']) : 'još nije bilo');
    if (!empty($status['last_error']) && $status['last_error'] > ($status['last_ok'] ?? 0)) {
        $text .= "\nZadnja provjera NIJE uspjela (" . date_i18n('d.m.Y. H:i', $status['last_error']) . '): ' . $status['last_error_msg'];
    }
    return $text;
}

// Nova adresa za upozorenja dobije pozdravni mail sa stanjem objave, postojeće ne
function cjenik_auto_welcome_new_emails($old, $new) {
    $split = fn($s) => array_filter(array_map('trim', explode(',', (string) $s)));
    foreach (array_diff($split($new), $split($old)) as $to) {
        $ok = wp_mail(
            $to,
            sprintf('[%s] Upozorenja o objavi cjenika', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)),
            "Ova adresa dodana je za upozorenja plugina Sidrene cijene na " . home_url('/') . ".\n"
                . "Javit ćemo vam se ako se cjenik ne može provjeriti ili objaviti (najviše jednom dnevno za istu grešku).\n\n"
                . "Trenutno stanje:\n\n" . cjenik_auto_status_text()
        );
        $status = get_option(CJENIK_OPTION_STATUS, []);
        $status[$ok ? 'mail_ok' : 'mail_failed'] = current_time('timestamp');
        update_option(CJENIK_OPTION_STATUS, $status);
    }
}
add_action('update_option_' . CJENIK_OPTION_EMAIL, fn($old, $new) => cjenik_auto_welcome_new_emails($old, $new), 10, 2);
add_action('add_option_' . CJENIK_OPTION_EMAIL, fn($name, $value) => cjenik_auto_welcome_new_emails('', $value), 10, 2);

// Pamti zadnju uspješnu provjeru; za greške šalje e-mail adminu, ista greška najviše jednom dnevno
function cjenik_auto_record_status($errors, $now) {
    $status = get_option(CJENIK_OPTION_STATUS, []);
    $today = gmdate('Ymd', $now); // $now je current_time('timestamp'), već u lokalnom vremenu
    $mailed = array_filter($status['mailed'] ?? [], fn($day) => $day === $today);

    if (!$errors) {
        $status['last_ok'] = $now;
    } else {
        $status['last_error'] = $now;
        $status['last_error_msg'] = implode(' ', $errors);
        $new = array_values(array_filter($errors, fn($e) => !isset($mailed[md5($e)])));
        if ($new) {
            $ok = wp_mail(
                cjenik_auto_alert_email(),
                sprintf('[%s] Cjenik nije objavljen', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)),
                "Plugin Sidrene cijene nije mogao provjeriti ili objaviti cjenik:\n\n- " . implode("\n- ", $new)
                    . "\n\nDosadašnji cjenik ostaje objavljen. Provjera se ponavlja svaki sat do " . CJENIK_UNTIL_HOUR . " sati.\nPostavke: "
                    . admin_url('options-general.php?page=cjenik-auto')
                    . "\n\nOva poruka za istu grešku stiže najviše jednom dnevno."
            );
            $status[$ok ? 'mail_ok' : 'mail_failed'] = $now;
            foreach ($new as $e) $mailed[md5($e)] = $today;
        }
    }
    $status['mailed'] = $mailed;
    update_option(CJENIK_OPTION_STATUS, $status);
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
    add_options_page('Sidrene cijene', 'Sidrene cijene', 'manage_options', 'cjenik-auto', 'cjenik_auto_settings_page');
});

add_action('admin_init', function () {
    register_setting('cjenik_auto_group', CJENIK_OPTION_FEEDS, ['sanitize_callback' => 'sanitize_textarea_field']);
    register_setting('cjenik_auto_group', CJENIK_OPTION_EMAIL, ['sanitize_callback' => 'cjenik_auto_sanitize_emails']);
    register_setting('cjenik_auto_group', CJENIK_OPTION_DAILY, ['sanitize_callback' => fn($v) => $v ? '1' : '0']);
});

function cjenik_auto_settings_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['cjenik_auto_run_now']) && check_admin_referer('cjenik_auto_run_now_action')) {
        [$errors, $published] = cjenik_auto_run(true);
        foreach ($errors as $e) echo '<div class="notice notice-error"><p>' . esc_html($e) . '</p></div>';
        if (!$errors || $published) {
            $msg = $published ? sprintf('Objavljeno novih cjenika: %d.', $published)
                : (get_option(CJENIK_OPTION_DAILY, '1') === '1'
                    ? 'Ništa novo za objavu: cjenik se nije promijenio i danas je već objavljen.'
                    : 'Ništa novo za objavu: cjenik se nije promijenio (dnevna objava je isključena).');
            echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
        }
    }

    foreach (cjenik_auto_parse_feeds(get_option(CJENIK_OPTION_FEEDS, '')) as $f) {
        if ($f['error']) echo '<div class="notice notice-warning"><p>' . esc_html(sprintf('Redak %d nije ispravan: %s.', $f['line'], $f['error'])) . '</p></div>';
    }

    $next = wp_next_scheduled(CJENIK_HOOK);
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px"><img src="<?php echo esc_url(plugins_url('assets/logo.svg', __FILE__)); ?>" width="36" height="36" alt="">Sidrene cijene: objava cjenika
            <small style="font-size:13px;font-weight:normal;color:#646970">verzija <?php echo esc_html(get_file_data(__FILE__, ['v' => 'Version'])['v']); ?></small></h1>
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
            <p><label><input type="checkbox" name="<?php echo esc_attr(CJENIK_OPTION_DAILY); ?>" value="1" <?php checked(get_option(CJENIK_OPTION_DAILY, '1'), '1'); ?>>
                Objavi svaki dan, i kad se cjenik nije promijenio</label><br>
                <small>Bez kvačice nova datoteka objavljuje se samo kad se cjenik promijeni (pravilnik traži objavu izmjene do 11 sati).</small></p>
            <p><label>E-mail za upozorenja (više adresa odvojite zarezom, prazno = administrator stranice):<br>
                <input type="text" class="regular-text" name="<?php echo esc_attr(CJENIK_OPTION_EMAIL); ?>"
                    value="<?php echo esc_attr(get_option(CJENIK_OPTION_EMAIL, '')); ?>"
                    placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"></label></p>
            <?php submit_button('Spremi postavke'); ?>
        </form>

        <form method="post">
            <?php wp_nonce_field('cjenik_auto_run_now_action'); ?>
            <?php submit_button('Provjeri i objavi sada', 'secondary', 'cjenik_auto_run_now'); ?>
        </form>

        <p>Tablica se provjerava jednom dnevno u <?php echo (int) CJENIK_FROM_HOUR; ?> sati (ako ne uspije, ponovno svaki sat do <?php echo (int) CJENIK_UNTIL_HOUR; ?>),
            <?php echo get_option(CJENIK_OPTION_DAILY, '1') === '1' ? 'i cjenik se objavljuje svaki dan, i kad je isti kao jučer.' : 'a nova datoteka objavljuje se samo kad se cjenik promijeni.'; ?>
            Izmjene u tablici napravljene tijekom dana objavljuju se sljedećeg jutra.
            Gumb iznad objavljuje odmah. Sljedeće pokretanje: <strong><?php echo $next ? esc_html(wp_date('d.m.Y. H:i', $next)) : 'nije zakazana'; ?></strong></p>
        <?php $status = get_option(CJENIK_OPTION_STATUS, []); ?>
        <p>Zadnja provjera bez grešaka:
            <strong><?php echo !empty($status['last_ok']) ? esc_html(date_i18n('d.m.Y. H:i', $status['last_ok'])) : 'još nije bilo'; ?></strong>
            <?php if (!empty($status['last_error']) && $status['last_error'] > ($status['last_ok'] ?? 0)): ?>
                <br><span style="color:#b32d2e">Zadnja provjera nije uspjela (<?php echo esc_html(date_i18n('d.m.Y. H:i', $status['last_error'])); ?>): <?php echo esc_html($status['last_error_msg']); ?></span>
            <?php endif; ?>
            <br><small>Upozorenja o greškama stižu na: <?php echo esc_html(cjenik_auto_alert_email()); ?>, najviše jednom dnevno za istu grešku.</small>
            <?php if (!empty($status['mail_failed']) && $status['mail_failed'] > ($status['mail_ok'] ?? 0)): ?>
                <br><span style="color:#b32d2e"><strong>Zadnji e-mail nije poslan</strong> (<?php echo esc_html(date_i18n('d.m.Y. H:i', $status['mail_failed'])); ?>).
                Stranica trenutno ne može slati e-mail, pa upozorenja ne stižu. Provjerite postavke slanja e-maila (npr. plugin WP Mail SMTP).</span>
            <?php endif; ?></p>

        <h2>Objavljeni cjenici</h2>
        <?php echo cjenik_auto_render_list(); ?>
    </div>
    <?php
}
