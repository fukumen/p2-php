// テスト用 XMLHttpRequest 差し替え。
// handler(req) は {status, body, statusText?, contentType?} を返す。
// null を返すとネットワークエラー相当 (status 0, onerror 発火) になる。
// jQuery は $.ajax 実行時に window.XMLHttpRequest を new するため、
// installFakeXHR を jQuery ロード前に呼べば $.get / $.ajax も本 Fake 経由で動作する。
export function installFakeXHR(win, handler) {
  class FakeXMLHttpRequest {
    constructor() {
      this.readyState = 0;
      this.status = 0;
      this.statusText = '';
      this.responseText = '';
      this.responseXML = null;
      this.withCredentials = false;
      this.onreadystatechange = null;
      this.onload = null;
      this.onerror = null;
      this.onabort = null;
      this.ontimeout = null;
      this._method = '';
      this._url = '';
      this._async = true;
      this.requestHeaders = {};
    }

    open(method, url, async = true) {
      this._method = method;
      this._url = url;
      this._async = async;
      this.readyState = 1;
    }

    setRequestHeader(name, value) {
      this.requestHeaders[name] = value;
    }

    getAllResponseHeaders() {
      return this._responseHeaders || '';
    }

    getResponseHeader() {
      return null;
    }

    abort() {}

    send(body) {
      this._body = body;
      const entry = handler(this);
      if (entry) {
        // 応答あり: HTTP エラー (500 等) も実ブラウザ同様に onload で通知し、
        // 成否の振り分けは jQuery 側 (status 判定) が行う
        this.readyState = 4;
        this.status = entry.status !== undefined ? entry.status : 200;
        this.statusText = entry.statusText !== undefined ? entry.statusText
          : (this.status === 200 ? 'OK' : '');
        this.responseText = typeof entry.body === 'string' ? entry.body : JSON.stringify(entry.body);
        this._responseHeaders = 'Content-Type: ' + (entry.contentType || 'text/plain; charset=utf-8');
        if (typeof this.onreadystatechange === 'function') this.onreadystatechange();
        if (typeof this.onload === 'function') this.onload();
      } else {
        this.readyState = 4;
        this.status = 0;
        if (typeof this.onreadystatechange === 'function') this.onreadystatechange();
        if (typeof this.onerror === 'function') this.onerror();
      }
    }
  }

  win.XMLHttpRequest = FakeXMLHttpRequest;
  win.FakeXMLHttpRequest = FakeXMLHttpRequest;
  return FakeXMLHttpRequest;
}
