<?php

// {{{ UA

/**
 * staticメソッドで利用する
 */
class UA
{
    // }}} isIPhoneGroup

    /**
     * UAがiPhone, iPod touch, Androidならtrueを返す。
     *
     * @param   string   $ua  UAを指定するなら
     * @return  boolean
     */
    static public function isIPhoneGroup($ua = null)
    {
        if (is_null($ua) and isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }

        // iPhone
        // Mozilla/5.0 (iPhone; U; CPU like Mac OS X; en) AppleWebKit/420+ (KHTML, like Gecko) Version/3.0 Mobile/1A543a Safari/419.3

        // iPod touch
        // Mozilla/5.0 (iPod; U; CPU like Mac OS X; ja-jp) AppleWebKit/420.1 (KHTML, like Gecko) Version/3.0 Mobile/3A110a Safari/419.3
        if (preg_match('/iP(?:hone|[ao]d)/', $ua) || self::isAndroid($ua)) {
            return true;
        }

        return false;
    }

    // }}} isAndroid

    /**
     * UAがAndroidならtrueを返す。
     *
     * @param   string   $ua  UAを指定するなら
     * @return  boolean
     */
    static public function isAndroid($ua = null)
    {
        if (is_null($ua) and isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }
        if (!$ua) {
            return false;
        }
        // シミュレータ
        // Mozilla/5.0 (Linux; U; Android 1.0; en-us; generic) AppleWebKit/525.10+ (KHTML, like Gecko) Version/3.0.4 Mobile Safari/523.12.2
        // T-mobile G1
        // Mozilla/5.0 (Linux; U; Android 1.0; en-us; dream) AppleWebKit/525.10+ (KHTML, like Gecko) Version/3.0.4 Mobile Safari/523.12.2
        // genericとdreamが異なる
        if (false !== strpos($ua, 'Android')) {
            return true;
        }
        return false;
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
