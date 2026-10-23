<?php
/**
 * rep2 - パスキー管理ページ
 */

define('P2_SESSION_CLOSE_AFTER_AUTHENTICATION', 0);

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

if ($_conf['ktai'] && !$_conf['iphone']) {
    p2die('パスキーは利用できません。');
}
if ($_conf['passkey_auth_mode'] != 1 && $_conf['passkey_auth_mode'] != 2) {
    p2die('パスキーは無効です。');
}

// 書き出し用変数 ========================================
$ptitle = 'パスキー管理';
$csrfid = P2Util::getCsrfId(__FILE__);

$passkey_signal_ht = false;

// 削除処理
if (isset($_POST['delete_credential_id']) && $_POST['delete_credential_id'] !== '') {
    if (!isset($_POST['csrfid']) || $_POST['csrfid'] != $csrfid) {
        p2die('不正なポストです');
    }
    $del_id = strval($_POST['delete_credential_id']);
    try {
        P2PasskeyAuth::deleteCredential($del_id);
        P2Util::pushInfoHtml('<p class="info-msg">○ パスキーを削除しました</p>');
        try {
            $passkey_signal_ht = P2PasskeyAuth::buildSignalScript($del_id);
        } catch (P2PasskeyAuthException $e) {
            P2Util::pushInfoHtml('<p class="info-msg">p2 error: ' . p2h($e->getMessage()) . '</p>');
        }
    } catch (P2PasskeyAuthException $e) {
        P2Util::pushInfoHtml('<p class="info-msg">p2 error: ' . p2h($e->getMessage()) . '</p>');
    }
}

// 登録名変更処理
if (isset($_POST['rename_credential_id']) && $_POST['rename_credential_id'] !== '') {
    if (!isset($_POST['csrfid']) || $_POST['csrfid'] != $csrfid) {
        p2die('不正なポストです');
    }
    $rename_id = strval($_POST['rename_credential_id']);
    $new_name = isset($_POST['new_name']) && is_string($_POST['new_name']) ? $_POST['new_name'] : '';
    try {
        P2PasskeyAuth::renameCredential($rename_id, $new_name);
        P2Util::pushInfoHtml('<p class="info-msg">○ パスキー名を変更しました</p>');
    } catch (P2PasskeyAuthException $e) {
        P2Util::pushInfoHtml('<p class="info-msg">p2 error: ' . p2h($e->getMessage()) . '</p>');
    }
}

// 登録検証処理
if (isset($_POST['attestation']) && $_POST['attestation'] !== '') {
    // challengeトークンによるCSRF対策済みであり、$csrfid による対策は実施しない
    try {
        P2PasskeyAuth::verifyRegistration(
            $_POST['attestation'],
            isset($_POST['name']) && is_string($_POST['name']) ? $_POST['name'] : ''
        );
        P2Util::pushInfoHtml('<p class="info-msg">○ パスキーを登録しました</p>');
    } catch (P2PasskeyAuthException $e) {
        P2Util::pushInfoHtml('<p class="info-msg">p2 error: ' . p2h($e->getMessage()) . '</p>');
        if ($e->getCredentialId() !== null) {
            try {
                $passkey_signal_ht = P2PasskeyAuth::buildSignalScript($e->getCredentialId());
            } catch (P2PasskeyAuthException $e2) {
                P2Util::pushInfoHtml('<p class="info-msg">p2 error: ' . p2h($e2->getMessage()) . '</p>');
            }
        }
    }
}

$mode = P2PasskeyStore::getEffectiveMode();
$creds = P2PasskeyAuth::findAllCredentials();
$count = count($creds);

// challengeは描画時に発行する (放置で失効した場合はverifyがエラーを返すため再読込で回復)
$passkey_options_ht = false;
$add_disabled_msg = '';
if ($count >= $_conf['passkey_max_registrations']) {
    $add_disabled_msg = "<p>登録数が上限 ({$_conf['passkey_max_registrations']}個) に達しています。追加するには削除してください</p>";
} else {
    try {
        $passkey_options_ht = P2PasskeyAuth::issueCreateOptions();
    } catch (P2PasskeyAuthException $e) {
        P2Util::pushInfoHtml('<p class="info-msg">p2 error: ' . p2h($e->getMessage()) . '</p>');
        $passkey_options_ht = false;
    }
}

