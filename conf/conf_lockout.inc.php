<?php
/**
 * rep2 - 管理者用設定ファイル
 *
 * このファイルの設定は、必要に応じて変更してください
 */

// ----------------------------------------------------------------------
// {{{ ロックアウト設定

// 同じIPアドレスからログイン失敗を繰り返しときにロックアウトします
// $_conf['login_log_rec'] と $_conf['login_log_rec_num'] を使用しているので
// 無効化しているとロックアウト判定は機能しません
// ブルートフォース攻撃には効果がありますが、
// PHPアプリ内で拒否するだけなのでCPU資源を食いつぶすような攻撃には効果ありません

// ロックアウト判定するログイン試行回数 (無効:0)
$_conf['login_attempts'] = 5;       // (5)

// ロックアウトする時間 (秒)
$_conf['login_lockout_time'] = 900; // (900)

// }}}
// ----------------------------------------------------------------------
// {{{ Whipの設定

// https://github.com/Vectorface/whip
use Vectorface\Whip\Whip;

// クライアントのIPアドレスを取得する必要が有りますが、環境によって取得方法が異なります

// IPアドレスの取得にrep2ではWhipを使用しており、その設定が必要です
// ケース毎のサンプルを参考に $_conf['whip_args'] を設定してください
// 最悪設定にミスっていても攻撃されたときに自分も締め出されるだけです

// 信頼出来るリーバスproxyがあり正しくヘッダ(X-Forwarded-For等)を設定してくれるケース
// Vectorface\Whip\Whip::$headers に定義されたproxyのヘッダ順に走査されます
/*
$_conf['whip_args'] = [
    Whip::PROXY_HEADERS,
    [
        Whip::PROXY_HEADERS => [
            Whip::IPV4 => [
                '192.168.0.xxx',
            ],
        ],
    ],
];
*/

// リバースproxyもなくグローバルIPの環境で単純に$_SERVER['REMOTE_ADDR']を使えばよいケース
// docker-rep2(network_mode: bridge)を使っている場合も同様
/*
$_conf['whip_args'] = [
    Whip::REMOTE_ADDR,
];
*/

// なんだよくわからんがとにかくよし、というケース
// Dockerやリバースproxyやcloudflare dns proxyやcloudflare zero trust(tunnel)がいてもいなくてもよし
$_conf['whip_args'] = [
    Whip::CLOUDFLARE_HEADERS | Whip::PROXY_HEADERS | Whip::REMOTE_ADDR,
    [
        // ローカルアドレスとプライベートIPの範囲を信頼する
        Whip::PROXY_HEADERS => [
            Whip::IPV4 => [
                '127.0.0.0/8',
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
            ],
            Whip::IPV6 => [
                '::1',
                'fc00::/7',
            ],
        ],

        // CloudflareのIPアドレスは要メンテ
        Whip::CLOUDFLARE_HEADERS => [
            # https://www.cloudflare.com/ips-v4/#
            Whip::IPV4 => [
                '173.245.48.0/20',
                '103.21.244.0/22',
                '103.22.200.0/22',
                '103.31.4.0/22',
                '141.101.64.0/18',
                '108.162.192.0/18',
                '190.93.240.0/20',
                '188.114.96.0/20',
                '197.234.240.0/22',
                '198.41.128.0/17',
                '162.158.0.0/15',
                '104.16.0.0/13',
                '104.24.0.0/14',
                '172.64.0.0/13',
                '131.0.72.0/22',
            ],
            # https://www.cloudflare.com/ips-v6/#
            Whip::IPV6 => [
                '2400:cb00::/32',
                '2606:4700::/32',
                '2803:f800::/32',
                '2405:b500::/32',
                '2405:8100::/32',
                '2a06:98c0::/29',
                '2c0f:f248::/32',
            ],
        ],
    ],
];

$whip = new Whip(...$_conf['whip_args']);
$_conf['whip_clientip'] = $whip->getValidIpAddress();

// }}}

/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:
