<?php
/**
 * rep2 - 名前コマンド編集インタフェース
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

$cmd_file = $_conf['pref_dir'] . '/p2_commands_name.txt';

// [保存]ボタン
if (!empty($_POST['submit_save'])) {
    if (!isset($_POST['csrfid']) || $_POST['csrfid'] != P2Util::getCsrfId()) {
        die('p2 error: 不正なポストです');
    }

    $dat = isset($_POST['dat']) ? $_POST['dat'] : array();
    $filtered = array();
    foreach ($dat as $i => $cmd) {
        if (!is_array($cmd)) {
            continue;
        }
        if ($cmd['del'] ?? false) {
            continue;
        }
        if (isset($cmd['cmd']) && trim($cmd['cmd']) !== '') {
            $filtered[] = trim($cmd['cmd']);
        }
    }
    $save_data = implode("\n", $filtered);
    if (!empty($save_data)) {
        $save_data .= "\n";
    }
    if (FileCtl::file_write_contents($cmd_file, $save_data) !== false) {
        $_info_msg_ht .= '<p>○設定を更新保存しました</p>';
    } else {
        $_info_msg_ht .= '<p>×設定を更新保存できませんでした</p>';
    }
// [リストを空にする]ボタン
} elseif (!empty($_POST['submit_default'])) {
    if (!isset($_POST['csrfid']) || $_POST['csrfid'] != P2Util::getCsrfId()) {
        die('p2 error: 不正なポストです');
    }
    if (file_exists($cmd_file)) {
        if (@unlink($cmd_file)) {
            $_info_msg_ht .= '<p>○リストを空にしました</p>';
        } else {
            $_info_msg_ht .= '<p>×リストを空にできませんでした</p>';
        }
    } else {
        $_info_msg_ht .= '<p>○リストを空にしました</p>';
    }
}

// リスト読み込み
$content = FileCtl::file_read_contents($cmd_file);
if ($content === false) {
    $formdata = array();
} else {
    $formdata = array();
    foreach (explode("\n", $content) as $line) {
        $trimmed = trim($line);
        if ($trimmed !== '') {
            $formdata[] = $trimmed;
        }
    }
}

//=====================================================================
// プリント設定
//=====================================================================
$ptitle_top = '名前コマンド編集';
$ptitle = strip_tags($ptitle_top);

$csrfid = P2Util::getCsrfId();

//=====================================================================
// プリント
//=====================================================================
// ヘッダHTMLをプリント
P2Util::header_nocache();
echo $_conf['doctype'];
echo <<<EOP
<html lang="ja">
<head>
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <meta http-equiv="Content-Script-Type" content="text/javascript">
    {$_conf['extra_headers_ht']}
    <title>{$ptitle}</title>\n
EOP;

if (!$_conf['ktai']) {
    echo <<<EOP
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css.php?css=edit_conf_user&amp;skin={$skin_en}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <script type="text/javascript" src="js/basic.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}

$body_at = ($_conf['ktai']) ? $_conf['k_colors'] : ' onLoad="top.document.title=self.document.title;"';
echo <<<EOP
</head>
<body{$body_at}>\n
EOP;

if (!$_conf['ktai']) {
    echo <<<EOP
<p id="pan_menu"><a href="editpref.php">設定管理</a> &gt; {$ptitle_top}</p>\n
EOP;
}

// 情報メッセージ表示
if (!empty($_info_msg_ht)) {
    echo $_info_msg_ht;
    $_info_msg_ht = "";
}

$usage = <<<EOP
<ul>
<li>×: 削除</li>
<li>コマンド: （例：`!donguri`）</li>
</ul>
EOP;
if ($_conf['ktai']) {
    $usage = mb_convert_kana($usage, 'k');
}
echo <<<EOP
{$usage}
<form method="POST" action="{$_SERVER['SCRIPT_NAME']}" target="_self" accept-charset="{$_conf['accept_charset']}">
    {$_conf['k_input_ht']}
    <input type="hidden" name="detect_hint" value="◎◇　◇◎">
    <input type="hidden" name="csrfid" value="{$csrfid}">\n
EOP;

// PC用表示
if (!$_conf['ktai']) {
    echo <<<EOP
    <table class="edit_conf_user" cellspacing="0">
        <tr>
            <td align="center">×</td>
            <td align="center">コマンド</td>
        </tr>
        <tr class="group">
            <td colspan="2">新規登録</td>
        </tr>\n
EOP;
    $row_format = <<<EOP
        <tr>
            <td><input type="checkbox" name="dat[%1\$d][del]" value="1"></td>
            <td><input type="text" size="60" name="dat[%1\$d][cmd]" value="%2\$s"></td>
        </tr>\n
EOP;
    $htm['form_submit'] = <<<EOP
        <tr class="group">
            <td colspan="2" align="center">
                <input type="submit" name="submit_save" value="変更を保存する">
                <input type="submit" name="submit_default" value="リストを空にする" onClick="if (!window.confirm('リストを空にしてもよろしいですか？（やり直しはできません）')) {return false;}"><br>
            </td>
        </tr>\n
EOP;
// 携帯用表示
} else {
    echo "新規登録<br>\n";
    $row_format = <<<EOP
<fieldset>
コマンド:<input type="text" name="dat[%1\$d][cmd]" value="%2\$s"><br>
<input type="checkbox" name="dat[%1\$d][del]" value="1">×<br>
</fieldset>\n
EOP;
    $htm['form_submit'] = <<<EOP
<input type="submit" name="submit_save" value="変更を保存する"><br>
<input type="submit" name="submit_default" value="リストを空にする" onClick="if (!window.confirm('リストを空にしてもよろしいですか？（やり直しはできません）')) {return false;}"><br>
EOP;
}

// 新規登録行
printf($row_format, -1, '');

// 既存行の表示
if (!empty($formdata)) {
    foreach ($formdata as $k => $v) {
        printf($row_format, $k, p2h($v));
    }
}

echo $htm['form_submit'];

// PCならtableを閉じる
if (!$_conf['ktai']) {
    echo '</table>'."\n";
}

echo '</form>'."\n";

if ($_conf['ktai']) {
    if ($_conf['iphone']) {
        echo <<<EOP
<hr>
<div class="center">
<a href="editpref.php" class="button">戻る</a>
{$_conf['k_to_index_ht']}
</div>
EOP;
    } else {
        echo <<<EOP
<hr>
<div class="center">
<a href="editpref.php{$_conf['k_at_q']}"{$_conf['k_accesskey_at']['up']}>{$_conf['k_accesskey_st']['up']}設定編集</a>
{$_conf['k_to_index_ht']}
</div>
EOP;
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
