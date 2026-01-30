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

        if ($method === HTTP_Request2::METHOD_POST && $_conf['http_post_method'] == 1) {
            $req = new P2CurlRequest($url, $method);
        } else {
            $req = new HTTP_Request2($url, $method);
        }

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
        if ($req instanceof P2CurlRequest) {
            return $req->send();
        }
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

/**
 * POSTリクエスト用のHTTP_Request2コンパチクラス
 */
class P2CurlRequest
{
    private $url;
    private $method;
    private $headers = array();
    private $config = array();
    private $postParams = array();
    private $body = null;
    private $cookies = array();
    private $debugfile = null;

    public function __construct($url, $method = 'GET')
    {
        $this->url = $url;
        $this->method = $method;
    }

    public function setHeader($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->headers[strtolower($k)] = $v;
            }
        } else {
            $this->headers[strtolower($name)] = $value;
        }
    }

    public function setConfig($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->config[$k] = $v;
            }
        } else {
            $this->config[$name] = $value;
        }
    }

    public function getConfig($name = null)
    {
        if ($name === null) return $this->config;
        return isset($this->config[$name]) ? $this->config[$name] : null;
    }

    public function setAdapter($adapter)
    {
        // Do nothing
    }

    public function setAuth($user, $password, $scheme = null)
    {
        // Do nothing
    }

    public function addPostParameter($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->postParams[$k] = $v;
            }
        } else {
            $this->postParams[$name] = $value;
        }
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function addCookie($name, $value)
    {
        $this->cookies[$name] = $value;
    }

    public function setDebugfile($filename)
    {
        $this->debugfile = $filename;
    }

    public function send()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if (isset($this->config['connect_timeout'])) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->config['connect_timeout']);
        }
        if (isset($this->config['timeout'])) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        }
        if (isset($this->config['ssl_capath'])) {
            curl_setopt($ch, CURLOPT_CAPATH, $this->config['ssl_capath']);
        }
        if (isset($this->config['proxy_host'])) {
            curl_setopt($ch, CURLOPT_PROXY, $this->config['proxy_host']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->config['proxy_port']);
            if (isset($this->config['proxy_user'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->config['proxy_user'] . ':' . $this->config['proxy_password']);
            }
            if (isset($this->config['proxy_type']) && $this->config['proxy_type'] == 'socks5') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            }
        }
        if (isset($this->config['follow_redirects']) && $this->config['follow_redirects']) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }

        $protocol_version = isset($this->config['protocol_version']) ? $this->config['protocol_version'] : '1.1';
        switch ($protocol_version) {
            case '2.0':
                if (defined('CURL_HTTP_VERSION_2_0')) {
                    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
                }
                break;
            case '1.0':
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
                break;
            case '1.1':
            default:
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                break;
        }

        if ($this->method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($this->postParams)) {
                if (!isset($this->headers['content-type'])) {
                    $this->headers['content-type'] = 'application/x-www-form-urlencoded';
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->postParams, '', '&'));
            } elseif ($this->body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
            }
        }

        if (!empty($this->cookies)) {
            $cookieParts = array();
            foreach ($this->cookies as $name => $value) {
                $cookieParts[] = urlencode($name) . '=' . urlencode($value);
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookieParts));
        }

        $headers = array();
        foreach ($this->headers as $k => $v) {
            if ($k === 'accept-encoding') {
                curl_setopt($ch, CURLOPT_ENCODING, $v);
                continue;
            }
            $headers[] = ucwords($k, '-') . ': ' . $v;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($this->debugfile) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_DEBUGFUNCTION, function($handle, $type, $data) {
                
                // type 1: CURLINFO_HEADER_IN（受信ヘッダ）
                // type 2: CURLINFO_HEADER_OUT（送信ヘッダ）
                // type 0: CURLINFO_TEXT（cURLの接続情報など）
                // type 4: CURLINFO_DATA_OUT（送信データ/BODY）
                if ($type === 1 || $type === 2 || $type === 0 || $type === 4) {
                    $prefix = "[INFO] ";
                    if ($type === 1) {
                        $prefix = "[RECV HEADER] ";
                    } elseif ($type === 2) {
                        $prefix = "[SEND HEADER] ";
                    } elseif ($type === 4) {
                        $prefix = "[SEND BODY] ";
                    }
                    file_put_contents($this->debugfile, $prefix . $data, FILE_APPEND);
                }
            });
        }

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            if (class_exists('HTTP_Request2_Exception')) {
                throw new HTTP_Request2_Exception("cURL Error: " . $error);
            }
            throw new Exception("cURL Error: " . $error);
        }

        return new P2CurlResponse($result, $info);
    }
}

class P2CurlResponse
{
    private $status;
    private $body;
    private $headers = array();
    private $cookies = array();

    public function __construct($result, $info)
    {
        $this->status = $info['http_code'];
        $header_size = $info['header_size'];
        $header_text = substr($result, 0, $header_size);
        $this->body = substr($result, $header_size);

        foreach (explode("\r\n", $header_text) as $i => $line) {
            if ($i === 0) continue;
            if (empty($line)) continue;
            $parts = explode(': ', $line, 2);
            if (count($parts) == 2) {
                $this->headers[strtolower($parts[0])] = $parts[1];
                if (strtolower($parts[0]) === 'set-cookie') {
                    $this->parseCookie($parts[1]);
                }
            }
        }
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getHeader($name)
    {
        $name = strtolower($name);
        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

    private function parseCookie($cookieStr)
    {
        $cookie = array(
            'name' => '',
            'value' => '',
            'domain' => '',
            'path' => '',
            'expires' => null,
            'secure' => false,
            'httponly' => false
        );

        $parts = explode(';', $cookieStr);
        $first = array_shift($parts);

        if (strpos($first, '=') !== false) {
            list($name, $value) = explode('=', $first, 2);
            $cookie['name'] = trim($name);
            $cookie['value'] = trim($value);
        } else {
            return;
        }

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;

            if (strpos($part, '=') !== false) {
                list($key, $val) = explode('=', $part, 2);
                $key = strtolower(trim($key));
                $val = trim($val);
                if (array_key_exists($key, $cookie)) {
                    $cookie[$key] = $val;
                }
            } else {
                $key = strtolower($part);
                if ($key === 'secure') {
                    $cookie['secure'] = true;
                } elseif ($key === 'httponly') {
                    $cookie['httponly'] = true;
                }
            }
        }

        $this->cookies[] = $cookie;
    }

    public function getCookies()
    {
        return $this->cookies;
    }
}