/*
 * rep2expack - NGあぼーん操作
 */

// {{{ show_ng_message()

/*
 * NGメッセージを表示する
 *
 * @param String id
 * @return void
 */
function show_ng_message(id)
{
	var target = document.getElementById(id);
	if (target.style.display === 'none' || target.style.display === '') {
		target.style.display = 'block';
	} else {
		target.style.display = 'none';
	}
}

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
