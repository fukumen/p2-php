<?php

/**
 * rep2 - Login の認証結果
 */

// {{{ LoginResult

class LoginResult
{
    // {{{ constants

    const STATUS_PASS = 'pass';
    const STATUS_FAIL = 'fail';

    const R_NO_IDENTITY = 'no_identity';
    const R_IDENTITY_MISMATCH = 'identity_mismatch';
    const R_NO_PASSWORD = 'no_password';
    const R_LOCKOUT = 'lockout';
    const R_FORM_REJECTED = 'form_rejected';
    const R_SESSION_ERROR = 'session_error';
    const R_NO_PROOF = 'no_proof';
    const R_PASSKEY_REJECTED = 'passkey_rejected';
    const R_NEW_REGIST_INPUT_ERROR = 'new_regist_input_error';
    const R_NEW_REGIST_PATTERN_ERROR = 'new_regist_pattern_error';

    const E_REFLECT_LOGIN = 'reflect_login';
    const E_MAKE_USER = 'make_user';
    const E_CLEAR_STRENGTHENED = 'clear_strengthened';
    const E_UNSESSION = 'unsession';
    const E_CLEAR_COOKIE = 'clear_cookie';
    const E_LOG_SUCCESS = 'log_success';
    const E_LOG_FAILED = 'log_failed';
    const E_BEGIN_SESSION = 'begin_session';
    const E_UPDATE_ACTIME = 'update_actime';
    const E_UPDATE_SESSION_VARS = 'update_session_vars';
    const E_UPDATE_SESSION_PASSKEY = 'update_session_passkey';
    const E_RECORD_LOGIN_MICROTIME = 'record_login_microtime';
    const E_RECORD_DEVICE_PIXEL_RATIO = 'record_device_pixel_ratio';
    const E_REGISTER_COOKIE = 'register_cookie';
    const E_CLOSE_SESSION = 'close_session';
    const E_LOGOUT_REDIRECT = 'logout_redirect';
    const E_REDIRECT = 'redirect';
    const E_SHOW_REGIST_DONE = 'show_regist_done';

    // }}}
    // {{{ properties

    public $status = self::STATUS_FAIL;
    public $effects = array();

    /* PASS */
    public $identity = null;
    public $passX = null;
    public $redirectTo = null;
    public $formLoginPass = null;
    public $devicePixelRatio = null;

    /* FAIL */
    public $reason = null;
    public $extra = null;

    // }}}
    // {{{ pass()

    /**
     * PASS (form / state / passkey 経路)
     *
     * @param string $identity
     * @param string|null $passX
     * @param float|null $devicePixelRatio
     * @param string|null $redirectTo
     * @return LoginResult
     */
    static public function pass($identity, $passX, $devicePixelRatio = null, $redirectTo = null)
    {
        $r = new self();
        $r->status = self::STATUS_PASS;
        $r->identity = $identity;
        $r->passX = $passX;
        $r->devicePixelRatio = $devicePixelRatio;
        $r->redirectTo = $redirectTo;
        return $r;
    }

    // }}}
    // {{{ passRegist()

    /**
     * PASS (form_new 経路)
     *
     * @param string $identity
     * @param string $formLoginPass
     * @param float|null $devicePixelRatio
     * @return LoginResult
     */
    static public function passRegist($identity, $formLoginPass, $devicePixelRatio = null)
    {
        $r = new self();
        $r->status = self::STATUS_PASS;
        $r->identity = $identity;
        $r->formLoginPass = $formLoginPass;
        $r->devicePixelRatio = $devicePixelRatio;
        return $r;
    }

    // }}}
    // {{{ fail()

    /**
     * FAIL
     *
     * @param string $reason
     * @param mixed $extra
     * @return LoginResult
     */
    static public function fail($reason, $extra = null)
    {
        $r = new self();
        $r->status = self::STATUS_FAIL;
        $r->reason = $reason;
        $r->extra = $extra;
        array_unshift($r->effects, self::E_UNSESSION);
        return $r;
    }

    // }}}
    // {{{ isPass()

    /**
     * 認証が確立したかどうか
     */
    public function isPass()
    {
        return $this->status === self::STATUS_PASS;
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
