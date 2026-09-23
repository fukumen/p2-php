#!/usr/bin/env bash
# rep2-allinone 向け static-php ビルドのローカル検証スクリプト。
# workflow (.github/workflows/build-rep2-unix.yml) と同一の手順・フラグでビルドし、
# 成果物を work/dist/ に出力する。Releases への公開は workflow のみが行う。
# 使い方は -h / --help で表示する。
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORK_DIR="$SCRIPT_DIR/work"
DIST_DIR="$WORK_DIR/dist"
VERIFY_VERSION="${SPC_VERIFY_VERSION:-}"   # --verify-only 時のバージョン絞り込み（任意）
TAG="${SPC_TAG:-}"   # 成果物ファイル名への追加タグ（実験ビルドの区別用。例: 41ext-suggests）
VERIFY_TAG="${SPC_VERIFY_TAG:-}"   # --verify-only 時のタグ絞り込み（任意。SPC_TAG に対応）

DEFAULT_REPO="$(cd "$SCRIPT_DIR/../../../../static-php-cli" 2>/dev/null && pwd || true)"
SPC_REPO="${SPC_REPO:-$DEFAULT_REPO}"
SPC_REF="${SPC_REF:-rep2}"
DOCKER_IMAGE="${SPC_DOCKER_IMAGE:-ubuntu:24.04}"
EXTRA_BUILD_FLAGS="${SPC_EXTRA_BUILD_FLAGS:-}"   # spc build への追加フラグ（--with-suggests 等、検証実験用）

log() { printf '\033[1;34m[build.sh]\033[0m %s\n' "$*"; }
die() { printf '\033[1;31m[build.sh]\033[0m %s\n' "$*" >&2; exit 1; }
usage() {
  cat <<'EOU'
rep2-allinone 向け static-php ビルドのローカル検証スクリプト。
workflow (.github/workflows/build-rep2-unix.yml) と同一の手順・フラグでビルドし、
成果物を work/dist/ に出力する。詳細は spc-build/README.md を参照。

使い方:
  spc-build/build.sh [オプション]

オプション:
  -h, --help      この使い方を表示
  --host          host モード（macOS 上で実行）でビルド + 検証（既定: docker モード）
  --verify-only   ビルド済み work/dist/ の検証のみ（docker / PHP パッケージ不要。
                  静的バイナリは依存ゼロのため任意の linux ホストで実行可能）

環境変数（既定値は workflow からの抽出値）:
  SPC_REPO               static-php-cli のパス（既定: ../../../../static-php-cli）
  SPC_REF                使用するブランチ / タグ（既定: rep2）
  SPC_PHP_VERSION        PHP フルバージョン固定（例: 8.5.9。既定: 系列最新）
  SPC_EXTENSIONS         拡張リストの上書き（パッチ検証時の増減実験用）
  SPC_UPX                UPX 圧縮の上書き（1 で有効、0 で無効。既定: workflow の matrix.upx）
  SPC_EXTRA_BUILD_FLAGS  spc build への追加フラグ（--with-suggests 等の検証実験用）
  SPC_TAG                成果物の tar.gz ファイル名に -<タグ> を追加（実験ビルドの区別用）
  SPC_DOCKER_IMAGE       docker モードのベースイメージ（既定: ubuntu:24.04）
  SPC_VERIFY_VERSION     --verify-only 時のバージョン絞り込み（例: 8.5.9）
  SPC_VERIFY_TAG         --verify-only 時のタグ絞り込み（SPC_TAG に対応）
EOU
}
MODE="docker"
VERIFY_ONLY=false
while [ $# -gt 0 ]; do
  case "$1" in
    -h|--help) usage; exit 0 ;;
    --host)  MODE="host" ;;
    --verify-only) VERIFY_ONLY=true ;;
    *) usage >&2; die "未知のオプション: $1" ;;
  esac
  shift
done

