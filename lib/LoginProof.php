<?php

/**
 * rep2 - 認証の検証 (verify)
 *
 * INTENT × PASS 着地のマトリクス (○ = 常に設定、△ = 条件付きで設定、ー = 設定されない)
 * | effect                    | FORM PASS | FORM_NEW PASS | PASSKEY PASS | STATE PASS① | STATE PASS② | STATE PASS③ | STATE PASS④ | STATE PASS⑤ |
 * | ------------------------- | --------- | ------------- | ------------ | ------------ | ------------ | ------------ | ------------ | ------------ |
 * | reflect_login             | ○        | ー            | ○           | ○           | ○           | ○           | ○           | ○           |
 * | make_user                 | ー        | ○            | ー           | ー           | ー           | ー           | ー           | ー           |
 * | clear_strengthened        | ー        | ー            | ー           | ー           | ○           | ー           | ー           | ー           |
 * | unsession                 | ー        | ー            | ー           | ー           | ー           | ー           | ー           | ○           |
 * | clear_cookie              | ー        | ー            | ー           | ー           | ー           | △ (※1)     | ー           | ○           |
 * | log_success               | ○        | ○            | ○           | ー           | ー           | ー           | ー           | ー           |
 * | begin_session             | △ (※2)  | △ (※2)      | ○           | ー           | ー           | ー           | ー           | ー           |
 * | update_actime             | ー        | ー            | ー           | ー           | ー           | ○           | ○           | ー           |
 * | update_session_vars       | ○        | ○            | ー           | ○           | ○           | ○           | ー           | ー           |
 * | update_session_passkey    | ー        | ー            | ○           | ー           | ー           | ー           | ○           | ー           |
 * | record_login_microtime    | ○        | ○            | ○           | ○           | ー           | ー           | ー           | ー           |
 * | record_device_pixel_ratio | △ (※3)  | △ (※3)      | △ (※3)     | △ (※4)     | △ (※4)     | ー           | ー           | ー           |
 * | register_cookie           | △ (※5)  | △ (※5)      | ー           | ー           | ー           | ー           | ー           | ー           |
 * | close_session             | △ (※6)  | △ (※6)      | △ (※6)     | △ (※6)     | △ (※6)     | △ (※6)     | △ (※6)     | ー           |
 * | logout_redirect           | ー        | ー            | ー           | ー           | ー           | ー           | ー           | ○           |
 * | redirect                  | △ (※7)  | ー            | △ (※7)     | ー           | ー           | ー           | ー           | ー           |
 * | show_regist_done          | ー        | ○            | ー           | ー           | ー           | ー           | ー           | ー           |
 * | log_failed                | ー        | ー            | ー           | ー           | ー           | ー           | ー           | ー           |
 *
 * STATE PASS① CID認証OK/通常セッション認証NG
 * STATE PASS② CID認証OK/通常セッション認証OK
 * STATE PASS③ 強化セッション認証OK
 * STATE PASS④ パスキーセッション認証OK
 * STATE PASS⑤ logout時
 * ※1 CID有り&&認証NG時
 * ※2 クッキー認証無し時
 * ※3 REQUEST dpr 有り時
 * ※4 CID dpr有り時
 * ※5 クッキー認証有り時
 * ※6 P2_SESSION_CLOSE_AFTER_AUTHENTICATION定義時
 * ※7 REQUEST_URI 有り時
 */

// {{{ LoginProof

class LoginProof
{
    // {{{ checkGate()

