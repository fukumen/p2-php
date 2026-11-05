<?php
/**
 * rep2 - 2ch API ログイン管理
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

//================================================================
// 変数
//================================================================
global $_conf;
$AppKey = $_conf['2chapi_appkey'];
$AppName = $_conf['2chapi_appname'];
$HMKey  = $_conf['2chapi_hmkey'];

//==============================================================
// 2chログイン処理
//==============================================================
if (isset($_GET['login2chapi'])) {
    if ($_GET['login2chapi'] == "in") {
        require_once P2_LIB_DIR . '/auth2chapi.inc.php';
        authenticate_2chapi();
    } elseif ($_GET['login2chapi'] == "out") {
        if (file_exists($_conf['sid2chapi_php'])) {
            unlink($_conf['sid2chapi_php']);
        }
    }
}

//================================================================
// ヘッダ
//================================================================
if ($_conf['iphone']) {
    $login_st = "ﾛｸﾞｲﾝ";
    $logout_st = "ﾛｸﾞｱｳﾄ";
    $password_st = "ﾊﾟｽﾜｰﾄﾞ";
} else {
    $login_st = "ログイン";
    $logout_st = "ログアウト";
    $password_st = "パスワード";
}

if (file_exists($_conf['sid2chapi_php'])) { // 2ch●書き込み
    $ptitle = "●2ch API 認証管理";
} else {
    $ptitle = "2ch API 認証管理";
}

P2Util::header_nocache();
echo $_conf['doctype'];
echo <<<EOP
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <meta http-equiv="Content-Script-Type" content="text/javascript">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    {$_conf['extra_headers_ht']}
    <title>{$ptitle}</title>\n
EOP;

if (!$_conf['iphone']) {
    echo <<<EOP
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css.php?css=login2ch&amp;skin={$skin_en}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <script type="text/javascript" src="js/basic.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}

$body_at = ($_conf['iphone']) ? '' : ' onload="setWinTitle();"';

if (!$_conf['iphone']) {
    echo <<<EOP
<p id="pan_menu"><a href="setting.php">ログイン管理</a> &gt; {$ptitle}</p>
EOP;
}

P2Util::printInfoHtml();

//================================================================
// 2ch API ログインフォーム
//================================================================

// ログイン中なら
if (file_exists($_conf['sid2chapi_php'])) {
    $idsub_str = "再認証する";
    $form_now_log = <<<EOFORM
    <form id="form_logout" method="GET" action="{$_SERVER['SCRIPT_NAME']}" target="_self">
        現在、2ちゃんねる API 認証中です
        {$_conf['k_input_ht']}
        <input type="hidden" name="login2chapi" value="out">
        <input type="submit" name="submit" value="認証解除する">
    </form>\n
EOFORM;

} else {
    $idsub_str = "認証する";
    $form_now_log = "2ちゃんねる API 認証していません</p>";
}

if ($autoLogin2ch) {
    $autoLogin2ch_checked = ' checked="checked"';
} else {
    $autoLogin2ch_checked = '';
}

$tora3_url = "http://2ch.tora3.net/";
$tora3_url_r = P2Util::throughIme($tora3_url);

if (!$_conf['iphone']) {
    $id_input_size_at = " size=\"30\"";
    $pass_input_size_at = " size=\"24\"";
}

// プリント =================================
echo "<div id=\"login_status\">";
echo $form_now_log;
echo "</div>";

if ($_conf['iphone']) {
    echo "<hr>";
}

echo <<<EOFORM
<form id="login_with_id" method="GET" action="{$_SERVER['SCRIPT_NAME']}" target="_self">
    {$_conf['k_input_ht']}
    AppKey: "{$AppKey}"<br>
    HMKey: "{$HMKey}"<br>
    AppName: "{$AppName}"<br>
    認証情報は<a href="edit_conf_user.php">ユーザ設定編集</a>で変更できます。<br>
    <input type="hidden" name="login2chapi" value="in">
    <input type="submit" name="submit" value="{$idsub_str}">
</form>\n
EOFORM;

if ($_conf['iphone']) {
    echo "<hr>";
}

//================================================================
// フッタHTML表示
//================================================================

if ($_conf['iphone']) {
    echo "<hr><div class=\"center\">{$_conf['k_to_index_ht']}</div>";
}

echo '</body></html>';

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
