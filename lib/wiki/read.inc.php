<?php
/*
    p2 - スレッド表示でincludeされるファイル
    read系共通で使用しているため削除不可
*/

// ImageCache2が有効の場合画像置換URLを読み込む
if ((!$_conf['iphone'] && $_conf['expack.ic2.enabled'] % 2 == 1) ||
            ($_conf['iphone'] && $_conf['expack.ic2.enabled'] >= 2)) {
    require_once P2_LIB_DIR . '/wiki/ReplaceImageUrlCtl.php';
    $GLOBALS['replaceImageUrlCtl'] = new ReplaceImageUrlCtl();

}
// リンクプラグイン
require_once P2_LIB_DIR . '/wiki/LinkPluginCtl.php';
$GLOBALS['linkPluginCtl'] = new LinkPluginCtl();
// 置換ワード
require_once P2_LIB_DIR . '/wiki/ReplaceWordCtl.php';
$GLOBALS['replaceWordCtl'] = new ReplaceWordCtl();
