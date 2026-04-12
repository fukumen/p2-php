<?php
/**
 * rep2 - どんぐり管理
 */

require_once __DIR__ . '/../init.php';

if (!$_conf['donguri_use']) {
    exit;
}

require_once P2_LIB_DIR . '/donguri.inc.php';

$_login->authorize();

// {{{ どんぐり確認

if (isset($_GET['mode']) && $_GET['mode'] === 'check') {
    $msg = Donguri::check_base();

    header('Content-Type: text/html; charset=Shift_JIS');
    echo Donguri::get_status_text() . $msg;
    exit;
} elseif (isset($_GET['mode']) && $_GET['mode'] === 'login') {
    $msg = Donguri::login_base();

    header('Content-Type: text/html; charset=Shift_JIS');
    echo Donguri::get_status_text() . $msg;
    exit;
} elseif (isset($_GET['mode']) && $_GET['mode'] === 'logout') {
    $msg = Donguri::logout_base();

    header('Content-Type: text/html; charset=Shift_JIS');
    echo Donguri::get_status_text() . $msg;
    exit;
} elseif (isset($_GET['mode']) && $_GET['mode'] === 'get_donguri') {
    $acorn = Donguri::get_donguri();

    P2Util::header_nocache();
    header('Content-Type: text/html; charset=Shift_JIS');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="Shift_JIS">
<title>どんぐり</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
<?php
    if ($acorn) {
        $js = "document.cookie = \"acorn=" . $acorn . "; domain=." . $_conf['2ch_domain'] . "; path=/;";
        $js .= "\";";
        $bookmarklet = "javascript:(function(){" . $js . "alert('acorn set');})();";
        $bookmarklet_link = htmlspecialchars($bookmarklet, ENT_QUOTES, 'Shift_JIS');
?>
<h3>rep2の警備員○のどんぐり基地を頑張って見る方法</h3>
<p><b>注意：ブラウザのクッキーにどんぐりがある場合、手順2のどんぐりシステムのログアウトにより削除されます。削除されたくない場合、手順2,3,4でシークレットモードなどを使ってください。</b></p>
<ol>
<li>「<a href="<?php echo $bookmarklet_link; ?>">どんぐり設定</a>」のリンクをブックマークバーにドラッグ＆ドロップし、ブックマークに登録してください。スマホなどドラッグ＆ドロップでブックマークに登録が出来ないブラウザでは適当なブックマークを登録後に編集しリンクにあるjavascriptに置き換えてください</li>
<li>「<a href="<?php echo P2Util::throughIme('https://donguri.' . $_conf['2ch_domain'] . '/'); ?>"<?php echo $_conf['ext_win_target_at']; ?>>どんぐり基地</a>」を開いてログインされていたら、ログアウトしてください</li>
<li>どんぐりシステムのログイン画面のはずです。ここで登録したブックマークレットをクリックするとrep2のの警備員○のどんぐりをセッションクッキーとして設定されます</li>
<li>この状態でどんぐり基地をリロード(F5)するとrep2の警備員○の情報が見えるはずです。確認が終わったらログアウトしてください。ログアウトすることでブラウザに登録されたどんぐりのクッキーが削除されます</li>
</ol>
<p>ブックマークレットにはどんぐりを含んでいるため、変化していると再利用出来ません。</p>
<?php
    } else {
        echo '<p>どんぐりがありません。</p>';
    }
?>
</body>
</html>
<?php
    exit;
} elseif (isset($_GET['mode']) && $_GET['mode'] === 'confirm') {
    $url = 'https://donguri.' . $_conf['2ch_domain'] . '/confirm?url=' . urlencode($_GET['url']) . '&date=' . urlencode($_GET['date']);
    header('Location: ' . $url);
    exit;
}

// }}}
// {{{ リダイレクト

if (!empty($_GET['redirect'])) {
    header('Location: ' . $_GET['redirect']);
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
} else {
    header('Location: index.php');
}
exit;

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
