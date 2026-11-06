// textareaの高さをライブ調節する
function adjustTextareaRows(obj, org, plus) {
	var brlen = null;
	if (obj.wrap) {
		if (obj.wrap == 'virtual' || obj.wrap == 'soft') {
			brlen = obj.cols;
		}
	}
	var aLen = countLines(obj.value, brlen);
	var aRows = aLen + plus;
	var move = 0;
	var scroll = 14;
	if (org) {
		if (Math.max(aRows, obj.rows) > org) {
			move = Math.abs((aRows - obj.rows) * scroll);
			if (move) {
				obj.rows = Math.max(org, aRows);
				window.scrollBy(0, move);
			}
		}
		/*
		if (aRows > org + plus) {
			if (obj.rows < aRows) {
				move = (aRows - obj.rows) * scroll;
			} else if (obj.rows > aRows) {
				move = (aRows - obj.rows) * -scroll;
			}
			if (move != 0) {
				if (move < 0) {
					window.scrollBy(0, move);
				}
				obj.rows = aRows;
				if (move > 0) {
					window.scrollBy(0, move);
				}
			}
		}
		*/
	} else if (obj.rows < aRows) {
		move = (aRows - obj.rows) * scroll;
		obj.rows = aRows;
		window.scrollBy(0, move);
	}
}

/**
 * \n を改行として行数を数える
 *
 * @param integer brlen 改行する文字数。無指定なら文字数で改行しない
 */
function countLines(str, brlen) {
	var lines = str.split("\n");
	var count = lines.length;
	var aLen = 0;
	for (var i = 0; i < lines.length; i++) {
		aLen = jstrlen(lines[i]);
		if (brlen) {
			var adjust =  1.15; // 単語単位の折り返しに対応していないのでアバウト調整
			if ((aLen * adjust) > brlen) {
				count = count + Math.floor((aLen * adjust) / brlen);
			}
		}
	}
	return count;
}

// 文字列をバイト数で数える
function jstrlen(str) {
	var len = 0;
	str = escape(str);
	for (var i = 0; i < str.length; i++, len++) {
		if (str.charAt(i) == "%") {
			if (str.charAt(++i) == "u") {
				i += 3;
				len++;
			}
			i++;
		}
	}
	return len;
}

// (対象がdisableでなければ) フォーカスを合わせる
function setFocus(ID){
	var obj;
	if (obj = document.getElementById(ID)) {
		if (obj.disabled != true) {
			obj.focus();
		}
	}
}

// sageチェックに合わせて、メール欄の内容を書き換える
function mailSage(){
	var mailran, cbsage;
	if (cbsage = document.getElementById('sage')) {
		if (mailran = document.getElementById('mail')) {
			if (cbsage.checked == true) {
				mailran.value = "sage";
			} else {
				if (mailran.value == "sage") {
					mailran.value = "";
				}
			}
		}
	}
}

// メール欄の内容に応じて、sageチェックをON OFFする
function checkSage(){
	var mailran, cbsage;
	if (mailran = document.getElementById('mail')) {
		if (cbsage = document.getElementById('sage')) {
			if (mailran.value == "sage") {
				cbsage.checked = true;
			} else {
				cbsage.checked = false;
			}
		}
	}
}

// 書き込みボタンの有効・無効を切り替える
function switchBlockSubmit(onoff) {
	var kakiko_submit = document.getElementById('kakiko_submit');
	if (kakiko_submit) {
		kakiko_submit.disabled = onoff;
	}
	var submit_beres = document.getElementById('submit_beres');
	if (submit_beres) {
		submit_beres.disabled = onoff;
	}
}

// 書き込み内容を検証する
function validateAll(doValidateMsg, doValidateSage) {
	var block_submit = document.getElementById('block_submit');
	if (block_submit && block_submit.checked) {
		alert('書き込みブロック中');
		return false;
	}
	if (doValidateMsg && !validateMsg()) {
		return false;
	}
	if (doValidateSage && !validateSage()) {
		return false;
	}
	return true;
}

// 本文が空でないか検証する
function validateMsg() {
	if (document.getElementById('MESSAGE').value.length == 0) {
		alert('本文がありません。');
		return false;
	}
	return true;
}

// sageているか検証する
function validateSage() {
	if (document.getElementById('mail').value.indexOf('sage') == -1) {
		if (window.confirm('sageてませんよ？')) {
			return true;
		} else {
			return false;
		}
	}
	return true;
}

// 引数にtrueを指定したら名無しで書込するかどうか確認する
function confirmNanashi(doconfirmNanashi) {
	// 引数がtrueで名前欄が0文字(名無し)ならば
	if (doconfirmNanashi && document.getElementById('FROM').value.length == 0) {
		if(window.confirm('この板は名無しに fusianasan が含まれています。\n名無しで書き込みますか？')) {
			return true;
		} else {
			return false;
		}
	}
	return true;
}

//引数にtrueを指定したら名無しで書込出来ない
function blockNanashi(blockNanashi) {
	// 引数がtrueで名前欄が0文字(名無し)ならば
	if (blockNanashi && document.getElementById('FROM').value.length == 0) {
		alert('この板は名無しの書き込みが制限されているので名無しで書き込むことが出来ません。');
			return false;
	}
	return true;
}