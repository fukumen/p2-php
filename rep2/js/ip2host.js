/*
 * ip2host
 */

var ip2hostCache = null;
var ip2hostProcessedSet = null;
var ip2hostScrollReplace = 0;

$(function() {
    ip2hostInit();
});

function ip2hostInit()
{
    ip2hostScrollReplace = ip2host_config.scroll_replace;
    ip2hostCache = ip2hostLoadCache();
    ip2hostProcessedSet = new Set();
    ip2hostRun();
    if (ip2hostScrollReplace) {
        $(window).on('scroll click', function() {
            ip2hostRun();
        });
    }
    // 遅延挿入(readajax の遅延ロード、実況モードのリロード応答など)を検知して再変換する
    var observer = new MutationObserver(function() {
        ip2hostRun();
    });
    observer.observe(document.body, {childList: true, subtree: true});
}

function ip2hostRun()
{
    var collected = ip2hostCollect();
    var misses = ip2hostScrollReplace ? collected.visibleMiss : collected.visibleMiss.concat(collected.otherMiss);
    var batches = ip2hostMakeBatches(misses);
    if (batches.length > 0) {
        ip2hostSendBatches(collected.ipMap, batches, 0);
    }
}

/**
 * res-header からワッチョイ IP を収集する。
 * キャッシュヒット分は即置換し、ミス分のみ未処理リストへ追加する。
 * scroll_replace 時は可視範囲内のみが対象になるように分類する。
 */
function ip2hostCollect()
{
    var scrollReplace = ip2hostScrollReplace;
    var ipMap = new Map();
    var visibleMiss = [];
    var otherMiss = [];
    var scrollTop = $(window).scrollTop();
    var winHeight = $(window).height();

    $('div.res-header, tr.res-header').each(function() {
        var resHeader = $(this);
        var m = resHeader.html().match(/<span class="ip2host-ip">\[([0-9a-fA-F.:*]+)/);
        if (!m) {
            return true;
        }
        var ip = m[1];
        var cachedHost = ip2hostCache.get(ip);
        if (cachedHost !== undefined) {
            // キャッシュヒット: 即置換して LRU の末尾へ移動する
            ip2hostCache.delete(ip);
            ip2hostCache.set(ip, cachedHost);
            ip2hostReplaceInHeader(resHeader, cachedHost);
            ip2hostProcessedSet.add(ip);
            return true;
        }
        if (ip2hostProcessedSet.has(ip)) {
            return true;
        }
        var visible = !scrollReplace || (resHeader.offset().top > scrollTop && resHeader.offset().top < scrollTop + winHeight);
        if (!ipMap.has(ip)) {
            ipMap.set(ip, []);
            if (visible) {
                visibleMiss.push(ip);
            } else {
                otherMiss.push(ip);
            }
        }
        ipMap.get(ip).push(resHeader);
    });

    return {'ipMap': ipMap, 'visibleMiss': visibleMiss, 'otherMiss': otherMiss};
}

function ip2hostMakeBatches(ips)
{
    var batches = [];
    for (var i = 0; i < ips.length; i += 100) {
        batches.push(ips.slice(i, i + 100));
    }
    return batches;
}

/**
 * バッチを直列に送信する(前バッチの応答を処理してから次を送る)。
 * fail したバッチの後も継続する。処理済み IP は送信時に Set へ登録する。
 */
function ip2hostSendBatches(ipMap, batches, index)
{
    if (index >= batches.length) {
        return;
    }
    var batch = batches[index];
    for (var i = 0; i < batch.length; i++) {
        ip2hostProcessedSet.add(batch[i]);
    }
    $.ajax({
        'type': 'POST',
        'url': 'ip2host.php',
        'data': {'ips': batch},
        'dataType': 'text',
        'timeout': 30000
    }).done(function(data, textStatus, jqXHR) {
        ip2hostHandleBatchResponse(ipMap, batch, data);
        ip2hostLogRdap429(jqXHR, batch.length);
    }).always(function() {
        ip2hostSendBatches(ipMap, batches, index + 1);
    });
}

/**
 * 応答(行ベース tab 区切り ip\thost)を適用する。
 * host が ip と同一の行は未解決のため、置換せずキャッシュにも保存しない。
 */
function ip2hostHandleBatchResponse(ipMap, batch, data)
{
    var lines = data.split('\n');
    for (var i = 0; i < lines.length; i++) {
        if (lines[i] === '') {
            continue;
        }
        var pos = lines[i].indexOf('\t');
        if (pos < 0) {
            continue;
        }
        var ip = lines[i].substring(0, pos);
        var host = lines[i].substring(pos + 1);
        if (host === ip) {
            continue;
        }
        var headers = ipMap.get(ip);
        if (headers) {
            for (var j = 0; j < headers.length; j++) {
                ip2hostReplaceInHeader(headers[j], host);
            }
        }
        ip2hostCache.delete(ip);
        ip2hostCache.set(ip, host);
    }
    ip2hostTrimCache();
    ip2hostSaveCache();
}

function ip2hostReplaceInHeader(resHeader, host)
{
    resHeader.html(resHeader.html().replace(/(<span class="ip2host-ip">\[)([0-9a-fA-F.:*]+)/, '$1' + host));
}

/** RDAP の 429 検出ヘッダをデバッグログに 1 行記録する(バッチ応答ごと) */
function ip2hostLogRdap429(jqXHR, batchSize)
{
    var count429 = jqXHR.getResponseHeader('X-Ip2host-Rdap-429');
    if (count429 === null) {
        return;
    }
    var retryAfter = jqXHR.getResponseHeader('X-Ip2host-Rdap-Retry-After');
    P2DebugLogger.log('ip2host', 'rdap 429: ' + count429 + '/' + batchSize + ' (Retry-After: ' + retryAfter + ')');
}

function ip2hostGetStorage()
{
    try {
        var cacheType = ip2host_config.cache_type;
        if (cacheType == 0) {
            return ('sessionStorage' in window && window.sessionStorage !== null) ? window.sessionStorage : null;
        }
        return ('localStorage' in window && window.localStorage !== null) ? window.localStorage : null;
    } catch (e) {
        return null;
    }
}

/** 単一キー ip2host の JSON blob から Map を再構築する(例外時は空 Map = キャッシュなし) */
function ip2hostLoadCache()
{
    var map = new Map();
    var st = ip2hostGetStorage();
    if (!st) {
        return map;
    }
    try {
        var raw = st.getItem('ip2host');
        if (!raw) {
            return map;
        }
        var obj = JSON.parse(raw);
        for (var ip in obj) {
            if (obj.hasOwnProperty(ip)) {
                map.set(ip, obj[ip]);
            }
        }
    } catch (e) {
        // 不正な blob は空 Map から開始する
    }
    return map;
}

function ip2hostTrimCache()
{
    var maxSize = ip2host_config.cache_size;
    while (ip2hostCache.size > maxSize) {
        var oldest = ip2hostCache.keys().next().value;
        ip2hostCache.delete(oldest);
    }
}

function ip2hostSaveCache()
{
    var st = ip2hostGetStorage();
    if (!st) {
        return;
    }
    try {
        var obj = {};
        ip2hostCache.forEach(function(host, ip) {
            obj[ip] = host;
        });
        st.setItem('ip2host', JSON.stringify(obj));
    } catch (e) {
        // 例外時はキャッシュなしで継続
    }
}
