<?php
/**
 * talk.jp 用 dat ダウンロードクラス
 */

// {{{ DownloadDatTalk

/**
 * JSONのDAT変換とDATダウンロードの差異
 *   メール欄の復元不可: JSON API からはメール欄の情報が取得できないため、DAT への復元は行えない
 *   ミリ秒のダミー補完: JSON API で提供されない書き込み時刻のミリ秒情報について、DAT 形式の仕様に合わせるためダミー値(.00)で補完している
 *   数値文字参照の表現形式の差異: 直接取得したDATでは10進数表記（&#...;）となるが、JSON経由では16進数表記（&#x...;）でなる(値としては同じ)
 *   数値文字参照への置き換え有無の差異： 数値文字参照への置き換えが起きたり起きなかったり差が多少発生している(実害は無さそうに見える)
 */
class DownloadDatTalk implements DownloadDatInterface
{
    // {{{ invoke()

    /**
     * スレッドの dat をダウンロードし、保存する
     *
     * @param ThreadRead $thread
     * @return bool
     */
    static public function invoke(ThreadRead $thread)
    {
        require_once P2_LIB_DIR . '/authtalkapi.inc.php';
        $talkapi = AuthTalkAPI::load();
        if ($talkapi && $talkapi->auth_sid) {
            return self::downloadDatDirect($thread, $talkapi);
        } else {
            return self::downloadFromJson($thread);
        }
    }

    // }}}
    // {{{ downloadDatDirect()
    /**
    * DATを直接ダウンロードする
    */
    static private function downloadDatDirect(ThreadRead $thread, AuthTalkAPI $talkapi)
    {
        global $_conf;

        $host = "api.talk-platform.com";
        $bbs = $thread->bbs;
        $key = $thread->key;

        $scheme = P2Util::selectScheme($host);
        $path = "/v1/classic/{$bbs}/{$key}";
        $url = "{$scheme}://{$host}{$path}";
        $post_data = $talkapi->make_hobo($path);
        if (empty($post_data)) {
            return self::downloadFromJson($thread);
        }

        $tempfile = $thread->keydat . '.tmp';
        FileCtl::mkdirFor($tempfile);
        if (file_exists($tempfile)) {
            unlink($tempfile);
        }

        try {
            $req = P2Commun::createHTTPRequest($url, HTTP_Request2::METHOD_POST, $_conf['talkapi_ua.auth'] ?: null);

            foreach ($post_data as $name => $val) {
                $req->addPostParameter($name, $val);
            }

            $response = P2Commun::getHTTPResponse($req);
        } catch (Exception $e) {
            $debug_info = P2Commun::getDebugInfo($req, $response ?? null, $post_data);
            $debug_txt = '<hr><h3>通信内容</h3><pre>' . p2h($debug_info) . '</pre>';
            $thread->getdat_error_msg_ht .= '<p>dat取得エラー(talk): ' . $e->getMessage() . '</p>' . $debug_txt;
            $thread->diedat = true;
            return false;
        }

        $code = $response->getStatus();
        if ($code != 200) {
            $debug_info = P2Commun::getDebugInfo($req, $response, $post_data);
            $debug_txt = '<hr><h3>通信内容</h3><pre>' . p2h($debug_info) . '</pre>';
            $thread->getdat_error_msg_ht .= '<p>dat取得エラー(talk): HTTP ' . $code . '</p>' . $debug_txt;
            $thread->diedat = true;
            return false;
        }

        $dat = $response->getBody();
        if (empty($dat) || str_starts_with($dat, 'ng (not valid)')) {
            $thread->getdat_error_msg_ht .= '<p>dat取得エラー(talk): データがありません</p>';
            $thread->diedat = true;
            return false;
        }

        if (FileCtl::file_write_contents($tempfile, $dat, 0) === false) {
            p2die('cannot write file.');
        }

        if (!rename($tempfile, $thread->keydat)) {
            p2die('cannot rename file.');
        }

        $thread->isonline = true;
        $thread->gotnum = substr_count($dat, "\n");
        if (substr($dat, -1) !== "\n") {
            $thread->gotnum++;
        }

        return true;
    }
    // }}}

