<?php
/**
 * rep2 - どんぐり共通処理
 */

class Donguri {

    private const COOKIE_HOST = 'www.5ch.net';
    private const COOKIE_NAME = 'acorn';
    private const BASE_MATCH = '/>(\S+)<\/div><div>(\S+)\[\s*ID:\s*([^\]]+)/';
    private static $instance = null;
    private $base_url = null;
    private $cookie_key = null;
    private $cookies = null;
    private $cookie_sts = null;     // 0:クッキーなし、1:どんぐり無し、2:どんぐり有り
    private $status_data = null;    // ステータス(表示用)

    // new禁止
    private function __construct()
    {
        global $_conf, $_login;

        $this->base_url = ($_conf['2ch_ssl.post'] ? 'https://' : 'http://') . 'donguri.5ch.net/';
        $this->cookie_key = $_login->user_u . '/' . self::COOKIE_HOST;
        $this->_read_cookie();
    }

    // インスタンス取得
    private static function _get_instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // クローン禁止
    private function __clone()
    {
    }

    // {{{ _update_cookie_sts()

    /**
     * クッキーステータス更新
     */
    private function _update_cookie_sts()
    {
        if ($this->cookies == null) {
            $this->cookie_sts = 0; // クッキー無し
        } elseif (!array_key_exists(self::COOKIE_NAME, $this->cookies)) {
            $this->cookie_sts = 1; // どんぐり無し
        } else {
            $this->cookie_sts = 2; // どんぐり有り
        }
        return;
    }

    // }}}
    // {{{ _read_cookie()

    /**
     * クッキー読み込み
     */
    private function _read_cookie()
    {
        // cookie 読み込み
        if ($this->cookies = CookieDataStore::get($this->cookie_key)) {
            if (is_array($this->cookies)) {
                if (array_key_exists('expires', $this->cookies)) {
                    // 期限切れなら破棄
                    if (time() > strtotime($this->cookies['expires'])) {
                        CookieDataStore::delete($this->cookie_key);
                        $this->cookies = null;
                    }
                }
            } else {
                CookieDataStore::delete($this->cookie_key);
                $this->cookies = null;
            }
        } else {
            $this->cookies = null;
        }
        $this->_update_cookie_sts();
        return;
    }

    // }}}
    // {{{ _check_base()

    /**
     * どんぐり基地確認(内部)
     */
    private function _check_base()
    {
        $detail = null;
        $msg = null;

        $req = P2Commun::createHTTPRequest ($this->base_url, HTTP_Request2::METHOD_GET);
        foreach ($this->cookies as $cname => $cvalue) {
            if ($cname != 'expires') {
                $req->addCookie($cname,$cvalue);
            }
        }
        $response = P2Commun::getHTTPResponse($req);

        $code = $response->getStatus();
        if ($code == 200) {
            $body = mb_convert_encoding($response->getBody(), 'CP932', 'UTF-8');
            // このパスに来るのは成功を想定している
            if (preg_match(self::BASE_MATCH, $body, $matches)) {
                $detail = [$matches[1], $matches[2], $matches[3]];
            } else {
                $msg = self::_make_errmsg($body);
            }
        }
        return [$detail, $msg];
    }

    // }}}
    // {{{ check_base()

    /**
     * どんぐり基地確認
     */
    public static function check_base()
    {
        global $_conf;

        $msg = null;
        $donguri = self::_get_instance();
        try {
            $detail = null;
            if ($donguri->cookie_sts == 2) {
                [$detail, $msg] = $donguri->_check_base();
            }
            $donguri->_update_status($detail);
        } catch (Exception $e) {
        }
        if ($msg) {
            $delimiter = $_conf['iphone'] ? '&nbsp' : '<br>　';
            return $delimiter . $msg;
        } else {
            return '';
        }
    }

    // }}}
    // {{{ _finish_base()

