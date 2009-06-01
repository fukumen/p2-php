<?php
/**
 * rep2 - スレッド表示 -  ヘッダ部分 -  for read.php
 */

// 変数
$diedat_msg = "";

$info_st        = "情報";
$delete_st      = "削除";
$all_st         = "全部";
$prev_st        = "前";
$next_st        = "次";
$shinchaku_st   = "新着レスの表示";
$midoku_st      = "未読レスの表示";
$tuduki_st      = "続きを読む";
$moto_thre_st   = "元スレ";
$siml_thre_st   = "似スレ"; // "類似スレ"
$latest_st      = "最新";
$dores_st       = "レス";
$aborn_st       = "あぼん";

$motothre_url = $aThread->getMotoThread();
$ttitle_en = base64_encode($aThread->ttitle);
$ttitle_urlen = rawurlencode($ttitle_en);
$ttitle_en_q = "&amp;ttitle_en=" . $ttitle_urlen;
$bbs_q = "&amp;bbs=" . $aThread->bbs;
$key_q = "&amp;key=" . $aThread->key;
$popup_q = "&amp;popup=1";
$offline_q = "&amp;offline=1";

//=================================================================
// ヘッダ
//=================================================================

// レスナビ設定
$rnum_range = 100;
$latest_show_res_num = 50; // 最新XX

$read_navi_range = "";

//----------------------------------------------
// $read_navi_range -- 1- 101- 201-
for ($i = 1; $i <= $aThread->rescount; $i = $i + $rnum_range) {
    $offline_range_q = "";
    $ito = $i + $rnum_range - 1;
    if ($ito <= $aThread->gotnum) {
        $offline_range_q = $offline_q;
    }
    $read_navi_range = $read_navi_range . "<a href=\"{$_conf['read_php']}?host={$aThread->host}{$bbs_q}{$key_q}&amp;ls={$i}-{$ito}{$offline_range_q}\">{$i}-</a>\n";

}

//----------------------------------------------
// $read_navi_previous -- 前100
$before_rnum = $aThread->resrange['start'] - $rnum_range;
if ($before_rnum < 1) { $before_rnum = 1; }
if ($aThread->resrange['start'] == 1) {
    $read_navi_previous_isInvisible = true;
} else {
    $read_navi_previous_isInvisible = false;
}
//if ($before_rnum != 1) {
//    $read_navi_previous_anchor = "#r{$before_rnum}";
//} else {
    $read_navi_previous_anchor = '';
//}

if (!$read_navi_previous_isInvisible) {
    $read_navi_previous = "<a href=\"{$_conf['read_php']}?host={$aThread->host}{$bbs_q}{$key_q}&amp;ls={$before_rnum}-{$aThread->resrange['start']}{$offline_q}{$read_navi_previous_anchor}\">{$prev_st}{$rnum_range}</a>";
    $read_navi_previous_header = "<a href=\"{$_conf['read_php']}?host={$aThread->host}{$bbs_q}{$key_q}&amp;ls={$before_rnum}-{$aThread->resrange['start']}{$offline_q}#r{$aThread->resrange['start']}\">{$prev_st}{$rnum_range}</a>";
} else {
    $read_navi_previous = '';
    $read_navi_previous_header = '';
}

//----------------------------------------------
//$read_navi_next -- 次100
if ($aThread->resrange['to'] > $aThread->rescount) {
    $aThread->resrange['to'] = $aThread->rescount;
    //$read_navi_next_anchor = "#r{$aThread->rescount}";
    //$read_navi_next_isInvisible = true;
} else {
    //$read_navi_next_anchor = "#r{$aThread->resrange['to']}";
}
if ($aThread->resrange['to'] == $aThread->rescount) {
    $read_navi_next_anchor = "#r{$aThread->rescount}";
} else {
    $read_navi_next_anchor = '';
}
$after_rnum = $aThread->resrange['to'] + $rnum_range;

$offline_range_q = "";
if ($after_rnum <= $aThread->gotnum) {
    $offline_range_q = $offline_q;
}

