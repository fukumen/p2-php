<?php
/**
 * rep2expack - コマンドラインでdatをダウンロード
 */

// {{{ 初期設定

if (PHP_SAPI != 'cli') {
    die('CLI only!');
}

define('P2_CLI_RUN', 1);
define('P2_FETCH_SUBJECT_TXT_DEBUG', 0);
define('P2_FETCH_SUBJECT_TXT_DEBUG_OUTPUT_FILE', '/tmp/p2_fetch_subject_txt.log');

require __DIR__ . '/../init.php';

// }}}
// {{{ コマンドライン引数を取得

$getopt = new Console_Getopt();
$args = $getopt->readPHPArgv();
if (PEAR::isError($args)) {
    fwrite(STDERR, $args->getMessage() . PHP_EOL);
    exit(1);
}
array_shift($args);

$short_options = 'm:s:';
$long_options = array('mode=', 'set=');
$options = $getopt->getopt2($args, $short_options, $long_options);
if (PEAR::isError($options)) {
    fwrite(STDERR, $options->getMessage() . PHP_EOL);
    exit(1);
}

$mode = null;
$set = null;

foreach ($options[0] as $option) {
    switch ($option[0]) {
    case 'm':
    case '--mode':
        $mode = p2_fst_checkopt_mode($option[1]);
        break;
    case 's':
    case '--set':
        $set = p2_fst_checkopt_set($option[1]);
        break;
    }
}

if ($mode === null) {
    fwrite(STDERR, 'Option `mode\' is required.' . PHP_EOL);
    exit(1);
} elseif (PEAR::isError($mode)) {
    fwrite(STDERR, sprintf('Invalid mode was given (%s).%s', $mode->getMessage(), PHP_EOL));
    exit(1);
}

if ($set === null) {
    $set = 0;
} elseif (PEAR::isError($set)) {
    fwrite(STDERR, sprintf('Invalid set was given (%s).%s', $set->getMessage(), PHP_EOL));
    exit(1);
}

// }}}
// {{{ メイン

$ta_keys = array();
$ta_num = 0;
$aThreadList = new ThreadList();
$aThreadList->setSpMode($mode);

if ($mode == 'fav') {
    if ($set > 0) {
        $_conf['favlist_idx'] = $_conf['pref_dir'] . DIRECTORY_SEPARATOR . sprintf('p2_favlist%d.idx', $set);
    }
}

// ソースリスト読込
$lines = $aThreadList->readList();

//============================================================
// それぞれの行解析
//============================================================
//$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('FORLOOP');

$linesize = sizeof($lines);
$subject_txts = array();

