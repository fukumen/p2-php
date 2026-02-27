<?php
/**
 * rep2 - タイトルページ
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

//=========================================================
// 変数
//=========================================================

if (!empty($GLOBALS['pref_dir_realpath_failed_msg'])) {
    P2Util::pushInfoHtml('<p>' . $GLOBALS['pref_dir_realpath_failed_msg'] . '</p>');
}

$p2web_url_r = P2Util::throughIme($_conf['p2web_url']);
$expack_url_r = P2Util::throughIme($_conf['expack.web_url']);
$expack_dl_url_r = P2Util::throughIme($_conf['expack.download_url']);
$expack_hist_url_r = P2Util::throughIme($_conf['expack.history_url']);

// rskさんのサイトへのリンクを表示するため by 2ch774
$rsk_expack_url = "http://rsky.github.io/p2-php/";
$rsk_expack_url_r = P2Util::throughIme($rsk_expack_url);

// {{{ データ保存ディレクトリのパーミッションの注意を喚起する

P2Util::checkDirWritable($_conf['dat_dir']);
$checked_dirs[] = $_conf['dat_dir']; // チェック済みのディレクトリを格納する配列に

// まだチェックしていなければ
if (!in_array($_conf['idx_dir'], $checked_dirs)) {
    P2Util::checkDirWritable($_conf['idx_dir']);
    $checked_dirs[] = $_conf['idx_dir'];
}
if (!in_array($_conf['pref_dir'], $checked_dirs)) {
    P2Util::checkDirWritable($_conf['pref_dir']);
    $checked_dirs[] = $_conf['pref_dir'];
}

// }}}

//=========================================================
// 前処理
//=========================================================
// ●ID 2ch オートログイン
if ($array = P2Util::readIdPw2ch()) {
    list($login2chID, $login2chPW, $autoLogin2ch, $methodLogin2ch) = $array;
    if ($autoLogin2ch) {
        require_once P2_LIB_DIR . '/login2ch.inc.php';
        login2ch();
    }
}

//=========================================================
// プリント設定
//=========================================================
// 最新版チェック
$newversion_found = '';
if (!empty($_conf['updatan_haahaa'])) {
    $newversion_found = checkUpdatan();
}

//=========================================================
// github actionの情報表示
//=========================================================
$ver_str = array();
foreach (array('VER_REPO_HASH', 'VER_REPO_LOG', 'VER_REP2_HASH', 'VER_REP2_LOG', 'VER_RUN_ID', 'VER_RUN_NUMBER') as $key) {
    if (($val = getenv($key)) !== false) {
        $ver_str[$key] = $val;
    }
}

if (count($ver_str) == 6) {
    $newversion_found2 = '';
    if (!empty($_conf['updatan_haahaa'])) {
        $newversion_found2 = checkUpdatan2($ver_str['VER_RUN_ID']);
    }

    $ver_str['VER_REP2_LOG'] = mb_convert_encoding(base64_decode($ver_str['VER_REP2_LOG']), 'CP932', 'UTF-8');
    $ver_str['VER_REPO_LOG'] = mb_convert_encoding(base64_decode($ver_str['VER_REPO_LOG']), 'CP932', 'UTF-8');
    $htm['ver_str'] = <<<EOT
<table border="0" cellspacing="0" cellpadding="1">
    <caption>ビルド情報</caption>
    <tbody>
        <tr><th>p2-php:</th><td>{$ver_str['VER_REP2_LOG']}&nbsp;{$ver_str['VER_REP2_HASH']}</td></tr>
        <tr><th>docker-rep2:</th><td>{$ver_str['VER_REPO_LOG']}&nbsp;{$ver_str['VER_REPO_HASH']}</td></tr>
        <tr><th>github action:</th><td>run_id:{$ver_str['VER_RUN_ID']}&nbsp;run_number:{$ver_str['VER_RUN_NUMBER']}</td></tr>
    </tbody>
</table>
    {$newversion_found2}
EOT;
} else {
    $htm['ver_str'] = '';
}

// ログインユーザ情報
$htm['auth_user'] = "<p>ログインユーザ: {$_login->user_u} - " . date("Y/m/d (D) G:i") . "</p>\n";

// （携帯）ログイン用URL
$base_url = rtrim(dirname(P2Util::getMyUrl()), '/') . '/';
$url_b = $base_url . '?user=' . rawurlencode($_login->user_u) . '&b=';
$url_b_ht = p2h($url_b);

// 携帯用ビューを開くブックマークレット
$bookmarklet = <<<JS
(function (u, w, v, x, y) {
    var t;
    if (typeof window.outerHeight === 'number') {
        t = y + window.outerHeight;
        if (v < t){
            v = t;
        }
    }
    t = window.open(u, '', 'width=' + w + ',height=' + v + ',' +
        'scrollbars=yes,resizable=yes,toolbar=no,menubar=no,status=no'
    );
    if (t) {
        t.resizeTo(w, v);
        t.focus();
        return false;
    } else {
        return true;
    }
})
JS;
$bookmarklet = preg_replace('/\\b(var|return|typeof) +/', '$1{%space%}', $bookmarklet);
$bookmarklet = preg_replace('/\\s+/', '', $bookmarklet);
$bookmarklet = str_replace('{%space%}', ' ', $bookmarklet);

$bookmarklet_k = $bookmarklet . "('{$url_b}k',240,320,20,-100)";
$bookmarklet_i = $bookmarklet . "('{$url_b}i',320,480,20,-100)";
$bookmarklet_k_ht = p2h($bookmarklet_k);
$bookmarklet_i_ht = p2h($bookmarklet_i);
$bookmarklet_k_en = rawurlencode($bookmarklet_k);
$bookmarklet_i_en = rawurlencode($bookmarklet_i);

$htm['ktai_url'] = <<<EOT
<table border="0" cellspacing="0" cellpadding="1">
    <tbody>
        <tr>
            <th>携帯用URL:</th>
            <td><a href="{$url_b_ht}k" target="_blank" onclick="return {$bookmarklet_k_ht};">{$url_b_ht}k</a></td>
            <td>[<a href="javascript:{$bookmarklet_k_en};">bookmarklet</a>]</td>
        </tr>
        <tr>
            <th>iPhone用URL:</th>
            <td><a href="{$url_b_ht}i" target="_blank" onclick="return {$bookmarklet_i_ht};">{$url_b_ht}i</a></td>
            <td>[<a href="javascript:{$bookmarklet_i_en};">bookmarklet</a>]</td>
        </tr>
    </tbody>
</table>
EOT;

// 前回のログイン情報
$htm['log'] = '';
$htm['last_login'] = '';
if ($_conf['login_log_rec'] && $_conf['last_login_log_show']) {
    if (($log = P2Util::getLastAccessLog($_conf['login_log_file'])) !== false) {
        $htm['log'] = array_map('p2h', $log);
        $htm['last_login'] = <<<EOT
<br>
<table border="0" cellspacing="0" cellpadding="1">
    <caption>前回のログイン情報 - {$htm['log']['date']}</caption>
    <tbody>
        <tr><th>ユーザ:</th><td>{$htm['log']['user']}</td></tr>
        <tr><th>IP:</th><td>{$htm['log']['ip']}</td></tr>
        <tr><th>HOST:</th><td>{$htm['log']['host']}</td></tr>
        <tr><th>UA:</th><td>{$htm['log']['ua']}</td></tr>
        <tr><th>REFERER:</th><td>{$htm['log']['referer']}</td></tr>
    </tbody>
</table>
EOT;
    }
}

//=========================================================
// HTMLプリント
//=========================================================
$ptitle = 'rep2 - title';

echo $_conf['doctype'];
echo <<<EOP
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <meta http-equiv="Content-Script-Type" content="text/javascript">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    {$_conf['extra_headers_ht']}
    <title>{$ptitle}</title>
    <base target="read">
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css.php?css=title&amp;skin={$skin_en}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
</head>
<body>\n
EOP;

// 情報メッセージ表示
P2Util::printInfoHtml();

echo <<<EOP
<br>
<div class="container">
    {$newversion_found}
    <table border="0" cellspacing="0" cellpadding="0"><tr><td>
        <img src="img/rep2.gif?140123" alt="rep2" width="120" height="63">
    </td><td style="padding-left:30px;">
    <p>{$_conf['p2name']} ver.{$_conf['p2version']} +live<br>
    <a href="{$expack_url_r}"{$_conf['ext_win_target_at']}>{$_conf['expack.web_url']}</a><br>
    <a href="{$rsk_expack_url_r}"{$_conf['ext_win_target_at']}>{$rsk_expack_url}</a><br>
    <a href="{$p2web_url_r}"{$_conf['ext_win_target_at']}>{$_conf['p2web_url']}</a></p>
    <ul>
        <li><a href="viewtxt.php?file=doc/README.txt">README.txt</a></li>
        <li><a href="viewtxt.php?file=doc/README-EX.txt">README-EX.txt</a></li>
        <li><a href="viewtxt.php?file=doc/README-774.txt">README-774.txt</a></li>
        <li><a href="img/how_to_use.png">ごく簡単な操作法</a></li>
        <li><a href="{$expack_hist_url_r}"{$_conf['ext_win_target_at']}>拡張パック 更新記録</a></li>
        <!-- <li><a href="viewtxt.php?file=doc/ChangeLog.txt">ChangeLog（rep2 更新記録）</a></li> -->
    </ul>
    {$htm['ver_str']}

    </td></tr></table>
    {$htm['auth_user']}
    {$htm['ktai_url']}
    {$htm['last_login']}
</div>
</body>
</html>
EOP;

//==================================================
// 関数
//==================================================
// {{{ checkUpdatan()

/**
 * オンライン上のrep2-expack最新版をチェックする
 *
 * @return string HTML
 */
