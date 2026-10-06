# rep2 テスト実行マニュアル

docker-rep2 で rep2 の単体テストを実行する手順と、テストファイルの作成方法を説明します。

## テスト実行コマンド

`build.py` の `agent-test` コマンドを使用します。

### 起動

`agent-test` の前に起動が必要です。また、rep2 本体のプログラムを更新している場合は起動の前にイメージ作成も必要です。

```bash
cd docker-rep2
# .env に暗号キーを設定（未設定の場合のみ。64桁の16進）
echo "REP2_AGENT_SECRET_KEY=$(openssl rand -hex 32)" >> .env

python3 build.py build        # ローカルイメージ作成（初回のみ）
python3 build.py agent-up     # エージェント環境を起動（設定適用まで自動）
```

起動後:

- rep2 の Web UI: http://127.0.0.1:10089 （`agent` / `rep2agent` でログイン可能）
- 通信ログ（mitmweb UI）: http://127.0.0.1:8081（パスワード: rep2agent）

### テスト実行

テストファイルはリポジトリの `test/` 配下に置きます（docker-rep2 から見て `../../../test`）。

```bash
python3 build.py agent-test ../../../test/agent_proxy_test.php
```

起動済みエージェント環境のコンテナ内（`/var/www/test` にマウント済み）で PHP テストを実行します。
設定適用済みの状態で実行されます。post.php の書き込みテストは実行できません（bbs.cgi への POST が 403 で遮断されるため）。
upload.php による画像アップロードも実行できません（外部アップローダ API への POST が 403 で遮断されるため）。
テストスクリプトへの引数は `--` を挟んで渡します。

全部入り（extra）イメージでのテストは `--extra` で起動し直してから実行します。

```bash
python3 build.py --extra agent-up
python3 build.py --extra agent-test ../../../test/my_test.php
```

### 終了

```bash
python3 build.py agent-down
```

データ（ユーザー設定・キャッシュ等）は tmpfs 上にあり、`agent-down` で消滅します。
次回の `agent-up` は毎回初期状態（新規ユーザー登録）から始まるため、過去の状態に影響されません。
通常の `rep2-data/` も参照しません。

## テスト専用 mitmproxy addon の追加

外部 API の応答をモックへ差し替えるなど、テスト専用の mitmproxy addon を追加するときは `agent/agent-compose.override.yml` を作成します。
build.py は agent 系コマンド（agent-up / agent-down / agent-logs / agent-exec / agent-test）でこ override ファイルがあると自動読み込みします。

テスト用 addon とテスト用 config.yaml は `test/` 配下に置き、
override ファイルでは volume mount により build.py でエージェント環境用に定義されている config.yaml を差し替えます。

```yaml
services:
  filter-proxy:
    volumes:
      - ../../../test/mitmproxy_addon/mitmproxy-test.yaml:/home/mitmproxy/.mitmproxy/config.yaml:ro
      - ../../../test/mitmproxy_addon/mock_hoge.py:/opt/test/mock_hoge.py:ro
```

テスト用 config.yaml の `scripts` では filter_proxy.py(bbs.cgi への POST を遮断する) と mock_hoge.py(テスト用 addon) を指定します。

```yaml
scripts:
  - /opt/agent/filter_proxy.py ← 
  - /opt/test/mock_hoge.py ←
```

テスト完了後、override ファイルは削除してください。
filter_proxy.py は POST 遮断の実現手段であり、テストのために編集しないでください。

## テストファイルの作成方法

テストファイル（PHP）は以下のルールで作成してください。

### 初期化
ファイルの先頭で `/var/www/init.php` を読み込むことで、rep2 のライブラリや設定が利用可能になります。

```php
<?php
define('P2_CLI_RUN', true);
require_once '/var/www/init.php';
// 以降、rep2の関数が利用可能
```

### 文字コード
rep2 本体に合わせて **Shift-JIS** で作成してください。

### 引数の渡し方
テストスクリプトに引数を渡す場合は、ファイルパスの後に `--` を挟んで記述します。

```bash
python3 build.py agent-test ../../../test/my_test.php -- --verbose --target=user
```
※ `--` を挟むことで、`build.py` 自身のオプションと区別できます。
