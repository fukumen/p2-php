#!/usr/bin/env python3
import os
import sys
import re
import json
import urllib.request
import urllib.error

# ANSI Color constants for visual representation
COLOR_RESET = "\033[0m"
COLOR_BOLD = "\033[1m"
COLOR_GREEN = "\033[32m"
COLOR_RED = "\033[31m"
COLOR_YELLOW = "\033[33m"
COLOR_CYAN = "\033[36m"

STATIC_PHP_LIST_URL = "https://dl.static-php.dev/v3/php-bin/common/?format=json"
REP2_STATIC_PHP_RELEASE_API_URL = "https://api.github.com/repos/fukumen/static-php-cli/releases/latest"
WINDOWS_PHP_URL_TEMPLATE = "https://windows.php.net/downloads/releases/archives/php-{version}-nts-Win32-vs17-x64.zip"
AIO_RELEASE_API_URL = "https://api.github.com/repos/fukumen/p2-php/releases/tags/latest"

PLATFORMS = {
    'linux-x86_64':   ('static',  'linux',  'x86_64',  'Linux x86_64'),
    'linux-aarch64':  ('static',  'linux',  'aarch64', 'Linux aarch64'),
    'macos-x86_64':   ('static',  'macos',  'x86_64',  'macOS x86_64'),
    'macos-aarch64':  ('static',  'macos',  'aarch64', 'macOS aarch64'),
    'windows-x86_64': ('windows', None,     None,      'Windows x64'),
}

def print_bold(text):
    print(f"{COLOR_BOLD}{text}{COLOR_RESET}")

def print_ok(text):
    print(f"{COLOR_GREEN}✔ {text}{COLOR_RESET}")

def print_warn(text):
    print(f"{COLOR_YELLOW}⚠ {text}{COLOR_RESET}")

def print_err(text):
    print(f"{COLOR_RED}✘ {text}{COLOR_RESET}")

def flatten_leaves(obj, prefix=''):
    """ネストした dict を 'a.b.c' → 値 のフラット辞書にする（キャッシュ比較用）"""
    leaves = {}
    if isinstance(obj, dict):
        for k, v in obj.items():
            key = f"{prefix}.{k}" if prefix else str(k)
            leaves.update(flatten_leaves(v, key))
    else:
        leaves[prefix] = obj
    return leaves


def fetch_json(url, headers=None):
    if headers is None:
        headers = {}
    if 'User-Agent' not in headers:
        headers['User-Agent'] = 'Mozilla/5.0 (Version Checker Tool; Anonymous)'
    req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=10) as response:
            return json.loads(response.read().decode('utf-8'))
    except Exception as e:
        print(f"Error fetching {url}: {e}", file=sys.stderr)
        return None

def get_ghcr_versions():
    # 1. Get anonymous token
    token_url = "https://ghcr.io/token?service=ghcr.io&scope=repository:fukumen/rep2:pull"
    token_data = fetch_json(token_url)
    if not token_data or 'token' not in token_data:
        return None
    token = token_data['token']
    
    headers = {
        'Authorization': f'Bearer {token}',
        'Accept': 'application/vnd.oci.image.index.v1+json,application/vnd.docker.distribution.manifest.list.v2+json'
    }
    
    # 2. Get Manifest List
    manifest_list_url = "https://ghcr.io/v2/fukumen/rep2/manifests/latest"
    manifest_list = fetch_json(manifest_list_url, headers=headers)
    if not manifest_list or 'manifests' not in manifest_list:
        return None
        
    # Find amd64 digest
    amd64_digest = None
    for m in manifest_list['manifests']:
        if m.get('platform', {}).get('architecture') == 'amd64':
            amd64_digest = m.get('digest')
            break
    if not amd64_digest:
        return None
        
    # 3. Get Config Digest from Manifest
    headers['Accept'] = 'application/vnd.oci.image.manifest.v1+json,application/vnd.docker.distribution.manifest.v2+json'
    manifest_url = f"https://ghcr.io/v2/fukumen/rep2/manifests/{amd64_digest}"
    manifest = fetch_json(manifest_url, headers=headers)
    if not manifest or 'config' not in manifest or 'digest' not in manifest['config']:
        return None
    config_digest = manifest['config']['digest']
    
    # 4. Get Config Blob
    blob_url = f"https://ghcr.io/v2/fukumen/rep2/blobs/{config_digest}"
    config = fetch_json(blob_url, headers=headers)
    if not config or 'config' not in config or 'Labels' not in config['config']:
        return None
        
    labels = config['config']['Labels']
    description = labels.get('org.opencontainers.image.description', '')
    
    versions = {}
    match_alpine = re.search(r'Alpine:\s*([0-9.]+)', description)
    match_php = re.search(r'PHP:\s*([0-9.]+)', description)
    match_caddy = re.search(r'Caddy:\s*v?([0-9.]+)', description)
    
    if match_alpine:
        versions['alpine'] = match_alpine.group(1)
    if match_php:
        versions['php'] = match_php.group(1)
    if match_caddy:
        versions['caddy'] = match_caddy.group(1)
        
    return versions

