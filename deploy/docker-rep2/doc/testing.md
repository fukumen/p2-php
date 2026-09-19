# rep2 テスト実行マニュアル

docker-rep2 環境の php-cli を利用して rep2 の関数単位テストをワンショットで実行する方法について説明します。

## 1. テスト実行コマンド

`build.py` の `test` コマンドを使用します。

### 事前設定

`test` コマンドを実行するには、`.env` にテストファイルの置き場所（コンテキスト）を記載してください。

```text
REP2_TEST_CONTEXT=../test
```

`REP2_TEST_CONTEXT` は docker-rep2 ディレクトリからの相対パス（絶対パスでも可）です。未設定のまま実行するとエラーになります。

```bash
cd docker-rep2
python3 build.py test ../test/my_test.php
```

### コマンドの挙動
1. 指定されたテストファイルをコンテナ内の `/tmp/my_test.php` にボリュームマウントします。
2. コンテナを `run --rm` モードで起動し、`php /tmp/my_test.php` を実行します。
3. 実行完了後、コンテナは自動的に削除されます。

## 2. テストファイルの作成方法

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

## 3. 高度な使い方

### 引数の渡し方
テストスクリプトに引数を渡す場合は、ファイルパスの後に続けて記述します。

```bash
python3 build.py test ../test/my_test.php -- --verbose --target=user
```
※ `--` を挟むことで、`build.py` 自身のオプションと区別できます。
