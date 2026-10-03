#!/usr/bin/env python3
# PYTHON_ARGCOMPLETE_OK
import argparse
import subprocess
import os
import sys
import atexit
import base64
import glob
import json
import re
import shutil
import tempfile
from datetime import datetime, timezone, timedelta

try:
    import argcomplete
except ImportError:
    argcomplete = None

DEFAULT_IMAGE_BASE = "ghcr.io/fukumen/rep2"
LOCAL_IMAGE_BASE = "rep2"

SERVICE_NAME = "rep2"
SPC_RELEASE_API = "https://api.github.com/repos/fukumen/static-php-cli/releases/latest"
SPC_RELEASE_BASE = "https://github.com/fukumen/static-php-cli/releases/download/latest"
DEB_RELEASE_API = "https://api.github.com/repos/fukumen/p2-php/releases/latest"
DEB_RELEASE_BASE = "https://github.com/fukumen/p2-php/releases/download/latest"

STATIC_IMAGE_NAME = "rep2-static:latest"
AIODEB_IMAGE_NAME = "rep2-aiodeb:latest"

# 検証モード / ephemeral 用の compose オーバーレイ。ファイルを新設しないため
# 実行時に一時ファイルへ書き出して -f で渡す。
# Dockerfile の VOLUME /ext による匿名ボリューム自動生成は volumes の reset では
# 消えないため、ephemeral は tmpfs で上書きする
STATIC_COMPOSE = """\
services:
  rep2:
    image: rep2-static:latest
    build:
      dockerfile: docker/Dockerfile.static
      additional_contexts:
        spc-dist: ../rep2-allinone/spc-build/work/dist
"""

AIODEB_COMPOSE = """\
services:
  rep2:
    image: rep2-aiodeb:latest
    build:
      dockerfile: docker/Dockerfile.aiodeb
      additional_contexts:
        aiodeb-dist: ../rep2-allinone/dist
    user: rep2
    ports: !override
      - "${REP2_PORT:-10088}:10088"
    volumes: !reset []
    environment: !override
      TZ: "Asia/Tokyo"
"""

EPHEMERAL_COMPOSE = """\
services:
  rep2:
    volumes: !reset []
    tmpfs:
      - /ext
"""

AGENT_COMPOSE = """\
name: rep2-agent
services:
  rep2:
    image: "%%AGENT_IMAGE%%"
    ports:
      - "127.0.0.1:10089:8443"
    volumes:
      - ../../../test:/var/www/test
    tmpfs:
      - /ext
    environment:
      SECRET_KEY: "%%AGENT_SECRET_KEY%%"
    depends_on:
      filter-proxy:
        condition: service_started
  filter-proxy:
    image: mitmproxy/mitmproxy:latest
    command:
      - mitmweb
      - -p
      - "3128"
      - --web-host
      - 0.0.0.0
      - --web-port
      - "8081"
      - --set
      - web_open_browser=false
      - --set
      - web_password=rep2agent
      - -s
      - /opt/agent/filter_proxy.py
    volumes:
      - ./agent/filter_proxy.py:/opt/agent/filter_proxy.py:ro
    ports:
      - "127.0.0.1:3128:3128"
      - "127.0.0.1:8081:8081"
"""

AGENT_COMPOSE_FILE = "docker-compose.agent.yml"

AGENT_COMMANDS = ("agent-up", "agent-down", "agent-logs", "agent-exec", "agent-test")

REMOTE_COMMAND = {
    "up": True,
    "build": False,
    "build-base": False,
    "build-static": False,
    "build-aiodeb": False,
    "down": True,
    "pull": True,
    "logs": True,
    "exec": True,
    "config": True,
    "update": True,
    "confdiff": True,
    "prune": True,
    "clean": False,
    "sync": False,
    "upload": False,
    "deploy": False,
    "images": True,
}

def load_env(path=".env"):
    """.env を読み込み、未設定の環境変数へ反映する（既存の環境変数が優先される）"""
    try:
        with open(path, encoding="utf-8") as f:
            lines = f.read().splitlines()
    except OSError:
        return
    for line in lines:
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip('"').strip("'"))


def env_bool(name, default=False):
    value = os.environ.get(name)
    if value is None:
        return default
    return value.strip().lower() in ("1", "true", "yes", "on")


def resolve_debug(debug_flag):
    if debug_flag is not None:
        return debug_flag
    return env_bool("REP2_BUILD_DEBUG", False)


def check_flag_conflicts(args):
    """検証モード (--static / --aiodeb) と FLAG 系オプション・ローカル override の排他チェック"""
    if args.command in AGENT_COMMANDS:
        for flag in ("debug", "static", "aiodeb", "ephemeral"):
            raw = getattr(args, f"raw_{flag}", None) if flag == "debug" else getattr(args, flag)
            if raw:
                print(f"Error: agent 系コマンドと --{flag} は同時に指定できません。")
                sys.exit(1)
    if args.static and args.aiodeb:
        print("Error: --static と --aiodeb は同時に指定できません (検証モードは排他です)。")
        sys.exit(1)
    for mode in ("static", "aiodeb"):
        if getattr(args, mode):
            if args.extra:
                print(f"Error: --{mode} と --extra は同時に指定できません (検証モードは FLAG 系オプションと排他です)。")
                sys.exit(1)
            if args.debug:
                print(f"Error: --{mode} と --debug は同時に指定できません (検証モードは FLAG 系オプションと排他です)。")
                sys.exit(1)
            if args.command in ("build", "build-base"):
                print(f"Error: --{mode} 付きの {args.command} は対応していません。")
                if mode == "static":
                    print("検証イメージのビルドは build-static コマンドを使用してください。")
                else:
                    print("検証イメージのビルドは build-aiodeb コマンドを使用してください。")
                sys.exit(1)
            # docker-compose.override.yml (ローカル開発用の bind マウント) は
            # 正式イメージのパス (/var/www, /usr/local/etc/php) を前提とするため、
            # パス構成が異なる検証イメージの起動を汚染する (--nooverride で回避できる)
            if os.path.exists("docker-compose.override.yml") and not args.nooverride and args.command != "config":
                print(f"Warning: docker-compose.override.yml が存在します。--{mode} での起動には")
                print("  正式イメージ向けの bind マウントが混在します (--nooverride を付けるか、")
                print("  ローカル override を使わない環境で実行してください)。")

    if args.ephemeral:
        if args.aiodeb:
            print("Error: --aiodeb は既定でデータを永続化しないため、--ephemeral と同時に指定できません。")
            sys.exit(1)
        if args.command not in ("up", "down", "pull", "logs", "exec", "config", "deploy"):
            print(f"Warning: --ephemeral は {args.command} では効果がないため無視します。")