# ---------------------------------------------------------------
# workflow からの値抽出（情報の単一ソース）
# ---------------------------------------------------------------
WORKFLOW_FILE="$SPC_REPO/.github/workflows/build-rep2-unix.yml"
[ -f "$WORKFLOW_FILE" ] || die "workflow が見つかりません: $WORKFLOW_FILE (SPC_REPO/SPC_REF を確認)"

extract_env() {
  # env: EXTENSIONS: ... の 1 行を抽出（workflow 側で 1 行に固定していること）
  sed -n 's/^  EXTENSIONS:[[:space:]]*//p' "$WORKFLOW_FILE" | head -n1
}
extract_default_php() {
  sed -n 's/^      php-version:.*default:[[:space:]]*"\([0-9.]*\)".*/\1/p' "$WORKFLOW_FILE" | head -n1
}
# UPX は workflow の matrix.upx（linux: --with-upx-pack / macOS: 空）を単一ソースとする。
# 実行中プラットフォームの行を抽出し、SPC_UPX（0/1）でのみ上書きできる
extract_workflow_upx() {
  local os arch flag
  os="$(uname -s)"; arch="$(uname -m)"
  local pattern
  case "$os:$arch" in
    Linux:x86_64)  pattern='runner: ubuntu-24.04,' ;;
    Linux:aarch64) pattern='runner: ubuntu-24.04-arm,' ;;
    Darwin:arm64)  pattern='runner: macos-15,' ;;
    Darwin:x86_64) pattern='runner: macos-15-intel,' ;;
    *) return 1 ;;
  esac
  flag="$(grep -F -- "$pattern" "$WORKFLOW_FILE" | sed -n 's/.*upx: "\([^"]*\)".*/\1/p' | head -n1)"
  [ -n "$flag" ] && echo "$flag"
}

EXTENSIONS="${SPC_EXTENSIONS:-$(extract_env)}"
WORKFLOW_PHP="$(extract_default_php)"
[ -n "$EXTENSIONS" ] || die "EXTENSIONS を workflow から抽出できませんでした"
[ -n "$WORKFLOW_PHP" ] || die "php-version 既定値を workflow から抽出できませんでした"
PHP_VERSION_INPUT="${SPC_PHP_VERSION:-$WORKFLOW_PHP}"

if [ -n "${SPC_UPX:-}" ]; then
  case "$SPC_UPX" in
    1) UPX_FLAG="--with-upx-pack" ;;
    0) UPX_FLAG="" ;;
    *) die "SPC_UPX は 0 または 1 で指定してください: $SPC_UPX" ;;
  esac
else
  UPX_FLAG="$(extract_workflow_upx || true)"
  [ -n "${UPX_FLAG:-}" ] || UPX_FLAG=""
fi
log "SPC_REPO: $SPC_REPO (ref: $SPC_REF)"
log "EXTENSIONS: $EXTENSIONS"
log "PHP: $PHP_VERSION_INPUT  UPX: ${UPX_FLAG:-none}  EXTRA_FLAGS: ${EXTRA_BUILD_FLAGS:-none}  MODE: $MODE"