def get_aio_release_assets():
    data = fetch_json(AIO_RELEASE_API_URL)
    if not data or 'assets' not in data:
        return None
    return [a.get('name', '') for a in data['assets']]

def parse_aio_built_versions(asset_names):
    # 新形式: rep2-allinone_<COMMIT_DATE>-php<PHP>-caddy<CADDY>_<arch>.deb
    #         rep2-allinone-<COMMIT_DATE>-php<PHP>.caddy<CADDY>.<arch>.rpm
    #         rep2-allinone-<COMMIT_DATE>-php<PHP>-caddy<CADDY>-macos-<arch>.tar.gz
    #         rep2-allinone-<COMMIT_DATE>-php<PHP>-caddy<CADDY>-windows-<arch>.zip
    pat_deb = re.compile(r'^rep2-allinone_(?P<date>\d+)-php(?P<php>[0-9.]+)-caddy(?P<caddy>[0-9.]+)_(?P<arch>amd64|arm64)\.deb$')
    pat_rpm = re.compile(r'^rep2-allinone-(?P<date>\d+)-php(?P<php>[0-9.]+)\.caddy(?P<caddy>[0-9.]+)\.(?P<arch>x86_64|aarch64)\.rpm$')
    pat_mac = re.compile(r'^rep2-allinone-(?P<date>\d+)-php(?P<php>[0-9.]+)-caddy(?P<caddy>[0-9.]+)-macos-(?P<arch>x86_64|arm64)\.tar\.gz$')
    pat_zip = re.compile(r'^rep2-allinone-(?P<date>\d+)-php(?P<php>[0-9.]+)-caddy(?P<caddy>[0-9.]+)-windows-(?P<arch>x86_64|arm64)\.zip$')
    arch_map = {'amd64': 'x86_64', 'arm64': 'aarch64'}
    built = {}
    for name in asset_names or []:
        for pattern, os_name in ((pat_deb, 'linux'), (pat_rpm, 'linux'),
                                 (pat_mac, 'macos'), (pat_zip, 'windows')):
            m = pattern.match(name)
            if m:
                arch = arch_map.get(m.group('arch'), m.group('arch'))
                built[f"{os_name}-{arch}"] = {'php': m.group('php'), 'caddy': m.group('caddy')}
                break
    return built

def get_static_php_available_set(files):
    """アセット名一覧から {platform_key: {'cli': set(versions), 'fpm': set(versions)}} を返す（dl.static-php.dev の一覧と GitHub Releases の assets 両方に使用）"""
    available = {key: {'cli': set(), 'fpm': set()}
                 for key, (kind, _, _, _) in PLATFORMS.items() if kind == 'static'}
    pat = re.compile(r'^php-([0-9.]+)-(cli|fpm)-(linux|macos)-(x86_64|aarch64)\.tar\.gz$')
    for f in files or []:
        m = pat.match(f.get('name', ''))
        if not m:
            continue
        ver, sapi, plat_os, plat_arch = m.groups()
        key = f"{plat_os}-{plat_arch}"
        if key in available:
            available[key][sapi].add(ver)
    return available

def check_windows_php_zip(version):
    url = WINDOWS_PHP_URL_TEMPLATE.format(version=version)
    try:
        req = urllib.request.Request(url, method='HEAD')
        req.add_header('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) VersionChecker')
        with urllib.request.urlopen(req, timeout=5) as resp:
            return resp.status == 200
    except Exception:
        return False

def find_latest_static_version(platform_key, available):
    vers = available.get(platform_key, {}).get('cli', set()) | available.get(platform_key, {}).get('fpm', set())
    if not vers:
        return None
    return max(vers, key=lambda v: tuple(int(x) for x in re.findall(r'\d+', v)))

def check_binaries_for_platform(version, platform_key, available):
    """platform_key が必要とする static-php バイナリが提供済みか。(bool, 未提供ラベルのリスト) を返す"""
    kind, _, _, label = PLATFORMS[platform_key]
    missing = []
    if kind == 'windows':
        if not check_windows_php_zip(version):
            missing.append("Windows x64 NTS")
    else:
        for sapi in ('cli', 'fpm'):
            if version not in available[platform_key][sapi]:
                missing.append(f"{label} {sapi.upper()}")
    return len(missing) == 0, missing

def get_windows_php_latest_version(official_latest):
    if not official_latest:
        return "未提供"
    parts = list(map(int, official_latest.split('.')))
    # Try current patch and decrement to find latest available
    while parts[2] >= 0:
        test_ver = f"{parts[0]}.{parts[1]}.{parts[2]}"
        url = WINDOWS_PHP_URL_TEMPLATE.format(version=test_ver)
        try:
            req = urllib.request.Request(url, method='HEAD')
            req.add_header('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) VersionChecker')
            with urllib.request.urlopen(req, timeout=3) as resp:
                if resp.status == 200:
                    return test_ver
        except Exception:
            pass
        parts[2] -= 1
    return "未提供"

