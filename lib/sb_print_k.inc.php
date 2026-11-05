<?php
/**
 * rep2 スレッドサブジェクト表示関数 携帯用
 * for subject.php
 */

// {{{ sb_print_k()

/**
 * sb_print - スレッド一覧を表示する (<tr>～</tr>)
 */
function sb_print_k($aThreadList)
{
    global $_conf, $sb_view, $p2_setting, $STYLE;
    global $sb_view;

    //=================================================

    if (!$aThreadList->threads) {
        if ($aThreadList->spmode == 'fav' && $sb_view == 'shinchaku') {
            echo '<p class="empty">お気にスレに新着なかったぽ</p>';
        } else {
            echo '<p class="empty">該当サブジェクトはなかったぽ</p>';
        }
        return;
    }

    // 変数 ================================================

    $only_one_bool = false;
    $ita_name_bool = false;
    $sortq_spmode = '';
    $sortq_host = '';
    $sortq_ita = '';

    // >>1
    if (strpos($aThreadList->bbs, 'news') !== false || $aThreadList->bbs == 'bizplus') {
        // 倉庫は除く
        if ($aThreadList->spmode != 'soko') {
            $only_one_bool = true;
        }
    }

    // 板名
    if ($aThreadList->spmode and $aThreadList->spmode != 'taborn' and $aThreadList->spmode != 'soko') {
        $ita_name_bool = true;
    }

    $norefresh_q = '&amp;norefresh=1';

    // ソート ==================================================

    // スペシャルモード時
    if ($aThreadList->spmode) {
        $sortq_spmode = "&amp;spmode={$aThreadList->spmode}";
        // あぼーんなら
        if ($aThreadList->spmode == "taborn" or $aThreadList->spmode == "soko") {
            $sortq_host = "&amp;host={$aThreadList->host}";
            $sortq_ita = "&amp;bbs={$aThreadList->bbs}";
        }
    } else {
        $sortq_host = "&amp;host={$aThreadList->host}";
        $sortq_ita = "&amp;bbs={$aThreadList->bbs}";
    }

    $midoku_sort_ht = "<a href=\"{$_conf['subject_php']}?sort=midoku{$sortq_spmode}{$sortq_host}{$sortq_ita}{$norefresh_q}{$_conf['k_at_a']}\">新着</a>";

    //=====================================================
    // ボディ
    //=====================================================

    // spmodeがあればクエリー追加
    if ($aThreadList->spmode) {
        $spmode_q = "&amp;spmode={$aThreadList->spmode}";
    }
    if ($aThreadList->spmode == 'palace' || $aThreadList->spmode == 'soko' || $aThreadList->spmode == 'taborn') {
        $show_unum = false;
    } else {
        $show_unum = true;
    }

    echo '<ul class="subject">';

    $i = 0;
    foreach ($aThreadList->threads as $aThread) {
        $i++;
        $midoku_ari = '';
        $htm = array('ita' => '', 'rnum' => '', 'unum' => '', 'sim' => '');

        $bbs_q = '&amp;bbs=' . $aThread->bbs;
        $key_q = '&amp;key=' . $aThread->key;
        $host_bbs_key_q = 'host=' . $aThread->host . $bbs_q . $key_q;
        $offline_q = '';

        if ($aThreadList->spmode!='taborn') {
            if (!$aThread->torder) {
                $aThread->torder = $i;
            }
        }

        // 新着レス数 =============================================
        // 既得済み
        if ($show_unum && $aThread->isKitoku()) {
            $htm['unum'] = "{$aThread->unum}";

            $classunum = 'num unread';

            // 新着あり
            if ($aThread->unum >= 1) {
                $midoku_ari = true;
                $classunum .= ' new';
            }

            // subject.txtにない時
            if (!$aThread->isonline) {
                $htm['unum'] = '-';
                $classunum .= ' offline';
            }

            $htm['unum'] = "<span class=\"{$classunum}\">{$htm['unum']}</span>";
        }

        // 新規スレ
        $classtitle = 'title';
        if ($aThread->new) {
            $classtitle .= ' new';
        }

        // 総レス数 =============================================
        $rescount_ht = "{$aThread->rescount}";

        // 板名 ============================================
        if ($ita_name_bool) {
            $ita_name = $aThread->itaj ? $aThread->itaj : $aThread->bbs;
            $ita_name_hd = p2h($ita_name);

            // 全角英数カナスペースを半角に
            if (!empty($_conf['mobile.save_packet'])) {
                $ita_name_hd = mb_convert_kana($ita_name_hd, 'rnsk');
            }

            //$htm['ita'] = "<a href=\"{$_conf['subject_php']}?host={$aThread->host}{$bbs_q}{$_conf['k_at_a']}\">{$ita_name_hd}</a>)";
            //$htm['ita'] = " ({$ita_name_hd})";
            $htm['ita'] = $ita_name_hd;
        }

        // torder(info) =================================================
        /*
        if ($aThread->fav) { //お気にスレ
            $torder_st = "<b>{$aThread->torder}</b>";
        } else {
            $torder_st = $aThread->torder;
        }
        $torder_ht = "<a id=\"to{$i}\" class=\"info\" href=\"info.php?{$host_bbs_key_q}{$_conf['k_at_a']}\">{$torder_st}</a>";
        */
        $torder_ht = $aThread->torder;

        // title =================================================
        $rescount_q = '&amp;rescount=' . $aThread->rescount;

        // dat倉庫 or 殿堂なら
        if ($aThreadList->spmode == 'soko' || $aThreadList->spmode == 'palace') {
            $rescount_q = '';
            $offline_q = '&amp;offline=true';
        // subject.txt にない場合
        } elseif (!$aThread->isonline) {
            $offline_q = '&amp;offline=true';
        }

        // タイトル未取得なら
        $ttitle_ht = $aThread->ttitle_ht;
        if (strlen($ttitle_ht) == 0) {
            // 見かけ上のタイトルなので携帯対応URLである必要はない
            $ttitle_ht = p2h($aThread->getMotoThread(''));
        }

        // 全角英数カナスペースを半角に
        if (!empty($_conf['mobile.save_packet'])) {
            $ttitle_ht = mb_convert_kana($ttitle_ht, 'rnsk');
        }

        // 総レス数 =============================================
        $htm['rnum'] = "<span class=\"num count\">{$rescount_ht}</span>";
        if ($aThread->similarity) {
            $htm['sim'] .= sprintf(' <span class="num score">%0.1f%%</span>', $aThread->similarity * 100);
        }

        $thre_url = "{$_conf['read_php']}?{$host_bbs_key_q}";
        $thre_url .= '&amp;ttitle_en=' . UrlSafeBase64::encode($aThread->ttitle);
        $thre_url .= "{$rescount_q}{$offline_q}{$_conf['k_at_a']}";

        // オンリー>>1
        $onlyone_url = "{$_conf['read_php']}?{$host_bbs_key_q}&amp;one=true&amp;k_continue=1{$_conf['k_at_a']}";
        if ($only_one_bool) {
            $one_ht = "<a href=\"{$onlyone_url}\">&gt;&gt;1</a>";
        }

        // >>1のみ, >>1から
        if (P2HostMgr::isHost2chs($aThreadList->host) && !$aThread->isKitoku()) {
            switch ($_conf['mobile.sb_show_first']) {
            case 1:
                $thre_url = $onlyone_url;
                break;
            case 2:
                $thre_url .= '&amp;ls=1-';
                break;
            default:
                $thre_url .= '&amp;ls=l' . $_conf['mobile.rnum_range'];
            }
        }

        //====================================================================================
        // スレッド一覧 table ボディ HTMLプリント <tr></tr>
        //====================================================================================

        //ボディ
        // information bubble用の隠しスレッド情報。
        // torderの指示子が"%d"でないのはmerge_favitaのため
        $thre_info = sprintf('[%s] %s (%01.1fレス/日)',
                             $aThread->torder,                 // 順序
                             date('y/m/d H:i', $aThread->key), // スレ立て日時
                             $aThread->dayres                  // 勢い。切り上げなし
                             );

        if ($htm['ita'] !== '') {
            $htm['ita'] = "<span class=\"ita\">{$htm['ita']}</span>";
        }

        echo <<<EOP
<li><a href="{$thre_url}"><span class="info">{$thre_info}</span> {$htm['unum']} <span class="{$classtitle}">{$ttitle_ht}</span> {$htm['rnum']} {$htm['sim']} {$htm['ita']}</a></li>\n
EOP;
    }

    echo '</ul>';
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
