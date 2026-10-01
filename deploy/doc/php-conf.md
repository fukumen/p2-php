# rep2 の PHP 設定

このドキュメントは rep2 の PHP 設定（ini 値と php-fpm.conf 値）を定義する仕様書である。

## 概要

rep2 の PHP 設定は次の 2 系統で管理する。

| 系統 | 内容 | 置き場所 |
| --- | --- | ---- |
| php.ini 系 | PHP の動作値（memory_limit ほかの ini ディレクティブ） | docker: conf.d の rep2-php.ini / allinone linux・macos: prefix 固有の conf.d（`<prefix>/etc/php/conf.d`）の rep2-php.ini / windows: conf/php.ini |
| php-fpm.conf 系 | fpm プロセス設定（listen / pm ほか） | docker: /etc/rep2 配下の rep2-php-fpm.conf / allinone linux・macos: rep2-php-fpm.conf（パッケージ prefix 配下） |

設定値は PHP 本体の既定値と異なる値のみを定義し、既定値と同じ値を自前で定義しない。
既定値と異なる値だけを明示定義し、根拠をこの文書の「設定値の一覧」に要約として記録する。

## 対象ファイルと適用経路

| ファイル | docker-rep2 | rep2-allinone linux/macos | rep2-allinone windows |
| ---- | ----------- | ------------------------- | --------------------- |
| rep2 固定の ini | `rootfs/usr/local/etc/php/conf.d/rep2-php.ini` | `<prefix>/etc/php/conf.d/rep2-php.ini` | `conf/php.ini`（`php-cgi -c` で指定） |
| rep2 固定の fpm 設定 | `/etc/rep2/rep2-php-fpm.conf` | `<prefix>/etc/rep2-php-fpm.conf` | なし（php-cgi のため） |
| ユーザー上書き ini | `php-local.ini` を `conf.d/z-php-local.ini` としてマウント | `/etc/rep2-allinone/php-local.ini` | php.ini を直接編集 |
| ユーザー上書き fpm 設定 | `php-fpm-local.conf` を `/etc/rep2/php-fpm.d/z-php-fpm-local.conf` としてマウント | linux: `/etc/rep2-allinone/php-fpm-local.conf` / macos: `$(brew --prefix)/etc/rep2-allinone/php-fpm-local.conf` | 存在しない（php-cgi のため） |

補足:

- `<prefix>` はパッケージ本体のインストール先（linux: `/opt/rep2-allinone`、macos: `$(brew --prefix)/opt/rep2-allinone`）。ユーザー上書きファイルの置き場所は
  パッケージ prefix 配下ではなく linux: `/etc/rep2-allinone`、macos: `$(brew --prefix)/etc/rep2-allinone` である
- rep2-allinone の scan-dir は PHP バイナリに焼き込まれている（linux: `/opt/rep2-allinone/etc/php/conf.d`、
  macos arm64: `/opt/homebrew/opt/rep2-allinone/etc/php/conf.d`、macos x64: `/usr/local/opt/rep2-allinone/etc/php/conf.d`）
- rep2 固定のファイル（rep2-php.ini / rep2-php-fpm.conf）はプログラム本体扱いで、パッケージ更新時に常時置き換わる。編集するのは -local の付いたファイルに限定する
- docker の fastcgi.logging は公式 php イメージ同梱の docker-fpm.ini が Off に設定する

## 設定値の一覧

php.ini 系の設定値。既定値は PHP 8.5.11 時点の値である。

