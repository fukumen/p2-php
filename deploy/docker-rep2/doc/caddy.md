# Caddyの設定とHTTPS化について

rep2への接続において、HTTPS接続を有効にしたい場合は、`docker-compose.override.yml` を作成して以下のいずれかの方法で設定を行ってください。

## 方法1：任意の証明書ファイルを指定して使用する
コンテナ内に存在する証明書ファイルを環境変数で指定することで HTTPS 通信が可能になります。

最も簡単な方法は、デフォルトで `/ext` にマウントされている `rep2-data` ディレクトリに証明書を配置することです。

**例：`rep2-data` に `server.crt` と `server.key` を配置した場合**
`docker-compose.override.yml`:
```yaml
services:
  rep2php8:
    environment:
      REP2_TLS_CERT: "/ext/server.crt"
      REP2_TLS_KEY: "/ext/server.key"
```

### 応用：ホスト側で Certbot 等を使用している場合

すでにホスト側で取得済みのLet's Encrypotの証明書がある場合は、それをコンテナにマウントして利用することも可能です。

`docker-compose.override.yml`:
```yaml
services:
  rep2php8:
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
docker compose -f /path/to/docker-rep2/docker-compose.yml exec rep2php8 caddy reload --config /etc/Caddyfile
```

## 方法2：Caddy本体をプラグイン入りにしてDNS-01チャンレンジを使用する
Caddy本体をプラグイン入りのものに差し替え、自前の `Caddyfile` を用意することでLet's Encrypotの証明書を Caddy に DNS-01 に取得させることが出来ます。

1. [Caddy公式サイト](https://caddyserver.com/download) から必要なプラグインを含んだカスタムバイナリをダウンロードします。

2. `caddy-local` ディレクトリを作成し、その中にダウンロードしたバイナリ (ファイル名: `caddy_linux_amd64_custom`) と、カスタマイズ用の設定ファイル (ファイル名: `Caddyfile`) を配置して、`docker-compose.override.yml`を以下のように設定します。

```yaml
services:
  rep2php8:
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
