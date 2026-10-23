<?php
/**
 * rep2 - パスキー (WebAuthn) クレデンシャルの保存と管理
 */

// {{{ P2PasskeyStore

/**
 * パスキーのクレデンシャルを p2_passkeys.srd (P2Encryptor により暗号化した serialize形式) に保存する
 *
 * 「ファイルが存在する = 登録済みパスキーが1個以上」を不変条件とする。
 * パスキーが0個になるとファイルは削除されるため、
 * getEffectiveMode() は存在確認だけで専用モードの実効化を判定できる
 */
class P2PasskeyStore
{
    private static $instance;

    /** @var array 読み込み済みのクレデンシャル */
    private $creds;

    /** @var P2Encryptor クレデンシャルの暗号化・復号 */
    private $encryptor;

    // {{{ getInstance()

    /**
     * 唯一のインスタンスを返す
     *
     * @return P2PasskeyStore
     */
    static public function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // }}}
    // {{{ __construct()

    /**
     * 保存ファイルからクレデンシャルを読み込む
     */
    private function __construct()
    {
        global $_conf;

        $this->encryptor = P2Encryptor::getInstance();
        $this->creds = array();

        $file = $_conf['passkeys_file'];
        if (!file_exists($file)) {
            return;
        }

        $cont = FileCtl::file_read_contents($file);
        $dec = ($cont !== false && $cont !== '') ? $this->encryptor->decrypt($cont) : null;
        $data = ($dec !== null) ? @unserialize($dec) : null;
        if (is_array($data) && count($data) > 0) {
            $this->creds = $data;
        } else {
            p2die('パスキーのクレデンシャルファイルが読み込めなかったか、壊れています。');
        }
    }

    // }}}
    // {{{ getEffectiveMode()

    /**
     * 実効的なパスキー認証モードを返す
     *
     * 専用モード (2) は登録済みパスキーが1個以上あるときにのみ維持できる。
     * パスキーが0個のとき (回復手順で p2_passkeys.srd を削除した場合等) は
     * 無効 (0) 扱いとしてパスワードログインを復活させる。
     *
     * @return int 0|1|2
     */
    static public function getEffectiveMode()
    {
        global $_conf;

        $mode = $_conf['passkey_auth_mode'];
        if ($mode == 2 && !file_exists($_conf['passkeys_file'])) {
            return 0;
        }
        return $mode;
    }

    // }}}
    // {{{ findAll()

    /**
     * 登録済みの全クレデンシャルを返す
     *
     * @return array
     */
    public function findAll()
    {
        return $this->creds;
    }

    // }}}
    // {{{ findByCredentialId()

    /**
     * credentialId (base64url) からクレデンシャルを検索する
     *
     * @param string $credentialId base64url
     * @return array|false
     */
    public function findByCredentialId($credentialId)
    {
        foreach ($this->creds as $cred) {
            if (hash_equals($cred['credentialId'], $credentialId)) {
                return $cred;
            }
        }
        return false;
    }

    // }}}
    // {{{ add()

    /**
     * クレデンシャルを追加する
     *
     * @param string $credentialId base64url
     * @param string $publicKey PEM形式の公開鍵
     * @param int|null $signCount 登録時の attestation の値 (null は未観測)
     * @param string $name 登録名
     * @param string $userHandle base64url
     * @return bool 成功すればtrue
     */
    public function add($credentialId, $publicKey, $signCount, $name, $userHandle)
    {
        $creds = $this->creds;
        $creds[] = array(
            'credentialId' => $credentialId,
            'publicKey' => $publicKey,
            'signCount' => $signCount,
            'name' => $name,
            'userHandle' => $userHandle,
            'registeredAt' => time(),
            'lastUsedAt' => null,
        );
        if (!$this->save($creds)) {
            return false;
        }
        $this->creds = $creds;
        return true;
    }

    // }}}
    // {{{ update()

    /**
     * credentialId (base64url) で指定したクレデンシャルのフィールドを更新する
     *
     * @param string $credentialId base64url
     * @param array $fields
     * @return bool
     */
    public function update($credentialId, $fields)
    {
        foreach ($this->creds as $i => $cred) {
            if (hash_equals($cred['credentialId'], $credentialId)) {
                $creds = $this->creds;
                foreach ($fields as $k => $v) {
                    $creds[$i][$k] = $v;
                }
                if (!$this->save($creds)) {
                    return false;
                }
                $this->creds = $creds;
                return true;
            }
        }
        return false;
    }

    // }}}
    // {{{ remove()

    /**
     * credentialId (base64url) で指定したクレデンシャルを削除する
     *
     * @param string $credentialId base64url
     * @return bool 成功すればtrue、失敗でfalse
     */
    public function remove($credentialId)
    {
        foreach ($this->creds as $i => $cred) {
            if (hash_equals($cred['credentialId'], $credentialId)) {
                $creds = $this->creds;
                unset($creds[$i]);
                $creds = array_values($creds);
                if (!$this->save($creds)) {
                    return false;
                }
                $this->creds = $creds;
                return true;
            }
        }
        return false;
    }

    // }}}
    // {{{ save()

    /**
     * クレデンシャルを保存する
     *
     * 0個になった場合はファイルを削除して「ファイルが存在する = 1個以上」が維持されるようにする
     *
     * @param array $creds 保存するクレデンシャルの配列
     * @return bool
     */
    private function save(array $creds)
    {
        global $_conf;

        $file = $_conf['passkeys_file'];

        if (count($creds) == 0) {
            if (is_file($file) && !@unlink($file)) {
                return false;
            }
            return true;
        }

        $cont = serialize($creds);
        $enc = $this->encryptor->encrypt($cont);
        if ($enc === null) {
            return false;
        }

        $temp_file = $file . '.tmp';
        if (FileCtl::file_write_contents($temp_file, $enc) === false) {
            return false;
        }
        @chmod($temp_file, 0600);

        if (!rename($temp_file, $file)) {
            @unlink($temp_file);
            return false;
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
