<?php
/**
 * rep2 - talk API 認証管理
 */

require_once __DIR__ . '/../init.php';
require_once P2_LIB_DIR . '/authtalkapi.inc.php';

$_login->authorize(); // ユーザ認証

//================================================================
// 変数
//================================================================
global $_conf;
$AppKey = $_conf['talkapi_appkey'];
$HMKey  = $_conf['talkapi_hmkey'];
$AppName = $_conf['talkapi_appname'];
$UAAuth = $_conf['talkapi_ua.auth'];
$UARead = $_conf['talkapi_ua.read'];
$UAPost = $_conf['talkapi_ua.post'];

//===============================================================
// 新規認証
//===============================================================
if (isset($_POST['loginTalkID'], $_POST['loginTalkPW'])) {
    $use_auth = isset($_POST['use_auth']) && $_POST['use_auth'] == '1';
    $use_login = isset($_POST['use_login']) && $_POST['use_login'] == '1';
    $talkapi = AuthTalkAPI::authenticate(true, $_POST['loginTalkID'], $_POST['loginTalkPW'], $use_auth, $use_login);

//==============================================================
// 再認証 or 認証解除
//==============================================================
} elseif (isset($_GET['logintalkapi'])) {
    if ($_GET['logintalkapi'] == "in") {
        $talkapi = AuthTalkAPI::authenticate(false);
    } elseif ($_GET['logintalkapi'] == "out") {
        if (file_exists($_conf['sidtalkapi_file'])) {
            unlink($_conf['sidtalkapi_file']);
        }
        $talkapi = null;
    }
} else {
    $talkapi = AuthTalkAPI::load();
}

//================================================================
// ヘッダ
//================================================================
if ($talkapi) { // talk●書き込み
    $ptitle = '●talk API 認証管理';
} else {
    $ptitle = 'talk API 認証管理';
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

if (!$_conf['ktai']) {
    echo <<<EOP
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css.php?css=login2ch&amp;skin={$skin_en}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <script type="text/javascript" src="js/basic.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}

$body_at = ($_conf['ktai']) ? $_conf['k_colors'] : ' onload="setWinTitle();"';

if (!$_conf['ktai']) {
    echo <<<EOP
<p id="pan_menu"><a href="setting.php">ログイン管理</a> &gt; {$ptitle}</p>
EOP;
}

P2Util::printInfoHtml();

//================================================================
// talk API ログインフォーム
//================================================================

// 認証中なら
if ($talkapi) {
    $idsub_str = '新規認証/ログインする';
    $status_msgs = array();
    $status_msgs[] = ($talkapi && empty($talkapi->talk_sid)) ? 'talk.jp未ログイン' : 'talk.jpログイン中';
    $status_msgs[] = ($talkapi && empty($talkapi->auth_sid)) ? 'API未認証' : 'API認証済み';
    $info_msg_ht = implode(' / ', $status_msgs);
    $form_now_log = <<<EOFORM
    現在、talk API 状態: {$info_msg_ht}
    <table border="0">
        <tr>
            <td>
                <form id="form_logout" method="GET" action="{$_SERVER['SCRIPT_NAME']}" target="_self">
                    {$_conf['k_input_ht']}
                    <input type="hidden" name="logintalkapi" value="out">
                    <input type="submit" name="submit" value="認証/ログイン解除する">
                </form>
            </td>
            <td>
                <form id="form_login" method="GET" action="{$_SERVER['SCRIPT_NAME']}" target="_self">
                    {$_conf['k_input_ht']}
                    <input type="hidden" name="logintalkapi" value="in">
                    <input type="submit" name="submit" value="再認証/ログインする">
                </form>
            </td>
        </tr>
    </table>
EOFORM;

} else {
    $idsub_str = '認証/ログインする';
    $form_now_log = 'talk API 認証/ログインしていません</p>';
}

if (!$_conf['ktai']) {
    $id_input_size_at = " size=\"30\"";
    $pass_input_size_at = " size=\"24\"";
}

$use_auth_checked = ($talkapi && (bool)$talkapi->use_auth) ? " checked" : "";
$use_login_checked = ($talkapi && (bool)$talkapi->use_login) ? " checked" : "";

// プリント =================================
echo "<div id=\"login_status\">";
echo $form_now_log;
echo "</div>";

if ($_conf['ktai']) {
    echo "<hr>";
}

echo <<<EOFORM
<form id="login_with_id" method="POST" action="{$_SERVER['SCRIPT_NAME']}" target="_self">
    {$_conf['k_input_ht']}
    アカウント連携を使用する場合、talk.jpのアカウントを指定してログインしてください（人柱機能）<br>
    <table border="0">
        <tr><td>メールアドレス: <input type="text" name="loginTalkID"{$id_input_size_at}></td></tr>
        <tr><td>パスワード: <input type="text" name="loginTalkPW"{$pass_input_size_at} style="-webkit-text-security: disc;"></td></tr>
    </table>
    <label><input type="checkbox" name="use_login" value="1"{$use_login_checked}> アカウントログインを行う (v1/login)</label><br><br>
    DATダウンロードや書き込みを行う場合、API認証してください。認証情報は<a href="edit_conf_user.php">ユーザ設定編集</a>の2ch APIのタブで編集できます。<br>
    <table>
        <tr><td>AppKey</td><td>"{$AppKey}"</td></tr>
        <tr><td>HMKey</td><td>"{$HMKey}"</td></tr>
        <tr><td>API認証に使用するX-2ch-UA</td><td>"{$AppName}"</td></tr>
        <tr><td>API認証で使用するUser-Agent</td><td>"{$UAAuth}"</td></tr>
        <tr><td>DAT取得で使用するUser-Agent</td><td>"{$UARead}"</td></tr>
        <tr><td>書き込みで使用するUser-Agent</td><td>"{$UAPost}"</td></tr>
    </table>
    <label><input type="checkbox" name="use_auth" value="1"{$use_auth_checked}> API認証を行う (v1/auth)</label><br>
    <br>
    <input type="submit" name="submit" value="{$idsub_str}">
</form>\n
EOFORM;

if ($_conf['ktai']) {
    echo "<hr>";
}

//================================================================
// フッタHTML表示
//================================================================

if ($_conf['ktai']) {
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
