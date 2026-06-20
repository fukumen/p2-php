<?php
/**
 * rep2 - スレッド表示 - ヘッダ部分 - iPhone用 for read.php
 */

require_once P2_LIB_DIR . '/get_info.inc.php';
require_once P2_LIB_DIR . '/spm_k.inc.php';

// {{{ 変数
// {{{ 基本変数

$motothre_url = $aThread->getMotoThread(false, '1-10');
$ttitle_en = UrlSafeBase64::encode($aThread->ttitle);
$ttitle_en_q = '&amp;ttitle_en=' . $ttitle_en;
$bbs_q = '&amp;bbs=' . $aThread->bbs;
$key_q = '&amp;key=' . $aThread->key;
$host_bbs_key_q = 'host=' . $aThread->host . $bbs_q . $key_q;
$offline_q = '&amp;offline=1';
$itaj_hd = p2h($aThread->itaj);
$rnum_range = $_conf['mobile.rnum_range'];
$thread_info = get_thread_info($aThread->host, $aThread->bbs, $aThread->key);

// }}}
// {{{ レスナビ設定

if ($do_filtering) {
    include P2_LIB_DIR . '/read_filter_k.inc.php';
} else {
    // {{{ ナビ - 前

    $before_rnum = max(1, $aThread->resrange['start'] - $rnum_range);
    if ($aThread->resrange['start'] == 1) {
        $read_navi_previous_isInvisible = true;
    } else {
        $read_navi_previous_isInvisible = false;
    }

    if ($read_navi_previous_isInvisible) {
        $read_navi_previous_url = '';
    } else {
        $read_navi_previous_url = "{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$before_rnum}-{$aThread->resrange['start']}n{$offline_q}{$_conf['k_at_a']}";
    }

    // }}}
    // {{{ ナビ - 次


    if (!empty($_GET['one'])) {
        $read_navi_next_isInvisible = false;
    } elseif ($aThread->resrange['to'] >= $aThread->rescount) {
        $aThread->resrange['to'] = $aThread->rescount;
        $read_navi_next_isInvisible = true;
    } else {
        $read_navi_next_isInvisible = false;
    }
    $after_rnum = $aThread->resrange['to'] + $rnum_range;

    if ($read_navi_next_isInvisible) {
        $read_navi_next_url = '';
    } else {
        $read_navi_next_url = "{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$aThread->resrange['to']}-{$after_rnum}n{$offline_q}&amp;nt={$newtime}{$_conf['k_at_a']}";
    }

    // }}}
}

// }}}
// {{{ ヘッダ要素

