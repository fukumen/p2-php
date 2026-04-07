<?php
/**
 * rep2expack - フォント設定編集インタフェース
 */

// {{{ 初期化

// 初期設定読み込み & ユーザ認証
require_once __DIR__ . '/../init.php';
$_login->authorize();

require_once P2_LIB_DIR . '/fontconfig.inc.php';

$_flexy_options = &PEAR::getStaticProperty('HTML_Template_Flexy', 'options');
$_flexy_options = array(
    'templateDir' => './skin',
    'compileDir'  => $_conf['compile_dir'] . DIRECTORY_SEPARATOR . 'fontconfig',
    'locale' => 'ja',
    'charset' => 'Shift_JIS',
);

$fontconfig_types = array(
    'windows'        => 'Windows',
    'macos'          => 'macOS',
    'linux'          => 'Linux',
    'android_phone'  => 'Android (スマホ)',
    'android_tablet' => 'Android (タブレット)',
    'iphone'         => 'iPhone',
    'ipad'           => 'iPad',
    'other'          => 'Other',
);
$fontconfig_params = array('enabled', 'fontfamily', 'fontfamily_bold', 'fontweight_bold', 'fontstyle_bold', 'fontfamily_aa', 'aa_textar_webfont', 'fontsize', 'menu_fontsize', 'sb_fontsize', 'read_fontsize', 'respop_fontsize', 'infowin_fontsize', 'form_fontsize', 'aa_fontsize');
$fontconfig_weights = array('normal', 'bold', 'lighter', 'bolder'/*, '100', '200', '300', '400', '500', '600', '700', '800', '900'*/);
$fontconfig_styles = array('normal', 'italic', 'oblique');
$fontconfig_sizes = array('6px', '8px', '9px', '10px', '11px', '12px', '13px', '14px', '16px', '18px', '21px', '24px');
$fontconfig_checkboxs = array('enabled', 'aa_textar_webfont');

$detected_type = p2_fontconfig_detect_agent();
$controllerObject = (object)array(
    'fontconfig_types' => $fontconfig_types,
    'fontconfig_params' => $fontconfig_params,
    'skindata' => p2_fontconfig_load_skin_setting($fontconfig_params),
    'detected_os' => $fontconfig_types[$detected_type],
    'accept_charset' => $_conf['accept_charset'],
    'detect_hint_input_ht' => $_conf['detect_hint_input_ht'],
);

if (file_exists($_conf['expack.skin.fontconfig_path'])) {
    $current_fontconfig = unserialize(file_get_contents($_conf['expack.skin.fontconfig_path']));
    if (!is_array($current_fontconfig) || isset($current_fontconfig['enabled'])) {
        $current_fontconfig = p2_fontconfig_get_osdefaults();
    }
} else {
    FileCtl::make_datafile($_conf['expack.skin.fontconfig_path']);
    $current_fontconfig = p2_fontconfig_get_osdefaults();
}
$fontconfig_hash = md5(serialize($current_fontconfig));
$updated_fontconfig = p2_fontconfig_get_osdefaults();

// }}}

if (!is_dir($_conf['compile_dir'])) {
    FileCtl::mkdirRecursive($_conf['compile_dir']);
}

// テンプレートをコンパイル
$flexy = new HTML_Template_Flexy;
$flexy->compile('edit_user_font.tpl.html');
$elements = $flexy->getElements();