//if (!$read_navi_next_isInvisible) {
$read_navi_next = "<a href=\"{$_conf['read_php']}?host={$aThread->host}{$bbs_q}{$key_q}&amp;ls={$aThread->resrange['to']}-{$after_rnum}{$offline_range_q}&amp;nt={$newtime}{$read_navi_next_anchor}\">{$next_st}{$rnum_range}</a>";
//}

//----------------------------------------------
// $read_footer_navi_new  続きを読む 新着レスの表示

if ($aThread->resrange['to'] == $aThread->rescount) {
    $read_footer_navi_new = "<a href=\"{$_conf['read_php']}?host={$aThread->host}{$bbs_q}{$key_q}&amp;ls={$aThread->rescount}-&amp;nt={$newtime}#r{$aThread->rescount}\" accesskey=\"r\">{$shinchaku_st}</a>";
} else {
    $read_footer_navi_new = "<a href=\"{$_conf['read_php']}?host={$aThread->host}{$bbs_q}{$key_q}&amp;ls={$aThread->resrange['to']}-{$offline_q}\" accesskey=\"r\">{$tuduki_st}</a>";
}


// レス番指定移動
$htm['goto'] = <<<GOTO
<form method="get" action="{$_conf['read_php']}" class="inline-form">
    <input type="hidden" name="host" value="{$aThread->host}">
    <input type="hidden" name="bbs" value="{$aThread->bbs}">
    <input type="hidden" name="key" value="{$aThread->key}">
    <input type="text" size="7" name="ls" value="{$aThread->ls}">
    {$_conf['k_input_ht']}
    <input type="submit" value="go">
</form>
GOTO;

//====================================================================
// HTMLプリント
//====================================================================
$sid_q = (defined('SID')) ? '&amp;'.strip_tags(SID) : '';

// ツールバー部分HTML =======

// お気にマーク設定
$similar_q = '&amp;itaj_en=' . rawurlencode(base64_encode($aThread->itaj)) . '&amp;method=similar&amp;word=' . rawurlencode($aThread->ttitle_hc) . '&amp;refresh=1';
$itaj_hd = htmlspecialchars($aThread->itaj, ENT_QUOTES);

if ($_conf['expack.misc.multi_favs']) {
    $favlist_titles = FavSetManager::getFavSetTitles('m_favlist_set');
    $toolbar_setfav_ht = 'お気に[';
    $favdo = (!empty($aThread->favs[0])) ? 0 : 1;
    $favdo_q = '&amp;setfav=' . $favdo;
    $favmark = $favdo ? '+' : '★';
    $favtitle = ((!isset($favlist_titles[0]) || $favlist_titles[0] == '') ? 'お気にスレ' : $favlist_titles[0]) . ($favdo ? 'に追加' : 'から外す');
    $setnum_q = '&amp;setnum=0';
    $toolbar_setfav_ht .= <<<EOP
<span class="favdo set0"><a href="info.php?host={$aThread->host}{$bbs_q}{$key_q}{$ttitle_en_q}{$favdo_q}{$setnum_q}{$sid_q}" target="info" onclick="return setFavJs('host={$aThread->host}{$bbs_q}{$key_q}{$ttitle_en_q}{$sid_q}', '{$favdo}', {$STYLE['info_pop_size']}, 'read', this, '0');" title="{$favtitle}">{$favmark}</a></span>
EOP;
    for ($i = 1; $i <= $_conf['expack.misc.favset_num']; $i++) {
        $favdo = (!empty($aThread->favs[$i])) ? 0 : 1;
        $favdo_q = '&amp;setfav=' . $favdo;
        $favmark = $favdo ? $i : '★';
        $favtitle = ((!isset($favlist_titles[$i]) || $favlist_titles[$i] == '') ? 'お気にスレ' . $i : $favlist_titles[$i]) . ($favdo ? 'に追加' : 'から外す');
        $setnum_q = '&amp;setnum=' . $i;
        $toolbar_setfav_ht .= <<<EOP
|<span class="favdo set{$i}"><a href="info.php?host={$aThread->host}{$bbs_q}{$key_q}{$ttitle_en_q}{$favdo_q}{$setnum_q}{$sid_q}" target="info" onclick="return setFavJs('host={$aThread->host}{$bbs_q}{$key_q}{$ttitle_en_q}{$sid_q}', '{$favdo}', {$STYLE['info_pop_size']}, 'read', this, '{$i}');" title="{$favtitle}">{$favmark}</a></span>
EOP;
    }
    $toolbar_setfav_ht .= ']';
} else {
    $favdo = (!empty($aThread->fav)) ? 0 : 1;
    $favdo_q = '&amp;setfav=' . $favdo;
    $favmark = $favdo ? '+' : '★';
    $favtitle = $favdo ? 'お気にスレに追加' : 'お気にスレから外す';
    $toolbar_setfav_ht = <<<EOP
<span class="favdo"><a href="info.php?host={$aThread->host}{$bbs_q}{$key_q}{$ttitle_en_q}{$favdo_q}{$sid_q}" target="info" onclick="return setFavJs('host={$aThread->host}{$bbs_q}{$key_q}{$ttitle_en_q}{$sid_q}', '{$favdo}', {$STYLE['info_pop_size']}, 'read', this);" title="{$favtitle}">お気に{$favmark}</a></span>
EOP;
}

