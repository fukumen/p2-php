# Caddyの設定とHTTPS化について

rep2への接続において、HTTPS接続を有効にしたい場合は、`docker-compose.override.yml` を作成して以下のいずれかの方法で設定を行ってください。

## 方法1：任意の証明書ファイルを指定して使用する
コンテナ内に存在する証明書ファイルを環境変数で指定することで HTTPS 通信が可能になります。

最も簡単な方法は、デフォルトで `/ext` にマウントされている `rep2-data` ディレクトリに証明書を配置することです。

**例：`rep2-data` に `server.crt` と `server.key` を配置した場合**
`docker-compose.override.yml`:
```yaml
services:
  rep2:
    environment:
      REP2_TLS_CERT: "/ext/server.crt"
      REP2_TLS_KEY: "/ext/server.key"
```

### 応用：ホスト側で Certbot 等を使用している場合

すでにホスト側で取得済みのLet's Encrypotの証明書がある場合は、それをコンテナにマウントして利用することも可能です。

`docker-compose.override.yml`:
```yaml
services:
  rep2:
    volumes:
      - /etc/letsencrypt:/etc/letsencrypt:ro  # 証明書ディレクトリを読み取り専用でマウント
    environment:
      REP2_TLS_CERT: "/etc/letsencrypt/live/example.com/fullchain.pem"
      REP2_TLS_KEY: "/etc/letsencrypt/live/example.com/privkey.pem"
      CADDY_USER: "root"  # ホスト側の証明書を読み取るために root 権限が必要な場合に指定
```

**注意：**
証明書ファイルのパーミッション設定により、コンテナ内で実行される Caddy がファイルを読み取れない場合があります。その場合は `CADDY_USER: "root"` を環境変数に設定し、Caddy を root 権限で起動させるようにしてください。なお、コンテナの起動時にcaddy_configやcaddy_dataの所有者をwww-dataに変更しているので root であれば問題ありませんが、それ以外のユーザーでは権限が不足する可能性があります。

ホスト側で動作している Certbot によって証明書が更新された際、新しい証明書を Caddy に反映させるためには、コンテナ内の Caddy をリロードする必要があります。

これを自動化するには、Certbot のデプロイフック (`--deploy-hook` または `/etc/letsencrypt/renewal-hooks/deploy/` に配置するスクリプト) を利用して、更新時に Caddy をリロードするコマンドを実行するように設定してください。

**フックで実行するコマンドの例:**
```shell
docker compose -f /path/to/docker-rep2/docker-compose.yml exec rep2 caddy reload --config /etc/Caddyfile
```

## 方法2：Caddy本体をプラグイン入りにしてDNS-01チャンレンジを使用する
Caddy本体をプラグイン入りのものに差し替え、自前の `Caddyfile` を用意することでLet's Encrypotの証明書を Caddy に DNS-01 に取得させることが出来ます。