    /**
     * 共通ゲートを判定する
     *
     * @param LoginCtx $ctx
     * @return LoginResult|null 失敗なら FAIL、通過なら null
     */
    static public function checkGate(LoginCtx $ctx)
    {
        if ($ctx->intent === LoginCtx::INTENT_FORM_NEW) {
            return null;
        }

        if ($ctx->intent === LoginCtx::INTENT_PASSKEY) {
            if ($ctx->recUser === null) {
                return LoginResult::fail(LoginResult::R_IDENTITY_MISMATCH);
            }
            return null;
        }

        if ($ctx->identity === null) {
            if ($ctx->passkeyOnly) {
                $result = LoginResult::fail(LoginResult::R_PASSKEY_REQUIRED);
            } else {
                $result = LoginResult::fail(LoginResult::R_NO_IDENTITY);
            }
            if ($ctx->logout) {
                $result->effects[] = LoginResult::E_LOGOUT_REDIRECT;
            }
            return $result;
        }

        if ($ctx->authFileExists && $ctx->identity != $ctx->recUser) {
            return LoginResult::failLog(LoginResult::R_IDENTITY_MISMATCH, $ctx->identity);
        }

        if (!$ctx->passX) {
            return LoginResult::fail(LoginResult::R_NO_PASSWORD);
        }

        return null;
    }

    // }}}
    // {{{ verifyForm()

    /**
     * form 経路を判定する
     *
     * @param LoginCtx $base
     * @param LoginFormCtx $ctx
     * @return LoginResult
     */
    static public function verifyForm(LoginCtx $base, LoginFormCtx $ctx)
    {
        if ($ctx->lockout) {
            return LoginResult::fail(LoginResult::R_LOCKOUT);
        }

        if (!$ctx->formVerified) {
            return LoginResult::failLog(LoginResult::R_FORM_REJECTED, $base->identity);
        }

        $r = LoginResult::pass($base->identity, $base->passX, $ctx->devicePixelRatio, $ctx->requestUri);
        $r->effects[] = LoginResult::E_REFLECT_LOGIN;
        $r->effects[] = LoginResult::E_LOG_SUCCESS;
        if (!$ctx->registerCid) {
            $r->effects[] = LoginResult::E_BEGIN_SESSION;
        }
        $r->effects[] = LoginResult::E_UPDATE_SESSION_VARS;
        $r->effects[] = LoginResult::E_RECORD_LOGIN_MICROTIME;
        if ($ctx->devicePixelRatio !== null) {
            $r->effects[] = LoginResult::E_RECORD_DEVICE_PIXEL_RATIO;
        }
        if ($ctx->registerCid) {
            $r->effects[] = LoginResult::E_REGISTER_COOKIE;
        }
        if (P2_SESSION_CLOSE_AFTER_AUTHENTICATION) {
            $r->effects[] = LoginResult::E_CLOSE_SESSION;
        }
        if ($ctx->requestUri !== null) {
            $r->effects[] = LoginResult::E_REDIRECT;
        }
        return $r;
    }

    // }}}
    // {{{ verifyFormNew()

    /**
     * 新規登録経路を判定する
     *
     * @param LoginCtx $base
     * @param LoginRegistCtx $ctx
     * @return LoginResult
     */
    static public function verifyFormNew(LoginCtx $base, LoginRegistCtx $ctx)
    {
        // passX があるときは新規登録できない (submit_new ボタンは auth_user_file 未存在時のみ表示)
        if ($base->passX !== null) {
            return LoginResult::fail(LoginResult::R_NO_PROOF);
        }

        // form_login_id / form_login_pass の両キーが POST にないときは
        // メッセージなしでログイン画面を表示する (通常のフォームは空値でも送信するため通常は到達しない)
        if ($ctx->formLoginId === null && $ctx->formLoginPass === null) {
            return LoginResult::fail(LoginResult::R_NO_PROOF);
        }

        if (!$ctx->formLoginId || !$ctx->formLoginPass) {
            return LoginResult::fail(LoginResult::R_NEW_REGIST_INPUT_ERROR);
        }

        if (!preg_match('/^[0-9A-Za-z_]+$/', $ctx->formLoginId) || !preg_match('/^[\\x21-\\x7E]+$/', $ctx->formLoginPass)) {
            return LoginResult::fail(LoginResult::R_NEW_REGIST_PATTERN_ERROR);
        }

        $r = LoginResult::passRegist($ctx->formLoginId, $ctx->formLoginPass, $ctx->devicePixelRatio);
        $r->effects[] = LoginResult::E_MAKE_USER;
        $r->effects[] = LoginResult::E_LOG_SUCCESS;
        if (!$ctx->registerCid) {
            $r->effects[] = LoginResult::E_BEGIN_SESSION;
        }
        $r->effects[] = LoginResult::E_UPDATE_SESSION_VARS;
        $r->effects[] = LoginResult::E_RECORD_LOGIN_MICROTIME;
        if ($ctx->devicePixelRatio !== null) {
            $r->effects[] = LoginResult::E_RECORD_DEVICE_PIXEL_RATIO;
        }
        if ($ctx->registerCid) {
            $r->effects[] = LoginResult::E_REGISTER_COOKIE;
        }
        if (P2_SESSION_CLOSE_AFTER_AUTHENTICATION) {
            $r->effects[] = LoginResult::E_CLOSE_SESSION;
        }
        $r->effects[] = LoginResult::E_SHOW_REGIST_DONE;
        return $r;
    }

