# spc-build

rep2-allinone 向け static-php のローカルビルド検証ツール。

`fukumen/static-php-cli` (`crazywhalecc/static-php-cli` の fork) の `build-rep2-unix.yml` workflow と同一の
手順・フラグでビルドし、成果物をローカルで検証する。GitHub Actions を実行する前に
パッチや拡張構成の変更をローカルで再現確認するためのものであり、Releases への公開は
workflow のみが行いこのスクリプトでは公開しない。

なお、`build-rep2-unix.yml` workflow は https://github.com/static-php/hosted/blob/master/.github/workflows/v3-php-bin-unix.yml を参考に作成。

## 前提

- static-php が p2-php と並ぶ位置（build.sh から見て `../../../../static-php-cli`）にあること。
  `SPC_REPO` 環境変数で別の場所を指定できる
- 情報の単一ソースは workflow ファイル。EXTENSIONS と PHP バージョンの既定値は
  `.github/workflows/build-rep2-unix.yml` から抽出される（ローカル側に定数を持たない）
- static-php の `rep2` ブランチの `patches/` は workflow・このスクリプトの両方で checkout 直後に
  適用されるため、パッチの効き目をローカルで検証できる。スクリプトは `git apply --check`
  で適用可否を判定し、適用済み（逆適用が通る）ならスキップ、どちらも通らなければエラー停止
  する。適用したパッチはスクリプト終了時に逆適用して元に戻すため、作業ツリーに
  パッチ適用後の状態は残らない。パッチをコミットするのは `static-php-cli` 側の
  `patches/` ファイルのみ（ソースファイル自体は常にパッチ適用前の状態を保つ）

## rep2-allinone 向け static-php の運用方針

`fukumen/static-php-cli`（`crazywhalecc/static-php-cli` の fork）を rep2-allinone 向け static-php として運用する。
upstream の `v3` をベースにした `rep2` ブランチで作業し、ビルド内容（拡張セット・PHP バージョン等）の
単一ソースは `rep2` ブランチの `.github/workflows/build-rep2-unix.yml` である。

| 項目 | 運用 |
|---|---|
| 対象 | PHP の正規リリースの最新バージョン（workflow の `php-version` input で指定。現時点では 8.5 系）。linux / macOS × x86_64 / aarch64 の 4 組み合わせ。Windows は PHP 公式バイナリをそのまま使うため対象外 |
| ブランチ構成 | `rep2`（既定ブランチ）に `build-rep2-unix.yml` と `patches/` を置く。`v3` は upstream と完全一致を保つ追従専用ブランチ |
| 上流追従 | Sync fork ボタンは使わない（既定ブランチが `rep2` のため Discard commits で自前コミットを破棄する恐れがある）。`git push origin upstream/v3:v3` で `v3` を更新してから `rep2` へ `git merge v3` する。workflow と `patches/` は `rep2` にのみ存在するためマージ衝突は原則起きず、patch の `git apply` 失敗で初めて影響を検知できる |
| 上流由来 workflow | 上流追従で新規 workflow が追加された場合は都度無効化する |
| ビルド失敗時 | 軽微なビルドエラーは `rep2` ブランチの `patches/NNNN-<概要>.patch` に置く（workflow が checkout 直後に適用する）。構造的な対処は `rep2` から一時ブランチ（例: `v3-rep2-hotfix`）を切り、workflow の `ref` input で指定してビルドする。依存ライブラリ側の失敗は `--dl-ignore-cache` や spc のバージョン指定で切り分け、恒久対処は patch 化する。上流修正が取り込まれたら `v3` を更新して `rep2` へマージし、patch / 一時ブランチを撤去する（下記参照） |

### patch と一時ブランチの撤去

上流修正を `v3` → `rep2` へマージして取り込んだ後、不要になった patch と一時ブランチは
次の方法で撤去する。これにより `v3` と `rep2` の diff は workflow と `patches/` の差分のみに保たれる

- patch の追加は `patches/` 配下のみを含むコミットに限定する。ソースファイルへの適用結果を
  コミットしない（ソースは常にパッチ適用前の状態を保つ。これが崩れると次の `v3` マージで
  衝突が起きる）
- patch の削除は追加コミットを `git revert` して打ち消す（削除対象の取り残しを防ぎ、履歴に
  因果を残すため）。revert コミットには上流のどの修正で解消したかを記載する
