<?php
/**
 * rep2 - パスキー認証クラス
 *
 * report-uri/passkeys-php を使用
 */

use ReportUri\Passkeys\WebAuthn;
use ReportUri\Passkeys\WebAuthnException;

// {{{ P2PasskeyAuth

class P2PasskeyAuth
{
    const COOKIE_NAME = 'PCC';  // Passkey Challenge Cookie

    // {{{ findAllCredentials()

    /**
     * 登録済みの全クレデンシャルの表示データを返す
     *
     * @return array
     */
    static public function findAllCredentials()
    {
        $list = array();
        foreach (P2PasskeyStore::getInstance()->findAll() as $cred) {
            $list[] = array(
                'credentialId' => $cred['credentialId'],
                'name' => $cred['name'],
                'registeredAt' => $cred['registeredAt'],
                'lastUsedAt' => $cred['lastUsedAt'],
            );
        }
        return $list;
    }

    // }}}
    // {{{ deleteCredential()

    /**
     * クレデンシャルを削除する
     *
     * @param string $credentialId base64url
     * @return void
     * @throws P2PasskeyAuthException 削除に失敗した場合
     */
    static public function deleteCredential($credentialId)
    {
        $store = P2PasskeyStore::getInstance();
        if ($store->findByCredentialId($credentialId) === false) {
            throw new P2PasskeyAuthException('該当するパスキーが見つかりません');
        }
        if (!$store->remove($credentialId)) {
            throw new P2PasskeyAuthException('パスキーを削除できませんでした');
        }
    }

    // }}}
    // {{{ renameCredential()

    /**
     * クレデンシャルの登録名を変更する
     *
     * @param string $credentialId base64url
     * @param string $name 新しい登録名
     * @return void
     * @throws P2PasskeyAuthException 変更に失敗した場合
     */
    static public function renameCredential($credentialId, $name)
    {
        $store = P2PasskeyStore::getInstance();
        if ($store->findByCredentialId($credentialId) === false) {
            throw new P2PasskeyAuthException('該当するパスキーが見つかりません');
        }
        if (!$store->update($credentialId, array('name' => $name))) {
            throw new P2PasskeyAuthException('パスキー名を変更できませんでした');
        }
    }

    // }}}
    // {{{ issueLoginOptions()

    /**
     * ログイン用のoptionsスクリプトを発行する
     *
     * challengeをchallengeストアに保存し、passkey.js用のoptionsを組み込んだ<script>要素を返す。
     *
     * @return string 発行したoptionsスクリプト
     * @throws P2PasskeyAuthException 発行に失敗した場合
     */
    static public function issueLoginOptions()
    {
        $webauthn = self::createWebAuthn();

        // 登録済み0個のときはallowCredentialsを省略し、認証器に選択させる (getGetArgsの仕様)
        $args = $webauthn->getGetArgs(
            self::getRawCredentialIds(),
            requireUserVerification: true
        );

        self::challengeSet(array('challenge' => UrlSafeBase64::encode($webauthn->getChallenge()->getBinaryString())));

        return self::buildOptionsScript($args->publicKey);
    }

    // }}}
    // {{{ issueCreateOptions()

    /**
     * 登録用のoptionsスクリプトを発行する
     *
     * challengeとuserHandleをchallengeストアに保存し、passkey.js用のoptionsを組み込んだ<script>要素を返す。
     *
     * @return string 発行したoptionsスクリプト
     * @throws P2PasskeyAuthException 発行に失敗した場合
     */
    static public function issueCreateOptions()
    {
        global $_login;

        $webauthn = self::createWebAuthn();
        $user_handle = UrlSafeBase64::encode(random_bytes(32));

        $args = $webauthn->getCreateArgs(
            UrlSafeBase64::decode($user_handle),    // user.id (rawバイナリ)
            $_login->user_u,
            $_login->user_u,
            requireResidentKey: 'preferred',
            requireUserVerification: true,
            excludeCredentialIds: self::getRawCredentialIds()
        );

        self::challengeSet(array(
            'challenge' => UrlSafeBase64::encode($webauthn->getChallenge()->getBinaryString()),
            'userHandle' => $user_handle,
        ));

        return self::buildOptionsScript($args->publicKey);
    }