function checkUpdatan()
{
    global $_conf, $p2web_url_r, $expack_url_r, $expack_dl_url_r, $expack_hist_url_r;

    $ver_txt_url = $_conf['expack.web_url'] . 'version.txt';
    $cachefile = P2Util::cacheFileForDL($ver_txt_url);
    FileCtl::mkdirFor($cachefile);

    P2Commun::fileDownload($ver_txt_url, $cachefile, $_conf['p2status_dl_interval'] * 86400);

    $ver_txt = FileCtl::file_read_lines($cachefile, FILE_IGNORE_NEW_LINES);
    $update_ver = $ver_txt[0];
    $kita = 'ｷﾀ━━━━（ﾟ∀ﾟ）━━━━!!!!!!';
    //$kita = 'ｷﾀ*･ﾟﾟ･*:.｡..｡.:*･ﾟ(ﾟ∀ﾟ)ﾟ･*:.｡. .｡.:*･ﾟﾟ･*!!!!!';

    $newversion_found_html = '';
    if ($update_ver && version_compare($update_ver, $_conf['p2version'], '>')) {
        $newversion_found_html = <<<EOP
<div class="kakomi">
    {$kita}<br>
    オンライン上に 拡張パック の最新バージョンを見つけますた。<br>
    rep2-expack rev.{$update_ver} → <a href="{$expack_dl_url_r}"{$_conf['ext_win_target_at']}>ダウンロード</a> / <a href="{$expack_hist_url_r}"{$_conf['ext_win_target_at']}>更新記録</a>
</div>
<hr class="invisible">
EOP;
    }
    return $newversion_found_html;
}