def _remove_compose_file(path):
    try:
        os.remove(path)
    except OSError:
        pass


def write_compose_file(name, content):
    """compose オーバーレイを一時ディレクトリの固定パスに書き出し、そのパスを返す

    固定パスへの上書きなので仮にプロセスが残っても蓄積はせず、
    通常の終了経路では atexit で削除する
    """
    path = os.path.join(tempfile.gettempdir(), name)
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, "w", encoding="utf-8") as f:
        f.write(content)
    atexit.register(_remove_compose_file, path)
    return path


def get_host_arch():
    """実行ホストの arch を spc 成果物の命名 (x86_64 / aarch64) へ変換する"""
    machine = os.uname().machine
    if machine in ("x86_64", "amd64"):
        return "x86_64"
    if machine in ("aarch64", "arm64"):
        return "aarch64"
    print(f"Error: 未対応のアーキテクチャです: {machine}")
    sys.exit(1)


def fetch_latest_release_assets(api_url):
    """GitHub API の latest リリースから asset 名の一覧を取得する"""
    print(f"==> {api_url} から最新リリースを解決中...")
    try:
        import json
        import urllib.request

        req = urllib.request.Request(api_url, headers={"Accept": "application/vnd.github+json"})
        with urllib.request.urlopen(req, timeout=30) as res:
            release = json.loads(res.read().decode("utf-8"))
    except Exception as e:
        print(f"Error: GitHub API リクエストに失敗しました: {e}")
        print("時間を置いて再実行するか、--src local を使用してください。")
        sys.exit(1)
    return [a.get("name", "") for a in release.get("assets", [])]


def download_release_asset(base_url, name, dest):
    """releases/download/latest/ 配下から asset を dest にダウンロードする"""
    url = f"{base_url}/{name}"
    print(f"==> {url} をダウンロード中...")
    try:
        import urllib.request

        with urllib.request.urlopen(url, timeout=300) as res:
            with open(dest, "wb") as f:
                shutil.copyfileobj(res, f)
    except Exception as e:
        print(f"Error: ダウンロードに失敗しました: {e}")
        print("時間を置いて再実行するか、--src local を使用してください。")
        sys.exit(1)


def resolve_static_release(php_src, host_arch):
    """github / local モードに応じて PHP_VERSION と tar.gz 名を解決する"""
    if php_src == "github":
        asset_names = fetch_latest_release_assets(SPC_RELEASE_API)
        pattern = re.compile(rf"^php-([0-9.]+)-cli-linux-{re.escape(host_arch)}\.tar\.gz$")
        for name in asset_names:
            m = pattern.match(name)
            if m:
                php_version = m.group(1)
                fpm_tgz = f"php-{php_version}-fpm-linux-{host_arch}.tar.gz"
                if fpm_tgz not in asset_names:
                    print(f"Error: 最新リリースに asset が見つかりません: {fpm_tgz}")
                    sys.exit(1)
                print(f"==> 解決: PHP {php_version} (latest: {name})")
                return php_version, name, fpm_tgz
        print(f"Error: 最新リリースに {host_arch} 向けの cli tar.gz が見つかりません")
        print(f"アセット一覧: {asset_names}")
        sys.exit(1)

    # local モード: spc-build work/dist の自ホスト arch の cli tar.gz のうち
    # 最新 mtime のものから PHP_VERSION を検出する (SPC_TAG 付き実験ビルドが
    # 混在していてもタグ無しを優先する)
    script_dir = os.path.dirname(os.path.abspath(__file__))
    dist_dir = os.path.normpath(os.path.join(
        script_dir, "..", "rep2-allinone", "spc-build", "work", "dist"))
    if not os.path.isdir(dist_dir):
        print(f"Error: dist ディレクトリが見つかりません: {dist_dir}")
        print("deploy/rep2-allinone/spc-build でビルドを実行してください。")
        sys.exit(1)

    tagless_pattern = re.compile(
        rf"^php-([0-9.]+)-cli-linux-{re.escape(host_arch)}\.tar\.gz$")
    tagged_pattern = re.compile(
        rf"^php-([0-9.]+)-cli-linux-{re.escape(host_arch)}-.+\.tar\.gz$")
    tagless = []  # (mtime, path)
    tagged = []
    for name in os.listdir(dist_dir):
        m = tagless_pattern.match(name)
        bucket = tagless if m else None
        if not m:
            m = tagged_pattern.match(name)
            bucket = tagged if m else None
        if not m:
            continue
        path = os.path.join(dist_dir, name)
        if os.path.isfile(path):
            bucket.append((os.path.getmtime(path), path))

    # タグ無しを優先し、その上で最新 mtime を選ぶ
    pool = tagless or tagged
    if not pool:
        print(f"Error: {dist_dir} に {host_arch} 向けの cli tar.gz が見つかりません")
        print("deploy/rep2-allinone/spc-build でビルドを実行してください。")
        sys.exit(1)

    _, cli_path = max(pool, key=lambda c: c[0])
    cli_tgz = os.path.basename(cli_path)
    if tagless:
        php_version = tagless_pattern.match(cli_tgz).group(1)
    else:
        php_version = tagged_pattern.match(cli_tgz).group(1)
    fpm_tgz = cli_tgz.replace("-cli-", "-fpm-")
    if not os.path.isfile(os.path.join(dist_dir, fpm_tgz)):
        print(f"Error: fpm tar.gz が見つかりません: {fpm_tgz}")
        sys.exit(1)
    print(f"==> 解決: PHP {php_version} (local: {cli_tgz})")
    return php_version, cli_tgz, fpm_tgz


