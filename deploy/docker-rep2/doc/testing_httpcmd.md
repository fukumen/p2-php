# rep2 httpcmd.php 操作マニュアル

エージェントモードでブラウザから rep2 の画面を操作せずにお気に入りや
既読状態などの状態を変更するための httpcmd.php の使い方を説明します。

## 概要

httpcmd.php はエージェント用に新設されたものではなく、rep2 本体の PC・スマホ UI
から Ajax で呼び出される既存のコマンドエンドポイントです。
`cmd` パラメータでコマンドを指定してアクセスすると、結果をテキスト (`text/plain; charset=Shift_JIS`) で返します。

- 認証が必要なため、ログイン済みのブラウザセッションから呼び出します。
  開いているページ上で `fetch()` を実行するため同一オリジンとなり、ログイン状態がそのまま使われます
- 戻り値は 1 文字の ASCII のみのため、文字化けの考慮は不要です。
  値の意味はコマンドごとに異なるので、コマンド一覧の「戻り値」列を参照してください
- タブ遷移を伴わないサブリクエストのため、「ログイン画面・トップ画面以外の URL を
  直接開くのは禁止」のルールには抵触しません

## 呼び出し方法

rep2 を開いているタブのページ上で `fetch()` を実行します。
実行には、開いているページに対して任意の JavaScript を実行できる手段
(ブラウザ自動化ツールのスクリプト実行、開発者ツールのコンソール等)を使用します。

```javascript
(() => {
  const q = new URLSearchParams({
    cmd: 'setfav',
    host: '...', bbs: '...', key: '...',
    setfav: '1', ttitle_en: '...'
  });
  return fetch('httpcmd.php?' + q).then(r => r.text());
})()
```

## コマンド一覧

| cmd | パラメータ | 機能 | 戻り値 |
|---|---|---|---|
| delelog | host, bbs, key | スレのログ(dat と key.idx)を削除 | `1`削除 / `2`対象なし / `0`失敗 |
| offrec | host, bbs, key | 履歴(最近読んだスレ・レス履歴)からスレを除去 | `1`除去 / `2`対象なし / `0`失敗 |
| setfavita | host, bbs, setfavita, itaj_en, [setnum] | お気に板の追加・解除・移動 | `1`完了 / `0`失敗 |
| setfav | host, bbs, key, setfav, [ttitle_en], [setnum] | お気にスレの追加・解除・移動 | `1`完了 / `0`失敗 |
| setpal | host, bbs, key, setpal, [ttitle_en] | 殿堂入りの追加・解除・移動 | `1`完了 / `0`失敗 |
| taborn | host, bbs, key, taborn | スレあぼーんの追加・解除 | `1`完了 / `0`失敗 |
| setreadnum | host, bbs, key, setreadnum | スレの既読数をセット | `1`成功 / `0`失敗 |
| ic2 | switch, [mobile] | ImageCache2 の ON/OFF | `1`ON / `2`OFF / `0`失敗 |

- setfavita / setfav / setpal の値: `0` 解除、`1` 追加、`2` トグル、
  `top` / `up` / `down` / `bottom` 移動
- taborn の値: `0` 解除、`1` 追加、`2` トグル
- setreadnum の値: 既読数(数値)。対象スレを一度画面で開いて key.idx が
  作成されている必要があります
- setnum は複数お気にスレセット使用時のセット番号で、通常は指定不要
- ic2 は ImageCache2 が有効な構成でのみ使用可(docker イメージは
  patch/settings.txt で有効化済み)
- offline コマンドは未実装(常に `null` を返す)ため使用しない

## パラメータの取得方法

| パラメータ | 取得元 |
|---|---|
| host, bbs, itaj_en | 板を開いたときの subject.php URL(menu 内の板リンク href にも含まれる) |
| key | subject 一覧のスレリンク href、または read.php URL |
| ttitle_en | PC: スレ表示中の read フレームのタイトルバー内リンク(お気にスレ☆等)の href から抽出<br>スマホ: subject 一覧のスレリンク href に含まれる |

PC の read フレームから host / bbs / key / ttitle_en を一括取得する例:

```javascript
(() => {
  const a = document.querySelector("frame[name=read]").contentDocument
    .querySelector("a[href*='ttitle_en']");
  if (!a) return null;
  const p = new URL(a.href, location.origin).searchParams;
  return { host: p.get('host'), bbs: p.get('bbs'),
           key: p.get('key'), ttitle_en: p.get('ttitle_en') };
})()
```

itaj_en / ttitle_en は UrlSafeBase64 エンコード済みのため URL にそのまま使えます。

## 注意事項

- リクエストパラメータは UTF-8 で送信します(ブラウザの fetch の標準動作)。
  rep2 はリクエストを init.php の処理で CP932 に変換するため、`_hint` パラメータは
  指定しません
- 板名・スレタイトルを値に含めるときは itaj_en / ttitle_en(UrlSafeBase64)を使います
- お気に板を変更した場合、menu フレームは自動では再読み込みされないため、
  画面確認のときは menu を開き直してください
- 操作結果の確認は、対象の板・スレを開き直して行います
- headline ホストの板のスレは「最近読んだスレ」に記録されません。
  offrec を使う場合は、対象のスレを headline 以外の板で開いて履歴に載せておく必要があります
- 同じ対象に複数のコマンドを並行(fetch の Promise.all 等)で実行すると、
  読み込みと書き込みの競合で一方の変更が失われることがあります。
  状態を変更するコマンドは 1 件ずつ直列に実行してください
- setfav / setfavita / setpal / taborn は対象が未登録・登録済みのどちらでも
  処理が成功すれば `1` を返します。対象の登録状態は戻り値からは判別できないため、
  変更後の状態は画面やファイルで確認してください
