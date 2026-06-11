<?php
// {{{ CONSTANTS

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

        curl_setopt($this->ch[$key], CURLOPT_URL, $url);
        curl_setopt($this->ch[$key], CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch[$key], CURLOPT_TIMEOUT, $_conf['http_read_timeout']);
        curl_setopt($this->ch[$key], CURLOPT_CONNECTTIMEOUT, $_conf['http_conn_timeout']);
        curl_setopt($this->ch[$key], CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch[$key], CURLOPT_TIMECONDITION, CURL_TIMECOND_IFMODSINCE);
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
                'error' => curl_error($ch_array)
            );
        }
        
        return $results;
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
