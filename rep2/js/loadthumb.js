/*
 * ImageCache2::Load Thumbnail
 */

// {{{ loadThumb()

/**
 * 非表示状態のサムネイルを読み込む
 * 
 * 読み込み判定には置換対象オブジェクトの有無を利用。
 * 返り値は画像が読み込み済みか否か。
 *
 * @param String thumb_url
 * @param String thumb_id
 * @return void
 */
function loadThumb(thumb_url, thumb_id)
{
	var tmp_thumb = document.getElementById(thumb_id);
	if (!tmp_thumb) {
		return true;
	}
	if (tmp_thumb.src.indexOf('/ic_load') === -1) {
		return true;
	}

	var thumb = document.createElement('img');
	thumb.className = 'thumbnail';
	thumb.setAttribute('src', thumb_url);
	thumb.setAttribute('hspace', 4);
	thumb.setAttribute('vspace', 4);
	thumb.setAttribute('align', 'middle');
	thumb.setAttribute('id', thumb_id);

	tmp_thumb.parentNode.replaceChild(thumb, tmp_thumb);

	// IEでは読み込み完了してからリサイズしないと変な挙動になるので
	if (document.all) {
		thumb.onload = function() {
			autoImgSize(thumb_id);
		}
	// その他
	} else {
		autoImgSize(thumb_id);
	}

	return false;
}

// }}}
// {{{ autoImgSize()

/**
 * 読み込みが完了したサムネイルを本来のサイズで表示する
 *
 * @param String|Image thumb
 * @return void
 */
function autoImgSize(thumb)
{
	if (typeof thumb === 'string') {
		thumb = document.getElementById(thumb);
	}
	var size = getImgNaturalSize(thumb);
	if (!size) {
		return;
	}

	thumb.style.width = size.width.toString() + 'px';
	thumb.style.height = size.height.toString() + 'px';
}

// }}}
// {{{ autoAdjustImgSize()

/**
 * 読み込みが完了したサムネイルを本来のサイズで表示する
 *
 * @param String|Image thumb
 * @param Number dpr device-pixel-ratio
 * @return void
 */
function autoAdjustImgSize(thumb, dpr)
{
	if (typeof thumb === 'string') {
		thumb = document.getElementById(thumb);
	}
	var size = getImgNaturalSize(thumb);
	if (!size) {
		return;
	}

	if (dpr > 1.0) {
		thumb.style.width = Math.round(size.width / dpr).toString() + 'px';
		thumb.style.height = Math.round(size.height / dpr).toString() + 'px';
	}
}

// }}}
// {{{ getImgNaturalSize()

/**
 * 画像本来のサイズを得る
 *
 * @link http://d.hatena.ne.jp/uupaa/20090602/1243933843
 *
 * @param Image img
 * @return Object { 'width': Number, 'height': Number }
 */
function getImgNaturalSize(img)
{
	var size = _getImgNaturalSize(img);
	if (size) {
		if (typeof size.width !== 'number') {
			size.width = parseInt(size.width);
		}
		if (typeof size.height !== 'number') {
			size.height = parseInt(size.height);
		}
	}
	return size;
}

function _getImgNaturalSize(img)
{
	if (typeof img.width === 'undefined') {
		return null;
	}

	// Firefox, Safari and Chrome
	if (typeof img.naturalWidth !== 'undefined') {
		if (img.naturalWidth == 0) {
			return null; // not loaded
		}
		return { 'width': img.naturalWidth, 'height': img.naturalHeight };
	}

	// IE
	if (typeof window.attachEvent !== 'undefined') {
		var w, h, run, mem;

		run = img.runtimeStyle;
		mem = { 'w': run.width, 'h': run.height }; // keep runtimeStyle

		run.width  = 'auto'; // override
		run.height = 'auto';
		w = img.width;
		h = img.height;
		run.width  = mem.w; // restore
		run.height = mem.h;

		return { 'width': w, 'height': h };
	}

	// Opera
	if (typeof window.opera !== 'undefined') {
		var w = 0, h = 0, mem, fn;

		fn = function () {
			w = img.width;
			h = img.height;
		};

		mem = { w: img.width, h: img.height };
		img.removeAttribute('width');
		img.addEventListener('DOMAttrModified', fn, false);
		img.removeAttribute('height');
		// call fn
		img.removeEventListener('DOMAttrModified', fn, false);
		img.width  = mem.w;
		img.height = mem.h;

		return { 'width': w, 'height': h };
	}

	return null;
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
