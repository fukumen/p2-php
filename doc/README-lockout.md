# ログインロックアウト機能とIPアドレス遮断への活用

## 概要

本機能は、同一IPアドレスからのログイン失敗が短時間に繰り返された場合に、そのIPアドレスからのログインを一時的に制限（ロックアウト）するものです。
設定の詳細は `conf/conf_lockout.inc.php` を参照してください。

## IPアドレス記録の改善

これまでの rep2 では、プログラムが認識する「直接の接続元IPアドレス（REMOTE_ADDR）」をそのままログに記録していました。
そのため、rep2 がリバースプロキシやルーターの背後に配置されている構成では、攻撃者のIPアドレスではなく「プロキシサーバーやルーターのプライベートIP」が記録されてしまい、外部ツール（fail2ban等）でログを監視しても、攻撃元を特定して遮断することが困難でした。

今回のアップデートにより `vectorface/whip` ライブラリを導入したことで、適切な設定（`conf/conf_lockout.inc.php`）を行うことにより、リバースプロキシや Cloudflare を経由している場合でも **「攻撃者の本来のIPアドレス」** を `p2_login_failed.dat.php` に正確に記録できるようになりました。

これにより、このログファイルを監視して、ファイアウォール（nftables/iptables）や Webサーバー（nginx / Apache）の層で不正アクセスを動的に遮断することが可能になります。

### 正確なIPを認識させるためのポイント (Webサーバー側の設定)

リバースプロキシや Cloudflare を経由する構成では、攻撃者の本来のIPアドレスは HTTP ヘッダー（`X-Forwarded-For` や `CF-Connecting-IP` など）に格納されて Webサーバーへ伝達されます。
Webサーバー層でのアクセス拒否を正しく機能させるには、これらのヘッダーを解釈し「本来のIPからの直接アクセス」として認識させる設定が重要になります。

*   **nginx の場合**: `ngx_http_realip_module` を使用して `real_ip` の設定を行います。
*   **Apache の場合**: `mod_remoteip` モジュールを使用します。

---

## ネットワーク構成別の遮断シナリオ

構成に応じて、どのレイヤーで遮断を行うのが適切かのサンプルを以下に示します。
※具体的な fail2ban や Webサーバーの設定手順は各ツールのドキュメントを参照してください。

### 1. 直接公開・ルーター・Docker構成 (送信元IPがサーバーOSから直接見える場合)
*   インターネット <-> (ルーター/Docker) <-> Webサーバー + PHP + rep2
*   **遮断レイヤーと概要**:
    *   **OSファイアウォール (nft/iptables)**: fail2ban でログを監視し、OSレベルで攻撃者のIPを直接 drop します。Docker の場合はホスト側で実行します。
    *   **Webサーバー (nginx / Apache)**: OSの操作権限がない場合、Webサーバーのアクセス拒否設定（nginx の `deny` や Apache の `Require not ip` 等）を fail2ban で動的に更新して遮断します。

### 2. リバースプロキシ・(Cloudflare DNS Proxy) 構成 (送信元IPがHTTPヘッダーにある場合)
*   インターネット <-> (Cloudflare) <-> リバースプロキシ <-> Webサーバー + PHP + rep2
*   **遮断レイヤーと概要**:
    *   **CDN/Edge (Cloudflare)**: Cloudflare API 経由で IP Access Rules に登録し、エッジで遮断します。
    *   **リバースプロキシ**: プロキシ層のアクセス拒否設定やファイアウォールで遮断します。
    *   **Webサーバー**: アプリケーション側の Webサーバーで、HTTPヘッダーを元に遮断します。

### 3. Cloudflare Zero Trust (Tunnel) 構成 (送信元IPがOSから見えない場合)
*   インターネット <-> Cloudflare Zero Trust <-> cloudflared <-> (リバースプロキシ) <-> Webサーバー + PHP + rep2
*   **遮断レイヤーと概要**:
    *   **Cloudflare (Edge)**: Zero Trust (Access/Gateway) のポリシーで該当IPを拒絶リストに追加します。
    *   **リバースプロキシ**: 前段に置かれたプロキシ層で、HTTPヘッダーを元に遮断します。
    *   **Webサーバー**: トンネル通信のため OS レベルのファイアウォールでは遮断できません。最も後ろの Webサーバー層で、HTTP ヘッダーを元に遮断します。

---

## リバースプロキシ設定時の注意点

リバースプロキシ構成において本機能（および Webサーバー層でのIP遮断）を正しく動作させるためには、**リバースプロキシからバックエンド（rep2）へ適切な HTTP ヘッダーを渡す設定** が不可欠です。

もしリバースプロキシ側で `X-Forwarded-For` 等のヘッダーを付与し忘れると、バックエンド側には「リバースプロキシ自身のIP」しか伝わらず、すべてのアクセスが同一のIPとみなされてしまいます。この状態では、単一の攻撃によって意図せず全ユーザーがロックアウトされるなどのトラブルに繋がるため、十分注意してください。

*   **nginx をリバースプロキシにする場合の設定例**:
    ```nginx
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Real-IP $remote_addr;
    ```
*   **Apache をリバースプロキシにする場合の設定例**:
    ```apache
    RequestHeader set X-Forwarded-For %{REMOTE_ADDR}e
    ```