    // {{{ downloadFromJson()
    /**
    * JSONから取得してDATに変換する
    */
    static private function downloadFromJson(ThreadRead $thread)
    {
        $host = $thread->host;
        $bbs = $thread->bbs;
        $key = $thread->key;

        $scheme = P2Util::selectScheme($host);
        $url = "{$scheme}://{$host}/api/boards/{$bbs}/threads/{$key}";

        try {
            $req = P2Commun::createHTTPRequest($url, HTTP_Request2::METHOD_GET);
            $response = P2Commun::getHTTPResponse($req);
        } catch (Exception $e) {
            $thread->getdat_error_msg_ht .= '<p>dat取得エラー(talk): ' . $e->getMessage() . '</p>';
            $thread->diedat = true;
            return false;
        }

        $code = $response->getStatus();
        if ($code != 200) {
            $thread->getdat_error_msg_ht .= '<p>dat取得エラー(talk): HTTP ' . $code . '</p>';
            $thread->diedat = true;
            return false;
        }

        $json = json_decode($response->getBody(), true);
        if (!isset($json['data'])) {
            $thread->getdat_error_msg_ht .= '<p>dat取得エラー(talk): 予期せぬ応答形式</p>';
            $thread->diedat = true;
            return false;
        }

        $data = mb_convert_encoding($json['data'], 'CP932', 'UTF-8');
        $thread_title = p2h($data['title'] ?? '', false);
        $quote_source = p2h($data['quote_source'] ?? '', false);
        $default_name = $data['board']['settings']['default_name'] ?? '';
        $comments = $data['comments'];
        $weekday = array('日', '月', '火', '水', '木', '金', '土');
        $data_map = array();
        foreach ($comments as $c) {
            $data_map[(int)$c['number']] = $c;
        }

        if (empty($comments)) {
            $thread->getdat_error_msg_ht .= '<p>dat取得エラー(talk): コメントデータがありません</p>';
            $thread->diedat = true;
            return false;
        }
        $last_number = (int)$comments[count($comments) - 1]['number'];

        $dat = '';
        for ($i = 1; $i <= $last_number; $i++) {
            if (isset($data_map[$i])) {
                $comment = $data_map[$i];
                $writer = $comment['writer'] ?? null;

                if (!isset($writer['name']) || !isset($comment['body'])) {
                    $abon = "削除<>削除<>削除<>削除<>";
                    if ($i === 1) {
                        // 1が削除されているケースは発見出来ず、テスト未実施
                        $abon .= $thread_title;
                    } else {
                        $abon .= "削除";
                    }
                    $dat .= "{$abon}\n";
                    continue;
                }

                $name = p2h($writer['name'] ?: $default_name, false);

                if (isset($writer['trip'])) {
                    $trip = p2h($writer['trip'], false);
                    $name .= "</b>◆{$trip}<b>";
                }

                if (isset($writer['slip'])) {
                    $slip = p2h($writer['slip'], false);
                    $name .= " </b>({$slip})<b>";
                }

                $id = p2h($writer['id'] ?? '', false);
                $talk_id = p2h($comment['user']['talk_id'] ?? '', false);
                $id_part = "ID:{$id}";
                if ($talk_id !== '') {
                    $id_part .= " TID:{$talk_id}";
                }

                $jst = $comment['timestamp'];
                $date_str = date('Y/m/d', $jst) . '(' . $weekday[date('w', $jst)] . ') ' . date('H:i:s', $jst) . '.00';

                $body = p2h($comment['body'], false);
                $body = str_replace("\n", ' <br> ', $body);

                if ($i === 1) {
                    $title_part = " <>{$thread_title} ";
                    if ($quote_source !== '') {
                        $body .= ' <br>  <br> 出典 ' . $quote_source;
                    }
                } else {
                    $title_part = ' <>';
                }

                $dat .= "{$name}<><>{$date_str} {$id_part}<> {$body}{$title_part}\n";
            } else {
                // レス番号が欠番しているケースは発見出来ず、テスト未実施
                $abon = "あぼーん<>あぼーん<>あぼーん<>あぼーん<>";
                if ($i === 1) {
                    $abon .= $thread_title;
                } else {
                    $abon .= "あぼーん";
                }
                $dat .= "{$abon}\n";
            }
        }

        FileCtl::mkdirFor($thread->keydat);
        if (FileCtl::file_write_contents($thread->keydat, $dat, 0) === false) {
            p2die('cannot write file.');
        }

        $thread->isonline = true;
        $thread->gotnum = $last_number;

        return true;
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
