# ワークフローの発火条件と title.php の更新通知

`publish-docker-base.yml` / `publish-docker.yml` / `publish-packages.yml` と、`rep2/title.php` の `checkUpdatan2()` による更新通知の関係をまとめたもの。

## 用語

- COMMIT_DATE: p2-php の HEAD commit 日時。`git log -1 --format="%cd" --date=format-local:"%Y%m%d%H%M"` で取得し、rep2-allinone のパッケージバージョン基礎になる。
- RUN_ID / RUN_NUMBER: GitHub Actions の run 識別子。リポジトリ単位で単調増加し、同一コミットの再ビルドでも必ず新しくなる。

## publish-docker-base.yml (rep2-base / rep2-base-extra イメージ)

- 発火条件: `deploy/docker-rep2/docker/Dockerfile.base` を含む push、または手動 dispatch。
- GHCR へ `latest` タグと日付タグ (TZ=Asia/Tokyo の実行時刻) を push する。
- COMMIT_DATE は関与しない。

## publish-docker.yml (rep2 / rep2-extra イメージ)

- 発火条件は次の 3 系統。
  - `workflow_run`: Base ワークフロー成功完了時に連動。トリガの `types` は `completed` のため Base 失敗時も run 自体は起動するが、job の `if` (`github.event.workflow_run.conclusion == 'success'`) でスキップされるため、ビルド・GHCR への push・RUN_ID の焼き込みは行われない。
  - `push`: `paths-ignore: ['.github/**', 'deploy/**', '**.md']` が適用される。
  - `workflow_dispatch`: 手動実行。
- ビルド時に `REPO_HASH` / `REPO_LOG` (base64) / `RUN_ID` / `RUN_NUMBER` を build-args で渡し、Dockerfile が `ENV VER_*` としてイメージに焼き込む。
- COMMIT_DATE は使わない。
- `latest` タグが更新されるため、`docker compose pull` で最新を取得できる。

## publish-packages.yml (rep2-allinone の deb/rpm/zip/macos)

- 発火条件は push (`paths-ignore` 同上) と手動 dispatch のみ。`workflow_run` は持たない。
- COMMIT_DATE は `dist/build_info_rep2_date` (= HEAD commit 日時) から取得され、`DEB_VERSION` / `RPM_VERSION` の基礎になる。
- `RUN_ID` / `RUN_NUMBER` は make 変数として渡り、`dist/build_info` の `VER_RUN_ID` 等になる。
- `Makefile` は PHP バイナリを static-php-cli の `releases/latest/download` から取得するため、static-php-cli を再ビルドしても rep2-allinone 側は自動では再ビルドされない。

## 空コミットでの発火

path フィルタは push の変更ファイル一覧に対して評価され、変更ファイルが 1 つもない場合はワークフローは動かない (GitHub 公式ドキュメント: "If there are no files changed, the workflow will not run")。`paths` / `paths-ignore` どちらの形式でも同じ。

| 発火方法 | publish-docker | publish-packages |
|---|---|---|
| 空コミットの push | 走らない | 走らない |
| 通常コミットの push (ignore 対象外の実変更あり) | 走る | 走る |
| Makefile / ワークフローのみの変更コミット | 走らない (ignore 対象) | 走らない (ignore 対象) |
| 手動 dispatch | 走る (コミット不要) | 走る (コミット不要) |
| workflow_run | Base 完了時に走る | 経路なし |

## title.php の更新通知 (checkUpdatan2)

- 表示条件: `VER_REPO_TYPE` / `VER_REPO_HASH` / `VER_REPO_LOG` / `VER_RUN_ID` / `VER_RUN_NUMBER` の 5 つが揃っていること。docker イメージでは Dockerfile が ENV で設定し、rep2-allinone では `build_info` をランチャーが export する。加えてユーザー設定 `updatan_haahaa` が ON であること。
- 監視対象: `VER_REPO_TYPE` が `rep2-allinone` なら `publish-packages.yml`、それ以外 (docker) は `publish-docker.yml` の最新成功 run を GitHub API で取得する。
- 通知条件: API が返した最新 run の id が自身の `VER_RUN_ID` より大きいこと。COMMIT_DATE は比較に使わない。

## 通知と実更新の整合性

- docker: 再ビルドのたび RUN_ID が進むため通知は常に正しく機能する。pull で取得できるイメージも確実に新しくなる。
- rep2-allinone: 手動 dispatch による同一 COMMIT_DATE の再ビルドでも RUN_ID は進むため「新しいビルドがあります。」は表示される。しかしパッケージバージョンが変わらないため apt / dnf では更新無しとなり、通知と実更新に不整合が発生する。
- 実更新として配布するにはパッケージバージョンが変わる必要がある。具体策は次の 2 つ。
  - COMMIT_DATE を進めるコミットを積む。実変更コミットなら push で自動発火する。空コミット (`git commit --allow-empty`) の場合も COMMIT_DATE は進むが push では発火しないため、手動 dispatch を併用する。
  - `Makefile` の `PHP_VERSION` / `CADDY_VERSION` を bump する (バージョン文字列には `phpX.Y.Z` / `caddyX.Y.Z` が含まれる)。この場合 push では発火しないため手動 dispatch が必要。
