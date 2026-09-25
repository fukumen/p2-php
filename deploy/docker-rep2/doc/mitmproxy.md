# mitmproxy

HTTPのリクエストをproxy経由でデバッグするときのメモです。

結論：mitmproxyが便利です。

## rep2側の設定

rep2の設定画面でproxy_useを「する」にし、proxy_host / proxy_portにmitmproxyの待ち受けを指定します。mitmproxyを別のPCで起動する場合は、proxy_hostにそのPCのIPアドレスを指定します。

```
proxy_use: する
proxy_host: <mitmproxyを起動しているPCのIP>
proxy_port: 8080
ssl_verify_peer: しない
```

Dockerホストと同じPCでmitmproxyを起動する場合は、proxy_hostにhost.docker.internalを指定し、docker-compose.ymlのextra_hostsに「host.docker.internal:host-gateway」を追加します。
解析後はproxy_useを「しない」に戻してください。

## CLI

上記のrep2側設定のとおりssl_verify_peerを「しない」にしておくと、use_httpsが「する」でも解析できます。解析後はssl_verify_peerを「する」に戻してください。

```
docker run --rm -it \
  -p 8080:8080 \
  -v $(pwd):/data \
  -w /data \
  mitmproxy/mitmproxy:latest \
  /usr/local/bin/mitmproxy --mode regular -p 8080
```

## WEB

以下のように起動するとURLが表示されるので、そのURLをブラウザで開いてください。

```
docker run --rm -it \
  -p 8080:8080 \
  -p 8081:8081 \
  -v $(pwd):/data \
  -w /data \
  mitmproxy/mitmproxy:latest \
  /usr/local/bin/mitmweb --mode regular -p 8080 \
    --web-host 0.0.0.0
```

## TLS1

TLS1しかサポートしていないような古いクライアントを接続するには、mitmproxyの7.0.4がTLS1接続出来る最終のようなのでそれを使います。

```
docker run --rm -it \
  -p 8080:8080 \
  -v $(pwd):/data \
  -w /data \
  mitmproxy/mitmproxy:7.0.4 \
  /usr/local/bin/mitmproxy --mode regular -p 8080 \
    --set tls_version_client_min=TLS1
```