    /**
     * どんぐり基地後処理
     */
    private function _finish_base($response)
    {
        $detail = null;
        $msg = null;

        // Cookieを取得
        $response_cookies = $response->getCookies();
        if ($response_cookies) {
            foreach ($response_cookies as $c) {
                if (!$this->cookies) {
                    $this->cookies = array();
                }
                if (isset($c['expires']) && time() > strtotime($c['expires'])) {
                    unset($this->cookies[$c['name']]);
                } else {
                    $this->cookies[ $c['name'] ] = $c['value'];
                }
            }
            // cookie 保存
            CookieDataStore::set($this->cookie_key, $this->cookies);
        }

        $this->_update_cookie_sts();
        $code = $response->getStatus();
        if ($code == 302) {
            if ($this->cookie_sts == 2) {
                [$detail, $msg] = $this->_check_base();
            }
        } elseif ($code == 200) {
            $body = mb_convert_encoding($response->getBody(), 'CP932', 'UTF-8');
            // このパスに来るのはエラーを想定しているが、何故かログイン出来ていたらその情報で更新する
            if (preg_match(self::BASE_MATCH, $body, $matches)) {
                $detail = [$matches[1], $matches[2], $matches[3]];
            } else {
                $msg = self::_make_errmsg($body);
            }
        }
        return [$detail, $msg];
    }

    // }}}
    // {{{ login_base()

    /**
     * どんぐり基地ログイン
     */
    public static function login_base()
    {
        global $_conf;

        $msg = null;
        $donguri = self::_get_instance();
        try {
            $detail = null;
            $req = P2Commun::createHTTPRequest ($donguri->base_url . 'login', HTTP_Request2::METHOD_POST);
            foreach ($donguri->cookies as $cname => $cvalue) {
                if ($cname != 'expires') {
                    $req->addCookie($cname,$cvalue);
                }
            }
            $req->setHeader('Referer', $donguri->base_url);
            $req->setHeader('Origin', rtrim($donguri->base_url, '/'));
            $req->addPostParameter('email', $_conf['donguri_user']);
            $req->addPostParameter('pass', $_conf['donguri_password']);
            $response = P2Commun::getHTTPResponse($req);

            [$detail, $msg] = $donguri->_finish_base($response);
            $donguri->_update_status($detail);
        } catch (Exception $e) {
        }
        if ($msg) {
            $delimiter = $_conf['iphone'] ? '&nbsp' : '<br>　';
            return $delimiter . $msg;
        } else {
            return '';
        }
    }

    // }}}
    // {{{ logout_base()

    /**
     * どんぐり基地ログアウト
     */
    public static function logout_base()
    {
        global $_conf;

        $msg = null;
        $donguri = self::_get_instance();
        try {
            $detail = null;
            if ($donguri->cookie_sts == 2) {
                $req = P2Commun::createHTTPRequest ($donguri->base_url . "logout", HTTP_Request2::METHOD_GET);
                foreach ($donguri->cookies as $cname => $cvalue) {
                    if ($cname != 'expires') {
                        $req->addCookie($cname,$cvalue);
                    }
                }
                $req->setHeader('Referer', $donguri->base_url);
                $response = P2Commun::getHTTPResponse($req);

                [$detail, $msg] = $donguri->_finish_base($response);
            } else {
                $msg = 'どんぐりがありません';
            }
            $donguri->_update_status($detail);
        } catch (Exception $e) {
        }
        if ($msg) {
            $delimiter = $_conf['iphone'] ? '&nbsp' : '<br>　';
            return $delimiter . $msg;
        } else {
            return '';
        }
    }

    // }}}
    // {{{ _load_status()

    /**
     * キャッシュ取得
     */
    private function _load_status()
    {
        global $_conf;

        if ($this->status_data == null && file_exists($_conf['donguri_cache'])) {
            $content = file_get_contents($_conf['donguri_cache']);
            $this->status_data = @unserialize($content);
        }
        return $this->status_data;
    }

    // }}}
    // {{{ _update_status()

