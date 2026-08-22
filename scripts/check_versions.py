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
WINDOWS_PHP_URL_TEMPLATE = "https://windows.php.net/downloads/releases/php-{version}-nts-Win32-vs17-x64.zip"
AIO_RELEASE_API_URL = "https://api.github.com/repos/fukumen/rep2-allinone/releases/tags/latest"

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
    """Release 成果物ファイル名から {platform_key: {'php': ver, 'caddy': ver}} を抽出する"""
    # rpm のみ Caddy とタイムスタンプの区切りが "." のため、Caddy を遅延マッチにしてタイムスタンプを分離する
    pat_deb = re.compile(r'^rep2-allinone_[\d.]+-php(?P<php>[0-9.]+)-caddy(?P<caddy>[0-9.]+)(?:\+\d+)?_(?P<arch>amd64|arm64)\.deb$')
    pat_rpm = re.compile(r'^rep2-allinone-[\d.]+-php(?P<php>[0-9.]+)\.caddy(?P<caddy>[0-9.]+?)\.\d+\.(?P<arch>x86_64|aarch64)\.rpm$')
    pat_mac = re.compile(r'^rep2-allinone-[\d.]+-php(?P<php>[0-9.]+)-caddy(?P<caddy>[0-9.]+)(?:\+\d+)?-macos-(?P<arch>x86_64|arm64)\.tar\.gz$')
    pat_zip = re.compile(r'^rep2-allinone-[\d.]+-php(?P<php>[0-9.]+)-caddy(?P<caddy>[0-9.]+)(?:\+\d+)?-windows-(?P<arch>x86_64|arm64)\.zip$')
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
    """dl.static-php.dev の一覧から {platform_key: {'cli': set(versions), 'fpm': set(versions)}} を返す"""
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
    """platform_key が必要とする上流バイナリが提供済みか。(bool, 未提供ラベルのリスト) を返す"""
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
        url = f"https://windows.php.net/downloads/releases/php-{test_ver}-nts-Win32-vs17-x64.zip"
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
    rep2_root = os.path.dirname(os.path.dirname(script_dir))
    
    docker_rep2_dir = os.path.join(rep2_root, "docker-rep2")
    rep2_allinone_dir = os.path.join(rep2_root, "rep2-allinone")
    
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
        
    # Fetch static php files list once
    static_php_files = fetch_json(STATIC_PHP_LIST_URL)
    static_php_available = get_static_php_available_set(static_php_files)
    
    # Fetch Official Upstreams
    print_bold("\n=== 3. 公式最新リリースバージョンの取得 ===")
    
    # Alpine
    alp_latest, alp_branches = get_official_alpine()
    print(f"Alpine Linux:")
    print(f"  - 最新安定系列: {alp_latest}")
    
    def parse_branch_version(b):
        m = re.match(r'^v?(\d+)\.(\d+)', b)
        if m:
            return (int(m.group(1)), int(m.group(2)))
        return (0, 0)
        
    sorted_branches = sorted(alp_branches.keys(), key=parse_branch_version, reverse=True)
    for br in sorted_branches[:3]:
        print(f"  - {br}系列最新:  {alp_branches[br]}")
        
    # PHP
    php_releases = get_official_php()
    print(f"PHP:")
    
    def parse_php_series(s):
        m = re.match(r'^(\d+)\.(\d+)', s)
        if m:
            return (int(m.group(1)), int(m.group(2)))
        return (0, 0)
        
    sorted_php_series = sorted(php_releases.keys(), key=parse_php_series, reverse=True)
    for series in sorted_php_series[:4]:
        print(f"  - {series}系列最新:  {php_releases[series]}")
        
    # Caddy
    caddy_latest = get_official_caddy()
    print(f"Caddy 最新安定:  {caddy_latest}")
    
    # Composer
    composer_latest = get_official_composer()
    print(f"Composer 最新安定: {composer_latest}")
    
    # Static PHP
    print(f"dl.static-php.dev 提供の最新 PHP バージョン:")
    for key, (_, _, _, label) in PLATFORMS.items():
        if key == 'windows-x86_64':
            continue
        ver = find_latest_static_version(key, static_php_available)
        print(f"  - {label}: {ver or '未提供'}")
        
    # Windows PHP (from windows.php.net) - also used for caching
    target_php_series = d_base_vers.get('php') or '8.5'
    latest_official_php = php_releases.get(target_php_series)
    win_latest_for_cache = "未提供"
    if latest_official_php:
        win_latest = get_windows_php_latest_version(latest_official_php)
        print(f"windows.php.net 提供の最新 PHP バージョン ({target_php_series}系列):")
        print(f"  - Windows (NTS):   {win_latest}")
        win_latest_for_cache = win_latest
    
    # --- 判定セクション ---
    print_bold("\n=== 4. 更新要否判定 ===")
    
    # docker-rep2 の判定
    print_bold("[docker-rep2 判定]")
    
    # 1. Base Image Rebuild
    rebuild_reasons = []
    # PHP
    target_php_series = d_base_vers.get('php')
    target_alpine_series = d_base_vers.get('alpine')
    # Check if local base image exists on Docker Hub
    base_exists = False
    if target_php_series and target_alpine_series:
        local_base_tag = f"{target_php_series}-fpm-alpine{target_alpine_series}"
        base_exists = check_docker_hub_tag("library/php", local_base_tag)
        if not base_exists:
            print_warn(f"ローカル設定に対応するベースイメージ php:{local_base_tag} が Docker Hub に存在しません。")

    # Collect Docker Hub digests for both decisions and caching (avoid duplicate calls)
    docker_hub_digests = {}
    
    if base_exists:
        # PHP
        if target_php_series and ghcr_vers.get('php') and target_alpine_series:
            latest_patch_php = php_releases.get(target_php_series)
            if latest_patch_php and latest_patch_php != ghcr_vers.get('php'):
                alias_tag = f"{target_php_series}-fpm-alpine{target_alpine_series}"
                patch_tag = f"{latest_patch_php}-fpm-alpine{target_alpine_series}"
                
                alias_digest = get_docker_hub_manifest_digest("library/php", alias_tag)
                patch_digest = get_docker_hub_manifest_digest("library/php", patch_tag)
                docker_hub_digests['php_alias_digest'] = alias_digest
                docker_hub_digests['php_patch_digest'] = patch_digest
                docker_hub_digests['php_alias_tag'] = alias_tag
                docker_hub_digests['php_patch_tag'] = patch_tag
                
                if alias_digest and patch_digest and alias_digest == patch_digest:
                    rebuild_reasons.append(f"PHP {target_php_series}系列に最新パッチ {latest_patch_php} が存在し、Docker Hubイメージに反映済み（GHCRは {ghcr_vers.get('php')}）")
                else:
                    print_warn(f"PHP {latest_patch_php} が公式リリースされていますが、Docker Hubのエイリアスイメージ {alias_tag} への反映がまだ完了していません。")
                
        # Alpine
        if target_alpine_series and ghcr_vers.get('alpine') and target_php_series:
            latest_patch_alpine = alp_branches.get(target_alpine_series) or alp_branches.get('v' + target_alpine_series)
            if latest_patch_alpine and latest_patch_alpine != ghcr_vers.get('alpine'):
                alpine_exists = check_docker_hub_tag("library/alpine", latest_patch_alpine)
                docker_hub_digests['alpine_patch_tag'] = latest_patch_alpine
                docker_hub_digests['alpine_patch_exists'] = alpine_exists
                if alpine_exists:
                    rebuild_reasons.append(f"Alpine {target_alpine_series}系列に最新パッチ {latest_patch_alpine} が存在（GHCRは {ghcr_vers.get('alpine')}、Docker Hubイメージあり）")
                else:
                    print_warn(f"Alpine {latest_patch_alpine} が公式リリースされていますが、Docker Hub に alpine:{latest_patch_alpine} がまだ用意されていません。")
            
        # Caddy
        target_caddy_series = d_vers.get('caddy')
        if target_caddy_series and ghcr_vers.get('caddy') and caddy_latest:
            if caddy_latest.startswith(target_caddy_series + '.'):
                if caddy_latest != ghcr_vers.get('caddy'):
                    alias_tag = f"{target_caddy_series}-alpine"
                    patch_tag = f"{caddy_latest}-alpine"
                    
                    alias_digest = get_docker_hub_manifest_digest("library/caddy", alias_tag)
                    patch_digest = get_docker_hub_manifest_digest("library/caddy", patch_tag)
                    docker_hub_digests['caddy_alias_digest'] = alias_digest
                    docker_hub_digests['caddy_patch_digest'] = patch_digest
                    docker_hub_digests['caddy_alias_tag'] = alias_tag
                    docker_hub_digests['caddy_patch_tag'] = patch_tag
                    
                    if alias_digest and patch_digest and alias_digest == patch_digest:
                        rebuild_reasons.append(f"Caddy {target_caddy_series}系列に最新パッチ {caddy_latest} が存在し、Docker Hubイメージに反映済み（GHCRは {ghcr_vers.get('caddy')}）")
                    else:
                        print_warn(f"Caddy {caddy_latest} が公式リリースされていますが、Docker Hubのエイリアスイメージ {alias_tag} への反映がまだ完了していません。")
            
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
    # PHP series update
    latest_php_series = max(php_releases.keys()) if php_releases else None
    if latest_php_series and target_php_series and latest_php_series != target_php_series:
        update_reasons.append(f"PHP の新系列 {latest_php_series} が利用可能（Dockerfile.baseは {target_php_series}）")
    # Caddy series update
    target_caddy_series = d_vers.get('caddy')
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
        bin_available, missing = check_binaries_for_platform(latest_patch_php, key, static_php_available)
        if bin_available:
            aio_update_reasons.append(f"{key}: PHP {series}系列に最新パッチ {latest_patch_php} が存在し、必要なバイナリも提供済み（公開済みパッケージは {built_php}）")
        else:
            print_warn(f"{key}: PHP {latest_patch_php} が公式リリースされていますが、必要なバイナリが未提供のため更新判定を見送ります（公開済みは {built_php}）")
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
    
    # Build Docker Hub reflection status snapshot (reuse digests from decision section)
    docker_hub_status = {}
    if base_exists and target_php_series and target_alpine_series:
        alias_tag = f"{target_php_series}-fpm-alpine{target_alpine_series}"
        latest_patch_php = php_releases.get(target_php_series)
        if latest_patch_php:
            patch_tag = f"{latest_patch_php}-fpm-alpine{target_alpine_series}"
            docker_hub_status['php_alias_tag'] = alias_tag
            docker_hub_status['php_patch_tag'] = patch_tag
        else:
            docker_hub_status['php_alias_tag'] = alias_tag
        
        # Alpine: reuse result from decision section (L478)
        alpine_patch_tag = docker_hub_digests.get('alpine_patch_tag')
        alpine_patch_exists = docker_hub_digests.get('alpine_patch_exists', False)
        if alpine_patch_tag:
            docker_hub_status['alpine_patch_tag'] = alpine_patch_tag
            docker_hub_status['alpine_patch_exists'] = alpine_patch_exists
        
        target_caddy_series = d_vers.get('caddy')
        if target_caddy_series and caddy_latest:
            alias_tag_c = f"{target_caddy_series}-alpine"
            patch_tag_c = f"{caddy_latest}-alpine"
            docker_hub_status['caddy_alias_tag'] = alias_tag_c
            docker_hub_status['caddy_patch_tag'] = patch_tag_c
    
    # Merge digests collected during decision section
    docker_hub_status.update(docker_hub_digests)
    
    # Build GHCR image version snapshot
    ghcr_snapshot = {
        'php': ghcr_vers.get('php'),
        'alpine': ghcr_vers.get('alpine'),
        'caddy': ghcr_vers.get('caddy'),
    }
    
    # Build binary availability snapshot
    static_bin_status = {}
    if static_php_files:
        for key in PLATFORMS:
            if key == 'windows-x86_64':
                continue
            static_bin_status[key] = find_latest_static_version(key, static_php_available) or 'none'
        # Windows: reuse result from display section (L428)
        static_bin_status['windows'] = win_latest_for_cache
    else:
        static_bin_status = {key: 'none' for key in PLATFORMS}
        static_bin_status['windows'] = win_latest_for_cache
    
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
            'docker_hub_status': docker_hub_status,
            'ghcr_snapshot': ghcr_snapshot,
        }
        
        if os.path.exists(cache_file):
            try:
                with open(cache_file, 'r', encoding='utf-8') as f:
                    prev_versions = json.load(f)
                if prev_versions == current_versions:
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
