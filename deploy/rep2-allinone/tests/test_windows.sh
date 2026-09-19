#!/bin/sh
set -e

usage() {
    cat << EOF
使用法: $(basename "$0") [オプション]

rep2-allinone の Windows 版 ZIP パッケージを dockurr/windows コンテナ内でインストール・起動テストします。

【事前準備】
初回のみ、以下のコマンドでベースとなる Windows 環境を構築し、
内部で OpenSSH サーバーを起動・自動起動設定し、公開鍵認証を設定しておく必要があります。
詳細は windows_test_env.md を参照してください。

  mkdir -p "$HOME/win-test-data/storage" "$HOME/win-test-data/shared"
  cp ~/.ssh/id_ed25519.pub "$HOME/win-test-data/shared/"
  docker run -d --name win-test-base --device=/dev/kvm --cap-add NET_ADMIN \\
    -v "$HOME/win-test-data/storage:/storage" -v "$HOME/win-test-data/shared:/shared" -e VERSION="2022" \\
    -p 8006:8006 -p 2222:22 dockurr/windows
  # http://localhost:8006 にアクセスしてセットアップ完了を待つ

オプション:
  --data-dir=DIR Windowsディスクデータディレクトリ (デフォルト: $HOME/win-test-data/storage)
  --manual       テスト終了後もコンテナを削除せずに維持します
  -h, --help     このヘルプを表示して終了します

使用例:
  $(basename "$0")
  $(basename "$0") --data-dir=/path/to/win-data/storage --manual
EOF
}

WIN_DATA_DIR="$HOME/win-test-data/storage"
MANUAL_MODE=false

while [ $# -gt 0 ]; do
    case "$1" in
        --data-dir=*)
            WIN_DATA_DIR="${1#*=}"
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

if [ ! -d "$WIN_DATA_DIR" ]; then
    echo "エラー: Windowsディスクデータディレクトリ '$WIN_DATA_DIR' が見つかりません。"
    echo "事前に dockurr/windows でセットアップを行ってください。(-h で詳細を表示)"
    exit 1
fi

# パッケージの検索
PKG_PATH=$(ls dist/rep2-allinone-*-windows-*.zip 2>/dev/null | sort -V | tail -n 1)
if [ -z "$PKG_PATH" ]; then
    echo "エラー: dist/ ディレクトリに Windows版 ZIP パッケージが見つかりません。'make windows' を先に実行してください。"
    exit 1
fi

echo "使用パッケージ: $PKG_PATH"
echo "使用データディレクトリ: $WIN_DATA_DIR"

CONTAINER_NAME="rep2-win-test-$(date +%s)"
SSH_PORT="2222"
SSH_USER="docker"

echo "Windows コンテナを起動中..."
docker run -d --name "$CONTAINER_NAME" \
    --device=/dev/kvm --cap-add NET_ADMIN \
    -v "$WIN_DATA_DIR:/storage" \
    -v "$(pwd)/dist:/shared" \
    -e VERSION="2022" \
    -p $SSH_PORT:22 \
    -p 8006:8006 \
    -p 10088:10088 \
    dockurr/windows

echo "Windows と SSH の起動を待機中..."
while ! nc -z localhost $SSH_PORT 2>/dev/null; do
    sleep 5
done
echo "SSH ポートの応答を確認しました。サービスの安定化を待ちます..."
sleep 20

echo "SSH経由でテストを実行します..."
set +e
# SSH接続が安定するまで数回リトライする
MAX_RETRIES=5
RETRY_COUNT=0
until ssh -p $SSH_PORT -o StrictHostKeyChecking=no -o PasswordAuthentication=no -o ConnectTimeout=5 $SSH_USER@localhost "powershell -Command Write-Host SSH_CONNECTED" > /dev/null 2>&1 || [ $RETRY_COUNT -eq $MAX_RETRIES ]; do
    echo "SSH 接続を再試行中... ($((RETRY_COUNT + 1))/$MAX_RETRIES)"
    sleep 5
    RETRY_COUNT=$((RETRY_COUNT + 1))
done

