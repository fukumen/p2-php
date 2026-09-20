# docker-rep2

## 概要

fukumen/p2-php リポジトリの `deploy/docker-rep2/` 配下にある、rep2 + caddy + PHP の dockerコンテナを構成するDockerfileとdocker-compose.ymlです。
[pen/docker-rep2](https://github.com/pen/docker-rep2)のフォークです。

* rep2
* caddy + PHP 他

rep2本体のソースコードは同じリポジトリのルートを参照してビルドします（`p2-rep2` コンテキストが `../..` = リポジトリルートを指します）。

## 使い方

### 公式イメージを使用する場合

イメージの取得と起動だけなら git clone は不要で、composeファイルがあれば足ります。
標準ではポート番号は10088です。変更したい場合はdocker-compose.ymlを編集してください。

```shell
mkdir rep2 && cd rep2
curl -O https://raw.githubusercontent.com/fukumen/p2-php/main/deploy/docker-rep2/docker-compose.yml
# docker-compose.yml の SECRET_KEY を「openssl rand -hex 32」の実行結果に置き換える
docker compose up -d
```

イメージの更新は `docker compose pull`（または `docker compose up -d --pull always`）で可能です。

追加機能（PostgreSQL / MySQL / ImageMagick / AAS 等）を使う場合は、overrideとして取得してください（編集不要）。

```shell
curl -o docker-compose.override.yml https://raw.githubusercontent.com/fukumen/p2-php/main/deploy/docker-rep2/docker-compose.extra.yml
```

### ビルドする場合

p2-php リポジトリ全体の clone が必要です（`p2-rep2` コンテキストがリポジトリルートを参照するため）。

```shell
git clone https://github.com/fukumen/p2-php.git
cd p2-php/deploy/docker-rep2
./build.py build
./build.py up
```

ビルド・運用コマンドは `./build.py --help` を参照してください。
デバッグとリモート実行は `.env` で指定します（後述）。
共通オプション（`--debug`、`--remote` など）はサブコマンドの前に指定してください。

標準ではカレントディレクトリのrep2-dataにrep2のdataやconf、caddyのcaddy_configやcaddy_dataが格納されます。
変更したい場合はdocker-compose.ymlを編集してください。

### :warning:confについての注意事項

初回起動時にデフォルト設定 (`conf.orig`) から現在の設定 (`conf`) へ設定ファイルがコピーされます。その後のアップデート時には、以下の項目のみが自動的にマージ・更新されます。

- `conf.inc.php` 内の `p2version`: 自動的に最新のパッケージ版に更新されます。
- `conf_user_def*`: これらのファイルは常に最新版で上書きされます。
- 新規追加ファイル: 自動的に追加されます。

それ以外は自動でマージされません。設定項目の追加や変更があった場合には、手動でマージする必要があります。

以下のコマンドで、デフォルト設定 (`conf.orig`) と現在の設定 (`conf`) の差分を確認できます。

```shell
docker compose exec rep2 diff /var/www/conf.orig /ext/conf | iconv -f SHIFT_JIS -t UTF-8
```

-のみの行が表示表示されているようならリポジトリ側で追加されているのでマージが必要です。
[confの変化点](https://github.com/fukumen/p2-php/commits/main/conf)を参考に作業してください。

### :warning:data/prefについての注意事項

「認証関係のハッシュや暗号化を強化」によりp2_auth_user.phpとconf_user.srd.cgiが従来のrep2では全く読めなくなります。バックアップをとっておいてください。

### 通常設定

rep2を以下の設定で使う想定です。

```
proxy_use: しない
use_https: する
2ch_to_5ch: する
http_post_method: HTTP_Request2コンパチ
```

HTTPリクエストをproxy経由で解析・デバッグしたい場合は[doc/mitmproxy.md](doc/mitmproxy.md)を参照してください。

## 構成

### rep2

ビルド時にはリポジトリルートのソース一式がイメージへ取り込まれます。

「認証関係のハッシュや暗号化を強化」により、environmentにSECRET_KEYの設定が必要です。ホストで openssl rand -hex 32 を実行した結果を記載してください。

### PHP

memory_limitを変更したいなどの理由でphp.iniの設定したい場合、php-local.iniのようなファイルを用意してdocker-compose.ymlでバインドマウントするよう記載してください。

memory_limitはデフォルトで128Mになっています。docker compose logsを確認してAllowed memory size of〜のようなエラーが出る場合には設定してください。

メモリ消費量を計測したいときはphp-fpm.confを変更したい場合、www-local.confのようなファイルを用意してdocker-compose.ymlでバインドマウントするよう記載してください。

### PostgreSQL/MySQLやExif、imagickなど追加機能を使用したい場合

標準構成でも基本的な機能（SQLite, GD）は利用可能です。
さらに以下の機能を使用したい場合、`docker-compose.extra.yml`を参考に`docker-compose.override.yml`を用意しておくことで、各種追加機能を含む「全部入り」イメージ `rep2-extra` を使用してください。

- ImageMagickを使用したい場合
- AASを使用したい場合
- Exif表示を使用したい場合
- PostgreSQLやMySQLを使用したい場合
- IC2のZIPで一括ダウンロードを使用したい場合
- RSSリーダー機能（デフォルト無効）をしたい場合

外部データベース（PostgreSQL/MySQL）の具体的な設定方法については、[doc/database.md](doc/database.md) を参照してください。

### caddy

rep2への接続はHTTP接続とHTTPS接続が選べます。デフォルトではホスト側のポート10088でHTTP接続として待ち受けます。ポート番号を変更したい場合は、`.env` ファイルを作成して `REP2_PORT=80` のように記載するか、`docker-compose.yml` を直接編集してください。

HTTPS接続を有効にしたい場合や証明書に関する設定については、[doc/caddy.md](doc/caddy.md) を参照してください。

### ソフトバージョン

実バージョンは[GitHub Packagesのrep2パッケージページ](https://github.com/fukumen/p2-php/pkgs/container/rep2)で確認できます。

## docker-compose.override.ymlについて

docker-compose.ymlを編集してしまってもよいですが、docker-compose.override.ymlを別途用意してそちらに記載した方がgit pullをしたときにコンフリクトも起きないのでオススメです。

## デバッグ方法

`--debug` を指定してビルドすると xdebug を有効化したデバッグ用イメージ (`rep2-dbg`) が作られ、起動時にも `docker-compose.debug.yml` が読み込まれます。既定はデバッグオフで、`.env` に `REP2_BUILD_DEBUG=true` と記載すると `--debug` を省略できます。

```shell
./build.py --debug build-base
./build.py --debug build
./build.py --debug --noremote up
```

XdebugのpathMappings等を設定済みのvscode launch設定をリポジトリルートの`.vscode/launch.json`に同梱しています。vscodeでリポジトリルート（p2-php）をフォルダとして開き、PHP Debug拡張機能を使ってrep2のデバッグが出来ます。

## リモート実行

`up` / `down` / `pull` / `logs` / `exec` / `config` / `update` / `confdiff` / `prune` は、`.env` にリモート先が記載されていれば既定でリモート実行、未設定ならローカル実行になります（`--remote` / `--noremote` で強制切り替え可能）。

リモートホストで操作する場合は、`.env` にリモート先を記載してください。

```text
REP2_REMOTE_HOST=rep2
REP2_REMOTE_PATH=docker-rep2
```

`REP2_REMOTE_HOST` と `REP2_REMOTE_PATH` の両方が設定されている場合にのみ `--remote` が機能します（未設定または片方のみの場合はエラーになります）。

`deploy` は down → build → (upload) → up → prune を一括実行するコマンドで、リモート設定の有無でローカル／リモートを自動判定します。

## おまけ

2026年1月の途中から今まで使っていたrep2でsubject.txtが取れなくなってしまったのでいろいろやるついでにいつのまにかコンテナ化も更新したいってことで作成。

ma8ma/2chproxy.plを使えばsubject.txtの件は解決するとは分かったのですが、書き込みが出来ないことの解決はハマりました。
決定的な原因がどれかわからないままですが、5chのread.cgiからの書き込みとなるべく近くなるようにfukumen/p2-phpは修正しています。

なお、fukumen/2chproxy.plの方はほぼma8ma/2chproxy.plから変わっていません。
docker-rep2への2chproxy.plのインテグレーションは廃止しています。既存のdataでproxy_useを「する」にしてproxy_host: 127.0.0.1 / proxy_port: 8080を設定している場合は、「しない」に変更してください。

5chでスレ読んでテストスレに書くぐらいの確認しかししていません。
スレ立てはホスト規制の表示まではいけたのでたぶん大丈夫？
