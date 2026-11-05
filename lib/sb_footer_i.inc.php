<?php
/**
 * rep2 - サブジェクト - iPhoneフッタ表示
 * for subject.php
 */

//=================================================
//フッタプリント
//=================================================


if (!empty($GLOBALS['wakati_words'])) {
    $paging_q .= '&amp;method=similar&amp;word=' . rawurlencode($GLOBALS['wakati_word']);
    $word_input_ht = '<input type="hidden" name="method" value="similar">';
    $word_input_ht .= '<input type="hidden" name="word" value="' . p2h($GLOBALS['wakati_word']) . '">';
} elseif ($word) {
    $paging_q .= '&amp;word=' . rawurlencode($word);
    $word_input_ht = '<input type="hidden" name="word" value="' . p2h($word) . '">';
    if (isset($sb_filter['method']) && $sb_filter['method'] == 'or') {
        $paging_q .= '&amp;method=or';
        $word_input_ht = '<input type="hidden" name="method" value="or">';
    }
} else {
    $word_input_ht = '';
}

if ($aThreadList->spmode == 'fav' && $sb_view == 'shinchaku') {
    $allfav_ht = <<<EOP
<div class=\"pager\"><a href="{$_conf['subject_php']}?spmode=fav{$norefresh_q}{$_conf['k_at_a']}">全てのお気にｽﾚを表示</a></div>
EOP;
} else {
    $allfav_ht = '';
}

// ページタイトル部分HTML設定 ====================================
if ($aThreadList->spmode == 'taborn') {
    $ptitle_ht = <<<EOP
<a href="{$ptitle_url}"><b>{$aThreadList->itaj}</b></a> (ｱﾎﾞﾝ中)
EOP;
} elseif ($aThreadList->spmode == 'soko') {
    $ptitle_ht = <<<EOP
<a href="{$ptitle_url}"><b>{$aThreadList->itaj}</b></a> (dat倉庫)
EOP;
} elseif (!empty($ptitle_url)) {
    $ptitle_ht = <<<EOP
<a href="{$ptitle_url}" class="nobutton"><b>{$ptitle_hd}</b></a>
EOP;
} else {
    $ptitle_ht = <<<EOP
<b>{$ptitle_hd}</b>
EOP;
}

// {{{ ナビ

if (!empty($_REQUEST['sb_view'])) {
    $paging_q = '&amp;sb_view=' . rawurlencode($_REQUEST['sb_view']);
    $sb_view_input_ht = '<input type="hidden" name="sb_view" value="' . p2h($_REQUEST['sb_view']) . '">';
} else {
    $sb_view_input_ht = '';
}

if (!empty($_REQUEST['rsort'])) {
    $paging_q .= '&amp;rsort=1';
    $sb_view_input_ht .= '<input type="hidden" name="rsort" value="1">';
}

if ($aThreadList->spmode == 'merge_favita' && $_conf['expack.misc.multi_favs']) {
    $paging_q .= $_conf['m_favita_set_at_a'];
    $sb_view_input_ht .= $_conf['m_favita_set_input_ht'];
}

if ($disp_navi['from'] == $disp_navi['end']) {
    $sb_range_on = $disp_navi['from'];
} else {
    $sb_range_on = "{$disp_navi['from']}-{$disp_navi['end']}";
}

$sb_all_pages = 1;

if (!$disp_navi['all_once']) {
    if ($_conf['mobile.sb_disp_range'] < 1) {
        $k_sb_navi_select_from_ht = '<option value="1">$_conf[&#39;mobile.sb_disp_range&#39;] の値が不正です</option>';
    } else {
        if ($disp_navi['offset'] % $_conf['mobile.sb_disp_range']) {
            $k_sb_navi_select_from_ht = "<option value=\"{$disp_navi['from']}\" selected>{$sb_range_on}</option>";
        } else {
            $k_sb_navi_select_from_ht = '';
        }

        /*$k_sb_navi_select_optgroup = $_conf['mobile.sb_disp_range'] * 5;
        if ($k_sb_navi_select_optgroup >= $sb_disp_all_num) {
            $k_sb_navi_select_optgroup = 0;
        }*/

        for ($i = 0; $i < $sb_disp_all_num; $i += $_conf['mobile.sb_disp_range']) {
            $j = $i + 1;
            $k = $i + $_conf['mobile.sb_disp_range'];
            if ($k > $sb_disp_all_num) {
                $k = $sb_disp_all_num;
            }

            /*if ($k_sb_navi_select_optgroup && $i % $k_sb_navi_select_optgroup == 0) {
                if ($i) {
                    $k_sb_navi_select_from_ht .= '</optgroup>';
                }
                $k_sb_navi_select_from_ht .= "<optgroup label=\"{$j}-\">";
            }*/

            $l = ceil($j / $_conf['mobile.sb_disp_range']);

            if ($j == $disp_navi['from']) {
                $k_sb_navi_select_from_ht .= "<option value=\"{$j}\" selected>{$l}</option>";
            } else {
                $k_sb_navi_select_from_ht .= "<option value=\"{$j}\">{$l}</option>";
            }

            $sb_all_pages = $l;
        }

        /*if ($k_sb_navi_select_optgroup) {
            $k_sb_navi_select_from_ht .= '</optgroup>';
        }*/
    }

    $k_sb_navi_ht = "<select class=\"form-control\" style=\"width:auto;display: inline;\" onchange=\"location.href = '{$_conf['subject_php']}?{$paging_q}&amp;from=' + this.options[this.selectedIndex].value + '{$_conf['k_at_a']}';\">{$k_sb_navi_select_from_ht}</select>";
}