// 変更の適用と、フォームへ値を代入
if (!empty($_POST['clear'])) {
    $_POST = array();
    $current_fontconfig = p2_fontconfig_get_osdefaults();
}
foreach ($fontconfig_params as $pname) {
    $elemName = $pname . '[%s]';
    if (isset($elements[$elemName])) {
        foreach ($fontconfig_types as $tname => $ttitle) {
            $newElemName = sprintf($elemName, $tname);
            if (!isset($elements[$newElemName])) {
                $elements[$newElemName] = clone $elements[$elemName];
            }
            if (!array_key_exists($tname, $updated_fontconfig) ||
                !is_array($updated_fontconfig[$tname]))
            {
                $updated_fontconfig[$tname] = array();
            }
            if (isset($_POST['set'])) {
                if (in_array($pname, $fontconfig_checkboxs)) {
                    $value = isset($_POST[$pname][$tname]);
                } else {
                    $value = isset($_POST[$pname][$tname]) ? trim($_POST[$pname][$tname]) : '';
                }
            } elseif (isset($current_fontconfig[$tname][$pname])) {
                $value = $current_fontconfig[$tname][$pname];
            } else {
                $value = '';
            }
            if ($elements[$newElemName]->tag == 'select') {
                if (strpos($pname, 'fontweight') !== false) {
                    $option_values = $fontconfig_weights;
                    $option_labels = $fontconfig_weights;
                    array_unshift($option_values, '');
                    array_unshift($option_labels, 'inherit');
                    $elements[$newElemName]->setOptions(array_combine($option_values, $option_labels));
                    if ($value !== '' && !in_array($value, $fontconfig_weights)) {
                        $elements[$newElemName]->setOptions(array($value, $value));
                    }
                } elseif (strpos($pname, 'fontstyle') !== false) {
                    $option_values = $fontconfig_styles;
                    $option_labels = $fontconfig_styles;
                    array_unshift($option_values, '');
                    array_unshift($option_labels, 'inherit');
                    $elements[$newElemName]->setOptions(array_combine($option_values, $option_labels));
                    if ($value !== '' && !in_array($value, $fontconfig_styles)) {
                        $elements[$newElemName]->setOptions(array($value, $value));
                    }
                } elseif (strpos($pname, 'fontsize') !== false) {
                    $option_values = $fontconfig_sizes;
                    $option_labels = $fontconfig_sizes;
                    array_unshift($option_values, '');
                    array_unshift($option_labels, 'inherit');
                    $elements[$newElemName]->setOptions(array_combine($option_values, $option_labels));
                    if ($value !== '' && !in_array($value, $fontconfig_sizes)) {
                        $elements[$newElemName]->setOptions(array($value, $value));
                    }
                }
            }
            if ($value !== '') {
                $updated_fontconfig[$tname][$pname] = $value;
            }
            if (in_array($pname, $fontconfig_checkboxs)) {
                $elements[$newElemName]->setValue($value ? "on": "off");
            } else {
                $elements[$newElemName]->setValue($value);
            }
        }
    }
}

// 保存
$fontconfig_data = serialize($updated_fontconfig);
$fontconfig_new_hath = md5($fontconfig_data);
if (strcmp($fontconfig_hash, $fontconfig_new_hath) != 0) {
    FileCtl::file_write_contents($_conf['expack.skin.fontconfig_path'], $fontconfig_data);
}

// スタイルシートをリセット
unset($STYLE);
include $skin;
foreach ($STYLE as $K => $V) {
    if (empty($V)) {
        $STYLE[$K] = '';
    } elseif (strpos($K, 'fontfamily') !== false) {
        $STYLE[$K] = p2_correct_css_fontfamily($V);
    } elseif (strpos($K, 'color') !== false) {
        $STYLE[$K] = p2_correct_css_color($V);
    } elseif (strpos($K, 'background') !== false) {
        $STYLE[$K] = 'url("' . addslashes($V) . '")';
    }
}
if (function_exists('p2_fontconfig_apply_custom')) {
    p2_fontconfig_apply_custom();
}
$controllerObject->STYLE = $STYLE;
$controllerObject->skin = $skin_en;
$controllerObject->p2vid = P2_VERSION_ID;

// 出力
$flexy->outputObject($controllerObject, $elements);

// {{{ p2_fontconfig_load_skin_setting()

/**
 * カスタム設定で上書きされていないスキン設定を読み込む
 */
function p2_fontconfig_load_skin_setting($fontconfig_params)
{
    global $_conf, $STYLE;

    $skindata = array();

    foreach ($fontconfig_params as $key) {
        if ($key === 'enabled') {
            continue;
        } elseif (isset($STYLE["{$key}.orig"])) {
            $skindata[$key] = $STYLE["{$key}.orig"];
        } elseif (isset($STYLE[$key])) {
            $skindata[$key] = $STYLE[$key];
        } else {
            $skindata[$key] = '';
        }
    }

    return $skindata;
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
