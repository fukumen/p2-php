/*
 * rep2expack - ポップアップメニュー for iPhone
 */

// {{{ GLOBALS

var SPM = {
	'activeThread': null,
	'activeNumber': null,
	'activeId': null,
	'activeDate': null
};
var spmReloadRes = null;

// }}}
// {{{ SPM.show()

/*
 * SPMを表示する
 *
 * @param Object thread
 * @param Number no
 * @param String id
 * @param MouseEvent evt
 * @param String date
 * @return void
 */
SPM.show = (function(thread, no, id, evt, date, hasWatchoi)
{
	// skOuterClickが発生しないようにする
	evt.stopPropagation();

	var spm = document.getElementById('spm');
	if (!spm) {
		return;
	}

	SPM.activeThread = thread;
	SPM.activeNumber = no;
	SPM.activeId = id;
	SPM.activeDate = date;

	var num = document.getElementById('spm-num');
	if (num) {
		if (num.childNodes.length === 0) {
			num.appendChild(document.createTextNode(no));
		} else if (num.childNodes.length === 1 && num.firstChild.nodeType === 3) {
			num.firstChild.nodeValue = no;
		} else {
			while (num.childNodes.length) {
				num.removeChild(num.childNodes[num.childNodes.length - 1]);
			}
			num.appendChild(document.createTextNode(no));
		}
	}

	// ワッチョイの表示/非表示を切り替え
	var selectTarget = document.getElementById('spm-select-target');
	if (selectTarget) {
		for (var i = 0; i < selectTarget.options.length; i++) {
			if (selectTarget.options[i].value == 'watchoi') {
				selectTarget.options[i].disabled = !hasWatchoi;
				selectTarget.options[i].style.display = hasWatchoi ? 'block' : 'none';
			}
		}
	}

	//spm.style.display = 'block';
	spm.style.top = (iutil.getPageY(evt) + 10) + 'px';
	$(spm).show();
	$(spm).skOuterClick(function(event){
		if (!$(spm).is(':hidden')) {
			SPM.hide(event);
		}
	});

	//document.body.addEventListener('touchmove', this.hide, true);
});

// }}}
// {{{ SPM.show()

/*
 * SPMを非表示にする
 *
 * @param MouseEvent evt
 * @return void
 */
SPM.hide = (function(evt)
{
	//document.body.removeEventListener('touchmove', this.hide, true);

	$('#spm').hide();

});

// }}}
// {{{ SPM.replyTo()

/*
 * レスする
 *
 * @param Boolean quote
 * @return void
 */
SPM.replyTo = (function(quote)
{
	var uri = 'post_form.php?resnum=' + SPM.activeNumber;
	uri += '&inyou=' + (quote ? '1' : '-1');
	uri += '&popup=1';
	if (location.href.indexOf('/read_new_k.php?') != -1) {
		uri += '&from_read_new=1';
	}
	uri += SPM.activeThread.query;

	window.open(uri);
});

// }}}
// {{{ SPM.doAction()

/*
 * あぼーん・NGワード・検索
 *
 * @return void
 */
SPM.doAction = (function()
{
	var action = document.getElementById('spm-select-action').value;
	var target = document.getElementById('spm-select-target').value;
	var uri;

	switch (action) {
		case 'aborn':
		case 'ng':
			uri = 'info_sp.php?mode=' + action + '_';
			break;
		default:
			alert('SPM: Invalid Action!');
			return;
	}

	switch (target) {
		case 'name':
		case 'mail':
		case 'id':
		case 'msg':
		case 'be':
		case 'watchoi':
			uri += target;
			break;
		default:
			alert('SPM: Invalid Target!');
			return;
	}

	uri += '&resnum=' + SPM.activeNumber + '&popup=' + SPM.activeThread.ngaborn_popup + SPM.activeThread.query;

	SPM.showDialog(uri);
});

/**
 * ダイアログ（オーバーレイ）を表示する
 */
SPM.showDialog = function (url) {
	var overlay = document.getElementById('spm-overlay');
	if (!overlay) {
		overlay = document.createElement('div');
		overlay.id = 'spm-overlay';
		overlay.innerHTML = '<div id="spm-modal"><iframe id="spm-iframe"></iframe></div>';
		overlay.onclick = function(e) {
			if (e.target === overlay) SPM.hideDialog();
		};
		document.body.appendChild(overlay);
	}
	var iframe = document.getElementById('spm-iframe');
	iframe.onload = function() {
		try {
			// iframeの高さがscrollHeightに干渉しないよう、一旦最小化して中身の自然な高さを測る
			iframe.style.height = '0px';
			var doc = iframe.contentWindow.document;
			var h = Math.max(doc.documentElement.scrollHeight, doc.body.scrollHeight);

			if (h > 0) {
				var maxH = Math.floor(window.innerHeight * 0.9);
				if (h > maxH) h = maxH;
				iframe.style.height = h + 'px';
			} else {
				iframe.style.height = '300px';
			}
		} catch (e) {
			iframe.style.height = '300px';
		}
	};
	iframe.src = url;
	overlay.style.display = 'flex';
};

/**
 * ダイアログを閉じる
 */
SPM.hideDialog = function () {
	var overlay = document.getElementById('spm-overlay');
	if (overlay) {
		overlay.style.display = 'none';
		document.getElementById('spm-iframe').src = 'about:blank';
		if (spmReloadRes) {
			var resnum = spmReloadRes;
			spmReloadRes = null;
			SPM.reload(resnum);
		}
	}
};

/**
 * 親画面をリロードする（アンカー指定付き）
 */
SPM.reload = function (resnum) {
	var url = location.href.split('#')[0];
	if (resnum) {
		url += '#r' + resnum;
	}
	location.replace(url);
	location.reload();
};

/*
 * 指定されたSPMを開く
 *
 * @return void
 */
SPM.open = (function(action)
{
	var uri;
	switch (action) {
		case 'goto':
			var to = parseInt(SPM.activeNumber) + parseInt(SPM.activeThread.rnum_range);
			uri = 'read.php?ls=' + SPM.activeNumber + '-' + (isNaN(to) ? '' : to);
			break;
		case 'rref':
			uri = 'read.php?' + SPM.activeThread.rref_params + '&rf[word]=' + SPM.activeNumber;
			break;
		case 'copy':
			uri = 'read_copy_k.php?copy=' + SPM.activeNumber;
			break;
		case 'copy_quote':
			uri = 'read_copy_k.php?copy=' + SPM.activeNumber + '&inyou=1';
			break;
		case 'am':
			activeMona(SPM.activeId);
			SPM.hide();
			return;
		case 'aas':
			uri = 'aas.php?resnum=' + SPM.activeNumber;
			break;
		case 'aas_rotate':
			uri = 'aas.php?resnum=' + SPM.activeNumber + '&rotate=1';
			break;
		default:
			alert('SPM: Invalid Action!');
			return;
	}
	uri += SPM.activeThread.query;
	window.open(uri);
});

// }}}
// {{{ SPM.show()

/*
 * どんぐり大砲
 *
 * @return void
 */
SPM.donguri = (function()
{
	if (window.confirm('どんぐり大砲を撃て！\nURL: ' + SPM.activeThread.url + '\nDate: ' + SPM.activeDate)) {
		var url = 'dongurictl.php?mode=confirm&url=' + encodeURIComponent(SPM.activeThread.url) + '&date=' + encodeURIComponent(SPM.activeDate);
		window.open(url, '_blank');
	}
});

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