    // }}}
    // {{{ verifyPasskey()

    /**
     * passkey 経路を判定する
     *
     * @param LoginCtx $base
     * @param LoginPasskeyCtx $ctx
     * @return LoginResult
     */
    static public function verifyPasskey(LoginCtx $base, LoginPasskeyCtx $ctx)
    {
        if ($ctx->lockout) {
            return LoginResult::fail(LoginResult::R_LOCKOUT);
        }

        if (!$ctx->passkeyVerified) {
            // 検証失敗のため identity は確定しない (未検証の credentialId からユーザーを逆引きして記録しない)
            return LoginResult::failLog(LoginResult::R_PASSKEY_REJECTED, '[passkey]', $ctx->failMessage);
        }

        $r = LoginResult::pass($base->recUser, null, $ctx->devicePixelRatio, $ctx->requestUri);
        $r->effects[] = LoginResult::E_REFLECT_LOGIN;
        $r->effects[] = LoginResult::E_LOG_SUCCESS;
        $r->effects[] = LoginResult::E_BEGIN_SESSION;
        $r->effects[] = LoginResult::E_UPDATE_SESSION_PASSKEY;
        $r->effects[] = LoginResult::E_RECORD_LOGIN_MICROTIME;
        if ($ctx->devicePixelRatio !== null) {
            $r->effects[] = LoginResult::E_RECORD_DEVICE_PIXEL_RATIO;
        }
        if (P2_SESSION_CLOSE_AFTER_AUTHENTICATION) {
            $r->effects[] = LoginResult::E_CLOSE_SESSION;
        }
        if ($ctx->requestUri !== null) {
            $r->effects[] = LoginResult::E_REDIRECT;
        }
        return $r;
    }

    // }}}
    // {{{ verifyState()

