/*
 * rep2 - スレ表示の非同期(Ajax)モード
 * 
 * 制約事項: 画面遷移時以降に新規書き込みされたレスを非同期で読んだ場合、
 * 非同期で読んだレスのIDやワッチョイのカウント数はその時点（全文を再集計した値）が反映されるが、
 * 非同期で読む以前に読み込み済みのレスの表示にはカウント数が反映されない
 * PCのLiveモードのAjaxは全レス置き換えするのでこの制約はない
 */

// {{{ iutil.readajax

iutil.readajax = {
	'config': null,				// 設定オブジェクト
	'placeholders': null,		// プレースホルダー管理オブジェクト
	'observerTop': null,		// 上部スクロール監視
	'observerBottom': null,		// 下部スクロール監視
	'observerResnum': null,		// レス可視・既読判定用監視
	'pendingJumpRes': null,		// ジャンプ先の保留レス番号
	'abortPrev': null,			// 後方ロード用AbortController
	'abortNext': null,			// 前方ロード用AbortController
	'prevRange': null,			// 後方の次回ロード範囲
	'nextRange': null,			// 前方の次回ロード範囲
	'maxVisibleResnum': 0,		// 画面内に表示された最大レス番号
	'lastSavedReadnum': 0,		// 保存済みの既読レス番号
	'readnumTimer': null,		// 既読保存タイマー
	'firstResnum': 0,			// 読み込み済みの最初のレス番号
	'lastResnum': 0,			// 読み込み済みの最後のレス番号
	'threadElement': null,		// スレッド表示コンテナのDOM要素
	'sentinelTop': null,		// 上部センチネルのDOM要素
	'sentinelBottom': null,		// 下部センチネルのDOM要素
	'newresElements': null,		// 未読のnewres要素を保持するオブジェクト (キー: レス番号, 値: span要素)
	'threadCleared': false,		// clearThread() でスレッドをクリアした直後か

	// ==========================================
	// 初期化ロジック
	// ==========================================
	'init': function() {
		if (!iutil_preload_config) {
			return;
		}
		if (typeof IntersectionObserver === 'undefined' ||
			typeof navigator.sendBeacon === 'undefined' ||
			typeof FormData === 'undefined') {
			return;
		}
		this.config = iutil_preload_config;
		this.config.prev = parseInt(this.config.prev, 10);
		this.config.next = parseInt(this.config.next, 10);
		this.config.num = parseInt(this.config.num, 10);
		this.config.timing = parseInt(this.config.timing, 10);
		this.config.readnum_timer = parseInt(this.config.readnum_timer, 10);
		this.config.jumpjdg = parseInt(this.config.jumpjdg, 10);
		this.config.fetch_timeout = parseInt(this.config.fetch_timeout, 10);
		this.config.realtimedisp_readnum = parseInt(this.config.realtimedisp_readnum, 10);
		this.config.rescount = parseInt(this.config.rescount, 10);
		this.config.datochiok = parseInt(this.config.datochiok, 10);
		this.config.rpp = parseInt(this.config.rpp, 10);

		// DOM要素のキャッシュ
		this.threadElement = document.getElementById('thread');
		this.sentinelTop = document.getElementById('sentinel-top');
		this.sentinelBottom = document.getElementById('sentinel-bottom');
		if (!this.threadElement || !this.sentinelTop || !this.sentinelBottom) {
			return;
		}

		// 初期表示されている newres 要素を収集
		this.newresElements = {};
		var newresEls = document.getElementsByClassName('newres');
		for (var j = 0; j < newresEls.length; j++) {
			var span = newresEls[j];
			var num = parseInt(span.textContent || span.innerText, 10);
			if (!isNaN(num)) {
				this.newresElements[num] = span;
			}
		}

		var keys = Object.keys(this.newresElements).map(function(k) { return parseInt(k, 10); });
		if (keys.length > 0) {
			var minNewresNum = Math.min.apply(null, keys);
			this.lastSavedReadnum = minNewresNum - 1;
			this.maxVisibleResnum = minNewresNum - 1;
		}

		if (document.body) {
			document.body.style.overflowAnchor = 'none';
		}
		P2DebugLogger.log('readajax', 'Init readajax. config=' + JSON.stringify(this.config));
		var prev = this.config.prev;
		var next = this.config.next;
		var num = this.config.num;
		var resEls = document.getElementsByClassName('res');
		if (resEls.length === 0) {
			return;
		}
		var firstMatch = resEls[0].id.match(/r(\d+)$/);
		var lastMatch = resEls[resEls.length - 1].id.match(/r(\d+)$/);
		if (!firstMatch || !lastMatch) {
			return;
		}
		var rescount = this.config.rescount;
		var currentStart = parseInt(firstMatch[1], 10);
		var currentTo = parseInt(lastMatch[1], 10);
		this.firstResnum = currentStart;
		this.lastResnum = currentTo;

		this.placeholders = {
			'prev': new iutil.ReadAjaxPlaceholder('prev', this),
			'next': new iutil.ReadAjaxPlaceholder('next', this)
		};

		this.updateJumpSelect();
		
		var initPrev = prev > 0 ? prev : num;
		if (initPrev > 0 && currentStart > 1) {
			var prevStart = Math.max(1, currentStart - initPrev);
			if (prevStart >= 1) {
				this.prevRange = {
					'start': prevStart,
					'to': currentStart - 1
				};
			}
		}
		
		var initNext = next > 0 ? next : num;
		if (initNext > 0 && currentTo < rescount) {
			this.nextRange = {
				'start': currentTo + 1,
				'to': Math.min(rescount, currentTo + initNext)
			};
		}
		var self = this;
		// 初回スクロール時、またはページ読み込み完了時に既読オブザーバーを動的初期化する
		// （レイアウト確定後に安全に高さを取得して交差監視を開始するため）
		var thread = this.threadElement;
		var startObserver = function() {
			self.setupObserverResnum();
			thread.removeEventListener('scroll', startObserver, false);
			window.removeEventListener('load', startObserver, false);
		};
		thread.addEventListener('scroll', startObserver, false);
		window.addEventListener('load', startObserver, false);

		// 引っ張って新規をチェック
		var startY = 0;
		var isPulling = false;
		var pullThreshold = 100;
		var originalText = '';
		var isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
		
		thread.addEventListener('touchstart', function(e) {
			if (self.placeholders.next && self.placeholders.next.state === 'check_new') {
				if (thread.scrollHeight - thread.scrollTop <= thread.clientHeight + 1) {
					startY = e.touches[0].clientY;
					isPulling = true;
					originalText = self.placeholders.next.element.innerHTML;
					
					if (!isIOS) {
						thread.style.transition = 'none';
					}
				}
			}
		}, { passive: true });
		
		thread.addEventListener('touchmove', function(e) {
			if (!isPulling) return;
			var currentY = e.touches[0].clientY;
			var distance = startY - currentY;
			if (distance <= 0) {
				isPulling = false;
				if (self.placeholders.next && self.placeholders.next.state === 'check_new') {
					self.placeholders.next.element.innerHTML = originalText;
				}
				if (!isIOS) {
					thread.style.transform = '';
				}
			} else if (self.placeholders.next && self.placeholders.next.state === 'check_new') {
				if (distance > pullThreshold) {
					self.placeholders.next.element.innerHTML = '離して新着をチェック...';
				} else {
					self.placeholders.next.element.innerHTML = 'さらに引いて新着をチェック...';
				}
				
				if (!isIOS) {
					var translateY = distance * 0.4;
					thread.style.transform = 'translateY(-' + translateY + 'px)';
				}
			}
		}, { passive: true });
		
		var endPull = function(e) {
			if (!isPulling) return;
			var currentY = e.changedTouches[0].clientY;
			var distance = startY - currentY;
			
			if (!isIOS) {
				thread.style.transition = 'transform 0.3s ease-out';
				thread.style.transform = '';
			}
			
			if (distance > pullThreshold && self.placeholders.next && self.placeholders.next.state === 'check_new') {
				self.placeholders.next.element.innerHTML = '読み込み中...';
				self.loadNew();
			} else if (self.placeholders.next && self.placeholders.next.state === 'check_new') {
				self.placeholders.next.element.innerHTML = originalText;
			}
			isPulling = false;
			startY = 0;
		};
		
		thread.addEventListener('touchend', endPull);
		thread.addEventListener('touchcancel', endPull);
		
		if (prev > 0 && this.prevRange) {
			this.loadRange('prev', this.prevRange, false, false, true);
		} else if (num > 0) {
			this.setupObserverTop();
		}
		
		if (next > 0 && this.nextRange) {
			this.loadRange('next', this.nextRange, false, false, true);
		} else if (num > 0) {
			this.setupObserverBottom();
		}

		if (!this.nextRange) {
			P2DebugLogger.log('readajax', 'init: nextRange is null. Adding check-new placeholder.');
			this.placeholders.next.showEnd();
		} else {
			P2DebugLogger.log('readajax', 'init: nextRange exists: ' + JSON.stringify(this.nextRange));
		}

		this.repositionSentinels();
		var self = this;
		var interval = this.config.readnum_timer;
		this.readnumTimer = setInterval(function() { self.saveReadnum(false); }, interval * 1000);
		window.addEventListener('pagehide', function() {
			clearInterval(self.readnumTimer);
			if (self.observerTop) {
				self.observerTop.disconnect();
				self.observerTop = null;
			}
			if (self.observerBottom) {
				self.observerBottom.disconnect();
				self.observerBottom = null;
			}
			if (self.observerResnum) {
				self.observerResnum.disconnect();
				self.observerResnum = null;
			}
			self.saveReadnum(true);
		}, false);
		window.addEventListener('pageshow', function(e) {
			if (e.persisted) {
				clearInterval(self.readnumTimer);
				self.readnumTimer = setInterval(function() { self.saveReadnum(false); }, interval * 1000);
				if (num > 0) {
					self.setupObserverResnum();
					self.setupObserverTop();
					self.setupObserverBottom();
				}
			}
		}, false);
	},

	// ==========================================
	// イベントエントリー (監視 / 操作の起点)
	// ==========================================
	'onJumpChange': function(select) {
		var val = select.value;
		if (!val) return;

		select.value = '';

		if (val === 'latest') {
			var targetRes = Math.max(this.config.rescount, this.lastResnum);
			if (targetRes <= this.lastResnum) {
				this.scrollToBottom();
				return;
			}
			this.pendingJumpRes = targetRes;
			var jumpStart = Math.max(1, targetRes - this.config.num + 1);
			P2DebugLogger.log('readajax', 'onJumpChange (latest): targetRes=' + targetRes + ', jumpStart=' + jumpStart);

			var firstRes = this.firstResnum;
			var lastRes = this.lastResnum;
			if (firstRes === 0 || lastRes === 0) return;

			var gap = jumpStart - lastRes;
			if (gap > this.config.jumpjdg) {
				this.clearThread();
				var range = {
					'start': jumpStart,
					'to': jumpStart + this.config.num - 1
				};
				this.loadRange('next', range, false, true, false);
			} else {
				this.scrollToBottom();
				var range = {
					'start': lastRes + 1,
					'to': jumpStart + this.config.num - 1
				};
				this.loadRange('next', range, false, true, false);
				this.scrollToBottom();
			}
			return;
		}

		var jumpStart = parseInt(val, 10);
		if (isNaN(jumpStart)) return;
		P2DebugLogger.log('readajax', 'onJumpChange: val=' + val + ', jumpStart=' + jumpStart + ' (current: ' + this.firstResnum + '-' + this.lastResnum + ')');

		var targetEl = document.getElementById('r' + jumpStart);
		if (targetEl) {
			P2DebugLogger.log('readajax', 'onJumpChange: target exists, scrolling to r' + jumpStart);
			this.pendingJumpRes = null;
			this.scrollToElement(targetEl);
			return;
		}

		var firstRes = this.firstResnum;
		var lastRes = this.lastResnum;

		if (firstRes === 0 || lastRes === 0) {
			P2DebugLogger.log('readajax', 'onJumpChange error: firstResnum or lastResnum is 0. aborting.');
			return;
		}

		this.pendingJumpRes = jumpStart;
		P2DebugLogger.log('readajax', 'onJumpChange: target outside current range, triggering loadRange. direction=' + (jumpStart < firstRes ? 'prev' : 'next'));

		if (jumpStart < firstRes) {
			var gap = firstRes - jumpStart;
			if (gap > this.config.jumpjdg) {
				this.clearThread();
				var range = {
					'start': jumpStart,
					'to': jumpStart + this.config.num - 1
				};
				this.loadRange('next', range, false, true, false);
			} else {
				var range = {
					'start': jumpStart,
					'to': firstRes - 1
				};
				this.scrollToTop();
				this.loadRange('prev', range, false, true, false);
			}
		} else {
			var gap = jumpStart - lastRes;
			if (gap > this.config.jumpjdg) {
				this.clearThread();
				var range = {
					'start': jumpStart,
					'to': jumpStart + this.config.num - 1
				};
				this.loadRange('next', range, false, true, false);
			} else {
				var num = this.config.num;
				var jumpEnd = jumpStart + num - 1;
				var range = {
					'start': lastRes + 1,
					'to': jumpEnd
				};
				this.scrollToBottom();
				this.loadRange('next', range, false, true, false);

				// プレースホルダー挿入されていたときのために再度スクロール
				this.scrollToBottom();
			}
		}
	},

	'loadNew': function() {
		if (this.abortNext) return;
		
		var lastResnum = this.lastResnum || this.config.rescount;

		var nextStart = lastResnum + 1;
		P2DebugLogger.log('readajax', 'loadNew: start=' + nextStart);
		var range = { 'start': nextStart, 'to': nextStart + this.config.num - 1 };
		this.loadRange('next', range, true, true, false);
	},

	'clearThread': function() {
		if (this.abortNext) {
			this.abortNext.abort();
			this.abortNext = null;
		}
		if (this.abortPrev) {
			this.abortPrev.abort();
			this.abortPrev = null;
		}
		if (this.observerTop) {
			this.observerTop.disconnect();
			this.observerTop = null;
		}
		if (this.observerBottom) {
			this.observerBottom.disconnect();
			this.observerBottom = null;
		}
		while (this.threadElement.firstChild) {
			this.threadElement.removeChild(this.threadElement.firstChild);
		}
		this.threadElement.appendChild(this.sentinelTop);
		this.threadElement.appendChild(this.sentinelBottom);
		this.firstResnum = 0;
		this.lastResnum = 0;
		this.newresElements = {};
		this.prevRange = null;
		this.nextRange = null;
		this.placeholders.prev.state = 'init';
		this.placeholders.prev.element = null;
		this.placeholders.next.state = 'init';
		this.placeholders.next.element = null;
		this.maxVisibleResnum = 0;
		this.threadCleared = true;
		P2DebugLogger.log('readajax', 'clearThread: thread cleared');
	},

	'setupObserverTop': function() {
		var self = this;
		var thread = this.threadElement;
		if (!this.observerTop) {
			this.observerTop = new IntersectionObserver(function(entries) {
				entries.forEach(function(entry) {
					if (entry.isIntersecting) {
						self.observerTop.unobserve(entry.target);
						if (self.abortPrev) return;
						if (self.prevRange) {
							self.loadRange('prev', self.prevRange, false, true, false);
						}
					}
				});
			}, {
				'root': thread,
				'threshold': 0
			});
		}
		this.observerTop.observe(this.sentinelTop);
	},

	'setupObserverBottom': function() {
		var self = this;
		var thread = this.threadElement;
		if (!this.observerBottom) {
			this.observerBottom = new IntersectionObserver(function(entries) {
				entries.forEach(function(entry) {
					if (entry.isIntersecting) {
						self.observerBottom.unobserve(entry.target);
						if (self.abortNext) return;
						if (self.nextRange) {
							self.loadRange('next', self.nextRange, false, true, false);
						}
					}
				});
			}, {
				'root': thread,
				'threshold': 0
			});
		}
		this.observerBottom.observe(this.sentinelBottom);
	},

	'setupObserverResnum': function() {
		var self = this;
		if (this.observerResnum) {
			return;
		}
		var thread = this.threadElement;
		this.observerResnum = new IntersectionObserver(function(entries) {
			entries.forEach(function(entry) {
				if (entry.isIntersecting) {
					self.onVisibleResnum(entry.target);
				}
			});
		}, {
			'root': thread,
			'threshold': 0,
			'rootMargin': '2px 0px 2px 0px'
		});
		this.observeResElements(thread);
	},

	'observeResElements': function(container) {
		if (!this.observerResnum) {
			return;
		}
		var resEls = container.getElementsByClassName('res');
		for (var i = 0; i < resEls.length; i++) {
			this.observerResnum.observe(resEls[i]);
		}

		var newresEls = container.getElementsByClassName('newres');
		for (var j = 0; j < newresEls.length; j++) {
			var span = newresEls[j];
			var num = parseInt(span.textContent || span.innerText, 10);
			if (!isNaN(num)) {
				this.newresElements[num] = span;
			}
		}

		// カプセル化されたオブジェクト経由で下部プレースホルダーを取得し、監視に登録
		if (this.placeholders.next && this.placeholders.next.element) {
			this.observerResnum.observe(this.placeholders.next.element);
		}
	},

	'onVisibleResnum': function(elem) {
		var num = 0;
		if (this.placeholders.next && elem === this.placeholders.next.element) {
			num = this.lastResnum + 1;
		} else {
			var numMatch = elem.id.match(/^r(\d+)$/);
			if (numMatch) {
				num = parseInt(numMatch[1], 10);
			}
		}

		if (num > 0) {
			var readNum = (num === 1) ? 1 : (num - 1);
			if (readNum > this.maxVisibleResnum) {
				this.maxVisibleResnum = readNum;
				this.clearReadNewres(readNum);
			}
		}
		this.observerResnum.unobserve(elem);
	},

	// ==========================================
	// コア通信・ロードロジック
	// ==========================================
	'loadRange': async function(direction, range, isNew, exclusive, silent) {
		var self = this;
		var isPrev = (direction === 'prev');
		P2DebugLogger.log('readajax', 'loadRange started. direction=' + direction + ', range=' + JSON.stringify(range) + ', isNew=' + isNew + ', exclusive=' + exclusive + ', silent=' + silent);
		
		var phPrev = this.placeholders.prev;
		var phNext = this.placeholders.next;
		var phCurrent = isPrev ? phPrev : phNext;

		// 逆方向通信のキャンセル（exclusiveがtrueの場合）
		if (exclusive) {
			if (isPrev && this.abortNext) {
				this.abortNext.abort();
				this.abortNext = null;
				phNext.showEnd();
			} else if (!isPrev && this.abortPrev) {
				this.abortPrev.abort();
				this.abortPrev = null;
				phPrev.remove();
			}
		}

		// 同方向の既存通信のキャンセル
		if (isPrev) {
			if (this.abortPrev) {
				this.abortPrev.abort();
				this.abortPrev = null;
			}
		} else {
			if (this.abortNext) {
				this.abortNext.abort();
				this.abortNext = null;
			}
		}

		// プレースホルダー表示文言の制御
		var rangeStrForView = range.start + '-' + (range.to ? range.to : '');
		var loadingText = isNew ? '新着をチェック中...' : 'レス ' + rangeStrForView + ' を読み込み中...';
		if (!silent) {
			phCurrent.showLoading(loadingText);
		}

		var rangeStr = rangeStrForView + 'n';
		var forcedView = this.config.b ? '&b=' + encodeURIComponent(this.config.b) : '';
		var uri = 'read.php?ajax=1&host=' + this.config.host + '&bbs=' + this.config.bbs
			+ '&key=' + this.config.key + '&ls=' + rangeStr + forcedView;

		var ac = new AbortController();
		if (isPrev) {
			this.abortPrev = ac;
		} else {
			this.abortNext = ac;
		}
		var timeoutId = setTimeout(function() { ac.abort(); }, this.config.fetch_timeout * 1000 );

		try {
			var response = await fetch(uri, {
				signal: ac.signal,
				credentials: 'include'
			});
			clearTimeout(timeoutId);

			if (!response.ok) throw new Error('HTTP ' + response.status);

			var html = await response.text();

			if (isPrev) {
				self.abortPrev = null;
			} else {
				self.abortNext = null;
			}
			self.onLoaded(html, direction, range, isNew);

		} catch (err) {
			clearTimeout(timeoutId);

			if ((isPrev && self.abortPrev !== ac) || (!isPrev && self.abortNext !== ac)) {
				return;
			}

			if (isPrev) {
				self.abortPrev = null;
			} else {
				self.abortNext = null;
			}

			if (err.name === 'AbortError') {
				self.pendingJumpRes = null;
				P2DebugLogger.log('readajax', 'loadRange timeout. direction=' + direction + ', range=' + rangeStr);
			} else {
				P2DebugLogger.log('readajax', 'loadRange error. direction=' + direction + ', range=' + rangeStr);
			}

			phCurrent.showError(function() {
				self.loadRange(direction, range, isNew, exclusive, false);
			});
		}
	},

	'onLoaded': function(html, direction, range, isNew) {
		var isPrev = (direction === 'prev');
		var phCurrent = isPrev ? this.placeholders.prev : this.placeholders.next;
		var thread = this.threadElement;
		
		if (!html) {
			P2DebugLogger.log('readajax', 'onLoaded: empty html. direction=' + direction + ', isNew=' + isNew);
			phCurrent.showEnd();
			this.pendingJumpRes = null;
			return;
		}

		var tempDiv = document.createElement('div');
		P2DebugLogger.log('readajax', 'onLoaded payload size=' + html.length + ', direction=' + direction + ', isNew=' + isNew);
		tempDiv.innerHTML = html;
		var resEls = tempDiv.getElementsByClassName('res');
		
		if (resEls.length === 0) {
			if (isNew) {
				var self = this;
				phCurrent.showNotice('新着はありません（タップして再チェック）', function() {
					self.loadNew();
				});
			} else {
				phCurrent.showEnd();
			}
			if (this.pendingJumpRes) {
				P2DebugLogger.log('readajax', 'onLoaded: jump failed (no res found). pendingJumpRes=' + this.pendingJumpRes);
			}
			this.pendingJumpRes = null;
			return;
		}

		if (!isPrev) {
			var latestResnum = 0;
			for (var i = resEls.length - 1; i >= 0; i--) {
				var match = resEls[i].id.match(/^r(\d+)$/);
				if (match) {
					latestResnum = parseInt(match[1], 10);
					break;
				}
			}
			if (latestResnum > 0) {
				this.lastResnum = latestResnum;
				this.updateJumpSelect();
			}
			if (this.threadCleared) {
				var earliestResnum = 0;
				for (var i = 0; i < resEls.length; i++) {
					var match = resEls[i].id.match(/^r(\d+)$/);
					if (match) {
						earliestResnum = parseInt(match[1], 10);
						break;
					}
				}
				if (earliestResnum > 0) {
					this.firstResnum = earliestResnum;
				}
			}
		} else {
			var earliestResnum = 0;
			for (var i = 0; i < resEls.length; i++) {
				var match = resEls[i].id.match(/^r(\d+)$/);
				if (match) {
					earliestResnum = parseInt(match[1], 10);
					break;
				}
			}
			if (earliestResnum > 0) {
				this.firstResnum = earliestResnum;
			}
		}

		phCurrent.remove();
		this.observeResElements(tempDiv);
		P2DebugLogger.log('readajax', 'onLoaded success. direction=' + direction + ', count=' + resEls.length + ', lastResnum=' + this.lastResnum);
		var loadedCount = resEls.length;

		var insertedNodes = [];
		if (isPrev) {
			var oldScrollHeight = thread.scrollHeight;
			var firstChild = thread.firstChild;
			var child = tempDiv.firstChild;
			while (child) {
				var nextChild = child.nextSibling;
				thread.insertBefore(child, firstChild);
				insertedNodes.push(child);
				child = nextChild;
			}
			var newScrollHeight = thread.scrollHeight;
			var insertedHeight = newScrollHeight - oldScrollHeight;
			thread.scrollTop += insertedHeight;
		} else {
			var child = tempDiv.firstChild;
			while (child) {
				var nextChild = child.nextSibling;
				thread.appendChild(child);
				insertedNodes.push(child);
				child = nextChild;
			}
		}

		if (window.limelight && typeof window.limelight.bind === 'function') {
			for (var i = 0; i < insertedNodes.length; i++) {
				window.limelight.bind(null, insertedNodes[i], false);
			}
		}

		if (isPrev) {
			this.updatePrevRangeAfterLoad(range);
		} else {
			this.updateNextRangeAfterLoad(range, isNew, loadedCount);
		}

		this.repositionSentinels();
		if (isPrev) {
			this.setupObserverTop();
		} else {
			this.setupObserverBottom();
		}

		if (this.threadCleared) {
			this.threadCleared = false;
			this.updatePrevRangeAfterLoad(range);
			this.setupObserverTop();
		}

		if (this.pendingJumpRes) {
			var targetEl = document.getElementById('r' + this.pendingJumpRes);
			if (targetEl) {
				this.scrollToElement(targetEl);
			}
			this.pendingJumpRes = null;
		}
	},

	// ==========================================
	// UI・スクロール補助ユーティリティ
	// ==========================================
	'scrollToTop': function() {
		P2DebugLogger.log('readajax', 'scrollToTop');
		this.threadElement.scrollTop = 0;
	},

	'scrollToBottom': function() {
		P2DebugLogger.log('readajax', 'scrollToBottom');
		this.threadElement.scrollTop = this.threadElement.scrollHeight;
	},

	'scrollToElement': function(elem) {
		var thread = this.threadElement;
		var offset = elem.offsetTop;
		var parent = elem.offsetParent;
		while (parent && parent !== thread) {
			offset += parent.offsetTop;
			parent = parent.offsetParent;
		}
		P2DebugLogger.log('readajax', 'scrollToElement: id=' + elem.id + ', offset=' + offset);
		thread.scrollTop = Math.max(0, offset);
	},

	'repositionSentinels': function() {
		var thread = this.threadElement;
		var timing = this.config ? this.config.timing : 0;
		var sentinelTop = this.sentinelTop;
		var sentinelBottom = this.sentinelBottom;
		var resEls = thread.getElementsByClassName('res');

		var targetTop = thread.firstChild;
		// まだ上に読み込む余地がある場合のみ、timing件手前に配置する
		if (this.prevRange && timing > 0 && resEls.length > timing) {
			targetTop = resEls[timing];
		}
		if (sentinelTop !== targetTop && (sentinelTop.parentNode !== thread || sentinelTop.nextSibling !== targetTop)) {
			thread.insertBefore(sentinelTop, targetTop);
		}

		var targetBottom = null;
		// まだ下に読み込む余地がある場合のみ、timing件手前に配置する
		if (this.nextRange && timing > 0 && resEls.length > timing) {
			targetBottom = resEls[resEls.length - timing];
		}
		if (sentinelBottom !== targetBottom && (sentinelBottom.parentNode !== thread || sentinelBottom.nextSibling !== targetBottom)) {
			if (targetBottom) {
				thread.insertBefore(sentinelBottom, targetBottom);
			} else {
				thread.appendChild(sentinelBottom);
			}
		}
	},

	// ==========================================
	// 既読管理・同期
	// ==========================================
	'clearReadNewres': function(readNum) {
		if (this.config.realtimedisp_readnum == 0) {
			return;
		}
		for (var num in this.newresElements) {
			var n = parseInt(num, 10);
			if (n <= readNum) {
				this.newresElements[num].classList.remove('newres');
				delete this.newresElements[num];
			}
		}
	},

	'saveReadnum': async function(isUrgent) {
		if (this.maxVisibleResnum <= this.lastSavedReadnum) {
			if (isUrgent) {
				P2DebugLogger.log('readajax', 'saveReadnum skipped (no update): maxVisible=' + this.maxVisibleResnum + ', lastSaved=' + this.lastSavedReadnum);
			}
			return;
		}
		
		var targetResnum = this.maxVisibleResnum;
		
		if (isUrgent) {
			this.lastSavedReadnum = targetResnum;
			var url = 'httpcmd.php';
			var formData = new FormData();
			formData.append('cmd', 'setreadnum');
			formData.append('host', this.config.host);
			formData.append('bbs', this.config.bbs);
			formData.append('key', this.config.key);
			formData.append('setreadnum', targetResnum);
			navigator.sendBeacon(url, formData);
			P2DebugLogger.log('readajax', 'saveReadnum (urgent sendBeacon) readnum=' + targetResnum);
		} else {
			var url = 'httpcmd.php?cmd=setreadnum&host=' + this.config.host
				+ '&bbs=' + this.config.bbs + '&key=' + this.config.key
				+ '&setreadnum=' + targetResnum;
			P2DebugLogger.log('readajax', 'saveReadnum (async HTTP GET) readnum=' + targetResnum);
			try {
				var resp = await fetch(url, { credentials: 'include' });
				if (resp.ok && targetResnum > this.lastSavedReadnum) {
					this.lastSavedReadnum = targetResnum;
					P2DebugLogger.log('readajax', 'saveReadnum completed. readnum=' + targetResnum);
				}
			} catch (err) {
				P2DebugLogger.log('readajax', 'saveReadnum error. readnum=' + targetResnum + ', err=' + err.message);
			}
		}
	},

	// ==========================================
	// 内部状態更新 (補助ロジック)
	// ==========================================
	'updatePrevRangeAfterLoad': function(range) {
		if (!range) return;
		var num = this.config.num;
		if (range.start > 1 && num > 0) {
			this.prevRange = {
				'start': Math.max(1, range.start - num),
				'to': range.start - 1
			};
		} else {
			this.prevRange = null;
		}
	},

	'updateNextRangeAfterLoad': function(range, isNew, loadedCount) {
		var num = this.config.num;

		if (isNew) {
			this.nextRange = null;
		} else {
			var requestedCount = range ? (range.to - range.start + 1) : 0;
			if (range && range.to && loadedCount === requestedCount && num > 0) {
				if (!range.to || !this.config.rescount || range.to < this.config.rescount) {
					this.nextRange = {
						'start': range.to + 1,
						'to': range.to + num
					};
				} else {
					this.nextRange = null;
				}
			} else {
				this.nextRange = null;
			}
		}

		if (!this.nextRange) {
			this.placeholders.next.showEnd();
		}
	},

	'updateJumpSelect': function() {
		var selectEl = document.getElementById('read-jump-select');
		if (!selectEl) {
			return;
		}
		var rpp = this.config.rpp;
		var rescount = Math.max(this.lastResnum, this.config.rescount);

		var selectedValue = selectEl.value;
		var isSelectedPlaceholder = (!selectedValue) ? ' selected' : '';
		var html = '<option value=""' + isSelectedPlaceholder + '>移動...</option>';

		var pages = Math.ceil(rescount / rpp);
		for (var i = 0; i < pages; i++) {
			var k = i * rpp + 1;
			var n = k.toString();
			var isSelected = (selectedValue === n) ? ' selected' : '';
			html += '<option value="' + n + '"' + isSelected + '>' + n + '</option>';
		}
		var isSelectedLatest = (selectedValue === 'latest') ? ' selected' : '';
		html += '<option value="latest"' + isSelectedLatest + '>最新</option>';

		selectEl.innerHTML = html;
	}
};

