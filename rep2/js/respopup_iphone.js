/**
 * rep2expack - iPhone用レスポップアップ
 *
 * iphone.jsの後に読み込む
 * jQuery 必須になりました by 2ch774
 */

// {{{ globals

var _IRESPOPG = {
	'hash': {},
	'serial': 0,
	'callbacks': []
};

var ipoputil = {};

// }}}
// {{{ ipoputil.getZ()

/**
 * z-indexに設定する値を返す
 *
 * css/ic2_iphone.css で div#ic2-info の z-index が 999 で
 * 固定されているのでポップアップを繰り返すと不具合がある。
 * ポップアップオブジェクトの z-index を集中管理する必要あり。
 *
 * @param {Element} obj
 * @return {String}
 */
ipoputil.getZ = function(obj) {
	return (200 + _IRESPOPG.serial).toString();
};

// }}}
// {{{ ipoputil.getActivator()

/**
 * オブジェクトを最前面に移動する関数を返す
 *
 * @param {Element} obj
 * @return void
 */
ipoputil.getActivator = function(obj) {
	return (function(){
		_IRESPOPG.serial++;
		obj.style.zIndex = ipoputil.getZ();
	});
};

// }}}
// {{{ ipoputil.getDeactivator()

/**
 * DOMツリーからオブジェクトを取り除く関数を返す
 *
 * @param {Element} obj
 * @param {String} key
 * @return void
 */
ipoputil.getDeactivator = function(obj, key) {
	if(obj!=null &&obj.parentNode !=null) {
		$(document).off('click.'+obj.id);
		delete _IRESPOPG.hash[key];
		obj.parentNode.removeChild(obj);
		delete obj;
	}
};

// }}}
// {{{ ipoputil.callback()

/**
 * iPhone用レスポップアップのコールバックメソッド
 *
 * @param {XMLHttpRequest} req
 * @param {String} url
 * @param {String} popid
 * @param {Number} yOffset
 * @return void
 * @todo use asynchronous request
 */
ipoputil.callback = function(req, url, popid, clientY, pageY) {
	var container = document.createElement('div');
	var closer = document.createElement('img');
	var is_readajax = typeof iutil_preload_config !== 'undefined';

	container.id = popid;
	container.className = 'respop';
	container.innerHTML = req.responseText;
	container.style.zIndex = ipoputil.getZ();

	closer.className = 'close-button';
	closer.setAttribute('src', 'img/iphone/close.png');

	$(closer).on('click', function(event) {
		ipoputil.getDeactivator(container, url);
	});

	if (is_readajax) {
		var thread = document.getElementById('thread');
		var yOffset = Math.max(10, (clientY - thread.getBoundingClientRect().top) + thread.scrollTop - 20);
		container.style.top = yOffset + 'px';
		container.appendChild(closer);
		thread.appendChild(container);
		var bottom = yOffset + container.offsetHeight;
		var limit = thread.scrollTop + thread.clientHeight;
		if (bottom > limit - 10) {
			container.style.top = Math.max(thread.scrollTop + 10, limit - container.offsetHeight - 10) + 'px';
		}
	} else {
		var yOffset = Math.max(10, pageY - 20);
		container.style.top = yOffset + 'px';
		container.appendChild(closer);
		document.body.appendChild(container);
		var scrollY = window.scrollY;
		var bottom = yOffset + container.offsetHeight;
		var limit = scrollY + window.innerHeight;
		if (bottom > limit - 10) {
			container.style.top = Math.max(10, limit - container.offsetHeight - 10) + 'px';
		}
	}

	var scrollToRes = parseInt(container.style.top, 10);

	//iutil.modifyInternalLink(container);
	iutil.modifyExternalLink(container);

	_IRESPOPG.hash[url] = container;

	var lastres = document.evaluate('./div[@class="res" and position() = last()]',
									container,
									null,
									XPathResult.ANY_UNORDERED_NODE_TYPE,
									null
									).singleNodeValue;

	if (lastres) {
		var back = document.createElement('div');
		back.className = 'respop-back';
		var anchor = document.createElement('a');
		anchor.setAttribute('href', '#' + popid);
		anchor.onclick = function(evt){
			iutil.stopEvent(evt || window.event);
			if (is_readajax) {
				document.getElementById('thread').scrollTop = Math.max(0, scrollToRes - 10);
			} else {
				scrollTo(0, scrollToRes - 10);
			}
			return false;
		};
		anchor.appendChild(document.createTextNode('▲'));
		back.appendChild(anchor);
		lastres.appendChild(back);
	}

	var i;
	for (i = 0; i < _IRESPOPG.callbacks.length; i++) {
		_IRESPOPG.callbacks[i](container);
	}

	//ウインドウ全体をクリックしたとき
	$(document).on('click.'+popid, function(event) {
		// レスポップアップと、SPMをクリックしたときは、閉じない
		if (!$(event.target).closest('.respop,#spm-header,#ic2-info-body,.input-group,#spm').length) {
			ipoputil.getDeactivator(container, url);
		}
	});
};

// }}}
// {{{ ipoputil.popup()

/**
 * iPhone用レスポップアップ
 *
 * @param {String} url
 * @param {Event} evt
 * @return void
 */
ipoputil.popup = function(url, evt) {
	var pos = iutil.getTouch(evt) || evt;
	var clientY = pos.clientY;
	var pageY   = pos.pageY;

	if (_IRESPOPG.hash[url]) {
		_IRESPOPG.serial++;
		if (typeof iutil_preload_config !== 'undefined') {
			var thread = document.getElementById('thread');
			var yOffset = Math.max(10, (clientY - thread.getBoundingClientRect().top) + thread.scrollTop - 20);
			_IRESPOPG.hash[url].style.top = yOffset + 'px';
			var bottom = yOffset + _IRESPOPG.hash[url].offsetHeight;
			var limit = thread.scrollTop + thread.clientHeight;
			if (bottom > limit - 10) {
				_IRESPOPG.hash[url].style.top = Math.max(thread.scrollTop + 10, limit - _IRESPOPG.hash[url].offsetHeight - 10) + 'px';
			}
		} else {
			var yOffset = Math.max(10, pageY - 20);
			_IRESPOPG.hash[url].style.top = yOffset + 'px';
			var scrollY = window.scrollY;
			var bottom = yOffset + _IRESPOPG.hash[url].offsetHeight;
			var limit = scrollY + window.innerHeight;
			if (bottom > limit - 10) {
				_IRESPOPG.hash[url].style.top = Math.max(10, limit - _IRESPOPG.hash[url].offsetHeight - 10) + 'px';
			}
		}
		_IRESPOPG.hash[url].style.zIndex = ipoputil.getZ();
		return false;
	}

	_IRESPOPG.serial++;
	var popnum = _IRESPOPG.serial;
	var popid = '_respop' + popnum;
	var req = new XMLHttpRequest();
	req.open('GET', url + '&ajax=1&respop_id=' + popnum, true);
	req.withCredentials = true;
	req.onreadystatechange = function() {
		if (this.readyState == 4) {
			if (this.status == 200) {
				ipoputil.callback(this, url, popid, clientY, pageY);
			}
		}
	};
	req.send(null);
};

// }}}
// {{{ iResPopUp()

/**
 * iPhone用レスポップアップ
 *
 * @param {String} url
 * @param {Event} evt
 * @return false
 * @see iutil.popup
 */
var iResPopUp = function(url, evt) {
	evt = evt || window.event;
	iutil.stopEvent(evt);
	if (typeof url !== 'string' && typeof url.href === 'string') {
		url = url.href;
	}
	ipoputil.popup(url, evt);
	return false;
};

// }}}

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
