<?php
/**
 * rep2expack - パスキーchallenge管理クラス
 */

// {{{ PasskeyChallengeDataStore

class PasskeyChallengeDataStore extends AbstractDataStore
{
    /**
     * challengeの有効期限（秒）
     */
    const TTL = 120;

    // {{{ getKVS()

    /**
     * challengeを保存するP2KeyValueStoreオブジェクトを取得する
     *
     * @param void
     * @return P2KeyValueStore
     */
    static public function getKVS()
    {
        return self::_getKVS($GLOBALS['_conf']['passkey_challenge_db_path']);
    }

    // }}}
    // {{{ gc()

    /**
     * 固定確率で実行し、TTLより古いchallengeレコードを削除する
     *
     * challengeを発行するまでDBを作らないため、DBファイルが存在しない場合は何もしない。
     *
     * @param void
     * @return int|false 削除したレコード数 (実行しなかった場合はfalse)
     */
    static public function gc()
    {
        if (mt_rand(1, 100) != 1) {
            return false;
        }
        if (!file_exists($GLOBALS['_conf']['passkey_challenge_db_path'])) {
            return false;
        }

        return self::getKVS()->gc(self::TTL);
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