# ---------------------------------------------------------------
# patches/ を checkout に適用（workflow の Apply local patches と同一。
# 何度実行しても同じ状態になる）
# ---------------------------------------------------------------
# 終了時に逆適用して作業ツリーを元に戻すパッチを記録する。対象ファイルのうち 1 つでも
# 開始時に dirty だった場合はそのパッチを記録しない（ユーザーの意図的な変更を
# 取り消してしまうため）。CI の checkout は使い捨てのためこの後始末は不要だが、
# ローカルの作業ツリーは維持されるため必要になる。
PATCH_RESTORE=()
shopt -s nullglob
for p in "$SPC_REPO"/patches/*.patch; do
  if git -C "$SPC_REPO" apply --check "$p" 2>/dev/null; then
    log "Applying $(basename "$p")"
    git -C "$SPC_REPO" apply "$p"
    safe=true
    while IFS= read -r f; do
      [ -n "$(git -C "$SPC_REPO" status --porcelain -- "$f" 2>/dev/null)" ] || continue
      # 適用直後はパッチ差分自体が dirty になる。パッチ差分以外の dirty かは
      # 逆適用が通るかで判定する（通らない = ユーザー変更が混在）
      if ! git -C "$SPC_REPO" apply --reverse --check "$p" 2>/dev/null; then
        safe=false
        break
      fi
    done < <(sed -n 's|^--- a/||p' "$p" | sort -u)
    $safe && PATCH_RESTORE+=("$p")
  elif git -C "$SPC_REPO" apply --reverse --check "$p" 2>/dev/null; then
    log "Already applied: $(basename "$p")"
  else
    die "パッチを適用できません: $p（ソースと不整合。git -C $SPC_REPO status を確認）"
  fi
done
shopt -u nullglob

# composer update は依存を再解決して composer.lock を書き換える（ビルドの副産物）。
# 開始時に clean だった場合のみ終了時に復元して作業ツリーを clean に保つ。
# 開始時に既に dirty だった場合は意図的な変更の可能性があるため触らない。
LOCK_DIRTY_AT_START=1
LOCK_STATUS="$(git -C "$SPC_REPO" status --porcelain -- composer.lock 2>/dev/null || true)"
if [ -z "$LOCK_STATUS" ]; then
  LOCK_DIRTY_AT_START=0
fi
restore_repo_state() {
  if [ "$LOCK_DIRTY_AT_START" = 0 ]; then
    git -C "$SPC_REPO" checkout -q -- composer.lock 2>/dev/null || true
  fi
  # ビルドで適用したパッチを逆適用し、作業ツリーをパッチ適用前の状態に戻す
  if [ "${#PATCH_RESTORE[@]}" -gt 0 ]; then
    local restored=0
    for p in "${PATCH_RESTORE[@]}"; do
      if git -C "$SPC_REPO" apply --reverse --check "$p" 2>/dev/null; then
        git -C "$SPC_REPO" apply --reverse "$p" && restored=$((restored + 1))
      fi
    done
    [ "$restored" -gt 0 ] && log "パッチ適用を取り消して作業ツリーを復元しました ($restored 件)"
  fi
}
trap restore_repo_state EXIT

mkdir -p "$WORK_DIR" "$DIST_DIR"

# ---------------------------------------------------------------
# spc の用意（ソースから実行。CI と同一手順）
# ---------------------------------------------------------------
git -C "$SPC_REPO" rev-parse --verify "$SPC_REF" >/dev/null 2>&1 || die "ref が存在しません: $SPC_REF"
log "checkout: $(git -C "$SPC_REPO" log -1 --oneline "$SPC_REF")"

# ---------------------------------------------------------------
# ビルド実行
# ---------------------------------------------------------------
BUILD_CMDS=(
  "composer update -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist --no-dev"
  "bin/spc doctor --auto-fix"
)
[ -n "$UPX_FLAG" ] && BUILD_CMDS+=("bin/spc install-pkg upx")
BUILD_CMDS+=("bin/spc build --build-cli --build-fpm \"$EXTENSIONS\" --with-suggests --debug $UPX_FLAG $EXTRA_BUILD_FLAGS --dl-with-php=\"$PHP_VERSION_INPUT\" --dl-retry=5 --dl-prefer-binary --dl-ignore-cache=php-src")
BUILD_CMDS+=("bin/spc dev:info php --json --no-ansi > /tmp/php-info.json")

run_build_in_container() {
  PACKED_LOG="$(mktemp)"
  # hosted runner（ubuntu-24.04 に PHP 8.3 / Composer 2.10.3 / upx 4.2.2 同梱）と
  # shivammathur/setup-php（ppa:ondrej/php + php8.4-* パッケージ、composer は
  # getcomposer.org の phar を別途導入）の実装に合わせた環境を再現する。
  # setup-php の php_packages に composer は含まれないため、composer は明示導入が必要。
  # ※ ppa:ondrej/php の追加は add-apt-repository を使わず keyserver から鍵を取得して
  #   source list を直接書く（add-apt-repository は launchpadlib が launchpad.net から
  #   メタデータを取得する関係でネットワーク切断時に IncompleteRead で落ちることがある）
  docker run --rm -t -v "$SPC_REPO:/spc" -v "$WORK_DIR:/work" -w /spc \
    -e DEBIAN_FRONTEND=noninteractive "$DOCKER_IMAGE" bash -exc '
      apt-get update -qq
      apt-get install -y -qq curl git unzip ca-certificates gnupg software-properties-common composer >/dev/null
      # ppa:ondrej/php を鍵直取得で追加（add-apt-repository を経由しない）
      curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14AA40EC0831756756D7F66C4F4EA0AAE5267A6C" \
        | gpg --dearmor -o /etc/apt/keyrings/ondrej-php.gpg
      echo "deb [signed-by=/etc/apt/keyrings/ondrej-php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" \
        > /etc/apt/sources.list.d/ondrej-php.list
      apt-get update -qq
      # setup-php の php_packages（cli, curl, dev, mbstring, xml, zip...）相当 + composer
      # ※ openssl / filter / phar / pdo / zlib は php8.4-cli に組み込み（php8.4-openssl という
      #   パッケージは存在しない。setup-php の extensions: openssl は無効化処理がない限り no-op）
      apt-get install -y -qq php8.4-cli php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-dev composer >/dev/null
      update-alternatives --set php /usr/bin/php8.4
      # --- 環境 assert（CI と同一であることを機械的に確認。想像を置かない） ---
      php -v | head -n1
      composer --version
      command -v composer >/dev/null || { echo "FATAL: composer not found"; exit 1; }
      cat > /tmp/ext-assert.php <<'"'"'EOPHP'"'"'
<?php
$need = ["curl", "openssl", "mbstring", "filter"];
$missing = array_values(array_filter($need, fn ($e) => !extension_loaded($e)));
if ($missing !== [] || PHP_VERSION_ID < 80400) {
    fwrite(STDERR, "missing=[" . implode(",", $missing) . "] PHP_VERSION_ID=" . PHP_VERSION_ID . "\n");
    exit(1);
}
exit(0);
EOPHP
      php /tmp/ext-assert.php || {
        echo "FATAL: php 8.4 extensions not ready (see missing= above)"
        echo "--- php -m ---"
        php -m
        echo "--- /usr/bin/php ---"
        ls -l /usr/bin/php* /etc/alternatives/php 2>/dev/null
        exit 1
      }
      # --- ビルド（CI と同一コマンド列） ---
      '"$(printf '%s; ' "${BUILD_CMDS[@]}")"'
      mkdir -p /work/dist
      PHPVER="$(php -r '\''$j = json_decode(file_get_contents("/tmp/php-info.json"), true); echo $j["cache"]["source"]["info"]["version"] ?? "";'\'')"
      [ -n "$PHPVER" ] || { echo "PHP version resolve failed"; exit 1; }
      chmod -R 755 buildroot/bin
      TAG_SUFFIX="'"${TAG:+-$TAG}"'"
      tar -czf "/work/dist/php-$PHPVER-cli-linux-$(uname -m)$TAG_SUFFIX.tar.gz" -C buildroot/bin php
      tar -czf "/work/dist/php-$PHPVER-fpm-linux-$(uname -m)$TAG_SUFFIX.tar.gz" -C buildroot/bin php-fpm
      cp buildroot/build-manifest.json "/work/dist/build-manifest-linux-$(uname -m).json" 2>/dev/null || true
      echo "PACKED: php-$PHPVER$TAG_SUFFIX"
    ' > "$PACKED_LOG"
  # verify の対象限定用に、今回作成した tar.gz のファイル名から PHP バージョンを解決する
  # （コンテナ内の PACKED 出力から取得するため、dist 内の旧成果物に影響されない）
  local packed_line
  packed_line="$(grep -a '^PACKED: ' "$PACKED_LOG" | tail -n1 | tr -d '\r' || true)"
  rm -f "$PACKED_LOG"
  [ -n "$packed_line" ] || die "コンテナの成果物出力 (PACKED) を取得できません: docker run の出力を確認"
  PHP_VERSION_RESOLVED="$(printf '%s' "$packed_line" | sed -E 's/^PACKED: php-([0-9.]+).*/\1/')"
  [ -n "$PHP_VERSION_RESOLVED" ] || die "PHP version resolve failed (PACKED 出力から解決できません)"
}

