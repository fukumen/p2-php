<?php

/**
 * rep2 - ログイン認証を扱うクラス
 *
 * 認証フローは collect (読むだけ) / verify (判定だけ) / establish (書くだけ) の
 * 3フェーズで構成し、判定はリクエストの意図 (intent) ごとの排他経路で行う。
 * collect と verify は Phase 1 (共通ゲート) と Phase 2 (intent 別) の2段階に分かれる。
 */

require_once P2_LIB_DIR . '/LoginCookie.php';
require_once P2_LIB_DIR . '/LoginSession.php';
require_once P2_LIB_DIR . '/LoginCtx.php';
require_once P2_LIB_DIR . '/LoginProof.php';
require_once P2_LIB_DIR . '/LoginResult.php';

// {{{ Login

class Login
{
    // {{{ properties

    public $user_u; // ユーザ名（ユーザと直接触れる部分）
    public $pass_x; // 暗号化されたパスワード

    private $hash;
    private $cookie;

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->hash = P2Hash::getInstance();
        $this->cookie = new LoginCookie();
    }

    // }}}
    // {{{ authorize()

    /**
     * 認証を行う
     *
     * @return bool 認証が確立した場合 true (リダイレクトを伴う場合は戻らない)
     */
    public function authorize()
    {
        $ctx = LoginCtx::collect($this);
        $result = LoginProof::checkGate($ctx);
        if ($result === null) {
            switch ($ctx->intent) {
                case LoginCtx::INTENT_FORM:
                    $result = LoginProof::verifyForm($ctx, LoginFormCtx::collect($this, $ctx));
                    break;
                case LoginCtx::INTENT_FORM_NEW:
                    $result = LoginProof::verifyFormNew($ctx, LoginRegistCtx::collect($this, $ctx));
                    break;
                case LoginCtx::INTENT_STATE:
                    $result = LoginProof::verifyState($ctx, LoginStateCtx::collect($this, $ctx));
                    break;
                default:
                    $result = LoginProof::verifyNone($ctx);
            }
        }

        if ($result->isPass()) {
            $this->establish($result);
            return true;
        }

        $this->applyFail($result);
    }

    // }}}
    // {{{ establish()

    /**
     * 認証結果に従って実行する
     *
     * @param LoginResult $result
     * @return void (logout・リダイレクト時は exit)
     */
    private function establish(LoginResult $result)
    {
        global $_p2session;

        // effects は LoginProof が実行順序込みで構築した実行計画。
        // case の並び順は LoginProof が構築する順序と同じであり、計画と実行が1対1で対応する
        foreach ($result->effects as $effect) {
            switch ($effect) {
                case LoginResult::E_REFLECT_LOGIN:
                    $this->user_u = $result->identity;
                    $this->pass_x = $result->passX;
                    break;
                case LoginResult::E_MAKE_USER:
                    $this->makeUser($result->identity, $result->formLoginPass);
                    break;
                case LoginResult::E_UNSESSION:
                    LoginSession::unSession();
                    break;
                case LoginResult::E_CLEAR_COOKIE:
                    $this->cookie->clearCookieAuth();
                    break;
                case LoginResult::E_LOG_SUCCESS:
                    $this->logLoginSuccess();
                    break;
                case LoginResult::E_BEGIN_SESSION:
                    $_p2session->begin();
                    break;
                case LoginResult::E_UPDATE_ACTIME:
                    $_p2session->updateActime();
                    break;
                case LoginResult::E_UPDATE_SESSION_VARS:
                    $_SESSION['login_user']   = $this->user_u;
                    $_SESSION['login_pass_x'] = P2Hash::hash($this->pass_x);
                    break;
                case LoginResult::E_RECORD_LOGIN_MICROTIME:
                    $_SESSION['login_microtime'] = microtime();
                    break;
                case LoginResult::E_RECORD_DEVICE_PIXEL_RATIO:
                    $_SESSION['device_pixel_ratio'] = $result->devicePixelRatio;
                    break;
                case LoginResult::E_REGISTER_COOKIE:
                    $this->registerCookie();
                    break;
                case LoginResult::E_CLOSE_SESSION:
                    session_write_close();
                    break;
                case LoginResult::E_LOGOUT_REDIRECT:
                    header('Location: ' . rtrim(dirname(P2Util::getMyUrl()), '/') . '/');
                    exit;
                case LoginResult::E_REDIRECT:
                    header('Location: ' . $result->redirectTo);
                    exit;
                case LoginResult::E_SHOW_REGIST_DONE:
                    $this->showRegistDone();
            }
        }
    }

    // }}}
    // {{{ applyFail()

    /**
     * 検証失敗
     *
     * @param LoginResult $result
     * @return void (printLoginFirst + exit のため戻らない)
     */
    private function applyFail(LoginResult $result)
    {
        // effects は LoginProof が実行順序込みで構築した実行計画。
        // case の並び順は LoginProof が構築する順序と同じであり、計画と実行が1対1で対応する
        foreach ($result->effects as $effect) {
            switch ($effect) {
                case LoginResult::E_UNSESSION:
                    LoginSession::unSession();
                    break;
                case LoginResult::E_CLEAR_COOKIE:
                    $this->cookie->clearCookieAuth();
                    break;
                case LoginResult::E_LOG_FAILED:
                    $this->logLoginFailed();
                    break;
            }
        }

        switch ($result->reason) {
            case LoginResult::R_NO_IDENTITY:
            case LoginResult::R_NO_PROOF:
                // メッセージなし
                break;

            case LoginResult::R_NEW_REGIST_INPUT_ERROR:
            case LoginResult::R_NEW_REGIST_PATTERN_ERROR:
                // メッセージは login_first 側が表示する
                break;

            case LoginResult::R_IDENTITY_MISMATCH:
                P2Util::pushInfoHtml('<p>p2 error: ログインエラー</p>');
                break;

            case LoginResult::R_NO_PASSWORD:
                P2Util::pushInfoHtml('<p>p2 error: ログインエラー</p>');
                break;

            case LoginResult::R_SESSION_ERROR:
                P2Util::pushInfoHtml('<p>p2 error: ' . p2h($result->extra) . '</p>');
                break;

            case LoginResult::R_LOCKOUT:
                P2Util::pushInfoHtml('<p>p2 info: ログイン試行回数が多すぎます。<br>ﾀｲ━━━━||Φ|(|ﾟ|∀|ﾟ|)|Φ||━━━━ﾎ!!</p>');
                break;

            case LoginResult::R_FORM_REJECTED:
                P2Util::pushInfoHtml('<p>p2 info: ログインできませんでした。<br>ユーザ名かパスワードが違います。</p>');
                break;
        }

        require_once P2_LIB_DIR . '/login_first.inc.php';
        printLoginFirst($this, $result->reason);
        exit;
    }

    // }}}
    // {{{ showRegistDone()

    /**
     * 新規登録完了を表示する
     */
    private function showRegistDone()
    {
        require_once P2_LIB_DIR . '/login_first.inc.php';
        printLoginFirst($this);
        exit;
    }

    // }}}
    // {{{ getHash()

    /**
     * P2Hash を取得
     *
     * @return P2Hash
     */
    public function getHash()
    {
        return $this->hash;
    }

    // }}}
    // {{{ getCookie()

    /**
     * LoginCookie を取得
     *
     * @return LoginCookie
     */
    public function getCookie()
    {
        return $this->cookie;
    }

    // }}}
    // {{{ registerCookie()

    /**
     * クッキー認証を登録する (cid 発行)
     *
     * rep2/cookie.php の専用操作 (クッキー認証開始) 用
     *
     * @return boolean
     */
    public function registerCookie()
    {
        $device_pixel_ratio = $_SESSION['device_pixel_ratio'] ?? null;
        return $this->cookie->setCookieCid($this->user_u, $this->pass_x, $device_pixel_ratio);
    }

    // }}}
    // {{{ clearCookie()

    /**
     * クッキー認証を解除する (cid 掃除)
     *
     * rep2/cookie.php の専用操作 (クッキー認証解除) 用
     *
     * @return void
     */
    public function clearCookie()
    {
        global $_p2session;

        $this->cookie->clearCookieAuth();
        $_p2session->begin();
    }

    // }}}
    // {{{ normalizeDevicePixelRatio()

    /**
     * devicePixelRatio を正規化する (1.5 / 2.0 のみ有効)
     *
     * @param mixed $device_pixel_ratio
     * @return float|null 正規化できなければ null
     */
    public function normalizeDevicePixelRatio($device_pixel_ratio)
    {
        $dpr = floatval($device_pixel_ratio);
        if ($dpr === 1.5 || $dpr === 2.0) {
            return $dpr;
        }

        return null;
    }

    // }}}
    // {{{ checkAuthUserFile()

    /**
     * 認証ユーザ設定のファイルを調べて、無効なデータなら捨ててしまう
     */
    public function checkAuthUserFile()
    {
        global $_conf;

        if (@include($_conf['auth_user_file'])) {
            // ユーザ情報がなかったら、ファイルを捨てて抜ける
            if (empty($rec_login_user_u) || empty($rec_login_pass_x)) {
                unlink($_conf['auth_user_file']);
            }
        }

        return true;
    }

    // }}}
    // {{{ checkUserPwWithCid()

    /**
     * Cookie情報（CID）とuser, passを照合する
     *
     * @return boolean
     */
    public function checkUserPwWithCid()
    {
        return $this->cookie->checkUserPwWithCid($this->user_u, $this->pass_x);
    }

    // }}}
    // {{{ updateAuthUser()

    /**
     * ユーザ情報を更新する
     *
     * @param string $user_u ユーザ名
     * @param string $pass   平文パスワード
     * @return bool
     */
    public function updateAuthUser($user_u, $pass)
    {
        global $_conf;

        $login_user = strval($user_u);
        $hashed_login_pass = $this->hash->password_hash($pass);
        $login_user_repr = var_export($login_user, true);
        $login_pass_repr = var_export($hashed_login_pass, true);
        $auth_user_cont = <<<EOP
<?php
\$rec_login_user_u = {$login_user_repr};
\$rec_login_pass_x = {$login_pass_repr};\n
EOP;

        $temp_file = $_conf['auth_user_file'] . '.tmp';
        if (FileCtl::file_write_contents($temp_file, $auth_user_cont) === false) {
            return false;
        }
        @chmod($temp_file, 0600);

        if (!rename($temp_file, $_conf['auth_user_file'])) {
            @unlink($temp_file);
            return false;
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($_conf['auth_user_file'], true);
        }
        $this->user_u = $user_u;
        $this->pass_x = $hashed_login_pass;
        return true;
    }

    // }}}
    // {{{ makeUser()

    /**
     * 新規ユーザを作成する
     *
     * @param string $user_u ユーザ名
     * @param string $pass   平文パスワード
     * @return bool
     */
    private function makeUser($user_u, $pass)
    {
        global $_conf;

        if (!$this->updateAuthUser($user_u, $pass)) {
            p2die("{$_conf['auth_user_file']} を保存できませんでした。認証ユーザ登録失敗。");
        }

        return true;
    }

    // }}}
    // {{{ checkLockout()

    /**
     * ロックアウト判定を行う
     *
     * @return bool ロックアウトされている場合はtrue
     */
    public function checkLockout()
    {
        global $_conf;

        require_once P2_CONFIG_DIR . '/conf_lockout.inc.php';

        if ($_conf['login_attempts'] == 0) {
            return false;
        }

        $client_ip = $_conf['whip_clientip'];
        $limit_time = time() - ($_conf['login_lockout_time']);

        $success_logs = P2Util::readAllAccessLog($_conf['login_log_file']);
        $failed_logs = P2Util::readAllAccessLog($_conf['login_failed_log_file']);

        $events = array();
        foreach ($success_logs as $log) {
            if ($log['ip'] === $client_ip) {
                $events[] = array('time' => strtotime(preg_replace('/\s\([^)]+\)\s/', ' ', $log['date'])), 'type' => 'success');
            }
        }
        foreach ($failed_logs as $log) {
            if ($log['ip'] === $client_ip) {
                $events[] = array('time' => strtotime(preg_replace('/\s\([^)]+\)\s/', ' ', $log['date'])), 'type' => 'failure');
            }
        }

        usort($events, function($a, $b) {
            return $b['time'] - $a['time'];
        });

        $fail_count = 0;
        foreach ($events as $event) {
            if ($event['time'] < $limit_time) {
                break;
            }
            if ($event['type'] === 'success') {
                break;
            } else {
                $fail_count++;
            }
        }

        return $fail_count >= $_conf['login_attempts'];
    }

    // }}}
    // {{{ logLoginSuccess()

    /**
     * ログインログを記録する
     */
    private function logLoginSuccess()
    {
        global $_conf;

        if (!empty($_conf['login_log_rec'])) {
            $recnum = $_conf['login_log_rec_num'] ?? 100;
            P2Util::recAccessLog($_conf['login_log_file'], $recnum);
        }

        return true;
    }

    // }}}
    // {{{ logLoginFailed()

    /**
     * ログイン失敗ログを記録する
     */
    private function logLoginFailed()
    {
        global $_conf;

        if (!empty($_conf['login_log_rec'])) {
            $recnum = $_conf['login_log_rec_num'] ?? 100;
            P2Util::recAccessLog($_conf['login_failed_log_file'], $recnum);
        }

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
