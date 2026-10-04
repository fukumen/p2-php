<?php
/**
 * HTTPクライアントクラス (GET / POST / HEAD)
 * 
 * @copyright 2026 fukumen (https://github.com/fukumen)
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 */
class P2CurlRequest
{
    const METHOD_GET  = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_HEAD = 'HEAD';
    const AUTH_BASIC  = 'basic';

    private $url;
    private $method;
    private $headers = array();
    private $config = array();
    private $postParams = array();
    private $uploads = array();
    private $body = null;
    private $cookies = array();
    private $auth = null;
    private $debugfile = null;

    public function __construct($url, $method = self::METHOD_GET)
    {
        if ($method !== self::METHOD_GET && $method !== self::METHOD_POST && $method !== self::METHOD_HEAD) {
            throw new Exception("P2CurlRequest only supports GET, POST and HEAD methods.");
        }
        $this->url = $url;
        $this->method = $method;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function setHeader($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                if ($v === null) {
                    unset($this->headers[strtolower($k)]);
                } else {
                    $this->headers[strtolower($k)] = $v;
                }
            }
        } else {
            if ($value === null) {
                unset($this->headers[strtolower($name)]);
            } else {
                $this->headers[strtolower($name)] = $value;
            }
        }
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setConfig($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->config[$k] = $v;
            }
        } else {
            $this->config[$name] = $value;
        }
    }

    public function getConfig($name = null)
    {
        if ($name === null) return $this->config;
        return isset($this->config[$name]) ? $this->config[$name] : null;
    }

    public function setAdapter($adapter)
    {
        // Do nothing
    }

    public function setAuth($user, $password, $scheme = null)
    {
        // Basic認証のみ対応 (schemeは無視する)
        $this->auth = $user . ':' . $password;
    }

    public function addPostParameter($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->postParams[$k] = $v;
            }
        } else {
            $this->postParams[$name] = $value;
        }
    }

    public function addUpload($fieldName, $filename, $sendFilename = null, $contentType = null)
    {
        $this->uploads[] = array(
            'fieldName' => $fieldName,
            'filename' => $filename,
            'sendFilename' => $sendFilename,
            'contentType' => $contentType
        );
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function addCookie($name, $value)
    {
        $this->cookies[$name] = $value;
    }

    public function setDebugfile($filename)
    {
        $this->debugfile = $filename;
    }

    public function send()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        // follow_redirects設定時のみリダイレクトを追跡する (最大5回)
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        // ヘッダはHEADERFUNCTIONで収集し、ステータス行(HTTP/で始まる行)を検出した時点で
        // 収集済みヘッダをリセットする。リダイレクトの各転送で呼ばれるため、
        // 最終転送のヘッダのみが残る (CURLOPT_HEADER+FOLLOWLOCATIONでは
        // 全転送のヘッダとボディが連結され分割が破綻するため)
        $responseHeaders = array();
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($handle, $line) use (&$responseHeaders) {
            if (strpos($line, 'HTTP/') === 0) {
                $responseHeaders = array();
            }
            $responseHeaders[] = $line;
            return strlen($line);
        });

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->config['connect_timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        if (isset($this->config['ssl_capath'])) {
            curl_setopt($ch, CURLOPT_CAPATH, $this->config['ssl_capath']);
        }
        if (isset($this->config['ssl_verify_peer'])) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config['ssl_verify_peer']);
        }
        if (isset($this->config['proxy_host'])) {
            curl_setopt($ch, CURLOPT_PROXY, $this->config['proxy_host']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->config['proxy_port']);
            if ($this->config['proxy_user'] !== '') {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->config['proxy_user'] . ':' . $this->config['proxy_password']);
            }
            if (isset($this->config['proxy_type']) && $this->config['proxy_type'] == 'socks5') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
            }
        }
        if (isset($this->config['follow_redirects']) && $this->config['follow_redirects']) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }

        if ($this->method == 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        } elseif ($this->method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($this->uploads)) {
                $postFields = $this->postParams;
                foreach ($this->uploads as $upload) {
                    $f = $upload['filename'];
                    $mime = $upload['contentType'];
                    $postname = $upload['sendFilename'];

                    $cfile = new CURLFile($f);
                    if ($mime) {
                        $cfile->setMimeType($mime);
                    }
                    if ($postname) {
                        $cfile->setPostFilename($postname);
                    }
                    $postFields[$upload['fieldName']] = $cfile;
                }
                if (isset($this->headers['content-type'])) {
                    unset($this->headers['content-type']);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            } elseif (!empty($this->postParams)) {
                if (!isset($this->headers['content-type'])) {
                    $this->headers['content-type'] = 'application/x-www-form-urlencoded';
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->postParams, '', '&'));
            } elseif ($this->body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
            }
        }

        if ($this->auth !== null) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->auth);
        }

        if (!empty($this->cookies)) {
            $cookieParts = array();
            foreach ($this->cookies as $name => $value) {
                // 生値を連結する (呼び出し側でurlencode済みの値の二重エンコードを防ぐ)
                $cookieParts[] = $name . '=' . $value;
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookieParts));
        }

        $headers = array();
        foreach ($this->headers as $k => $v) {
            if ($v === null) continue;
            if ($k === 'accept-encoding') {
                curl_setopt($ch, CURLOPT_ENCODING, $v);
                continue;
            }
            if ($k === 'user-agent') {
                curl_setopt($ch, CURLOPT_USERAGENT, $v);
                continue;
            }
            if ($k === 'referer') {
                curl_setopt($ch, CURLOPT_REFERER, $v);
                continue;
            }
            if ($v === '') {
                $headers[] = ucwords($k, '-') . ':';
            } else {
                $headers[] = ucwords($k, '-') . ': ' . $v;
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($this->debugfile) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_DEBUGFUNCTION, function($handle, $type, $data) {
                
                // type 1: CURLINFO_HEADER_IN（受信ヘッダ）
                // type 2: CURLINFO_HEADER_OUT（送信ヘッダ）
                // type 0: CURLINFO_TEXT（cURLの接続情報など）
                // type 4: CURLINFO_DATA_OUT（送信データ/BODY）
                if ($type === 1 || $type === 2 || $type === 0 || $type === 4) {
                    $prefix = "[INFO] ";
                    if ($type === 1) {
                        $prefix = "[RECV HEADER] ";
                    } elseif ($type === 2) {
                        $prefix = "[SEND HEADER] ";
                    } elseif ($type === 4) {
                        $prefix = "[SEND BODY] ";
                    }
                    file_put_contents($this->debugfile, $prefix . $data, FILE_APPEND);
                }
            });
        }

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($result === false) {
            throw new P2CurlException("cURL Error: " . $error, $errno);
        }

        return new P2CurlResponse(implode('', $responseHeaders), $result);
    }
}

