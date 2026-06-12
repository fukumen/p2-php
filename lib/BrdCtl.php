<?php

// {{{ BrdCtl

/**
 * rep2 - BrdCtl -- 板リストコントロールクラス for menu.php
 *
 * @static
 */
class BrdCtl
{
    // {{{ read_brds()

    /**
     * boardを全て読み込む
     */
    static public function read_brds()
    {
        $brd_menus_dir = BrdCtl::read_brd_dir();
        $brd_menus_online = BrdCtl::read_brd_online();
        $brd_menus = array_merge($brd_menus_dir, $brd_menus_online);
        return $brd_menus;
    }

    // }}}
    // {{{ read_brd_dir()

    /**
     * boardディレクトリを走査して読み込む
     */
    static public function read_brd_dir()
    {
        global $_conf;
        $brd_menus = array();
        $brd_dir = $_conf['data_dir'] . '/board';

        // ディレクトリがない場合は新規で作成
        if (!file_exists($brd_dir)) {
            FileCtl::mkdirRecursive($brd_dir);
            if(!is_writable($brd_dir)){
                // 書き込み権限を得られなかった場合はパーミッションの注意喚起をする
                p2die("親ディレクトリのパーミッションを見直して下さい。");
            }
            return $brd_menus;
        }

        if ($cdir = @dir($brd_dir)) {
            // ディレクトリ走査
            while ($entry = $cdir->read()) {
                if ($entry[0] == '.') {
                    continue;
                }
                $filepath = $brd_dir.'/'.$entry;
                $brd_format = preg_match('/\.html?$/', $entry) ? 'html' : 'brd';
                if ($data = FileCtl::file_read_lines($filepath)) {
                    $aBrdMenu = new BrdMenu();    // クラス BrdMenu のオブジェクトを生成
                    $aBrdMenu->source = $entry;
                    $aBrdMenu->source_type = 'local';
                    $aBrdMenu->setBrdMatch($filepath, $brd_format);    // パターンマッチ形式を登録
                    $aBrdMenu->setBrdList($data);    // カテゴリーと板をセット
                    $brd_menus[] = $aBrdMenu;

                } else {
                    P2Util::pushInfoHtml("<p>p2 error: 板リスト {$entry} が読み込めませんでした。</p>");
                }
            }
            $cdir->close();
        }

        return $brd_menus;
    }

    // }}}
    // {{{ read_brd_online()

    /**
    * オンライン板リストを読込む
    */
    static public function read_brd_online()
    {
        global $_conf;

        $brd_menus = array();
        $isNewDL = false;

        if ($_conf['brdfile_online']) {
            $urls = array_map('trim', explode(',', $_conf['brdfile_online']));

            $downloadSpecs = array();

            foreach ($urls as $key => $brdfile_online) {
                if (empty($brdfile_online)) {
                    continue;
                }
                $cachefile = P2Util::cacheFileForDL($brdfile_online);
                if (empty($_GET['nr']) || !file_exists($cachefile)) {
                    $cache_time = $_conf['menu_dl_interval'] * 3600;
                    $downloadSpecs[$key] = array(
                        'url'        => $brdfile_online,
                        'localfile'  => $cachefile,
                        'cache_time' => $cache_time,
                    );
                }
            }

            $downloadResults = !empty($downloadSpecs) ? P2CurlMulti::fileDownloadParallel($downloadSpecs) : array();

            foreach ($urls as $key => $brdfile_online) {
                if (empty($brdfile_online)) {
                    continue;
                }

                $cachefile = P2Util::cacheFileForDL($brdfile_online);
                $source_host = P2Util::normalizeHostName(parse_url($brdfile_online, PHP_URL_HOST));

                $read_html_flag = false;
                $format = null;

                // DLした結果の判定
                $response = P2CurlMulti::getResponse($downloadResults, $key);
                if ($response && $response->getStatus() != 304) {
                    $contentType = $response->getHeader('content-type');
                    if (!empty($contentType)) {
                        if (is_array($contentType)) {
                            $contentType = $contentType[0];
                        }
                        if (preg_match('/text\/html|application\/xhtml\+xml/i', $contentType)) {
                            $isNewDL = true;
                            $format = 'html';
                        } elseif (preg_match('/application\/json/i', $contentType)) {
                            $isNewDL = true;
                            $format = 'json';
                        }
                    }
                }

                // 更新されていたら新規キャッシュ作成
                if ($isNewDL) {
                    // 検索結果がキャッシュされるのを回避
                    if (isset($GLOBALS['word']) && strlen($GLOBALS['word']) > 0) {
                        $_tmp = array($GLOBALS['word'], $GLOBALS['word_fm'], $GLOBALS['words_fm']);
                        $GLOBALS['word'] = null;
                        $GLOBALS['word_fm'] = null;
                        $GLOBALS['words_fm'] = null;
                    } else {
                        $_tmp = null;
                    }

                    //echo "NEW!<br>"; //
                    $aBrdMenu = new BrdMenu(); // クラス BrdMenu のオブジェクトを生成
                    $aBrdMenu->source = $source_host;
                    $aBrdMenu->source_type = 'online';
                    $aBrdMenu->makeBrdFile($cachefile, $format); // .p2.brdファイルを生成
                    $brd_menus[] = $aBrdMenu;
                    unset($aBrdMenu);

                    if ($_tmp) {
                        list($GLOBALS['word'], $GLOBALS['word_fm'], $GLOBALS['words_fm']) = $_tmp;
                        $brd_menus = array();
                    } else {
                        $read_html_flag = true;
                    }
                }

                if (file_exists($cachefile.'.p2.brd')) {
                    $cache_brd = $cachefile.'.p2.brd';
                } else {
                    $cache_brd = $cachefile;
                }

                if (!$read_html_flag) {
                    if ($data = FileCtl::file_read_lines($cache_brd)) {
                        $aBrdMenu = new BrdMenu(); // クラス BrdMenu のオブジェクトを生成
                        $aBrdMenu->source = $source_host;
                        $aBrdMenu->source_type = 'online';
                        $aBrdMenu->setBrdMatch($cache_brd, 'brd'); // パターンマッチ形式を登録
                        $aBrdMenu->setBrdList($data); // カテゴリーと板をセット
                        if ($aBrdMenu->num) {
                            $brd_menus[] = $aBrdMenu;
                        } else {
                            P2Util::pushInfoHtml("<p>p2 error: {$cache_brd} から板メニューを生成することはできませんでした。</p>");
                        }
                        unset($data, $aBrdMenu);
                    } else {
                        P2Util::pushInfoHtml("<p>p2 error: {$cachefile} は読み込めませんでした。</p>");
                    }
                }

                $isNewDL = false;
            }
        }

        return $brd_menus;
    }

    // }}}
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
