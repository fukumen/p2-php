<?php
// {{{ CONSTANTS

require_once P2_LIB_DIR . '/P2CurlRequest.php';

class P2CurlMulti
{
    private $mh;
    private $ch;
    private $file_update;

    public function __construct() {
        global $_conf;

        $this->mh = curl_multi_init();
        curl_multi_setopt($this->mh, CURLMOPT_MAX_HOST_CONNECTIONS, $_conf['expack.curl_per_host']);

        $this->ch = array();
        $this->file_update = array();
    }

    public function __destruct() {
        foreach ($this->ch as $ch_array) {
            curl_multi_remove_handle($this->mh, $ch_array);
            curl_close($ch_array);
        }
        curl_multi_close($this->mh);
    }


    public function add($key, $url, $header = array(), $before_time = 0) {
        global $_conf;

        if (empty($url)) { return false; }
        if (isset($this->ch[$key])) { return false; }

        $host = parse_url($url, PHP_URL_HOST);

        $this->ch[$key] = curl_init();
        $this->file_update[$key] = $before_time;

        if ($before_time > 0) {
            $header[] = "If-Modified-Since: " . gmdate('D, d M Y H:i:s T', $before_time);
        }

        curl_setopt($this->ch[$key], CURLOPT_URL, $url);
        curl_setopt($this->ch[$key], CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch[$key], CURLOPT_TIMEOUT, $_conf['http_read_timeout']);
        curl_setopt($this->ch[$key], CURLOPT_CONNECTTIMEOUT, $_conf['http_conn_timeout']);
        curl_setopt($this->ch[$key], CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch[$key], CURLOPT_FILETIME, true);
        curl_setopt($this->ch[$key], CURLOPT_HTTPHEADER, $header);
        curl_setopt($this->ch[$key], CURLINFO_HEADER_OUT, true);
        curl_setopt($this->ch[$key], CURLOPT_HEADER, true);

        // User-Agent
        if(P2HostMgr::isHost2chs($host) && !P2HostMgr::isNotUse2chsAPI($host) && $_conf['2chapi_use']){
            $user_agent = sprintf ($_conf['2chapi_ua.read'], $_conf['2chapi_appname']);
        } else {
            $user_agent = P2Commun::getP2UA(true, P2HostMgr::isHost2chs($host));
        }
        curl_setopt($this->ch[$key], CURLOPT_USERAGENT, $user_agent);

        // プロキシ
        if ($_conf['tor_use'] && P2HostMgr::isHostTor($host, 0)) { // Tor(.onion)はTor用の設定をセット
            $tor_user_info = sprintf("%s%s@", $_conf['tor_proxy_user'], empty($_conf['tor_proxy_password']) ? "" : ":{$_conf['tor_proxy_password']}");
            $tor_address   = "{$_conf['tor_proxy_host']}:{$_conf['tor_proxy_port']}";
            $address = sprintf("http://%s%s", strpos($tor_user_info, "@") === 0 ? "" : $tor_user_info, $tor_address);

            curl_setopt($this->ch[$key], CURLOPT_PROXY, $address);

            if($_conf['tor_proxy_mode'] == 'socks5'){
                curl_setopt($this->ch[$key], CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            }

        } elseif ($_conf['proxy_use']) {
            $proxy_user_info = sprintf("%s%s@", $_conf['proxy_user'], empty($_conf['proxy_password']) ? "" : ":{$_conf['proxy_password']}");
            $proxy_address   = "{$_conf['proxy_host']}:{$_conf['proxy_port']}";
            $address = sprintf("http://%s%s", strpos($proxy_user_info, "@") === 0 ? "" : $proxy_user_info, $proxy_address);

            curl_setopt($this->ch[$key], CURLOPT_PROXY, $address);

            if($_conf['proxy_mode'] == 'socks5'){
                curl_setopt($this->ch[$key], CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            }
        }

        curl_multi_add_handle($this->mh, $this->ch[$key]);
        return true;
    }

    public function execute() {
        global $_conf;

        if (empty($this->ch) || !$this->mh) {
            return false;
        }

        // execute
        $timeout = $_conf['http_conn_timeout'] + $_conf['http_read_timeout'];
        do {
            $status = curl_multi_exec($this->mh, $running);
            if ($running) {
                if (curl_multi_select($this->mh, $timeout) === -1) {
                    usleep(100);
                }
            }
        } while ($running > 0 && $status === CURLM_OK);

        return $status === CURLM_OK;
    }

    public function getResult() {

        $results = array();

        if (empty($this->ch)) {
            return $results;
        }

        foreach ($this->ch as $key => $ch_array) {
            if (!$ch_array) {
                continue;
            }

            $tmp = curl_getinfo($ch_array);
            $tmp += array("before_time" =>  $this->file_update[$key], "after_time" => empty($tmp['filetime']) ? time() : $tmp['filetime']);

            $data = curl_multi_getcontent($ch_array);
            $header_size = $tmp['header_size'];
            $body = substr($data, $header_size);

            $results[$key] = array(
                'info' => $tmp,
                'body' => $body,
                'raw' => $data,
                'error' => curl_error($ch_array)
            );
        }
        
        return $results;
    }

    /**
     * 複数のHTTPリクエスト（GET）を並列実行する
     *
     * @param array $requestSpecs キー => リクエスト設定配列、またはキー => URL文字列
     *                            $key => [
     *                                'url' => (string) 必須,
     *                                'headers' => (array) 送信ヘッダ配列, 省略可能
     *                                'before_time' => (int) If-Modified-Since用タイムスタンプ, 省略可能
     *                            ]
     *                            または
     *                            $key => (string) URL文字列
     * @return array キー => P2CurlResponse | HTTP_Request2_Response | Exception
     */
    static public function httpRequestsParallel($requestSpecs)
    {
        global $_conf;
        $responses = array();

        foreach ($requestSpecs as $key => $spec) {
            if (is_string($spec)) {
                $spec = array('url' => $spec);
            }
            $requestSpecs[$key] = $spec;
        }

        if ($_conf['expack.use_curl_multi'] == 0) {
            foreach ($requestSpecs as $key => $spec) {
                try {
                    $url = $spec['url'];
                    
                    $req = P2Commun::createHTTPRequest($url, HTTP_Request2::METHOD_GET);
                    if (isset($spec['before_time']) && $spec['before_time'] > 0) {
                        $req->setHeader('If-Modified-Since', gmdate('D, d M Y H:i:s T', $spec['before_time']));
                    }
                    if (isset($spec['headers']) && is_array($spec['headers'])) {
                        foreach ($spec['headers'] as $name => $val) {
                            $req->setHeader($name, $val);
                        }
                    }
                    $responses[$key] = P2Commun::getHTTPResponse($req);
                } catch (Exception $e) {
                    // 例外オブジェクトをそのまま結果配列に格納
                    $responses[$key] = $e;
                }
            }
            return $responses;
        }

        $multi = new self();
        foreach ($requestSpecs as $key => $spec) {
            $url = $spec['url'];
            $headers = array();
            if (isset($spec['headers']) && is_array($spec['headers'])) {
                foreach ($spec['headers'] as $name => $val) {
                    $headers[] = "{$name}: {$val}";
                }
            }
            // add の引数は: $key, $url, $header, $before_time
            $before_time = $spec['before_time'] ?? 0;
            $multi->add($key, $url, $headers, $before_time);
        }

        $multi->execute();
        $results = $multi->getResult();

        foreach ($requestSpecs as $key => $spec) {
            if (isset($results[$key])) {
                $res = $results[$key];
                if (!empty($res['error'])) {
                    $error_msg = "cURL Error: " . $res['error'];
                    $responses[$key] = new Exception($error_msg);
                } else {
                    $responses[$key] = new P2CurlResponse($res['raw'], $res['info']);
                }
            } else {
                $error_msg = "Request failed without results";
                $responses[$key] = new Exception($error_msg);
            }
        }

        return $responses;
    }

    /**
     * 複数のファイルを並列にダウンロード保存する
     *
     * @param array $downloadSpecs ダウンロード設定配列
     *                             $key => [
     *                                 'url' => (string) 必須,
     *                                 'localfile' => (string) 保存先パス, 必須
     *                                 'cache_time' => (int) キャッシュ時間, 省略時 0
     *                                 'disp_error' => (bool) エラー表示有無, 省略時 true
     *                                 'trace_redirection' => (bool) リダイレクト追跡有無, 省略時 false
     *                                 'error_cache_time' => (int) エラー時キャッシュ時間, 省略時 0
     *                             ]
     * @return array キー => レスポンスオブジェクト | Exception | null
     */
    static public function fileDownloadParallel($downloadSpecs)
    {
        global $_conf;
        $requestSpecs = array();
        $responses = array();
        $skipKeys = array();

        foreach ($downloadSpecs as $key => $spec) {
            $localfile = $spec['localfile'];
            $cache_time = $spec['cache_time'] ?? 0;

            if (file_exists($localfile)) {
                if (filemtime($localfile) > time() - $cache_time) {
                    $responses[$key] = null;
                    $skipKeys[$key] = true;
                    continue;
                }
            }

            $requestSpecs[$key] = array(
                'url' => $spec['url'],
                'before_time' => file_exists($localfile) ? filemtime($localfile) : 0
            );
        }

        $apiResponses = self::httpRequestsParallel($requestSpecs);

        foreach ($downloadSpecs as $key => $spec) {
            if (isset($skipKeys[$key])) {
                continue;
            }

            $localfile = $spec['localfile'];
            $url = $spec['url'];
            $disp_error = $spec['disp_error'] ?? true;
            $error_cache_time = $spec['error_cache_time'] ?? 0;
            $cache_time = $spec['cache_time'] ?? 0;

            $response = self::getResponse($apiResponses, $key);
            $error_msg = null;
            $code = null;
            $body = '';

            if (!$response) {
                $error_msg = self::getErrorMessage($apiResponses, $key) ?? 'Request failed';
            } else {
                $code = $response->getStatus();
                if (!($code == 200 || $code == 206 || $code == 304)) {
                    $error_msg = (string)$code;
                }
                $body = $response->getBody();
            }

            if (isset($error_msg) && strlen($error_msg) > 0) {
                if ($error_cache_time > 0 && file_exists($localfile)) {
                    $new_mtime = time() - $cache_time + $error_cache_time;
                    if ($new_mtime > filemtime($localfile)) {
                        @touch($localfile, $new_mtime);
                    }
                }
                if ($disp_error) {
                    $url_t = P2Util::throughIme($url);
                    $info_msg_ht = "<p class=\"info-msg\">Error: {$error_msg}<br>";
                    $info_msg_ht .= "rep2 info: <a href=\"{$url_t}\"{$_conf['ext_win_target_at']}>{$url}</a> に接続できませんでした。</p>";
                    P2Util::pushInfoHtml($info_msg_ht);
                }
                $responses[$key] = $apiResponses[$key] ?? null;
                continue;
            }

            if ($code != 304) {
                if (FileCtl::file_write_contents($localfile, $body) === false) {
                    p2die('cannot write file.');
                }
            } else {
                @touch($localfile);
            }

            $responses[$key] = $response;
        }

        return $responses;
    }

    /**
     * 結果配列から正常なレスポンスを取得する
     *
     * @param array $responses sendRequestsParallelの結果配列
     * @param string $key キー
     * @return P2CurlResponse|HTTP_Request2_Response|null 失敗または存在しない場合はnull
     */
    static public function getResponse($responses, $key)
    {
        $response = $responses[$key] ?? null;
        return (($response instanceof P2CurlResponse) || ($response instanceof HTTP_Request2_Response)) ? $response : null;
    }

    /**
     * 結果配列から失敗時のエラーメッセージを取得する
     *
     * @param array $responses sendRequestsParallelの結果配列
     * @param string $key キー
     * @return string|null エラーがない場合や存在しない場合はnull
     */
    static public function getErrorMessage($responses, $key)
    {
        $response = $responses[$key] ?? null;
        if (($response instanceof P2CurlResponse) || ($response instanceof HTTP_Request2_Response)) {
            return null;
        }
        if ($response instanceof Exception) {
            return $response->getMessage();
        }
        return null;
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