    // }}}
    // {{{ buildSignalScript()

    /**
     * signalUnknownCredentialの実行を再描画ページに埋め込む<script>要素を組み立てる
     *
     * @param string $credentialId base64url
     * @return string <script>要素
     * @throws P2PasskeyAuthException ホスト名を解決できない場合
     */
    static public function buildSignalScript($credentialId)
    {
        $rp_id_ht = p2h(self::getRpId());
        $id_ht = p2h(strval($credentialId));

        return <<<EOP
<script type="text/javascript">
//<![CDATA[
passkeySignalUnknown('{$rp_id_ht}', '{$id_ht}');
//]]>
</script>
EOP;
    }

    // }}}
    // {{{ verifyLogin()

    /**
     * ログイン (assertion) を検証する
     *
     * @param string $assertionJson 認証結果の JSON 文字列
     * @return void
     */
    static public function verifyLogin($assertionJson)
    {
        $webauthn = self::createWebAuthn();

        // challengeは検証後に使い捨てる (パース失敗時も消費済みにするためパースより先に消費)
        $data = self::challengeTake();
        if (!$data) {
            throw new P2PasskeyAuthException('challengeが存在しません。最初からやり直してください');
        }
        $challenge = $data['challenge'];

        $assertion = self::jsonDecodeResult($assertionJson,
            array('clientDataJSON', 'authenticatorData', 'signature'),
            array('userHandle'));

        $id = $assertion['id'];
        // 非discoverableな認証ではresponseにuserHandleが含まれない (null) ことがある
        $userHandle = $assertion['response']['userHandle'] ?? '';

        $store = P2PasskeyStore::getInstance();
        $cred = $store->findByCredentialId($id);
        if ($cred === false) {
            throw new P2PasskeyAuthException('登録されていないパスキーです');
        }

        $auth_data = UrlSafeBase64::decode($assertion['response']['authenticatorData']);
        try {
            $webauthn->processGet(
                UrlSafeBase64::decode($assertion['response']['clientDataJSON']),
                $auth_data,
                UrlSafeBase64::decode($assertion['response']['signature']),
                $cred['publicKey'],
                UrlSafeBase64::decode($challenge),
                prevSignatureCnt: null,   // signCountチェックは下で行う
                requireUserVerification: true
            );
        } catch (WebAuthnException $e) {
            throw new P2PasskeyAuthException('パスキー認証に失敗しました (' . $e->getMessage() . ')');
        }

        // signCountのチェック (前回値と新値が両方nonzeroのとき単調増加を要求する。
        // nonzero→0はsync型credential化等で起こり得るため受け入れ、0→0はチェック対象外)。
        // processGetがauthDataの形式を検証済みのためunpackは安全
        $prev_count = isset($cred['signCount']) ? intval($cred['signCount']) : 0;
        $new_count = unpack('Ncount', substr($auth_data, 33, 4))['count'];

        if ($prev_count > 0 && $new_count > 0 && $new_count <= $prev_count) {
            // クローン認証器の疑い (検証失敗として login_failed_log に記録される)
            throw new P2PasskeyAuthException('パスキー認証に失敗しました (認証器の状態が不正です)');
        }

        // userHandleの検証 (spec §7.2 step 2)。
        // 非discoverableな認証ではresponseにuserHandleが含まれないことがあるため、存在する場合のみ照合する
        if ($userHandle !== '' && !hash_equals($cred['userHandle'], $userHandle)) {
            throw new P2PasskeyAuthException('パスキー認証に失敗しました (認証器の情報が一致しません)');
        }

        $fields = array('lastUsedAt' => time());
        if ($new_count > 0) {
            $fields['signCount'] = $new_count;
        }
        $store->update($id, $fields);
    }

    // }}}
    // {{{ verifyRegistration()