/**
 * cURLエラーの例外。getNativeCode()でcurl_errno()のエラーコードを返す。
 */
class P2CurlException extends Exception
{
    public function getNativeCode()
    {
        return $this->getCode();
    }
}

class P2CurlResponse
{
    private $status = 0;
    private $body;
    private $headers = array();
    private $cookies = array();

    public function __construct($headerText, $body)
    {
        $this->body = $body;

        $lines = explode("\r\n", $headerText);
        // ステータス行からステータスコードをパースする (HTTP/1.x と HTTP/2 の両形式に対応)
        $statusLine = rtrim($lines[0], "\r");
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $statusLine, $m)) {
            $this->status = (int)$m[1];
        }

        foreach ($lines as $i => $line) {
            if ($i === 0) continue;
            $line = rtrim($line, "\r");
            if (empty($line)) continue;
            $parts = explode(':', $line, 2);
            if (count($parts) == 2) {
                $name = strtolower(trim($parts[0]));
                $this->headers[$name] = trim($parts[1]);
                if ($name === 'set-cookie') {
                    $this->parseCookie(trim($parts[1]));
                }
            }
        }
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getHeader($name = null)
    {
        if ($name === null) {
            return $this->headers;
        }
        $name = strtolower($name);
        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

    private function parseCookie($cookieStr)
    {
        $cookie = array(
            'name' => '',
            'value' => '',
            'domain' => '',
            'path' => '',
            'expires' => null,
            'secure' => false,
            'httponly' => false
        );
        $maxAge = null;

        $parts = explode(';', $cookieStr);
        $first = array_shift($parts);

        if (strpos($first, '=') !== false) {
            list($name, $value) = explode('=', $first, 2);
            $cookie['name'] = trim($name);
            $cookie['value'] = trim($value);
        } else {
            return;
        }

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;

            if (strpos($part, '=') !== false) {
                list($key, $val) = explode('=', $part, 2);
                $key = strtolower(trim($key));
                $val = trim($val);
                if ($key === 'max-age') {
                    $maxAge = is_numeric($val) ? (int)$val : null;
                    continue;
                }
                if (array_key_exists($key, $cookie)) {
                    $cookie[$key] = $val;
                }
            } else {
                $key = strtolower($part);
                if ($key === 'secure') {
                    $cookie['secure'] = true;
                } elseif ($key === 'httponly') {
                    $cookie['httponly'] = true;
                }
            }
        }

        // Max-Ageが指定されている場合はexpiresを合成する (優先、0以下は過去日付になり削除指示として扱われる)
        if ($maxAge !== null) {
            $cookie['expires'] = gmdate('D, d-M-Y H:i:s T', time() + $maxAge);
        }

        $this->cookies[] = $cookie;
    }

    public function getCookies()
    {
        return $this->cookies;
    }
}