def resolve_aiodeb_deb(deb_src, host_arch):
    """deb 検証イメージのビルドに使う rep2-allinone_*.deb を解決する (github / local)"""
    if deb_src == "github":
        deb_arch = "arm64" if host_arch == "aarch64" else "amd64"
        arch_deb = f"rep2-allinone_*_{deb_arch}.deb"
        pattern = re.compile(rf"^rep2-allinone_.+_{deb_arch}\.deb$")
        debs = [n for n in fetch_latest_release_assets(DEB_RELEASE_API) if pattern.match(n)]
        if not debs:
            print(f"Error: 最新リリースに {arch_deb} が見つかりません")
            sys.exit(1)
        if len(debs) > 1:
            print(f"Error: 最新リリースに複数の {arch_deb} が存在します:")
            for n in debs:
                print(f"  {n}")
            sys.exit(1)
        print(f"==> 解決 (github): {DEB_RELEASE_BASE}/{debs[0]}")
        return debs[0]

    script_dir = os.path.dirname(os.path.abspath(__file__))
    dist_dir = os.path.normpath(os.path.join(
        script_dir, "..", "rep2-allinone", "dist"))
    deb_arch = "arm64" if host_arch == "aarch64" else "amd64"
    arch_deb = f"rep2-allinone_*_{deb_arch}.deb"
    debs = sorted(glob.glob(os.path.join(dist_dir, arch_deb)))
    if not debs:
        print(f"Error: {dist_dir} に {arch_deb} が見つかりません")
        print("deploy/rep2-allinone で make deb を実行するか、--src github を使用してください。")
        sys.exit(1)
    if len(debs) > 1:
        print(f"Error: {dist_dir} に複数の deb が存在します:")
        for d in debs:
            print(f"  {os.path.basename(d)}")
        print("どれを検証するか曖昧になるため、make clean で古い dist を削除してください。")
        sys.exit(1)

    deb_path = debs[0]
    print(f"==> 解決: {os.path.basename(deb_path)}")
    return os.path.basename(deb_path)


def get_remote_config():
    host = os.environ.get("REP2_REMOTE_HOST", "").strip()
    path = os.environ.get("REP2_REMOTE_PATH", "").strip()
    return host, path


def require_remote_config():
    host, path = get_remote_config()
    if not host or not path:
        print("Error: リモート実行には REP2_REMOTE_HOST と REP2_REMOTE_PATH の両方の設定が必要です (.env に記述できます)。")
        sys.exit(1)
    return host, path


def get_agent_compose_args(args):
    """agent 系コマンド専用。compose は AGENT_COMPOSE 1本のみ"""
    secret_key = os.environ.get("REP2_AGENT_SECRET_KEY")
    if not secret_key or len(secret_key) != 64:
        print("Error: REP2_AGENT_SECRET_KEY に 64 桁の 16 進の文字列(暗号キー)を設定してください (.env に記述できます)。")
        print("生成例: openssl rand -hex 32")
        sys.exit(1)
    image_name = "rep2-extra:latest" if args.extra else "rep2:latest"
    script_dir = os.path.dirname(os.path.abspath(__file__))
    compose = AGENT_COMPOSE.replace("%%AGENT_IMAGE%%", image_name) \
                           .replace("%%AGENT_SECRET_KEY%%", secret_key)
    return [
        "docker", "compose",
        "--project-directory", script_dir,
        "-f", write_compose_file(AGENT_COMPOSE_FILE, compose),
    ]


def get_git_info(path="."):
    if not os.path.isdir(path):
        return "unknown", ""
    try:
        # Get hash
        repo_hash = subprocess.check_output(
            ["git", "rev-parse", "--short", "HEAD"],
            cwd=path, stderr=subprocess.DEVNULL, text=True
        ).strip()

        # Get log
        env = os.environ.copy()
        env["TZ"] = "Asia/Tokyo"
        repo_raw_log = subprocess.check_output(
            ["git", "log", "-1", "--format=%cd %s", "--date=format-local:%Y-%m-%d %H:%M"],
            cwd=path, env=env, stderr=subprocess.DEVNULL, text=True
        ).strip().split('\n')[0]

        repo_log = base64.b64encode(repo_raw_log.encode('utf-8')).decode('utf-8')
        return repo_hash, repo_log
    except (subprocess.CalledProcessError, FileNotFoundError):
        return "unknown", ""

VERSION_IMAGE_TAG = "rep2:version"

def run_capture(cmd, env=None):
    """docker run --rm <image> <cmd> 等の出力を取得する (失敗時は終了)"""
    res = subprocess.run(cmd, capture_output=True, text=True, env=env)
    if res.returncode != 0:
        print(f"Error: コマンドが失敗しました (終了コード {res.returncode}): {' '.join(cmd)}")
        if res.stderr.strip():
            print(res.stderr.strip())
        sys.exit(res.returncode)
    return res.stdout.strip()

def run_in_image(image, cmd, env=None):
    """1回目ビルドイメージ内で cmd を実行して出力を返す"""
    return run_capture(["docker", "run", "--rm", image] + cmd, env=env)

def get_image_env(image, key, env=None):
    """docker image inspect の Config.Env から指定キーの値を取得する (無ければ空文字)"""
    out = run_capture(["docker", "image", "inspect", image,
                       "--format", "{{json .Config.Env}}"], env=env)
    try:
        env_list = json.loads(out)
    except json.JSONDecodeError:
        print(f"Error: {image} の Config.Env の解析に失敗しました")
        sys.exit(1)
    prefix = key + "="
    for entry in env_list:
        if entry.startswith(prefix):
            return entry[len(prefix):]
    return ""

