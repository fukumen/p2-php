<?php
/**
 * rep2expack - dump post cookies per BBS
 *
 * post.php が書き込み時に送信する保存クッキー（p2_cookies.sqlite3）を、
 * BBS（正規化後ホスト＋ユーザー）ごとに一覧表示する読み取り専用ツール。
 * 表示には CookieDataStore::get() を使う。期限切れ除去やレコード削除という
 * 副作用を持つ loadActive() は使用しない。
 */

// {{{ 初期設定

if (PHP_SAPI != 'cli') {
    die('CLI only!');
}

define('P2_CLI_RUN', 1);

require __DIR__ . '/../init.php';

// }}}
// {{{ コマンドライン引数を取得

$getopt = new Console_Getopt();
$args = $getopt->readPHPArgv();
if (PEAR::isError($args)) {
    fwrite(STDERR, $args->getMessage() . PHP_EOL);
    exit(1);
}
array_shift($args);

$short_options = 'ah:';
$long_options = array('all', 'host=');
$options = $getopt->getopt2($args, $short_options, $long_options);
if (PEAR::isError($options)) {
    fwrite(STDERR, $options->getMessage() . PHP_EOL);
    exit(1);
}

$show_all = false;
$host_filter = null;

foreach ($options[0] as $option) {
    switch ($option[0]) {
    case 'a':
    case '--all':
        $show_all = true;
        break;
    case 'h':
    case '--host':
        $host_filter = $option[1];
        break;
    }
}

// }}}
// {{{ メイン

$now = time();
$tz_jst = new DateTimeZone('Asia/Tokyo');

// host => user => 行の配列 にブロック化してから出力する
$blocks = array();
$cookie_count = 0;

$kvs = CookieDataStore::getKVS();

foreach ($kvs->getKeys() as $key) {
    $user_host = explode('/', $key, 2);
    if (count($user_host) != 2) {
        continue;
    }
    list($user, $host) = $user_host;

    if ($host_filter !== null && strcasecmp($host, $host_filter) != 0) {
        continue;
    }

    // 読み取り専用。loadActive() は再保存・レコード削除の副作用があるため使わない
    $data = CookieDataStore::get($key);

    if (!is_array($data)) {
        if (!$show_all) {
            continue;
        }
        $blocks[$host][$user][] = '  (broken record: value is not an array)  [BROKEN]';
        $cookie_count++;
        continue;
    }

    // 有効クッキーが一つもないレコードもブロックごと表示する（(none) 用）
    $blocks[$host][$user] = array();

    foreach ($data as $name => $c) {
        // broken/expired は post.php が送信しない項目 (loadActive() の除去基準と同義)
        if (!is_array($c) || !isset($c['value'])) {
            $status = 'broken';
        } else {
            $expires = p2_dump_cookies_normalize_expires($c['expires'] ?? null);
            if ($expires === false) {
                $status = 'broken';
            } elseif ($expires !== null && $now >= $expires) {
                $status = 'expired';
            } else {
                $status = 'active';
            }
        }
        if (!$show_all && $status != 'active') {
            continue;
        }

        if ($status == 'broken') {
            // 壊れた項目も値が読める場合は併記する
            if (is_array($c) && isset($c['value'])) {
                $line = sprintf('  %-6s  %s  expires (invalid)  [BROKEN]', $name, strval($c['value']));
            } elseif (is_string($c)) {
                $line = sprintf('  %-6s  %s  (legacy)  [BROKEN]', $name, $c);
            } else {
                $line = sprintf('  %-6s  (no value)  [BROKEN]', $name);
            }
            $blocks[$host][$user][] = $line;
            $cookie_count++;
            continue;
        }

        $value = strval($c['value']);

        if ($expires === null) {
            $expires_text = 'expires (session cookie)';
            $suffix = '';
        } else {
            $dt = new DateTime('@' . $expires);
            $dt->setTimezone($tz_jst);
            if ($expires > $now) {
                $expires_text = 'expires ' . $dt->format('Y-m-d H:i:s') . ' JST';
                $suffix = '(remain ' . p2_dump_cookies_format_remain($expires - $now) . ')';
            } else {
                $expires_text = 'expired ' . $dt->format('Y-m-d H:i:s') . ' JST';
                $suffix = '[EXPIRED]';
            }
        }

        $line = sprintf('  %-6s  %s  %s', $name, $value, $expires_text);
        if ($suffix !== '') {
            $line .= '  ' . $suffix;
        }
        $blocks[$host][$user][] = $line;
        $cookie_count++;
    }
}

