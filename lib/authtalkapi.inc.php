<?php
/**
 * rep2 - talk API 認証
 */

/// {{{ AuthTalkAPI
class AuthTalkAPI
{
    public $id;
    public $pw;
    public $auth_sid;
    public $talk_sid;
    public $use_auth;
    public $use_login;

// {{{ authenticate()

    /**
    * talk API の 認証する
    *
    * @return AuthTalkAPI
    */
    static public function authenticate($newauth, $loginTalkID = '', $loginTalkPW = '', $use_auth = true, $use_login = true)
    {
        global $_conf;

        $auth_sid = '';
        $talk_sid = '';

        if (!$newauth && file_exists($_conf['sidtalkapi_file'])) {
            $content = FileCtl::file_read_contents($_conf['sidtalkapi_file']);
            if ($content) {
                $decrypted = P2Encryptor::getInstance()->decrypt($content);
                if ($decrypted !== null) {
                    $data = @unserialize($decrypted);
                    if (is_array($data)) {
                        $loginTalkID = isset($data['id']) ? $data['id'] : '';
                        $loginTalkPW = isset($data['pw']) ? $data['pw'] : '';
                        $use_auth = isset($data['use_auth']) ? (bool)$data['use_auth'] : true;
                        $use_login = isset($data['use_login']) ? (bool)$data['use_login'] : true;
                    }
                }
            }
        }

        $host = "api.talk-platform.com";
        $url_auth = http_build_url(array(
            "scheme" => P2Util::selectScheme($host),
            "host" => $host,
            "path" => "v1/auth"));
        $url_login = http_build_url(array(
            "scheme" => P2Util::selectScheme($host),
            "host" => $host,
            "path" => "v1/login"));

        $CT = time();
        $AppKey = $_conf['talkapi_appkey'];
        $HMKey = $_conf['talkapi_hmkey'];
        $AppName = $_conf['talkapi_appname'];
        $AuthUA = $_conf['talkapi_ua.auth'];

        $canAuth = $use_auth && (!empty($AppKey) && !empty($AppName) && !empty($HMKey));
        $canLogin = $use_login && (!empty($loginTalkID) && !empty($loginTalkPW));

        if (!$use_auth && !$use_login) {
            P2Util::pushInfoHtml("<p>p2 Error: API認証かアカウントログインを選択してください。</p>");
            return null;
        }

        if (!$canAuth && !$canLogin) {
            P2Util::pushInfoHtml("<p>p2 Error: talk API の認証に必要な情報が設定されていません。</p>");
            return null;
        }

        if ($canAuth) {
            $message = $AppKey.$CT;
            $HB = hash_hmac("sha256", $message, $HMKey);
            $body = null;
            try {
                $req = P2Commun::createHTTPRequest($url_auth, HTTP_Request2::METHOD_POST, $AuthUA);

                $req->setHeader('X-2ch-UA', $AppName);

                $req->addPostParameter('ID', $loginTalkID);
                $req->addPostParameter('PW', $loginTalkPW);
                $req->addPostParameter('KY', $AppKey);
                $req->addPostParameter('CT', $CT);
                $req->addPostParameter('HB', $HB);

                // POSTデータの送信
                $res = P2Commun::getHTTPResponse($req);

                $code = $res->getStatus();
                if ($code != 200) {
                    P2Util::pushInfoHtml("<p>p2 Error: HTTP Error({$code})</p>");
                } else {
                    $body = $res->getBody();
                }
            } catch (Exception $e) {
                P2Util::pushInfoHtml("<p>p2 Error: talk API の認証サーバに接続出来ませんでした。({$e->getMessage()})</p>");
                return null;
            }

            $body = rtrim($body);

            // 分解
            if (!preg_match('/SESSION-ID=(.+?):(.+)/', $body, $matches)) {
                if ($newauth && file_exists($_conf['sidtalkapi_file'])) { unlink($_conf['sidtalkapi_file']); }
                P2Util::pushInfoHtml("<p>p2 error: talk API のレスポンスからSessionIDを取得出来ませんでした。</p>");
                return null;
            }

            // 認証照合失敗なら
            if ($matches[1] == 'ERROR') {
                if ($newauth && file_exists($_conf['sidtalkapi_file'])) { unlink($_conf['sidtalkapi_file']); }
                P2Util::pushInfoHtml("<p>p2 error: talk API のSESSION-IDの取得に失敗しました。認証情報を確認の上、ログインし直して下さい。</p>");
                return null;
            }
            $auth_sid = $matches[2];
        }

        if ($canLogin) {
            $body = null;
            try {
                $req = P2Commun::createHTTPRequest($url_login, HTTP_Request2::METHOD_POST, $AuthUA);

                $req->setHeader('X-2ch-UA', $AppName);

                $req->addPostParameter('ID', $loginTalkID);
                $req->addPostParameter('PW', $loginTalkPW);

                // POSTデータの送信
                $res = P2Commun::getHTTPResponse($req);

                $code = $res->getStatus();
                if ($code != 200) {
                    P2Util::pushInfoHtml("<p>p2 Error: HTTP Error({$code})</p>");
                } else {
                    $body = $res->getBody();
                }
            } catch (Exception $e) {
                P2Util::pushInfoHtml("<p>p2 Error: talk API のログインサーバに接続出来ませんでした。({$e->getMessage()})</p>");
                return null;
            }

            $body = rtrim($body);

            // 分解
            if (!preg_match('/SESSION-ID=(.+?):(.+)/', $body, $matches)) {
                if ($newauth && file_exists($_conf['sidtalkapi_file'])) { unlink($_conf['sidtalkapi_file']); }
                P2Util::pushInfoHtml("<p>p2 error: talk API のレスポンスからTalk SIDを取得出来ませんでした。</p>");
                return null;
            }

            // ログイン失敗なら
            if ($matches[1] == 'ERROR') {
                if ($newauth && file_exists($_conf['sidtalkapi_file'])) { unlink($_conf['sidtalkapi_file']); }
                P2Util::pushInfoHtml("<p>p2 error: talk API のSESSION-IDの取得に失敗しました。IDとパスワードを確認の上、ログインし直して下さい。</p>");
                return null;
            }
            $talk_sid = $matches[2];
        }

        $data = serialize(array(
            'id' => $loginTalkID,
            'pw' => $loginTalkPW,
            'auth_sid' => $auth_sid,
            'talk_sid' => $talk_sid,
            'use_auth' => $use_auth,
            'use_login' => $use_login,
        ));
        $encrypted = P2Encryptor::getInstance()->encrypt($data);

        $temp_file = $_conf['sidtalkapi_file'] . '.tmp';
        if (FileCtl::file_write_contents($temp_file, $encrypted) === false) {
            P2Util::pushInfoHtml("<p>p2 Error: {$_conf['sidtalkapi_file']} を保存できませんでした。ログイン登録失敗。</p>");
            return null;
        }
        @chmod($temp_file, 0600);

        if (!rename($temp_file, $_conf['sidtalkapi_file'])) {
            @unlink($temp_file);
            P2Util::pushInfoHtml("<p>p2 Error: {$_conf['sidtalkapi_file']} を保存できませんでした。ログイン登録失敗。</p>");
        }

        return new AuthTalkAPI($loginTalkID, $loginTalkPW, $auth_sid, $talk_sid, $use_auth, $use_login);
    }
    // }}}
    // {{{ load()
    
