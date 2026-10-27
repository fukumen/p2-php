<?php

/**
 * rep2 - 認証検証の入力 (collect)
 *
 */

// {{{ LoginCtx

class LoginCtx
{
    const INTENT_FORM = 'form';
    const INTENT_FORM_NEW = 'form_new';
    const INTENT_PASSKEY = 'passkey';
    const INTENT_STATE = 'state';
    const INTENT_NONE = 'none';

    public $intent;
    public $identity = null;
    public $authFileExists = false;
    public $recUser = null;
    public $passX = null;
    public $cidCookiePresent = false;
    public $cidInfo = false;
    public $cidExpired = false;
    public $passkeyOnly = false;
    public $logout = false;

    /**
     * 共通ゲートの入力と intent を集める
     *
     * @param Login $login
     * @return LoginCtx
     */
    static public function collect(Login $login)
    {
        global $_conf;

        $ctx = new self();
        $cookie = $login->getCookie();

        $ctx->cidCookiePresent = $cookie->hasCid();
        $ctx->cidInfo = $cookie->getCidInfo();
        $ctx->passkeyOnly = (P2PasskeyStore::getEffectiveMode() == 2);
        $ctx->logout = !empty($_REQUEST['logout']);

        // 有効期限チェック (副作用なし)
        if ($ctx->cidInfo !== false) {
            $ctx->cidExpired = $cookie->isExpiredCid($ctx->cidInfo);
        }

        // ユーザ名決定の優先順位に沿って
        $pattern = "/^[0-9A-Za-z_]+\$/";

        // ログインフォームからの指定
        if (isset($_REQUEST['form_login_id']) && preg_match($pattern, $_REQUEST['form_login_id'])) {
            $ctx->identity = $_REQUEST['form_login_id'];

        // Cookieで指定
        } elseif ($ctx->cidInfo !== false && !$ctx->cidExpired && preg_match($pattern, $ctx->cidInfo[0])) {
            $ctx->identity = $ctx->cidInfo[0];

        // Sessionで指定
        } elseif (isset($_SESSION['login_user']) && preg_match($pattern, $_SESSION['login_user'])) {
            $ctx->identity = $_SESSION['login_user'];
        }

        $isPasskey = !empty($_POST['assertion']);
        if ($ctx->identity !== null || $isPasskey) {
            $login->checkAuthUserFile();    // 副作用あり（許容する）
            $ctx->authFileExists = file_exists($_conf['auth_user_file']);
            $rec_pass_x = null;
            if ($ctx->authFileExists) {
                include $_conf['auth_user_file'];
                $ctx->recUser = $rec_login_user_u;
                if (!$isPasskey && isset($rec_login_pass_x) && strlen($rec_login_pass_x) > 0) {
                    $rec_pass_x = $rec_login_pass_x;
                }
            }
            $ctx->passX = $rec_pass_x;
        }

        if (!empty($_POST['submit_member'])) {
            $ctx->intent = self::INTENT_FORM;
        } elseif (!empty($_POST['submit_new'])) {
            $ctx->intent = self::INTENT_FORM_NEW;
        } elseif ($isPasskey) {
            $ctx->intent = self::INTENT_PASSKEY;
        } elseif ($ctx->cidCookiePresent || (isset($_SESSION['login_user']) && (isset($_SESSION['login_pass_x']) || isset($_SESSION['login_passkey_auth'])))) {
            $ctx->intent = self::INTENT_STATE;
        } else {
            $ctx->intent = self::INTENT_NONE;
        }

        return $ctx;
    }
}

// }}}
// {{{ LoginFormCtx

class LoginFormCtx
{
    public $requestUri = null;
    public $devicePixelRatio = null;
    public $registerCid = false;
    public $lockout = false;
    public $formVerified = false;

    /**
     * form 経路用の入力を集める
     *
     * @param Login $login
     * @param LoginCtx $ctx
     * @return LoginFormCtx
     */
    static public function collect(Login $login, LoginCtx $ctx)
    {
        $ctx2 = new self();
        $ctx2->requestUri = $_SERVER['REQUEST_URI'] ?? null;
        $ctx2->devicePixelRatio = $login->normalizeDevicePixelRatio($_REQUEST['device_pixel_ratio'] ?? null);
        $ctx2->registerCid = !empty($_REQUEST['ctl_keep_login']) && !empty($_REQUEST['keep_login']);
        $ctx2->lockout = $login->checkLockout();    // アクセスログの読み込みのみで副作用なし

        $formLoginPass = $_POST['form_login_pass'] ?? null;
        $formLoginId = $_POST['form_login_id'] ?? null;

        // P2Hash は副作用なし
        if (!$ctx2->lockout && $formLoginId == $ctx->identity) {
            $ctx2->formVerified = $login->getHash()->password_verify($formLoginPass, $ctx->passX);
        }

        return $ctx2;
    }
}