if ($sb_all_pages < 2) {
    $sb_all_pages = 1;
    $k_sb_navi_ht = '<select class="form-control" style="width:auto;display: inline;"><option>1</option></select>';
}

// }}}
// {{{ お気にスレセット切替

if ($aThreadList->spmode == 'fav' && $_conf['expack.misc.multi_favs']) {
    $switchfavlist_ht = '<div>' . FavSetManager::makeFavSetSwitchForm(
        'm_favlist_set', 'お気にスレ', null, null, false, array('spmode' => 'fav')
    ) . '</div>';
} else {
    $switchfavlist_ht = '';
}

// }}}
// {{{ ツールバーを表示

echo '<div class="ntoolbar" id="footer"><div class="ntoolbar" id="pager">';
echo '<table><tbody><tr>';

// {{{ ページャ

// 前のページ
echo '<td>';
if ($disp_navi['from'] > 1) {
    $escaped_url = "{$_conf['subject_php']}?{$paging_q}&amp;from={$disp_navi['mae_from']}{$_conf['k_at_a']}";
    echo toolbar_i_standard_button('img/gp3-prev.png', null, $escaped_url);
} else {
    echo toolbar_i_disabled_button('img/gp3-prev.png', null);
}
echo '</td>';

// ページ番号を直接指定
echo '<td colspan="2">';
echo "{$k_sb_navi_ht}<span class=\"large\">/{$sb_all_pages}</span>";
echo '</td>';

// 次のページ
echo '<td>';
if ($disp_navi['tugi_from'] <= $sb_disp_all_num) {
    $escaped_url = "{$_conf['subject_php']}?{$paging_q}&amp;from={$disp_navi['tugi_from']}{$_conf['k_at_a']}";
    echo toolbar_i_standard_button('img/gp4-next.png', null, $escaped_url);
} else {
    echo toolbar_i_disabled_button('img/gp4-next.png', null);
}
echo '</td>';

// 上へ
echo '<td>';
echo toolbar_i_standard_button('img/gp1-up.png', null, '#header');
echo '</td>';

// }}}

echo '</tr></tbody></table></div>';

// {{{ その他ボタン類

echo '<table><tbody><tr>';

// あぼーん中のスレッド一覧を開く
echo '<td>';
if ($ta_num) {
    $escaped_url = "{$_conf['subject_php']}?{$host_bbs_q}{$norefresh_q}&amp;spmode=taborn{$_conf['k_at_a']}";
    echo toolbar_i_badged_button('img/glyphish/icons2/128-bone.png', 'あぼーんｽﾚ', $escaped_url, $ta_num);
}  else {
    echo toolbar_i_disabled_button('img/glyphish/icons2/128-bone.png', 'あぼーんｽﾚ');
}
echo '</td>';

// dat倉庫を開く
echo '<td>';
if (!$aThreadList->spmode || $aThreadList->spmode == 'taborn') {
    $escaped_url = "{$_conf['subject_php']}?{$host_bbs_q}{$norefresh_q}&amp;spmode=soko{$_conf['k_at_a']}";
    echo toolbar_i_standard_button('img/glyphish/icons2/33-cabinet.png', 'dat倉庫', $escaped_url);
} else {
    echo toolbar_i_disabled_button('img/glyphish/icons2/33-cabinet.png', 'dat倉庫');
}
echo '</td>';

// トップに戻る
echo '<td>';
echo toolbar_i_standard_button('img/glyphish/icons2/53-house.png', 'TOP', "index.php{$_conf['k_at_q']}");
echo '</td>';

// アクション
echo '<td>';
echo toolbar_i_action_board_button('img/glyphish/icons2/12-eye.png', 'アクション', $aThreadList);
echo '</td>';

// 新しいスレッドを立てる
echo '<td>';
if (!$aThreadList->spmode) {
    $escaped_url = "post_form.php?{$host_bbs_q}&amp;newthread=1{$_conf['k_at_a']}";
    echo toolbar_i_standard_button('img/glyphish/icons2/08-chat.png', 'スレ立て', $escaped_url);
} else {
    echo toolbar_i_disabled_button('img/glyphish/icons2/08-chat.png', 'スレ立て');
}
echo '</td>';

// }}}

echo '</tr></tbody></table>';
echo '</div>';

// }}}

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
