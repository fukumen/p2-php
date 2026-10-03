#!/bin/sh
# rep2 エージェント環境: 編集画面(edit_conf_user.php)経由でフィルタプロキシ設定を適用する
set -eu

BASE_URL="http://127.0.0.1:10089"
AGENT_USER="agent"
AGENT_PASS="rep2agent"
UA="rep2-agent-setup"
CJ=$(mktemp)
trap 'rm -f "$CJ"' EXIT

fetch_page() {
    curl -sS --noproxy '*' -A "$UA" -b "$CJ" -c "$CJ" "$1" | iconv -f CP932 -t UTF-8
}

page=$(fetch_page "${BASE_URL}/edit_conf_user.php")

if printf '%s' "$page" | grep -q 'submit_new'; then
    curl -sS --noproxy '*' -A "$UA" -b "$CJ" -c "$CJ" -o /dev/null \
        --data-urlencode "form_login_id=${AGENT_USER}" \
        --data-urlencode "form_login_pass=${AGENT_PASS}" \
        -d "submit_new=1" \
        "${BASE_URL}/edit_conf_user.php"
else
    curl -sS --noproxy '*' -A "$UA" -b "$CJ" -c "$CJ" -o /dev/null \
        --data-urlencode "form_login_id=${AGENT_USER}" \
        --data-urlencode "form_login_pass=${AGENT_PASS}" \
        -d "submit_member=1" \
        "${BASE_URL}/edit_conf_user.php"
fi

page=$(fetch_page "${BASE_URL}/edit_conf_user.php")
csrfid=$(printf '%s' "$page" | sed -n 's/.*name="csrfid" value="\([^"]*\)".*/\1/p')
if [ -z "$csrfid" ]; then
    echo "Error: csrfid を取得できませんでした" >&2
    exit 1
fi

location=$(curl -sS --noproxy '*' -A "$UA" -b "$CJ" -c "$CJ" -o /dev/null -w '%{redirect_url}' \
    --data-urlencode "conf_edit[proxy_use]=1" \
    --data-urlencode "conf_edit[proxy_host]=filter-proxy" \
    --data-urlencode "conf_edit[proxy_port]=3128" \
    --data-urlencode "conf_edit[ssl_verify_peer]=0" \
    --data-urlencode "csrfid=${csrfid}" \
    -d "submit_save=1" \
    "${BASE_URL}/edit_conf_user.php")

case "$location" in
    *saved=1*) echo "[agent] applied: proxy=filter-proxy:3128 ssl_verify_peer=0" ;;
    *) echo "Error: 設定の保存に失敗しました (${location:-no redirect})" >&2; exit 1 ;;
esac