    /**
     * 登録 (attestation) を検証し、credential を保存する
     *
     * @param string $attestationJson 登録結果の JSON 文字列
     * @param string $name 登録名
     * @return void
     */
    static public function verifyRegistration($attestationJson, $name)
    {
        $webauthn = self::createWebAuthn();

        // challengeとuserHandleは検証後に使い捨てる (パース失敗時も消費済みにするためパースより先に消費)
        $data = self::challengeTake();

        // signal対象となりうるcredentialIdを先取りする
        $attestation = self::jsonDecodeResult($attestationJson,
            array('clientDataJSON', 'attestationObject'));
        $signal_id = $attestation['id'];

        if (!$data) {
            throw self::newRegistrationException('challengeが存在しません。最初からやり直してください', $signal_id);
        }
        $challenge = $data['challenge'];
        $user_handle = $data['userHandle'];

        try {
            $cred = $webauthn->processCreate(
                UrlSafeBase64::decode($attestation['response']['clientDataJSON']),
                UrlSafeBase64::decode($attestation['response']['attestationObject']),
                UrlSafeBase64::decode($challenge),
                requireUserVerification: true
            );
        } catch (WebAuthnException $e) {
            throw self::newRegistrationException('パスキーの登録に失敗しました (' . $e->getMessage() . ')', $signal_id);
        }

        // 登録時 (attestation) のsignCountはほぼ常に0で判断材料にならないため、
        // ライブラリの値をそのまま保存する (int または null=未観測)。
        // counter の判断はログイン時の比較で行う
        if (!P2PasskeyStore::getInstance()->add(
            UrlSafeBase64::encode($cred->credentialId),
            $cred->credentialPublicKey,
            $cred->signatureCounter,
            $name,
            $user_handle
        )) {
            throw self::newRegistrationException('パスキーを保存できませんでした', $signal_id);
        }
    }

    // }}}
    // {{{ getRpId()

    /**
     * RP-ID (自ホスト名) を返す
     *
     * @return string
     * @throws P2PasskeyAuthException ホスト名を解決できない場合
     */
    static private function getRpId()
    {
        $rp_id = P2Util::getMyHost();
        if (!$rp_id) {
            throw new P2PasskeyAuthException('ホスト名を解決できません');
        }
        return $rp_id;
    }

    // }}}
    // {{{ getRawCredentialIds()

    /**
     * 登録済みの全 credentialId を raw バイナリの配列で返す
     *
     * @return array
     */
    static private function getRawCredentialIds()
    {
        $raw_ids = array();
        foreach (P2PasskeyStore::getInstance()->findAll() as $cred) {
            // credentialIdは保存時にbase64url化済み
            $raw_ids[] = UrlSafeBase64::decode($cred['credentialId']);
        }
        return $raw_ids;
    }

    // }}}
    // {{{ buildOptionsScript()