run_build_on_host() {
  [ "$(uname -s)" = "Darwin" ] || die "--host は macOS 上で実行してください（linux は docker モード）"
  command -v php >/dev/null || die "host モードには PHP 8.4+ が必要です (brew install php)"
  command -v composer >/dev/null || die "host モードには composer が必要です (brew install composer)"
  PHP_VERSION_ID="$(php -r 'echo PHP_VERSION_ID;')"
  [ "$PHP_VERSION_ID" -ge 80400 ] || die "spc の実行には PHP >= 8.4 が必要です（現在: $(php -v | head -n1)）"
  cd "$SPC_REPO"
  for c in "${BUILD_CMDS[@]}"; do bash -c "$c"; done
  PHPVER="$(php -r '$j = json_decode(file_get_contents("/tmp/php-info.json"), true); echo $j["cache"]["source"]["info"]["version"] ?? "";')"
  [ -n "$PHPVER" ] || die "PHP version resolve failed"
  ARCH="$(uname -m)"; [ "$ARCH" = "arm64" ] && ARCH_OUT="aarch64" || ARCH_OUT="x86_64"
  chmod -R 755 buildroot/bin
  TAG_SUFFIX="${TAG:+-$TAG}"
  tar -czf "$DIST_DIR/php-$PHPVER-cli-macos-$ARCH_OUT$TAG_SUFFIX.tar.gz" -C buildroot/bin php
  tar -czf "$DIST_DIR/php-$PHPVER-fpm-macos-$ARCH_OUT$TAG_SUFFIX.tar.gz" -C buildroot/bin php-fpm
  cp buildroot/build-manifest.json "$DIST_DIR/build-manifest-macos-$ARCH_OUT.json" 2>/dev/null || true
  echo "PACKED: php-$PHPVER -> $DIST_DIR"
  PHP_VERSION_RESOLVED="$PHPVER"
}

