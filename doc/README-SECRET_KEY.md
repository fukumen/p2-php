# 環境変数SECRET_KEY

環境変数SECRET_KEYにopenssl rand -hex 32の結果を設定する必要があります。

設定方法は、実行環境（コンテナか実機か）と、PHPを動かす仕組み（Apacheモジュールか、PHP-FPMか）によって多岐にわたります。

> [!IMPORTANT]
> なんのこっちゃ？という場合、docker-rep2に乗り換えてもらうのが一番簡単だと思います。

代表的なパターンを整理して解説します。

## コンテナ環境（Docker / LXC）
コンテナ環境では、**「実行時に外部から注入する」** のが最も一般的でセキュアな方法です。

### Dockerの場合

docker-compose.yml や Dockerfile で定義します。

<details open>
<summary>docker-compose.yml</summary>

```yaml
services:
  php:
    environment:
      - SECRET_KEY=your_secret_value
```
</details>


<details open>
<summary>Dockerfile</summary>

```INI
ENV SECRET_KEY=your_secret_value （※イメージ自体に書き込まれるため、秘密情報の管理には注意が必要）
```
</details>


docker-rep2ではdocker-compose.ymlやdocker-compose.override.ymlに記載してもらう想定です。

### LXCの場合

コンテナの設定ファイル（/var/lib/lxc/容器名/config）に記載します。

<details open>
<summary>/var/lib/lxc/容器名/config</summary>

```INI
lxc.environment = SECRET_KEY=your_secret_value
```
</details>




## Apacheを使用している場合
Apacheの場合、PHPの動作モード（MPM）や接続方式によって書き場所が変わります。

### mod_php（Apacheモジュールとして動作）
httpd.conf や .htaccess に記述します。MPM（Prefork等）に関わらずこの方法で設定可能です。

<details open>
<summary>httpd.conf や .htaccess</summary>

```INI
SetEnv SECRET_KEY "your_secret_value"
```
</details>

### PHP-FPM を Apache から利用している場合
現在の主流（Event MPM + ProxyPass）です。Apache側で SetEnv をしても、PHP-FPM側で受け取りを許可しないと反映されません（後述のPHP-FPMの項を参照）。

## Nginx + PHP-FPM の場合
Nginx側で fastcgi_param を使ってPHPに値を渡します。

<details open>
<summary>/etc/nginx/sites-enabled/なんとか 等</summary>

```INI
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SECRET_KEY "your_secret_value";
    fastcgi_pass unix:/var/run/php/php-fpm.sock;
}
```
</details>

## :warning:PHP-FPM自体の設定（重要）
PHP-FPMを使っている場合（Nginx連携や、ApacheのFastCGI連携）、セキュリティのために**「OSの環境変数をクリアする」**設定がデフォルトで有効になっていることが多いです。

### パターンA：pool設定ファイルに直接書く
/etc/php/X.X/fpm/pool.d/www.conf などに直接記述します。

<details open>
<summary>/etc/php/X.X/fpm/pool.d/www.conf</summary>

```INI
env[SECRET_KEY] = 'your_secret_value'
```
</details>

### パターンB：OSの環境変数を引き継ぐ
OSやDockerで設定した環境変数をPHPで読み取りたい場合は、www.conf で以下の設定を確認してください。

<details open>
<summary>/etc/php/X.X/fpm/pool.d/www.conf</summary>

```INI
; デフォルトは yes。これを no にすると OS の環境変数が PHP で取得可能になる
clear_env = no
```


## OS（Linux）レベルでの設定
WebサーバーではなくOS側で設定する場合です。前述のようにPHP-FPMを使っているとこれだけじゃ駄目です。

#### Systemd サービスファイル
PHP-FPMなどをOS起動時に動かす場合、ユニットファイルに記載します。

<details open>
<summary>sudo systemctl edit php8.2-fpm</summary>

```INI
[Service]
Environment="SECRET_KEY=your_secret_value"
```

### /etc/environmentに書く
システム全体に適用されます（再起動が必要）。

# まとめ

| 実行環境                  | 設定場所                                    | 備考                         |
| :------------------------ | :------------------------------------------ | :--------------------------- |
| Docker                    | docker-compose.yml                          | 最も一般的                   |
| Apache (mod_php)          | .htaccess / httpd.conf                      | SetEnv を使用                |
| Nginx + PHP-FPM           | nginx.conf                                  | fastcgi_param で渡す         |
| PHP-FPM                   | www.conf                                    | clear_env = no の設定に注意  |
| OS（Linux）レベルでの設定 | Systemd サービスファイル / /etc/environment | OSの環境変数に依存 |
