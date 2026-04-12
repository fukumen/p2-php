<?php
/**
 * rep2 - スレッド表示部分の初期表示
 * フレーム3分割画面、右下部分
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

// {{{ スレ指定フォーム

$explanation = '見たいスレッドのURLを入力して下さい。例：http://pc.2ch.net/test/read.cgi/mac/1034199997/';

// $defurl = getLastReadTreadUrl();
$defurl = '';
$ini_url_text = '';

$onclick_ht = <<<EOP
var url_v=document.forms["urlform"].elements["url_text"].value;
if(url_v=="" || url_v=="{$ini_url_text}"){
    alert("{$explanation}");
    return false;
}
EOP;
$onclick_ht = p2h($onclick_ht);
$htm['urlform'] = <<<EOP
    <form id="urlform" method="GET" action="{$_conf['read_php']}" target="read">
        スレURLを直接指定
        <input id="url_text" type="text" value="{$defurl}" name="url" size="60">
        <input type="submit" name="btnG" value="表示" onclick="{$onclick_ht}">
    </form>\n
EOP;

$bookmarkletUrl = "javascript:location='" . rtrim(dirname(P2Util::getMyUrl()), '/') . "/" . $_conf['read_php'] . "?url='+escape(location);";

$bookmarklet_url = P2Util::throughIme('http://akid.s17.xrea.com/p2puki/pukiwiki.php?Bookmarklet');

// }}}
// {{{ HTMLプリント

echo $_conf['doctype'];
echo <<<EOP
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    {$_conf['extra_headers_ht']}
    <title>rep2</title>
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
</head>
<body>
<br>
<div class="container">
    {$htm['urlform']}
    <hr>
    <ul>
        <li><a href="{$bookmarklet_url}"{$_conf['ext_win_target_at']}>ブックマークレット</a> 「<a href="$bookmarkletUrl">p2で読む</a>」</li>
    </ul>
</div>
</body>
</html>
EOP;

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
