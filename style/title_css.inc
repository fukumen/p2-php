<?php
/**
 * rep2 - スタイル設定
 * for title.php タイトルページ
 */

echo <<<EOSTYLE

.rep2_logo_svg {
    display: inline-block;
    width: 120px;
    height: 63px;
    color: {$STYLE['textcolor']};
    opacity: 0.4;
}
.rep2_logo_svg svg {
    width: 100%;
    height: 100%;
}

table caption {
    text-align: left;
    font-size: {$STYLE['fontsize']};
}

table th {
	padding-right: 0.25em;
	text-align: right;
	vertical-align: top;
	white-space: nowrap;
	line-height: 100%;
	font-weight: normal;
	font-size: {$STYLE['fontsize']};
}

table td {
	text-align: left;
	vertical-align: top;
	line-height: 100%;
	font-size: {$STYLE['fontsize']};
}

EOSTYLE;

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