if (!$blocks) {
    if ($host_filter !== null) {
        fwrite(STDOUT, sprintf("No cookies found for host '%s'." . PHP_EOL, $host_filter));
    } else {
        fwrite(STDOUT, 'No cookies found.' . PHP_EOL);
    }
    exit(0);
}

ksort($blocks);
$bbs_count = 0;
foreach ($blocks as $users) {
    $bbs_count += count($users);
}

$summary_word = $show_all ? 'record(s)' : 'cookie(s)';
fwrite(STDOUT, sprintf('%d BBS(es), %d %s found.' . PHP_EOL, $bbs_count, $cookie_count, $summary_word));

foreach ($blocks as $host => $users) {
    ksort($users);
    foreach ($users as $user => $lines) {
        fwrite(STDOUT, PHP_EOL);
        fwrite(STDOUT, sprintf('%s  (%s)  user=%s' . PHP_EOL,
                               $host, p2_dump_cookies_classify_host($host), $user));
        if ($lines) {
            foreach ($lines as $line) {
                fwrite(STDOUT, $line . PHP_EOL);
            }
        } else {
            fwrite(STDOUT, '  (none)' . PHP_EOL);
        }
    }
}

exit(0);

// }}}
// {{{ p2_dump_cookies_classify_host()

/**
 * 正規化済みホスト名を参考分類に変換する（装飾用、ASCII 限定）
 *
 * @param string $host
 * @return string
 */
function p2_dump_cookies_classify_host($host)
{
    if (P2HostMgr::isHostBbsPink($host)) {
        return 'BBSPink';
    }
    if (P2HostMgr::isHost2chs($host)) {
        return '5ch';
    }
    if (P2HostMgr::isHostJbbsShitaraba($host)) {
        return 'shitaraba';
    }
    if (P2HostMgr::isHostMachiBbs($host)) {
        return 'machiBBS';
    }
    if (P2HostMgr::isHostTalk($host)) {
        return 'talk';
    }
    return 'other';
}

// }}}
// {{{ p2_dump_cookies_normalize_expires()

/**
 * expires 値を Unix timestamp へ正規化する
 *
 * @param mixed $expires
 * @return int|null|false int: timestamp / null: 未指定(セッション) / false: 解釈不能
 */
function p2_dump_cookies_normalize_expires($expires)
{
    if ($expires === null || $expires === '' || $expires === false) {
        return null;
    }
    if (is_numeric($expires)) {
        return (int)$expires;
    }
    if (is_string($expires)) {
        $ts = strtotime($expires);
        if ($ts !== false) {
            return $ts;
        }
    }
    return false;
}

// }}}
// {{{ p2_dump_cookies_format_remain()

/**
 * 残り秒数を「9d 3h」形式に整形する
 *
 * @param int $seconds
 * @return string
 */
function p2_dump_cookies_format_remain($seconds)
{
    $days = (int)floor($seconds / 86400);
    $hours = (int)floor(($seconds % 86400) / 3600);
    if ($days > 0) {
        return sprintf('%dd %dh', $days, $hours);
    }
    $minutes = (int)floor(($seconds % 3600) / 60);
    if ($hours > 0) {
        return sprintf('%dh %dm', $hours, $minutes);
    }
    return sprintf('%dm', $minutes);
}

// }}}

/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:
