# rep2 expack 全部入り for PHP 8.x by fukumen

* rep2-expack https://github.com/rsky/p2-php
* rep2-expack +live https://github.com/pluslive/p2-php
* rep2-expack test https://github.com/orzisun/p2-php
* rep2-expack https://github.com/open774/p2-php
* rep2-expack https://github.com/junk2ool/p2-php
* rep2-expack https://github.com/mikoim/p2-php

上記やスレに上げられた修正を取り込んで全部入りを目指す闇鍋バージョンです。

**このリポジトリにはrep2をPHP 8.xで動かすためのパッチが含まれています。**

- [スクリーンショット](https://open774.github.io/p2-php/screenshots.html)
- [Wiki](https://github.com/open774/p2-php/wiki)
- [p2Wiki](http://akid.s17.xrea.com/p2puki/index.phtml)
- **[FAQ](https://github.com/open774/p2-php/wiki/FAQ) スレに書く前にからならず確認**

### 主な追加機能

各機能の説明はdocディレクトリのREADMEファイルを見てください。

* PHP8系で起きていた文字化け対策(スレの情報を元にいい加減なパッチ当て)
* proxy無しで5chへアクセス出来るよう対応(不足有り)
* 5chの/板名/oyster/スレッドキー上位4桁の数字/スレッドキー.datの形式の過去ログに対応
* 5chの過去ログ倉庫(kako.5ch.net)のスクレイピングに対応
* 認証関係のハッシュや暗号化を強化
* 5chのどんぐりシステムの警備員●に対応([詳細はこちら](doc/README-donguri.md))

なお、5ch以外やpinkでは全くテストしていません。

## セットアップ

### Git & Composerで

1. 本体をclone

```shell
git clone https://github.com/fukumen/p2-php.git
cd p2-php
```

2. 依存ライブラリをダウンロード

```shell
curl -O https://getcomposer.org/composer.phar
chmod +x composer.phar
./composer.phar install
```

3. Webサーバが書き込めるようにディレクトリのアクセス権をセット  

(CGI/suEXECIやCLI/Built-in web serverでは不要)

```shell
chmod 0777 data/* rep2/ic
```

## 動作環境

PHP8.2以上が必要です。

> [!CAUTION]
> PHP7.x以下では使えません。
> なお、現在はPHP8.5で動作確認しています。

以下のコマンドを実行して、全ての項目で `OK` が出たなら大丈夫です。

何かエラーが出たらがんばって環境を整えてください。

```shell
php scripts/p2cmd.php check
```

### :warning:注意事項

「認証関係のハッシュや暗号化を強化」によりdata/prefのp2_auth_user.phpとconf_user.srd.cgiが従来のrep2では全く読めなくなります。バックアップをとっておいてください。

更新後、初回接続時はセッションとクッキー認証が失敗扱いになりログイン画面が表示されます。ログイン画面からログインした時点で暗号化が強化された状態に更新されます。

また、環境変数SECRET_KEYの設定が必要です。ホストで openssl rand -hex 32 を実行した結果を設定してください。
どうやるの？という場合は[こちら](doc/README-SECRET_KEY.md)を見てください。

### :warning:proxyについて

rep2側で過去ログ倉庫のスクレイピングも実装済みのため、現時点ではproxyは不要になっているはず。proxy無しの場合の推奨設定は[fukumen/docker-rep2](https://github.com/fukumen/docker-rep2)のREADME.mdを参照してください。

2chproxy.plを使いたい場合、[fukumen/2chproxy.pl](https://github.com/fukumen/2chproxy.pl)を使ってENABLE_ALWAYS_HTTPS_FOR_2CH: 1, KEEP_COOKIE : 0とする必要があります。

proxyを使い場合は、2chproxy.plよりも開発が続いているproxy2chの方が良さそう。

[どんぐり](doc/README-donguri.md)の説明にも少し書きましたが、proxyがクッキーを保持する機能が有効な場合、expireの対応がされていないとどんぐりを複数持ったりおかしなことになりそうなのでproxy側でのクッキー保持はオフが推奨です。

## Built-in web serverで使ってみる (PHP 5.4+)

PHP 5.4の新機能、[ビルトインウェブサーバー](http://docs.php.net/manual/ja/features.commandline.webserver.php) で簡単に試せます。

以下のようにすると、Webサーバーの設定をしなくても `http://localhost:8080/` でrep2を使えます。**(Windowsでも!)**

```shell
cd rep2
php -S localhost:8080 web.php
```

moriyoshi++

## 画像を自動で保存したい

スレに貼られている画像を自動で保存する機能、**ImageCache2**があります。

see also [doc/ImageCache2/README.txt](doc/ImageCache2/README.txt), [doc/ImageCache2/INSTALL.txt](doc/ImageCache2/INSTALL.txt)

### 準備

1. SQLite以外のデータベースを使う場合はデータベースサーバーを立ち上げておく。  

2. conf/conf_admin_ex.inc.phpでImageCache2を有効にする。
  <pre>$_conf['expack.ic2.enabled'] = 3;</pre>

3. conf/conf_ic2.inc.phpで[DSN](http://pear.php.net/manual/ja/package.database.db.intro-dsn.php)を設定する。
  <pre>$_conf['expack.ic2.general.dsn'] = 'mysql://username:password@localhost:3306/database';</pre>

4. setupスクリプトを実行する。
  <pre>php scripts/ic2.php setup</pre>

### 注意

* PHP 5.4ではSQLite2がサポートされなくなったので、ImageCache2を使いたいときはMySQLかPostgreSQLが必要です。
* ホストに`localhost`を指定して接続できないときは、代わりに`127.0.0.1`にしてみてください。

## 設定を変えたい

細かい挙動の変更は `メニュー > 設定管理 > ユーザー設定編集` から行えます。

Webブラウザから変更できない項目は [conf/conf_admin.inc.php](https://github.com/open774/p2-php/blob/master/conf/conf_admin.inc.php) (基本), [conf/conf_admin_ex.inc.php](https://github.com/open774/p2-php/blob/master/conf/conf_admin_ex.inc.php) (拡張パック), [conf/conf_ic2.inc.php](https://github.com/open774/p2-php/blob/master/conf/conf_ic2.inc.php) (ImageCache2) を直接編集します。

どういうことができるか書き起こすのが面倒なので設定ファイルのコメントを見てください。

## cronを使った便利機能
下記のスクリプトをcronで定期的に回すとより便利にrep2を使用することが出来ます。
必要に応じてどちらか一つを使用すれば充分でしょう。

### 履歴の新着数更新
ブラウザから更新を行うと一覧の表示に時間がかかるため、subject.txtを更新するためのスクリプトが付属しています。
並列ダウンロードで高速ですが、使用するために設定変更を行う必要があります。

<pre>php scripts/fetch-subject-txt.php --mode モードを一つ指定(fav recent res_hist)</pre>

### 更新ついでにDATのダウンロード
並列ダウンロードの代わりにsubject.txtとDATのダウンロード機能を実装したスクリプトです。
時間はかかりますが、設定変更無しで使えるのでこちらがお手軽です。

<pre>php scripts/fetch-dat.php --mode モードを一つ指定(fav recent res_hist)</pre>

## 更新

    php scripts/p2cmd.php update

これは下記コマンドを個別に実行するのと等価です。

    git pull
    php -d detect_unicode=0 composer.phar self-update
    php -d detect_unicode=0 composer.phar update

## Authors & Contributors

* **aki** *(original)* http://akid.s17.xrea.com/
* **rsk** *(expack)* https://github.com/rsky/p2-php/
* **unpush** https://github.com/unpush/p2-php/
* **thermon** https://github.com/thermon/p2-php/
* **part32の892** *(+live)* https://github.com/pluslive/p2-php/
* **orzisun** https://github.com/orzisun/p2-php
* **open774** https://github.com/open774/p2-php
* **killer4989** https://github.com/killer4989/p2-php
* **dgg712** https://github.com/dgg712/p2-php
* **2ch p2/rep2スレの>>1-1000**

## License

see [LICENSE.txt](LICENSE.txt)
