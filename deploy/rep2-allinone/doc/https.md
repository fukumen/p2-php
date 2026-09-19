# HTTPS 化と証明書の運用ガイド

rep2への接続において、HTTPS接続を有効にしたい場合は、以下のいずれかの方法で設定を行ってください。

## 方法 1: Caddy プラグインによる DNS-01 チャレンジ
対応する DNS プロバイダー（Cloudflare, Route53, Google Cloud DNS 等）を利用している場合に最も簡単で確実な方法です。

### 1. カスタムバイナリの導入
標準の Caddy バイナリには DNS-01 チャレンジ用プラグインが含まれていません。以下の手順でカスタムバイナリを導入してください。

1. [Caddy公式サイト](https://caddyserver.com/download) から、必要なプラグイン（例: `dns.providers.cloudflare`）を含んだバイナリをダウンロードします。
2. ダウンロードしたバイナリを以下の場所に配置し、実行権限を付与します。

#### Linux
```bash
sudo mv caddy /var/lib/rep2-allinone/custom-caddy
sudo chmod +x /var/lib/rep2-allinone/custom-caddy
sudo chown rep2:rep2 /var/lib/rep2-allinone/custom-caddy
```

#### macOS (Homebrew)
```bash
mv caddy $(brew --prefix)/var/lib/rep2-allinone/custom-caddy
chmod +x $(brew --prefix)/var/lib/rep2-allinone/custom-caddy
```

※ これらのパスに実行可能なバイナリがある場合、パッケージ標準のバイナリよりも優先して使用されます。

### 2. 認証情報の設定
設定ファイル（Linux: `/etc/rep2-allinone/secrets.conf`、macOS: `$(brew --prefix)/etc/rep2-allinone/secrets.conf`）に API トークンなどを記述します。このファイルに記述された内容は環境変数として Caddy から参照できます。
```bash
# Cloudflare の場合
CLOUDFLARE_API_TOKEN=your_token_here
```

### 3. Caddyfile の編集
設定ディレクトリにある `Caddyfile` を編集し、ドメイン名と DNS チャレンジの設定を記述します。

```caddy
{
    email your-email@example.com
}

https://your-domain.example.com:443 {
    log {
        output stderr
    }
    tls {
        issuer acme {
            # Cloudflare の例
            dns cloudflare {env.CLOUDFLARE_API_TOKEN}

            # 伝搬確認を 1.1.1.1 で行う（ローカルDNS環境での失敗を防ぐため）
            resolvers 1.1.1.1
        }
    }
    root * /opt/rep2-allinone/p2-php/rep2 # macOS の場合はパスを調整すること
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

---

## 方法 2: 外部の Certbot を利用する (MyDNS 等)
Caddy プラグインが存在しないサービス（MyDNS.jp 等）を利用している場合や、すでにホスト側で Certbot を運用している場合に有効な方法です。

### 権限に関する注意点
root 権限で生成・管理される `/etc/letsencrypt/live/` 以下の証明書ファイルは、一般ユーザー（Linux の `rep2` ユーザーや macOS の Homebrew ユーザー）から直接読み取ることができません。これを解決するために、Certbot のデプロイフックを利用して証明書ファイルを「アプリが読み書き可能な場所」へコピーし、所有権を変更します。

### 手順

1. **デプロイフックスクリプトの作成**
#### Linux 用
   `/etc/letsencrypt/renewal-hooks/deploy/rep2-allinone.sh` を作成します。

```bash
#!/bin/sh
DEST="/var/lib/rep2-allinone/certs"
mkdir -p "$DEST"

cp "$RENEWED_LINEAGE/fullchain.pem" "$DEST/fullchain.pem"
cp "$RENEWED_LINEAGE/privkey.pem" "$DEST/privkey.pem"

chown -R rep2:rep2 "$DEST"
chmod 600 "$DEST/privkey.pem"

systemctl reload rep2-allinone
```

#### macOS (Homebrew) 用
   `$(brew --prefix)/etc/letsencrypt/renewal-hooks/deploy/rep2-allinone.sh` を作成します。

```bash
#!/bin/sh
DEST="$(brew --prefix)/var/lib/rep2-allinone/certs"
mkdir -p "$DEST"

cp "$RENEWED_LINEAGE/fullchain.pem" "$DEST/fullchain.pem"
cp "$RENEWED_LINEAGE/privkey.pem" "$DEST/privkey.pem"

BREW_USER=$(stat -f "%Su" "$(brew --prefix)")
chown -R "$BREW_USER" "$DEST"
chmod 600 "$DEST/privkey.pem"

brew services restart rep2-allinone
```

作成後、それぞれ実行権限を付与します（例: `sudo chmod +x ...`）。

2. **初回コピーの実行**
   すでに証明書が取得済みの場合は、一度手動で上記のスクリプトを（必要に応じて `sudo` で）実行してファイルを配置してください。
   ```bash
   # RENEWED_LINEAGE 環境変数をシミュレートして実行例
   sudo RENEWED_LINEAGE="/etc/letsencrypt/live/your-domain.example.com" /etc/letsencrypt/renewal-hooks/deploy/rep2-allinone.sh
   ```

3. **Caddyfile の設定**
   Caddyfile で、コピーした証明書ファイルを指定します。

```caddy
https://your-domain.example.com:443 {
    log {
        output stderr
    }
    tls /var/lib/rep2-allinone/certs/fullchain.pem /var/lib/rep2-allinone/certs/privkey.pem  # macOS の場合はパスを調整すること

    root * /opt/rep2-allinone/p2-php/rep2
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