$toolbar_right_ht = <<<EOTOOLBAR
            <a href="{$_conf['subject_php']}?host={$aThread->host}{$bbs_q}{$key_q}" target="subject" title="板を開く">{$itaj_hd}</a>
            <a href="info.php?host={$aThread->host}{$bbs_q}{$key_q}{$ttitle_en_q}" target="info" onclick="return OpenSubWin('info.php?host={$aThread->host}{$bbs_q}{$key_q}{$ttitle_en_q}{$popup_q}{$sid_q}',{$STYLE['info_pop_size']},0,0)" title="スレッド情報を表示">{$info_st}</a>
            {$toolbar_setfav_ht}
            <span><a href="info.php?host={$aThread->host}{$bbs_q}{$key_q}{$ttitle_en_q}&amp;dele=true" target="info" onclick="return deleLog('host={$aThread->host}{$bbs_q}{$key_q}{$ttitle_en_q}{$sid_q}', {$STYLE['info_pop_size']}, 'read', this);" title="ログを削除する">{$delete_st}</a></span>
<!--            <a href="info.php?host={$aThread->host}{$bbs_q}{$key_q}{$ttitle_en_q}&amp;taborn=2" target="info" onclick="return OpenSubWin('info.php?host={$aThread->host}{$bbs_q}&amp;key={$aThread->key}{$ttitle_en_q}&amp;popup=2&amp;taborn=2{$sid_q}',{$STYLE['info_pop_size']},0,0)" title="スレッドのあぼーん状態をトグルする">{$aborn_st}</a> -->
            <a href="{$motothre_url}" title="板サーバ上のオリジナルスレを表示">{$moto_thre_st}</a>
            <a href="{$_conf['subject_php']}?host={$aThread->host}{$bbs_q}{$key_q}{$similar_q}" target="subject" title="タイトルが似ているスレッドを検索">{$siml_thre_st}</a>
EOTOOLBAR;

//=====================================
echo $_conf['doctype'];
echo <<<EOP
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <meta http-equiv="Content-Script-Type" content="text/javascript">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    {$_conf['extra_headers_ht']}
    <title>{$ptitle_ht}</title>
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css.php?css=read&amp;skin={$skin_en}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <script type="text/javascript" src="js/basic.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/respopup.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/htmlpopup.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/ngabornctl.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/setfavjs.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/delelog.js?{$_conf['p2_version_id']}"></script>\n
EOP;

