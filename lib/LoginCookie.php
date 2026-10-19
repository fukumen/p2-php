<?php

/**
 * rep2 - Cookie情報（CID）
 */
// {{{ LoginCookie

class LoginCookie
{
    // {{{ properties

    private $encryptor;

    // }}}
    // {{{ constructor

    public function __construct()
    {
        $this->encryptor = P2Encryptor::getInstance();
    }

    // }}}
    // {{{ hasCid()

    /**
     * cid cookie が存在するか
     *
     * @return bool
     */
    public function hasCid()
    {
        return isset($_COOKIE['cid']);
    }

    // }}}
    // {{{ clearCookieAuth()

    /**
     * Cookie認証をクリアする
     *
     */
    public function clearCookieAuth()
    {
        if (!$this->hasCid()) {
            return true;
        }

        setcookie('cid', '', time() - 3600);
        $_COOKIE = array();

        return true;
    }

    // }}}
    // {{{ setCookieCid()

    /**
     * CIDをcookieにセットする
     *
     * @param string $user_u
     * @param string $pass_x
     * @param float|null $device_pixel_ratio
     * @return boolean
     */
    public function setCookieCid($user_u, $pass_x, $device_pixel_ratio = null)
    {
        global $_conf;

        $time = time() + 60*60*24 * $_conf['cid_expire_day'];

        if ($cid = $this->makeCid($user_u, $pass_x, $device_pixel_ratio)) {
            return P2Util::setCookie('cid', $cid, $time);
        }
        return false;
    }

    // }}}
    // {{{ makeCid()

    /**
     * IDとPASSと時間をくるめて暗号化したCookie情報（CID）を生成取得する
     *
     * @param string $user_u
     * @param string $pass_x
     * @param float|null $device_pixel_ratio
     * @return mixed
     */
    public function makeCid($user_u, $pass_x, $device_pixel_ratio = null)
    {
        if (is_null($user_u) || is_null($pass_x)) {
            return false;
        }

        $user_time  = $user_u . ':' . time() . ':';
        $hash_utpx = P2Hash::hash($user_time . $pass_x);
        $cid_src  = $user_time . $hash_utpx;
        if ($device_pixel_ratio !== null) {
            $cid_src .= ':' . $device_pixel_ratio;
        }
        return $this->encryptor->encrypt($cid_src);
    }

    // }}}
    // {{{ getCidInfo()

    /**
     * Cookie（CID）からユーザ情報を得る
     *
     * 4要素目の devicePixelRatio は float に復元して返す
     *
     * @return array|false 成功すれば配列、失敗なら false を返す
     */
    public function getCidInfo()
    {
        if (!isset($_COOKIE['cid'])) {
            return false;
        }

        $dec = $this->encryptor->decrypt($_COOKIE['cid']);
        if (!$dec) {
            return false;
        }

        $cid_info = explode(':', $dec);
        if (count($cid_info) === 4) {
            // 4要素目の devicePixelRatio は文字列で暗号化されているため float に復元する
            $cid_info[3] = floatval($cid_info[3]);
        } elseif (count($cid_info) !== 3) {
            return false;
        }

        list($user, $time, $hash_utpx) = $cid_info;
        if (!strlen($user) || !$time || !$hash_utpx) {
            return false;
        }

        return $cid_info;
    }

    // }}}
    // {{{ isExpiredCid()

    /**
     * CID情報の有効期限が切れているか
     *
     * @param array $cid_info
     * @return boolean
     */
    public function isExpiredCid($cid_info)
    {
        global $_conf;

        return time() > $cid_info[1] + (60*60*24 * $_conf['cid_expire_day']);
    }

    // }}}
    // {{{ compareUserPw()

    /**
     * CID情報とuser, passを照合する (復号済みの CID情報 を受け取る)
     *
     * @param array $cid_info
     * @param string $user_u
     * @param string $pass_x
     * @return boolean
     */
    public function compareUserPw($cid_info, $user_u, $pass_x)
    {
        if (is_null($user_u) || is_null($pass_x)) {
            return false;
        }

        $time = $cid_info[1];
        $pw_enc = $cid_info[2];

        // PWを照合
        if ($pw_enc == P2Hash::hash($user_u . ':' . $time . ':' . $pass_x)) {
            return true;
        } else {
            return false;
        }
    }

    // }}}
    // {{{ checkUserPwWithCid()

    /**
     * Cookie情報（CID）とuser, passを照合する
     *
     * @param string $user_u
     * @param string $pass_x
     * @return boolean
     */
    public function checkUserPwWithCid($user_u, $pass_x)
    {
        if (is_null($user_u) || is_null($pass_x)) {
            return false;
        }

        if (!$ar = $this->getCidInfo()) {
            return false;
        }

        if ($this->isExpiredCid($ar)) {
            return false; // 期限切れ
        }

        return $this->compareUserPw($ar, $user_u, $pass_x);
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