$_conf['extra_headers_ht'] .= <<<EOS
<script type="text/javascript" src="js/debug_log.js?{$_conf['p2_version_id']}"></script>
<script type="text/javascript" src="js/jquery.skOuterClick.js?{$_conf['p2_version_id']}"></script>
<script type="text/javascript" src="js/respopup_iphone.js?{$_conf['p2_version_id']}"></script>
<script type="text/javascript" src="js/preview_video.js?{$_conf['p2_version_id']}"></script>
EOS;
// ImageCache2
if ($_conf['expack.ic2.enabled']) {
    $_conf['extra_headers_ht'] .= <<<EOS
<link rel="stylesheet" type="text/css" href="css/ic2_iphone.css?{$_conf['p2_version_id']}">
<script type="text/javascript" src="js/json2.js?{$_conf['p2_version_id']}"></script>
<script type="text/javascript" src="js/ic2_iphone.js?{$_conf['p2_version_id']}"></script>
EOS;
}
// SPM
if ($_conf['expack.spm.enabled']) {
    $_conf['extra_headers_ht'] .= <<<EOS
<script type="text/javascript" src="js/spm_iphone.js?{$_conf['p2_version_id']}"></script>
EOS;
}
// ActiveMona
if ($_conf['expack.am.enabled']) {
    if ($STYLE['aa_textar_webfont']) {
        $_conf['extra_headers_ht'] .= <<<EOS
<style type="text/css">
@font-face {
  font-family: 'Textar';
  src: url('https://yamacraft.github.io/textar-font/fonts/textar-min.woff') format('woff'),
       url('https://yamacraft.github.io/textar-font/fonts/textar-min.ttf') format('truetype');
}
</style>
EOS;
    }
    if ($_conf['expack.am.autodetect_i']) {
        $_conf['extra_headers_ht'] .= <<<EOS
<script type="text/javascript" src="js/asciiart.js?{$_conf['p2_version_id']}"></script>
<style type="text/css">
.aMonaSW { cursor: pointer; }
div.res > div.message.ActiveMona {
	white-space: nowrap;
	line-height: 100%;
	font-family: {$STYLE['fontfamily_aa']};
	font-size: {$STYLE['aa_fontsize']};
}
</style>
EOS;
    }
}
// Limelight
if ($_conf['expack.aas.enabled'] || $_conf['expack.ic2.enabled']) {
    $_conf['extra_headers_ht'] .= <<<EOS
<link rel="stylesheet" type="text/css" href="css/limelight.css?{$_conf['p2_version_id']}">
<script type="text/javascript" src="js/limelight.js?{$_conf['p2_version_id']}"></script>
<script type="text/javascript">
// <![CDATA[
document.addEventListener('DOMContentLoaded', function(event) {
    var limelight;
    document.removeEventListener(event.type, arguments.callee, false);
    limelight = new Limelight({ 'savable': true, 'title': true });
    limelight.bind();
    window._IRESPOPG.callbacks.push(function(container) {
        limelight.bind(null, container, true);
    });
}, false);
// ]]>
</script>
EOS;
}
// 書き込み欄
if ($_conf['bottom_res_form']) {
    $_conf['extra_headers_ht'] .= "<script type=\"text/javascript\" src=\"js/post_form.js?{$_conf['p2_version_id']}\"></script>\n";
    if ($_conf['expack.editor.savedraft'] != '0') {
        $_conf['extra_headers_ht'] .= <<<EOP
    <script type="text/javascript" src="js/post_draft.js?{$_conf['p2_version_id']}"></script>
EOP;
    }
}
// coloredid
if ($_conf['coloredid.enable'] > 0) {
    if ($_conf['coloredid.click'] > 0) {
        $_conf['extra_headers_ht'] .= <<<EOS
<script type="text/javascript" src="js/colorLib.js?{$_conf['p2_version_id']}"></script>
<script type="text/javascript" src="js/coloredId.js?{$_conf['p2_version_id']}"></script>
EOS;
    }
    $_conf['extra_headers_ht'] .= <<<EOS
<style type="text/css">
@keyframes p2-hissi-blink {
	75% { visibility: hidden; }
}
@-webkit-keyframes p2-hissi-blink {
	75% { visibility: hidden; }
}
</style>
EOS;
}
// }}}
// {{{ preload
if ($is_readajax_active) {
    $_ra_host = StrCtl::toJavaScript($aThread->host);
    $_ra_bbs = StrCtl::toJavaScript($aThread->bbs);
    $_ra_key = StrCtl::toJavaScript($aThread->key);
    $_ra_ls = StrCtl::toJavaScript($aThread->ls);   // デバッグ用
    $_ra_prev = intval($_conf['smartphone.lazyload.prev']);
    $_ra_next = intval($_conf['smartphone.lazyload.next']);
    $_ra_num = intval($_conf['smartphone.preload.num']);
    $_ra_timing = intval($_conf['smartphone.preload.timing']);
    $_ra_readnum_timer = intval($_conf['smartphone.preload.readnum_timer']);
    $_ra_jumpjdg = intval($_conf['smartphone.preload.jumpjdg']);
    $_ra_fetch_timeout = intval($_conf['smartphone.readajax.timeout']);
    $_ra_realtimedisp_readnum = intval($_conf['smartphone.realtimedisp.readnum']);
    $_ra_rescount = intval($aThread->rescount);
    $_ra_datochiok = $aThread->datochiok ? 1 : 0;
    $_ra_rpp = 100;
    $_conf['extra_headers_ht'] .= <<<EOS
<script type="text/javascript" src="js/iphone_readajax.js?{$_conf['p2_version_id']}"></script>
<script type="text/javascript">
//<![CDATA[
var iutil_preload_config = {
    'host': '{$_ra_host}',
    'bbs': '{$_ra_bbs}',
    'key': '{$_ra_key}',
    'ls': '{$_ra_ls}',
    'prev': '{$_ra_prev}',
    'next': '{$_ra_next}',
    'num': '{$_ra_num}',
    'timing': '{$_ra_timing}',
    'readnum_timer': '{$_ra_readnum_timer}',
    'jumpjdg': '{$_ra_jumpjdg}',
    'fetch_timeout': '{$_ra_fetch_timeout}',
    'realtimedisp_readnum': '{$_ra_realtimedisp_readnum}',
    'rescount': '{$_ra_rescount}',
    'datochiok': '{$_ra_datochiok}',
    'rpp': '{$_ra_rpp}'
};
//]]>
</script>
EOS;
}

// }}}
// {{{ HTMLプリント

if ($is_readajax_active) {
    $body_class = 'nopad readajax';
} else {
    $body_class = 'nopad';
}
//!empty($_GET['nocache']) and P2Util::header_nocache();
echo $_conf['doctype'];
echo <<<EOP
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
<meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
{$_conf['extra_headers_ht']}
<title>{$ptitle_ht}</title>
</head>
<body class="{$body_class}">
<div class="ntoolbar" id="header">

