<?php
/**
 * rep2 - 2ch●ログイン管理
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

//================================================================
// 変数
//================================================================
$login2chID = null;
$login2chPW = null;
$autoLogin2ch = false;
$methodLogin2ch = null;

//===============================================================
// ログインなら、IDとPWを登録保存して、ログインする
//===============================================================
if (isset($_POST['login2chID']) && isset($_POST['login2chPW'])) {

    if (isset($_POST['autoLogin2ch'])) {
        $autoLogin2ch = ($_POST['autoLogin2ch'] === '1') ? true : false;
    } else {
        $autoLogin2ch = false;
    }
    if (isset($_POST['methodLogin2ch'])) {
        $methodLogin2ch = ($_POST['methodLogin2ch'] === 'tora3') ? 1 : 0;
    } else {
        $methodLogin2ch = null;
    }

    P2Util::saveIdPw2ch($_POST['login2chID'], $_POST['login2chPW'], $autoLogin2ch, $methodLogin2ch);

    require_once P2_LIB_DIR . '/login2ch.inc.php';
    login2ch();
}

$expiration_st = '';
$sid_expires_st = '';
// （フォーム入力用に）ID, PW設定を読み込む
$array = P2Util::readIdPw2ch();
if ($array) {
    list($login2chID, $login2chPW, $autoLogin2ch, $methodLogin2ch) = $array;
}

//==============================================================
// 2chログイン処理
//==============================================================
if (isset($_GET['login2ch'])) {
    if ($_GET['login2ch'] == "in") {
        require_once P2_LIB_DIR . '/login2ch.inc.php';
        login2ch();
    } elseif ($_GET['login2ch'] == "out") {
        if (file_exists($_conf['sid2ch_php'])) {
            unlink($_conf['sid2ch_php']);
        }
        if (file_exists($_conf['siduplift_file'])) {
            unlink($_conf['siduplift_file']);
        }
    }
}


//==============================================================
// UPLIFTのログイン中は有効期限を表示する
//==============================================================
if ($array && file_exists($_conf['siduplift_file'])) {
    require_once P2_LIB_DIR . '/login2ch.inc.php';
    // UPLIFTの有効期限を取得
    $data = get_uplift_sid($array);
    if (isset($data['expiration'])) {
        $expiration_time = $data['expiration'];
        if ($expiration_time) {
            $expiration_date = date('Y/m/d H:i:s', $expiration_time);
            $diff = $expiration_time - time();
            if ($diff > 0) {
                $days = floor($diff / 86400);
                $hours = floor(($diff % 86400) / 3600);
                $minutes = floor(($diff % 3600) / 60);
                $expiration_date .= " (残り{$days}日 {$hours}時間 {$minutes}分)";
            } else {
                // 期限切れ
                unlink($_conf['siduplift_file']);
            }
            $expiration_st = "アカウントの有効期限: {$expiration_date}<br>";
        }
    }
    if (isset($data['sid']['expires'])) {
        $sid_expires_time = strtotime($data['sid']['expires']);
        $sid_expires_date = date('Y/m/d H:i:s', $sid_expires_time);
        $diff = $sid_expires_time - time();
        if ($diff > 0) {
            $days = floor($diff / 86400);
            $hours = floor(($diff % 86400) / 3600);
            $minutes = floor(($diff % 3600) / 60);
            $sid_expires_date .= " (残り{$days}日 {$hours}時間 {$minutes}分)";
        } else {
            $sid_expires_date .= " (期限切れ)";
        }
        $sid_expires_st = "クッキー(sid)の有効期限: {$sid_expires_date}<br>";
    }
}

//================================================================
// ヘッダ
//================================================================
if ($_conf['ktai']) {
    $login_st = "ﾛｸﾞｲﾝ";
    $logout_st = "ﾛｸﾞｱｳﾄ";
    $password_st = "ﾊﾟｽﾜｰﾄﾞ";
} else {
    $login_st = "ログイン";
    $logout_st = "ログアウト";
    $password_st = "パスワード";
}

$ptitle = "2ch{$login_st}管理";
$method_st = ($methodLogin2ch === 0 ? '(uplift)': ($methodLogin2ch === 1 ? '(tora3)' : ''));

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

echo <<<EOP
    <script type="text/javascript">
    //<![CDATA[
    function checkPass2ch(){
        if (pass2ch_input = document.getElementById('login2chPW')) {
            if (pass2ch_input.value == "") {
                alert("パスワードを入力して下さい");
                return false;
            }
        }
    }
    //]]>
    </script>
</head>
<body{$body_at}>
EOP;

if (!$_conf['ktai']) {
    echo <<<EOP
<p id="pan_menu"><a href="setting.php">ログイン管理</a> &gt; {$ptitle}</p>
EOP;
}

P2Util::printInfoHtml();

//================================================================
// 2ch●ログインフォーム
//================================================================

// ログイン中なら
if (file_exists($_conf['siduplift_file']) || file_exists($_conf['sid2ch_php'])) {
    $idsub_str = "再{$login_st}する";
    $form_now_log = <<<EOFORM
    <form id="form_logout" method="GET" action="{$_SERVER['SCRIPT_NAME']}" target="_self">
        現在、2ちゃんねるに{$login_st}中です{$method_st}
        {$_conf['k_input_ht']}
        <input type="hidden" name="login2ch" value="out">
        <input type="submit" name="submit" value="{$logout_st}する">
    </form>\n
    {$expiration_st}
    {$sid_expires_st}
EOFORM;

} else {
    $idsub_str = "新規{$login_st}する";
    if (file_exists($_conf['idpw2ch_php'])) {
        $form_now_log = <<<EOFORM
    <form id="form_logout" method="GET" action="{$_SERVER['SCRIPT_NAME']}" target="_self">
        現在、{$login_st}していません{$method_st}
        {$_conf['k_input_ht']}
        <input type="hidden" name="login2ch" value="in">
        <input type="submit" name="submit" value="再{$login_st}する">
    </form>\n
EOFORM;
    } else {
        $form_now_log = "<p>現在、{$login_st}していません</p>";
    }
}

if ($autoLogin2ch) {
    $autoLogin2ch_checked = ' checked="checked"';
} else {
    $autoLogin2ch_checked = '';
}
if ($methodLogin2ch == 1) {
    $uplift_checked = '';
    $tora3_checked = ' checked';
} else {
    $uplift_checked = ' checked';
    $tora3_checked = '';
}

$tora3_url = "http://2ch.tora3.net/";
$tora3_url_r = P2Util::throughIme($tora3_url);

$tool_url = "https://maraga.jp/1.1/RoninTool.php";
$tool_url_r = P2Util::throughIme($tool_url);

if (!$_conf['ktai']) {
    $id_input_size_at = " size=\"30\"";
    $pass_input_size_at = " size=\"24\"";
}

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
    ID: <input type="text" name="login2chID" value="{$login2chID}"{$id_input_size_at}><br>
    {$password_st}: <input type="text" name="login2chPW" id="login2chPW"{$pass_input_size_at} style="-webkit-text-security: disc;"><br>
    <input type="checkbox" id="autoLogin2ch" name="autoLogin2ch" value="1"{$autoLogin2ch_checked}><label for="autoLogin2ch">起動時に自動{$login_st}する</label><br>
    <input type="radio" id="uplift" name="methodLogin2ch" value="uplift"{$uplift_checked}><label for="uplift">uplift</label><br>
    <input type="radio" id="tora3" name="methodLogin2ch" value="tora3"{$tora3_checked}><label for="tora3">tora3</label><br>
    <input type="submit" name="submit" value="{$idsub_str}" onclick="return checkPass2ch();">
</form>\n
EOFORM;

if ($_conf['ktai']) {
    echo "<hr>";
}

//================================================================
// フッタHTML表示
//================================================================

echo <<<EOP
<p>
浪人お役立ちツールはこちら→ <a href="{$tool_url_r}" target="_blank">{$tool_url}</a><br />
２ちゃんねる浪人についての詳細はこちら→ <a href="{$tora3_url_r}" target="_blank">{$tora3_url}</a>
</p>
EOP;

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
