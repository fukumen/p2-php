<?php
/**
 * rep2 - デバッグログ表示
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

$ptitle = 'デバッグログ表示';

P2Util::header_nocache();
echo $_conf['doctype'];
?>
<html lang="ja">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
	<meta http-equiv="Content-Style-Type" content="text/css">
	<meta http-equiv="Content-Script-Type" content="text/javascript">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
	<?php echo $_conf['extra_headers_ht']; ?>
	<title><?php echo $ptitle; ?></title>
	<script type="text/javascript" src="js/debug_log.js?<?php echo $_conf['p2_version_id']; ?>"></script>
</head>
<body <?php echo $_conf['ktai'] ? $_conf['k_colors'] : ''; ?>>
<p><?php echo $ptitle; ?> <span id="log-retention-info"></span></p>
<div>※ブラウザの sessionStorage に保存しており、タブやウィンドウを閉じると消去されます。</div>
<hr>
<div style="margin-top: 5px;">
	<label for="log-selector">ログ種別:</label>
	<select id="log-selector">
		<option value="readajax">非同期スレ表示</option>
	</select>
</div>
<div style="margin-top: 5px;">
	<textarea id="log-area" readonly rows="15" cols="40" style="width: 100%;" placeholder="ログがありません。"></textarea>
</div>
<div style="margin-top: 5px;">
	<button id="btn-copy">クリップボードにコピー</button>
</div>
<div style="margin-top: 5px;">
	<button id="btn-clear">このログを消去</button>
</div>
<hr>
<div class="center">
<?php
if ($_conf['iphone']) {
	echo '<a href="javascript:history.back();" class="button">戻る</a>';
} else {
	echo '<a href="editpref.php' . $_conf['k_at_q'] . '"' . ($_conf['k_accesskey_at']['up'] ?? '') . '>' . ($_conf['k_accesskey_st']['up'] ?? '') . '設定編集</a>';
}
echo $_conf['k_to_index_ht'];
?>
</div>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
	var selector = document.getElementById('log-selector');
	var logArea = document.getElementById('log-area');
	var btnCopy = document.getElementById('btn-copy');
	var btnClear = document.getElementById('btn-clear');
	
	var infoSpan = document.getElementById('log-retention-info');
	if (infoSpan && typeof P2DebugLogger !== 'undefined') {
		infoSpan.textContent = '（直近' + P2DebugLogger.RETENTION_HOURS + '時間、Max' + P2DebugLogger.MAX_LOG_COUNT + ' 件）';
	}
	
	function refreshLog() {
		var logType = selector.value;
		if (typeof P2DebugLogger !== 'undefined') {
			var logs = P2DebugLogger.get(logType);
			if (logs.length > 0) {
				logArea.value = logs.join('\n');
			} else {
				logArea.value = 'ログがありません。';
			}
		} else {
			logArea.value = 'ロガーライブラリが読み込まれていません。';
		}
	}
	
	selector.addEventListener('change', refreshLog);
	
	btnCopy.addEventListener('click', function() {
		logArea.select();
		logArea.setSelectionRange(0, 99999);
		try {
			var successful = document.execCommand('copy');
			if (successful) {
				alert('コピーしました');
			} else {
				alert('コピーに失敗しました');
			}
		} catch (err) {
			alert('コピーできませんでした: ' + err);
		}
	});
	
	btnClear.addEventListener('click', function() {
		if (confirm('本当にこのログを消去しますか？')) {
			var logType = selector.value;
			if (typeof P2DebugLogger !== 'undefined') {
				P2DebugLogger.clear(logType);
				refreshLog();
			}
		}
	});
	
	refreshLog();
});
</script>
</body>
</html>
