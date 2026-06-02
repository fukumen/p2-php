<?php

// {{{ P2HostMgr

/**
 * rep2 - 鯖移転などのホスト名に関する機能を提供するクラス
 * インスタンスを作らずにクラスメソッドで利用する
 *
 * @create  2017/10/19
 * @static
 */
class P2HostMgr
{
    // {{{ properties

    /**
     * isHost2ch() のキャッシュ
     */
    static private $_hostIs2ch = array();

    /**
     * isHost5ch() のキャッシュ
     */
    static private $_hostIs5ch = array();

    /**
     * isHostBe2chNet() のキャッシュ
     */
    //static private $_hostIsBe2chNet = array();

    /**
     * isHostBbsPink() のキャッシュ
     */
    static private $_hostIsBbsPink = array();

    /**
     * isHostMachiBbs() のキャッシュ
     */
    static private $_hostIsMachiBbs = array();

    /**
     * isHostMachiBbsNet() のキャッシュ
     */
    static private $_hostIsMachiBbsNet = array();

    /**
     * isHostJbbsShitaraba() のキャッシュ
     */
    static private $_hostIsJbbsShitaraba = array();

    /**
     * isHostVip2ch()のキャッシュ
     */
    static private $_hostIsVip2ch = array();

    /**
     * isHost2chSc()のキャッシュ
     */
    static private $_hostIs2chSc = array();

    /**
     * isHostOpen2ch()のキャッシュ
     */
    static private $_hostIsOpen2ch = array();

    /**
     * isHostNext2ch()のキャッシュ
     */
    static private $_hostIsNext2ch = array();

    /**
     * isHostSuper2ch()のキャッシュ
     */
    static private $_hostIsSuper2ch = array();

    /**
     * isHostJikkyoOrg()のキャッシュ
     */
    static private $_hostIsJikkyoOrg = array();

    /**
     * 板-ホストの対応表
     *
     * @var array
     */
    static private $_map = null;

    // }}}
    // {{{ isHostExample

