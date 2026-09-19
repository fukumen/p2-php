#!/usr/bin/env python3
# PYTHON_ARGCOMPLETE_OK
import argparse
import subprocess
import os
import sys
import base64

try:
    import argcomplete
except ImportError:
    argcomplete = None

DEFAULT_IMAGE_BASE = "ghcr.io/fukumen/rep2"
LOCAL_IMAGE_BASE = "rep2"

DEFAULT_P2_CONTEXT = "https://github.com/fukumen/p2-php.git#php8-merge-mbstring"
LOCAL_P2_CONTEXT = "../p2-php"

SERVICE_NAME = "rep2php8"

REMOTE_COMMAND = {
    "up": True,
    "build": False,
    "build-base": False,
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
    "test": False,
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


def require_test_context():
    ctx = os.environ.get("REP2_TEST_CONTEXT", "").strip()
    if not ctx:
        print("Error: test コマンドの実行には REP2_TEST_CONTEXT の設定が必要です (.env に記述できます)。")
        sys.exit(1)
    return ctx


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

def run_cmd(cmd, env=None, shell=False):
    print(f"==> Executing: {' '.join(cmd) if isinstance(cmd, list) else cmd}")
    try:
        process = subprocess.Popen(cmd, env=env, shell=shell)
        res = process.wait()
    except KeyboardInterrupt:
        print("\nInterrupted")
        process.terminate()
        process.wait()
        sys.exit(130)
    if res != 0:
        print(f"\nError: Command failed with exit code {res}")
        sys.exit(res)

def get_compose_args(args, is_remote):
    cmd = ["docker", "compose", "-f", "docker-compose.yml"]
    if args.debug:
        cmd.extend(["-f", "docker-compose.debug.yml"])
    if (is_remote or args.use_remote_yml) and os.path.exists("docker-compose.remote.yml"):
        cmd.extend(["-f", "docker-compose.remote.yml"])
    if os.path.exists("docker-compose.override.yml"):
        cmd.extend(["-f", "docker-compose.override.yml"])
    return cmd

def execute_command(cmd_name, args, extra_args=None):
    is_remote = args.remote if args.remote is not None else REMOTE_COMMAND.get(cmd_name, False)
    remote_host = remote_path = None
    if is_remote:
        remote_host, remote_path = require_remote_config()
    image_name = get_image_name(args)
    env = os.environ.copy()
    env["REP2_IMAGE"] = image_name
    if is_remote:
        env["DOCKER_HOST"] = f"ssh://{remote_host}"

    compose_base = get_compose_args(args, is_remote)

    if cmd_name == "up":
        if is_remote:
            flags = []
            if args.extra: flags.append("--extra")
            if args.ghcr: flags.append("--ghcr")
            if args.debug: flags.append("--debug")
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
        context_p2 = DEFAULT_P2_CONTEXT if args.ghcr else LOCAL_P2_CONTEXT

        repo_hash, repo_log = get_git_info(".")
        rep2_hash, rep2_log = get_git_info(context_p2)

        build_cmd = [
            "docker", "build",
            "-t", image_name,
            "--build-arg", f"FLAG_EXTRA={flag_extra}",
            "--build-arg", f"FLAG_LOCAL={flag_local}",
            "--build-arg", f"FLAG_DEBUG={flag_debug}",
            "--build-arg", f"REPO_HASH={repo_hash}",
            "--build-arg", f"REPO_LOG={repo_log}",
            "--build-arg", f"REP2_HASH={rep2_hash}",
            "--build-arg", f"REP2_LOG={rep2_log}",
            "--build-context", f"p2-rep2={context_p2}",
            "-f", "docker/Dockerfile",
            "."
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
            "-f", "docker/Dockerfile.base",
            "."
        ]
        run_cmd(build_cmd, env=env)
        run_cmd(["docker", "image", "prune", "-f"], env=env)

    elif cmd_name == "down":
        run_cmd(compose_base + ["down"], env=env)
        
    elif cmd_name == "pull":
        run_cmd(compose_base + ["pull"], env=env)
        
    elif cmd_name == "logs":
        run_cmd(compose_base + ["logs"] + extra_args, env=env)
        
    elif cmd_name == "exec":
        run_cmd(compose_base + ["exec", SERVICE_NAME, "/bin/sh"], env=env)

    elif cmd_name == "config":
        run_cmd(compose_base + ["config"], env=env)

    elif cmd_name == "update":
        run_cmd(compose_base + ["cp", f"{LOCAL_P2_CONTEXT}/lib", f"{SERVICE_NAME}:/var/www"], env=env)
        run_cmd(compose_base + ["cp", f"{LOCAL_P2_CONTEXT}/rep2", f"{SERVICE_NAME}:/var/www"], env=env)
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
        print(f"==> Uploading image {image_name} to {remote_host}...")
        sh_cmd = f"docker save {image_name} | ssh {remote_host} 'docker load'"
        run_cmd(sh_cmd, shell=True)

    elif cmd_name == "deploy":
        print("==> Starting deploy sequence...")

        # フラグ未指定ならリモート設定の有無でデプロイ先を自動判定
        if args.remote is None:
            args.remote = all(get_remote_config())

        deploy_steps = (
            ["down", "build", "upload", "up", "prune"]
            if args.remote
            else ["down", "build", "up", "prune"]
        )

        for i, cmd in enumerate(deploy_steps, 1):
            print(f"\n--- [{i}/{len(deploy_steps)}] {cmd} ---")
            execute_command(cmd, args)

        print("\n==> Deploy completed successfully!")

    elif cmd_name == "test":
        test_context = require_test_context()
        test_file = os.path.abspath(args.test_file)
        if not os.path.exists(test_file):
            print(f"Error: file not found: {test_file}")
            sys.exit(1)

        test_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), test_context))
        if not (test_file.startswith(test_dir + os.sep) or test_file == test_dir):
            print(f"Error: Test file must be located under the test directory: {test_dir}")
            sys.exit(1)

        rel_path = os.path.relpath(test_file, test_dir)
        mount_opts = ["-v", f"{test_dir}:/var/www/test"]
        container_php_file = f"/var/www/test/{rel_path}"
        test_args = extra_args
        run_cmd(compose_base + [
            "run", "--rm",
        ] + mount_opts + [
            SERVICE_NAME,
            "php", container_php_file
        ] + test_args, env=env)

    else:
        print(f"Unknown command: {cmd_name}")
        sys.exit(1)