# 非対話モードでスクリプトを流し込む
ssh -T -p $SSH_PORT -o StrictHostKeyChecking=no -o PasswordAuthentication=no $SSH_USER@localhost "powershell -NoProfile -ExecutionPolicy Bypass -Command -" << 'EOF'
Write-Host "Cleaning up previous test artifacts..."
Stop-Process -Name "php", "php-cgi", "caddy" -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2
if (Test-Path "C:\temp.zip") { Remove-Item "C:\temp.zip" -Force }
if (Test-Path "C:\rep2-test") { Remove-Item "C:\rep2-test" -Recurse -Force }

Write-Host "Expanding ZIP package..."
$zipItem = Get-ChildItem "\\host.lan\Data\rep2-allinone-*-windows-*.zip" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
Copy-Item -Path $zipItem.FullName -Destination "C:\temp.zip"
Expand-Archive -Path "C:\temp.zip" -DestinationPath "C:\rep2-test" -Force

Write-Host "Starting rep2-allinone.bat..."
Start-Process -FilePath "C:\rep2-test\rep2-allinone\rep2-allinone.bat" -WindowStyle Hidden

Write-Host "Waiting for service to start..."
$success = $false
for ($i = 1; $i -le 15; $i++) {
    $msg = ""
    $body = ""
    try {
        $resp = Invoke-WebRequest -Uri "http://localhost:10088/" -UseBasicParsing -ErrorAction Stop
        $msg = "Status: $($resp.StatusCode)"
        $body = $resp.Content
    } catch {
        if ($_.Exception.Response) {
            $msg = "Status: $([int]$_.Exception.Response.StatusCode)"
            $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
            $body = $reader.ReadToEnd()
            $reader.Close()
        } else {
            $msg = "No Response"
        }
    }
    
    $match = "No Match"
    if ($body -match "rep2") { $match = "Match" }
    
    if ($msg -match "401" -and $match -eq "Match") {
        Write-Host "Waiting... ($i/15) $msg [Body: $match] -> Success!"
        $success = $true
        break
    }
    
    Write-Host "Waiting... ($i/15) $msg [Body: $match]"
    Start-Sleep -Seconds 3
}

Write-Host "Test loop completed."
if ($success) {
    Write-Host "Test Result: Success"
} else {
    Write-Host "Test Result: Failed"
    exit 1
}
EOF

TEST_RESULT=$?
set -e

if [ $TEST_RESULT -eq 0 ] && [ "$MANUAL_MODE" = "false" ]; then
    echo "クリーンアップを実行中 (C:/rep2-test)..."
    ssh -T -p $SSH_PORT -o StrictHostKeyChecking=no -o PasswordAuthentication=no $SSH_USER@localhost "powershell -NoProfile -ExecutionPolicy Bypass -Command \"Stop-Process -Name 'php', 'php-cgi', 'caddy' -Force -ErrorAction SilentlyContinue; Start-Sleep -Seconds 5; Remove-Item -Path 'C:\\rep2-test' -Recurse -Force; Remove-Item -Path 'C:\\temp.zip' -Force\""
fi

if [ $TEST_RESULT -ne 0 ]; then
    echo "エラー: SSH接続またはテストの実行に失敗しました。"
    echo "事前に Windows コンテナ内で OpenSSH サーバーを起動し、公開鍵認証を設定しているか確認してください。"
    EXIT_CODE=1
else
    EXIT_CODE=0
fi

if [ "$MANUAL_MODE" = "true" ]; then
    echo "マニュアルモードのため、コンテナを維持します。"
    echo "コンテナ名: $CONTAINER_NAME"
    echo "アクセス先: http://localhost:10088/"
    echo "SSHアクセス: ssh -p $SSH_PORT $SSH_USER@localhost"
    echo "停止・削除するには以下のコマンドを実行してください:"
    echo "  docker stop $CONTAINER_NAME && docker rm $CONTAINER_NAME"
else
    echo "クリーンアップ中..."
    docker stop "$CONTAINER_NAME" >/dev/null
    docker rm "$CONTAINER_NAME" >/dev/null
fi

exit ${EXIT_CODE:-0}