1. [Caddy公式サイト](https://caddyserver.com/download) から必要なプラグインを含んだカスタムバイナリをダウンロードします。

2. `caddy-local` ディレクトリを作成し、その中にダウンロードしたバイナリ (ファイル名: `caddy_linux_amd64_custom`) と、カスタマイズ用の設定ファイル (ファイル名: `Caddyfile`) を配置して、`docker-compose.override.yml`を以下のように設定します。

```yaml
services:
  rep2:
    volumes:
      - ./caddy-local/caddy_linux_amd64_custom:/usr/bin/caddy   # DNS-01対応バイナリをマウント
      - ./caddy-local/Caddyfile:/etc/Caddyfile                  # カスタマイズしたCaddyfileをマウント
    environment:
      # Cloudflare の場合
      CLOUDFLARE_API_TOKEN: "your_api_token_here"
```

3. `Caddyfile` にドメイン名や DNS チャレンジの設定を記述します。

```caddy
{
    email your-email@example.com
}

https://your.domain.example.com:8443 {
    log {
        output stderr
        # debug
    }
    tls {
        issuer acme {
            # Cloudflare の例
            dns cloudflare {env.CLOUDFLARE_API_TOKEN}

            # 伝搬確認を 1.1.1.1 で行う（ローカルDNS環境での失敗を防ぐため）
            resolvers 1.1.1.1
        }
    }
    root * /var/www/rep2
    php_fastcgi 127.0.0.1:9000
    file_server

    @js_files {
        path *.js
    }
    header @js_files Content-Type "application/javascript; charset=Shift_JIS"

    @css_files {
        path *.css
    }
    header @css_files Content-Type "text/css; charset=Shift_JIS"
}
```

この方法では、`rc.entry` による `Caddyfile` の自動生成は行われず、マウントした `Caddyfile` がそのまま使用されます。

### 他の DNS プロバイダーの設定方法
Cloudflare 以外のプロバイダー（Route53, Google Cloud DNS 等）を使用したい場合は、以下の手順で正しい設定方法を確認してください。

1. [Caddy Modules](https://caddyserver.com/docs/modules/) ページから、`dns.providers.` で始まるモジュール（例: `dns.providers.cloudflare`）を探します。
2. モジュール詳細ページにある **"Code repository"** にある GitHub のリンクを開きます。
3. リポジトリの **README.md** に記載されている `Caddyfile config` のセクションを確認します。
   - `dns <provider_name> { ... }` または `dns <provider_name> <arguments...>` の形式で記述方法が記載されています。
4. 設定値（APIキーなど）を直接書く代わりに、`{env.VARIABLE_NAME}` という形式（プレースホルダー）の説明がある場合とない場合があるようです。

**注意：**
mydns は DNS プラグインが無さそうなので「方法1」のやり方で certbot を使うしか無さそう。

## クライアント証明書による認証（mTLS）

アクセスするブラウザにクライアント証明書を要求する認証（mTLS）を設定できます。HTTPS化とは目的が異なり、こちらはアクセスしてくるクライアントの認証のための機能です。クライアント証明書のやりとりはTLSハンドシェイクの中で行われるため、方法1・方法2のHTTPS化の設定に`client_auth`ブロックを追加する形で使います。Caddyの標準機能で対応できるため、カスタムバイナリは不要です。

### 1. プライベートCAの作成とクライアント証明書の発行

任意のマシンでopensslを使って作成します。

```shell
# CAの作成（最初に1回だけ実行）
openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 -nodes \
  -keyout ca.key -out ca.pem -subj "/CN=rep2 client CA"

# クライアント証明書の発行（デバイスを追加するときに実行）
openssl req -newkey rsa:4096 -nodes -keyout client.key -out client.csr -subj "/CN=rep2-user"
printf "extendedKeyUsage=clientAuth" > client-ext.cnf
openssl x509 -req -in client.csr -CA ca.pem -CAkey ca.key -CAcreateserial -days 365 \
  -sha256 -extfile client-ext.cnf -out client.pem
openssl pkcs12 -export -inkey client.key -in client.pem -certfile ca.pem -out client.p12
```

各デバイスにインストールするのは`client.p12`です。`ca.pem`はCaddyの設定で使用します。`ca.key`は漏洩すると誰でも証明書を発行できてしまうため厳重に管理してください。デバイスを追加するときは`-subj`のCNをデバイス名に変えてクライアント証明書の発行コマンドを再度実行します。`client.p12`のインストール後は、それ以外の生成ファイルは不要なので削除して構いません。

エクスポート時にパスワードを入力する必要が有ります。このパスワードはデバイスにclient.p12をインストールするときに入力することになります。

### 2. 証明書ファイルの配置

`ca.pem`とサーバー証明書（`server.crt`、`server.key`）を`rep2-data`ディレクトリ（コンテナ内の`/ext`）に配置し、許可するデバイスの`client.pem`を`/ext/clients`ディレクトリに配置します。デバイスの追加・削除は、このディレクトリの`client.pem`を出し入れした後、`docker compose -f /path/to/docker-rep2/docker-compose.yml exec rep2 caddy reload --config /etc/Caddyfile`でCaddyに反映します。

### 3. Caddyfileの作成とマウント

クライアント証明書の設定は`tls`ディレクティブのブロック内に記述するため、rc.entryによる自動生成Caddyfileでは対応できません。カスタムCaddyfileを`/etc/Caddyfile`にマウントすると、自動生成は行われずマウントしたファイルがそのまま使用されます。

方法1を利用している場合は、カスタムCaddyfileを新たに作成して`tls`ディレクティブに証明書のパスを直接記述します（下記のCaddyfileの例がそのまま使えます）。`REP2_TLS_CERT`と`REP2_TLS_KEY`の環境変数による指定は不要になります。

方法2を利用している場合は、マウント済みのCaddyfileの`tls`ブロックに`client_auth`ブロックを追加するだけです。mTLSの設定のためにカスタムバイナリを差し替える必要はありません。

`caddy-local/Caddyfile`:
```caddy
:8443 {
    log {
        output stderr
    }
    tls /ext/server.crt /ext/server.key {
        client_auth {
            mode require_and_verify
            trust_pool file /ext/ca.pem
            verifier leaf {
                folder /ext/clients
            }
        }
    }
    root * /var/www/rep2
    php_fastcgi 127.0.0.1:9000
    file_server

    @js_files {
        path *.js
    }
    header @js_files Content-Type "application/javascript; charset=Shift_JIS"

    @css_files {
        path *.css
    }
    header @css_files Content-Type "text/css; charset=Shift_JIS"
}
```

`docker-compose.override.yml`:
```yaml
services:
  rep2:
    volumes:
      - ./caddy-local/Caddyfile:/etc/Caddyfile
```

### 4. クライアント証明書のインストール

`client.p12`をアクセスに使うデバイスの証明書ストアにインストールします。

- Windows: ダブルクリックして「個人」ストアにインストール
- macOS: キーチェーンアクセスにインストール（OpenSSL 3 既定の p12 はインポート不可。エクスポート時に `-macalg sha1 -keypbe PBE-SHA1-3DES -certpbe PBE-SHA1-3DES` を付ける）
- iOS / Android: OSの設定（プロファイル）からインストール
- Firefox: OSのストアとは別に独自の証明書ストアを持つため、Firefoxの設定からインストールが必要
- Linux (Chrome): 設定 → プライバシーとセキュリティ → セキュリティ → 証明書の管理の「あなたの証明書」からインストール

アクセス時にブラウザの証明書選択ダイアログが表示され、`clients`ディレクトリに置かれた証明書であればrep2が表示されます。