    /**
    * 認証済みの情報をロード
    *
    * @return AuthTalkAPI
    */
    static public function load()
    {
        global $_conf;

        if (file_exists($_conf['sidtalkapi_file'])) {
            // 認証後、設定した時間経過していたら自動再認証
            if (filemtime($_conf['sidtalkapi_file']) < time() - 60*60*$_conf['talkapi_interval']) {
                return self::authenticate(false);
            }

            $content = FileCtl::file_read_contents($_conf['sidtalkapi_file']);
            if ($content) {
                $decrypted = P2Encryptor::getInstance()->decrypt($content);
                if ($decrypted !== null) {
                    $data = @unserialize($decrypted);
                    if (is_array($data) && (isset($data['auth_sid']) || isset($data['talk_sid']))) {
                        return new AuthTalkAPI($data['id'], $data['pw'], $data['auth_sid'], $data['talk_sid'], (bool)$data['use_auth'], (bool)$data['use_login']);
                    }
                }
            }
        }

        return null;
    }
    
    // }}}
    // {{{ __construct()

    /**
     * コンストラクタ
     */
    public function __construct($id, $pw, $auth_sid, $talk_sid, $use_auth = true, $use_login = true)
    {
        $this->id = $id;
        $this->pw = $pw;
        $this->auth_sid = $auth_sid;
        $this->talk_sid = $talk_sid;
        $this->use_auth = $use_auth;
        $this->use_login = $use_login;
    }
    // }}}
    // {{{ is_login()

    /**
    * ログイン状態を取得する
    *
    * @return bool
    */
    static public function is_login()
    {
        $talkapi = self::load();
        if ($talkapi && $talkapi->talk_sid) {
            return true;
        } else {
            return false;
        }
    }
    // }}}
    // {{{ make_hobo()

    /**
    * hobo を作成する
    *
    * @return array appkey, hobo を返す
    */
    public function make_hobo($path)
    {
        global $_conf;

        $AppKey = $_conf['talkapi_appkey'];
        $HMKey = $_conf['talkapi_hmkey'];
        if (empty($this->auth_sid) || empty($AppKey) || empty($HMKey)) {
            return null;
        }

        $msg = $path . $this->auth_sid. $AppKey;
        return array(
            'sid' => $this->auth_sid,
            'appkey' => $AppKey,
            'hobo' => hash_hmac('sha256', $msg, $HMKey)
        );
    }
    // }}}
    // {{{ make_writetoken()

    /**
    * X-Write-Token を作成する
    *
    * @return string X-Write-Token を返す
    */
    public function make_writetoken($bbs, $key, $subject, $message, $time)
    {
        global $_conf;

        $HMKey = $_conf['talkapi_hmkey'];
        if (empty($HMKey)) {
            return null;
        }

        $msg = $bbs . '<>' . $key . '<>' . $subject . '<>' . $message . '<>' . $time . '<>' . $this->auth_sid . '<>' . $this->talk_sid;
        return hash_hmac('sha256', $msg, $HMKey);
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
