/*
 * rep2 - デバッグログ共通ライブラリ
 */

var P2DebugLogger = {
	// ログ保持期間（時間）
	'RETENTION_HOURS': 24,
	// 最大ログ保持件数
	'MAX_LOG_COUNT': 100,

	// ログを記録する
	'log': function(type, message) {
		var key = 'p2_debug_log_' + type;
		var logs = [];
		
		try {
			logs = JSON.parse(sessionStorage.getItem(key) || '[]');
		} catch (e) {
			return;
		}

		var now = Date.now();
		var timeStr = (new Date(now)).toLocaleTimeString();
		
		logs.push({
			'timestamp': now,
			'text': '[' + timeStr + '] ' + message
		});

		// 指定時間以内のログのみを保持する
		var retentionMs = P2DebugLogger.RETENTION_HOURS * 60 * 60 * 1000;
		var oneDayAgo = now - retentionMs;
		logs = logs.filter(function(item) {
			return item.timestamp >= oneDayAgo;
		});

		// 最大件数を超えた場合は、最新の最大件数のみを残す
		if (logs.length > P2DebugLogger.MAX_LOG_COUNT) {
			logs = logs.slice(-P2DebugLogger.MAX_LOG_COUNT);
		}

		try {
			sessionStorage.setItem(key, JSON.stringify(logs));
		} catch (e) {
			// 容量オーバー時に古いものをさらに半分削除する
			if (logs.length > 50) {
				logs = logs.slice(Math.floor(logs.length / 2));
				try {
					sessionStorage.setItem(key, JSON.stringify(logs));
				} catch (ex) {}
			}
		}
	},

	// ログを取得する
	'get': function(type) {
		var key = 'p2_debug_log_' + type;
		var logs = [];
		
		try {
			logs = JSON.parse(sessionStorage.getItem(key) || '[]');
		} catch (e) {
			return [];
		}

		var now = Date.now();
		var retentionMs = P2DebugLogger.RETENTION_HOURS * 60 * 60 * 1000;
		var oneDayAgo = now - retentionMs;
		
		var activeLogs = logs.filter(function(item) {
			return item.timestamp >= oneDayAgo;
		});

		// 最大件数を超えた場合は、最新の最大件数のみを残す
		if (activeLogs.length > P2DebugLogger.MAX_LOG_COUNT) {
			activeLogs = activeLogs.slice(-P2DebugLogger.MAX_LOG_COUNT);
		}

		// 期限切れのログが除外されていれば書き戻す
		if (activeLogs.length !== logs.length) {
			try {
				sessionStorage.setItem(key, JSON.stringify(activeLogs));
			} catch (e) {}
		}

		return activeLogs.map(function(item) {
			return item.text;
		});
	},

	// ログを消去する
	'clear': function(type) {
		var key = 'p2_debug_log_' + type;
		try {
			sessionStorage.removeItem(key);
		} catch (e) {}
	}
};
