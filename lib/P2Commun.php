<?php

// {{{ P2Commun

/**
 * rep2 - 余所のサーバーとお話しするための機能をP2Utilから分離したユーティリティクラス
 * インスタンスを作らずにクラスメソッドで利用する
 *
 * @create  2017/04/06
 * @static
 */
class P2Commun
{
    // {{{ createHTTPRequest()

    /**
     * HTTP_Request2クラスのインスタンスを生成する
     *
     * @param string $url 文字列のURL(絶対に必須)
     * @param $method HTTP_Request2と同じ
     * @return HTTP_Request2
     */
    static public function createHTTPRequest($url , $method = HTTP_Request2::METHOD_GET)
    {
        global $_conf;

        $purl = parse_url ($url);

        if(empty($url) || $purl === false)
        {
            throw new InvalidArgumentException ("URLの指定が変です。");
        }

        $req = new HTTP_Request2($url, $method);

        // よく使うヘッダを指定
        // p2のHTTP通信は特に指定の無い限りMonazillaを名乗るようにする
        $req->setHeader ('User-Agent', self::getP2UA(true,P2HostMgr::isHost2chs($purl['host'])));
        $req->setHeader ('Accept-Language', 'ja,en-us;q=0.7,en;q=0.3');
        $req->setHeader ('Accept', '*/*');
        $req->setHeader ('Accept-Encoding', 'gzip, deflate');

        // タイムアウトの設定
        $req->setConfig (array (
                'connect_timeout' => $_conf['http_conn_timeout'],
                'timeout' => $_conf['http_read_timeout'],
        ));

        // 外部との通信は全てcURLを使う（socketはopenSSL絡みで地雷踏むので絶対使用禁止！）
        $req->setAdapter('curl');

        // SSLの設定
        if($purl['scheme'] == 'https') {
            if($_conf['ssl_capath'])
            {
                $req->setConfig ('ssl_capath', $_conf['ssl_capath']);
            }
        }

        // プロキシ
        if ($_conf['tor_use'] && P2HostMgr::isHostTor($purl['host'], 0)) { // Tor(.onion)はTor用の設定をセット
            $req->setConfig (array (
                    'proxy_host' => $_conf['tor_proxy_host'],
                    'proxy_port' => $_conf['tor_proxy_port'],
                    'proxy_user' => $_conf['tor_proxy_user'],
                    'proxy_password' => $_conf['tor_proxy_password']
            ));
            if($_conf['tor_proxy_mode'] == 'socks5'){
                $req->setConfig('proxy_type', $_conf['tor_proxy_mode']);
            }
        } elseif ($_conf['proxy_use']) {
            $req->setConfig (array (
                    'proxy_host' => $_conf['proxy_host'],
                    'proxy_port' => $_conf['proxy_port'],
                    'proxy_user' => $_conf['proxy_user'],
                    'proxy_password' => $_conf['proxy_password']
            ));
            if($_conf['proxy_mode'] == 'socks5'){
                $req->setConfig('proxy_type', $_conf['proxy_mode']);
            }
        }

        unset ($purl);

        return $req;
    }

    static public function getHTTPResponse($req) {
        if($req->getConfig('proxy_type') == 'socks5') {
            $socks = new HTTP_Request2_Adapter_Socket();
            $res = $socks->sendRequest($req);
            unset($socks);
        } else {
            $res = $req->send ();
        }
        return $res;
    }
    // }}}
    // {{{ getP2UA()
    /**
     * p2又はAPIのUAを返す
     * @param   bool $withMonazilla trueならMonazilla/1.00を付ける
     * @param   bool $apiUA trueで尚且つAPIが利用可能なときにAPIのUAを返す
     * @return  string
     */
    static public function getP2UA($withMonazilla = true,$apiUA = false)
    {
        global $_conf;

        // APIを使用する設定の場合はAPIのUAを返す
        if ($apiUA && $_conf['2chapi_use'] == 1) {
            if ($_conf['2chapi_appname'] != "") {
                $p2ua = $_conf['2chapi_appname'];
            } else {
                p2die("2chと通信するために必要な情報が設定されていません。");
            }
        } else {
            $p2ua = $_conf['p2ua'];
        }

        if ($withMonazilla) {
            $p2ua = sprintf('Monazilla/1.00 (%s)', $p2ua);
        }

        return $p2ua;
    }
    // }}}
    // {{{ getWebPage

    /**
     * Webページを取得する
     *
     * 200 OK
     * 206 Partial Content
     * 304 Not Modified → 失敗扱い
     *
     * @return string|false 成功したらページ内容を返す。失敗したらfalseを返す。
     */
    static public function getWebPage($url, &$error_msg, $timeout = 15)
    {
        try {
            $req = self::createHTTPRequest($url, HTTP_Request2::METHOD_GET);
            //$req->addHeader("X-PHP-Version", phpversion());

            $response = self::getHTTPResponse($req);

            $code = $response->getStatus();
            if ($code == 200 || $code == 206) { // || $code == 304) {
                return $response->getBody();
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }

    // }}}
    // {{{ fileDownload()

    /**
     *  ファイルをダウンロード保存する
     */
    static public function fileDownload($url, $localfile,
                                        $cache_time = 0,
                                        $disp_error = true,
                                        $trace_redirection = false)
    {
        global $_conf;

        if (file_exists($localfile)) {
            // キャッシュ有効期間ならダウンロードしない
            if (filemtime($localfile) > time() - $cache_time) {
                return null;
            }
        }

        try {
            // DL
            $req = self::createHTTPRequest($url, HTTP_Request2::METHOD_GET);

            $req->setConfig(array('follow_redirects' => $trace_redirection));

            if (file_exists($localfile)) {
                $req->setHeader ('If-Modified-Since', http_date(filemtime($localfile)) );
            }

            $response = self::getHTTPResponse($req);

            $code = $response->getStatus();
            if (!($code == 200 || $code == 206 || $code == 304)) {
                $error_msg = $code;
            }
            $body = $response->getBody();

        } catch (Exception $e) {
            $error_msg = $e->getMessage();
        }

        // エラーが出たらnullを返して終わり
        if (isset($error_msg) && strlen($error_msg) > 0) {
            // エラーメッセージを設定
            if ($disp_error) {
                $url_t = P2Util::throughIme($url);
                $info_msg_ht = "<p class=\"info-msg\">Error: {$error_msg}<br>";
                $info_msg_ht .= "rep2 info: <a href=\"{$url_t}\"{$_conf['ext_win_target_at']}>{$url}</a> に接続できませんでした。</p>";
                P2Util::pushInfoHtml($info_msg_ht);
            }
            return null;
        }

        // 更新されていたら保存
        if ($code != 304) {
            if (FileCtl::file_write_contents($localfile, $body) === false) {
                p2die('cannot write file.');
            }
        }

        return $response;
    }

    // }}}
    public static function getResponseCode($url)
    {
        try {
            $req = self::createHTTPRequest ($url, HTTP_Request2::METHOD_HEAD);
            $response = self::getHTTPResponse($req);
            return $response->getStatus();

        } catch (Exception $e) {
            return false; // $error_msg
        }
    }
}