if ($_conf['link_youtube'] == 2 || $_conf['link_niconico'] == 2) {
    echo <<<EOP
    <script type="text/javascript" src="js/preview_video.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}
if ($_conf['expack.am.enabled']) {
    echo <<<EOP
    <script type="text/javascript" src="js/asciiart.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}
/*if ($_conf['expack.misc.async_respop']) {
    echo <<<EOP
    <script type="text/javascript" src="js/async.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}*/
if ($_conf['expack.spm.enabled']) {
    echo <<<EOP
    <script type="text/javascript" src="js/invite.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/smartpopup.js?{$_conf['p2_version_id']}"></script>\n
EOP;
}
if ($_conf['expack.ic2.enabled']) {
    echo <<<EOP
    <script type="text/javascript" src="js/json2.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/loadthumb.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/ic2_getinfo.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/ic2_popinfo.js?{$_conf['p2_version_id']}"></script>
    <link rel="stylesheet" type="text/css" href="css/ic2_popinfo.css?{$_conf['p2_version_id']}">\n
EOP;
}

$onload_script = '';

if ($_conf['bottom_res_form']) {
    if ($_conf['expack.editor.dpreview']) {
        echo "\t<link rel=\"stylesheet\" type=\"text/css\" href=\"css.php?css=prvw&amp;skin={$skin_en}\">\n";
    }
    echo "\t<script type=\"text/javascript\" src=\"js/post_form.js?{$_conf['p2_version_id']}\"></script>\n";
    $onload_script .= 'checkSage();';
}

if (empty($_GET['one'])) {
    $onload_script .= 'setWinTitle();';
}

echo <<<EOHEADER
    <script type="text/javascript">
    //<![CDATA[
    gIsPageLoaded = false;

    function pageLoaded()
    {
        gIsPageLoaded = true;
        {$onload_script}
    }

    (function(){
        if (typeof window.p2BindReady == 'undefined') {
            window.setTimeout(arguments.callee, 100);
        } else {
            window.p2BindReady(pageLoaded, 'js/defer/pageLoaded.js');
        }
    })();
    //]]>
    </script>\n
EOHEADER;

echo <<<EOP
</head>
<body><div id="popUpContainer"></div>\n
EOP;

echo $_info_msg_ht;
$_info_msg_ht = "";

// スレが板サーバになければ ============================
if ($aThread->diedat) {

    if ($aThread->getdat_error_msg_ht) {
        $diedat_msg = $aThread->getdat_error_msg_ht;
    } else {
        $diedat_msg = "<p><b>p2 info - 板サーバから最新のスレッド情報を取得できませんでした。</b></p>";
    }

    $motothre_popup = " onmouseover=\"showHtmlPopUp('{$motothre_url}',event,{$_conf['iframe_popup_delay']})\" onmouseout=\"offHtmlPopUp()\"";
    if ($_conf['iframe_popup'] == 1) {
        $motothre_ht = "<a href=\"{$motothre_url}\"{$_conf['bbs_win_target_at']}{$motothre_popup}>{$motothre_url}</a>";
    } elseif ($_conf['iframe_popup'] == 2) {
        $motothre_ht = "(<a href=\"{$motothre_url}\"{$_conf['bbs_win_target_at']}{$motothre_popup}>p</a>)<a href=\"{$motothre_url}\"{$_conf['bbs_win_target_at']}>{$motothre_url}</a>";
    } else {
        $motothre_ht = "<a href=\"{$motothre_url}\"{$_conf['bbs_win_target_at']}>{$motothre_url}</a>";
    }

    echo $diedat_msg;
    echo "<p>";
    echo  $motothre_ht;
    echo "</p>";
    echo "<hr>";

    // 既得レスがなければツールバー表示
    if (!$aThread->rescount) {
        echo <<<EOP
<table width="100%" style="padding:0px 0px 10px 0px;">
    <tr>
        <td align="left">
            &nbsp;
        </td>
        <td align="right">
            {$toolbar_right_ht}
        </td>
    </tr>
</table>
EOP;
    }
}


if ($aThread->rescount && empty($_GET['renzokupop'])) {
// レスフィルタ ===============================
    $selected_field = array('hole' => '', 'name' => '', 'mail' => '', 'date' => '', 'id' => '', 'msg' => '');
    $selected_field[($res_filter['field'])] = ' selected';

    $selected_match = array('on' => '', 'off' => '');
    $selected_match[($res_filter['match'])] = ' selected';

    // 拡張条件
    if ($_conf['enable_exfilter']) {
        $selected_method = array('and' => '', 'or' => '', 'just' => '', 'regex' => '');
        $selected_method[($res_filter['method'])] = ' selected';
        $select_method_ht = <<<EOP
    の
    <select id="method" name="method">
        <option value="or"{$selected_method['or']}>いずれか</option>
        <option value="and"{$selected_method['and']}>すべて</option>
        <option value="just"{$selected_method['just']}>そのまま</option>
        <option value="regex"{$selected_method['regex']}>正規表現</option>
    </select>
EOP;
    }

    $hd['word'] = htmlspecialchars($GLOBALS['word'], ENT_QUOTES);

    echo <<<EOP
<form id="header" method="GET" action="{$_conf['read_php']}" accept-charset="{$_conf['accept_charset']}" style="white-space:nowrap">
    <input type="hidden" name="bbs" value="{$aThread->bbs}">
    <input type="hidden" name="key" value="{$aThread->key}">
    <input type="hidden" name="host" value="{$aThread->host}">
    <input type="hidden" name="ls" value="all">
    <input type="hidden" name="offline" value="1">
    <select id="field" name="field">
        <option value="hole"{$selected_field['hole']}>全体で</option>
        <option value="name"{$selected_field['name']}>名前が</option>
        <option value="mail"{$selected_field['mail']}>メールが</option>
        <option value="date"{$selected_field['date']}>日付が</option>
        <option value="id"{$selected_field['id']}>IDが</option>
        <option value="msg"{$selected_field['msg']}>メッセージが</option>
    </select>
    <input id="word" name="word" value="{$hd['word']}" size="24">{$select_method_ht}
    を
    <select id="match" name="match">
        <option value="on"{$selected_match['on']}>含む</option>
        <option value="off"{$selected_match['off']}>含まない</option>
    </select>
    レスを
    <input type="submit" name="submit_filter" value="フィルタ表示">
    {$_conf['detect_hint_input_ht']}{$_conf['k_input_ht']}
</form>\n
EOP;
}

// {{{ p2フレーム 3ペインで開く

$htm['p2frame'] = <<<EOP
<script type="text/javascript">
//<![CDATA[
if (top == self) {
    document.writeln('<a href="index.php?url={$motothre_url}&amp;offline=1">p2フレーム 3ペインで開く<' + '/a> |');
}
//]]>
</script>\n
EOP;

// }}}

if (empty($_GET['renzokupop']) && ($aThread->rescount || (!empty($_GET['one']) && !$aThread->diedat))) {

    if (!empty($_GET['one'])) {
        $id_header = ' id="header"';
    } else {
        $id_header = '';
    }
    echo <<<EOP
<table{$id_header} width="100%" style="padding:0px 0px 10px 0px;">
    <tr>
        <td align="left">
            <a href="{$_conf['read_php']}?host={$aThread->host}{$bbs_q}{$key_q}&amp;ls=all">{$all_st}</a>
            {$read_navi_range}
            {$read_navi_previous_header}
            <a href="{$_conf['read_php']}?host={$aThread->host}{$bbs_q}{$key_q}&amp;ls=l{$latest_show_res_num}">{$latest_st}{$latest_show_res_num}</a> {$htm['goto']}
        </td>
        <td align="right">
            {$htm['p2frame']}
            {$toolbar_right_ht}
        </td>
        <td align="right">
            <a href="#footer">▼</a>
        </td>
    </tr>
</table>\n
EOP;

}


//if (!$_GET['renzokupop']) {
    echo "<h3 class=\"thread_title\">{$aThread->ttitle_hd}</h3>\n";
//}

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
