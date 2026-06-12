<?php

// {{{ SubjectTxt

/**
 * SubjectTxtクラス
 */
class SubjectTxt
{
    // {{{ properties

    public $host;
    public $bbs;
    public $subject_url;
    public $subject_file;
    public $subject_lines;
    public $storage;

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     */
    public function __construct($host, $bbs)
    {
        global $_conf;
        $this->host = P2HostMgr::normalize5chHost($host);
        $this->bbs =  $bbs;
        $this->storage = 'file';

        $this->subject_file = P2Util::datDirOfHostBbs($host, $bbs) . 'subject.txt';
        $this->subject_url = self::getSubjectUrl($this->host, $this->bbs);

        // subject.txtをダウンロード＆セットする
        $this->dlAndSetSubject();
    }

    // }}}
    // {{{ dlAndSetSubject()

    /**
     * subject.txtをダウンロード＆セットする
     *
     * @return boolean セットできれば true、できなければ false
     */
    public function dlAndSetSubject()
    {
        $cont = $this->downloadSubject();
        if ($this->setSubjectLines($cont)) {
            return true;
        } else {
            return false;
        }
    }

    // }}}
    // {{{ downloadSubject()

    /**
     * subject.txtをダウンロードする
     *
     * @return string subject.txt の中身
     */
    public function downloadSubject()
    {
        global $_conf;

        if ($this->storage === 'file') {
            FileCtl::mkdirFor($this->subject_file); // 板ディレクトリが無ければ作る

            if (file_exists($this->subject_file)) {
                if (!empty($_REQUEST['norefresh']) || (empty($_REQUEST['refresh']) && isset($_REQUEST['word']))) {
                    return;    // 更新しない場合は、その場で抜けてしまう
                } elseif (!empty($GLOBALS['expack.subject.multi-threaded-download.done'])) {
                    return;    // 並列ダウンロード済の場合も抜ける
                } elseif (empty($_POST['newthread']) and $this->isSubjectTxtFresh()) {
                    return;    // 新規スレ立て時でなく、更新が新しい場合も抜ける
                }
                $modified = http_date(filemtime($this->subject_file));
            } else {
                $modified = false;
            }
        }

        // DL
        try {
            $req = P2Commun::createHTTPRequest($this->subject_url, HTTP_Request2::METHOD_GET);
            $modified && $req->setHeader("If-Modified-Since", $modified);

            $response = P2Commun::getHTTPResponse($req);
            $body = '';
            $code = $response->getStatus();
            if ($code == 302) {
                // ホストの移転を追跡
                $new_host = P2HostMgr::getCurrentHost($this->host, $this->bbs);
                if ($new_host != $this->host) {
                    $aNewSubjectTxt = new SubjectTxt($new_host, $this->bbs);
                    $body = $aNewSubjectTxt->downloadSubject();
                    return $body;
                }
            } elseif ($code == 200 || $code == 206) {
                //var_dump($req->getResponseHeader());
                $body = $response->getBody();
                try {
                    $body = self::convertSubjectBody($this->host, $body);
                } catch (Exception $e) {
                    p2die($e->getMessage());
                }
                if (FileCtl::file_write_contents($this->subject_file, $body) === false) {
                    p2die('cannot write file');
                }
            } elseif ($code == 304) {
                // touchすることで更新インターバルが効くので、しばらく再チェックされなくなる
                // （変更がないのに修正時間を更新するのは、少し気が進まないが、ここでは特に問題ないだろう）
                if ($this->storage === 'file') {
                    touch($this->subject_file);
                }
            } elseif ($code == 301) {
                $location = $response->getHeader("location");
                if ($location && P2Util::isHttpToHttpsUpgrade($this->subject_url, $location)) {
                    $this->subject_url = $location;
                    return $this->downloadSubject();
                }
            } else {
                $error_msg = $code;
            }
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
        }

        if (isset($error_msg) && strlen($error_msg) > 0) {
            $url_t = P2Util::throughIme($this->subject_url);
            $info_msg_ht = "<p class=\"info-msg\">Error: {$error_msg}<br>";
            $info_msg_ht .= "rep2 info: <a href=\"{$url_t}\"{$_conf['ext_win_target_at']}>{$this->subject_url}</a> に接続できませんでした。</p>";
            P2Util::pushInfoHtml($info_msg_ht);
            $body = '';
        }

        return $body;
    }

    // }}}
    // {{{ isSubjectTxtFresh()

    /**
     * subject.txt が新鮮なら true を返す
     *
     * @return boolean 新鮮なら true。そうでなければ false。
     */
    public function isSubjectTxtFresh()
    {
        global $_conf;

        // キャッシュがある場合
        if (file_exists($this->subject_file)) {
            // キャッシュの更新が指定時間以内なら
            // clearstatcache();
            if (filemtime($this->subject_file) > time() - $_conf['sb_dl_interval']) {
                return true;
            }
        }

        return false;
    }

    // }}}
    // {{{ setSubjectLines()

    /**
     * subject.txt を読み込む
     *
     * 成功すれば、$this->subject_lines がセットされる
     *
     * @param string $cont これは eashm 用に渡している。
     * @return boolean 実行成否
     */
    public function setSubjectLines($cont = '')
    {
        $this->subject_lines = FileCtl::file_read_lines($this->subject_file);

        // JBBS@したらばなら重複スレタイを削除する
        if (P2HostMgr::isHostJbbsShitaraba($this->host)) {
            $this->subject_lines = array_unique($this->subject_lines);
        }

        if ($this->subject_lines) {
            return true;
        } else {
            return false;
        }
    }

