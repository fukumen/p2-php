/**
 * rep2 - パスキー認証
 *
 * @simplewebauthn/browser を使用
 */

(function () {
    'use strict';

    /* 認証器とのやり取りで例外が発生したときの表示 */
    var showPasskeyError = function (e) {
        var msg = document.getElementById('passkey_msg');
        if (!msg) {
            return;
        }

        if (e.name === 'NotAllowedError') {
            msg.textContent = 'パスキーの認証がキャンセルされたか、タイムアウトしました。';
        } else if (e.name === 'InvalidStateError') {
            msg.textContent = 'このパスキーは既に登録されています。';
        } else {
            msg.textContent = 'パスキーの処理でエラーが発生しました (' + e.name + ': ' + e.message + ')';
        }
    }

    var getRealm = function () {
        var w = (window === window.top) ? window : window.top;
        return (w.SimpleWebAuthnBrowser) ? w : window;
    };

    /* ログイン (assertion) */
    window.passkeyLogin = async function (form) {
        try {
            var assertion = await getRealm().SimpleWebAuthnBrowser.startAuthentication({
                optionsJSON: window.passkeyOptions.publicKey
            });

            /* 結果の全フィールドはASCII (base64url) のため stringify したまま送れる */
            form.elements.assertion.value = JSON.stringify(assertion);
            form.submit();
        } catch (e) {
            showPasskeyError(e);
        }
    }

    /* 登録 (attestation)。登録名はフォーム内の入力欄から送信される */
    window.passkeyRegister = async function (form) {
        try {
            var attestation = await getRealm().SimpleWebAuthnBrowser.startRegistration({
                optionsJSON: window.passkeyOptions.publicKey
            });

            /* 未入力の登録名は登録日時で補完する */
            if (form.elements.name.value === '') {
                var d = new Date();
                var pad2 = function (n) { return String(n).padStart(2, '0'); };

                form.elements.name.value = 'パスキー ' + d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-'
                    + pad2(d.getDate()) + ' ' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
            }

            form.elements.attestation.value = JSON.stringify(attestation);
            form.submit();
        } catch (e) {
            showPasskeyError(e);
        }
    }

    /* 登録名の変更 */
    window.passkeyRename = function (form) {
        var name = window.prompt('新しい登録名を入力してください', form.elements.current_name.value);
        if (name === null || name === form.elements.current_name.value) {
            return false;
        }
        if (name === '' || name.length > 50) {
            window.alert('登録名は1～50文字で入力してください');
            return false;
        }
        form.elements.new_name.value = name;
        return true;
    }

    /* サーバーに存在しないcredentialの認証器側の整理 (Signal API)。
       削除成功時と、登録検証に失敗して登録が行われなかったcredentialに使用する */
    window.passkeySignalUnknown = function (rpId, credentialId) {
        SimpleWebAuthnBrowser.sendSignal({
            signalName: 'unknownCredential',
            rpID: rpId,
            credentialID: credentialId
        }).catch(
            /* 非対応環境や失敗は握りつぶす */
            function () {}
        );
    }

}());

/*
 * Local Variables:
 * mode: javascript
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: t
 * End:
 */
/* vim: set syn=javascript fenc=cp932 ai noet ts=4 sw=4 sts=4 fdm=marker: */