// }}}
// {{{ LoginRegistCtx

class LoginRegistCtx
{
    public $formLoginId = null;
    public $formLoginPass = null;
    public $devicePixelRatio = null;
    public $registerCid = false;

    /**
     * form_new 経路用の入力を集める
     *
     * @param Login $login
     * @param LoginCtx $ctx
     * @return LoginRegistCtx
     */
    static public function collect(Login $login, LoginCtx $ctx)
    {
        $ctx2 = new self();
        $ctx2->formLoginId = $_POST['form_login_id'] ?? null;
        $ctx2->formLoginPass = $_POST['form_login_pass'] ?? null;
        $ctx2->devicePixelRatio = $login->normalizeDevicePixelRatio($_REQUEST['device_pixel_ratio'] ?? null);
        $ctx2->registerCid = !empty($_REQUEST['ctl_keep_login']) && !empty($_REQUEST['keep_login']);

        return $ctx2;
    }
}

// }}}
// {{{ LoginPasskeyCtx

class LoginPasskeyCtx
{
    public $requestUri = null;
    public $devicePixelRatio = null;
    public $lockout = false;
    public $passkeyVerified = false;
    public $failMessage = null;

    /**
     * passkey 経路用の入力を集める
     *
     * @param Login $login
     * @param LoginCtx $ctx
     * @return LoginPasskeyCtx
     */
    static public function collect(Login $login, LoginCtx $ctx)
    {
        $ctx2 = new self();
        $ctx2->requestUri = $_SERVER['REQUEST_URI'] ?? null;
        $ctx2->devicePixelRatio = $login->normalizeDevicePixelRatio($_REQUEST['device_pixel_ratio'] ?? null);
        $ctx2->lockout = $login->checkLockout();    // アクセスログの読み込みのみで副作用なし

        // verifyLoginは副作用あり（許容する）
        if (!$ctx2->lockout) {
            try {
                P2PasskeyAuth::verifyLogin($_POST['assertion']);
                $ctx2->passkeyVerified = true;
            } catch (P2PasskeyAuthException $e) {
                $ctx2->failMessage = $e->getMessage();
            }
        }

        return $ctx2;
    }
}

// }}}
// {{{ LoginStateCtx

class LoginStateCtx
{
    public $devicePixelRatio = null;
    public $cidVerified = false;
    public $sessionChecked = false;
    public $sessionError = false;
    public $sessionVerified = false;
    public $sessionUser = null;
    public $passkeyAuth = false;

    /**
     * state 経路用の入力を集める
     *
     * @param Login $login
     * @param LoginCtx $ctx
     * @return LoginStateCtx
     */
    static public function collect(Login $login, LoginCtx $ctx)
    {
        $ctx2 = new self();

        $cookie = $login->getCookie();

        if ($ctx->cidInfo !== false && !$ctx->cidExpired && count($ctx->cidInfo) === 4) {
            $ctx2->devicePixelRatio = $ctx->cidInfo[3];
        }

        // cid 照合 (副作用なし)
        if ($ctx->cidCookiePresent && $ctx->cidInfo !== false && !$ctx->cidExpired) {
            $ctx2->cidVerified = $cookie->compareUserPw($ctx->cidInfo, $ctx->identity, $ctx->passX);
        }

        $sessionUser = $_SESSION['login_user'] ?? null;
        $sessionPassX = $_SESSION['login_pass_x'] ?? null;
        $sessionPasskeyAuth = isset($_SESSION['login_passkey_auth']);

        $ctx2->passkeyAuth = $sessionPasskeyAuth;

        // セッション判定 (checkSessionErrors は副作用なし)。cid で成立した場合は判定しない。
        if (!$ctx2->cidVerified && $sessionUser !== null && ($sessionPassX !== null || $sessionPasskeyAuth)) {
            $ctx2->sessionChecked = true;
            $ctx2->sessionError = $login->checkSessionErrors();
        }

        // P2Hash::hashは副作用無し
        $ctx2->sessionVerified = ($sessionUser == $ctx->identity && $sessionPassX == P2Hash::hash($ctx->passX));

        $ctx2->sessionUser = $sessionUser;

        return $ctx2;
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