    /**
     * ステータス更新
     */
    private function _update_status($detail = null)
    {
        global $_conf;

        $data = array();
        if ($this->cookie_sts == 0) {
            $data['status'] = 0; // クッキー無し
        } elseif ($this->cookie_sts == 1) {
            $data['status'] = 1; // どんぐり無し
        } elseif ($detail == null) {
            $data['status'] = 2; // 不正などんぐり
        } else {
            if ($detail[1] == "警備員○") {
                $data['status'] = 3; // 警備員○
            } else {
                $data['status'] = 4; // 警備員●
            }
            $data['name'] = $detail[0];
            $data['job'] = $detail[1];
            $data['id'] = $detail[2];
        }
        $this->status_data = $data;

        // キャッシュ保存
        $temp_file = $_conf['donguri_cache'] . '.tmp';
        if (FileCtl::file_write_contents($temp_file, serialize($data)) === false) {
            return;
        }
        @chmod($temp_file, 0600);

        if (!rename($temp_file, $_conf['donguri_cache'])) {
            @unlink($temp_file);
        }
    }

    // }}}
    // {{{ getAcornValue()

    /**
     * どんぐりの値を取得
     */
    public static function get_donguri()
    {
        $donguri = self::_get_instance();
        return isset($donguri->cookies[self::COOKIE_NAME]) ? $donguri->cookies[self::COOKIE_NAME] : null;
    }

    // }}}
    // {{{ _make_errmsg()

    /**
     * エラーメッセージ作成
     */
    private static function _make_errmsg($body)
    {
        if (str_starts_with($body, 'NG<>')) {
            return substr($body, 4);
        } else {
            return '不明なエラー';
        }
    }

    // }}}
    // {{{ get_status_text()

    /**
     * ステータス表示
     */
    public static function get_status_text()
    {
        global $_conf;

        $donguri = self::_get_instance();
        $data = $donguri->_load_status();
        $is_iphone = $_conf['iphone'] ? '1' : '0';
        $delimiter = $_conf['iphone'] ? '&nbsp' : '<br>　';
        if ($_conf['donguri_user'] && $_conf['donguri_password']) {
            $login_link = $delimiter . '[<a href="javascript:void(0);" onclick="return loginDonguri(' . $is_iphone . ');">ログイン</a>]';
        } else {
            $login_link = '';
        }

        if (!$data) {
            $txt = '未確認 [<a href="javascript:void(0);" onclick="return checkDonguri(' . $is_iphone . ');">確認</a>]';
        } elseif ($data['status'] == 0) {
            //$txt = 'クッキー無し';
            $txt = 'どんぐり無し';
            $txt .= $login_link;
        } elseif ($data['status'] == 1) {
            $txt = 'どんぐり無し';
            $txt .= $login_link;
        } elseif ($data['status'] == 2) {
            $txt = '[<a href="javascript:void(0);" onclick="return checkDonguri(' . $is_iphone . ');">確認</a>]';
            $txt .= $delimiter . '不正などんぐり';
            $txt .= $login_link;
        } elseif ($data['status'] == 3) {
            $txt = '[<a href="dongurictl.php?mode=get_donguri" target="_blank">どんぐり基地</a>]';
            $txt .= $delimiter . $data['name'] . $delimiter . $data['job'] . $delimiter . "ID:" . $data['id'];
            $txt .= $login_link;
        } elseif ($data['status'] == 4) {
            $txt = '[<a href="https://donguri.5ch.net/" target="_blank">どんぐり基地</a>]';
            $txt .= $delimiter . $data['name'] . $delimiter . $data['job'] . $delimiter . "ID:". $data['id'];
            $txt .= $delimiter . '[<a href="javascript:void(0);" onclick="return logoutDonguri(' . $is_iphone . ');">ログアウト</a>]';
        } else {
            $txt = 'エラー';
        }
        return $txt;
    }

    // }}}
    // {{{ check_status()

    /**
     * ステータス確認
     */
    public static function check_status()
    {
        global $_conf;

        $donguri = self::_get_instance();
        $data = $donguri->_load_status();
        $is_iphone = $_conf['iphone'] ? '1' : '0';

        if (!$data) {
            echo '<script type="text/javascript">checkDonguri(' . $is_iphone . ');</script>';
        }
    }

    // }}}
}

/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:description
