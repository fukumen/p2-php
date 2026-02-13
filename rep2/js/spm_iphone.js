/*
 * rep2expack - ポップアップメニュー for iPhone
 */

// {{{ GLOBALS

var SPM = {
	'activeThread': null,
	'activeNumber': null,
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
	var uri = 'spm_k.php?ktool_name=res';
	if (quote) {
		uri += '_quote';
	}
	if (location.href.indexOf('/read_new_k.php?') != -1) {
		uri += '&from_read_new=1';
	}
	uri += '&ktool_value=' + SPM.activeNumber + SPM.activeThread.query;

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
	var action = document.getElementById('spm-select-action');
	var target = document.getElementById('spm-select-target');
	var uri = 'spm_k.php?ktool_name=';

	switch (action.options[action.selectedIndex].value) {
	  case 'aborn':
	  case 'ng':
		uri += action.options[action.selectedIndex].value + '_';
		break;
	  default:
		alert('SPM: Invalid Action!');
		return;
	}

	switch (target.options[target.selectedIndex].value) {
	  case 'name':
	  case 'mail':
	  case 'id':
	  case 'msg':
	  case 'be':
		uri += target.options[target.selectedIndex].value;
		break;
	  default:
		alert('SPM: Invalid Target!');
		return;
	}

	uri += '&ktool_value=' + SPM.activeNumber + SPM.activeThread.query;

	window.open(uri);
});

/*
 * 指定されたSPMを開く
 *
 * @return void
 */
SPM.open = (function(action)
{
	var uri = 'spm_k.php?ktool_name=' + action + '&ktool_value=' + SPM.activeNumber + SPM.activeThread.query;
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
		var url = 'https://donguri.5ch.net/confirm?url=' + encodeURIComponent(SPM.activeThread.url) + '&date=' + encodeURIComponent(SPM.activeDate);
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
