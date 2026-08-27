<?php
/**
 * rep2expack - Cookie管理クラス
 */

// {{{ CookieDataStore

class CookieDataStore extends AbstractDataStore
{
    // {{{ getKVS()

    /**
     * Cookieを保存するP2KeyValueStoreオブジェクトを取得する
     *
     * @param void
     * @return P2KeyValueStore
     */
    static public function getKVS()
    {
        return self::_getKVS($GLOBALS['_conf']['cookie_db_path']);
    }

    // }}}
    // {{{ loadActive()

    /**
     * 有効なクッキーのみを取得する
     * 値が配列でない項目(旧形式・破損データ)と期限切れの項目は取り除き、
     * 変更があれば保存し直す。レコードが空になった場合は削除する。
     *
     * @param string $key
     * @return array name => array('value' => string, 'expires' => int|null)
     */
    static public function loadActive($key)
    {
        $data = self::get($key);
        if (!is_array($data)) {
            if ($data !== null) {
                self::delete($key);
            }
            return array();
        }
        $now = time();
        $changed = false;
        foreach ($data as $name => $c) {
            if (!is_array($c) || !isset($c['value'])) {
                unset($data[$name]);
                $changed = true;
            } elseif (isset($c['expires']) && $now >= $c['expires']) {
                unset($data[$name]);
                $changed = true;
            }
        }
        if (!$data) {
            self::delete($key);
            return array();
        }
        if ($changed) {
            self::set($key, $data);
        }
        return $data;
    }

    // }}}
    // {{{ mergeResponse()

    /**
     * 受信したクッキーを保存用配列へマージする
     * 期限切れのクッキーは削除指示として扱い、該当項目を取り除く
     *
     * @param array &$cookies 保存用クッキー配列 (参照渡し)
     * @param array $responseCookies getCookies() の戻り値
     * @return void
     */
    static public function mergeResponse(&$cookies, $responseCookies)
    {
        if (!$responseCookies) {
            return;
        }
        if (!$cookies) {
            $cookies = array();
        }
        foreach ($responseCookies as $c) {
            if (empty($c['name'])) {
                continue;
            }
            $expires = (!empty($c['expires'])) ? strtotime($c['expires']) : null;
            if ($expires !== null && $expires !== false && time() >= $expires) {
                unset($cookies[$c['name']]);
            } else {
                $cookies[$c['name']] = array(
                    'value' => $c['value'],
                    'expires' => ($expires === false) ? null : $expires,
                );
            }
        }
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