EOP;

// {{{ 各種ボタン類
// 板に戻る
$escaped_url = "{$_conf['subject_php']}?{$host_bbs_key_q}{$_conf['k_at_a']}";
echo toolbar_i_back_button($itaj_hd, $escaped_url);

echo '<div id="toolbar_header">';

// レス検索
echo toolbar_i_showhide_button('img/glyphish/icons2/06-magnifying-glass.png', null, 'read_toolbar_filter');


// お気にスレ
if ($thread_info) {
    echo toolbar_i_fav_button('img/glyphish/icons2/28-star.png', null, $thread_info);
}

// その他ボタンとドロップダウンメニュー
$icon = "img/gp0-more.png";
$srcset = _toolbar_i_srcset($icon);
echo ' <span class="dropdown">';
//echo toolbar_i_dropdown_button('img/gp0-more.png', null);
echo <<<EOP
	<a class="dropdown-toggle" href="#" data-toggle="dropdown"><img src="{$icon}" {$srcset}></a>
  <ul class="dropdown-menu dropdown-menu-right popup">
EOP;

// >>1に移動
if (!$is_readajax_active) {
    $escaped_url = "{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls=1-{$rnum_range}{$offline_q}{$_conf['k_at_a']}";
    echo toolbar_i_menuItem('img/glyphish/icons2/63-runner.png', '&gt;&gt;1に移動', $escaped_url);
}

// 類似スレ検索
$escaped_url = "{$_conf['subject_php']}?{$host_bbs_key_q}&amp;itaj_en="
             . UrlSafeBase64::encode($aThread->itaj)
             . '&amp;method=similar&amp;word='
             . rawurlencode($aThread->ttitle_hc)
             . "&amp;refresh=1{$_conf['k_at_a']}";
echo toolbar_i_menuItem('img/glyphish/icons2/06-magnifying-glass.png', '類似スレを検索', $escaped_url);

// 殿堂入り
echo toolbar_i_palace_menuItem('img/glyphish/icons2/108-badge.png', '殿堂入り', $thread_info);

// IC2リンク、件数
if ($_conf['expack.ic2.enabled'] && $_conf['expack.ic2.thread_imagelink']) {
    $escaped_url = 'iv2.php?field=memo&amp;keyword='
        . rawurlencode($aThread->ttitle)
        . "&amp;session_no_close=1{$_conf['k_at_a']}";

    if ($_conf['expack.ic2.thread_imagecount']) {
        require_once P2EX_LIB_DIR . '/ic2_getcount.inc.php';
        $cnt = 0;
        try {
            $cnt = getIC2ImageCount($aThread->ttitle);
        }
        catch (Exception $e) {
            $cnt = -1;
        }
        if ($cnt == 0) {
            echo toolbar_i_disabled_menuItem('img/glyphish/icons2/42-photos.png',
                '画像一覧');
        } else {
            echo toolbar_i_menuItem('img/glyphish/icons2/42-photos.png',
                '画像一覧' . ($cnt < 0 ? '(?)' : "({$cnt})"), $escaped_url, ' target="_blank"');
        }
    } else {
        echo toolbar_i_menuItem('img/glyphish/icons2/42-photos.png',
            '画像一覧', $escaped_url, ' target="_blank"');
    }
}

// お気に入りセット
if ($thread_info && $_conf['expack.misc.multi_favs']) {
    echo toolbar_i_menuItem('img/glyphish/icons2/28-star.png', 'お気に入りセットに登録', '#' ,'data-toggle="modal" data-target="#favsetModal"');
}

echo '<li class="divider"></li>'; //仕切り

// スレッドあぼーん
echo toolbar_i_aborn_menuItem('img/glyphish/icons2/128-bone.png', 'スレをあぼーん', $thread_info);

// ログ削除
if (file_exists($aThread->keydat)) {
    $escaped_url = "info.php?{$host_bbs_key_q}{$ttitle_en_q}&amp;dele=1{$_conf['k_at_a']}";
    echo toolbar_i_menuItem('img/glyphish/icons2/64-zap.png', 'ログを削除', $escaped_url);
} else {
    echo toolbar_i_disabled_menuItem('img/glyphish/icons2/64-zap.png', 'ログを削除');
}

echo '</ul></span></div>';

