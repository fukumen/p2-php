<?php
/**
 * rep2 - 最初のログイン画面を表示する
 */

// {{{ printLoginFirst()

/**
 *  最初のログイン画面を表示する
 */
function printLoginFirst(Login $_login, $failReason = null)
{
    global $STYLE, $_conf;
    global $skin_en;

    // {{{ データ保存ディレクトリのパーミッションの注意を喚起する
    P2Util::checkDirWritable($_conf['dat_dir']);
    $checked_dirs[] = $_conf['dat_dir']; // チェック済みのディレクトリを格納する配列に

    if (!in_array($_conf['idx_dir'], $checked_dirs)) {
        P2Util::checkDirWritable($_conf['idx_dir']);
        $checked_dirs[] = $_conf['idx_dir'];
    }
    if (!in_array($_conf['pref_dir'], $checked_dirs)) {
        P2Util::checkDirWritable($_conf['pref_dir']);
        $checked_dirs[] = $_conf['pref_dir'];
    }
    // }}}

    // 前処理
    $_login->checkAuthUserFile();
    clearstatcache();

    //=========================================================
    // 書き出し用変数
    //=========================================================
    $ptitle = 'rep2';

    $myname = basename($_SERVER['SCRIPT_NAME']);

    $auth_sub_input_ht = "";
    $body_ht = "";

    $p_str = array(
        'user'      => 'ユーザ',
        'password'  => 'パスワード'
    );

    // 携帯用表示文字列全角→半角変換
    if ($_conf['ktai'] && function_exists('mb_convert_kana')) {
        foreach ($p_str as $k => $v) {
            $p_str[$k] = mb_convert_kana($v, 'rnsk');
        }
    }

    //==============================================
    // 補助認証
    //==============================================
    $passkey_mode = P2PasskeyStore::getEffectiveMode();
    $keep_login_disabled = '';
    $keep_login_note = '';
    if ($_conf['passkey_auth_mode'] == 2) {
        // パスキー専用モード（実効モードではなく設定に従う）ではcidを発行しないためチェックボックスを無効化する
        $keep_login_disabled = ' disabled';
        $keep_login_note = ' (パスキー専用モードでは無効)';
    }

    $mobile = (new Net_UserAgent_Mobile)->singleton();

    $keep_login_checked = ' checked';
    if (isset($_POST['submit_new']) || isset($_POST['submit_member'])) {
        if (!isset($_POST['keep_login']) || $_POST['keep_login'] !== '1') {
            $keep_login_checked = '';
        }
    }
    $auth_sub_input_ht = <<<EOP
<input type="hidden" name="device_pixel_ratio" id="device_pixel_ratio" value="1">
<input type="hidden" name="ctl_keep_login" value="1">
<input type="checkbox" id="keep_login" name="keep_login" value="1"{$keep_login_checked}{$keep_login_disabled}><label for="keep_login">Cookieにログイン状態を保持{$keep_login_note}</label><br>
EOP;

    // }}}

    // ログインフォームからの指定
    if (!empty($_POST['form_login_id']) && preg_match("/^[0-9A-Za-z_]+\$/", $_POST['form_login_id'])) {
        $hd['form_login_id'] = p2h($_POST['form_login_id']);
    } else {
        $hd['form_login_id'] = '';
    }


    if (!empty($_POST['form_login_pass']) && preg_match('/^[\\x21-\\x7E]+$/', $_POST['form_login_pass'])) {
        $hd['form_login_pass'] = p2h($_POST['form_login_pass']);
    } else {
        $hd['form_login_pass'] = '';
    }

    // docomoならpasswordにしない
    if ($mobile->isDoCoMo()) {
        $type = 'text';
    } else {
        $type = 'password';
    }

    // {{{ ログイン用フォームを生成

    $hd['REQUEST_URI'] = p2h($_SERVER['REQUEST_URI']);
    if ($mobile->isDoCoMo()) {
        if (strpos($hd['REQUEST_URI'], '?') === false) {
            $hd['REQUEST_URI'] .= '?guid=ON';
        } else {
            $hd['REQUEST_URI'] .= '&amp;guid=ON';
        }
    }

    if (file_exists($_conf['auth_user_file'])) {
        $submit_ht = '<input type="submit" name="submit_member" value="ユーザログイン">';
    } else {
        $submit_ht = '<input type="submit" name="submit_new" value="新規登録">';
    }

    if ($_conf['ktai']) {
        //$k_roman_input_at = ' istyle="3" format="*m" mode="alphabet"';
        $k_roman_input_at = ' istyle="3" format="*x" mode="alphabet"';
        $k_input_size_at = '';
    } else {
        $k_roman_input_at = '';
        $k_input_size_at = ' size="32"';
    }
    $login_form_ht = '';
    if ($passkey_mode != 2) {
        $login_form_ht = <<<EOP
<form id="login" method="post" action="{$hd['REQUEST_URI']}" target="_self">
    {$_conf['k_input_ht']}
    {$p_str['user']}: <input type="text" name="form_login_id" value="{$hd['form_login_id']}"{$k_roman_input_at}{$k_input_size_at}><br>
    {$p_str['password']}: <input type="{$type}" name="form_login_pass" value="{$hd['form_login_pass']}"{$k_roman_input_at}><br>
    {$auth_sub_input_ht}
    <br>
    {$submit_ht}
</form>\n
EOP;
    }

    // }}}

    //=================================================================
    // 新規ユーザ登録処理
    //=================================================================

    if ($failReason === null) {
        $hd['form_login_id'] = p2h($_login->user_u);
        $body_ht .= "<p class=\"info-msg\">○ 認証{$p_str['user']}「{$hd['form_login_id']}」を登録しました</p>";
        $body_ht .= "<p><a href=\"{$myname}?form_login_id={$hd['form_login_id']}{$_conf['k_at_a']}\">rep2 start</a></p>";

    } else {
        if ($failReason === LoginResult::R_NEW_REGIST_INPUT_ERROR) {
            $info_msg_ht = '<p class="info-msg">';
            if (!isset($_POST['form_login_id']) || !$_POST['form_login_id']) {
                $info_msg_ht .= "rep2 error: 「{$p_str['user']}」が入力されていません。<br>";
            }
            if (!isset($_POST['form_login_pass']) || !$_POST['form_login_pass']) {
                $info_msg_ht .= "rep2 error: 「{$p_str['password']}」が入力されていません。";
            }
            $info_msg_ht .= '</p>';
            P2Util::pushInfoHtml($info_msg_ht);

        } elseif ($failReason === LoginResult::R_NEW_REGIST_PATTERN_ERROR) {
            P2Util::pushInfoHtml("<p class=\"info-msg\">rep2 error: 「{$p_str['user']}」名と「{$p_str['password']}」は半角英数字で入力して下さい。</p>");
        }
        // ログインフォームを表示する
        $show_login_form_flag = true;
    }

    //=========================================================
    // パスキー
    //=========================================================
    // 描画時にchallengeを発行してoptionsを埋め込む (フォーム送信は不要)
    $passkey_options_ht = false;
    $passkey_login_ht = '';
    if ($_conf['ktai'] && !$_conf['iphone']) {
        // 専用モードでガラケーからアクセスした場合の空白ページ防止
        if ($passkey_mode == 2) {
            $passkey_login_ht = '<p>パスキー専用モードのためガラケーからはログインできません</p>';
        }
    } elseif ($passkey_mode > 0) {
        try {
            $passkey_options_ht = P2PasskeyAuth::issueLoginOptions();
        } catch (P2PasskeyAuthException $e) {
            P2Util::pushInfoHtml('<p class="info-msg">p2 error: ' . p2h($e->getMessage()) . '</p>');
            $passkey_options_ht = false;
        }
        if ($passkey_options_ht !== false) {
            // ボタンとパスキー処理スクリプトは options 発行時のみ出力する
            $passkey_login_ht = <<<EOP
<div id="passkey_login">
    <p id="passkey_msg"></p>
</div>
<form id="passkey_verify" method="post" action="{$hd['REQUEST_URI']}">
    <input type="hidden" name="assertion">
    <input type="hidden" name="device_pixel_ratio" value="1">
    <button type="button" id="passkey_btn" onclick="passkeyLogin(this.form)">パスキーでログイン</button>
</form>\n
EOP;
        }
    }

    //=========================================================
    // HTMLプリント
    //=========================================================
    http_response_code (401);
    P2Util::header_nocache();
    echo $_conf['doctype'];
    echo <<<EOP
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <meta http-equiv="Content-Script-Type" content="text/javascript">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    <title>{$ptitle}</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">\n
EOP;
    if ($_conf['iphone']) {
        echo <<<EOP
    <meta name="viewport" content="width=device-width,initial-scale=1.0,user-scalable=yes">
    <meta name="format-detection" content="telephone=no">
    <style type="text/css">
    /* <![CDATA[ */\n
EOP;
        include P2_STYLE_DIR . '/login_first_css_i.inc';
        echo <<<EOP
\n/* ]]> */
</style>\n
EOP;
    } elseif (!$_conf['ktai']) {
        echo <<<EOP
<style type="text/css">
/* <![CDATA[ */\n
EOP;
        include P2_STYLE_DIR . '/style_css.inc';
        include P2_STYLE_DIR . '/login_first_css.inc';
        echo <<<EOP
\n/* ]]> */
</style>\n
EOP;
    }
    if ($_conf['iphone']) {
        echo <<<EOP
<script type="text/javascript">
// <![CDATA[
function setDevicePixelRatio()
{
    if (typeof window.devicePixelRatio === 'number') {
        var dprs = document.getElementsByName('device_pixel_ratio');
        for (var i = 0; i < dprs.length; i++) {
            dprs[i].value = window.devicePixelRatio;
        }
    }
}
// ]]>
</script>
</head>
<body onload="setDevicePixelRatio()">
EOP;
    } else {
        echo "</head><body>\n";
    }
    echo "<h3>{$ptitle}</h3>\n";

    // 情報表示
    P2Util::printInfoHtml();

    echo $body_ht;

    if (!empty($show_login_form_flag)) {
        echo $login_form_ht;
    }

    if ($passkey_login_ht !== '') {
        echo $passkey_login_ht;

        if ($passkey_options_ht !== false) {
            echo $passkey_options_ht . "\n";
            echo '<script type="text/javascript" src="js/simplewebauthn-browser-' . $_conf['simplewebauthn_browser_version'] . '.min.js"></script>' . "\n";
            echo '<script type="text/javascript" src="js/passkey.js?' . $_conf['p2_version_id'] . '"></script>' . "\n";
            echo <<<EOP
<script type="text/javascript">
//<![CDATA[
(function () {
    var msg = document.getElementById('passkey_msg');

    if (msg && !(window.isSecureContext && navigator.credentials && window.PublicKeyCredential)) {
        msg.textContent = 'この環境ではパスキーを利用できません (HTTPS接続と対応ブラウザが必要です)';
    }
})();
//]]>
</script>
EOP;
            if ($failReason === LoginResult::R_PASSKEY_REQUIRED) {
                echo <<<EOP
<script type="text/javascript">
//<![CDATA[
window.addEventListener('load', function () {
    if (window.isSecureContext && navigator.credentials && window.PublicKeyCredential) {
        passkeyLogin(document.getElementById('passkey_verify'));
    }
});
//]]>
</script>
EOP;
            }
        }
    }

    echo '</body></html>';

    return true;
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
