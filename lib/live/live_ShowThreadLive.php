<?php
/**
 * rep2 - スレッドを表示しないでSPMとかのコードを作成する クラス live_read.php専用
 */

// {{{ ShowThreadLive

class ShowThreadLive extends ShowThreadPc
{
    // {{{ constructor

    /**
     * コンストラクタ
     */
    public function __construct($aThread, $matome = false)
    {
        parent::__construct($aThread, $matome);
    }

    // }}}
    // {{{ transRes()

    /**
     * DatレスをHTMLレスに変換する（だだし表示できない）
     * 削除したlive_ShowThreadPc.phpから拝借
     *
     * @param   string  $ares       datの1ライン
     * @param   int     $i          レス番号
     * @param   string  $pattern    ハイライト用正規表現
     * @return  string
     */
    public function transRes($ares, $i, $pattern = null)
    {
        global $_conf, $STYLE, $mae_msg;

        // +Wiki:置換ワード
        if (isset($GLOBALS['replaceWordCtl'])) {
            $replaceWordCtl = $GLOBALS['replaceWordCtl'];
            $name    = $replaceWordCtl->replace('name', $this->thread, $ares, $i);
            $mail    = $replaceWordCtl->replace('mail', $this->thread, $ares, $i);
            $date_id = $replaceWordCtl->replace('date', $this->thread, $ares, $i);
            $msg     = $replaceWordCtl->replace('msg',  $this->thread, $ares, $i);
        } else {
            list($name, $mail, $date_id, $msg) = $this->thread->explodeDatLine($ares);
        }

        // どんぐり大砲用の日付を抽出
        $raw_date = null;
        if ($_conf['donguri_use'] && preg_match('/^\d{4}\/\d{2}\/\d{2}\(.+?\) \d{2}:\d{2}:\d{2}(\.\d+)?/', $date_id, $m)) {
            $raw_date = p2h($m[0]);
        }

        if (($id = $this->thread->ids[$i]) !== null) {
            $idstr = 'ID:' . $id;
            $date_id = str_replace($this->thread->idp[$i] . $id, $idstr, $date_id);
        } else {
            $idstr = null;
        }

        $tores = '';
        $rpop = '';
        if ($this->_matome) {
            $res_id = "t{$this->_matome}r{$i}";
            $msg_id = "t{$this->_matome}m{$i}";
        } else {
            $res_id = "r{$i}";
            $msg_id = "m{$i}";
        }
        $msg_class = 'message';

        // NGあぼーんチェック
        $ng_type = $this->_ngAbornCheck($i, strip_tags($name), $mail, $date_id, $id, $msg, false, $ng_info);
        if ($ng_type == self::ABORN) {
            return $this->_abornedRes($res_id);
        }
        if ($ng_type != self::NG_NONE) {
            $ngaborns_head_hits = self::$_ngaborns_head_hits;
            $ngaborns_body_hits = self::$_ngaborns_body_hits;
        }

        // AA判定
        if ($this->am_autodetect && $this->activeMona->detectAA($msg)) {
            $msg_class .= ' ActiveMona';
        }

        //=============================================================
        // まとめて出力
        //=============================================================

        $name = $this->transName($name); // 名前HTML変換
        $msg = $this->transMsg($msg, $i); // メッセージHTML変換

        // BEプロファイルリンク変換
        $date_id = $this->replaceBeId($date_id, $i);

        // IDフィルタ
        if ($_conf['flex_idpopup'] == 1 && $id && $this->thread->idcount[$id] > 1) {
            $date_id = str_replace($idstr, $this->idFilter($idstr, $id), $date_id);
        }

        // HTMLポップアップ
        if ($_conf['iframe_popup']) {
            $date_id = preg_replace_callback("{<a href=\"(http://[-_.!~*()0-9A-Za-z;/?:@&=+\$,%#]+)\"({$_conf['ext_win_target_at']})>((\?#*)|(Lv\.\d+))</a>}", array($this, 'iframePopupCallback'), $date_id);
        }

        // NGメッセージ変換
        if ($ng_type != self::NG_NONE && count($ng_info)) {
            $ng_info = implode(', ', $ng_info);
            $msg = <<<EOMSG
<span class="ngword" onclick="show_ng_message('ngm{$ngaborns_body_hits}', this);">{$ng_info}</span>
<div id="ngm{$ngaborns_body_hits}" class="ngmsg ngmsg-by-msg">{$msg}</div>
EOMSG;
        }

        // NGネーム変換
        if ($ng_type & self::NG_NAME) {
            $name = <<<EONAME
<span class="ngword" onclick="show_ng_message('ngn{$ngaborns_head_hits}', this);">{$name}</span>
EONAME;
            $msg = <<<EOMSG
<div id="ngn{$ngaborns_head_hits}" class="ngmsg ngmsg-by-name">{$msg}</div>
EOMSG;

        // NGメール変換
        } elseif ($ng_type & self::NG_MAIL) {
            $mail = <<<EOMAIL
<span class="ngword" onclick="show_ng_message('ngn{$ngaborns_head_hits}', this);">{$mail}</span>
EOMAIL;
            $msg = <<<EOMSG
<div id="ngn{$ngaborns_head_hits}" class="ngmsg ngmsg-by-mail">{$msg}</div>
EOMSG;

        // NGID変換
        } elseif ($ng_type & self::NG_ID) {
            $date_id = <<<EOID
<span class="ngword" onclick="show_ng_message('ngn{$ngaborns_head_hits}', this);">{$date_id}</span>
EOID;
            $msg = <<<EOMSG
<div id="ngn{$ngaborns_head_hits}" class="ngmsg ngmsg-by-id">{$msg}</div>
EOMSG;

        }

        /*
        //「ここから新着」画像を挿入
        if ($i == $this->thread->readnum +1) {
            $tores .= <<<EOP
                <div><img src="img/image.png" alt="新着レス" border="0" vspace="4"></div>
EOP;
        }
        */

        // SPM
        if ($_conf['expack.spm.enabled']) {
            $js_date = $raw_date ? "'$raw_date'" : 'null';
            $spmeh = " onmouseover=\"{$this->spmObjName}.show({$i},'{$msg_id}',event,{$js_date})\"";
            $spmeh .= " onmouseout=\"{$this->spmObjName}.hide(event)\"";
        } else {
            $spmeh = '';
        }

		// +live スレ内容表示部削除

        /*if ($_conf['expack.am.enabled'] == 2) {
            $tores .= <<<EOJS
<script type="text/javascript">
//<![CDATA[
detectAA("{$msg_id}");
//]]>
</script>\n
EOJS;
        }*/

        // まとめてフィルタ色分け
        if ($pattern) {
            $tores = StrCtl::filterMarking($pattern, $tores);
        }

        return array('body' => $tores, 'q' => $rpop);
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