// }}}
// {{{ iutil.ReadAjaxPlaceholder

iutil.ReadAjaxPlaceholder = function(type, readajax) {
	this.type = type;
	this.id = 'preload-placeholder-' + type;
	this.element = null;
	this.readajax = readajax;
	this.state = 'init';
};

iutil.ReadAjaxPlaceholder.prototype._ensureElement = function() {
	var thread = this.readajax.threadElement;

	if (!this.element) {
		this.element = document.getElementById(this.id);
	}
	if (!this.element) {
		this.element = document.createElement('div');
		this.element.id = this.id;
		this.element.className = 'res loading-placeholder';
		this.element.style.textAlign = 'center';
		this.element.style.padding = '15px';
		
		if (this.type === 'prev') {
			thread.insertBefore(this.element, thread.firstChild);
		} else {
			thread.appendChild(this.element);
		}

		// 要素が新規生成され、かつ監視オブザーバーが稼働していれば監視に追加
		if (this.readajax.observerResnum) {
			this.readajax.observerResnum.observe(this.element);
		}
	}
	return true;
};

iutil.ReadAjaxPlaceholder.prototype.showLoading = function(text) {
	if (!this._ensureElement()) return;
	this.state = 'loading';
	this.element.innerHTML = text;
	this.element.onclick = null;
};

