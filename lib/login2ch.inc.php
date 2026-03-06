<?php
/**
 * rep2 - 2chログイン
 */

// {{{ login2ch()

/**
 * 2ch IDにログインする
 *
 * @return  string|false  成功したら2ch SIDを返す
 */
function login2ch()
{
    global $_conf;

    // 2ch●ID, PW設定を読み込む
    if ($array = P2Util::readIdPw2ch()) {
        list($login2chID, $login2chPW, $autoLogin2ch, $methodLogin2ch) = $array;

    } else {
        P2Util::pushInfoHtml("<p>p2 error: 2chログインのためのIDとパスワードを登録して下さい。[<a href=\"login2ch.php\" target=\"subject\">2chログイン管理</a>]</p>");
        return false;
    }

    if ($methodLogin2ch == 1) {
        login2ch_tora3($login2chID, $login2chPW);
    } else {
        login2ch_uplift($login2chID, $login2chPW);
    }
}

// }}}
// {{{ login2ch_tora3()

/**
 * tora3へログイン
 */
function login2ch_tora3($login2chID, $login2chPW)
{
    global $_conf;

    // 浪人の有効性確認(要 ID / PW)
    if (!empty($login2chID) && !empty($login2chPW)) {
        P2Util::checkRoninExpiration();
    }

    $auth2ch_url= http_build_url(array(
        "scheme" => 'https',
        "host" => "2chv.tora3.net",
        "path" => "futen.cgi"));

    $dolib2ch = 'DOLIB/1.00';

    if($_conf['2chapi_use'] == 1) {
        if($_conf['2chapi_appname'] != "") {
            $x_2ch_ua = 'X-2ch-UA: ' . $_conf['2chapi_appname'];
        } else {
            P2Util::pushInfoHtml("<p>p2 error: 2chと通信するために必要な情報が設定されていません。</p>");
            return false;
        }
    } else {
        $x_2ch_ua = 'X-2ch-UA: ' . P2Commun::getP2UA(false,false);
    }

    try {
        $req = P2Commun::createHTTPRequest($auth2ch_url,HTTP_Request2::METHOD_POST);

        // ヘッダー
        $req->setHeader('User-Agent', $dolib2ch);
        $req->setHeader('X-2ch-UA', $x_2ch_ua);

        // POSTデータ
        $req->addPostParameter('ID', $login2chID);
        $req->addPostParameter('PW', $login2chPW);

        // POSTデータの送信
        $res = P2Commun::getHTTPResponse($req);

        $code = $res->getStatus();
        if ($code =! 200) {
            P2Util::pushInfoHtml("<p>p2 Error: HTTP Error({$code})</p>");
        } else {
            $body = $res->getBody();
        }

    } catch (Exception $e) {
        P2Util::pushInfoHtml("<p>p2 Error: ●の認証サーバに接続出来ませんでした。({$e->getMessage()})</p>");
    }

    // 接続失敗ならば
    if (empty($body)) {
        if (file_exists($_conf['idpw2ch_php'])) { unlink($_conf['idpw2ch_php']); }
        if (file_exists($_conf['sid2ch_php']))  { unlink($_conf['sid2ch_php']); }
        if (file_exists($_conf['siduplift_file']))  { unlink($_conf['siduplift_file']); }

        P2Util::pushInfoHtml('<p>p2 info: 2ちゃんねるへの●IDログインを行うには、PHPの<a href="'.
                P2Util::throughIme("http://www.php.net/manual/ja/ref.curl.php").
                '">cURL関数</a>又は<a href="'.
                P2Util::throughIme("http://www.php.net/manual/ja/ref.openssl.php").
                '">OpenSSL関数</a>が有効である必要があります。</p>');

        P2Util::pushInfoHtml("<p>p2 error: 2chログイン処理に失敗しました。{$curl_msg}</p>");
        return false;
    }

    $body = rtrim($body);

    // 分解
    if (preg_match('/SESSION-ID=(.+?):(.+)/', $body, $matches)) {
        $uaMona = $matches[1];
        $SID2ch = $matches[1] . ':' . $matches[2];
    } else {
        if (file_exists($_conf['sid2ch_php'])) { unlink($_conf['sid2ch_php']); }
        P2Util::pushInfoHtml("<p>p2 error: 2ch●ログイン接続に失敗しました。</p>");
        return false;
    }

    // 認証照合失敗なら
    if ($uaMona == 'ERROR') {
        file_exists($_conf['idpw2ch_php']) and unlink($_conf['idpw2ch_php']);
        file_exists($_conf['sid2ch_php']) and unlink($_conf['sid2ch_php']);
        P2Util::pushInfoHtml("<p>p2 error: 2ch●ログインのSESSION-IDの取得に失敗しました。IDとパスワードを確認の上、ログインし直して下さい。</p>");
        return false;
    }

    // SIDの記録保持
    file_exists($_conf['siduplift_file']) and unlink($_conf['siduplift_file']);
    $cont = sprintf('<?php $uaMona = %s; $SID2ch = %s;', var_export($uaMona, true), var_export($SID2ch, true));
    $temp_file = $_conf['sid2ch_php'] . '.tmp';
    if (FileCtl::file_write_contents($temp_file, serialize($sid)) === false) {
        P2Util::pushInfoHtml("<p>p2 Error: {$_conf['sid2ch_php']} を保存できませんでした。ログイン登録失敗。</p>");
        return false;
    }
    @chmod($temp_file, 0600);

    if (!rename($temp_file, $_conf['sid2ch_php'])) {
        @unlink($temp_file);
        P2Util::pushInfoHtml("<p>p2 Error: {$_conf['sid2ch_php']} を保存できませんでした。ログイン登録失敗。</p>");
        return false;
    }

    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($_conf['sid2ch_php'], true);
    }

    return $SID2ch;
}

