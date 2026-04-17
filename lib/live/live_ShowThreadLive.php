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

        //=============================================================
        // 以降、不要そうだけど証明できないしメンテしつつ残す
        //=============================================================
        $ng_badges = array();
        // NGメッセージ変換
        if ($ng_type != self::NG_NONE && count($ng_info)) {
            $ng_info = implode(', ', $ng_info);
            $msg = <<<EOMSG
<span class="ngword">{$ng_info}</span>
<div id="ngn{$ngaborns_head_hits}" class="ngmsg ngmsg-by-msg">{$msg}</div>
EOMSG;
            $ng_badges[] = '本文';
        }

        // NGネーム変換
        if ($ng_type & self::NG_NAME) {
	        $name = preg_replace("(<b>|</b>)", "", $name);
            $name = <<<EONAME
</b><span class="ngword">{$name}</span><b>
EONAME;
            $ng_badges[] = '名前';
        } elseif ($ng_type & self::NG_WATCHOI) {
            if ($_conf['ngaborn_watchoi4']) {
                $pattern = '#\(([^\s()]+\s+)?([0-9A-Za-z./*+]{4}-)([0-9A-Za-z./*+]{4}(?:\s+\[.+])?)\)#';
                $name = preg_replace($pattern, "($1<span class=\"ngword\">$2</span>$3)", $name);
            } else {
                $pattern = '#\(((?:[^\s()]+\s+)?(?:[0-9A-Za-z./*+]{4}-[0-9A-Za-z./*+]{4})(?:\s+\[.+])?)\)#';
                $name = preg_replace($pattern, "(<span class=\"ngword\">$1</span>)", $name);
            }
            $ng_badges[] = 'ワッチョイ';
        }

        // NGメール変換
        if ($ng_type & self::NG_MAIL) {
            $mail = <<<EOMAIL
<span class="ngword">{$mail}</span>
EOMAIL;
            $ng_badges[] ='メール';
        }

        // NGID変換
        if ($ng_type & self::NG_ID) {
            $date_id = <<<EOID
<span class="ngword">{$date_id}</span>
EOID;
            $ng_badges[] = 'ID';
        }

        // NGメッセージ変換(msg)
        if ($ng_type != self::NG_NONE && !empty($ng_info)) {
            // 変換済み

        // NGネーム変換(msg)
        } elseif ($ng_type & (self::NG_NAME | self::NG_WATCHOI)) {
            $msg = <<<EOMSG
<div id="ngn{$ngaborns_head_hits}" class="ngmsg ngmsg-by-name">{$msg}</div>
EOMSG;

        // NGメール変換(msg)
        } elseif ($ng_type & self::NG_MAIL) {
            $msg = <<<EOMSG
<div id="ngn{$ngaborns_head_hits}" class="ngmsg ngmsg-by-mail">{$msg}</div>
EOMSG;

        // NGID変換(msg)
        } elseif ($ng_type & self::NG_ID) {
            $msg = <<<EOMSG
<div id="ngn{$ngaborns_head_hits}" class="ngmsg ngmsg-by-id">{$msg}</div>
EOMSG;

        }

        // NG開閉バッジ
        $ng_badge_html = '';
        if (!empty($ng_badges)) {
            $reason = implode(', ', $ng_badges);
            $ng_badge_html = <<<EOBADGE
 <span style="cursor:pointer; margin-left:0.5em;" onclick="show_ng_message('ngn{$ngaborns_head_hits}');">[NG: {$reason}]</span>
EOBADGE;
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
            $has_watchoi = isset($this->thread->watchois[$i]) ? 'true' : 'false';
            $spmeh = " onmouseover=\"{$this->spmObjName}.show({$i},'{$msg_id}',event,{$js_date},{$has_watchoi})\"";
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
