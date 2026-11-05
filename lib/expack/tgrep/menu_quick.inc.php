<?php
/**
 * rep2expack -tGrep お気にリストメニュー
 */

if ($_conf['iphone']) {
    tgrep_print_quick_list_i();
} else {
    tgrep_print_quick_list();
}

// {{{ tgrep_read_quick_list()

/**
 * お気にリストを読み込む
 */
function tgrep_read_quick_list()
{
    global $_conf;

    $list = FileCtl::file_read_lines($_conf['expack.tgrep.quick_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($list)) {
        return array();
    }
    return $list;
}

// }}}
// {{{ tgrep_print_quick_list()

/**
 * PC用表示
 */
function tgrep_print_quick_list()
{
    global $_conf;

    $tgrep_quick_list = tgrep_read_quick_list();

    if (!defined('TGREP_SMARTLIST_PRINT_ONLY_LINKS')) {
        echo '<div class="menu_cate">' . "\n";
        echo '<b><a class="menu_cate" href="#" onclick="return showHide(\'c_tgrep_quick\');" target="_self">スレ一発検索</a></b>' . "\n";
        echo '[<a href="#" onclick="return tGrepAppendListInput(\'quick\',\'c_tgrep_quick\');" target="_self">＋</a>]' . "\n";
        echo '[<a href="#" onclick="return tGrepClearList(\'quick\',\'c_tgrep_quick\');" target="_self">空</a>]' . "\n";
        echo '<div class="itas" id="c_tgrep_quick">' . "\n";
    }
    if ($tgrep_quick_list) {
        foreach ($tgrep_quick_list as $tgrep_quick_query) {
            $tgrep_quick_query_en = rawurlencode($tgrep_quick_query);
            $tgrep_quick_query_ht = p2h($tgrep_quick_query);
            echo '<a class="fav" href="#" onclick="return tGrepRemoveListItem(\'quick\',\'c_tgrep_quick\',\'' . $tgrep_quick_query_en . '\');" target="_self">★</a>' . "\n";
            echo '<a href="tgrepc.php?Q=' . $tgrep_quick_query_en . '">' . $tgrep_quick_query_ht . '</a><br>' . "\n";
        }
    } else {
        echo "（なし）\n";
    }
    if (!defined('TGREP_SMARTLIST_PRINT_ONLY_LINKS')) {
        echo "</div>\n</div>\n";
    }
}

// }}}

// {{{ tgrep_print_quick_list_i()

/**
 * iPhone用表示
 */
function tgrep_print_quick_list_i()
{
    global $_conf;

    $tgrep_quick_list = tgrep_read_quick_list();

    if ($tgrep_quick_list) {
        foreach ($tgrep_quick_list as $tgrep_quick_query) {
            $tgrep_quick_query_en = rawurlencode($tgrep_quick_query);
            $tgrep_quick_query_ht = p2h($tgrep_quick_query);
            echo '<li><a href="tgrepc.php?iq=' . $tgrep_quick_query_en . '">' . $tgrep_quick_query_ht . '</a></li>' . "\n";
        }
    } else {
        echo '<li class="weight-n">（なし）</li>' . "\n";
    }
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
