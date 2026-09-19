# rep2-allinone

PHP-FPM、Caddy (Webサーバー)、そして rep2を統合したパッケージです。対応しているのは以下の通り。

- Linux Debian系 (amd64/arm64): `.deb`
- Linux RedHat系 (x86_64/aarch64): `.rpm`
- macOS (Intel/Apple Silicon): `Homebrew Tap`

なお、Windows向けを試験的に用意していますが、PHP-FPMではなくPHP-CGIだったり拡張ライブラリの不足が未チェックだったりします。

## 特徴

- All-in-One パッケージ: PHP-FPM、Caddy (Webサーバー)、そして rep2 本体が1つのパッケージに統合されており、複雑なミドルウェアのインストールが不要です。
- 外部依存なし: 静的リンクされた PHP バイナリを使用しているため、OSのPHPバージョンや他のシステム環境に影響されずに独立して動作します。
- 簡単かつ安全な運用: 自動的に専用の非特権ユーザー (`rep2`) が作成され、標準の systemd サービスとして管理できるため、安全かつ簡単に運用できます。
- 常に最新: [`rep2`](https://github.com/fukumen/p2-php) のソースコードが更新されると自動的に新しいパッケージがビルドされ、リポジトリ経由で手軽にアップデートできます。
- パッケージサイズについて: 静的リンクされた Caddy と PHP を内包しているため、パッケージのファイルサイズがやや大きくなります。（目安: Caddy 約39MB、PHP / PHP-FPM 各約11MB）

## インストールと起動

パッケージは公式の APT / DNF リポジトリからインストールすることをお勧めします。これにより、アップストリーム (`p2-php`) の更新に自動で追従できます。

### Ubuntu / Debian 系 (APT リポジトリ)

```bash
curl -fsSL https://fukumen.github.io/rep2-allinone/apt/fukumen.gpg.key | sudo gpg --dearmor -o /usr/share/keyrings/rep2-allinone-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/rep2-allinone-keyring.gpg] https://fukumen.github.io/rep2-allinone/apt ./" | sudo tee /etc/apt/sources.list.d/rep2-allinone.list

sudo apt update
sudo apt install rep2-allinone
```

### RHEL / CentOS / AlmaLinux 系 (DNF リポジトリ)

```bash
cat <<EOF | sudo tee /etc/yum.repos.d/rep2-allinone.repo
[rep2-allinone]
name=rep2-allinone Repository
baseurl=https://fukumen.github.io/rep2-allinone/rpm
enabled=1
gpgcheck=1
repo_gpgcheck=1
gpgkey=https://fukumen.github.io/rep2-allinone/rpm/fukumen.gpg.key
EOF

sudo dnf install rep2-allinone
```

### macOS (Homebrew Tap)

```bash
brew tap fukumen/tap
brew install rep2-allinone

# ログイン中のみサービスを起動したい場合
brew services start rep2-allinone

# ログインしていないときもサービスを起動したい場合
sudo brew services start rep2-allinone
```

### ⚠️アップデート時の注意事項

設定ディレクトリ (`conf`) は、初回起動時にデフォルト設定からコピーされ、その後のアップデート時には以下の項目が自動的にマージ・更新されます。

- `conf.inc.php` 内の `p2version`: 自動的に最新のパッケージ版に更新されます。
- `conf_user_def*`: これらのファイルは常に最新版で上書きされます。
- 新規追加ファイル: 自動的に追加されます。

それ以外は自動でマージされません。設定項目の追加や変更があった場合には、手動でマージする必要があります。

以下のコマンドで、パッケージ同梱の最新テンプレート (`conf.orig`) と現在運用中の設定の差分を確認できます。

### Linux (Debian / RHEL)
```bash
diff -ru /opt/rep2-allinone/p2-php/conf.orig /var/lib/rep2-allinone/conf | iconv -f SHIFT_JIS -t UTF-8
```

### macOS (Homebrew)
```bash
diff -ru $(brew --prefix)/opt/rep2-allinone/p2-php/conf.orig $(brew --prefix)/var/lib/rep2-allinone/conf | iconv -f SHIFT_JIS -t UTF-8
```

-のみの行が表示表示されているようならリポジトリ側で追加されているのでマージが必要です。
[confの変化点](https://github.com/fukumen/p2-php/commits/php8-merge-mbstring/conf)を参考に作業してください。

### インストール後の動作

インストールが完了し、サービスが起動した状態（Linux では自動起動、macOS では `brew services start` 実行後）で、Webブラウザからアクセス可能になります。Linux では専用ユーザー (`rep2`) 下で systemd サービスとして、macOS では Homebrew のサービスとして動作します（ポート等の詳細はそれぞれの `Caddyfile` で設定されます）。

デフォルトでは HTTP (ポート `10088`) で動作します。HTTPS 化や証明書の運用については、[HTTPS 化と証明書の運用ガイド](doc/https.md) を参照してください。

## システム構成 (インストール後)

### Linux (Debian / RHEL)
- プログラム本体: `/opt/rep2-allinone` (PHPスクリプトなど、変更されないシステムファイル)
- 環境設定ファイル: `/etc/default/rep2-allinone` (ポート番号などの環境変数を設定)
- 設定ファイル: `/etc/rep2-allinone` (`Caddyfile` や `php-fpm.conf` はここに配置され、自由に編集可能です)
- データ領域: `/var/lib/rep2-allinone` (アプリの設定やログ、キャッシュデータはこちらに保存されます)
- サービス名: `rep2-allinone.service`

### macOS (Homebrew)
- プログラム本体: `/opt/homebrew/opt/rep2-allinone` (PHPスクリプトなど、変更されないシステムファイル。Intel Macの場合は `/usr/local/opt/rep2-allinone`)
- 環境設定ファイル: `/opt/homebrew/etc/rep2-allinone/default` (ポート番号などの環境変数を設定)
- 設定ファイル: `/opt/homebrew/etc/rep2-allinone` (`Caddyfile` や `php-fpm.conf` はここに配置され、自由に編集可能です)
- データ領域: `/opt/homebrew/var/lib/rep2-allinone` (アプリの設定やログ、キャッシュデータはこちらに保存されます)
- サービス名: `rep2-allinone`

```bash
# ステータスの確認 (Linux)
sudo systemctl status rep2-allinone

# サービスの再起動 (Linux)
sudo systemctl restart rep2-allinone

# ログの確認 (Linux)
sudo journalctl -u rep2-allinone -f
sudo journalctl -t php-fpm -f

# ステータスの確認 (macOS)
brew services info rep2-allinone

# サービスの再起動 (macOS)
brew services restart rep2-allinone

# ログの確認 (macOS)
tail -f $(brew --prefix)/var/lib/rep2-allinone/rep2-allinone.log
tail -f $(brew --prefix)/var/lib/rep2-allinone/php-fpm.log

※brew services start 時に sudo をつけて起動している場合は、sudo をつけて操作してください
```

## パッケージのバージョンについて

本パッケージのバージョン番号は、内包する各コンポーネントのバージョンがひと目でわかるように構成されています。
インストールされたパッケージのバージョン（例: `1.0.0-php8.5.3-caddy2.9.1+202403051200` 等）は、以下の情報を表しています。

- `1.0.0`: rep2-allinone 自体のベースバージョン
- `php8.5.3` / `caddy2.9.1`: 同梱されている PHP と Caddy のバージョン
- `202403051200`: 上流リポジトリ (`rep2`) の最新コミット日時 (JST)

※ OSのパッケージ命名規則により、RPM パッケージの場合は区切り文字がハイフンではなくドット (`.`) に変換されます。

## アンインストール

### Debian / Ubuntu

#### パッケージの削除

```bash
sudo apt remove --purge rep2-allinone
```

#### 設定・データの削除 (任意)

```bash
# リポジトリ設定の削除
sudo rm /etc/apt/sources.list.d/rep2-allinone.list
sudo rm /usr/share/keyrings/rep2-allinone-keyring.gpg
sudo apt update

# 設定・データディレクトリの削除
sudo rm -rf /etc/rep2-allinone
sudo rm -rf /var/lib/rep2-allinone
sudo rm -f /etc/default/rep2-allinone

# 専用ユーザーの削除
sudo userdel rep2
```

### RHEL / CentOS / AlmaLinux

#### パッケージの削除

```bash
sudo dnf remove rep2-allinone
```

#### 設定・データの削除 (任意)

```bash
# リポジトリ設定の削除
sudo rm /etc/yum.repos.d/rep2-allinone.repo
sudo dnf clean all

# 設定・データディレクトリの削除
sudo rm -rf /etc/rep2-allinone
sudo rm -rf /var/lib/rep2-allinone
sudo rm -f /etc/default/rep2-allinone

# 専用ユーザーの削除
sudo userdel rep2
```

### macOS (Homebrew)

#### パッケージの削除

```bash
brew services stop rep2-allinone
brew uninstall rep2-allinone
```

#### 設定・データの削除 (任意)

```bash
# Homebrew Tap の解除
brew untap fukumen/tap

# 設定・データディレクトリの削除
rm -rf $(brew --prefix)/etc/rep2-allinone
rm -rf $(brew --prefix)/var/lib/rep2-allinone
```

## 開発者向け: パッケージのビルド

ご自身でカスタマイズしてパッケージをビルドする場合は、各プラットフォーム向けのビルドコマンドを使用します。
ビルドが完了すると、各プラットフォーム向けのパッケージが `dist/` ディレクトリ配下に生成されます。

### Linux (Debian / RPM)

`dpkg-deb` または `rpmbuild` が必要です。

```bash
# Debian パッケージ (.deb) のビルド
make deb               # ホスト環境 (amd64/arm64) に応じて自動判定
make ARCH=arm64 deb    # arm64 向けを明示的に指定

# RPM パッケージ (.rpm) のビルド
make rpm               # ホスト環境 (x86_64/aarch64) に応じて自動判定
make ARCH=arm64 rpm    # aarch64 向けを明示的に指定
```

### macOS (Homebrew)

Homebrew で配布するための `.tar.gz` 形式のアーカイブが生成されます。

```bash
# macOS パッケージ (.tar.gz) のビルド
make macos               # ホスト環境 (x86_64/arm64) に応じて自動判定
make ARCH=x86_64 macos   # Intel (x86_64) 向けを明示的に指定
make ARCH=arm64 macos    # Apple Silicon (arm64) 向けを明示的に指定
```

ビルドが完了したら、生成された `.tar.gz` ファイルの SHA256 ハッシュ値を計算し、`macos/homebrew-formula.rb.template` を基に Homebrew Tap のフォーミュラを更新してください。

### ローカルの rep2 リポジトリを参照する場合

`make` 実行時に `REP2_REPO` と `REP2_BRANCH` を指定することで、任意のリポジトリやローカルディレクトリ、およびブランチを参照してビルドできます。

```bash
# 相対パスでローカルディレクトリの特定のブランチを参照してビルド
make REP2_REPO=../p2-php REP2_BRANCH=develop deb
```

