# Linux 上での Windows テスト環境構築ガイド (dockurr/windows)

このドキュメントでは、Linux ホスト上で Windows 版アプリケーションの動作テストを自動化するための環境構築方法について説明します。

## 1. 仕組み

- `dockurr/windows` Docker イメージを使用して、Linux 上の KVM/QEMU で Windows を実行します。
- Windows Server 2022 (180日間評価版) を使用し、ライセンス購入なしでテスト可能です。
- SSH (PowerShell) 経由でパッケージの展開・起動・テストを実行します。
- ホストのディレクトリを Windows 内に SMB 共有 (`\\host.lan\Data`) としてマウントします。

## 2. 前提条件

- **ホストOS:** Linux (Ubuntu/Debian 等)
- **仮想化:** CPU の仮想化支援 (VT-x/AMD-V) が有効で、`/dev/kvm` が利用可能であること。
- **ツール:** Docker, SSH クライアント, `nc` (netcat)
- **ネットワーク:** ポート 2222 (SSH用) と 8006 (Web管理画面用) が利用可能であること。

## 3. 初回セットアップ手順

### Step 3.1: 保存用ディレクトリの作成
Windows の仮想ディスクデータと共有ファイルを保存するディレクトリを作成します。
```bash
mkdir -p ~/win-test-data/storage ~/win-test-data/shared
```

### Step 3.2: ベース環境の構築
以下のコマンドで、インストール用のコンテナを起動します。
```bash
docker run -d --name win-test-base --device=/dev/kvm --cap-add NET_ADMIN \
  -v ~/win-test-data/storage:/storage \
  -v ~/win-test-data/shared:/shared \
  -e VERSION="2022" \
  -p 8006:8006 -p 2222:22 dockurr/windows
```
起動後、ブラウザで `http://localhost:8006` にアクセスします。
Windows のインストールが自動で進みます。デスクトップ画面が表示されるまで（数分〜十数分）待機してください。

### Step 3.3: セットアップスクリプトの準備 (ホスト側)
ホストOS (Linux) の `~/win-test-data/shared` ディレクトリに、公開鍵とセットアップスクリプトを準備します。
```bash
cp ~/.ssh/id_ed25519.pub ~/win-test-data/shared/
cat << 'EOF' > ~/win-test-data/shared/setup_ssh.ps1
$ErrorActionPreference = "Stop"

Write-Host "Installing OpenSSH Server..."
if (!(Get-WindowsCapability -Online | Where-Object Name -like 'OpenSSH.Server*').State -eq 'Installed') {
    Add-WindowsCapability -Online -Name OpenSSH.Server~~~~0.0.1.0
}

Write-Host "Starting SSH service to generate config files..."
Start-Service sshd -ErrorAction SilentlyContinue

$sshdConfig = "$env:ProgramData\ssh\sshd_config"
$timeout = 30
while (!(Test-Path $sshdConfig) -and $timeout -gt 0) {
    Write-Host "Waiting for $sshdConfig to be created... ($timeout seconds left)"
    Start-Sleep -Seconds 2
    $timeout -= 2
    Start-Service sshd -ErrorAction SilentlyContinue
}

# 必要に応じて Visual C++ Redistributable などをインストール
Write-Host "Installing Visual C++ Redistributable..."
$vcRedistPath = "$env:TEMP\vc_redist.x64.exe"
Invoke-WebRequest -Uri "https://aka.ms/vs/17/release/vc_redist.x64.exe" -OutFile $vcRedistPath
Start-Process -FilePath $vcRedistPath -ArgumentList "/install /quiet /norestart" -Wait

Write-Host "Fixing sshd_config for Administrators..."
$configContent = Get-Content $sshdConfig
$configContent = $configContent -replace '^Match Group administrators', '#Match Group administrators'
$configContent = $configContent -replace '^\s+AuthorizedKeysFile __PROGRAMDATA__/ssh/administrators_authorized_keys', '#AuthorizedKeysFile __PROGRAMDATA__/ssh/administrators_authorized_keys'
$configContent | Set-Content $sshdConfig -Encoding ASCII

Write-Host "Restarting SSH service with new config..."
Set-Service -Name sshd -StartupType 'Automatic'
Restart-Service sshd

Write-Host "Setting up authorized_keys..."
$sshDir = "C:\Users\docker\.ssh"
if (!(Test-Path $sshDir)) { New-Item -ItemType Directory -Force -Path $sshDir }

$pubKey = Get-Content "\\host.lan\Data\id_ed25519.pub" -Raw
[System.IO.File]::WriteAllText("$sshDir\authorized_keys", $pubKey.Trim())

Write-Host "Setting permissions..."
icacls $sshDir /inheritance:r /grant "Administrators:F" /grant "SYSTEM:F" /grant "docker:F"
icacls "$sshDir\authorized_keys" /inheritance:r /grant "Administrators:F" /grant "SYSTEM:F" /grant "docker:F"

Write-Host "Setting PowerShell as default shell for OpenSSH..."
$shellPath = "C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe"
New-ItemProperty -Path "HKLM:\SOFTWARE\OpenSSH" -Name "DefaultShell" -Value $shellPath -PropertyType String -Force

Write-Host "Setup completed successfully! Please try connecting from host."
EOF
cat << 'EOF' > ~/win-test-data/shared/setup.txt
powershell -ExecutionPolicy Bypass -File \\host.lan\Data\setup_ssh.ps1
EOF
```
1. Windows のデスクトップが表示されたら、**スタートメニューを右クリック > Terminal (Admin)** または **PowerShell (Admin)** を開きます。
2. `\\host.lan\Data\setup.txt` をメモ帳で開くと実行すべきコマンドが書かれているので、これを実行します：
```powershell
powershell -ExecutionPolicy Bypass -File \\host.lan\Data\setup_ssh.ps1
```
3. 完了後、ホストからログインできるか確認します： `ssh -p 2222 docker@localhost`

### Step 3.5: コンテナの停止
準備ができたら、ベース環境の コンテナを停止して削除します。
```bash
docker stop win-test-base
docker rm win-test-base
```
※ `~/win-test-data/storage` にデータが残っている限り、この準備は一度きりで OK です。

## 4. 各プロジェクトでの利用方法

各プロジェクトのテストスクリプト（例: `tests/test_windows.sh`）から、以下の設定でコンテナを起動して利用します。

- **仮想ディスク:** `~/win-test-data/storage` を `/storage` にマウント。
- **テスト対象物:** ビルド済みバイナリ等を `/shared` にマウント（Windows 内では `\\host.lan\Data`）。
- **SSHポート:** 任意（例: 2222）。
- **ユーザー:** `docker` (公開鍵認証)。

## 5. メンテナンス

- **評価版の期限切れ:** 180日を過ぎて Windows が起動しなくなった場合は、`~/win-test-data/storage` 内のファイルを削除し、Step 3 からやり直してください。
- **デバッグ:** テストスクリプトでコンテナを削除しないように設定し、`http://localhost:8006` で内部状態を確認してください。