    /**
     * host が例示用ドメインなら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostExample($host)
    {
        return (bool)preg_match('/(?:^|\\.)example\\.(?:com|net|org|jp)$/i', $host);
    }

    // }}}
    // {{{ isHost2chs()

    /**
     * host が 2ch or 5ch or bbspink なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHost2chs($host)
    {
        return self::isHost2ch($host) || self::isHost5ch($host) || self::isHostBbsPink($host);
    }

    // }}}
    // {{{ isHostBe2chs()

    /**
     * host が be.2ch.net or be.5ch.net なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostBe2chs($host)
    {
        return self::isHostBe2chNet($host) || self::isHostBe5chNet($host);
    }

    // }}}
    // {{{ isNotUse2chsAPI()

    /**
     * host が API を用いなくても取得できる場合なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isNotUse2chsAPI($host)
    {
        return self::isNotUse2chAPI($host) || self::isNotUse5chAPI($host);
    }

    // }}}
    // {{{ isHost2ch()

    /**
     * host が 2ch なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHost2ch($host)
    {
        if (!array_key_exists($host, self::$_hostIs2ch)) {
            self::$_hostIs2ch[$host] = (bool)preg_match('<^\\w+\\.(?:2ch\\.net)$>', $host);
        }
        return self::$_hostIs2ch[$host];
    }

    // }}}
    // {{{ isHostBe2chNet()

    /**
     * host が be.2ch.net なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostBe2chNet($host)
    {
        return $host == 'be.2ch.net';
    }

    // }}}
    // {{{ isNotUse2chAPI()

    /**
     * host が API を用いなくても取得できる場合なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isNotUse2chAPI($host)
    {
        return ($host == 'qb5.2ch.net' || $host == 'carpenter.2ch.net');
    }

    // }}}
    // {{{ isHost5ch()

    /**
     * host が 5ch なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHost5ch($host)
    {
        global $_conf;
        if (!array_key_exists($host, self::$_hostIs5ch)) {
            $domain = preg_quote($_conf['2ch_domain'], '<');
            self::$_hostIs5ch[$host] = (bool)preg_match('<^\\w+\\.(?:5ch\\.net|' . $domain . ')$>', $host);
        }
        return self::$_hostIs5ch[$host];
    }

    // }}}
    // {{{ isHostHeadline()

    /**
     * host がヘッドラインなら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostHeadline($host)
    {
        global $_conf;
        return ($host == 'headline.' . $_conf['2ch_domain'] || $host == 'headline.5ch.net' || $host == 'headline.2ch.net');
    }

    // }}}
    // {{{ isHostBe5chNet()

    /**
     * host が be.2ch.net なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostBe5chNet($host)
    {
        global $_conf;
        return ($host == 'be.' . $_conf['2ch_domain']) || ($host == 'be.5ch.net');
    }

    // }}}
    // {{{ isNotUse5chAPI()

    /**
     * host が API を用いなくても取得できる場合なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isNotUse5chAPI($host)
    {
        global $_conf;
        return ($host == 'qb5.' . $_conf['2ch_domain'] || $host == 'carpenter.' . $_conf['2ch_domain']) || ($host == 'qb5.5ch.net' || $host == 'carpenter.5ch.net');
    }

    // }}}
    // {{{ isHostBbsPink()

    /**
     * host が bbspink なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostBbsPink($host)
    {
        if (!array_key_exists($host, self::$_hostIsBbsPink)) {
            self::$_hostIsBbsPink[$host] = (bool)preg_match('<^\\w+\\.bbspink\\.com$>', $host);
        }
        return self::$_hostIsBbsPink[$host];
    }

    // }}}
    // {{{ isHost2chSc()

    /**
     * host が 2ch.sc なら true を返す
     *
     * @param string $host
     * @return  boolean
     */
    static public function isHost2chSc($host)
    {
        if (!array_key_exists($host, self::$_hostIs2chSc)) {
            self::$_hostIs2chSc[$host] = (bool)preg_match('/\\.(2ch\\.sc)$/', $host);
        }
        return self::$_hostIs2chSc[$host];
    }

    // }}}
    // {{{ isHostOpen2ch()

    /**
     * host が おーぷん2ch なら true を返す
     *
     * @param string $host
     * @return  boolean
     */
    static public function isHostOpen2ch($host)
    {
        if (!array_key_exists($host, self::$_hostIsOpen2ch)) {
            self::$_hostIsOpen2ch[$host] = (bool)preg_match('/\\.(open2ch\\.net)$/', $host);
        }
        return self::$_hostIsOpen2ch[$host];
    }

    // }}}
    // {{{ isHostNext2ch()

