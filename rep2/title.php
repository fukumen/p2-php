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

$fukumen_url = "https://github.com/fukumen/p2-php";
$fukumen_url_r = P2Util::throughIme($fukumen_url);

// img/rep2.svg からコピペ
$rep2_logo_svg_path = <<<EOT
M 255.578125 96 L 201.027344 96 L 201.027344 94.496094 C 217.752686 74.580627 227.903946 61.182327 231.481445 54.300781 C 235.058945 47.419235 236.847656 40.697296 236.847656 34.134766 C 236.847656 29.349586 235.366547 25.37339 232.404297 22.206055 C 229.442047 19.038719 225.819031 17.455078 221.535156 17.455078 C 214.516891 17.455078 209.070984 20.964157 205.197266 27.982422 L 202.667969 27.09375 C 205.128922 18.343704 208.86586 11.872414 213.878906 7.679688 C 218.891953 3.486954 224.679657 1.390625 231.242188 1.390625 C 235.936218 1.390625 240.220032 2.48436 244.09375 4.671875 C 247.967468 6.859383 250.998032 9.855774 253.185547 13.661133 C 255.373047 17.466492 256.466797 21.032532 256.466797 24.359375 C 256.466797 30.420601 254.780609 36.572884 251.408203 42.816406 C 246.805313 51.247437 236.756592 63.005135 221.261719 78.089844 L 241.291016 78.089844 C 246.212921 78.089844 249.414383 77.884766 250.895508 77.474609 C 252.376648 77.064453 253.595703 76.369476 254.552734 75.389648 C 255.509766 74.409821 256.763 72.347672 258.3125 69.203125 L 260.773438 69.203125 Z M 149.210938 89.505859 L 149.210938 113.294922 C 149.210938 116.576187 149.472977 118.809235 149.99707 119.994141 C 150.521164 121.179047 151.330078 122.044922 152.423828 122.591797 C 153.517578 123.138672 155.659485 123.412109 158.849609 123.412109 L 158.849609 125.941406 L 122.414063 125.941406 L 122.414063 123.412109 C 125.239594 123.320961 127.335938 122.523445 128.703125 121.019531 C 129.614594 119.971352 130.070313 117.259789 130.070313 112.884766 L 130.070313 45.414063 C 130.070313 40.902321 129.546234 38.019859 128.498047 36.766602 C 127.44986 35.513344 125.42189 34.79557 122.414063 34.613281 L 122.414063 32.083984 L 149.210938 32.083984 L 149.210938 40.492188 C 151.444016 37.210922 153.722641 34.841156 156.046875 33.382813 C 159.373718 31.240875 162.996719 30.169922 166.916016 30.169922 C 171.610046 30.169922 175.882462 31.651024 179.733398 34.613281 C 183.584335 37.575539 186.51236 41.665665 188.517578 46.883789 C 190.522797 52.101913 191.525391 57.718719 191.525391 63.734375 C 191.525391 70.205765 190.488617 76.11879 188.415039 81.473633 C 186.341461 86.828476 183.333679 90.907211 179.391602 93.709961 C 175.449524 96.512711 171.063171 97.914063 166.232422 97.914063 C 162.723297 97.914063 159.442078 97.139328 156.388672 95.589844 C 154.110016 94.404938 151.717453 92.376968 149.210938 89.505859 Z M 149.210938 82.601563 C 153.130234 88.161484 157.322891 90.941406 161.789063 90.941406 C 164.250015 90.941406 166.277985 89.642593 167.873047 87.044922 C 170.242844 83.216782 171.427734 75.925186 171.427734 65.169922 C 171.427734 54.14122 170.128922 46.598976 167.53125 42.542969 C 165.799469 39.854156 163.475281 38.509766 160.558594 38.509766 C 155.955704 38.509766 152.173187 41.836555 149.210938 48.490234 Z M 116.466797 61.751953 L 81.398438 61.751953 C 81.808594 70.228561 84.06443 76.927711 88.166016 81.849609 C 91.310562 85.632179 95.093079 87.523438 99.513672 87.523438 C 102.248062 87.523438 104.731758 86.760101 106.964844 85.233398 C 109.197929 83.706696 111.590485 80.960953 114.142578 76.996094 L 116.466797 78.5 C 113.003235 85.563843 109.175148 90.565414 104.982422 93.504883 C 100.789696 96.444351 95.936226 97.914063 90.421875 97.914063 C 80.942657 97.914063 73.764999 94.268265 68.888672 86.976563 C 64.969383 81.097626 63.009766 73.80603 63.009766 65.101563 C 63.009766 54.437447 65.892227 45.949577 71.657227 39.637695 C 77.422226 33.325813 84.178345 30.169922 91.925781 30.169922 C 98.397171 30.169922 104.013977 32.824516 108.776367 38.133789 C 113.538757 43.443062 116.102211 51.315704 116.466797 61.751953 Z M 99.650391 57.171875 C 99.650391 49.834602 99.251633 44.798843 98.454102 42.064453 C 97.65657 39.330063 96.414719 37.256516 94.728516 35.84375 C 93.771477 35.02343 92.495453 34.613281 90.900391 34.613281 C 88.530586 34.613281 86.593758 35.775375 85.089844 38.099609 C 82.401031 42.155617 81.056641 47.715462 81.056641 54.779297 L 81.056641 57.171875 Z M 29.240234 32.083984 L 29.240234 46.576172 C 33.478539 39.968063 37.192692 35.581718 40.382813 33.416992 C 43.572933 31.252266 46.649075 30.169922 49.611328 30.169922 C 52.163425 30.169922 54.202793 30.956047 55.729492 32.52832 C 57.256191 34.100594 58.019531 36.32225 58.019531 39.193359 C 58.019531 42.246758 57.27898 44.616531 55.797852 46.302734 C 54.316723 47.988937 52.528004 48.832031 50.431641 48.832031 C 48.016266 48.832031 45.91993 48.057297 44.142578 46.507813 C 42.365227 44.958328 41.317059 44.092445 40.998047 43.910156 C 40.542316 43.636719 40.01823 43.5 39.425781 43.5 C 38.10416 43.5 36.850918 44.001297 35.666016 45.003906 C 33.797516 46.553391 32.384769 48.763657 31.427734 51.634766 C 29.969395 56.055359 29.240234 60.931618 29.240234 66.263672 L 29.240234 80.960938 L 29.308594 84.789063 C 29.308594 87.386734 29.468098 89.050125 29.787109 89.779297 C 30.333988 91.009773 31.142899 91.909828 32.213867 92.479492 C 33.284836 93.049156 35.09634 93.402344 37.648438 93.539063 L 37.648438 96 L 3.126953 96 L 3.126953 93.539063 C 5.906915 93.311195 7.786779 92.547859 8.766602 91.249023 C 9.746424 89.950188 10.236328 86.520859 10.236328 80.960938 L 10.236328 45.619141 C 10.236328 41.973289 10.054038 39.649094 9.689453 38.646484 C 9.233722 37.370438 8.572921 36.436203 7.707031 35.84375 C 6.841142 35.251297 5.314464 34.841148 3.126953 34.613281 L 3.126953 32.083984 Z
EOT;

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
//if (!empty($_conf['updatan_haahaa'])) {
//    $newversion_found = checkUpdatan();
//}

