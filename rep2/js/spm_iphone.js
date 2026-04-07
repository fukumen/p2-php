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
SPM.show = (function(thread, no, id, evt, date)
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
			uri += target;
			break;
		default:
			alert('SPM: Invalid Target!');
			return;
	}

	uri += '&resnum=' + SPM.activeNumber + '&popup=1' + SPM.activeThread.query;

	window.open(uri);
});

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
