<?php
/*
	p2 - レス書き込みフォーム
*/

include_once './conf.inc.php';  // 基本設定ファイル読込
require_once './p2util.class.php';	// p2用のユーティリティクラス
require_once './dataphp.class.php';

authorize(); //ユーザ認証

//==================================================
// 変数
//==================================================
$_info_msg_ht = '';

$htm = array();

$fake_time = -10; // time を10分前に偽装
$time = time() - 9*60*60;
$time = $time + $fake_time * 60;

$bbs = isset($_GET['bbs']) ? $_GET['bbs'] : '';
$key = isset($_GET['key']) ? $_GET['key'] : '';
$host = isset($_GET['host']) ? $_GET['host'] : '';

$rescount = isset($_GET['rc']) ? $_GET['rc'] : 1;
$popup = isset($_GET['popup']) ? $_GET['popup'] : 0;

$itaj = P2Util::getItaName($host, $bbs);
if (!$itaj) { $itaj = $bbs; }

$ttitle_en = isset($_GET['ttitle_en']) ? $_GET['ttitle_en'] : '';
$ttitle = (strlen($ttitle_en) > 0) ? base64_decode($ttitle_en) : '';



// ■key.idxから名前とメールを読込み
$datdir_host = P2Util::datdirOfHost($host);
$key_idx = $datdir_host."/".$bbs."/".$key.".idx";
if ($lines = @file($key_idx)) {
	$line = explode('<>', rtrim($lines[0]));
	$line = array_map(create_function('$n', 'return htmlspecialchars($n, ENT_QUOTES);'), $line);
	$FROM = $line[7];
	$mail = $line[8];
}

// 前回のPOST失敗があれば呼び出し
$failed_post_file = P2Util::getFailedPostFilePath($host, $bbs, $key);
if ($cont_srd = DataPhp::getDataPhpCont($failed_post_file)) {
	$last_posted = unserialize($cont_srd);
	$last_posted = array_map('htmlspecialchars', $last_posted);
	//$addslashesS = create_function('$str', 'return str_replace("\'", "\\\'", $str);');
	//$last_posted = array_map($addslashesS, $last_posted);

	$htm['FROM'] = $last_posted['FROM'];
	$htm['mail'] = $last_posted['mail'];
	$htm['MESSAGE'] = $last_posted['MESSAGE'];
	$htm['subject'] = $last_posted['subject'];

	/*
	$htm['load_last_posted'] = <<<EOP
[<a href="javascript:void(0);" onClick="return loadLastPosted('{$last_posted['FROM']}', '{$last_posted['mail']}', '{$last_posted['MESSAGE']}');" title="要JavaScript">前回投稿失敗した内容を読み込む</a>]<br>
EOP;
	*/
}

// 2ch●書き込み
if (P2Util::isHost2chs($host) and file_exists($_conf['sid2ch_php'])) {
	$isMaruChar = "●";
} else {
	$isMaruChar = "";
}

if (!$_conf['ktai']) {
	$class_ttitle = ' class="thre_title"';
	$target_read = ' target="read"';
	$sub_size_at = ' size="40"';
	$name_size_at = ' size="19"';
	$mail_size_at = ' size="19"';
	$msg_cols_at = ' cols="'.$STYLE['post_msg_cols'].'"';
} else {
	$STYLE['post_msg_rows'] = 3;
}