    /**
     * optionsをJSON文字列化して<script>要素に組み立てる
     *
     * 出力は有効なJSオブジェクトリテラル (var passkeyOptions = {publicKey: {...}};)。
     * json_encodeは入力をUTF-8として要求するが、challenge/credentialId/userHandleは
     * base64url、ユーザー名は全供給源が /^[0-9A-Za-z_]+$/ で検証済みのuser_uであるため、
     * optionsの全値はASCIIに限定される。このためSJIS のまま渡せる。
     * 非ASCIIが混入した場合は P2PasskeyAuthException になり発行しない (fail-safe)。
     * JSON_HEX_TAGで< >をエスケープしscriptタグの突破を防ぐ
     *
     * @param object $public_key publicKeyオブジェクト (getGetArgs/getCreateArgsの戻り値)
     * @return string <script>要素
     */
    static private function buildOptionsScript($public_key)
    {
        try {
            $json = json_encode(
                array('publicKey' => $public_key),
                JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $e) {
            throw new P2PasskeyAuthException('パスキーoptionsの生成に失敗しました');
        }

        return <<<EOP
<script type="text/javascript">
//<![CDATA[
var passkeyOptions = {$json};
//]]>
</script>
EOP;
    }

    // }}}
    // {{{ jsonDecodeResult()

    /**
     * パスキーの応答データ (JSON文字列) をデコードする
     *
     * @param string $json
     * @param array $response_fields responseのフィールド名
     * @param array $response_fields_opts responseのフィールド名(省略可)
     * @return array
     */
    static private function jsonDecodeResult($json, $response_fields, $response_fields_opts = array())
    {
        $decoded = json_decode(strval($json), true);
        if (!is_array($decoded) || !is_array($decoded['response'] ?? null)
            || !is_string($decoded['id'] ?? null)
        ) {
            throw new P2PasskeyAuthException('パスキーの応答データが不正です');
        }
        foreach ($response_fields as $field) {
            if (!is_string($decoded['response'][$field] ?? null)) {
                throw new P2PasskeyAuthException('パスキーの応答データが不正です');
            }
        }
        foreach ($response_fields_opts as $field) {
            if (isset($decoded['response'][$field]) && !is_string($decoded['response'][$field])) {
                throw new P2PasskeyAuthException('パスキーの応答データが不正です');
            }
        }
        return $decoded;
    }

    // }}}
    // {{{ newRegistrationException()

    /**
     * 登録検証の失敗例外を生成する
     *
     * @param string $message
     * @param string|null $credentialId base64url。取得できない場合はnull
     * @return P2PasskeyAuthException
     */
    static private function newRegistrationException($message, $credentialId)
    {
        if ($credentialId !== null
            && P2PasskeyStore::getInstance()->findByCredentialId($credentialId) === false
        ) {
            return new P2PasskeyAuthException($message, $credentialId);
        }
        return new P2PasskeyAuthException($message);
    }

    // }}}
    // {{{ createWebAuthn()

    /**
     * WebAuthnインスタンスを生成する
     *
     * HTTPS判定・RP-ID解決を含む環境判定を行い、challengeの発行時と
     * 検証時で同一条件とするため発行・検証メソッドはすべてこれを経由する
     *
     * @return WebAuthn
     */
    static private function createWebAuthn()
    {
        $rp_id = self::getRpId();

        if (!P2_HTTPS_CONNECTION && $rp_id !== 'localhost') {
            throw new P2PasskeyAuthException('パスキー認証にはHTTPS接続が必要です');
        }

        try {
            return new WebAuthn('rep2', $rp_id, true);
        } catch (WebAuthnException $e) {
            throw new P2PasskeyAuthException('パスキー処理の初期化に失敗しました (' . $e->getMessage() . ')');
        }
    }

    // }}}
    // {{{ challengeSet()

    /**
     * challengeをchallengeストアに保存し、challengeストアIDをPCCに発行する
     *
     * @param mixed $data challengeとともに保存するデータ
     * @throws P2PasskeyAuthException challengeの保存に失敗した場合
     */
    static private function challengeSet($data)
    {
        $challenge_store_id = UrlSafeBase64::encode(random_bytes(16));

        if (!PasskeyChallengeDataStore::set($challenge_store_id, $data)) {
            throw new P2PasskeyAuthException('challengeを保存できませんでした');
        }

        P2Util::setCookie(self::COOKIE_NAME, $challenge_store_id, 0);
    }

    // }}}
    // {{{ challengeTake()

    /**
     * PCCのchallengeストアIDでchallengeストアからchallengeデータを取り出す (取り出したら破棄する)
     *
     * @return mixed|false challengeデータ (存在しない場合はfalse)
     */
    static private function challengeTake()
    {
        $challenge_store_id = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!$challenge_store_id) {
            return false;
        }

        $data = PasskeyChallengeDataStore::getKVS()->get($challenge_store_id, PasskeyChallengeDataStore::TTL);
        if ($data === null) {
            return false;
        }

        if (!PasskeyChallengeDataStore::delete($challenge_store_id)) {
            return false;
        }

        return $data;
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