VER_IMAGE_NAMES = (
    "rep2", "rep2-extra", "rep2-dbg", "rep2-extra-dbg",
    "rep2-base", "rep2-base-extra", "rep2-base-dbg", "rep2-base-extra-dbg",
    "rep2-static", "rep2-aiodeb",
)

VER_ENV_KEYS = (
    "VER_BUILD_TYPE", "VER_PHP", "VER_CADDY", "VER_ALPINE",
    "VER_DEBIAN", "VER_COMPOSER", "VER_REPO_HASH", "VER_REPO_LOG",
    "VER_RUN_ID", "VER_RUN_NUMBER",
)

VER_LOG_MAX = 24

def strip_ver_prefix(key):
    return key.removeprefix("VER_")

def list_version_images(env=None):
    """docker images から rep2 関連イメージを抽出し、VER_* ENV とビルド時刻を表示する"""
    out = run_capture(["docker", "images", "--format", "{{json .}}"], env=env)
    images = []
    for line in out.splitlines():
        line = line.strip()
        if not line:
            continue
        try:
            data = json.loads(line)
        except json.JSONDecodeError:
            continue
        repo = data.get("Repository", "")
        # ghcr.io/fukumen/rep2 等のレジストリ付き名前は末尾の short 名で判定する
        if repo.rsplit("/", 1)[-1] not in VER_IMAGE_NAMES:
            continue
        images.append(data)
    if not images:
        print("rep2 関連のイメージが見つかりません。")
        return

    jst = timezone(timedelta(hours=9))
    header = ("IMAGE", "BUILT(JST)") + tuple(strip_ver_prefix(k) for k in VER_ENV_KEYS)
    rows = []
    for img in sorted(images, key=lambda d: (d.get("Repository", ""), d.get("Tag", ""))):
        ref = f"{img['Repository']}:{img.get('Tag', '')}"
        env_ = {strip_ver_prefix(k): get_image_env(ref, k, env) for k in VER_ENV_KEYS}
        repo_log = env_["REPO_LOG"]
        if repo_log:
            try:
                repo_log = base64.b64decode(repo_log).decode("utf-8", "replace")
            except Exception:
                pass
            if len(repo_log) > VER_LOG_MAX:
                repo_log = repo_log[:VER_LOG_MAX] + "..."
            env_["REPO_LOG"] = repo_log
        created = ""
        raw = img.get("CreatedAt", "")
        if raw:
            try:
                created = datetime.strptime(raw.split(" +0000")[0].strip(),
                                            "%Y-%m-%d %H:%M:%S") \
                    .replace(tzinfo=timezone.utc).astimezone(jst).strftime("%Y-%m-%d %H:%M")
            except ValueError:
                created = raw
        rows.append((ref, created) + tuple(env_[strip_ver_prefix(k)] for k in VER_ENV_KEYS))

    widths = [max(len(row[i]) for row in ([header] + rows)) for i in range(len(header))]
    fmt = "  ".join(f"{{:{w}}}" for w in widths)
    print(fmt.format(*header))
    for row in rows:
        print(fmt.format(*row))

def build_once_then_extract(base_cmd, extract_fn, env):
    """1回目ビルド (rep2:version) → 実イメージから抽出 → 削除 → 抽出値を返す

    base_cmd は本ビルドと同一内容 (タグ・バージョン ARG を除く) の引数リスト。
    1回目ビルドはこのリストへタグ (rep2:version) を付けて実行する。
    """
    version_cmd = ["docker", "build", "-t", VERSION_IMAGE_TAG] + base_cmd
    run_cmd(version_cmd, env=env)
    try:
        extract_args = extract_fn(VERSION_IMAGE_TAG, env)
    finally:
        run_cmd(["docker", "rmi", "-f", VERSION_IMAGE_TAG], env=env)
    return extract_args

def get_image_name(args):
    if args.ghcr:
        image_name = DEFAULT_IMAGE_BASE
    else:
        image_name = LOCAL_IMAGE_BASE
    if args.extra:
        image_name += "-extra"
    if args.debug:
        image_name += "-dbg"
    return image_name + ":latest"

def get_upload_image_name(args):
    """upload で docker save するイメージ名。検証モードは overlay 側の固定名と一致させる"""
    if args.static:
        return STATIC_IMAGE_NAME
    if args.aiodeb:
        return AIODEB_IMAGE_NAME
    return get_image_name(args)

def get_base_image_name(args):
    if args.ghcr:
        base_image_name = f"{DEFAULT_IMAGE_BASE}-base"
    else:
        base_image_name = f"{LOCAL_IMAGE_BASE}-base"
    if args.extra:
        base_image_name += "-extra"
    if not args.ghcr and args.debug:
        base_image_name += "-dbg"
    return base_image_name + ":latest"

def compose_build_type(prefix, extra, debug):
    """VER_BUILD_TYPE を合成する (extra / debug は真偽値)"""
    build_type = prefix
    if extra:
        build_type += "-extra"
    if debug:
        build_type += "-dbg"
    return build_type

def run_cmd(cmd, env=None, shell=False):
    print(f"==> 実行: {' '.join(cmd) if isinstance(cmd, list) else cmd}")
    try:
        process = subprocess.Popen(cmd, env=env, shell=shell)
        res = process.wait()
    except KeyboardInterrupt:
        print("\n中断しました")
        process.terminate()
        process.wait()
        sys.exit(130)
    if res != 0:
        print(f"\nError: コマンドが失敗しました (終了コード {res})")
        sys.exit(res)

