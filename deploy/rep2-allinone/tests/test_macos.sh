#!/bin/sh
set -e

usage() {
    cat << EOF
使用法: $(basename "$0") [オプション]

rep2-allinone の macOS 版パッケージ (.tar.gz) を Lume を使って macOS 仮想マシン内で展開・起動テストします。

【事前準備】
1. Lume のインストール:
   brew tap trycua/lume && brew install lume
2. SSH 公開鍵 (~/.ssh/id_ed25519.pub または id_rsa.pub) が存在することを確認してください。

オプション:
  --image=IMAGE  使用する macOS イメージ (デフォルト: macos-tahoe-vanilla:latest)
  --user=USER    VM内のユーザー名 (デフォルト: admin)
  --repo         ローカルのパッケージではなく、公式 Tap (fukumen/tap) からインストールします
  --manual       テスト終了後もコンテナを削除せずに維持します
  -h, --help     このヘルプを表示して終了します

使用例:
  $(basename "$0")
  $(basename "$0") --repo
  $(basename "$0") --manual
EOF
}

IMAGE="macos-tahoe-vanilla:latest"
SSH_USER="lume"
SSH_PASS="lume"
REPO_MODE=false
MANUAL_MODE=false

while [ $# -gt 0 ]; do
    case "$1" in
        --image=*)
            IMAGE="${1#*=}"
            shift
            ;;
        --user=*)
            SSH_USER="${1#*=}"
            shift
            ;;
        --repo)
            REPO_MODE=true
            shift
            ;;
        --manual)
            MANUAL_MODE=true
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "エラー: 未知のオプション '$1'"
            usage
            exit 1
            ;;
    esac
done

PUB_KEY=""
for key in "$HOME/.ssh/id_ed25519.pub" "$HOME/.ssh/id_rsa.pub"; do
    if [ -f "$key" ]; then
        PUB_KEY="$key"
        break
    fi
done

if [ -z "$PUB_KEY" ]; then
    echo "エラー: SSH 公開鍵が見つかりません。ssh-keygen で作成してください。"
    exit 1
fi

if [ "$REPO_MODE" = "false" ]; then
    PKG_PATH=$(ls dist/rep2-allinone-*-macos-*.tar.gz 2>/dev/null | sort -V | tail -n 1)
    if [ -z "$PKG_PATH" ]; then
        echo "エラー: dist/ ディレクトリに macOS 版の .tar.gz パッケージが見つかりません。'make macos' を先に実行してください。"
        exit 1
    fi
    echo "使用パッケージ: $PKG_PATH"
    VERSION=$(basename "$PKG_PATH" | sed -E 's/rep2-allinone-([0-9]+\.[0-9]+\.[0-9]+)-.*/\1/')
    echo "バージョン：$VERSION"
else
    echo "Repo モード: 公式 Tap (fukumen/tap) からインストールします。"
fi

echo "使用イメージ: $IMAGE"

VM_IMAGE=$(echo "$IMAGE" | tr ':' '_')
if lume get "$VM_IMAGE" >/dev/null 2>&1; then
    echo "イメージ ($IMAGE) は既にあります。"
else
    echo "イメージ ($IMAGE) をpull..."
    lume pull "$IMAGE"
fi

VM_NAME="rep2-macos-test-$(date +%s)"
LUME_LOG="/tmp/${VM_NAME}_lume.log"
echo "macOS 仮想マシン ($VM_NAME) を起動中..."
lume clone "$VM_IMAGE" "$VM_NAME"
stdbuf -oL -eL lume run "$VM_NAME" --no-display > "$LUME_LOG" 2>&1 &