# ---------------------------------------------------------------
# 検証
# ---------------------------------------------------------------
# php -m の表示名と EXTENSIONS（spc パッケージ名）の対応:
#   pdo → PDO, phar → Phar, mbregex → mbstring 内蔵（独立表示なし）
normalize_module_name() {
  case "$1" in
    pdo) echo "PDO" ;;
    phar) echo "Phar" ;;
    simplexml) echo "SimpleXML" ;;
    mbregex) echo "" ;;   # mbstring に同梱。php -m には現れない
    *) echo "$1" ;;
  esac
}
# 拡張ごとの最小動作テスト（EXTENSIONS 由来で動的に選択する）。
# upstream に成果物テストスクリプトは無いため、拡張ごとに代表関数を 1 つ実行する
cat > "$WORK_DIR/ext-function-test.php" <<'EOPHP'
<?php
$ok = function (string $name) use (&$results) { $results[] = "  $name: OK"; };
$fail = function (string $name, string $reason) {
    fwrite(STDERR, "  NG: $name ($reason)\n");
    exit(1);
};
// 注意: stdout への echo が session_start() の前にあると CLI でも
// 「headers already sent」で失敗するため、OK 出力は最後にまとめて行う
$results = [];
$exts = array_map('trim', explode(',', getenv('EXTENSIONS')));
$done = [];
$t = function (string $ext, callable $fn) use ($ok, $fail, &$done) {
    if (!in_array($ext, $done, true)) {
        if (!extension_loaded($ext)) { $fail($ext, 'extension not loaded'); }
        try { $fn(); } catch (Throwable $e) { $fail($ext, $e->getMessage()); }
        $ok($ext);
        $done[] = $ext;
    }
};

