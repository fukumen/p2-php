<?php
/**
 * rep2 - パスキー認証例外
 */

// {{{ P2PasskeyAuthException

/**
 * P2PasskeyAuth の検証が失敗したことを表す。
 * 登録検証の失敗では、signalUnknownCredentialの送信対象のcredentialIdを持つことがある
 */
class P2PasskeyAuthException extends RuntimeException
{
    /** @var string|null signalUnknownCredential送信対象のcredentialId (base64url) */
    private $credential_id;

    // {{{ __construct()

    /**
     * @param string $message
     * @param string|null $credential_id signalUnknownCredentialの送信対象。
     *        検証に失敗して登録が行われず、かつストアに存在しないcredentialにのみ添付する
     */
    public function __construct($message = '', $credential_id = null)
    {
        parent::__construct($message);
        $this->credential_id = $credential_id;
    }

    // }}}
    // {{{ getCredentialId()

    /**
     * signalUnknownCredentialの送信対象のcredentialIdを返す
     *
     * @return string|null 送信対象が無い場合はnull
     */
    public function getCredentialId()
    {
        return $this->credential_id;
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