// }}}
// {{{ login2ch_uplift()

/**
 * upliftへログイン
 */
function login2ch_uplift($login2chID, $login2chPW)
{
    global $_conf;

    $uplift_url = ($_conf['2ch_ssl.post'] ? 'https://' : 'http://') . 'uplift.' . $_conf['2ch_domain'] . '/';
    $sid_name = 'sid';
    $sid = null;
    $response_cookies = [];
    try {
        $req = P2Commun::createHTTPRequest ($uplift_url . 'log', HTTP_Request2::METHOD_POST);
        $req->setHeader('Referer', $uplift_url . 'login');
        $req->setHeader('Origin', rtrim($uplift_url, '/'));
        $req->setHeader('User-Agent', P2Commun::getP2UA(true, true));
        $req->addPostParameter('usr', $login2chID);
        $req->addPostParameter('pwd', $login2chPW);
        $response = P2Commun::getHTTPResponse($req);

        // Cookieを取得
        $cookies_names = [];
        $response_cookies = $response->getCookies();
        if ($response_cookies) {
            foreach ($response_cookies as $c) {
                if ($c['name'] == $sid_name) {
                    if (isset($c['expires']) && time() > strtotime($c['expires'])) {
                    } else {
                        $sid = $c;
                    }
                }
                array_push($cookies_names, $c['name']);
            }
        }
        $cookies_names_str = implode(',', $cookies_names);

        $code = $response->getStatus();
        if ($code == 302) {
            if ($sid) {
                // ログイン成功
            } else {
                P2Util::pushInfoHtml("<p>p2 error: UPLIFTにログインしましたが、sidの取得に失敗しました。取得したクッキーは[{$cookies_names_str}]です。</p>");
            }
        } elseif ($code == 200) {
            // ログイン失敗と思われる
            P2Util::pushInfoHtml("<p>p2 error: UPLIFTのログイン接続に失敗しました。IDとパスワードを確認の上、ログインし直して下さい。</p>");
            $sid = null;
        } else {
            P2Util::pushInfoHtml("<p>p2 Error: HTTP Error({$code})</p>");
            $sid = null;
        }
    } catch (Exception $e) {
        P2Util::pushInfoHtml("<p>p2 Error: UPLIFTのサーバに接続出来ませんでした。({$e->getMessage()})</p>");
        $sid = null;
    }

    if (!$sid) {
        if (file_exists($_conf['idpw2ch_php'])) { unlink($_conf['idpw2ch_php']); }
        if (file_exists($_conf['sid2ch_php']))  { unlink($_conf['sid2ch_php']); }
        if (file_exists($_conf['siduplift_file']))  { unlink($_conf['siduplift_file']); }
        return false;
    }

    // 有効期限を取得するが、失敗は気にしない
    $expiration = check_uplift_expiration($response_cookies);

    $data = array('sid' => $sid, 'cookies' => $response_cookies, 'expiration' => $expiration);

    file_exists($_conf['sid2ch_php']) and unlink($_conf['sid2ch_php']);
    $temp_file = $_conf['siduplift_file'] . '.tmp';
    if (FileCtl::file_write_contents($temp_file, serialize($data)) === false) {
        P2Util::pushInfoHtml("<p>p2 Error: {$_conf['siduplift_file']} を保存できませんでした。ログイン登録失敗。</p>");
        return false;
    }
    @chmod($temp_file, 0600);

    if (!rename($temp_file, $_conf['siduplift_file'])) {
        @unlink($temp_file);
        P2Util::pushInfoHtml("<p>p2 Error: {$_conf['siduplift_file']} を保存できませんでした。ログイン登録失敗。</p>");
        return false;
    }

    return $data;
}