def check_docker_hub_tag(repository, tag):
    token_url = f"https://auth.docker.io/token?service=registry.docker.io&scope=repository:{repository}:pull"
    token_data = fetch_json(token_url)
    if not token_data or 'token' not in token_data:
        return False
    token = token_data['token']
    
    url = f"https://registry-1.docker.io/v2/{repository}/manifests/{tag}"
    headers = {
        'Authorization': f'Bearer {token}',
        'Accept': 'application/vnd.docker.distribution.manifest.v2+json,application/vnd.oci.image.manifest.v1+json'
    }
    
    req = urllib.request.Request(url, headers=headers, method='HEAD')
    try:
        with urllib.request.urlopen(req, timeout=5) as response:
            return response.status == 200
    except urllib.error.HTTPError as e:
        if e.code == 404:
            return False
        return False
    except Exception:
        return False

def get_docker_hub_manifest_digest(repository, tag):
    token_url = f"https://auth.docker.io/token?service=registry.docker.io&scope=repository:{repository}:pull"
    token_data = fetch_json(token_url)
    if not token_data or 'token' not in token_data:
        return None
    token = token_data['token']
    
    url = f"https://registry-1.docker.io/v2/{repository}/manifests/{tag}"
    headers = {
        'Authorization': f'Bearer {token}',
        'Accept': 'application/vnd.docker.distribution.manifest.v2+json,application/vnd.oci.image.manifest.v1+json'
    }
    
    req = urllib.request.Request(url, headers=headers, method='HEAD')
    try:
        with urllib.request.urlopen(req, timeout=5) as response:
            digest = response.headers.get('Docker-Content-Digest')
            return digest
    except Exception:
        return None

def parse_dockerfile_base(path):
    if not os.path.exists(path):
        return {}
    versions = {}
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()
        
    m_php = re.search(r'ARG\s+PHP_VERSION\s*=\s*"([^"]+)"', content)
    m_alpine = re.search(r'ARG\s+ALPINE_VERSION\s*=\s*"([^"]+)"', content)
    m_composer = re.search(r'ARG\s+COMPOSER_VERSION\s*=\s*"([^"]+)"', content)
    
    if m_php: versions['php'] = m_php.group(1)
    if m_alpine: versions['alpine'] = m_alpine.group(1)
    if m_composer: versions['composer'] = m_composer.group(1)
    return versions

def parse_dockerfile(path):
    if not os.path.exists(path):
        return {}
    versions = {}
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()
        
    m_caddy = re.search(r'ARG\s+CADDY_VERSION\s*=\s*"([^"]+)"', content)
    if m_caddy: versions['caddy'] = m_caddy.group(1)
    return versions

def parse_makefile(path):
    if not os.path.exists(path):
        return {}
    versions = {}
    with open(path, 'r', encoding='utf-8') as f:
        for line in f:
            m = re.match(r'^([A-Z0-9_]+)\s*=\s*([0-9.]+)', line.strip())
            if m:
                var_name, value = m.group(1), m.group(2)
                # PHP_VERSION は linux-arm64 向けの上書き定義が後続にあるため先勝ちとする
                if var_name == 'PHP_VERSION':
                    if 'php_default' not in versions:
                        versions['php_default'] = value
                elif var_name == 'CADDY_VERSION':
                    versions['caddy'] = value
                elif var_name == 'COMPOSER_VERSION':
                    versions['composer'] = value
    return versions

def get_official_alpine():
    url = "https://alpinelinux.org/releases.json"
    data = fetch_json(url)
    if not data:
        return None, {}
    
    latest_stable_branch = data.get("latest_stable", "") # e.g. "v3.23"
    
    branch_releases = {}
    for branch in data.get("release_branches", []):
        rel_branch = branch.get("rel_branch", "") # e.g. "v3.23" or "edge"
        if rel_branch == "edge":
            continue
        releases = branch.get("releases", [])
        if releases:
            norm_branch = rel_branch if rel_branch.startswith('v') else 'v' + rel_branch
            branch_releases[norm_branch] = releases[0].get("version", "")
            
    norm_latest_stable = latest_stable_branch if latest_stable_branch.startswith('v') else 'v' + latest_stable_branch
    latest_stable_ver = branch_releases.get(norm_latest_stable)
    return latest_stable_ver, branch_releases

def get_official_php():
    url = "https://www.php.net/releases/active.php"
    data = fetch_json(url)
    if not data:
        return {}
        
    series_versions = {}
    for major in data.values():
        for series_name, series_info in major.items():
            version = series_info.get("version")
            if version:
                series_versions[series_name] = version
    return series_versions

def get_official_caddy():
    url = "https://api.github.com/repos/caddyserver/caddy/releases/latest"
    data = fetch_json(url)
    if not data:
        return None
    tag = data.get("tag_name", "")
    return tag.lstrip('v')