- 一時ブランチはローカルを `git branch -D`、リモートを `git push origin --delete` で削除する。
  workflow の `ref` input で参照できなくなるため、上流修正のマージ後に実施する

## 使い方

```bash
# linux バイナリをビルドして検証（docker 内で hosted workflow と同一手順を実行）
./build.sh

# macOS バイナリをビルドして検証（macOS ホスト上で PHP 8.4+ / composer が必要）
./build.sh --host

# ビルド済み成果物の検証のみ（docker / PHP パッケージ不要。静的バイナリは依存ゼロ）
./build.sh --verify-only

# 使い方を表示（オプションと環境変数の一覧。--help も同じ）
./build.sh -h

# UPX 圧縮を無効化
SPC_UPX=0 ./build.sh

# PHP のフルバージョン固定（既定は workflow 抽出値の系列最新）
SPC_PHP_VERSION=8.5.9 ./build.sh
```

### 環境変数

| 変数 | 既定 | 説明 |
|---|---|---|
| `SPC_REPO` | `../../../../static-php-cli`（build.sh から相対解決） | static-php のパス |
| `SPC_REF` | `rep2` | 使用するブランチ / タグ |
| `SPC_PHP_VERSION` | workflow 抽出値 | PHP バージョン。フルバージョン（例: 8.5.9）指定で完全固定 |
| `SPC_EXTENSIONS` | workflow 抽出値 | 拡張リストの上書き（パッチ検証時の増減実験用） |
| `SPC_UPX` | workflow 抽出値 | UPX 圧縮の上書き（`1` で有効、`0` で無効） |
| `SPC_DOCKER_IMAGE` | `ubuntu:24.04` | docker モードのベースイメージ |
| `SPC_VERIFY_VERSION` | （未設定） | `--verify-only` 実行時に検証対象を特定バージョンへ絞る（例: `8.5.9`）。dist に複数バージョンの成果物が混在しているときに使用 |
| `SPC_VERIFY_TAG` | （未設定） | `--verify-only` 実行時に検証対象を特定タグへ絞る（例: `26ext-suggests`）。`SPC_TAG` でビルドした成果物を後から検証するときに使用 |
| `SPC_EXTRA_BUILD_FLAGS` | （未設定） | `spc build` への追加フラグ（検証実験用。例: `--build-micro`。`--with-suggests` は既定で付与済み） |
| `SPC_TAG` | （未設定） | 成果物の tar.gz ファイル名に `-<タグ>` を追加（実験ビルドの区別用。例: `26ext-suggests` → `php-8.5.10-cli-linux-x86_64-26ext-suggests.tar.gz`）。未指定なら workflow と同一の命名。ビルドモード実行時の検証はこのタグの成果物のみを対象とする |

環境変数の分類:

- ビルド内容の制御: `SPC_EXTENSIONS` / `SPC_PHP_VERSION` / `SPC_UPX` / `SPC_EXTRA_BUILD_FLAGS` / `SPC_TAG`
- 検証（verify）の制御: `SPC_VERIFY_VERSION` / `SPC_VERIFY_TAG`
- 環境・参照先: `SPC_REPO` / `SPC_REF` / `SPC_DOCKER_IMAGE`

### モードの対応表

| モード | OS | 成果物 | 必要なもの |
|---|---|---|---|
| docker（既定） | linux x86_64 / aarch64 | cli + fpm の tar.gz | docker のみ（コンテナ内で PHP 8.4 / composer を導入） |
| host | macOS x86_64 / aarch64 | cli + fpm の tar.gz | macOS 上で PHP 8.4+ / composer（brew） |
| `--verify-only` | linux / macOS（自ホスト） | （検証のみ） | なし。静的バイナリは依存ゼロ |

補足:

- docker モードは `static-php/hosted` の workflow（v3-php-bin-unix.yml）と同一方式。ubuntu:24.04 コンテナ内で
  `bin/spc` を直実行し、`doctor --auto-fix` が zig toolchain とビルド依存を解決する
- `spc-alpine-docker`（`crazywhalecc/static-php-cli` の workflow （build-unix.yml）は別流儀のため使わない
- ローカルで検証できるのは自ホスト arch の 1 組み合わせのみ。残りは CI での確認になる

## 成果物と検証内容

`work/dist/` に公式 common と同一命名の tar.gz が出力される:

```
php-<ver>-cli-linux-x86_64.tar.gz
php-<ver>-fpm-linux-x86_64.tar.gz
build-manifest-linux-x86_64.json
```