// 一覧の生成
$list_ht = '';
foreach ($creds as $cred) {
    $name = p2h($cred['name']);
    $registered = $cred['registeredAt'] ? date('Y-m-d H:i', $cred['registeredAt']) : '-';
    $last_used = $cred['lastUsedAt'] ? date('Y-m-d H:i', $cred['lastUsedAt']) : '未使用';
    $id_head = p2h(mb_substr($cred['credentialId'], 0, 10));
    $id_b64 = p2h($cred['credentialId']);
    $list_ht .= <<<EOP
<tr>
    <td>{$name}</td>
    <td>{$registered}</td>
    <td>{$last_used}</td>
    <td>{$id_head}…</td>
    <td><form method="post" action="passkey.php{$_conf['k_at_q']}" onsubmit="return passkeyRename(this);" style="display: inline-block;"><input type="hidden" name="rename_credential_id" value="{$id_b64}"><input type="hidden" name="current_name" value="{$name}"><input type="hidden" name="new_name"><input type="hidden" name="csrfid" value="{$csrfid}"><input type="submit" value="編集"></form>
    <form method="post" action="passkey.php{$_conf['k_at_q']}" onsubmit="return confirm('このパスキーを削除しますか？');" style="display: inline-block;"><input type="hidden" name="delete_credential_id" value="{$id_b64}"><input type="hidden" name="csrfid" value="{$csrfid}"><input type="submit" value="削除"></form></td>
</tr>\n
EOP;
}

// 登録UIの状態
if ($passkey_options_ht !== false) {
    // ボタンとパスキー処理スクリプトは options 発行時のみ出力する
    $add_form_ht = <<<EOP
<form id="passkey_verify" method="post" action="passkey.php{$_conf['k_at_q']}">
    <input type="hidden" name="attestation">
    <p><input type="text" id="passkey_name" name="name" size="30" maxlength="50" placeholder="登録名 (例: Chrome on Windows)">
    <button type="button" id="passkey_btn" onclick="passkeyRegister(this.form)">パスキーを追加</button></p>
    <p id="passkey_msg"></p>
</form>
EOP;
} else {
    // 押せないときは入力欄とボタンを出力せず、案内文があれば表示する
    $add_form_ht = $add_disabled_msg;
}

//=========================================================
// HTMLプリント
//=========================================================
P2Util::header_nocache();
echo $_conf['doctype'];
echo <<<EOP
<html>
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
    <link rel="stylesheet" type="text/css" href="css.php?css=setting&amp;skin={$skin_en}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <script type="text/javascript" src="js/basic.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}

$body_at = ($_conf['ktai']) ? $_conf['k_colors'] : ' onload="setWinTitle();"';
echo <<<EOP
</head>
<body{$body_at}>
EOP;

if (!$_conf['ktai']) {
    echo <<<EOP
<p id="pan_menu"><a href="setting.php">ログイン管理</a> &gt; {$ptitle}</p>
EOP;
}

// インフォメッセージ表示
P2Util::printInfoHtml();

echo <<<EOP
<h3>{$ptitle}</h3>
<p>認証モード: 
EOP;

if ($_conf['passkey_auth_mode'] == 1) {
    echo '併用 (パスワード・cid・パスキーが有効)';
} elseif ($mode == 2) {
    echo 'パスキー専用 (パスワード・cidは無効)';
} else {
    // 専用モード設定だがパスキー未登録の移行状態 (実効モードは 0 相当)
    echo 'パスキー専用 (パスキー未登録のため、現在はパスワードログインとパスキーの登録が可能)';
}

echo " (登録数: {$count}/{$_conf['passkey_max_registrations']})</p>\n";

echo $add_form_ht;

echo <<<EOP
<table border="1" cellspacing="0" cellpadding="3">
<tr><th>登録名</th><th>登録日時</th><th>最終使用日時</th><th>credentialId</th><th>操作</th></tr>
EOP;
echo $list_ht;
echo '</table>';

// フッタプリント===================
if ($_conf['ktai']) {
    echo "<hr><div class=\"center\">{$_conf['k_to_index_ht']}</div>";
}

if ($passkey_options_ht !== false || $passkey_signal_ht !== false || $count > 0) {
    if ($passkey_options_ht !== false) {
        echo $passkey_options_ht . "\n";
    }
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
    if ($passkey_signal_ht !== false) {
        echo $passkey_signal_ht . "\n";
    }
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
