<?php
/**
 * rep2 - ƒXƒ^ƒCƒ‹Ý’è
 * for login_first (iPhone)
 */

echo <<<EOSTYLE

body {
    margin: 0;
    padding: 10px;
    line-height: 140%;
    color: #000;
    background-color: #fafafa;
    -webkit-text-size-adjust: none;
}

h3, p, form {
    margin: 8px 0;
}

EOSTYLE;

if ($_conf['mobile_dark_mode'] != 1) {
    $dark_prefix = ($_conf['mobile_dark_mode'] == 0) ? "@media (prefers-color-scheme: dark) {\n" : "";
    $dark_suffix = ($_conf['mobile_dark_mode'] == 0) ? "}\n" : "";
    echo <<<EODARK
{$dark_prefix}
body {
    background: #121212;
    color: #e0e0e0;
}

a:link, a:visited {
    color: #aef;
}
{$dark_suffix}
EODARK;
}

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