echo "VM の起動と SSH の準備を待機中..."
MAX_WAIT=30
WAIT_COUNT=0
while ! grep -q "Wrote VNC config to VM" "$LUME_LOG" 2>/dev/null; do
    if [ $WAIT_COUNT -ge $MAX_WAIT ]; then
        echo "エラー: VMの起動タイムアウト"
        cat "$LUME_LOG"
        lume stop "$VM_NAME" || true
        lume delete "$VM_NAME" --force || true
        rm -f "$LUME_LOG"
        exit 1
    fi
    sleep 2
    WAIT_COUNT=$((WAIT_COUNT + 2))
done

echo "VM の IP アドレスを取得しています..."
VM_IP=""
VM_INFO=$(lume get "$VM_NAME" 2>/dev/null || true)
if [ -n "$VM_INFO" ]; then
    VM_IP=$(echo "$VM_INFO" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+' | grep -v '127.0.0.1' | tail -1)
fi

if [ -z "$VM_IP" ]; then
    echo "エラー: VMのIPアドレスが取得できませんでした。"
    lume stop "$VM_NAME" || true
    lume delete "$VM_NAME" --force || true
    rm -f "$LUME_LOG"
    exit 1
fi

echo "VM IP: $VM_IP"

echo "SSH の起動を待機中..."
while ! nc -z "$VM_IP" 22 2>/dev/null; do
    sleep 5
done
echo "SSH ポートの応答を確認しました。"

echo "------------------------------------------------------------"
echo "SSH 公開鍵を VM に登録します。"
echo "------------------------------------------------------------"

cat "$PUB_KEY" | sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 "$SSH_USER@$VM_IP" \
    "mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"

echo "SSH 接続を確認中 (公開鍵認証)..."
ssh -o StrictHostKeyChecking=no -o PasswordAuthentication=no "$SSH_USER@$VM_IP" "echo SSH_CONNECTED" > /dev/null

if [ "$REPO_MODE" = "false" ]; then
    echo "パッケージを VM に転送中..."
    scp -o StrictHostKeyChecking=no -o PasswordAuthentication=no "$PKG_PATH" "$SSH_USER@$VM_IP:/tmp/rep2-allinone.tar.gz"

    SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
    PROJECT_ROOT=$(dirname "$SCRIPT_DIR")
    TEMPLATE_FILE="$PROJECT_ROOT/macos/homebrew-formula.rb.template"

    echo "Homebrew formula テンプレートを VM に転送中..."
    scp -o StrictHostKeyChecking=no -o PasswordAuthentication=no "$TEMPLATE_FILE" "$SSH_USER@$VM_IP:/tmp/homebrew-formula.rb.template"
fi
echo "Homebrew をインストールします..."
ssh -tt -o StrictHostKeyChecking=no -o ConnectTimeout=10 "$SSH_USER@$VM_IP" "bash -c 'echo $SSH_PASS | sudo -S -v && NONINTERACTIVE=1 /bin/bash -c \"\$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)\"'"

run_ssh() {
    ssh -o StrictHostKeyChecking=no -o PasswordAuthentication=no -o ConnectTimeout=10 "$SSH_USER@$VM_IP" "$@"
}

BREW_ENV='if [ "$(uname -m)" = "arm64" ]; then eval "$(/opt/homebrew/bin/brew shellenv)"; else eval "$(/usr/local/bin/brew shellenv)"; fi;'

if [ "$REPO_MODE" = "true" ]; then
    echo "Homebrew Tap (fukumen/tap) を登録中..."
    run_ssh "${BREW_ENV} brew tap fukumen/tap"
else
    echo "Homebrew パッケージを展開します..."

    echo "SHA256の計算中..."
    SHA256=$(run_ssh "shasum -a 256 /tmp/rep2-allinone.tar.gz | awk '{print \$1}'" | tr -d '\r')

    echo "Formulaディレクトリの作成中..."
    run_ssh "mkdir -p ~/homebrew-tap/Formula"

    echo "rep2-allinone.rbの作成中..."
    run_ssh "sed -e 's/@@VERSION@@/${VERSION}/g' \
        -e 's|url \"https://fukumen.github.io/rep2-allinone/macos/@@FILE_ARM64@@\"|url \"file:///tmp/rep2-allinone.tar.gz\"|' \
        -e 's/@@SHA_ARM64@@/${SHA256}/' \
        -e 's|url \"https://fukumen.github.io/rep2-allinone/macos/@@FILE_X86_64@@\"|url \"file:///tmp/rep2-allinone.tar.gz\"|' \
        -e 's/@@SHA_X86_64@@/${SHA256}/' \
        /tmp/homebrew-formula.rb.template > ~/homebrew-tap/Formula/rep2-allinone.rb"

    echo "Homebrew Tapのセットアップ中..."
    run_ssh "${BREW_ENV} mkdir -p \"\$(brew --repository)/Library/Taps/fukumen\" && ln -sf \"\$HOME/homebrew-tap\" \"\$(brew --repository)/Library/Taps/fukumen/homebrew-tap\""
fi

echo "rep2-allinone をインストール..."
run_ssh "${BREW_ENV} brew install rep2-allinone < /dev/null"

echo "rep2-allinone サービス開始 (root/Systemデーモンとして起動)..."
run_ssh "${BREW_ENV} echo \"$SSH_PASS\" | sudo -S \$(which brew) services start rep2-allinone < /dev/null"

echo "rep2-allinone をセットアップ完了。"

TEST_RESULT=$?
set -e

if [ $TEST_RESULT -ne 0 ]; then
    echo "エラー: VM内での展開・起動に失敗しました。"
    EXIT_CODE=1
else
    echo "サービスの起動を待機中..."
    SUCCESS=false
    MAX_CURL_RETRIES=15
    CURL_COUNT=0
    
    while [ $CURL_COUNT -lt $MAX_CURL_RETRIES ]; do
        HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://$VM_IP:10088/" || echo "Failed")
        if [ "$HTTP_STATUS" = "401" ]; then
            if curl -s "http://$VM_IP:10088/" | grep -q "rep2"; then
                echo "待機中... (Status: $HTTP_STATUS, Try: $((CURL_COUNT + 1))/$MAX_CURL_RETRIES) -> 成功！"
                SUCCESS=true
                break
            fi
        fi
        echo "待機中... (Status: $HTTP_STATUS, Try: $((CURL_COUNT + 1))/$MAX_CURL_RETRIES)"
        CURL_COUNT=$((CURL_COUNT + 1))
        sleep 3
    done
    
    if [ "$SUCCESS" = "true" ]; then
        echo "成功: macOS 仮想マシン内で rep2-allinone サービスが正常に起動しました。"
        EXIT_CODE=0
    else
        echo "失敗: 接続テストがタイムアウトしたか、期待されるレスポンスが得られませんでした。"
        run_ssh "${BREW_ENV} cat \$(brew --prefix)/var/lib/rep2-allinone/rep2-allinone.log 2>/dev/null || cat \$(brew --prefix)/var/lib/rep2-allinone/rep2-allinone.error.log 2>/dev/null || echo 'ログファイルが見つかりません'" || true
        EXIT_CODE=1
    fi
fi

if [ "$MANUAL_MODE" = "true" ]; then
    echo "マニュアルモードのため、VMを維持します。"
    echo "VM名: $VM_NAME"
    echo "アクセス先: http://$VM_IP:10088/"
    echo "SSHアクセス: ssh $SSH_USER@$VM_IP"
    echo "SSHポートフォワード: ssh -g -L 10088:localhost:10088 $SSH_USER@$VM_IP"
    echo "停止・削除するには以下のコマンドを実行してください:"
    echo "  lume stop $VM_NAME && lume delete $VM_NAME --force"
else
    echo "クリーンアップ中..."
    lume stop "$VM_NAME" 2>/dev/null || true
    lume delete "$VM_NAME" --force 2>/dev/null || true
fi

rm -f "$LUME_LOG"
exit $EXIT_CODE