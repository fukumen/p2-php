<?php
/**
 * POSTリクエスト用のHTTP_Request2コンパチクラス
 * 
 * @copyright 2026 fukumen (https://github.com/fukumen)
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 */
class P2CurlRequest
{
    private $url;
    private $method;
    private $headers = array();
    private $config = array();
    private $postParams = array();
    private $body = null;
    private $cookies = array();
    private $debugfile = null;

    public function __construct($url, $method = 'GET')
    {
        if ($method !== 'GET' && $method !== 'POST') {
            throw new Exception("P2CurlRequest only supports GET and POST methods.");
        }
        $this->url = $url;
        $this->method = $method;
    }

    public function setHeader($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->headers[strtolower($k)] = $v;
            }
        } else {
            $this->headers[strtolower($name)] = $value;
        }
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
        // Do nothing
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
        curl_setopt($ch, CURLOPT_HEADER, true);

        if (isset($this->config['connect_timeout'])) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->config['connect_timeout']);
        }
        if (isset($this->config['timeout'])) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        }
        if (isset($this->config['ssl_capath'])) {
            curl_setopt($ch, CURLOPT_CAPATH, $this->config['ssl_capath']);
        }
        if (isset($this->config['proxy_host'])) {
            curl_setopt($ch, CURLOPT_PROXY, $this->config['proxy_host']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->config['proxy_port']);
            if (isset($this->config['proxy_user'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->config['proxy_user'] . ':' . $this->config['proxy_password']);
            }
            if (isset($this->config['proxy_type']) && $this->config['proxy_type'] == 'socks5') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            }
        }
        if (isset($this->config['follow_redirects']) && $this->config['follow_redirects']) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }

        $protocol_version = isset($this->config['protocol_version']) ? $this->config['protocol_version'] : '1.1';
        switch ($protocol_version) {
            case '2.0':
                if (defined('CURL_HTTP_VERSION_2_0')) {
                    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
                }
                break;
            case '1.0':
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
                break;
            case '1.1':
            default:
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                break;
        }

        if ($this->method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($this->postParams)) {
                if (!isset($this->headers['content-type'])) {
                    $this->headers['content-type'] = 'application/x-www-form-urlencoded';
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->postParams, '', '&'));
            } elseif ($this->body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
            }
        }

        if (!empty($this->cookies)) {
            $cookieParts = array();
            foreach ($this->cookies as $name => $value) {
                $cookieParts[] = urlencode($name) . '=' . urlencode($value);
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookieParts));
        }

        $headers = array();
        foreach ($this->headers as $k => $v) {
            if ($k === 'accept-encoding') {
                curl_setopt($ch, CURLOPT_ENCODING, $v);
                continue;
            }
            $headers[] = ucwords($k, '-') . ': ' . $v;
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
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            if (class_exists('HTTP_Request2_Exception')) {
                throw new HTTP_Request2_Exception("cURL Error: " . $error);
            }
            throw new Exception("cURL Error: " . $error);
        }

        return new P2CurlResponse($result, $info);
    }
}

class P2CurlResponse
{
    private $status;
    private $body;
    private $headers = array();
    private $cookies = array();

    public function __construct($result, $info)
    {
        $this->status = $info['http_code'];
        $header_size = $info['header_size'];
        $header_text = substr($result, 0, $header_size);
        $this->body = substr($result, $header_size);

        foreach (explode("\r\n", $header_text) as $i => $line) {
            if ($i === 0) continue;
            if (empty($line)) continue;
            $parts = explode(': ', $line, 2);
            if (count($parts) == 2) {
                $this->headers[strtolower($parts[0])] = $parts[1];
                if (strtolower($parts[0]) === 'set-cookie') {
                    $this->parseCookie($parts[1]);
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

    public function getHeader($name)
    {
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

        $this->cookies[] = $cookie;
    }

    public function getCookies()
    {
        return $this->cookies;
    }
}