    /**
     * host が next2ch なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostNext2ch($host)
    {
        if ($host === 'next2ch.net') {
            return true;
        }

        if (!array_key_exists($host, self::$_hostIsNext2ch)) {
            self::$_hostIsNext2ch[$host] = (bool)preg_match('/\\.(next2ch\\.net)$/', $host);
        }
        return self::$_hostIsNext2ch[$host];
    }

    // }}}
    // {{{ isHostSuper2ch()

    /**
     * host が super2ch なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostSuper2ch($host)
    {
        if ($host === 'super2ch.net') {
            return true;
        }

        if (!array_key_exists($host, self::$_hostIsSuper2ch)) {
            self::$_hostIsSuper2ch[$host] = (bool)preg_match('/\\.(super2ch\\.net)$/', $host);
        }
        return self::$_hostIsSuper2ch[$host];
    }

    // }}}
    // {{{ isHostMachiBbs()

    /**
     * host が machibbs なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostMachiBbs($host)
    {
        if ($host === "machi.to") {
            return true;
        }

        if (!array_key_exists($host, self::$_hostIsMachiBbs)) {
            self::$_hostIsMachiBbs[$host] = (bool)preg_match('<^\\w+\\.machi(?:bbs\\.com|\\.to)$>', $host);
        }
        return self::$_hostIsMachiBbs[$host];
    }

    // }}}
    // {{{ isHostMachiBbsNet()

    /**
     * host が machibbs.net まちビねっと なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostMachiBbsNet($host)
    {
        if (!array_key_exists($host, self::$_hostIsMachiBbsNet)) {
            self::$_hostIsMachiBbsNet[$host] = (bool)preg_match('<^\\w+\\.machibbs\\.net$>', $host);
        }
        return self::$_hostIsMachiBbsNet[$host];
    }

    // }}}
    // {{{ isHostJbbsShitaraba()

    /**
     * host が livedoor レンタル掲示板 : したらば なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostJbbsShitaraba($in_host)
    {
        if (!array_key_exists($in_host, self::$_hostIsJbbsShitaraba)) {
            if ($in_host == 'rentalbbs.livedoor.com') {
                self::$_hostIsJbbsShitaraba[$in_host] = true;
            } elseif (preg_match('<^jbbs\\.(?:shitaraba\\.(?:net|com)|livedoor\\.(?:com|jp))(?:/|$)>', $in_host)) {
                self::$_hostIsJbbsShitaraba[$in_host] = true;
            } else {
                self::$_hostIsJbbsShitaraba[$in_host] = false;
            }
        }
        return self::$_hostIsJbbsShitaraba[$in_host];
    }

    // }}}
    // {{{ adjustHostJbbs()

    /**
     * livedoor レンタル掲示板 : したらばのホスト名変更に対応して変更する
     *
     * @param   string $in_str ホスト名でもURLでもなんでも良い
     * @return  string
     */
    static public function adjustHostJbbs($in_str)
    {
        return preg_replace('<(^|/)jbbs\\.(?:shitaraba|livedoor)\\.(?:net|com)(/|$)>', '\\1jbbs.shitaraba.net\\2', $in_str, 1);
    }

    // }}}
    // {{{ normalizeTalkHost()

    /**
     * talk.jp 系ホストを正規化する
     *
     * @param   string $host
     * @return  string
     */
    static public function normalizeTalkHost($host)
    {
        if ($host === 'classic.talk-platform.com') {
            return 'talk.jp';
        }
        return $host;
    }

    // }}}
    // {{{ isHostTor()

    /**
     * host が tor 系板 なら true を返す
     *
     * @access public
     * @param string $host
     * @return boolean
     */
    static function isHostTor($host, $isGatewayMode = 99)
    {
        switch ($isGatewayMode) {
            case 0:
                $ret = (bool)preg_match('/\\.onion$/', $host);
                break;

            case 1:
                $ret = (bool)preg_match('/\\.(onion\\.cab|onion\\.city|onion\\.direct|onion\\.link|onion\\.nu|onion\\.to|onion\\.rip)$/', $host);
                break;

            default:
                $ret = (bool)preg_match('/\\.(onion\\.cab|onion\\.city|onion\\.direct|onion\\.link|onion\\.nu|onion\\.to|onion\\.rip|onion)$/', $host);
                break;
        }

        return $ret;
    }

    // }}}
    // {{{ isHostVip2ch()

    /**
     * host が vip2ch なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostVip2ch($host)
    {
        if (!array_key_exists($host, self::$_hostIsVip2ch)) {
            self::$_hostIsVip2ch[$host] = (bool)preg_match('<^\\w+\\.(?:vip2ch\\.com)$>', $host);
        }
        return self::$_hostIsVip2ch[$host];
    }

    // }}}
    // {{{ isHostJikkyoOrg()

    /**
     * host が jikkyo.org なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostJikkyoOrg($host)
    {
        if ($host === 'jikkyo.org' || $host === 'livech.sakura.ne.jp') {
            return true;
        }

        if (!array_key_exists($host, self::$_hostIsJikkyoOrg)) {
            self::$_hostIsJikkyoOrg[$host] = (bool)preg_match('/\\.(jikkyo\\.org)$/', $host);
        }
        return self::$_hostIsJikkyoOrg[$host];
    }

    // }}}
    // {{{ isHostTalk()

    /**
     * host が talk.jp なら true を返す
     *
     * @param string $host
     * @return bool
     */
    static public function isHostTalk($host)
    {
        if ($host === 'talk.jp') {
            return true;
        }
        return false;
    }

