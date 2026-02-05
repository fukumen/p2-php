/**
 * どんぐり関係
 */

// {{{ checkDonguri()

function checkDonguri(is_iphone) {
    var el = document.getElementById('donguri_content');
    if (el) el.innerHTML = '確認中...';
    var xhr = new XMLHttpRequest();
    var url = 'dongurictl.php?mode=check';
    if (is_iphone) {
        url += '&b=i';
    }
    var res = getResponseTextHttp(xhr, url);
    if (el) el.innerHTML = res;
    return false;
}
// }}}
// {{{ loginDonguri()

function loginDonguri(is_iphone) {
    var el = document.getElementById('donguri_content');
    if (el) el.innerHTML = 'ログイン中...';
    var xhr = new XMLHttpRequest();
    var url = 'dongurictl.php?mode=login';
    if (is_iphone) {
        url += '&b=i';
    }
    var res = getResponseTextHttp(xhr, url);
    if (el) el.innerHTML = res;
    return false;
}
// }}}
// {{{ logoutDonguri()

function logoutDonguri(is_iphone) {
    var el = document.getElementById('donguri_content');
    if (el) el.innerHTML = 'ログアウト中...';
    var xhr = new XMLHttpRequest();
    var url = 'dongurictl.php?mode=logout';
    if (is_iphone) {
        url += '&b=i';
    }
    var res = getResponseTextHttp(xhr, url);
    if (el) el.innerHTML = res;
    return false;
}
// }}}


/*
 * Local Variables:
 * mode: javascript
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: t
 * End:
 */
/* vim: set syn=javascript fenc=cp932 ai noet ts=4 sw=4 sts=4 fdm=marker: */