def get_official_composer():
    url = "https://getcomposer.org/versions"
    data = fetch_json(url)
    if not data or 'stable' not in data or len(data['stable']) == 0:
        return None
    return data['stable'][0].get("version")

def main():
    import argparse
    parser = argparse.ArgumentParser(description="rep2 dependency version checker")
    parser.add_argument('--fail-on-update', action='store_true', help="Fail with exit code 1 if any updates are required")
    parser.add_argument('--only-on-change', action='store_true', help="Only fail if official versions changed compared to the last run cache")
    args = parser.parse_args()

    script_dir = os.path.dirname(os.path.abspath(__file__))
    rep2_root = os.path.dirname(script_dir)

    docker_rep2_dir = os.path.join(rep2_root, "deploy", "docker-rep2")
    rep2_allinone_dir = os.path.join(rep2_root, "deploy", "rep2-allinone")
    
    # Parse local files
    d_base_path = os.path.join(docker_rep2_dir, "docker", "Dockerfile.base")
    d_path = os.path.join(docker_rep2_dir, "docker", "Dockerfile")
    m_path = os.path.join(rep2_allinone_dir, "Makefile")
    
    d_base_vers = parse_dockerfile_base(d_base_path)
    d_vers = parse_dockerfile(d_path)
    makefile_vers = parse_makefile(m_path)
    
    print_bold("=== 1. リポジトリ設定バージョンの取得 ===")
    print(f"docker-rep2 (Dockerfile.base/Dockerfile):")
    print(f"  - Alpine 系列:   {d_base_vers.get('alpine', '未設定')}")
    print(f"  - PHP 系列:      {d_base_vers.get('php', '未設定')}")
    print(f"  - Composer 指定: {d_base_vers.get('composer', '未設定')}")
    print(f"  - Caddy 系列:    {d_vers.get('caddy', '未設定')}")
    print(f"rep2-allinone (Makefile):")
    print(f"  - PHP (Default): {makefile_vers.get('php_default', '未設定')}")
    print(f"  - Composer 指定: {makefile_vers.get('composer', '未設定')}")
    print(f"  - Caddy 指定:    {makefile_vers.get('caddy', '未設定')}")
    print(f"  ※ rep2-allinone の PHP/Caddy 更新判定は GitHub Release 公開済みパッケージとの比較で行います")
    
    # Fetch GHCR versions
    print_bold("\n=== 2. GHCRビルド済みイメージ (rep2:latest) の取得 ===")
    ghcr_vers = get_ghcr_versions()
    if ghcr_vers:
        print(f"  - Alpine 実装:   {ghcr_vers.get('alpine', '取得失敗')}")
        print(f"  - PHP 実装:      {ghcr_vers.get('php', '取得失敗')}")
        print(f"  - Caddy 実装:    {ghcr_vers.get('caddy', '取得失敗')}")
    else:
        print_err("GHCRイメージのメタデータ取得に失敗しました。")
        ghcr_vers = {}
        
    # Fetch static php binaries info（dl.static-php.dev は表示専用、判定源は rep2-allinone 向け static-php の Releases）
    static_php_files = fetch_json(STATIC_PHP_LIST_URL)
    static_php_available = get_static_php_available_set(static_php_files)
    rep2_release = fetch_json(REP2_STATIC_PHP_RELEASE_API_URL)
    if rep2_release is not None and 'assets' in rep2_release:
        rep2_static_php_assets = rep2_release['assets']
        rep2_static_php_tag = rep2_release.get('tag_name')
    else:
        rep2_static_php_assets = None
        rep2_static_php_tag = None
    rep2_available = get_static_php_available_set(rep2_static_php_assets)
    
    # Fetch Official Upstreams
    print_bold("\n=== 3. 公式最新リリースバージョンの取得 ===")

    # ターゲット系列（リポジトリ設定値）。Docker Hub 反映確認とセクション4の判定で再利用する
    target_php_series = d_base_vers.get('php')
    target_alpine_series = d_base_vers.get('alpine')
    target_caddy_series = d_vers.get('caddy')
    alias_tag = None
    alias_digest = None
    patch_digest = None
    caddy_alias_digest = None
    caddy_patch_digest = None
    alpine_exists = False
    base_exists = False
    docker_hub_digests = {}

    # Alpine（表示はターゲット系列のみ。全系列のデータは新系列判定に使用）
    alp_latest, alp_branches = get_official_alpine()
    print(f"Alpine Linux:")
    print(f"  - 最新安定系列: {alp_latest}")
    target_alpine_latest = None
    if target_alpine_series:
        target_alpine_latest = alp_branches.get(target_alpine_series) or alp_branches.get('v' + target_alpine_series)
    if target_alpine_latest:
        print(f"  - {target_alpine_series}系列最新:  {target_alpine_latest}")
        alpine_exists = check_docker_hub_tag("library/alpine", target_alpine_latest)
        if alpine_exists:
            print_ok(f"  - Docker Hub (alpine:{target_alpine_latest}): 提供済み")
        else:
            print_warn(f"  - Docker Hub (alpine:{target_alpine_latest}): 未提供")
        docker_hub_digests['alpine_patch_tag'] = target_alpine_latest
        docker_hub_digests['alpine_patch_exists'] = alpine_exists
    elif target_alpine_series:
        print_warn(f"  - {target_alpine_series}系列最新:  取得失敗")

    # PHP（Alpine と同様に最新安定系列を常に表示し、ターゲット系列の最新を併記する。全系列のデータは新系列判定に使用）
    php_releases = get_official_php()
    print(f"PHP:")

    def parse_php_series(s):
        m = re.match(r'^(\d+)\.(\d+)', s)
        if m:
            return (int(m.group(1)), int(m.group(2)))
        return (0, 0)

    latest_php_series = max(php_releases.keys(), key=parse_php_series) if php_releases else None
    if latest_php_series:
        print(f"  - 最新安定系列: {php_releases[latest_php_series]}")
    else:
        print_warn("  - 最新安定系列:  取得失敗")
    latest_official_php = php_releases.get(target_php_series)
    if latest_official_php:
        print(f"  - {target_php_series}系列最新:  {latest_official_php}")
    elif target_php_series:
        print_warn(f"  - {target_php_series}系列最新:  取得失敗")
    else:
        print_warn("  - Dockerfile.base に PHP 系列指定がありません")
    if target_php_series and target_alpine_series:
        alias_tag = f"{target_php_series}-fpm-alpine{target_alpine_series}"
        docker_hub_digests['php_alias_tag'] = alias_tag
        base_exists = check_docker_hub_tag("library/php", alias_tag)
        if not base_exists:
            print_warn(f"  - Docker Hub: ベースイメージ php:{alias_tag} が存在しません")
        elif latest_official_php:
            patch_tag = f"{latest_official_php}-fpm-alpine{target_alpine_series}"
            alias_digest = get_docker_hub_manifest_digest("library/php", alias_tag)
            patch_digest = get_docker_hub_manifest_digest("library/php", patch_tag)
            docker_hub_digests['php_patch_tag'] = patch_tag
            docker_hub_digests['php_alias_digest'] = alias_digest
            docker_hub_digests['php_patch_digest'] = patch_digest
            if alias_digest and patch_digest and alias_digest == patch_digest:
                print_ok(f"  - Docker Hub (php:{patch_tag}): 反映済み")
            elif alias_digest and patch_digest:
                print_warn(f"  - Docker Hub (php:{patch_tag}): タグは存在しますが、エイリアス {alias_tag} には未反映")
            else:
                print_warn(f"  - Docker Hub (php:{patch_tag}): manifest 取得失敗")
        
    # Caddy（同系列のパッチリリースかを確認し、Docker Hub 反映状況を表示。結果はセクション4の判定でも再利用する）
    caddy_latest = get_official_caddy()
    print(f"Caddy 最新安定:  {caddy_latest}")
    if target_caddy_series and caddy_latest and caddy_latest.startswith(target_caddy_series + '.'):
        caddy_alias_tag = f"{target_caddy_series}-alpine"
        caddy_patch_tag = f"{caddy_latest}-alpine"
        caddy_alias_digest = get_docker_hub_manifest_digest("library/caddy", caddy_alias_tag)
        caddy_patch_digest = get_docker_hub_manifest_digest("library/caddy", caddy_patch_tag)
        docker_hub_digests['caddy_alias_tag'] = caddy_alias_tag
        docker_hub_digests['caddy_patch_tag'] = caddy_patch_tag
        docker_hub_digests['caddy_alias_digest'] = caddy_alias_digest
        docker_hub_digests['caddy_patch_digest'] = caddy_patch_digest
        if caddy_alias_digest and caddy_patch_digest and caddy_alias_digest == caddy_patch_digest:
            print_ok(f"  - Docker Hub (caddy:{caddy_patch_tag}): 反映済み")
        elif caddy_alias_digest and caddy_patch_digest:
            print_warn(f"  - Docker Hub (caddy:{caddy_patch_tag}): タグは存在しますが、エイリアス {caddy_alias_tag} には未反映")
        else:
            print_warn(f"  - Docker Hub (caddy:{caddy_patch_tag}): manifest 取得失敗")
    
    # Composer
    composer_latest = get_official_composer()
    print(f"Composer 最新安定: {composer_latest}")
    
    # Static PHP（dl.static-php.dev は参考表示のみ。更新判定には使用しない）
    print(f"dl.static-php.dev 提供の最新 PHP バージョン（参考）:")
    if static_php_files is None:
        print_warn("  - 取得失敗（参考表示のため判定には影響しません）")
    else:
        for key, (_, _, _, label) in PLATFORMS.items():
            if key == 'windows-x86_64':
                continue
            ver = find_latest_static_version(key, static_php_available)
            print(f"  - {label}: {ver or '未提供'}")

    # rep2-allinone 向け static-php（fukumen/static-php-cli）の提供状況（更新判定に使用）
    print(f"rep2-allinone 向け static-php の提供状況（更新判定に使用）:")
    if rep2_static_php_assets is None:
        print_err("  - リリース情報の取得に失敗しました（バイナリ提供有無の確認をスキップします）")
    else:
        print(f"  - リリースタグ: {rep2_static_php_tag or '不明'}")
        for key, (_, _, _, label) in PLATFORMS.items():
            if key == 'windows-x86_64':
                continue
            ver = find_latest_static_version(key, rep2_available)
            print(f"  - {label}: {ver or '未提供'}")
        
    # Windows PHP (from windows.php.net) - also used for caching
    target_php_series = d_base_vers.get('php') or '8.5'
    latest_official_php = php_releases.get(target_php_series)
    win_latest_for_cache = "未提供"
    if latest_official_php:
        win_latest = get_windows_php_latest_version(latest_official_php)
        print(f"windows.php.net（archives）提供の最新 PHP バージョン ({target_php_series}系列):")
        print(f"  - Windows (NTS):   {win_latest}")
        win_latest_for_cache = win_latest
    
    # --- 判定セクション ---
    print_bold("\n=== 4. 更新要否判定 ===")
    
    # docker-rep2 の判定
    print_bold("[docker-rep2 判定]")
    
    # 1. Base Image Rebuild（Docker Hub の確認結果はセクション3で取得済みのため再利用する）
    rebuild_reasons = []
    if base_exists:
        # PHP
        if target_php_series and target_alpine_series and ghcr_vers.get('php'):
            latest_patch_php = php_releases.get(target_php_series)
            if latest_patch_php and latest_patch_php != ghcr_vers.get('php'):
                if alias_digest and patch_digest and alias_digest == patch_digest:
                    rebuild_reasons.append(f"PHP {target_php_series}系列に最新パッチ {latest_patch_php} が存在し、Docker Hubイメージに反映済み（GHCRは {ghcr_vers.get('php')}）")

        # Alpine
        if target_alpine_series and target_php_series and ghcr_vers.get('alpine'):
            if target_alpine_latest and target_alpine_latest != ghcr_vers.get('alpine') and alpine_exists:
                rebuild_reasons.append(f"Alpine {target_alpine_series}系列に最新パッチ {target_alpine_latest} が存在（GHCRは {ghcr_vers.get('alpine')}、Docker Hubイメージあり）")

        # Caddy
        if target_caddy_series and ghcr_vers.get('caddy') and caddy_latest:
            if caddy_latest.startswith(target_caddy_series + '.') and caddy_latest != ghcr_vers.get('caddy'):
                if caddy_alias_digest and caddy_patch_digest and caddy_alias_digest == caddy_patch_digest:
                    rebuild_reasons.append(f"Caddy {target_caddy_series}系列に最新パッチ {caddy_latest} が存在し、Docker Hubイメージに反映済み（GHCRは {ghcr_vers.get('caddy')}）")
            
    if rebuild_reasons:
        print_warn("ベースイメージまたはCaddyの再ビルドが必要です。")
        for reason in rebuild_reasons:
            print(f"  - {reason}")
    else:
        print_ok("ベースイメージおよびCaddyは最新パッチを維持しています。再ビルドは不要です。")
        
    # 2. Dockerfile / Dockerfile.base 更新
    update_reasons = []
    # Alpine series update
    if alp_latest and target_alpine_series and target_php_series:
        alp_latest_clean = alp_latest.lstrip('v')
        alp_latest_mm = '.'.join(alp_latest_clean.split('.')[:2])
        if alp_latest_mm != target_alpine_series:
            # We must check if the alias tag is available on Docker Hub for building
            php_tag = f"{target_php_series}-fpm-alpine{alp_latest_mm}"
            if check_docker_hub_tag("library/php", php_tag):
                update_reasons.append(f"Alpine の新系列 {alp_latest_mm} が利用可能（Dockerfile.baseは {target_alpine_series}、Docker Hubイメージあり）")
            else:
                print_warn(f"Alpine の新系列 {alp_latest_mm} が公式リリースされましたが、Docker Hub に php:{php_tag} がまだ用意されていません。")
    # PHP series update（latest_php_series はセクション3で数値比較により算出済み）
    if latest_php_series and target_php_series and latest_php_series != target_php_series:
        update_reasons.append(f"PHP の新系列 {latest_php_series} が利用可能（Dockerfile.baseは {target_php_series}）")
    # Caddy series update
    if caddy_latest and target_caddy_series:
        caddy_latest_mm = '.'.join(caddy_latest.split('.')[:2])
        if caddy_latest_mm != target_caddy_series:
            alias_tag = f"{caddy_latest_mm}-alpine"
            patch_tag = f"{caddy_latest}-alpine"

            alias_digest = get_docker_hub_manifest_digest("library/caddy", alias_tag)
            patch_digest = get_docker_hub_manifest_digest("library/caddy", patch_tag)
            docker_hub_digests['caddy_alias_digest'] = alias_digest
            docker_hub_digests['caddy_patch_digest'] = patch_digest
            docker_hub_digests['caddy_alias_tag'] = alias_tag
            docker_hub_digests['caddy_patch_tag'] = patch_tag
            
            if alias_digest and patch_digest and alias_digest == patch_digest:
                update_reasons.append(f"Caddy の新系列 {caddy_latest_mm} が利用可能（Dockerfileは {target_caddy_series}、Docker Hubイメージに反映済み）")
            else:
                print_warn(f"Caddy の新系列 {caddy_latest_mm} が公式リリースされましたが、Docker Hubのエイリアスイメージ {alias_tag} への反映がまだ完了していません。")
    # Composer update
    target_composer = d_base_vers.get('composer')
    if composer_latest and target_composer and composer_latest != target_composer:
        update_reasons.append(f"Composer の新バージョン {composer_latest} がリリース（Dockerfile.baseは {target_composer}）")
        
    if update_reasons:
        print_warn("Dockerfile/Dockerfile.base の更新が推奨されます。")
        for reason in update_reasons:
            print(f"  - {reason}")
    else:
        print_ok("Dockerfile/Dockerfile.base のバージョン系列指定は最新です。")
        
    # rep2-allinone の判定
    print_bold("\n[rep2-allinone 判定]")
    aio_update_reasons = []
    
    # GitHub Release (latest) から公開済みパッケージの実ビルドバージョンを取得
    asset_names = get_aio_release_assets()
    aio_built = {}
    if asset_names is None:
        print_err("rep2-allinone の latest リリース情報の取得に失敗しました。allinone の PHP/Caddy 判定をスキップします。")
    else:
        aio_built = parse_aio_built_versions(asset_names)
        print("GitHub Release (latest) 公開済みパッケージ:")
        for key in sorted(aio_built):
            print(f"  - {key}: PHP {aio_built[key]['php']} / Caddy {aio_built[key]['caddy']}")
        unparsed_platforms = [key for key in PLATFORMS if key not in aio_built]
        if unparsed_platforms:
            print_warn(f"成果物名から判別できなかったプラットフォームがあります: {', '.join(unparsed_platforms)}")
    
    # rep2-allinone 向け static-php のリリース情報が取得できない場合はバイナリ確認をスキップする（dl.static-php.dev へフォールバックしない）
    rep2_bin_check_enabled = rep2_static_php_assets is not None
    if not rep2_bin_check_enabled:
        print_err("rep2-allinone 向け static-php のリリース情報の取得に失敗したため、PHP バイナリの提供有無を確認できません。")

    # PHP: プラットフォーム別に「実ビルド vs 公式最新パッチ vs 必要バイナリの提供状況」
    for key in sorted(aio_built):
        if key not in PLATFORMS:
            print_warn(f"{key}: 本チェッカーが判別できないプラットフォームの成果物です。更新判定の対象外とします")
            continue
        built_php = aio_built[key]['php']
        series = '.'.join(built_php.split('.')[:2])
        latest_patch_php = php_releases.get(series)
        if not latest_patch_php or latest_patch_php == built_php:
            continue
        if not rep2_bin_check_enabled:
            continue
        bin_available, missing = check_binaries_for_platform(latest_patch_php, key, rep2_available)
        if bin_available:
            aio_update_reasons.append(f"{key}: PHP {series}系列に最新パッチ {latest_patch_php} が存在し、必要なバイナリも提供済み（公開済みパッケージは {built_php}）")
        else:
            print_warn(f"{key}: PHP {latest_patch_php} が公式リリースされていますが、必要なバイナリが未提供のため更新判定を見送ります（公開済みは {built_php}）。rep2-allinone 向け static-php のビルド workflow の実行が必要です")
            print(f"  バイナリ確認状況:")
            for item in missing:
                print(f"    - {item}: 未提供")
            
    # Caddy: プラットフォーム別に「実ビルド vs 公式最新」
    if caddy_latest:
        caddy_latest_series = '.'.join(caddy_latest.split('.')[:2])
        if not aio_built:
            # フォールバック: 成果物が1つも取れない場合は従来どおり Makefile 指定と比較
            aio_caddy = makefile_vers.get('caddy')
            if aio_caddy and caddy_latest != aio_caddy:
                aio_update_reasons.append(f"Caddy の最新バージョン {caddy_latest} がリリース（Makefileは {aio_caddy}）")
        else:
            for key in sorted(aio_built):
                built_caddy = aio_built[key]['caddy']
                built_series = '.'.join(built_caddy.split('.')[:2])
                if built_series == caddy_latest_series:
                    if built_caddy != caddy_latest:
                        aio_update_reasons.append(f"{key}: Caddy {built_series}系列に最新 {caddy_latest} が存在（公開済みパッケージは {built_caddy}）")
                else:
                    print_warn(f"{key}: 公開済み Caddy {built_caddy} は最新系列 {caddy_latest_series} と異なります")
        
    # Composer（成果物名に現れないため Makefile 指定との直接比較。docker-rep2 の Composer 判定と同じモデル）
    aio_composer = makefile_vers.get('composer')
    if composer_latest and aio_composer and composer_latest != aio_composer:
        aio_update_reasons.append(f"Composer の最新バージョン {composer_latest} がリリース（Makefileは {aio_composer}）")
        
    if aio_update_reasons:
        print_warn("Makefile の更新が必要です。")
        for reason in aio_update_reasons:
            print(f"  - {reason}")
    else:
        print_ok("rep2-allinone の Makefile 指定バージョンはすべて最新です。")
        
    # Build local config snapshot for change detection
    local_config = {
        'docker_php': d_base_vers.get('php'),
        'docker_alpine': d_base_vers.get('alpine'),
        'docker_composer': d_base_vers.get('composer'),
        'docker_caddy': d_vers.get('caddy'),
        'aio_php_default': makefile_vers.get('php_default'),
        'aio_caddy': makefile_vers.get('caddy'),
        'aio_composer': makefile_vers.get('composer'),
        'aio_built_php': {key: aio_built[key]['php'] for key in aio_built},
    }
    
    # Build Docker Hub reflection status snapshot（タグ・digest はセクション3/4で docker_hub_digests に収集済み）
    docker_hub_status = dict(docker_hub_digests)
    
    # Build GHCR image version snapshot
    ghcr_snapshot = {
        'php': ghcr_vers.get('php'),
        'alpine': ghcr_vers.get('alpine'),
        'caddy': ghcr_vers.get('caddy'),
    }
    
    # Build binary availability snapshot（判定源は rep2-allinone 向け static-php の Releases。dl.static-php.dev は参考値として別キーに記録）
    static_bin_status = {}
    for key in PLATFORMS:
        if key == 'windows-x86_64':
            continue
        if rep2_static_php_assets is None:
            continue
        static_bin_status[key] = find_latest_static_version(key, rep2_available) or 'none'
    # Windows: reuse result from display section
    static_bin_status['windows'] = win_latest_for_cache
    if rep2_static_php_tag is not None:
        static_bin_status['release_tag'] = rep2_static_php_tag

    dl_reference_status = {}
    for key in PLATFORMS:
        if key == 'windows-x86_64':
            continue
        if static_php_files is None:
            dl_reference_status[key] = 'unknown'
        else:
            dl_reference_status[key] = find_latest_static_version(key, static_php_available) or 'none'
    
    # Cache and comparison logic
    versions_changed = True
    if args.only_on_change:
        cache_file = os.path.join(script_dir, "prev_versions.json")
        current_versions = {
            'alpine': alp_latest,
            'php_series': php_releases,
            'caddy': caddy_latest,
            'composer': composer_latest,
            'local_config': local_config,
            'static_bin_status': static_bin_status,
            'dl_reference_status': dl_reference_status,
            'docker_hub_status': docker_hub_status,
            'ghcr_snapshot': ghcr_snapshot,
        }
        
        if os.path.exists(cache_file):
            try:
                with open(cache_file, 'r', encoding='utf-8') as f:
                    prev_versions = json.load(f)
                # 取得失敗のプレースホルダ（'unknown'）と、片側にしか存在しないキーは
                # 比較対象から除外する（取得失敗・キャッシュスキーマ変化を「変化あり」と誤判定しないため）
                prev_leaves = flatten_leaves(prev_versions)
                cur_leaves = flatten_leaves(current_versions)
                common = prev_leaves.keys() & cur_leaves.keys()
                if all(prev_leaves[k] == cur_leaves[k]
                       or prev_leaves[k] == 'unknown' or cur_leaves[k] == 'unknown'
                       for k in common):
                    versions_changed = False
            except Exception as e:
                print(f"Error reading cache file: {e}", file=sys.stderr)
                
        # Save the current versions to cache for the next run
        try:
            with open(cache_file, 'w', encoding='utf-8') as f:
                json.dump(current_versions, f, indent=2, ensure_ascii=False)
        except Exception as e:
            print(f"Error saving cache file: {e}", file=sys.stderr)
        
    has_updates = len(rebuild_reasons) > 0 or len(update_reasons) > 0 or len(aio_update_reasons) > 0
    if args.fail_on_update and has_updates:
        if args.only_on_change and not versions_changed:
            print_bold("\n更新が必要な項目がありますが、前回の実行時から公式バージョンに変化がないため、ステータス 0 で終了します。")
            sys.exit(0)
        else:
            print_bold("\n更新が必要な項目があるため、ステータス 1 で終了します。")
            sys.exit(1)

if __name__ == "__main__":
    main()