    // }}}
    // {{{ getSubjectUrl()

    /**
     * ホストとBBS名から、適切なsubject.txt（またはAPI）のURLを生成する
     *
     * @param string $host
     * @param string $bbs
     * @return string
     */
    public static function getSubjectUrl($host, $bbs)
    {
        if (P2HostMgr::isHostTalk($host)) {
            $url = P2Util::selectScheme($host) . '://' . $host . '/api/boards/' . $bbs . '/threads';
        } else {
            $url = P2Util::selectScheme($host) . '://' . $host . '/' . $bbs . '/subject.txt';

            if (P2HostMgr::isHostJbbsShitaraba($host)) {
                $url = P2HostMgr::adjustHostJbbs($url);
            }
        }
        return $url;
    }

    // }}}
    // {{{ convertSubjectBody()

    /**
     * ダウンロードした生データを5ch互換のsubject.txt形式（CP932）に変換する
     *
     * @param string $host
     * @param string $body
     * @return string
     * @throws Exception
     */
    public static function convertSubjectBody($host, $body)
    {
        if (P2HostMgr::isHostJbbsShitaraba($host) || P2HostMgr::isHostBe2chs($host)) {
            $body = mb_convert_encoding($body, 'CP932', 'CP51932');
        } elseif (P2HostMgr::isHostTalk($host)) {
            $json = json_decode($body, true);
            if ($json === null || !isset($json['data']['threads'])) {
                throw new Exception('talk.jp API Invalid response');
            }
            $subject_txt = "";
            foreach ($json['data']['threads'] as $t) {
                $subject_txt .= "{$t['timestamp']}.dat<>". str_replace('<', '&lt;', $t['title']). " ({$t['comment_count']})\n";
            }
            $body = mb_convert_encoding($subject_txt, 'CP932', 'UTF-8');
        }
        return $body;
    }

    // }}}
    // {{{ fetchSubjectTxt()

    /**
     * subject.txtを一括ダウンロード&保存する
     *
     * @param array|string $subjects
     * @param bool $force
     * @return void
     */
    static public function fetchSubjectTxt($subjects, $force = false)
    {
        global $_conf;

        $makeIdFormat = "%s_%s";

        // {{{ ダウンロード対象を設定

        // お気に板等の.idx形式のファイルをパース
        if (is_string($subjects)) {
            $lines = FileCtl::file_read_lines($subjects, FILE_IGNORE_NEW_LINES);
            if (!$lines) {
                return;
            }

            $subjects = array();

            foreach ($lines as $l) {
                $la = explode('<>', $l);
                if (count($la) < 12) {
                    continue;
                }

                $host = $la[10];
                $bbs = $la[11];
                if ($host === '' || $bbs === '') {
                    continue;
                }

                $host = P2HostMgr::normalize5chHost($host);
                $key = sprintf($makeIdFormat, $host, $bbs);
                if (isset($subjects[$key])) {
                    continue;
                }

                $subjects[$key] = array($host, $bbs);
            }

        // [host, bbs] の連想配列を検証
        } elseif (is_array($subjects)) {
            $originals = $subjects;
            $subjects = array();

            foreach ($originals as $s) {
                if (!is_array($s) || !isset($s['host']) || !isset($s['bbs'])) {
                    continue;
                }

                $host = P2HostMgr::normalize5chHost($s['host']);
                $bbs = $s['bbs'];
                $key = sprintf($makeIdFormat, $host, $bbs);
                if (isset($subjects[$key])) {
                    continue;
                }

                $subjects[$key] = array($host, $bbs);
            }

        // 上記以外
        } else {
            return;
        }

        if (!count($subjects)) {
            return;
        }

        // }}}
        // {{{

        ksort($subjects);

        $self = new P2CurlMulti();
        $time = time() - $_conf['sb_dl_interval'];

        // 各 subject.txt へのリクエストをキューに追加
        foreach (array_keys($subjects) as $key) {
            list($host, $bbs) = explode("_", $key);
            $url = SubjectTxt::getSubjectUrl($host, $bbs);
            $file = P2Util::datDirOfHostBbs($host, $bbs) . 'subject.txt';

            if (!$force && file_exists($file) && $time <= filemtime($file)) {
                continue;
            }

            $before_time = file_exists($file) ? filemtime($file) : 0;
            $header = array();
            $header[] = "Connection: close";

            $self->add($key, $url, $header, $before_time);
        }

        // ダウンロードスタート
        $self->execute();

        // 各 subject.txt を保存
        $results = $self->getResult();
        foreach ($results as $key => $result) {
            list($host, $bbs) = explode("_", $key);
            $file = P2Util::datDirOfHostBbs($host, $bbs) . 'subject.txt';
            $tmp = $result['info'];

            if ($tmp['http_code'] != "304" && $tmp['before_time'] <= $tmp['after_time']) {
                $body = $result['body'];
                try {
                    $body = SubjectTxt::convertSubjectBody($host, $body);
                    if (file_put_contents($file, $body) === false) {
                        error_log("cannot write file.[$file]\n");
                    }
                } catch (Exception $e) {
                    error_log($e->getMessage() . " for {$host}_{$bbs}\n");
                }
            }
        }

        // }}}

        return ;
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