def main():
    load_env()

    command_descriptions = {
        "up": "docker compose up を実行",
        "build": "docker build を実行してイメージを作成",
        "build-base": "docker/Dockerfile.base を使用してベースイメージを作成",
        "down": "docker compose down を実行",
        "pull": "docker compose pull を実行",
        "logs": "docker compose logs を実行",
        "exec": "コンテナ内でシェル (/bin/sh) を実行",
        "config": "docker compose config を実行",
        "update": "ローカルのソースコードをコンテナ内にコピーして権限を修正",
        "confdiff": "コンテナ内の conf.orig と conf の差分を表示",
        "prune": "不要な Docker イメージを削除 (docker image prune -f)",
        "clean": "Docker のイメージとビルドキャッシュをすべて削除",
        "sync": "rsync でローカルディレクトリをリモートホストへ同期",
        "upload": "ビルドしたイメージをリモートホストへ転送",
        "deploy": "down -> build -> upload -> up のデプロイシーケンスを一括実行",
        "test": "指定した PHP テストファイルをコンテナ内でワンショット実行",
    }

    command_help = "コマンド一覧:\n"
    for cmd, desc in command_descriptions.items():
        remote_status = "--remote" if REMOTE_COMMAND.get(cmd, False) else "--noremote"
        command_help += f"  {cmd:<10} : {desc} (default: {remote_status})\n"

    # 共通オプションを定義する親パーサー (ヘルプ重複衝突を避けるため add_help=False)
    parent_parser = argparse.ArgumentParser(add_help=False)
    parent_parser.add_argument('--extra', action='store_true', help="全部入りイメージにする")
    parent_parser.add_argument('--ghcr', action='store_true', help=f"githubのソースコードを使用し、公式イメージ名 ({DEFAULT_IMAGE_BASE}) を使用する")
    parent_parser.add_argument('--noghcr', dest='ghcr', action='store_false', help=f"ローカルのソースコードを使用し、ローカルイメージ名 ({LOCAL_IMAGE_BASE}) を使用する (default)")
    parent_parser.add_argument('--debug', dest='debug', action='store_true', default=None, help="デバッグを有効にする (default: .env の REP2_BUILD_DEBUG、未設定なら無効)")
    parent_parser.add_argument('--nodebug', dest='debug', action='store_false', help="デバッグを無効にする")
    parent_parser.add_argument('--remote', action='store_true', default=None, help="リモートホストで実行する (SSH経由 / DOCKER_HOST=ssh://<REP2_REMOTE_HOST>、REP2_REMOTE_HOST と REP2_REMOTE_PATH の両方が必要)")
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
        if cmd == 'test':
            test_parser = subparsers.add_parser(cmd, help=desc)
            test_file_arg = test_parser.add_argument('test_file', help="テストファイルへのパス")
            if argcomplete:
                from argcomplete.completers import FilesCompleter
                test_file_arg.completer = FilesCompleter()
        else:
            subparsers.add_parser(cmd, help=desc)

    if argcomplete:
        argcomplete.autocomplete(parser)
    args, extra_args = parser.parse_known_args()
    args.debug = resolve_debug(args.debug)

    execute_command(args.command, args, extra_args)

if __name__ == "__main__":
    main()