def get_compose_args(args, is_remote, tmp_compose=None):
    tmp_compose = tmp_compose or {}
    cmd = ["docker", "compose", "-f", "docker-compose.yml"]
    if args.aiodeb:
        cmd.extend(["-f", tmp_compose["aiodeb"]])
    if args.static:
        cmd.extend(["-f", tmp_compose["static"]])
    if args.debug:
        cmd.extend(["-f", "docker-compose.debug.yml"])
    if tmp_compose and "ephemeral" in tmp_compose:
        # 検証モードや debug の後、remote / override より前に読ませることで
        # /ext のマウントだけを無効化し、それ以外のマウントは後読み分で復活させる
        cmd.extend(["-f", tmp_compose["ephemeral"]])
    if (is_remote or args.use_remote_yml) and os.path.exists("docker-compose.remote.yml"):
        cmd.extend(["-f", "docker-compose.remote.yml"])
    if os.path.exists("docker-compose.override.yml") and not args.nooverride:
        cmd.extend(["-f", "docker-compose.override.yml"])
    return cmd

def execute_command(cmd_name, args, extra_args=None, remote_override=None):
    if cmd_name in AGENT_COMMANDS:
        if args.remote:
            print("Error: agent 系コマンドはローカル実行のみ対応しています。")
            sys.exit(1)
        is_remote = False
    elif remote_override is not None:
        is_remote = remote_override
    else:
        # フラグ未指定時はリモート設定の有無で自動判定する（deploy と同じ挙動）
        is_remote = args.remote if args.remote is not None else (
            REMOTE_COMMAND.get(cmd_name, False) and all(get_remote_config())
        )
    remote_host = remote_path = None
    if is_remote:
        remote_host, remote_path = require_remote_config()
    image_name = get_image_name(args)
    env = os.environ.copy()
    env["REP2_IMAGE"] = image_name
    if is_remote:
        env["DOCKER_HOST"] = f"ssh://{remote_host}"

    tmp_compose = {}
    if args.aiodeb:
        tmp_compose["aiodeb"] = write_compose_file("docker-rep2-aiodeb.yml", AIODEB_COMPOSE)
    if args.static:
        tmp_compose["static"] = write_compose_file("docker-rep2-static.yml", STATIC_COMPOSE)
    if args.ephemeral and cmd_name in ("up", "down", "pull", "logs", "exec", "config"):
        tmp_compose["ephemeral"] = write_compose_file("docker-rep2-ephemeral.yml", EPHEMERAL_COMPOSE)
    compose_base = get_compose_args(args, is_remote, tmp_compose)

    if cmd_name == "up":
        if is_remote:
            flags = []
            if args.extra: flags.append("--extra")
            if args.ghcr: flags.append("--ghcr")
            flags.append("--debug" if args.debug else "--nodebug")
            if args.nooverride: flags.append("--nooverride")
            if args.static: flags.append("--static")
            if args.aiodeb: flags.append("--aiodeb")
            if args.ephemeral: flags.append("--ephemeral")
            flags.append("--use-remote-yml")
            argv0 = os.path.basename(sys.argv[0])
            remote_cmd = f"cd {remote_path} && ./{argv0} --noremote {' '.join(flags)} up"
            run_cmd(["ssh", "-t", remote_host, remote_cmd])
        else:
            run_cmd(compose_base + ["up", "-d"], env=env)
            
    elif cmd_name == "build":
        flag_extra = "true" if args.extra else "false"
        flag_local = "false" if args.ghcr else "true"
        flag_debug = "true" if args.debug else "false"

        repo_hash, repo_log = get_git_info("../..")

        common_args = [
            "--build-arg", f"FLAG_EXTRA={flag_extra}",
            "--build-arg", f"FLAG_LOCAL={flag_local}",
            "--build-arg", f"FLAG_DEBUG={flag_debug}",
            "--build-arg", f"REPO_HASH={repo_hash}",
            "--build-arg", f"REPO_LOG={repo_log}",
            "--build-context", "p2-rep2=../..",
            "-f", "docker/Dockerfile",
            "."
        ]

        def extract_main(tag, env):
            alpine_full = run_in_image(tag, ["cat", "/etc/alpine-release"], env=env)
            caddy_full = run_in_image(tag, ["caddy", "version"], env=env).split()[0].lstrip("v")
            return {"ALPINE_VERSION_FULL": alpine_full, "CADDY_VERSION_FULL": caddy_full}

        build_args = build_once_then_extract(common_args, extract_main, env)

        build_cmd = ["docker", "build", "-t", image_name] + common_args + [
            "--build-arg", f"VER_BUILD_TYPE={compose_build_type('rep2', args.extra, args.debug)}",
            "--build-arg", f"ALPINE_VERSION_FULL={build_args['ALPINE_VERSION_FULL']}",
            "--build-arg", f"CADDY_VERSION_FULL={build_args['CADDY_VERSION_FULL']}",
        ]
        run_cmd(build_cmd, env=env)
        run_cmd(["docker", "image", "prune", "-f"], env=env)

    elif cmd_name == "build-base":
        base_image_name = get_base_image_name(args)
        flag_extra = "true" if args.extra else "false"
        flag_debug = "true" if args.debug else "false"
        build_cmd = [
            "docker", "build",
            "--pull",
            "-t", base_image_name,
            "--build-arg", f"FLAG_EXTRA={flag_extra}",
            "--build-arg", f"FLAG_DEBUG={flag_debug}",
            "--build-arg", f"VER_BUILD_TYPE={compose_build_type('rep2-base', args.extra, args.debug)}",
            "-f", "docker/Dockerfile.base",
            "."
        ]
        run_cmd(build_cmd, env=env)
        run_cmd(["docker", "image", "prune", "-f"], env=env)

    elif cmd_name == "build-static":
        host_arch = get_host_arch()
        php_version, cli_tgz, fpm_tgz = resolve_static_release(args.src, host_arch)
        repo_hash, repo_log = get_git_info("../..")
        script_dir = os.path.dirname(os.path.abspath(__file__))
        if args.src == "local":
            dist_dir = os.path.normpath(os.path.join(
                script_dir, "..", "rep2-allinone", "spc-build", "work", "dist"))
            spc_context = dist_dir
        else:
            spc_context = os.path.join(tempfile.mkdtemp(prefix="spc-dist-"))
            for tgz in (cli_tgz, fpm_tgz):
                download_release_asset(SPC_RELEASE_BASE, tgz,
                                       os.path.join(spc_context, tgz))

        # Dockerfile.static の FROM rep2-base:latest と同一イメージから取得する
        composer_version = get_image_env("rep2-base:latest", "VER_COMPOSER")
        if not composer_version:
            print("Error: rep2-base:latest から ENV VER_COMPOSER を取得できません。")
            print("build-base の実行が必要です。")
            if args.src != "local":
                shutil.rmtree(spc_context, ignore_errors=True)
            sys.exit(1)

        common_args = [
            "--build-arg", f"SPC_CLI_TGZ={cli_tgz}",
            "--build-arg", f"SPC_FPM_TGZ={fpm_tgz}",
            "--build-arg", f"REPO_HASH={repo_hash}",
            "--build-arg", f"REPO_LOG={repo_log}",
            "--build-context", f"spc-dist={spc_context}",
            "--build-context", "p2-rep2=../..",
            "-f", "docker/Dockerfile.static",
            "."
        ]

        def extract_static(tag, env):
            alpine_full = run_in_image(tag, ["cat", "/etc/alpine-release"], env=env)
            caddy_full = run_in_image(tag, ["caddy", "version"], env=env).split()[0].lstrip("v")
            return {"SPC_ALPINE_VERSION_FULL": alpine_full, "CADDY_VERSION_FULL": caddy_full}

        version_args = common_args + [
            "--build-arg", f"SPC_PHP_VERSION={php_version}",
            "--build-arg", f"SPC_COMPOSER_VERSION={composer_version}",
        ]
        try:
            build_args = build_once_then_extract(version_args, extract_static, env)

            build_cmd = ["docker", "build", "-t", "rep2-static:latest"] + version_args + [
                "--build-arg", f"SPC_ALPINE_VERSION_FULL={build_args['SPC_ALPINE_VERSION_FULL']}",
                "--build-arg", f"CADDY_VERSION_FULL={build_args['CADDY_VERSION_FULL']}",
            ]
            run_cmd(build_cmd, env=env)
        finally:
            if args.src != "local":
                shutil.rmtree(spc_context, ignore_errors=True)
        run_cmd(["docker", "image", "prune", "-f"], env=env)

    elif cmd_name == "build-aiodeb":
        host_arch = get_host_arch()
        deb_name = resolve_aiodeb_deb(args.src, host_arch)
        if args.src == "local":
            deb_context = os.path.normpath(os.path.join(
                os.path.dirname(os.path.abspath(__file__)), "..", "rep2-allinone", "dist"))
        else:
            deb_context = os.path.join(tempfile.mkdtemp(prefix="aiodeb-deb-"))
            download_release_asset(DEB_RELEASE_BASE, deb_name,
                                   os.path.join(deb_context, deb_name))

        common_args = [
            "--build-arg", f"DEB_NAME={deb_name}",
            "--build-context", f"aiodeb-dist={deb_context}",
            "-f", "docker/Dockerfile.aiodeb",
            "."
        ]

        BUILD_INFO_KEYS = {
            "VER_REPO_HASH": "REPO_HASH",
            "VER_REPO_LOG": "REPO_LOG",
            "VER_RUN_ID": "RUN_ID",
            "VER_RUN_NUMBER": "RUN_NUMBER",
            "VER_PHP": "PHP_VERSION_FULL",
            "VER_CADDY": "CADDY_VERSION_FULL",
            "VER_COMPOSER": "COMPOSER_VERSION_FULL",
        }

        def extract_aiodeb(tag, env):
            debian_full = run_in_image(tag, ["cat", "/etc/debian_version"], env=env)
            build_info = run_in_image(tag, ["cat", "/etc/rep2-allinone/build_info"], env=env)
            info = {}
            for line in build_info.splitlines():
                if "=" in line:
                    k, v = line.split("=", 1)
                    info[k.strip()] = v.strip()
            extract = {"DEBIAN_VERSION": debian_full}
            for env_key, arg_key in BUILD_INFO_KEYS.items():
                extract[arg_key] = info.get(env_key, "")
            return extract

        try:
            build_args = build_once_then_extract(common_args, extract_aiodeb, env)

            build_cmd = ["docker", "build", "-t", "rep2-aiodeb:latest"] + common_args
            for key, value in build_args.items():
                build_cmd += ["--build-arg", f"{key}={value}"]
            run_cmd(build_cmd, env=env)
        finally:
            if args.src != "local":
                shutil.rmtree(deb_context, ignore_errors=True)
        run_cmd(["docker", "image", "prune", "-f"], env=env)

    elif cmd_name == "down":
        run_cmd(compose_base + ["down"], env=env)
        
    elif cmd_name == "pull":
        run_cmd(compose_base + ["pull"], env=env)
        
    elif cmd_name == "logs":
        run_cmd(compose_base + ["logs"] + extra_args, env=env)
        
    elif cmd_name == "exec":
        run_cmd(compose_base + ["exec", SERVICE_NAME] + (extra_args or ["/bin/sh"]), env=env)

    elif cmd_name == "config":
        run_cmd(compose_base + ["config"], env=env)

    elif cmd_name == "update":
        run_cmd(compose_base + ["cp", "../../lib", f"{SERVICE_NAME}:/var/www"], env=env)
        run_cmd(compose_base + ["cp", "../../rep2", f"{SERVICE_NAME}:/var/www"], env=env)
        run_cmd(compose_base + ["exec", SERVICE_NAME, "chown", "-R", "root:root", "/var/www/lib"], env=env)
        run_cmd(compose_base + ["exec", SERVICE_NAME, "chown", "-R", "root:root", "/var/www/rep2"], env=env)

    elif cmd_name == "confdiff":
        compose_str = " ".join(compose_base)
        sh_cmd = f"{compose_str} exec {SERVICE_NAME} diff /var/www/conf.orig /ext/conf | iconv -f SHIFT_JIS -t UTF-8"
        run_cmd(sh_cmd, env=env, shell=True)

    elif cmd_name == "prune":
        run_cmd(["docker", "image", "prune", "-f"], env=env)

    elif cmd_name == "clean":
        run_cmd(["docker", "image", "prune", "-f"], env=env)
        run_cmd(["docker", "builder", "prune", "-a"], env=env)
            
    elif cmd_name == "sync":
        remote_host, remote_path = require_remote_config()
        run_cmd(["rsync", "-av", "-i", "--exclude", ".git", "--exclude", "rep2-data", "--exclude", "__pycache__", "./", f"{remote_host}:{remote_path}/"])

    elif cmd_name == "upload":
        remote_host, _ = require_remote_config()
        upload_image = get_upload_image_name(args)
        print(f"==> イメージ {upload_image} を {remote_host} へ転送中...")
        sh_cmd = f"docker save {upload_image} | ssh {remote_host} 'docker load'"
        run_cmd(sh_cmd, shell=True)

    elif cmd_name == "deploy":
        print("==> デプロイシーケンスを開始します...")

        # フラグ未指定ならリモート設定の有無でデプロイ先を自動判定
        deploy_is_remote = args.remote if args.remote is not None else all(get_remote_config())

        if args.static:
            build_cmd_name = "build-static"
        elif args.aiodeb:
            build_cmd_name = "build-aiodeb"
        else:
            build_cmd_name = "build"

        deploy_steps = (
            ["down", build_cmd_name, "upload", "up", "prune"]
            if deploy_is_remote
            else ["down", build_cmd_name, "up", "prune"]
        )

        for i, cmd in enumerate(deploy_steps, 1):
            print(f"\n--- [{i}/{len(deploy_steps)}] {cmd} ---")
            # build/upload などはローカルで実行する。deploy がリモートのときだけ
            # 各ステップの既定 (REMOTE_COMMAND) に従う
            execute_command(
                cmd, args,
                remote_override=deploy_is_remote and REMOTE_COMMAND.get(cmd, False),
            )

        print("\n==> デプロイが完了しました！")

    elif cmd_name == "images":
        list_version_images(env)

    elif cmd_name == "agent-up":
        run_cmd(get_agent_compose_args(args) + ["up", "-d"], env=env)
        # rc.init による conf 生成・Web UI の起動待ち (ephemeral のため毎回初期化が走る)
        # 起動直後は 401 (ログインフォーム) が返る。HTTP 応答 (000 以外) が返れば処理が生きている
        run_cmd(["sh", "-c",
                 "i=0; until code=$(curl -sS --noproxy '*' -o /dev/null "
                 "-w '%{http_code}' http://127.0.0.1:10089/ 2>/dev/null); "
                 '[ -n "$code" ] && [ "$code" != "000" ]; do '
                 "i=$((i+1)); [ $i -ge 60 ] && exit 1; sleep 2; done"])
        script = os.path.normpath(os.path.join(os.path.dirname(__file__), "agent", "agent_setup.sh"))
        run_cmd(["sh", script], env=env)

    elif cmd_name == "agent-down":
        run_cmd(get_agent_compose_args(args) + ["down"], env=env)

    elif cmd_name == "agent-logs":
        run_cmd(get_agent_compose_args(args) + ["logs"] + extra_args, env=env)

    elif cmd_name == "agent-exec":
        run_cmd(get_agent_compose_args(args) + ["exec", SERVICE_NAME] + (extra_args or ["/bin/sh"]), env=env)

    elif cmd_name == "agent-test":
        test_file = os.path.abspath(args.test_file)
        test_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../test"))
        if not os.path.exists(test_file):
            print(f"Error: ファイルが見つかりません: {test_file}")
            sys.exit(1)
        if not (test_file.startswith(test_dir + os.sep) or test_file == test_dir):
            print(f"Error: テストファイルは test ディレクトリ配下に配置する必要があります: {test_dir}")
            sys.exit(1)
        rel_path = os.path.relpath(test_file, test_dir)
        container_php_file = f"/var/www/test/{rel_path}"
        run_cmd(get_agent_compose_args(args) + ["exec", "-T", SERVICE_NAME,
                 "php", container_php_file] + extra_args, env=env)

    else:
        print(f"Error: 不明なコマンドです: {cmd_name}")
        sys.exit(1)

