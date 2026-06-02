<?php
/**
 * rep2 - 2chログイン
 */

// {{{ authenticate_2chapi()


/**
* 2chAPIの SID を取得する
*
* @return mix 取得できた場合はSIDを返す
*/
    function authenticate_2chapi()
    {
    	global $_conf;

        $url= http_build_url(array(
            "scheme" => $_conf['2chapi_ssl.auth']?"https":"http",
            "host" => "api.5ch.net",
            "path" => "v1/auth/"));

        $CT = time();
        $AppKey = $_conf['2chapi_appkey'];
        $AppName = $_conf['2chapi_appname'];
        $HMKey = $_conf['2chapi_hmkey'];
        $AuthUA = sprintf($_conf['2chapi_ua.auth'],$AppName);
        $login2chID = "";
        $login2chPW = "";
        $message = $AppKey.$CT;
        $HB = hash_hmac("sha256", $message, $HMKey);

        if(empty($AppKey) || empty($AppName) || empty($HMKey)) {
            P2Util::pushInfoHtml("<p>p2 Error: 2ch API の認証に必要な情報が設定されていません。</p>");
            return '';
        }

        if ($array = P2Util::readIdPw2ch()) {
            list($login2chID, $login2chPW, $autoLogin2ch, $methodLogin2ch) = $array;
        }

        // 浪人の有効性確認(要 ID / PW)
        if (!empty($login2chID) && !empty($login2chPW)) {
            P2Util::checkRoninExpiration();
        }

        try {
            $req = P2Commun::createHTTPRequest($url, HTTP_Request2::METHOD_POST, $AuthUA);

            $req->setHeader('X-2ch-UA', $AppName);

            $req->addPostParameter('ID', $login2chID);
            $req->addPostParameter('PW', $login2chPW);
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
            P2Util::pushInfoHtml("<p>p2 Error: 2ch API の認証サーバに接続出来ませんでした。({$e->getMessage()})</p>");
        }

        if(file_exists($_conf['sid2chapi_php'])) {
            unlink($_conf['sid2chapi_php']);
        }

        // 接続失敗ならば
        if (empty($body)) {
            P2Util::pushInfoHtml('<p>p2 info: 2ちゃんねるのAPIを使用するには、PHPの<a href="'.
                    P2Util::throughIme("http://www.php.net/manual/ja/ref.curl.php").
                    '">cURL関数</a>又は<a href="'.
                    P2Util::throughIme("http://www.php.net/manual/ja/ref.openssl.php").
                    '">OpenSSL関数</a>が有効である必要があります。</p>');

            P2Util::pushInfoHtml("<p>p2 error: 2ch API認証に失敗しました。{$curl_msg}</p>");
            return false;
        }

        $body = rtrim($body);

        // 分解
        if (!preg_match('/SESSION-ID=(.+?):(.+)/', $body, $matches)) {
            if (file_exists($_conf['sid2chapi_php'])) { unlink($_conf['sid2chapi_php']); }
            P2Util::pushInfoHtml("<p>p2 error: 2ch API のレスポンスからSessionIDを取得出来ませんでした。</p>");
            return false;
        }
        $uaMona = $matches[1];
        $SID2chAPI = $matches[1] . ':' . $matches[2];

        // SID の記録保持
        $cont = sprintf('<?php $uaMona = %s; $SID2chAPI = %s;', var_export($uaMona, true), var_export($SID2chAPI, true));
        if (false === file_put_contents($_conf['sid2chapi_php'], $cont, LOCK_EX)) {
            P2Util::pushInfoHtml("<p>p2 Error: {$_conf['sid2chapi_php']} を保存できませんでした。ログイン登録失敗。</p>");
            return false;
        }

        return $SID2chAPI;
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