if (in_array('pdo_sqlite', $exts, true)) {
    $t('PDO', function () { new PDO('sqlite::memory:'); });
    $ok('pdo_sqlite');
}
if (in_array('sqlite3', $exts, true)) { $t('sqlite3', fn () => (new SQLite3(':memory:'))->close()); }
if (in_array('gd', $exts, true)) { $t('gd', fn () => gd_info()); }
if (in_array('curl', $exts, true)) {
    $t('curl', function () {
        $c = curl_init();
        curl_setopt_array($c, [CURLOPT_URL => 'https://example.com', CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 15]);
        curl_exec($c);
    });
}
if (in_array('mbstring', $exts, true)) {
    $t('mbstring', function () {
        if (mb_strimwidth('あいうえお', 0, 4, '...') === '') { throw new Exception('mb_strimwidth failed'); }
    });
}
if (in_array('mbregex', $exts, true)) {
    if (!function_exists('mb_regex_encoding')) { $fail('mbregex', 'mb_regex_encoding not found'); }
    mb_regex_encoding('UTF-8');
    $ok('mbregex');
}
if (in_array('openssl', $exts, true)) {
    $t('openssl', function () {
        openssl_encrypt('x', 'aes-128-gcm', '0123456789abcdef', OPENSSL_RAW_DATA, '0123456789ab', $tag);
    });
}
if (in_array('bcmath', $exts, true)) {
    $t('bcmath', function () { if (bcadd('1', '2') !== '3') { throw new Exception('bcadd result'); } });
}
if (in_array('ctype', $exts, true)) { $t('ctype', fn () => ctype_digit('123') || throw new Exception('ctype_digit')); }
if (in_array('dom', $exts, true)) {
    $t('dom', function () { (new DOMDocument())->loadXML('<a/>') || throw new Exception('loadXML'); });
}
if (in_array('exif', $exts, true)) {
    $t('exif', function () {
        // exif_read_data は実ファイルが要るため、exif 拡張の動作は関数存在 + エンコード変換で確認
        if (!function_exists('exif_read_data') || !function_exists('exif_thumbnail')) {
            throw new Exception('exif functions not found');
        }
    });
}
if (in_array('fileinfo', $exts, true)) { $t('fileinfo', fn () => finfo_open(FILEINFO_MIME_TYPE) || throw new Exception('finfo_open')); }
if (in_array('filter', $exts, true)) {
    $t('filter', function () { if (filter_var('a@b.c', FILTER_VALIDATE_EMAIL) === false) { throw new Exception('filter_var'); } });
}
if (in_array('iconv', $exts, true)) {
    $t('iconv', function () {
        // 静的 libiconv 環境でも有効な汎用エンコーディング名を使用（SJIS-win は不可）
        if (iconv('UTF-8', 'UTF-16LE', 'あ') === false) { throw new Exception('iconv convert'); }
    });
}
if (in_array('intl', $exts, true)) {
    $t('intl', function () {
        if (!function_exists('locale_get_default')) { throw new Exception('locale_get_default not found'); }
        // de-DE の数値パース（upstream の src/globals/ext-tests/intl.php と同一）と NFKC 正規化
        $fmt = new NumberFormatter('de-DE', NumberFormatter::DECIMAL);
        if (strval($fmt->parse('1.100')) !== '1100') { throw new Exception('NumberFormatter parse'); }
        if (Normalizer::normalize('ﾊﾟ', Normalizer::NFKC) !== 'パ') { throw new Exception('Normalizer NFKC'); }
    });
}
if (in_array('json', $exts, true)) { $t('json', fn () => json_decode('{}') !== null || throw new Exception('json_decode')); }
if (in_array('phar', $exts, true)) {
    $t('phar', function () {
        $f = tempnam(sys_get_temp_dir(), 'p') . '.tar';
        new PharData($f);
    });
}
if (in_array('session', $exts, true)) {
    $t('session', function () {
        // save_path を /tmp に固定。session_start() より前に echo 出力があると
        // CLI でも「headers already sent」で開始に失敗するため、結果出力は start 後に行う
        $path = sys_get_temp_dir() . '/spc-session-' . getmypid();
        @mkdir($path, 0700, true);
        $s = @session_start(['save_path' => $path, 'use_strict_mode' => true, 'cache_limiter' => '']);
        if ($s !== true) { throw new Exception('session_start'); }
        session_destroy();
    });
}
if (in_array('simplexml', $exts, true)) {
    // SimpleXMLElement は子要素が無いと (bool) が false になるため instanceof で判定する
    $t('simplexml', function () {
        $r = simplexml_load_string('<a><b/></a>');
        if (!($r instanceof SimpleXMLElement)) { throw new Exception('simplexml_load_string'); }
    });
}
if (in_array('sockets', $exts, true)) {
    $t('sockets', function () {
        $s = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($s === false) { throw new Exception('socket_create'); }
        socket_close($s);
    });
}
if (in_array('tokenizer', $exts, true)) {
    $t('tokenizer', function () { if (token_get_all('<?php echo 1; ?>') === []) { throw new Exception('token_get_all'); } });
}
if (in_array('xmlreader', $exts, true)) { $t('xmlreader', fn () => new XMLReader()); }
if (in_array('xmlwriter', $exts, true)) {
    $t('xmlwriter', function () { $w = new XMLWriter(); $w->openMemory(); $w->startDocument(); $w->endDocument(); });
}
if (in_array('xml', $exts, true)) { $t('xml', fn () => xml_parser_create() || throw new Exception('xml_parser_create')); }
if (in_array('zip', $exts, true)) { $t('zip', fn () => new ZipArchive()); }
if (in_array('zlib', $exts, true)) { $t('zlib', fn () => gzencode('x') || throw new Exception('gzencode')); }
if (in_array('posix', $exts, true)) { $t('posix', fn () => posix_getpid() > 0 || throw new Exception('posix_getpid')); }
if (in_array('pcntl', $exts, true)) { $t('pcntl', fn () => function_exists('pcntl_fork')); }
if (in_array('mysqlnd', $exts, true)) {
    // mysqlnd は libmysqlclient 代替のドライバで、mysqli は別 EXTENSIONS エントリ。
    // mysqlnd 単体では mysqli が読み込まれないため、拡張ロードのみを確認する
    $t('mysqlnd', function () { if (!extension_loaded('mysqlnd')) { throw new Exception('mysqlnd not loaded'); } });
}
if (in_array('mysqli', $exts, true)) { $t('mysqli', fn () => extension_loaded('mysqli') || throw new Exception('mysqli')); }
if (in_array('pdo_mysql', $exts, true)) { $t('pdo_mysql', fn () => extension_loaded('pdo_mysql') || throw new Exception('pdo_mysql')); }
if (in_array('pgsql', $exts, true)) { $t('pgsql', fn () => extension_loaded('pgsql') || throw new Exception('pgsql')); }
if (in_array('pdo_pgsql', $exts, true)) { $t('pdo_pgsql', fn () => extension_loaded('pdo_pgsql') || throw new Exception('pdo_pgsql')); }
$ok('all function tests');
echo implode("\n", $results) . "\n";
EOPHP

