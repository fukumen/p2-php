<?php

// {{{ P2KeyValueStore_Codec_JSONShiftJIS

/**
 * 値をJSONエンコード・デコードするCodec
 *
 * 文字列は妥当なCP932シーケンスでなければならず、
 * 配列のキーは数値かUS-ASCII文字列であることを期待する。
 */
class P2KeyValueStore_Codec_JSONShiftJIS extends P2KeyValueStore_Codec_JSON
{
    // {{{ encodeValue()

    /**
     * 値をUTF-8に変換した後、JSONエンコードする
     *
     * @param mixed $value
     * @return string
     */
    public function encodeValue($value)
    {
        mb_convert_variables('UTF-8', 'CP932', $value);
        return parent::encodeValue($value);
    }

    // }}}
    // {{{ decodeValue()

    /**
     * 値をJSONデコードした後、CP932に変換する
     *
     * @param string $value
     * @return mixed
     */
    public function decodeValue($json)
    {
        $value = parent::decodeValue($json);
        mb_convert_variables('CP932', 'UTF-8', $value);
        return $value;
    }

    // }}}
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