def main():
    load_env()

    command_descriptions = {
        "up": "docker compose up を実行",
        "build": "docker build を実行してイメージを作成",
        "build-base": "docker/Dockerfile.base を使用してベースイメージを作成",
        "build-static": "検証用イメージ (static-php / Dockerfile.static) を作成",
        "build-aiodeb": "検証用イメージ (deb パッケージ / Dockerfile.aiodeb) を作成",
        "down": "docker compose down を実行",
        "pull": "docker compose pull を実行",
        "logs": "docker compose logs を実行",
        "exec": "コンテナ内でシェル (/bin/sh) または指定したコマンドを実行",
        "config": "docker compose config を実行",
        "update": "ローカルのソースコードをコンテナ内にコピーして権限を修正",
        "confdiff": "コンテナ内の conf.orig と conf の差分を表示",
        "prune": "不要な Docker イメージを削除 (docker image prune -f)",
        "clean": "Docker のイメージとビルドキャッシュをすべて削除",
        "sync": "rsync でローカルディレクトリをリモートホストへ同期",
        "upload": "ビルドしたイメージをリモートホストへ転送",
        "deploy": "down -> build(build-static/build-aiodeb) -> upload -> up のデプロイシーケンスを一括実行",
        "images": "rep2 関連イメージの VER_* ENV とビルド時刻 (JST) を一覧表示",
        "agent-up": "エージェント環境 (強制フィルタリング Proxy) を起動して設定を適用",
        "agent-down": "エージェント環境を停止",
        "agent-logs": "エージェント環境のログを表示",
        "agent-exec": "エージェント環境の rep2 コンテナでシェルまたは指定したコマンドを実行",
        "agent-test": "起動済みエージェント環境で PHP テストを実行 (test/ 配下のファイル)",
    }

    command_help = "コマンド一覧:\n"
    for cmd, desc in command_descriptions.items():
        command_help += f"  {cmd:<14} : {desc}\n"
    command_help += (
        "\nデバッグ:\n"
        "  .env に REP2_BUILD_DEBUG=true が設定されていれば既定で有効、\n"
        "  未設定なら無効 (--debug / --nodebug で強制)\n"
        "\n検証用イメージ:\n"
        "  --static で rep2-static、--aiodeb で rep2-aiodeb をオーバーレイする\n"
        "  イメージの作成は build-static / build-aiodeb コマンドを使う\n"
        "  検証モードは --extra / --debug との同時指定不可\n"
        "\nephemeral 起動:\n"
        "  --ephemeral でデータ (/ext) を tmpfs マウントに置き換えて起動する (down で消滅)\n"
        "  --aiodeb とは併用不可\n"
        "\nローカル override:\n"
        "  docker-compose.override.yml は存在すれば常に読み込まれる。\n"
        "  --nooverride を付けると読み込まずに実行する (検証モードでの汚染回避に使う)\n"
        "\nリモート実行:\n"
        "  up/down/pull/logs/exec/config/update/confdiff/prune は\n"
        "  .env の REP2_REMOTE_HOST と REP2_REMOTE_PATH の両方が設定されていれば\n"
        "  既定でリモート実行、未設定ならローカル実行 (--remote / --noremote で強制)\n"
        "  deploy はリモート設定の有無でローカル/リモートを自動判定する\n"
    )

    # 共通オプションを定義する親パーサー (ヘルプ重複衝突を避けるため add_help=False)
    parent_parser = argparse.ArgumentParser(add_help=False)
    parent_parser.add_argument('--extra', action='store_true', help="全部入りイメージにする")
    parent_parser.add_argument('--ghcr', action='store_true', help=f"公式イメージ名 ({DEFAULT_IMAGE_BASE}) を使用する")
    parent_parser.add_argument('--noghcr', dest='ghcr', action='store_false', help=f"ローカルイメージ名 ({LOCAL_IMAGE_BASE}) を使用する (default)")
    parent_parser.add_argument('--debug', dest='debug', action='store_true', default=None, help="デバッグを有効にする")
    parent_parser.add_argument('--nodebug', dest='debug', action='store_false', help="デバッグを無効にする")
    parent_parser.add_argument('--static', dest='static', action='store_true', help="検証用イメージ (static-php) を compose で使用する")
    parent_parser.add_argument('--aiodeb', dest='aiodeb', action='store_true', help="検証用イメージ (deb パッケージ) を compose で使用する")
    parent_parser.add_argument('--ephemeral', dest='ephemeral', action='store_true', help="データ (/ext) を永続化せずに起動する (tmpfs マウント / down で消滅)")
    parent_parser.add_argument('--nooverride', dest='nooverride', action='store_true', help="docker-compose.override.yml を読み込まない")
    parent_parser.add_argument('--remote', action='store_true', default=None, help="リモートホストで実行する (SSH経由 / DOCKER_HOST=ssh://<REP2_REMOTE_HOST>)")
    parent_parser.add_argument('--noremote', dest='remote', action='store_false', help="ローカルホストで実行する")
    parent_parser.add_argument('--use-remote-yml', action='store_true', help=argparse.SUPPRESS)

    # メインパーサー
    parser = argparse.ArgumentParser(
        description="docker-rep2 build script",
        epilog=command_help,
        formatter_class=argparse.RawTextHelpFormatter,
        parents=[parent_parser]
    )

    subparsers = parser.add_subparsers(dest='command', help="実行するコマンド", required=True)

    # 各コマンドをサブコマンドとして登録
    for cmd, desc in command_descriptions.items():
        if cmd == 'agent-test':
            test_parser = subparsers.add_parser(cmd, help=desc)
            test_file_arg = test_parser.add_argument('test_file', help="テストファイルへのパス (リポジトリの test/ 配下)")
            if argcomplete:
                from argcomplete.completers import FilesCompleter
                test_file_arg.completer = FilesCompleter()
        elif cmd == 'build-static':
            build_static_parser = subparsers.add_parser(cmd, help=desc)
            build_static_parser.add_argument('--src', dest='src', choices=['github', 'local'], default='github',
                                             help="tar.gz 入手先: github=Releases から取得 / local=spc-build work/dist (default: github)")
        elif cmd == 'build-aiodeb':
            build_aiodeb_parser = subparsers.add_parser(cmd, help=desc)
            build_aiodeb_parser.add_argument('--src', dest='src', choices=['github', 'local'], default='github',
                                             help="deb 入手先: github=Releases (latest タグ) から取得 / local=rep2-allinone dist (default: github)")
        elif cmd == 'deploy':
            deploy_parser = subparsers.add_parser(cmd, help=desc)
            deploy_parser.add_argument('--src', dest='src', choices=['github', 'local'], default='github',
                                       help="検証モード (--static / --aiodeb) 時の成果物入手先: github=Releases から取得 / local=ローカルビルド (default: github)")
        else:
            subparsers.add_parser(cmd, help=desc)

    if argcomplete:
        argcomplete.autocomplete(parser)
    args, extra_args = parser.parse_known_args()
    args.raw_debug = args.debug  # 明示的な --debug / --nodebug の値 (未指定なら None)
    args.debug = resolve_debug(args.debug)

    check_flag_conflicts(args)
    execute_command(args.command, args, extra_args)

if __name__ == "__main__":
    main()