// }}}
// {{{ get_uplift_sid()

/**
 * UPLIFTのsid取得
 */
function get_uplift_sid($idpw2ch = null)
{
    global $_conf;

    $content = file_get_contents($_conf['siduplift_file']);
    if ($content) {
        $data = @unserialize($content);
        $sid = $data['sid'];
        if ($sid && is_array($sid)) {
            // 期限切れ1時間前なら再ログイン
            if (isset($sid['expires']) && time() > strtotime($sid['expires']) - 3600) {
                if ($idpw2ch == null) {
                    $idpw2ch = P2Util::readIdPw2ch();
                }
                if ($idpw2ch) {
                    list($login2chID, $login2chPW, $autoLogin2ch, $methodLogin2ch) = $idpw2ch;
                    if ($methodLogin2ch != 1) {
                        $new_data = login2ch_uplift($login2chID, $login2chPW);
                        if ($new_data) {
                            $data = $new_data;
                            $sid = $data['sid'];
                        }
                    }
                }
            }
            if (isset($sid['expires']) && time() > strtotime($sid['expires'])) {
                @unlink($_conf['siduplift_file']);
                return null;
            }
            if (isset($sid['name']) && isset($sid['value'])) {
                return $data;
            }
        }
    }
    return null;
}

// }}}
// {{{ get_uplift_sid()

/**
 * UPLIFTの有効期限を確認
 */
function check_uplift_expiration($uplift_cookie)
{
    global $_conf;

    $uplift_url = ($_conf['2ch_ssl.post'] ? 'https://' : 'http://') . 'uplift.5ch.net/';
    $body = null;
    try {
        $req = P2Commun::createHTTPRequest ($uplift_url . 'dashboard', HTTP_Request2::METHOD_GET);
        $req->setHeader('User-Agent', P2Commun::getP2UA(true, true));
        if ($uplift_cookie) {
            // ログイン中なのでクッキーの期限は大丈夫なはずなのでそのまま詰める
            foreach ($uplift_cookie as $c) {
                $req->addCookie($c['name'], $c['value']);
            }
        }
        $response = P2Commun::getHTTPResponse($req);
        $code = $response->getStatus();
        if ($code != 200) {
            P2Util::pushInfoHtml("<p>p2 Error: HTTP Error({$code})</p>");
        } else {
            $body = $response->getBody();
        }
    } catch (Exception $e) {
        P2Util::pushInfoHtml("<p>p2 Error: UPLIFTのサーバに接続出来ませんでした。({$e->getMessage()})</p>");
    }

    if (!$body) {
        return null;
    }

    $pattern = '/有効期限日:\s*(\d+)年\s*(\d+)月\s*(\d+)日\s*(\d+)時\s*(\d+)分\s*(\d+)秒/u';
    $pattern = mb_convert_encoding($pattern, 'UTF-8', 'CP932');
    if (!preg_match($pattern, $body, $matches)) {
        P2Util::pushInfoHtml("<p>p2 error: 有効期限が取得できませんでした｡</p>");
        return false;
    }

    // UPLIFTの有効期限日の表示はUTCなのでJSTに変換する
    $expiration = gmmktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]);
    return $expiration;
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
