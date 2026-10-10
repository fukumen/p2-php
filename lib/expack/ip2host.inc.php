<?php

// {{{ ip2host.inc.php

/**
 * ip2host
 */

$ip2host_scroll_replace = (int)$_conf['ip2host.replace.type'];
if (!empty($is_readajax_active) || (array_key_exists('live', $_GET) && $_GET['live'])) {
    $ip2host_scroll_replace = 0;
}
$ip2host_config = array(
    'scroll_replace' => $ip2host_scroll_replace,
    'cache_type' => (int)$_conf['ip2host.cache.type'],
    'cache_size' => (int)$_conf['ip2host.cache.size'],
);
echo '<script type="text/javascript">var ip2host_config = ' . json_encode($ip2host_config) . ";</script>\n";
echo '<script type="text/javascript" src="js/debug_log.js?' . $_conf['p2_version_id'] . '"></script>' . "\n";
echo '<script type="text/javascript" src="js/ip2host.js?' . $_conf['p2_version_id'] . '"></script>' . "\n";

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
