<?php
/**
 * rep2expack - カスタムフォント設定用関数群
 */

/**
 * OS毎のデフォルトを返す
 *
 * @return array
 */
// {{{ p2_fontconfig_get_osdefaults()

function p2_fontconfig_get_osdefaults()
{
    return array(
        'windows' => array(
            'enabled' => false,
            'fontfamily' => '"Yu Gothic", "Meiryo", "MS PGothic", sans-serif',
            'fontfamily_aa' => '"MS PGothic", sans-serif',
            'aa_textar_webfont' => false,
        ),
        'macos' => array(
            'enabled' => false,
            'fontfamily' => '-apple-system, BlinkMacSystemFont, "Hiragino Kaku Gothic ProN", sans-serif',
            'fontfamily_aa' => 'Textar, sans-serif',
            'aa_textar_webfont' => true,
        ),
        'linux' => array(
            'enabled' => false,
            'fontfamily' => '"Noto Sans CJK JP", sans-serif',
            'fontfamily_aa' => 'Textar, sans-serif',
            'aa_textar_webfont' => true,
        ),
        'android_phone' => array(
            'enabled' => true,
            'fontfamily' => 'Roboto, "Noto Sans CJK JP", sans-serif',
            'fontfamily_aa' => 'Textar, sans-serif',
            'aa_textar_webfont' => true,
            'fontsize' => '16px',
            'sb_fontsize' => '16px',
            'read_fontsize' => '16px',
        ),
        'android_tablet' => array(
            'enabled' => true,
            'fontfamily' => 'Roboto, "Noto Sans CJK JP", sans-serif',
            'fontfamily_aa' => 'Textar, sans-serif',
            'aa_textar_webfont' => true,
            'fontsize' => '16px',
            'sb_fontsize' => '16px',
            'read_fontsize' => '16px',
        ),
        'iphone' => array(
            'enabled' => true,
            'fontfamily' => '-apple-system, BlinkMacSystemFont, "Hiragino Kaku Gothic ProN", sans-serif',
            'fontfamily_aa' => 'Textar, sans-serif',
            'aa_textar_webfont' => true,
            'fontsize' => '16px',
            'sb_fontsize' => '16px',
            'read_fontsize' => '16px',
        ),
        'ipad' => array(
            'enabled' => true,
            'fontfamily' => '-apple-system, BlinkMacSystemFont, "Hiragino Kaku Gothic ProN", sans-serif',
            'fontfamily_aa' => 'Textar, sans-serif',
            'aa_textar_webfont' => true,
            'fontsize' => '16px',
            'sb_fontsize' => '16px',
            'read_fontsize' => '16px',
        ),
        'other' => array(
            'enabled' => false,
            'fontfamily' => 'system-ui, sans-serif',
            'fontfamily_aa' => 'Textar, sans-serif',
            'aa_textar_webfont' => true,
        ),
    );
}

// }}}
// {{{ p2_fontconfig_detect_agent()

/**
 * フォント設定用にユーザエージェントを判定する
 *
 * @return string
 */
function p2_fontconfig_detect_agent()
{
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

    if (preg_match('/\bAndroid\b/i', $ua)) {
        return (preg_match('/\bMobile\b/i', $ua)) ? 'android_phone' : 'android_tablet';
    }
    if (preg_match('/\biPhone\b/i', $ua)) {
        return 'iphone';
    }
    if (preg_match('/\biPad\b/i', $ua)) {
        return 'ipad';
    }
    if (preg_match('/\bWindows\b/i', $ua)) {
        return 'windows';
    }
    if (preg_match('/\bMac(?:intoth)?\b/i', $ua)) {
        return 'macos';
    }
    if (preg_match('/\bLinux\b/i', $ua)) {
        return 'linux';
    }

    return 'other';
}

// }}}
// {{{ p2_fontconfig_apply_custom()

/**
 * フォント設定を読み込む
 *
 * @return void
 */
function p2_fontconfig_apply_custom()
{
    global $STYLE, $_conf, $skin_en, $skin_uniq;

    if ($_conf['expack.skin.enabled']) {
        $current_fontconfig = null;
        $fontconfig_data = '';

        // アクティブモナー関係の値はユーザー設定の値を使用する
        $STYLE['fontfamily_aa'] = isset($_conf['expack.am.fontfamily']) ? p2_correct_css_fontfamily($_conf['expack.am.fontfamily']) : $STYLE['fontfamily'];
        $STYLE['aa_textar_webfont'] = (isset($_conf['expack.am.textar_webfont']) && $_conf['expack.am.textar_webfont'] == 1);
        if (!$_conf['ktai']) {
            $STYLE['aa_fontsize'] = $_conf['expack.am.fontsize'] ?? $STYLE['read_fontsize'];
        } else {
            $STYLE['aa_fontsize'] = $_conf['expack.am.fontsize_i'] ?? $STYLE['read_fontsize'];
        }

        if (file_exists($_conf['expack.skin.fontconfig_path'])) {
            $fontconfig_data = file_get_contents($_conf['expack.skin.fontconfig_path']);
            $current_fontconfig = unserialize($fontconfig_data);
        }
        if (!is_array($current_fontconfig) || isset($current_fontconfig['enabled'])) {
            // フォント設定がないか旧形式の場合、デフォルト値を保存
            $current_fontconfig = p2_fontconfig_get_osdefaults();
            $fontconfig_data = serialize($current_fontconfig);
            if (function_exists('FileCtl::file_write_contents')) {
                FileCtl::file_write_contents($_conf['expack.skin.fontconfig_path'], $fontconfig_data);
            } else {
                file_put_contents($_conf['expack.skin.fontconfig_path'], $fontconfig_data);
            }
        }

        $type = p2_fontconfig_detect_agent();

        // 現在のOSのフォント設定が有効ならスタイルを更新
        if ($current_fontconfig[$type]['enabled']) {
            $skin_uniq = $skin_uniq . sprintf('.%u', crc32($fontconfig_data));

            foreach ($current_fontconfig[$type] as $key => $value) {
                if ($value === '' || $key === 'enabled') {
                    continue;
                } else {
                    $STYLE["{$key}.orig"] = isset($STYLE[$key]) ? $STYLE[$key] : '';
                    if (strpos($key, 'fontfamily') !== false) {
                        $STYLE[$key] = p2_correct_css_fontfamily($value);
                    } else {
                        $STYLE[$key] = $value;
                    }
                }
            }

            $skin_en = preg_replace('/&amp;_=[^&]*/', '', $skin_en) . '&amp;_=' . rawurlencode($skin_uniq);
        }
    }
}

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
