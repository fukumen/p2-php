<?php
/**
 * rep2expack - ŠG•¶Žš
 */

// {{{ p2_get_emoji()

/**
 * ŠG•¶Žš‚ðŽæ“¾‚·‚é
 *
 * @return array
 */
function p2_get_emoji()
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $emoji = array(
        0 => '0.',
        1 => '1.',
        2 => '2.',
        3 => '3.',
        4 => '4.',
        5 => '5.',
        6 => '6.',
        7 => '7.',
        8 => '8.',
        9 => '9.',
        '*' => '*.',
        '#' => '#.',
        'ut1' => '£',
        'ut2' => '¢',
        'dt1' => '¥',
        'dt2' => '¤',
        'lt1' => '&lt;',
        'lt2' => '&laquo;',
        'rt1' => '&gt;',
        'rt2' => '&raquo;',
    );

    $cache = $emoji;
    return $emoji;
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
