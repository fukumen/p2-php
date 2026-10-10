<?php
/**
 * ip2host
 */

require_once __DIR__ . '/../init.php';

$ips = $_POST['ips'] ?? null;
if (!is_array($ips)) {
    return;
}

header('Content-Type: text/plain; charset=Shift_JIS');

$rdap_available = $_conf['ip2host.rdap.enabled'] && $_conf['expack.use_curl_multi'];

$hosts = array();
$unresolved = array();
foreach ($ips as $ip) {
    if (!is_string($ip) || $ip === '') {
        continue;
    }
    $normalized = str_replace(':*', '::', $ip);
    if (!filter_var($normalized, FILTER_VALIDATE_IP)) {
        $hosts[$ip] = $ip;
        continue;
    }
    $host = gethostbyaddr($normalized);
    if ($host !== false && $host !== $normalized) {
        $hosts[$ip] = $host;
    } else {
        $unresolved[$ip] = $normalized;
    }
}

if ($unresolved && $rdap_available) {
    // IANA ブートストラップ(RFC 9224)は参照せず rdap.apnic.net に直接リクエストする
    // (管轄外は 30x で委任先へリダイレクトされる)
    $specs = array();
    foreach ($unresolved as $i => $normalized) {
        $specs['k' . $i] = array('url' => 'https://rdap.apnic.net/ip/' . $normalized, 'follow' => true);
    }
    $responses = P2CurlMulti::httpRequestsParallel($specs);

    $rdap_429 = 0;
    $retry_after = null;
    foreach ($unresolved as $i => $normalized) {
        $res = $responses['k' . $i] ?? null;
        if (!$res instanceof P2CurlResponse) {
            continue;
        }
        $status = $res->getStatus();
        if ($status == 429) {
            // レートリミットは解決失敗として扱い、観測のために応答ヘッダへ出力する
            $rdap_429++;
            if ($retry_after === null) {
                $retry_after = $res->getHeader('Retry-After');
            }
            continue;
        }
        if ($status != 200) {
            continue;
        }
        $json = json_decode($res->getBody(), true);
        if (!is_array($json)) {
            continue;
        }
        $org = ip2host_rdap_org_name($json);
        if ($org !== null) {
            $hosts[$i] = $org;
        }
    }
    if ($rdap_429 > 0) {
        header('X-Ip2host-Rdap-429: ' . $rdap_429);
        if ($retry_after !== null) {
            header('X-Ip2host-Rdap-Retry-After: ' . $retry_after);
        }
    }
}

foreach ($ips as $ip) {
    if (!is_string($ip) || $ip === '') {
        continue;
    }
    $host = $hosts[$ip] ?? $ip;
    $host = str_replace(array("\t", "\r", "\n"), '', $host);
    $host_sjis = mb_convert_encoding($host, 'CP932', 'UTF-8');
    echo $ip . "\t" . p2h($host_sjis) . "\n";
}

// {{{ ip2host_rdap_org_name()

/**
 * RDAP 応答から組織名を取得する
 */
function ip2host_rdap_org_name($json)
{
    if (isset($json['remarks']) && is_array($json['remarks'])) {
        foreach ($json['remarks'] as $remark) {
            if (!is_array($remark) || ($remark['title'] ?? '') !== 'description') {
                continue;
            }
            $desc = $remark['description'] ?? null;
            if (is_array($desc)) {
                $first = $desc[0] ?? null;
                if (is_string($first) && $first !== '') {
                    return $first;
                }
            } elseif (is_string($desc) && $desc !== '') {
                return $desc;
            }
        }
    }
    $name = $json['name'] ?? null;
    return (is_string($name) && $name !== '') ? $name : null;
}

// }}}