    // }}}
    // {{{ isUrlWikipediaJa()

    /**
     * URLがウィキペディア日本語版の記事ならtrueを返す
     */
    static public function isUrlWikipediaJa($url)
    {
        return (strncmp($url, 'http://ja.wikipedia.org/wiki/', 29) == 0);
    }

    // }}}
    // {{{ getCurrentHost()

    /**
     * 最新のホストを取得する
     *
     * @param   string  $host   ホスト名
     * @param   string  $bbs    板名
     * @param   bool    $autosync   移転を検出したときに自動で同期するか否か
     * @return  string  板に対応する最新のホスト
     */
    static public function getCurrentHost($host, $bbs, $autosync = true)
    {
        static $synced = false;

        // マッピング読み込み
        $map = self::_getMapping();
        if (!$map) {
            return $host;
        }
        $type = self::getHostGroupName($host);

        // チェック
        if (isset($map[$type]) && isset($map[$type][$bbs])) {
            $new_host = $map[$type][$bbs]['host'];
            if ($host != $new_host && $autosync && !$synced) {
                // 移転を検出したらお気に板、お気にスレ、最近読んだスレを自動で同期
                $msg_fmt = '<p>rep2 info: ホストの移転を検出しました。(%s/%s → %s/%s)<br>';
                $msg_fmt .= 'お気に板、お気にスレ、最近読んだスレを自動で同期します。</p>';
                P2Util::pushInfoHtml(sprintf($msg_fmt, $host, $bbs, $new_host, $bbs));
                self::syncFav();
                $synced = true;
            }
            $host = $new_host;
        }

        return $host;
    }

    // }}}
    // {{{ getItaName()

    /**
     * 板名LONGを取得する
     *
     * @param   string  $host   ホスト名
     * @param   string  $bbs    板名
     * @return  string  板メニューに記載されている板名
     */
    static public function getItaName($host, $bbs)
    {
        // マッピング読み込み
        $map = self::_getMapping();
        if (!$map) {
            return $bbs;
        }
        $type = self::getHostGroupName($host);

        // チェック
        if (isset($map[$type]) && isset($map[$type][$bbs])) {
            $itaj = $map[$type][$bbs]['itaj'];
        } else {
            $itaj = $bbs;
        }

        return $itaj;
    }

    // }}}
    // {{{ isRegisteredBbs()