| 項目 | 既定値 | docker | linux/macos | windows | 根拠（要約） |
| --- | --- | ------ | ----------- | ------- | ------ |
| memory_limit | 128M | 512M | 512M | 512M | IC2 が 128M を超えるため、十分大きな 512M とする |
| post_max_size | 8M | 128M | 128M | 128M | IC2 は上限の expack.ic2.source.maxsize のガードがあり、十分大きな 128M とする |
| upload_max_filesize | 2M | 128M | 128M | 128M | 同上（dat アップロードも依存） |
| display_errors | On | Off | Off | Off | 画面へのエラー露出を止める（php.ini-production 相当） |
| display_startup_errors | On | Off | Off | Off | display_errors と一体で管理 |
| log_errors | Off | On | On | On | warning をログに残す（php.ini-production 相当） |
| fastcgi.logging | On | （docker-fpm.ini が Off） | Off | Off | ログの二重記録の抑止。Off でも fpm ログ / stderr への記録は維持される |
| opcache.jit | disable | tracing | tracing | tracing | 本体に JIT 依存の実装はない。functionよりはtracingの方が無難 |
| opcache.enable_cli | Off | 1 | 1 | 1 | CLI でもキャッシュ有効にする |
| opcache.enable | On | 定義しない | 定義しない | 定義しない | 既定値で問題ない |
| opcache.jit_buffer_size | 64M | 定義しない | 定義しない | 定義しない | 既定値で問題ない |
| max_execution_time | 30 | 定義しない | 定義しない | 定義しない | conf.inc.php の `set_time_limit(60)` が実効値。CLI はこの ini を 0 にハードコードし、conf.inc.php では set_time_limit をスキップする |
| date.timezone | UTC | 定義しない | 定義しない | 定義しない | conf.inc.php の `date_default_timezone_set('Asia/Tokyo')` が実効値 |
| mbstring.* | （言語別） | 定義しない | 定義しない | 定義しない | `mb_detect_encoding` の候補を常にコード側で明示するため language / detect_order に依存しない |
| variables_order | EGPCS | 定義しない | 定義しない | 定義しない | 既定値で問題ない |
| extension_dir / extension | （なし） | （公式イメージの docker-php-ext 生成物） | 不要（静的リンク組込） | ext / 12 本 | windows のみ指定が必要 |

php-fpm.conf 系（rep2-php-fpm.conf）の設定値。既定値は 8.5.11 時点の値である。

