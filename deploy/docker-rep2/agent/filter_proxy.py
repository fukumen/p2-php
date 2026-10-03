"""rep2 エージェント環境用フィルタプロキシアドオン。

POST かつ URL パスに /bbs.cgi（5ch）または /write.cgi（machibbs / JBBS@したらば）
を含むリクエストを 403 で遮断する。画像アップロード（upload.php が POST する
外部アップローダ API: Imgur / Imgbb / Catbox / Litterbox）への POST も遮断する。
それ以外は透過的に転送する。ルールは BLOCK_RULES で拡張できる。
"""
from mitmproxy import http


# (host, path, method, reason) — host は None でホストを問わない
BLOCK_RULES = (
    (None, "/bbs.cgi", "POST", "POST to BBS"),
    (None, "/write.cgi", "POST", "POST to BBS"),
    ("api.imgur.com", "/3/image", "POST", "image upload"),
    ("api.imgbb.com", "/1/upload", "POST", "image upload"),
    ("catbox.moe", "/user/api.php", "POST", "image upload"),
    ("litterbox.catbox.moe", "/resources/internals/api.php", "POST", "image upload"),
)


class FilterProxy:
    def request(self, flow: http.HTTPFlow) -> None:
        for host, path, method, reason in BLOCK_RULES:
            if flow.request.method != method:
                continue
            if host is not None and flow.request.host != host:
                continue
            if path in flow.request.path:
                flow.response = http.Response.make(
                    403,
                    f"Blocked by rep2 agent filter proxy: {reason} is not allowed.".encode(),
                    {"Content-Type": "text/plain; charset=utf-8"},
                )
                return


addons = [FilterProxy()]