unset($icon,$srcset);
// }}}
// {{{ お気にセットダイアログ
if ($thread_info && $_conf['expack.misc.multi_favs']) {
    $favset_body = '<table><tbody><tr>';
    for ($i = 1; $i <= $_conf['expack.misc.favset_num']; $i++) {
        $favset_body .= '<td>';
        $favset_body .= toolbar_i_fav_button('img/glyphish/icons2/28-star.png', '-', $thread_info, $i);
        $favset_body .= '</td>';
        if ($i % 5 === 0 && $i != $_conf['expack.misc.favset_num']) {
            $favset_body .= '</tr><tr>';
        }
    }
    $mod_cells = $_conf['expack.misc.favset_num'] % 5;
    if ($mod_cells) {
        $mod_cells = 5 - $mod_cells;
        for ($i = 0; $i < $mod_cells; $i++) {
            $favset_body .= '<td>&nbsp;</td>';
        }
    }
    $favset_body .= '</tr></tbody></table>';

echo <<<EOP
<div id="favsetModal" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content popup">
      <div class="modal-body">
      	<button type="button" class="close" style="color: #fff" data-dismiss="modal">&times;</button>
        {$favset_body}
      </div>
    </div>

  </div>
</div>
EOP;
}

// }}}
// {{{ レス検索フォーム

$htm['rf_hidden_fields'] = ResFilterElement::getHiddenFields($aThread->host, $aThread->bbs, $aThread->key);
$htm['rf_word_field'] = ResFilterElement::getWordField(array(
    'autocorrect' => 'off',
    'autocapitalize' => 'off',
    'class' => 'form-control',
));
$htm['rf_field_field'] = ResFilterElement::getFieldField();
$htm['rf_method_field'] = ResFilterElement::getMethodField();
$htm['rf_match_field'] = ResFilterElement::getMatchField();
$htm['rf_include_field'] = ResFilterElement::getIncludeField();

echo <<<EOP
<div id="read_toolbar_filter" class="extra">
<form id="read_filter" method="get" action="{$_conf['read_php']}" accept-charset="{$_conf['accept_charset']}">
{$htm['rf_hidden_fields']}
<div class="input-group form-group">
{$htm['rf_word_field']}
<span class="input-group-btn">
<button type="submit" id="submit1" name="submit_filter" value="検索" class="btn">検索</button>
</span>
</div>
<div class="form-group">
{$htm['rf_field_field']}に{$htm['rf_method_field']}を{$htm['rf_match_field']}
{$htm['rf_include_field']}
</div>
{$_conf['detect_hint_input_ht']}{$_conf['k_input_ht']}
</form>
</div>
<div class="ntoolbar" id="pager">
<table><tbody><tr>
<td colspan="4" id="thread_title"><div>
{$aThread->ttitle_hd}
</div></td>
EOP;

if (!$is_readajax_active) {
    echo '<td>';
    echo toolbar_i_standard_button('img/gp2-down.png', null, '#footer');
    echo '</td>';
}

echo <<<EOP
</tr></tbody></table></div>
EOP;

// }}}
// {{{ 各種通知

$info_ht = P2Util::getInfoHtml();
if (strlen($info_ht)) {
    echo "<div class=\"info\">{$info_ht}</div>";
}

// スレが板サーバになければ
if ($aThread->diedat) {
    echo '<div class="info">';
    if ($aThread->getdat_error_msg_ht) {
        echo $aThread->getdat_error_msg_ht;
    } else {
        echo $aThread->getDefaultGetDatErrorMessageHTML();
    }
    echo "<p><a href=\"{$motothre_url}\" target=\"_blank\">{$motothre_url}</a></p>";
    echo '</div>';
}

// フィルタヒット数
if ($do_filtering) {
    $htm['rf_field_names'] = array(
        ResFilter::FIELD_NUMBER => 'レス番号',
        ResFilter::FIELD_HOLE => '全体',
        ResFilter::FIELD_MESSAGE => '本文',
        ResFilter::FIELD_NAME => '名前',
        ResFilter::FIELD_MAIL => 'メール',
        ResFilter::FIELD_DATE => '日付',
        ResFilter::FIELD_ID => 'ID',
    );
    $hd['word'] = ResFilter::getWord('p2h');
    echo "<div class=\"hits\">{$htm['rf_field_names'][$resFilter->field]}に&quot;{$hd['word']}&quot;を";
    echo ($resFilter->match == ResFilter::MATCH_ON) ? '含む' : '含まない';
    if ($resFilter->include & ResFilter::INCLUDE_REFERENCES) {
        echo '+参照';
    }
    if ($resFilter->include & ResFilter::INCLUDE_REFERENCED) {
        echo '+逆参照';
    }
    echo ': <span id="searching">n</span>hit!</div>';
}

// }}}

if (isset($_GET['showbl']) && $_GET['showbl']) {
    echo  '<div class="hits">' . p2h($aThread->resrange['start']) . 'へのﾚｽ</div>';
}

echo <<<EOP
</div>

EOP;

if ($is_readajax_active) {
    echo '<div id="sentinel-top"></div>';
}
// end toolbar

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