検証対象は dist/ の tar.gz のうち自ホストの OS / arch と一致するものだけ
（別プラットフォーム分は `skip (host mismatch)` で無視する。mac に linux 分が
混在していても実行されない。一致するものが 1 つも無い場合はエラーになる）。

検証は 2 段階:

1. `php -m` と workflow 抽出値の EXTENSIONS を照合。spc パッケージ名と表示名の対応
   （pdo → PDO / phar → Phar / mbregex → mbstring 内蔵）を変換して照合する
2. 拡張ごとの最小動作テスト（`ext-function-test.php`）。EXTENSIONS 由来で動的に対象を選択し、
   拡張ごとに代表関数を 1 つ実行する（PDO sqlite 接続、gd_info、openssl 暗号化、bcadd、
   mb_regex_encoding、Normalizer NFKC など）。upstream に成果物テストスクリプトは存在しないため独自実装

どちらの検証も EXTENSIONS に列挙された拡張のみを対象とする（`in_array` ガード）。
EXTENSIONS に無い拡張がバイナリに含まれていても検査されず失敗もしない（合否は
「EXTENSIONS ⊆ php -m」の単方向判定）。そのため `SPC_EXTENSIONS` 実験で intl 等を
追加・削除しても、workflow 側の EXTENSIONS が一致しない限り通常の検証には影響しない

検証対象は自ホストの OS / arch に一致する tar.gz すべて。dist に複数バージョンの
成果物（例: 8.5.9 と 8.5.10）が混在している場合、EXTENSIONS が一致しない旧版で
検証が失敗するため、`SPC_VERIFY_VERSION=8.5.9 ./build.sh --verify-only` のように
バージョンを明示して対象を絞ること

## ファイル構成

```
spc-build/
  build.sh              # 本体
  README.md             # このファイル
  work/                 # 作業ディレクトリ（.gitignore 対象。再生可能な成果物のみ置く）
    dist/               # tar.gz 出力先
    ext-function-test.php  # 検証用 PHP スクリプト（build.sh が生成）
```

## macOS 向けの補足

- 必要なのはビルドツール類（make / bison / re2c / flex 等）と spc 実行用の php / composer
- ビルドツールの不足は `build.sh` や `doctor --auto-fix` が `brew install` で自動導入するため、
  ホストを汚したくない場合は VM で実行すること


## PHP CLIサイズ計測(2026/9/22)

| プラットフォーム | ビルド         | PHP    | 拡張数 | UPX  | サイズ   | 備考                                |
| ---------------- | -------------- | ------ | ------ | ---- | -------- | ----------------------------------- |
| linux-x86_64     | 公式common(v3) | 8.5.10 | 41     | 有り | 14.20 MB |                                     |
| linux-x86_64     | ローカル       | 8.5.10 | 41     | 有り | 14.21 MB | openssl 3.6.3 / libxml2 2.15.3 ピン |
| linux-x86_64     | ローカル       | 8.5.10 | 26     | 有り | 13.49 MB |                                     |
| linux-x86_64     | ローカル       | 8.5.10 | 27     | 有り | 25.23 MB | 26 拡張に intl を追加               |
| linux-aarch64    | 公式rep2ビルド | 8.5.10 | 27     | 有り | 25.88 MB |                                     |
| macos-aarch64    | 公式common(v3) | 8.5.9  | 41     | 無し | 44.1 MB  |                                     |
| macos-aarch64    | ローカル       | 8.5.9  | 41     | 無し | 53.6 MB  | openssl 3.6.3 / libxml2 2.15.3 ピン |
| macos-aarch64    | ローカル       | 8.5.10 | 26     | 無し | 51.12 MB |                                     |
| macos-aarch64    | ローカル       | 8.5.10 | 27     | 無し | 78.38 MB | 26 拡張に intl を追加               |
| macos-aarch64    | 公式rep2ビルド | 8.5.10 | 27     | 無し | 76.99 MB |                                     |

**決定事項** common には intl が入っていない。rep2 では intl 代替のフォールバック処理があるが、intl を追加した 27 拡張とする。intl の有無による rep2 の動作の違いは [doc/README-intl.md](../../../doc/README-intl.md) を参照。

26 拡張は common に含まれていて rep2 が使用している拡張機能。

macos のGitHub Actionsのビルドとローカルビルドの差は詳細不明。可能性が高いのは SDK バージョン。