iutil.ReadAjaxPlaceholder.prototype.showError = function(callback) {
	if (!this._ensureElement()) return;
	this.state = 'error';
	this.element.innerHTML = '読み込みに失敗。タップして再試行';
	var self = this;
	this.element.onclick = function() {
		self.element.onclick = null;
		if (callback) callback();
	};
};

iutil.ReadAjaxPlaceholder.prototype.showNotice = function(text, callback) {
	if (!this._ensureElement()) return;
	this.state = 'notice';
	this.element.innerHTML = text;
	var self = this;
	this.element.onclick = function() {
		self.element.onclick = null;
		if (callback) callback();
	};
};

iutil.ReadAjaxPlaceholder.prototype.showEnd = function() {
	if (!this._ensureElement()) return;
	
	var isNext = (this.type === 'next');
	var isDatochiok = this.readajax.config && this.readajax.config.datochiok;

	if (isNext && !isDatochiok) {
		this.state = 'check_new';
		this.element.innerHTML = 'タップして新着をチェック';
		var self = this;
		this.element.onclick = function() {
			self.readajax.loadNew();
		};
	} else {
		this.state = 'end';
		this.element.innerHTML = 'これ以上のレスはありません';
		this.element.onclick = null;
	}
};

iutil.ReadAjaxPlaceholder.prototype.remove = function() {
	this.state = 'removed';
	if (this.element && this.element.parentNode) {
		this.element.parentNode.removeChild(this.element);
	} else {
		var el = document.getElementById(this.id);
		if (el && el.parentNode) {
			el.parentNode.removeChild(el);
		}
	}
	this.element = null;
};

// }}}

document.addEventListener('DOMContentLoaded', function(event) {
	iutil.readajax.init();
}, false);

/*
 * Local Variables:
 * mode: javascript
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: t
 * End:
 */
/* vim: set syn=javascript fenc=cp932 ai noet ts=4 sw=4 sts=4 fdm=marker: */
