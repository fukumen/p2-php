<?php
/*
	+live - ハイライトワードの変換 ../ShowThreadPc.php より読み込まれる
*/

$live_highlight_style = "background-color: {$STYLE['live_highlight']}; font-weight: {$STYLE['live_highlight_word_weight']}; border-bottom: {$STYLE['live_highlight_word_border']};";
$live_highlight_chain_style = "background-color: {$STYLE['live_highlight_chain']}; font-weight: {$STYLE['live_highlight_word_weight']}; border-bottom: {$STYLE['live_highlight_word_border']};";

// 連鎖ハイライト変換
if ($ng_type & self::HIGHLIGHT_CHAIN) {
	$pattern_chain_nums = implode('|', array_intersect($highlight_chain_nums, $this->_highlight_nums));
	$pattern_chain_nums = "(" . $pattern_chain_nums . ")(?!\d)(?![^<]*>)"; // 数字が一部被ってしまうアンカーとHTMLタグ内にマッチさせない
	$msg = preg_replace("(((?:&gt;|＞|-)+)($pattern_chain_nums))", "<span class=\"live_highlight_chain\" style=\"{$live_highlight_chain_style}\">$1$2</span>", $msg);
}

// ハイライトメッセージ変換
if ($ng_type & self::HIGHLIGHT_MSG) {
	foreach ($highlight_msgs as $h_msg) {
		if (preg_match('{^(?:<(regex|regex:i|regexi|i)>)?(?:<bbs>(.+?)</bbs>)?(?:<title>(.+?)</title>)?(.+)$}s', $h_msg, $matches)) {
			$mode = $matches[1];
			$word = $matches[4];

			$is_regex = in_array($mode, array('regex', 'regex:i', 'regexi'));
			$ignore_case = in_array($mode, array('regex:i', 'regexi', 'i'));

			if (!$is_regex) {
				$word = quotemeta($word);
			}

			$pattern = "(" . $word . ")(?![^<]*>)"; // HTMLタグ内にマッチさせない

			if ($ignore_case) {
				$msg = mb_eregi_replace($pattern, "<span class=\"live_highlight\" style=\"{$live_highlight_style}\">\\1</span>", $msg);
			} else {
				$msg = mb_ereg_replace($pattern, "<span class=\"live_highlight\" style=\"{$live_highlight_style}\">\\1</span>", $msg);
			}
		}
	}
}

// ハイライトネーム変換
if ($ng_type & self::HIGHLIGHT_NAME) {
	$name = preg_replace("(<b>|</b>)", "", $name);
	$name = "</b><span class=\"live_highlight\" style=\"{$live_highlight_style}\">$name</span><b>";
} elseif ($ng_type & self::HIGHLIGHT_WATCHOI) {
	if ($_conf['ngaborn_watchoi4']) {
		$pattern = '#\(([^\s()]+\s+)?([0-9A-Za-z./*+]{4}-)([0-9A-Za-z./*+]{4}(?:\s+\[.+])?)\)#';
		$name = preg_replace($pattern, "($1<span class=\"live_highlight\" style=\"{$live_highlight_style}\">$2</span>$3)", $name);
	} else {
		$pattern = '#\(((?:[^\s()]+\s+)?(?:[0-9A-Za-z./*+]{4}-[0-9A-Za-z./*+]{4})(?:\s+\[.+])?)\)#';
		$name = preg_replace($pattern, "(<span class=\"live_highlight\" style=\"{$live_highlight_style}\">$1</span>)", $name);
	}
}

// ハイライトメール変換
if ($ng_type & self::HIGHLIGHT_MAIL) {
	$mail = "<span class=\"live_highlight\" style=\"{$live_highlight_style}\">$mail</span>";
}

// ハイライトID変換
if ($ng_type & self::HIGHLIGHT_ID) {
	$date_id = preg_replace("((ID:))", "<span class=\"live_highlight\" style=\"{$live_highlight_style}\">$1", $date_id ."</span>");
}

?>