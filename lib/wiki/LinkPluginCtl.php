<?php
/*
replaceLinkToHTML(url, src) メイン関数
save(array)                 データを保存
load()                      データを読み込んで返す(自動的に実行される)
clear()                     データを削除
*/

require_once __DIR__ . '/WikiPluginCtlBase.php';

class LinkPluginCtl extends WikiPluginCtlBase
{
    protected $filename = 'p2_plugin_link.txt';
    protected $data = array();

    public function clear()
    {
        global $_conf;

        $path = $_conf['pref_dir'] . '/' . $this->filename;

        return @unlink($path);
    }

    public function load()
    {
        global $_conf;

        $lines = array();
        $path = $_conf['pref_dir'] . '/' . $this->filename;
        if ($lines = @file($path)) {
            foreach ($lines as $l) {
                $lar = explode("\t", trim($l));
                if (strlen($lar[0]) == 0) {
                    continue;
                }
                $ar = array(
                    'match'   => $lar[0], // 対象文字列
                    'replace' => $lar[1], // 置換文字列
                );

                $this->data[] = $ar;
            }
        }

        return $this->data;
    }

    /*
    $data[$i]['match']       Match
    $data[$i]['replace']     Replace
    $data[$i]['del']         削除
    */
    public function save($data)
    {
        global $_conf;

        $path = $_conf['pref_dir'] . '/' . $this->filename;

        $newdata = '';

        foreach ($data as $na_info) {
            $a[0] = strtr(trim($na_info['match'], "\t\r\n"), "\t\r\n", "   ");
            $a[1] = strtr(trim($na_info['replace'], "\t\r\n"), "\t\r\n", "   ");
            if ($na_info['del'] ?? false || ($a[0] === '' || $a[1] === '')) {
                continue;
            }
            $newdata .= implode("\t", $a) . "\n";
        }

        return FileCtl::file_write_contents($path, $newdata);
    }

    public function replaceLinkToHTML($url, $str)
    {
        global $_conf;

        $this->setup();

        $src = false;
        foreach ($this->data as $v) {
            // 置換を試行
            $temp = @preg_replace ('{'.$v['match'].'}', $v['replace'], $url, -1, $count);
            // 置換された場合
            if ($count) {
                $src = $temp;
                $replace_pairs = array();
                if (strstr($v['replace'], '$ime_url')) {
                    $replace_pairs['$ime_url'] = P2Util::throughIme($url);
                }
                if (strstr($v['replace'], '$str')) {
                    $replace_pairs['$str'] = $str;
                }
                if (strstr($v['replace'], '$atag')) {
                    // ime
                    if ($_conf['through_ime']) {
                        $link_url = P2Util::throughIme($url);
                    } else {
                        $link_url = $url;
                    }
                    // HTMLポップアップ(PCの時だけ)
                    if ($_conf['iframe_popup'] && !$_conf['ktai']) {
                        // *pm 指定の場合のみ、特別に手動転送指定を追加する
                        if (substr($_conf['through_ime'], -2) == 'pm') {
                            $pop_url = P2Util::throughIme($url, -1);
                        } else {
                            $pop_url = $link_url;
                        }
                        $replace_pairs['$atag'] = ShowThreadPc::iframePopup(array($link_url, $pop_url), $str, $_conf['ext_win_target_at']);
                    } else {
                        $replace_pairs['$atag'] = "<a href=\"{$link_url}\"{$_conf['ext_win_target_at']}>{$str}</a>";
                    }
                }
                // 変数を展開
                $src = strtr($src, $replace_pairs);
                break;
            }
        }
        return $src;
    }

}