// }}}
// {{{ checkUpdatan2()

/**
 * github actionの最新版をチェックする
 *
 * @return string HTML
 */
function checkUpdatan2($run_id)
{
    global $_conf;

    try {
        $req = P2Commun::createHTTPRequest ('https://api.github.com/repos/fukumen/docker-rep2/actions/workflows/publish-php8.yml/runs?status=success&per_page=1', HTTP_Request2::METHOD_GET);
        $response = P2Commun::getHTTPResponse($req);
        $code = $response->getStatus();
        if ($code == 200) {
            $json = json_decode($response->getBody(), true);
            if (isset($json['workflow_runs'][0]['id'])) {
                $latest_run_id = $json['workflow_runs'][0]['id'];
                if ($latest_run_id > $run_id) {
                    $docker_msg = isset($json['workflow_runs'][0]['head_commit']['message']) ? $json['workflow_runs'][0]['head_commit']['message'] : '';
                    $docker_msg = (string)strtok($docker_msg, "\r\n");
                    $docker_msg = p2h(mb_convert_encoding($docker_msg, 'Shift_JIS', 'UTF-8'));
                    $docker_hash = isset($json['workflow_runs'][0]['head_sha']) ? substr($json['workflow_runs'][0]['head_sha'], 0, 7) : '';
                    $docker_date = isset($json['workflow_runs'][0]['head_commit']['timestamp']) ? $json['workflow_runs'][0]['head_commit']['timestamp'] : '';
                    if ($docker_date) {
                        $dt = new DateTime($docker_date);
                        $dt->setTimezone(new DateTimeZone('Asia/Tokyo'));
                        $docker_date = $dt->format('Y-m-d H:i');
                    }

                    $req2 = P2Commun::createHTTPRequest ('https://api.github.com/repos/fukumen/p2-php/actions/workflows/trigger-docker.yml/runs?status=success&per_page=1', HTTP_Request2::METHOD_GET);
                    $response2 = P2Commun::getHTTPResponse($req2);
                    $rep2_msg = '';
                    $rep2_date = '';
                    $rep2_hash = '';
                    if ($response2->getStatus() == 200) {
                        $json2 = json_decode($response2->getBody(), true);
                        $rep2_msg = isset($json2['workflow_runs'][0]['head_commit']['message']) ? $json2['workflow_runs'][0]['head_commit']['message'] : '';
                        $rep2_msg = (string)strtok($rep2_msg, "\r\n");
                        $rep2_msg = p2h(mb_convert_encoding($rep2_msg, 'Shift_JIS', 'UTF-8'));
                        $rep2_hash = isset($json2['workflow_runs'][0]['head_sha']) ? substr($json2['workflow_runs'][0]['head_sha'], 0, 7) : '';
                        $rep2_date = isset($json2['workflow_runs'][0]['head_commit']['timestamp']) ? $json2['workflow_runs'][0]['head_commit']['timestamp'] : '';
                        if ($rep2_date) {
                            $dt = new DateTime($rep2_date);
                            $dt->setTimezone(new DateTimeZone('Asia/Tokyo'));
                            $rep2_date = $dt->format('Y-m-d H:i');
                        }
                    }
                    return <<<EOP
<br>
<div class="kakomi">
    <table border="0" cellspacing="0" cellpadding="1">
        <caption>新しいビルドがあります。</caption>
        <tbody>
            <tr><th>p2-php:</th><td>{$rep2_date}&nbsp;{$rep2_msg}&nbsp;{$rep2_hash}</td></tr>
            <tr><th>docker-rep2:</th><td>{$docker_date}&nbsp;{$docker_msg}&nbsp;{$docker_hash}</td></tr>
            <tr><th>github action:</th><td>run_id:<a href="https://github.com/fukumen/docker-rep2/actions/runs/{$latest_run_id}">{$latest_run_id}</td></tr>
        </tbody>
    </table>
</div>
EOP;
                }
            }
        }
    } catch (Exception $e) {
    }
    return '';
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
