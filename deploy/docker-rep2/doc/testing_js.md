# rep2 JS 単体テスト実行マニュアル

docker-rep2 で rep2 の JS の単体テストを実行する手順を説明します。
spec はリポジトリの `test/` 配下に `*.spec.js` という名前で作成します。

## 本環境の構造

- テストはテストランナー **Vitest** で実行する。spec（テストコード）は `*.spec.js` という名前のファイル
- spec は **jsdom**（ブラウザを模擬する純 JS 実装）上の window で実行する。ブラウザは起動しない
- テスト対象の本番 .js は CP932 → UTF-8 にデコードしてから、スクリプトとして実行する
- `XMLHttpRequest` と `fetch` は Fake 実装に差し替え、テストから応答を制御する
- node と npm 依存は実行時にコンテナ内へ自動構築する。環境は `agent-down` で消滅し、次回実行時に自動再構築

## テスト実行コマンド

`build.py` の `agent-jstest` コマンドを使用します。

### 起動・終了

エージェント環境の起動・終了の手順は、rep2 テスト実行マニュアル (testing.md) と同じです。

### テスト実行

`agent-jstest` の前に起動が必要です。

```bash
python3 build.py agent-jstest                                       # 全 spec を実行
python3 build.py agent-jstest -- /var/www/test/<機能>/xxx.spec.js   # 特定 spec を実行
```

node 環境が未構築の場合（agent-down 直後など）は、実行時に自動でセットアップされます
（セットアップにはコンテナからのインターネットアクセスが必要です）。

全部入り（extra）イメージでのテストは `--extra` で起動し直してから実行します。

```bash
python3 build.py --extra agent-up
python3 build.py --extra agent-jstest
```

環境は ephemeral（tmpfs）で、データは `agent-down` で消滅します。
node 環境も消滅しますが、次回の `agent-jstest` 実行時に自動で再構築されます。

## spec の作成方法

spec（JavaScript）は以下のルールで作成してください。

### 文字コード

spec は **UTF-8** で作成します。テスト対象の本番 .js は CP932 のため、テスト側でデコードしてから実行します（ヘルパーの `loadInstrumentedCp932` を使用）。

### config の注入

本番では PHP が `<機能名>_config` という変数で設定値（JSON）を注入します。テストでは spec 側で `window.<機能名>_config` に fixture を設定してから対象 .js を評価します。

### 通信のモック

Fake XMLHttpRequest の応答は handler 関数で制御します（ヘルパーの `installFakeXHR` を使用）。handler はリクエストから `status` / `body` を返し、`null` を返すとネットワークエラー相当になります。jQuery の `$.get` / `$.ajax` も Fake XMLHttpRequest 経由で動作します。jQuery はヘルパーの `loadJquery` でリポジトリ同梱のものをロードします。

### カバレッジ

対象 .js はヘルパーの `loadInstrumentedCp932` で instrument して実行し、`getCoverageSummary` で `window.__coverage__` の数値を取得します。

ヘルパーは `agent/js-test/helpers/` 配下にあります（コンテナ内 `/var/www/js-test/helpers/`）。

## 制限

post.php の書き込みテストは実行できません（bbs.cgi への POST が 403 で遮断されるため）。
upload.php による画像アップロードも実行できません（外部アップローダ API への POST が 403 で遮断されるため）。