    /**
     * state 経路を判定する
     *
     * @param LoginCtx $base
     * @param LoginStateCtx $ctx
     * @return LoginResult
     */
    static public function verifyState(LoginCtx $base, LoginStateCtx $ctx)
    {
        if ($base->cidCookiePresent) {
            if ($ctx->cidVerified) {
                if ($base->logout) {
                    $r = LoginResult::pass($base->identity, $base->passX);
                    $r->effects[] = LoginResult::E_REFLECT_LOGIN;
                    $r->effects[] = LoginResult::E_UNSESSION;
                    $r->effects[] = LoginResult::E_CLEAR_COOKIE;
                    $r->effects[] = LoginResult::E_LOGOUT_REDIRECT;
                    return $r;
                }
                $r = LoginResult::pass($base->identity, $base->passX, $ctx->devicePixelRatio);
                $r->effects[] = LoginResult::E_REFLECT_LOGIN;
                if ($ctx->sessionUser !== null) {
                    $r->effects[] = LoginResult::E_CLEAR_STRENGTHENED;
                }
                $r->effects[] = LoginResult::E_UPDATE_SESSION_VARS;
                if ($ctx->sessionUser === null) {
                    $r->effects[] = LoginResult::E_RECORD_LOGIN_MICROTIME;
                }
                if ($ctx->devicePixelRatio !== null) {
                    $r->effects[] = LoginResult::E_RECORD_DEVICE_PIXEL_RATIO;
                }
                if (P2_SESSION_CLOSE_AFTER_AUTHENTICATION) {
                    $r->effects[] = LoginResult::E_CLOSE_SESSION;
                }
                return $r;
            }
        }

        if ($ctx->sessionChecked) {
            if ($ctx->sessionError !== false) {
                $extra = $base->cidExpired
                    ? 'クッキー認証の有効期限が切れました。再度ログインしてください。'
                    : $ctx->sessionError;
                if ($base->passkeyOnly) {
                    $r = LoginResult::fail(LoginResult::R_PASSKEY_REQUIRED, $extra);
                } else {
                    $r = LoginResult::fail(LoginResult::R_SESSION_ERROR, $extra);
                }
                $r->effects[] = LoginResult::E_CLEAR_COOKIE;
                if ($base->logout) {
                    $r->effects[] = LoginResult::E_LOGOUT_REDIRECT;
                }
                return $r;
            }

            if ($ctx->passkeyAuth) {
                if ($base->logout) {
                    $r = LoginResult::pass($base->identity, null);
                    $r->effects[] = LoginResult::E_REFLECT_LOGIN;
                    $r->effects[] = LoginResult::E_UNSESSION;
                    $r->effects[] = LoginResult::E_CLEAR_COOKIE;
                    $r->effects[] = LoginResult::E_LOGOUT_REDIRECT;
                    return $r;
                }
                $r = LoginResult::pass($base->identity, null);
                $r->effects[] = LoginResult::E_REFLECT_LOGIN;
                $r->effects[] = LoginResult::E_UPDATE_ACTIME;
                $r->effects[] = LoginResult::E_UPDATE_SESSION_PASSKEY;
                if (P2_SESSION_CLOSE_AFTER_AUTHENTICATION) {
                    $r->effects[] = LoginResult::E_CLOSE_SESSION;
                }
                return $r;
            }

            if ($ctx->sessionVerified) {
                if ($base->logout) {
                    $r = LoginResult::pass($base->identity, $base->passX);
                    $r->effects[] = LoginResult::E_REFLECT_LOGIN;
                    $r->effects[] = LoginResult::E_UNSESSION;
                    $r->effects[] = LoginResult::E_CLEAR_COOKIE;
                    $r->effects[] = LoginResult::E_LOGOUT_REDIRECT;
                    return $r;
                }
                $r = LoginResult::pass($base->identity, $base->passX);
                $r->effects[] = LoginResult::E_REFLECT_LOGIN;
                $r->effects[] = LoginResult::E_UPDATE_ACTIME;
                $r->effects[] = LoginResult::E_UPDATE_SESSION_VARS;
                if (P2_SESSION_CLOSE_AFTER_AUTHENTICATION) {
                    $r->effects[] = LoginResult::E_CLOSE_SESSION;
                }
                return $r;
            }

            // セッションの情報が古いため破棄する
            $r = LoginResult::fail(LoginResult::R_NO_PROOF);
            $r->effects[] = LoginResult::E_CLEAR_COOKIE;
            return $r;
        }

        $r = LoginResult::fail(LoginResult::R_NO_PROOF);
        $r->effects[] = LoginResult::E_CLEAR_COOKIE;
        return $r;
    }

    // }}}
    // {{{ verifyNone()

    /**
     * none 経路を判定する
     *
     * @param LoginCtx $ctx
     * @return LoginResult
     */
    static public function verifyNone(LoginCtx $ctx)
    {
        return LoginResult::fail(LoginResult::R_NO_PROOF);
    }

    // }}}
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
