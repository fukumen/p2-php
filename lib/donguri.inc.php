<?php
/**
 * rep2 - どんぐり共通処理
 */

class Donguri {

    private const COOKIE_HOST = 'www.5ch.net';
    private const COOKIE_NAME = 'acorn';
    private const SID_NAME = 'sid';
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

        $this->base_url = P2Util::selectScheme($_conf['2ch_domain']) . '://donguri.' . $_conf['2ch_domain'] . '/';
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
        $this->cookies = CookieDataStore::loadActive($this->cookie_key);
        if (!$this->cookies) {
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
        foreach ($this->cookies as $cname => $c) {
            $req->addCookie($cname, $c['value']);
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
            if (!$this->cookies) {
                $this->cookies = array();
            }
            CookieDataStore::mergeResponse($this->cookies, $response_cookies);
            // cookie 保存
            if ($this->cookies) {
                CookieDataStore::set($this->cookie_key, $this->cookies);
            } else {
                CookieDataStore::delete($this->cookie_key);
            }
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

        $sid = null;
        $msg = null;
        $donguri = self::_get_instance();
        try {
            $detail = null;
            if ($_conf['donguri_method'] == 1) {
                if ($array = P2Util::readIdPw2ch()) {
                    [$user, $pass, $autoLogin, $methodLogin2ch] = $array;
                    if (file_exists($_conf['siduplift_file'])) {
                        require_once P2_LIB_DIR . '/login2ch.inc.php';
                        $data = get_uplift_sid($array);
                        if (isset($data['sid'])) {
                            $sid = $data['sid'];
                        }
                    }
                    if (P2Util::hasInfoHtml()) {
                        // login2ch_upliftのエラーメッセージを取得
                        $msg = P2Util::getInfoHtml();
                    }
                } else {
                    $msg = '2chログイン管理にUPLIFTアカウントが登録されていません';
                }
            }
            if (!$msg) {
                $req = P2Commun::createHTTPRequest ($donguri->base_url . 'login', HTTP_Request2::METHOD_POST);
                foreach ($donguri->cookies as $cname => $c) {
                    $req->addCookie($cname, $c['value']);
                }
                if ($sid) {
                    // upliftのsidは無しでもログイン出来るようだが、ログインしているなら付ける
                    $req->addCookie($sid['name'], $sid['value']);
                }

                $req->setHeader('Referer', $donguri->base_url);
                $req->setHeader('Origin', rtrim($donguri->base_url, '/'));
                if ($_conf['donguri_method'] == 1) {
                    $req->addPostParameter('email', $user);
                    $req->addPostParameter('pass', $pass);
                } else {
                    $req->addPostParameter('email', $_conf['donguri_user']);
                    $req->addPostParameter('pass', $_conf['donguri_password']);
                }
                $response = P2Commun::getHTTPResponse($req);

                [$detail, $msg] = $donguri->_finish_base($response);
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
                foreach ($donguri->cookies as $cname => $c) {
                    $req->addCookie($cname, $c['value']);
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
        if ($this->cookie_sts != 2 && $this->status_data
            && isset($this->status_data['status']) && $this->status_data['status'] >= 2) {
            // どんぐりが無いのに過去のどんぐり有り状態が残っている場合、表示を補正する
            $this->_update_status(null);
        }
        return $this->status_data;
    }

    // }}}
    // {{{ _update_status()

    /**
     * ステータス更新
     */
    private function _update_status($detail)
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
                $data['status'] = 4; // 警備員● or ハンター
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
        return isset($donguri->cookies[self::COOKIE_NAME]['value'])
            ? $donguri->cookies[self::COOKIE_NAME]['value'] : null;
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
        if ($_conf['donguri_method'] == 1 && file_exists($_conf['idpw2ch_php'])) {
            $login_link = $delimiter . '[<a href="javascript:void(0);" onclick="return loginDonguri(' . $is_iphone . ');">ログイン</a>]';
        } elseif ($_conf['donguri_user'] && $_conf['donguri_password'] && strlen($_conf['donguri_user']) > 0 && strlen($_conf['donguri_password']) > 0) {
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
            $txt = '[<a href="dongurictl.php?mode=get_donguri"' . $_conf['ext_win_target_at'] . '>どんぐり基地</a>]';
            $txt .= $delimiter . $data['name'] . $delimiter . $data['job'] . $delimiter . "ID:" . $data['id'];
            $txt .= $login_link;
        } elseif ($data['status'] == 4) {
            $txt = '[<a href="' . P2Util::throughIme('https://donguri.' . $_conf['2ch_domain'] . '/') . '"' . $_conf['ext_win_target_at'] . '>どんぐり基地</a>]';
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
    // {{{ need_relogin()

    /**
     * 書き込み前にどんぐり基地への再ログインが必要かどうかを返す
     * acorn が無い、または期限切れ 1 時間前以降の場合は true
     */
    public static function need_relogin()
    {
        $donguri = self::_get_instance();
        if ($donguri->cookie_sts != 2) {
            return true;
        }
        $expires = $donguri->cookies[self::COOKIE_NAME]['expires'] ?? null;
        return ($expires !== null && time() > $expires - 3600);
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