for ($x = 0; $x < $linesize; $x++) {
    $aThread = new ThreadRead();

    $l = rtrim($lines[$x]);

// データ読み込み
// spmode
    switch ($aThreadList->spmode) {
    case "recent":  // 履歴
        $aThread->getThreadInfoFromExtIdxLine($l);
        $aThread->itaj = P2Util::getItaName($aThread->host, $aThread->bbs);
        if (!$aThread->itaj) {$aThread->itaj = $aThread->bbs;}
        break;
    case "res_hist":    // 書き込み履歴
        $aThread->getThreadInfoFromExtIdxLine($l);
        $aThread->itaj = P2Util::getItaName($aThread->host, $aThread->bbs);
        if (!$aThread->itaj) {$aThread->itaj= $aThread->bbs;}
        break;
    case "fav":     // お気に
        $aThread->getThreadInfoFromExtIdxLine($l);
        $aThread->itaj = P2Util::getItaName($aThread->host, $aThread->bbs);
        if (!$aThread->itaj) {$aThread->itaj = $aThread->bbs;}
        break;
    }

    // メモリ節約（特にmerge_favita）のため
    $lines[$x] = null;

    // hostかbbsかkeyが不明ならスキップ
    if (!($aThread->host && $aThread->bbs && $aThread->key)) {
        unset($aThread);
        continue;
    }

    $subject_id = $aThread->host . '/' . $aThread->bbs;

    // ここで一旦スレッドリストにまとめて、キャッシュもさせようかと思ったが、メモリ消費(750K→2M)が激しかったのでやめておいた。

    // }}}

    $aThread->setThreadPathInfo($aThread->host, $aThread->bbs, $aThread->key);

    // 既得スレッドデータをidxから取得
    $aThread->getThreadInfoFromIdx();

    //  subject.txt が未DLなら落としてデータを配列に格納
    if (!isset($subject_txts[$subject_id])) {
        $subject_txts[$subject_id] = array();

        $aSubjectTxt = new SubjectTxt($aThread->host, $aThread->bbs);
        $subject_txts[$subject_id] = $aSubjectTxt->subject_lines;
    }

    // スレ情報取得 =============================
    if (isset($subject_txts[$subject_id])) {

        $thread_key = (string)$aThread->key;
        $thread_key_len = strlen($thread_key);
        foreach ($subject_txts[$subject_id] as $l) {
            if (strncmp($l, $thread_key, $thread_key_len) == 0) {
                // subject.txt からスレ情報取得
                $aThread->getThreadInfoFromSubjectTxtLine($l);
                break;
            }
        }
    }

    // subjexctからrescountが取れなかった場合は、gotnumを利用する。
    if ((!$aThread->rescount) and $aThread->gotnum) {
        $aThread->rescount = $aThread->gotnum;
    }

    // 新着ありandスレッドの総レス数が既得レス数より多いとき
    if ($aThread->unum > 0 && $aThread->rescount > $aThread->gotnum) {
        //ダウンロードする
        fwrite(STDOUT, 'Downloading ' . $aThread->host . '/' . $aThread->bbs . '/' . $aThread->key . PHP_EOL);
        $aThread->downloadDat();
        
        //===========================================================
        // idxの値を更新
        //===========================================================
        if ($aThread->rescount) {
            if ($idx_lines = FileCtl::file_read_lines($aThread->keyidx, FILE_IGNORE_NEW_LINES)) {
                $data = explode('<>', $idx_lines[0]);
            } else {
                $data = array_fill(0, 12, '');
            }
            $sar = array($aThread->ttitle, $aThread->key, $data[2], $aThread->rescount, $aThread->modified,
                         $data[5], $data[6], $data[7], $data[8], $data[9],
                         $data[10], $data[11], $aThread->datochiok);
            P2Util::recKeyIdx($aThread->keyidx, $sar); // key.idxに記録
        }
    }

    // リストに追加
    $aThreadList->addThread($aThread);

    unset($aThread);
}


/// }}}
// {{{ 後処理

// 正常終了
exit(0);

// }}}
// {{{ p2_fst_checkopt_mode()

/**
 * モード名が正しければそのまま、正しくなければPEAR_Errorを返す
 *
 * @param string $mode
 * @return string|PEAR_Error
 */
function p2_fst_checkopt_mode($mode)
{
    switch ($mode) {
    case 'fav':
    case 'recent':
    case 'res_hist':
        return $mode;
    }
    return PEAR::raiseError($mode);
}

// }}}
// {{{ p2_fst_checkopt_set()

/**
 * セットIDが正しければ整数として、正しくなければPEAR_Errorを返す
 *
 * @param string $set
 * @return int|PEAR_Error
 */
function p2_fst_checkopt_set($set)
{
    global $_conf;

    if (!is_numeric($set)) {
        return PEAR::raiseError($set);
    }

    $set = (int)$set;
    if ($set == 0) {
        return $set;
    }

    if (!$_conf['expack.misc.multi_favs']) {
        return PEAR::raiseError('Multi favorites is not enabled.');
    }

    if ($set > $_conf['expack.misc.favset_num']) {
        return PEAR::raiseError("{$set}: Out of range.");
    }

    return $set;
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
