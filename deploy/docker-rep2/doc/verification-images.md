# 検証用イメージ

docker-rep2 には、正式イメージ (`docker/Dockerfile.base` + `docker/Dockerfile`) とは別に、検証イメージが 2 種類あります。

| イメージ | Dockerfile | 検証対象 |
|---|---|---|
| static 検証イメージ (`rep2-static`) | `docker/Dockerfile.static` | docker-rep2 の php を static-php  に差し替えた場合の挙動 |
| deb 検証イメージ (`rep2-aiodeb`) | `docker/Dockerfile.aiodeb` | rep2-allinone の deb パッケージそのもの |

共通の位置づけ:

- 正式リリース対象外です。ghcr への push はなく、CI も通しません (ローカル検証専用)
- どちらも `docker-compose.yml` の `rep2` サービスをオーバーレイで差し替える方式です (オーバーレイは build.py が実行時に一時ファイルとして生成します)
- static 検証イメージは、イメージの入れ替え (compose オーバーレイによる `image` の差し替え) のみで volumes / ports / 起動スクリプトは docker-rep2 と同等です
- deb 検証イメージは rootfs を使わず、deb 同梱の launcher・conf で起動します。オーバーレイで `ports` / `volumes` / `environment` を置き換え、`user: rep2` を追加します
- 検証モード (`--static` / `--aiodeb`) は互いに排他です。`--extra` / `--debug` (FLAG 系オプション) とも排他で、同時指定は build.py がエラーにします
- イメージの作成は `build-static` / `build-aiodeb` コマンドを使います

## static 検証イメージ (rep2-static)

alpine:3 + static-php バイナリ (php / php-fpm) + composer.phar + caddy で構成し、rootfs を正式イメージと共有します。docker-rep2 の php を static-php に差し替えた場合の挙動確認をします。

### ビルド (build-static)

```shell
./build.py build-static               # github モード: fukumen/static-php-cli の latest リリースから tar.gz 取得
./build.py build-static --src local   # local モード: spc-build work/dist の tar.gz を使用
```

- github モードは `fukumen/static-php-cli` の latest リリースから tar.gz を取得します
- local モードは `deploy/rep2-allinone/spc-build/work/dist/` 内の cli tar.gz を使います。dist に対象ファイルがなければエラーになるので、spc-build でビルド (`deploy/rep2-allinone/spc-build/README.md` 参照) を先に実行してください
- `--src` は `build-static` コマンド専用のオプションです (サブコマンドの後に指定します)

### 起動方法

```shell
./build.py --static up       # static 検証イメージ (rep2-static) で起動
./build.py up                # 正式イメージで起動 (--static 未指定時の既定)
```

- 起動時のオーバーレイは build.py が生成します
- 正式イメージと同様に php-local.ini と php-fpm-local.conf のマウントは可能です

## deb 検証イメージ (rep2-aiodeb)

rep2-allinone の deb パッケージを、deb 本来の実行環境 (`debian:stable-slim`) でそのまま検証します。

- systemd はコンテナに存在しないため、postinst の `systemctl` 呼び出しはビルド時にのみ stub (exit 0) を置いて通過させ、インストール後に削除します。unit の `User=rep2` に相当する実行ユーザーで launcher を直接起動します (compose の `user: rep2`)
- launcher は `/etc/default/rep2-allinone` (systemd の EnvironmentFile) を読む者が存在しないため、Caddy は Caddyfile の既定値 (`:10088`) で待ち受けます。compose の `ports: !override` で container 側 10088 → ホスト側 `${REP2_PORT:-10088}` に置換しています
- SECRET_KEY は postinst がコンテナ内で生成するため、compose の environment では渡しません (`environment: !override` で TZ のみ)
- データはコンテナ破棄とともに消えます (ephemeral)。Dockerfile.aiodeb に VOLUME 宣言が無いため匿名ボリュームにもなりません

### ビルド (build-aiodeb)

```shell
./build.py build-aiodeb              # github モード: fukumen/p2-php の latest リリースから deb を取得
./build.py build-aiodeb --src local  # local モード: rep2-allinone dist の deb を使用
```

- github モードは `fukumen/p2-php` の latest リリースから deb を取得します
- local モードは `deploy/rep2-allinone/dist/` 内の deb を使います。dist に対象ファイルがなければエラーになるので、`make deb` でビルドを先に実行してください
- `--src` は `build-aiodeb` コマンド専用のオプションです (サブコマンドの後に指定します)

### 起動方法

```shell
./build.py --aiodeb up      # deb 検証イメージ (rep2-aiodeb) で起動
./build.py up               # 正式イメージで起動 (--aiodeb 未指定時の既定)
```

- 起動時のオーバーレイは build.py が生成します (`ports` の置換、`volumes` の無効化、`user: rep2` の追加)
- `docker-compose.override.yml` が存在する環境では、正式イメージ向けのバインドマウントが混在するのは想定していないため、`--nooverride` を付けて override を読み込まずに起動する必要があります

### update / confdiff

update / confdiff はコピー先と diff 元のパスが正式イメージ前提 (`/var/www`) でハードコードされていますが、deb 検証イメージの実体は `/opt/rep2-allinone/p2-php` にあるため使用できません。

### 複数回の起動検証をする場合 (named volume)

```yaml
# docker-compose.override.yml 等に記載する例
services:
  rep2:
    volumes: !override
      - rep2-allinone-data:/var/lib/rep2-allinone
volumes:
  rep2-allinone-data:
```

named volume を使うのは、VOLUME 宣言が無い限りデータは writable layer 直書きになりコンテナ削除時に完全に消えるためです。