    /**
     * 板がrep2に登録されているかどうか
     *
     * @param   string  $host   ホスト名
     * @param   string  $bbs    板名
     * @return  bool  rep2に追加されている板ならtrue
     */
    static public function isRegisteredBbs($host, $bbs)
    {
        global $_conf;

        $type = self::getHostGroupName($host);

        // dat破損防止のためitest.[25]ch.netは問答無用でfalse
        if($host == 'itest.' . $_conf['2ch_domain'] || $host == 'itest.5ch.net' || $host == 'itest.2ch.net') {
            return false;
        }

        // 登録無しでもrep2で扱える板はチェック無しでtrue
        if($host != $type) {
            return true;
        }

        // マッピング読み込み
        $map = self::_getMapping();
        if (!$map) {
            return false;
        }

        // チェック
        if (isset($map[$type]) && isset($map[$type][$bbs])) {
            return true;
        }

        // もし見つからなければお気に板の内容も確認(外部板が登録可能になっているため)
        if ($lines = FileCtl::file_read_lines($_conf['favita_brd'], FILE_IGNORE_NEW_LINES)) {
            foreach ($lines as $l) {
                if (preg_match("/^\t?(.+)\t(.+)\t(.+)\$/", $l, $matches)) {
                    if ($host == $matches[1])
                    {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    // }}}
    // {{{ syncBrd()

    /**
     * お気に板などのbrdファイルを同期する
     *
     * @param   string  $brd_path   brdファイルのパス
     * @return  void
     */
    static public function syncBrd($brd_path)
    {
        global $_conf;
        static $done = array();

        // {{{ 読込

        if (isset($done[$brd_path])) {
            return;
        }

        if (!($lines = FileCtl::file_read_lines($brd_path))) {
            return;
        }
        $map = self::_getMapping();
        if (!$map) {
            return;
        }
        $neolines = array();
        $updated = false;

        // }}}
        // {{{ 同期

        foreach ($lines as $line) {
            $setitaj = false;
            $data = explode("\t", rtrim($line, "\n"));
            $hoge = $data[0]; // 予備?
            $host = $data[1];
            $bbs  = $data[2];
            $itaj = $data[3];
            $type = self::getHostGroupName($host);

            if (isset($map[$type]) && isset($map[$type][$bbs])) {
                $newhost = $map[$type][$bbs]['host'];
                if ($itaj === '') {
                    $itaj = $map[$type][$bbs]['itaj'];
                    if ($itaj != $bbs) {
                        $setitaj = true;
                    } else {
                        $itaj = '';
                    }
                }
            } else {
                $newhost = $host;
            }

            if ($host != $newhost || $setitaj) {
                $neolines[] = "{$hoge}\t{$newhost}\t{$bbs}\t{$itaj}\n";
                $updated = true;
            } else {
                $neolines[] = $line;
            }
        }

        // }}}
        // {{{ 書込

        $brd_name = p2h(basename($brd_path));
        if ($updated) {
            self::_writeData($brd_path, $neolines);
            P2Util::pushInfoHtml(sprintf('<p class="info-msg">rep2 info: %s を同期しました。</p>', $brd_name));
        } else {
            P2Util::pushInfoHtml(sprintf('<p class="info-msg">rep2 info: %s は変更されませんでした。</p>', $brd_name));
        }
        $done[$brd_path] = true;

        // }}}
    }

    // }}}
    // {{{ syncIdx()

    /**
     * お気にスレなどのidxファイルを同期する
     *
     * @param   string  $idx_path   idxファイルのパス
     * @return  void
     */
    static public function syncIdx($idx_path)
    {
        global $_conf;
        static $done = array();

        // {{{ 読込

        if (isset($done[$idx_path])) {
            return;
        }

        if (!($lines = FileCtl::file_read_lines($idx_path))) {
            return;
        }
        $map = self::_getMapping();
        if (!$map) {
            return;
        }
        $neolines = array();
        $updated = false;

        // }}}
        // {{{ 同期

        foreach ($lines as $line) {
            $data = explode('<>', rtrim($line, "\n"));
            $host = $data[10];
            $bbs  = $data[11];
            $type = self::getHostGroupName($host);

            if (isset($map[$type]) && isset($map[$type][$bbs])) {
                $newhost = $map[$type][$bbs]['host'];
            } else {
                $newhost = $host;
            }

            if ($host != $newhost) {
                $data[10] = $newhost;
                $neolines[] = implode('<>', $data) . "\n";
                $updated = true;
            } else {
                $neolines[] = $line;
            }
        }

        // }}}
        // {{{ 書込

        $idx_name = p2h(basename($idx_path));
        if ($updated) {
            self::_writeData($idx_path, $neolines);
            P2Util::pushInfoHtml(sprintf('<p class="info-msg">rep2 info: %s を同期しました。</p>', $idx_name));
        } else {
            P2Util::pushInfoHtml(sprintf('<p class="info-msg">rep2 info: %s は変更されませんでした。</p>', $idx_name));
        }
        $done[$idx_path] = true;

        // }}}
    }

    // }}}
    // {{{ syncFav()

    /**
     * お気に板、お気にスレ、最近読んだスレを同期する
     *
     * @return  void
     */
    static public function syncFav()
    {
        global $_conf;
        self::syncBrd($_conf['favita_brd']);
        self::syncIdx($_conf['favlist_idx']);
        self::syncIdx($_conf['recent_idx']);
    }

    // }}}
    // {{{ _getMapping()

    /**
     * 2ch公式メニューをパースし、板-ホストの対応表を作成する
     *
     * @return  array   site/bbs/(host,itaj) の多次元連想配列
     *                  ダウンロードに失敗したときは false
     */
    static private function _getMapping()
    {
        global $_conf;

        // {{{ 設定
        $map_cache_path = $_conf['cache_dir'] . '/host_bbs_map.txt';
        $map_cache_lifetime = 60 * 10; // 10分おきにキャッシュを再構築するが、BrdCtl側で最低30分はアクセスしない。

        // }}}
        // {{{ キャッシュ確認

        if (!is_null(self::$_map)) {
            return self::$_map;
        } elseif (file_exists($map_cache_path)) {
            $mtime = filemtime($map_cache_path);
            $expires = $mtime + $map_cache_lifetime;
            if (time() < $expires) {
                $map_cahce = file_get_contents($map_cache_path);
                self::$_map = unserialize($map_cahce);
                return self::$_map;
            }
        } else {
            FileCtl::mkdirFor($map_cache_path);
        }
        touch($map_cache_path);
        clearstatcache();

        // }}}
        // {{{ メニューをダウンロード
        $brd_menus_online = BrdCtl::read_brds();
        $map = array();

        foreach ($brd_menus_online as $a_brd_menu) {
            foreach ($a_brd_menu->categories as $cate) {
                if ($cate->num > 0) {
                    foreach ($cate->menuitas as $mita) {
                        $host = $mita->host;
                        $bbs  = $mita->bbs;
                        $itaj = $mita->itaj;
                        $type = self::getHostGroupName($host);
                        if (!isset($map[$type])) {
                            $map[$type] = array();
                        }
                        $map[$type][$bbs] = array('host' => $host, 'itaj' => $itaj);
                    }
                }
            }
        }
        unset ($brd_menus_online);
        // }}}
        // {{{ キャッシュする

        $map_cache = serialize($map);
        if (FileCtl::file_write_contents($map_cache_path, $map_cache) === false) {
            p2die("cannot write file. ({$map_cache_path})");
        }

        // }}}

        return (self::$_map = $map);
    }

    // }}}
    // {{{ _writeData()

    /**
     * 更新後のデータを書き込む
     *
     * @param   string  $path   書き込むファイルのパス
     * @param   array   $neolines   書き込むデータの配列
     * @return  void
     */
    static private function _writeData($path, $neolines)
    {
        if (is_array($neolines) && count($neolines) > 0) {
            $cont = implode('', $neolines);
        /*} elseif (is_scalar($neolines)) {
            $cont = strval($neolines);*/
        } else {
            $cont = '';
        }
        if (FileCtl::file_write_contents($path, $cont) === false) {
            p2die("cannot write file. ({$path})");
        }
    }
    // }}}
    // {{{ getHostGroupName()

    /**
     * ホストに対応するお気に板・お気にスレグループ名を取得する
     *
     * @param string $host
     * @return void
     */
    static public function getHostGroupName($host)
    {
        if (self::isHost2chs($host)) {
            return '2channel';
        } elseif (self::isHostMachiBbs($host)) {
            return 'machibbs';
        } elseif (self::isHostJbbsShitaraba($host)) {
            return 'shitaraba';
        } elseif (self::isHostVip2ch($host)) {
            return 'vip2ch';
        } else {
            return $host;
        }
    }

    // }}}
    // {{{ normalize5chHost()

    /**
     * 2ch.net を $_conf['2ch_domain'] に置き換える
     *
     * @param string $host
     * @return string
     */
    static public function normalize5chHost($host)
    {
        global $_conf;
        if ($_conf['2ch_to_5ch']) {
            return preg_replace('/\.(2ch\.net|5ch\.net)$/', '.' . $_conf['2ch_domain'], $host);
        }
        return $host;
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