// スレ立て
if ($_GET['newthread']) {
	$ptitle = "{$itaj} - 新規スレッド作成";
	// machibbs、JBBS@したらば なら
	if (P2Util::isHostMachiBbs($host) or P2Util::isHostJbbsShitaraba($host)) {
		$submit_value = "新規書き込み";
	// 2chなら
	} else {
		$submit_value = "新規スレッド作成";
	}
	$subject_ht = <<<EOP
<b><span{$class_ttitle}>タイトル</span></b>：<input type="text" name="subject"{$sub_size_at} value="{$_htm['subject']}"><br>
EOP;
	if ($_conf['ktai']) {
		$subject_ht = "<a href=\"{$_conf['subject_php']}?host={$host}&amp;bbs={$bbs}{$_conf['k_at_a']}\">{$itaj}</a><br>".$subject_ht;
	}
	$newthread_hidden_ht = "<input type=\"hidden\" name=\"newthread\" value=\"1\">";

// 書き込み
} else {
	$ptitle = "{$itaj} - レス書き込み";
	
	// machibbs、JBBS@したらば なら
	if (P2Util::isHostMachiBbs($host) or P2Util::isHostJbbsShitaraba($host)) {
		$submit_value = "書き込む";
	// 2chなら
	} else {
		$submit_value = "書き込む";
	}
	$ttitle_ht = <<<EOP
<p><b><a{$class_ttitle} href="{$_conf['read_php']}?host={$host}&amp;bbs={$bbs}&amp;key={$key}{$_conf['k_at_a']}"{$target_read}>{$ttitle}</a></b></p>
EOP;
	$newthread_hidden_ht = '';
}

$readnew_hidden_ht = !empty($_GET['from_read_new']) ? '<input type="hidden" name="from_read_new" value="1">' : '';

// Be.2ch
if (P2Util::isHost2chs($host) and $_conf['be_2ch_code'] && $_conf['be_2ch_mail']) {
	$htm['be2ch'] = '<input type="checkbox" id="post_be2ch" name="post_be2ch" value="1"><label for="post_be2ch">Be.2chのコードを送信</label><br>'."\n";
}

//==========================================================
// ■HTMLプリント
//==========================================================
if (!$_conf['ktai']) {
	$body_on_load = <<<EOP
 onLoad="setFocus('MESSAGE'); checkSage();"
EOP;
	$on_check_sage = 'onChange="checkSage();"';
	$sage_cb_ht=<<<EOP
<input id="sage" type="checkbox" onClick="mailSage();"><label for="sage">sage</label><br>
EOP;
}

P2Util::header_content_type();
if ($_conf['doctype']) { echo $_conf['doctype']; }
echo <<<EOHEADER
<html lang="ja">
<head>
	{$_conf['meta_charset_ht']}
	<meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
	<meta http-equiv="Content-Style-Type" content="text/css">
	<meta http-equiv="Content-Script-Type" content="text/javascript">
	<title>{$ptitle}</title>\n
EOHEADER;
if(!$_conf['ktai']){
	@include("style/style_css.inc"); // スタイルシート
	@include("style/post_css.inc"); // スタイルシート
echo <<<EOSCRIPT
	<script type="text/javascript" src="js/basic.js"></script>
	<script type="text/javascript" src="js/post_form.js"></script>
EOSCRIPT;
}
echo <<<EOP
</head>
<body{$body_on_load}>
EOP;

echo $_info_msg_ht;
$_info_msg_ht = "";

// 文字コード判定用文字列を先頭に仕込むことでmb_convert_variables()の自動判定を助ける
echo <<<EOP
{$ttitle_ht}
<form method="POST" action="./post.php" accept-charset="{$_conf['accept_charset']}">
	<input type="hidden" name="detect_hint" value="◎◇">
	{$subject_ht}
	{$isMaruChar}名前： <input id="FROM" name="FROM" type="text" value="{$htm['FROM']}"{$name_size_at}> 
	 E-mail : <input id="mail" name="mail" type="text" value="{$htm['mail']}"{$mail_size_at}{$on_check_sage}>
	{$sage_cb_ht}
	<textarea id="MESSAGE" name="MESSAGE" rows="{$STYLE['post_msg_rows']}"{$msg_cols_at} wrap="off">{$htm['MESSAGE']}</textarea>
	<input type="submit" name="submit" value="{$submit_value}"><br>
	{$htm['be2ch']}

	<input type="hidden" name="bbs" value="{$bbs}">
	<input type="hidden" name="key" value="{$key}">
	<input type="hidden" name="time" value="{$time}">
	
	<input type="hidden" name="host" value="{$host}">
	<input type="hidden" name="popup" value="{$popup}">
	<input type="hidden" name="rescount" value="{$rescount}">
	<input type="hidden" name="ttitle_en" value="{$ttitle_en}">
	{$newthread_hidden_ht}{$readnew_hidden_ht}
	{$_conf['k_input_ht']}
</form>
</body>
</html>
EOP;

?>