run_verify() {
  # ビルド済み tar.gz のうち、自ホストの OS/arch と一致するものだけを検証する。
  # 静的バイナリは依存ゼロのため自ホストで直接実行でき、別プラットフォーム分は
  # そのプラットフォーム上の build.sh で検証する（mac に linux 分が混在していても無視）。
  # 検証対象の選択ルール:
  #   1. ビルドモード実行時: 今回ビルドした成果物のみ（SPC_TAG があればタグ付きのみ、
  #      なければタグ無しのみ。ビルド時の PACKED 出力から解決したバージョンで判定）
  #   2. --verify-only 単体実行時: dist 内の自ホスト一致分すべて。ただし
  #      SPC_VERIFY_TAG / SPC_VERIFY_VERSION で絞り込み可能
  # EXTENSIONS が一致しない成果物は検証に失敗するため、混在時は絞り込み指定を推奨。
  log "=== 検証 ==="
  local HOST_S HOST_M HOST_KEY verified=0
  HOST_S="$(uname -s)"; HOST_M="$(uname -m)"
  case "$HOST_S:$HOST_M" in
    Linux:x86_64)  HOST_KEY="-linux-x86_64" ;;
    Linux:aarch64) HOST_KEY="-linux-aarch64" ;;
    Darwin:arm64)  HOST_KEY="-macos-aarch64" ;;
    Darwin:x86_64) HOST_KEY="-macos-x86_64" ;;
    *) die "未対応のホスト: $HOST_S:$HOST_M" ;;
  esac
  shopt -s nullglob
  for tgz in "$DIST_DIR"/php-*-cli-*.tar.gz; do
    local base
    base="$(basename "$tgz")"
    # ホスト照合: <os>-<arch> を含むか（タグの有無を問わない）
    case "$base" in
      *"$HOST_KEY"*) ;;
      *) log "skip (host mismatch): $base"; continue ;;
    esac
    # バージョン絞り込み（--verify-only 時のオプション）
    if [ -n "$VERIFY_VERSION" ]; then
      case "$base" in
        php-"$VERIFY_VERSION"-cli-*) ;;
        *) log "skip (version mismatch): $base"; continue ;;
      esac
    fi
    # タグ絞り込み
    if [ -n "$VERIFY_TAG" ]; then
      case "$base" in
        *-"$VERIFY_TAG".tar.gz) ;;
        *) log "skip (tag mismatch): $base"; continue ;;
      esac
    fi
    # ビルドモード実行時は「今回ビルドしたもの」に限定する:
    # タグありビルドなら同名タグの tar.gz のみ、タグなしならタグ無しの tar.gz のみ
    if [ "$VERIFY_ONLY" != true ] && [ -n "$PHP_VERSION_RESOLVED" ]; then
      local want_prefix="php-$PHP_VERSION_RESOLVED-cli${HOST_KEY}"
      if [ -n "$TAG" ]; then
        case "$base" in
          "$want_prefix-$TAG".tar.gz) ;;
          *) log "skip (not this build): $base"; continue ;;
        esac
      else
        case "$base" in
          "$want_prefix".tar.gz) ;;
          *) log "skip (not this build): $base"; continue ;;
        esac
      fi
    fi
    log "verify: $base"
    TMP="$(mktemp -d)"
    tar -xzf "$tgz" -C "$TMP"
    GOT="$("$TMP/php" -m)"
    MISSING=0
    IFS=',' read -ra WANT <<< "$EXTENSIONS"
    for e in "${WANT[@]}"; do
      mod="$(normalize_module_name "$e")"
      [ -z "$mod" ] && continue
      grep -qx "$mod" <<< "$GOT" || { echo "  NG: 拡張 $e ($mod) が php -m に無い"; MISSING=1; }
    done
    [ "$MISSING" -eq 0 ] && echo "  ${#WANT[@]} 拡張すべて含まれる: OK"
    EXTENSIONS="$EXTENSIONS" "$TMP/php" "$WORK_DIR/ext-function-test.php" || MISSING=1
    rm -rf "$TMP"
    [ "$MISSING" -eq 0 ] || die "検証失敗: $base"
    verified=$((verified + 1))
  done
  shopt -u nullglob
  [ "$verified" -gt 0 ] || die "検証対象がありません: $DIST_DIR/php-*-cli-*$HOST_KEY*.tar.gz${VERIFY_VERSION:+ (version: $VERIFY_VERSION)}${VERIFY_TAG:+ (tag: $VERIFY_TAG)}"
  log "すべての検証に合格しました。成果物: $DIST_DIR"
}

if [ "$VERIFY_ONLY" = true ]; then
  # --verify-only: ビルドせず既存 work/dist/ を検証のみ実行
  [ -d "$DIST_DIR" ] && ls "$DIST_DIR"/php-*-cli-*.tar.gz >/dev/null 2>&1 || die "検証対象がありません: $DIST_DIR/php-*-cli-*.tar.gz"
  run_verify
  exit 0
fi


if [ "$MODE" = "docker" ]; then
  command -v docker >/dev/null || die "docker が見つかりません"
  run_build_in_container
else
  run_build_on_host
fi

run_verify