| 項目 | 既定値 | docker | linux | macos | 根拠（要約） |
| --- | --- | ------ | ----- | ----- | ------ |
| error_log（[global]） | 未設定 | /proc/self/fd/2 | /var/lib/rep2-allinone/php-fpm.log | @@ERROR_LOG_PATH@@（post_install で php-fpm.log のパスに置換） | linux はデータ領域のファイルに出力する。stderr 経路は systemd 下では使えず（journald の socket 接続により fpm が open できず起動不能）、syslog は catch_workers_output と両立しないため不採用。ローテートは起動スクリプトが行う（上記のとおり） |
| log_limit（[global]） | 1024 | 8192 | 8192 | 8192 | worker 出力集約（catch_workers_output）とセット。log_limit 超過のログ行は「...」付きで切詰められるため、バックトレース級の長い行を保持するには 8192 が必要 |
| user / group | なし（マスター実行ユーザーのまま） | www-data / www-data | 未設定（service が rep2 で実行） | 未設定（起動スクリプトが brew 所有ユーザーで実行） | docker は root 起動のため fpm の必須要件として明示。linux は systemd unit の `User=rep2`、macos は起動スクリプトの権限降格（root 時は Homebrew の所有ユーザーに落とす）が実効ユーザーを決める |
| listen | なし（未設定なら起動失敗の必須項目） | 127.0.0.1:9000 | 127.0.0.1:9000 | 127.0.0.1:9000 | Caddy と同じホスト内の FastCGI 接続 |
| pm / pm.max_children | なし（未設定なら起動失敗の必須項目） | dynamic / 5 | dynamic / 5 | dynamic / 5 | docker 公式イメージの www.conf と同一構成 |
| pm.start_servers | 未設定時は計算値（(min_spare + max_spare) / 2 = 2） | 2 | 2 | 2 | 同上 |
| pm.min_spare_servers | なし（dynamic 時は必須） | 1 | 1 | 1 | 同上 |
| pm.max_spare_servers | なし（dynamic 時は必須） | 3 | 3 | 3 | 同上 |
| catch_workers_output | no | yes | yes | yes | worker 内 PHP エラー（log_errors = On）は worker の stderr に出るため、これを fpm ログへ集約しないとどこにも記録されない。log_errors = On を実効的にする要件 |
| decorate_workers_output | yes | no | no | no | 集約時の `[pool www] child N said into …` 装飾を外し、PHP エラー行を素の形式で記録する |
| clear_env | yes | no | no | no | rep2 は SECRET_KEY を getenv() で読むため環境変数を worker に届ける必要がある |
| include | なし | /etc/rep2/php-fpm.d/*.conf | /etc/rep2-allinone/php-fpm-local.conf | @@CONF_DIR@@/php-fpm-local.conf（post_install で置換） | ユーザー上書き用。docker は glob（ダミーの z-php-fpm-local.conf をイメージに同梱し、マウント時は置き換わる）。ファイル不在時は WARNING のみで起動継続 |
| daemonize（[global]） | yes | 定義しない | 定義しない | 定義しない | conf では未設定。docker は supervisord が `-F` で起動、linux / macos は起動スクリプトが `-F` で起動するため、定義しない |
| access.log | なし（アクセスログ無効） | 定義しない | 定義しない | 定義しない | 既定書式にメモリ等は含まれず Caddy ログと重複するため。メモリ消費の確認が必要な場合は php-fpm-local.conf で access.log と access.format をセット定義して opt-in（既定書式はメモリを含まないため format なしの access.log 追加だけでは意味がない） |

## ログの行き先

PHP のエラーログ（log_errors = On の出力）は fpm 環境では catch_workers_output = yes により
fpm の error_log へ集約されるため、ログの実体は各形態の次の場所に出る。
サービスの起動ログ（起動スクリプト自身の出力）は PHP ログとは別の場所に出る。

| 形態 | PHP ログ（fpm ログ・worker 出力の集約先） | サービスの起動ログ |
| ---- | ------------------------------------------ | ------------------ |
| docker | docker logs | docker logs |
| linux | /var/lib/rep2-allinone/php-fpm.log | journal（`journalctl -u rep2-allinone`） |
| macos | $(brew --prefix)/var/lib/rep2-allinone/php-fpm.log | $(brew --prefix)/var/lib/rep2-allinone/rep2-allinone.log |

php-fpm.log は起動スクリプトが起動時に php-fpm.log.1 へ mv する 2 世代方式の
ローテートを行う（linux / macos とも同一。最大で現行 + .1 の 2 ファイル）。

## PHP バージョン更新時の注意

既定値は PHP のバージョンで変わる可能性がある。
PHP を更新したときは「設定値の一覧」の既定値列と実効値を突き合わせて再確認すること。
確認は次の 2 系統で行う。

- 実測（実行バイナリ・イメージ）: `php -n -i` で ini を一切読まない状態の既定値を確認、
  `php --ini` で読み込まれた ini ファイルを確認、`php -i` / `php-fpm -i` で実行形態ごとの
  実効値を確認する。比較対象の公式イメージは最新に pull してから測る
- PHP 本体ソース: ini エントリの既定値は `STD_PHP_INI_ENTRY` / `PHP_INI_ENTRY` を grep して
  確認する。core（memory_limit ほか）は `main/main.c`、date は `ext/date/php_date.c`、
  mbstring は `ext/mbstring/mbstring.c`、opcache は `ext/opcache/zend_accelerator_module.c`、
  fpm 固有（fastcgi.logging）は `sapi/fpm/fpm/fpm_main.c` に定義される

fpm conf（php-fpm.conf 系）も同様に再確認する。php-fpm.conf の値は ini 系の実測方法には
現れない（別系統のため）ため、`php-fpm -tt` で確認する。

- 実測: `php-fpm -tt` で読み込まれた conf ファイルと最終 conf の全値を確認する。
  conf に書かれていない項目の実効値（clear_env / decorate_workers_output / daemonize ほか）
  は php-fpm のコード既定であり、これも `-tt` の出力に現れる
- PHP 本体ソース: fpm conf のコード既定は `sapi/fpm/fpm/fpm_conf.c`（初期化子と
  `ini_value_parser` の登録）を確認する
