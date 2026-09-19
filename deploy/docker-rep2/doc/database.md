# 外部データベースの利用設定 (PostgreSQL / MySQL)

`rep2-extra` イメージを使用することで、ImageCache2 (ic2) のバックエンドに SQLite 以外のデータベース（PostgreSQL, MySQL, MariaDB）を利用できます。

## データベースサービスの追加

`docker-compose.extra.yml` を参考に `docker-compose.override.yml` を作成し、利用したいデータベースのサービス定義を追加します。

### PostgreSQL の例

```yaml
services:
  rep2php8:
    image: ghcr.io/fukumen/rep2-extra:latest
    depends_on:
      db:
        condition: service_healthy
    network_mode: ""
    networks:
      - rep2_net

  db:
    image: postgres:alpine
    environment:
      POSTGRES_DB: rep2_ic2
      POSTGRES_USER: rep2_user
      POSTGRES_PASSWORD: user_password     # 変更してください
      TZ: "Asia/Tokyo"
    volumes:
      - ./rep2-db:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U rep2_user -d rep2_ic2"]
      interval: 5s
      timeout: 5s
      retries: 10
    networks:
      - rep2_net

networks:
  rep2_net:
    driver: bridge
```

### MariaDB (MySQL) の例

```yaml
services:
  rep2php8:
    # PostgreSQL の例を参照

  db:
    image: mariadb:latest
    environment:
      MARIADB_ROOT_PASSWORD: root_password # 変更してください
      MARIADB_DATABASE: rep2_ic2
      MARIADB_USER: rep2_user
      MARIADB_PASSWORD: user_password      # 変更してください
      TZ: "Asia/Tokyo"
    volumes:
      - ./rep2-db:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mariadb-admin", "ping", "-h", "localhost", "-u", "rep2_user", "--password=user_password"]
      interval: 5s
      timeout: 5s
      retries: 10
    networks:
      - rep2_net

networks:
    # PostgreSQL の例を参照
```

## ImageCache2 の設定修正

`rep2-data/conf/conf_ic2.inc.php`を編集し、接続情報を設定します。コンテナを一度も起動していないと `rep2-data` が作成されていないので一度は起動してください。一度起動したときに `rep2-data/data/db/imgcache.sqlite` が作成されてしまうので不要であれば削除してください。

- ホスト名には `localhost` ではなく、`docker-compose.yml` で定義したサービス名（上記の例では `db`）を指定します。
- `docker-compose.override.yml` の `environment` で設定・変更したデータベース名、ユーザー名、パスワードと、以下のDSN内で指定する値は一致させる必要があります。

### DSN の設定例

```php
// PostgreSQL の場合
$_conf['expack.ic2.general.dsn'] = 'pgsql://rep2_user:user_password@db:5432/rep2_ic2';

// MySQL / MariaDB の場合
$_conf['expack.ic2.general.dsn'] = 'mysql://rep2_user:user_password@db:3306/rep2_ic2?charset=utf8';
```

## データベースとテーブルの準備について

PostgreSQL や MySQL を使用する場合、接続先となるデータベース（箱）自体はあらかじめ存在している必要があります。
ただし、「データベースサービスの追加」で記載した例のように、DBコンテナ側の設定（`environment`）で `MARIADB_DATABASE` や `POSTGRES_DB` などの環境変数を指定しておけば、DBコンテナの初回起動時にその名前のデータベースが自動で作成されます。

その後、ImageCache2 用のテーブル（デフォルトでは `imgcache` 等）については、rep2コンテナ起動時に自動で実行されるセットアップスクリプト (`php scripts/ic2.php setup`) によって上記で作成されたデータベース内に自動的に作成されます。
そのため、ユーザー側で手動でSQLを実行してテーブルを作成する操作は不要です。