//=========================================================
// github actionの情報表示
//=========================================================
$ver_str = array();
foreach (array('VER_REPO_TYPE', 'VER_REPO_HASH', 'VER_REPO_LOG', 'VER_REP2_HASH', 'VER_REP2_LOG', 'VER_RUN_ID', 'VER_RUN_NUMBER') as $key) {
    if (($val = getenv($key)) !== false) {
        $ver_str[$key] = $val;
    }
}
if (empty($ver_str['VER_REPO_TYPE'])) {
    $ver_str['VER_REPO_TYPE'] = 'docker-rep2';
}

$current_rep2_hash_en = '';
if (count($ver_str) >= 6) {
    $newversion_found2 = '';
    if (!empty($_conf['updatan_haahaa'])) {
        $newversion_found2 = checkUpdatan2($ver_str['VER_REPO_TYPE'], $ver_str['VER_RUN_ID']);
    }

    $ver_str['VER_REP2_LOG'] = mb_convert_encoding(base64_decode($ver_str['VER_REP2_LOG']), 'CP932', 'UTF-8');
    $ver_str['VER_REPO_LOG'] = mb_convert_encoding(base64_decode($ver_str['VER_REPO_LOG']), 'CP932', 'UTF-8');
    $current_rep2_hash_en = rawurlencode($ver_str['VER_REP2_HASH']);
    $htm['ver_str'] = <<<EOT
<table border="0" cellspacing="0" cellpadding="1">
    <caption>ビルド情報</caption>
    <tbody>
        <tr><th>p2-php:</th><td>{$ver_str['VER_REP2_LOG']}&nbsp;{$ver_str['VER_REP2_HASH']}</td></tr>
        <tr><th>{$ver_str['VER_REPO_TYPE']}:</th><td>{$ver_str['VER_REPO_LOG']}&nbsp;{$ver_str['VER_REPO_HASH']}</td></tr>
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
    <div style="display: flex; gap: 30px; align-items: flex-start;">
        <div>
            <div class="rep2_logo_svg" title="rep2">
                <svg viewBox="0 0 262 126" xmlns="http://www.w3.org/2000/svg">
                    <path fill="currentColor" fill-rule="evenodd" stroke="none" d="{$rep2_logo_svg_path}"/>
                </svg>
            </div>
        </div>
        <div>
            <p>
            {$_conf['p2name']} ver.{$_conf['p2version']} +live<br>
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
        </div>
        <div>
            <p>
            <a href="{$fukumen_url_r}"{$_conf['ext_win_target_at']}>{$fukumen_url}</a><br>
            </p>
            <ul>
                <li><a href="viewmd.php?file=README.md">README.md</a></li>
                <li><a href="viewcommit.php?hash={$current_rep2_hash_en}">最新のコミットを確認する</a></li>
            </ul>
            {$htm['ver_str']}
        </div>
    </div>
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
function checkUpdatan2($repo_type, $run_id)
{
    global $_conf;

    if ($repo_type === 'rep2-allinone') {
        $github_repo = 'fukumen/rep2-allinone';
        $workflow_file = 'publish.yml';
    } else {
        $github_repo = 'fukumen/docker-rep2';
        $workflow_file = 'publish-php8.yml';
        $repo_type = 'docker-rep2';
    }

    try {
        $requests = array(
            'repo' => "https://api.github.com/repos/{$github_repo}/actions/workflows/{$workflow_file}/runs?status=success&per_page=1",
            'rep2' => 'https://api.github.com/repos/fukumen/p2-php/actions/workflows/trigger-docker.yml/runs?status=success&per_page=1'
        );

        $responses = P2CurlMulti::httpRequestsParallel($requests);

        $repo_res = P2CurlMulti::getResponse($responses, 'repo');
        if ($repo_res && $repo_res->getStatus() == 200) {
            $json = json_decode($repo_res->getBody(), true);
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

                    $rep2_res = P2CurlMulti::getResponse($responses, 'rep2');
                    $rep2_msg = '';
                    $rep2_date = '';
                    $rep2_hash = '';
                    if ($rep2_res && $rep2_res->getStatus() == 200) {
                        $json2 = json_decode($rep2_res->getBody(), true);
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

                    $github_url = P2Util::throughIme('https://github.com/' . $github_repo . '/actions/runs/' . $latest_run_id);
                    return <<<EOP
<br>
<div class="kakomi">
    <table border="0" cellspacing="0" cellpadding="1">
        <caption>新しいビルドがあります。</caption>
        <tbody>
            <tr><th>p2-php:</th><td>{$rep2_date}&nbsp;{$rep2_msg}&nbsp;{$rep2_hash}</td></tr>
            <tr><th>{$repo_type}:</th><td>{$docker_date}&nbsp;{$docker_msg}&nbsp;{$docker_hash}</td></tr>
            <tr><th>github action:</th><td>run_id:<a href="{$github_url}"{$_conf['ext_win_target_at']}>{$latest_run_id}</a></td></tr>
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
