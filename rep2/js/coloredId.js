(function(){

var b64chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
var b64decs = function(){
    var ret = {};
    for (var i = 0; i < b64chars.length; i++) ret[b64chars.charAt(i)] = i;
    return ret;
}();

var ip2long = function(ip) {
    var parts = ip.split('.');
    if (parts.length !== 4) return 0;
    var num = 0;
    for (var i = 0; i < 4; i++) {
        var val = parseInt(parts[i], 10);
        if (isNaN(val) || val < 0 || val > 255) return 0;
        num = (num << 8) + val;
    }
    return num >>> 0;
};

var halfid2num = function(idstr) {
    idstr = idstr.replace(/\./g, '+');
    var n = (b64decs[ idstr.charAt(0) ] << 18)
        |   (b64decs[ idstr.charAt(1) ] << 12)
        |   (b64decs[ idstr.charAt(2) ] <<  6)
        |   (b64decs[ idstr.charAt(3) ]);
    return n;
};

var colorFromId = function(id, count, mode) {
    var n1, n2;

    if (id.indexOf(':') !== -1) {
        // IPv6
        var s = id.replace(/[:*]/g, '');
        var s_pad = (s + '000000000000').substr(0, 12);
        n1 = parseInt(s_pad.substr(0, 6), 16);
        n2 = parseInt(s_pad.substr(6, 6), 16);
    } else if (/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(id)) {
        // IPv4
        var seed = ip2long(id);
        n1 = (seed >>> 8) & 0xFFFFFF;
        n2 = seed & 0xFF;
    } else {
        // 従来のID
        var id8 = (id + 'AAAAAAAA').substr(0, 8);
        n1 = halfid2num(id8.substr(0, 4));
        n2 = halfid2num(id8.substr(4, 4));
    }

    var h1 = n1 % 360;
    var h2 = n2 % 360;
    var isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (mode == null) mode = 'L*C*h';
    var ret = (function() {
        switch (mode) {
            case 'HSV':     // HSV色空間
                // 彩度S(HSV)：値域0（淡い）～1（濃い)
                var S = Math.min(count * 0.05, 1);
                // 明度V(HSV)：値域0（暗い）～1（明るい）
                var V = isDark ? Math.min(0.1 + count * 0.025, 1) : Math.max(1 - count * 0.025, 0.1);
                return {label : ColorLib.HSV2RGB([h2, 1, 0.6]),
                        body  : ColorLib.HSV2RGB([h1, S, V]) };
                break;
            case 'HLS':     // HLS色空間
                // 輝度L(HLS)：値域0（黒）～0.5（純色）～1（白）
                var L = isDark ? Math.min(0.1 + count * 0.025, 0.8) : Math.max(0.95 - count * 0.025, 0.2);
                // 彩度S(HLS)：値域0（灰色）～1（純色）
                var S = Math.min(count * 0.05, 1);
                return {label : ColorLib.HLS2RGB([h2, 0.6, 0.5]),
                        body  : ColorLib.HLS2RGB([h1, L, S]) };
                break;
            case 'L*C*h':   // L*C*h色空間
                // 明度L*(L*C*h)：値域0（黒）～50（純色）～100（白）
                var L = isDark ? Math.min(10 + count * 2.5, 80) : Math.max(100 - count * 2.5, 20);
                // 彩度C*(L*C*h)：値域0（灰色）～100（純色）
                var C = Math.floor(40 * Math.sin((Math.min(count, 25) * 180 / 50) * Math.PI / 180) + 8);
                return {label : ColorLib.LCh2RGB([50, 60, h2]),
                        body  : ColorLib.LCh2RGB([L, C, h1]) };
                break;
        }
    })();
    ret.nums = [n1, n2];
    return ret;
};

var styleFromId = function(id, count, hissi, mode) {
    if (mode == null) mode = 'L*C*h';
    var colors = colorFromId(id, count, mode);
    var f = function (c) {
        var light = c.type == 'L*C*h' ? c.LCh[0]
            : (RGB2LCh([c.r, c.g, c.b]))[0];
        var ret = {backgroundColor : c.color, color : light > 60 ? '#000' : '#fff'};
        if (hissi && hissi > 0 && count >= hissi) {
            ret.animation = 'p2-hissi-blink 1s step-end infinite';  // 必死チェッカー発動
            ret.webkitAnimation = 'p2-hissi-blink 1s step-end infinite';
        }
        return ret;
    };
    return {label : f(colors.label), body : f(colors.body),
            klass : cssClassFromNum(colors.nums[0], colors.nums[1]) };
};

var cssClassFromNum = function(n1, n2) {
    return 'idcss-'
        + ('000000' + n1.toString(16)).slice(-6)
        + ('000000' + n2.toString(16)).slice(-6);
};

var cssClassFromId = function(id) {
    var colors = colorFromId(id, 0);
    return cssClassFromNum(colors.nums[0], colors.nums[1]);
};

var toggle = function(idstr, cnt, colorStyle, hissi) {
    var styles = styleFromId(idstr, cnt, hissi);
    var d0 = delrule(styles.klass + '-l');
    var d1 = delrule(styles.klass + '-b');
    var n  = delrule(styles.klass);
    if (!d0 && !d1) {
        for (var i in colorStyle) {
            styles.label[i] = (styles.label[i] ? styles.label[i] + ' ' : '')
                + colorStyle[i];
            styles.body[i] = (styles.body[i] ? styles.body[i] + ' ' : '')
                + colorStyle[i];
        }
        insrule(styles.label, styles.klass + '-l');
        insrule(styles.body, styles.klass + '-b');
        return true;;
    }
    return false;
};

var STYLEID = 0;    // 標的にするスタイルシートのID

var getRule = function(kls, f) {
    var i = STYLEID;
    var rules = document.styleSheets[i].cssRules
        ? document.styleSheets[i].cssRules
        : document.styleSheets[i].rules;
    for(var j = 0; j < rules.length; j++) {
        if (rules[j].selectorText && rules[j].selectorText == ('.' + kls)) {
            return rules[j];
        }
    }
    return null;
};

var delrule = function(kls) {
//    for(var i=0; i<document.styleSheets.length; i++) {
    var i = STYLEID;
    var rules = document.styleSheets[i].cssRules
        ? document.styleSheets[i].cssRules
        : document.styleSheets[i].rules;
    for(var j = 0; j < rules.length; j++) {
        if (rules[j].selectorText && rules[j].selectorText == ('.' + kls)) {
            if (document.all) document.styleSheets[i].removeRule(j)
            else document.styleSheets[i].deleteRule(j);
            return true;
        }
    }
//    }
    return false;
};

var clearrule = function() {
    var i = STYLEID;
    var rules = document.styleSheets[i].cssRules
        ? document.styleSheets[i].cssRules
        : document.styleSheets[i].rules;
    var f = function() {
        var hit = 0;
        for(var j = 0; j < rules.length; j++) {
            if (rules[j].selectorText && rules[j].selectorText.substr(0, '.idcss-'.length) == '.idcss-') {
                if (document.all) document.styleSheets[i].removeRule(j)
                else document.styleSheets[i].deleteRule(j);
                hit++;
            }
        }
        return hit;
    };
    while (f() > 0);
};

var insrule = function(styles, kls) {
    var ss = document.styleSheets[STYLEID];
    var style = '';
    for (var i in styles) style += i.replace(/([A-Z])/g, "-$1").toLowerCase() + ':' + styles[i] + ';';
    if (document.all) ss.addRule('.' + kls, style)
    else ss.insertRule('.' + kls + '{' + style + '}', ss.cssRules.length);
};


var makeColor = function(idstr, cnt, colorStyle, hissi) {
    var styles = styleFromId(idstr, cnt, hissi);
    for (var i in colorStyle) {
        styles.label[i] = (styles.label[i] ? styles.label[i] + ' ' : '')
            + colorStyle[i];
        styles.body[i] = (styles.body[i] ? styles.body[i] + ' ' : '')
            + colorStyle[i];
    }
    insrule(styles.label, styles.klass + '-l');
    insrule(styles.body, styles.klass + '-b');
};

var addStyles = function(idstr, cnt, hissi, addStyle) {
    var styles = styleFromId(idstr, cnt, hissi);
    for (var i in addStyle) {
        styles.label[i] = addStyle[i];
        styles.body[i] = addStyle[i];
    }
    var l = getRule(styles.klass + '-l');
    if (l) for (var i in styles.label) l.style[i] = styles.label[i];
    else insrule(styles.label, styles.klass + '-l');
    var b = getRule(styles.klass + '-b');
    if (b) for (var i in styles.body) b.style[i] = styles.body[i];
    else insrule(styles.body, styles.klass + '-b');
    var n = getRule(styles.klass);
    if (n) b.style.color = addStyle.color;
    else insrule({color : addStyle.color}, styles.klass);
};


if (!this['ColoredIDLib']) ColoredIDLib = {
    makeColor : makeColor,
    toggle : toggle,
    addStyles : addStyles,
    clearrule : clearrule
};

var colorFromWatchoi = function(wid32, count, mode) {
    if (wid32.length != 8) return;
    var n = parseInt(wid32, 16);
    var h1 = n % 360;
    var isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (mode == null) mode = 'L*C*h';
    var ret = (function() {
        switch (mode) {
            case 'HSV':     // HSV色空間
                var S = Math.min(count * 0.05, 1);
                var V = isDark ? Math.min(0.1 + count * 0.025, 1) : Math.max(1 - count * 0.025, 0.1);
                return {body  : ColorLib.HSV2RGB([h1, S, V]) };
                break;
            case 'HLS':     // HLS色空間
                var L = isDark ? Math.min(0.1 + count * 0.025, 0.95) : Math.max(0.95 - count * 0.025, 0.1);
                var S = Math.min(count * 0.05, 1);
                return {body  : ColorLib.HLS2RGB([h1, L, S]) };
                break;
            case 'L*C*h':   // L*C*h色空間
                var L = isDark ? Math.min(10 + count * 2.5, 100) : Math.max(100 - count * 2.5, 10);
                var C = Math.floor(40 * Math.sin((Math.min(count, 25) * 180 / 50) * Math.PI / 180) + 8);
                return {body  : ColorLib.LCh2RGB([L, C, h1]) };
                break;
        }
    })();
    ret.num = n;
    return ret;
};

var styleFromWatchoi = function(wid32, count, hissi, mode) {
    if (mode == null) mode = 'L*C*h';
    wid32 = wid32.substr(0, 8);
    var colors = colorFromWatchoi(wid32, count, mode);
    var f = function (c) {
        var light = c.type == 'L*C*h' ? c.LCh[0]
            : (RGB2LCh([c.r, c.g, c.b]))[0];
        var ret = {backgroundColor : c.color, color : light > 60 ? '#000' : '#fff'};
        if (hissi && hissi > 0 && count >= hissi) {
            ret.animation = 'p2-hissi-blink 1s step-end infinite';  // 必死チェッカー発動
            ret.webkitAnimation = 'p2-hissi-blink 1s step-end infinite';
        }
        return ret;
    };
    return {body : f(colors.body),
            klass : 'watchoicss-' + wid32 };
};

var toggleWatchoi = function(wid32, cnt, colorStyle, hissi) {
    var styles = styleFromWatchoi(wid32, cnt, hissi);
    var d1 = delrule(styles.klass + '-b');
    var n  = delrule(styles.klass);
    if (!d1) {
        for (var i in colorStyle) {
            styles.body[i] = (styles.body[i] ? styles.body[i] + ' ' : '')
                + colorStyle[i];
        }
        insrule(styles.body, styles.klass + '-b');
        return true;
    }
    return false;
};

var clearruleWatchoi = function() {
    var i = STYLEID;
    var rules = document.styleSheets[i].cssRules
        ? document.styleSheets[i].cssRules
        : document.styleSheets[i].rules;
    var f = function() {
        var hit = 0;
        for(var j = 0; j < rules.length; j++) {
            if (rules[j].selectorText && rules[j].selectorText.substr(0, '.watchoicss-'.length) == '.watchoicss-') {
                if (document.all) document.styleSheets[i].removeRule(j)
                else document.styleSheets[i].deleteRule(j);
                hit++;
            }
        }
        return hit;
    };
    while (f() > 0);
};

var makeColorWatchoi = function(wid32, cnt, colorStyle, hissi) {
    var styles = styleFromWatchoi(wid32, cnt, hissi);
    for (var i in colorStyle) {
        styles.body[i] = (styles.body[i] ? styles.body[i] + ' ' : '')
            + colorStyle[i];
    }
    insrule(styles.body, styles.klass + '-b');
};

var addStylesWatchoi = function(wid32, cnt, hissi, addStyle) {
    var styles = styleFromWatchoi(wid32, cnt, hissi);
    for (var i in addStyle) {
        styles.body[i] = addStyle[i];
    }
    var b = getRule(styles.klass + '-b');
    if (b) for (var i in styles.body) b.style[i] = styles.body[i];
    else insrule(styles.body, styles.klass + '-b');
    var n = getRule(styles.klass);
    if (n) b.style.color = addStyle.color;
    else insrule({color : addStyle.color}, styles.klass);
};

if (!this['ColoredWatchoiLib']) ColoredWatchoiLib = {
    makeColor : makeColorWatchoi,
    toggle : toggleWatchoi,
    addStyles : addStylesWatchoi,
    clearrule : clearruleWatchoi
};

})();

(function(){

var addIdlist = function(addlist) {
    for (var i in addlist) {
        if (this.idlist[i]) this.idlist[i] = this.idlist[i] + addlist[i]
        else this.idlist[i] = addlist[i];
    }
};

var initColor = function(rate, idlist) {
    if (!rate) rate = this.rate;
    if (!idlist) idlist = this.idlist;
    if (rate && idlist) {
        for (var i in idlist) {
            if (idlist[i] >= rate)
                ColoredIDLib.makeColor(i, idlist[i], this.colorStyle, this.hissi);
        }
    }
}

var refreshColor = function(rate) {
    this.clear();
    this.initColor(rate);
};

var toggle = function(idstr) {
    if (this.idlist[idstr])
        ColoredIDLib.toggle(idstr, this.idlist[idstr], this.colorStyle, hissi);
};


var getColor = function() {
    color = this.colors.shift();
    this.colors.push(color);
    return color;
};

var mark = function(idstr) {
    var style = {color : this.getColor()};
    var addStyle = (function(p) {
            var F = new Function();
            F.prototype = p;
            var ret = new F();
            ret.color = style.color;
            return ret;
            })(this.highlightStyle);
    ColoredIDLib.addStyles(idstr, this.idlist[idstr], this.hissi, addStyle);
};

var click = function(idstr, evt) {
    if (evt.type == 'click') this.toggle(idstr)
    else if (evt.type == 'dblclick') this.mark(idstr);
};

var createSPMmenuPC = function (idval) {
    var amenu = document.createElement('div');
    amenu.id = idval;
    amenu.className = 'spm';
    amenu.appendItem = function()
    {
        this.appendChild(SPM.createMenuItem.apply(this, arguments));
    }
    SPM.setOnPopUp(amenu, amenu.id, true);

    var _this = this;
    amenu.appendItem('全てクリア', function() {_this.clear()});
    if (this.tops) amenu.appendItem('トップ10', function() {_this.refreshColor(_this.tops)});
    if (this.average) amenu.appendItem('平均(' + _this.average + ')以上', function() {_this.refreshColor(_this.average)});
    amenu.appendItem(_this.rate + '以上', function() {_this.refreshColor(_this.rate)});
    if (this.rate != 2) amenu.appendItem('2以上', function() {_this.refreshColor(2)});
    return amenu;
};

var setupSPMPC = function (objName) {
    var amenu = this.createSPMmenuPC(objName + '_col');
    document.getElementById(objName + '_spm').appendItem(
            'IDカラー', null, objName + '_col');
    document.getElementById('popUpContainer').appendChild(amenu);
};

if (!this['IDColorChanger']) {
    IDColorChanger = function(idlist, hissi) {
        this.idlist = idlist;
        this.hissi = hissi;
    };
    IDColorChanger.prototype = {
        idlist : {},
        hissi : null,               // 必死チェッカー発動値
        rate : null,                // 初期カラーリング閾値
        addIdlist : addIdlist,
        initColor : initColor,
        toggle : toggle,
        mark : mark,
        getColor : getColor,
        click : click,
        clear : ColoredIDLib.clearrule,
        refreshColor : refreshColor,
        colors : [],
        colorStyle : {},
        highlightStyle : {},
        createSPMmenuPC : createSPMmenuPC,
        setupSPMPC : setupSPMPC
    };
}

var addWatchoilist = function(addlist) {
    for (var i in addlist) {
        if (this.watchoilist[i]) this.watchoilist[i] = this.watchoilist[i] + addlist[i]
        else this.watchoilist[i] = addlist[i];
    }
};

var initColorWatchoi = function(rate, watchoilist) {
    if (!rate) rate = this.rate;
    if (!watchoilist) watchoilist = this.watchoilist;
    if (rate && watchoilist) {
        for (var i in watchoilist) {
            if (watchoilist[i] >= rate)
                ColoredWatchoiLib.makeColor(i, watchoilist[i], this.colorStyle, this.hissi);
        }
    }
};

var refreshColorWatchoi = function(rate) {
    this.clear();
    this.initColor(rate);
};

var toggleWatchoiChanger = function(wid32) {
    if (this.watchoilist[wid32])
        ColoredWatchoiLib.toggle(wid32, this.watchoilist[wid32], this.colorStyle, this.hissi);
};

var markWatchoi = function(wid32) {
    var style = {color : this.getColor()};
    var addStyle = (function(p) {
            var F = new Function();
            F.prototype = p;
            var ret = new F();
            ret.color = style.color;
            return ret;
            })(this.highlightStyle);
    ColoredWatchoiLib.addStyles(wid32, this.watchoilist[wid32], this.hissi, addStyle);
};

var clickWatchoi = function(wid32, evt) {
    if (evt.type == 'click') this.toggle(wid32)
    else if (evt.type == 'dblclick') this.mark(wid32);
};

var createSPMmenuWatchoiPC = function (idval) {
    var amenu = document.createElement('div');
    amenu.id = idval;
    amenu.className = 'spm';
    amenu.appendItem = function()
    {
        this.appendChild(SPM.createMenuItem.apply(this, arguments));
    }
    SPM.setOnPopUp(amenu, amenu.id, true);

    var _this = this;
    amenu.appendItem('全てクリア', function() {_this.clear()});
    if (this.tops) amenu.appendItem('トップ10', function() {_this.refreshColor(_this.tops)});
    if (this.average) amenu.appendItem('平均(' + _this.average + ')以上', function() {_this.refreshColor(_this.average)});
    amenu.appendItem(_this.rate + '以上', function() {_this.refreshColor(_this.rate)});
    if (this.rate != 2) amenu.appendItem('2以上', function() {_this.refreshColor(2)});
    return amenu;
};

var setupSPMWatchoiPC = function (objName) {
    var amenu = this.createSPMmenuPC(objName + '_wcol');
    document.getElementById(objName + '_spm').appendItem(
            'ワッチョイカラー', null, objName + '_wcol');
    document.getElementById('popUpContainer').appendChild(amenu);
};

if (!this['WatchoiColorChanger']) {
    WatchoiColorChanger = function(watchoilist, hissi) {
        this.watchoilist = watchoilist;
        this.hissi = hissi;
    };
    WatchoiColorChanger.prototype = {
        watchoilist : {},
        hissi : null,
        rate : null,
        addWatchoilist : addWatchoilist,
        initColor : initColorWatchoi,
        toggle : toggleWatchoiChanger,
        mark : markWatchoi,
        getColor : getColor,
        click : clickWatchoi,
        clear : ColoredWatchoiLib.clearrule,
        refreshColor : refreshColorWatchoi,
        colors : [],
        colorStyle : {},
        highlightStyle : {},
        createSPMmenuPC : createSPMmenuWatchoiPC,
        setupSPMPC : setupSPMWatchoiPC
    };
}

})()

var setupSPMColorI = function () {
    var colorActionDiv = document.getElementById('spm-color-action');
    var targetSelect = document.getElementById('spm-color-target');
    var rateSelect = document.getElementById('spm-color-rate');
    var okBtn = document.getElementById('spm-color-ok');

    if (!colorActionDiv || !targetSelect || !rateSelect || !okBtn) return;

    var hasIdCol = (typeof idCol !== 'undefined');
    var hasWatchoiCol = (typeof watchoiCol !== 'undefined');

    // 両方不在ならセクション全体をグレーアウト
    if (!hasIdCol && !hasWatchoiCol) {
        colorActionDiv.style.opacity = '0.5';
        targetSelect.disabled = true;
        rateSelect.disabled = true;
        okBtn.disabled = true;
        return;
    }

    colorActionDiv.style.display = 'table';
    colorActionDiv.style.opacity = '1.0';

    // targetSelect の各項目の有効・無効（グレーアウト）制御
    for (var i = 0; i < targetSelect.options.length; i++) {
        var opt = targetSelect.options[i];
        if (opt.value === 'id') {
            opt.disabled = !hasIdCol;
        } else if (opt.value === 'watchoi') {
            opt.disabled = !hasWatchoiCol;
        }
    }

    // デフォルトの選択肢を有効な方に合わせる
    if (!hasIdCol && hasWatchoiCol) {
        targetSelect.value = 'watchoi';
    } else if (hasIdCol) {
        targetSelect.value = 'id';
    }

    targetSelect.onchange = function() {
        var t = targetSelect.value;
        var colObj = (t === 'id') ? (hasIdCol ? idCol : null) : (hasWatchoiCol ? watchoiCol : null);

        if (!colObj) {
            rateSelect.disabled = true;
            okBtn.disabled = true;
            return;
        }

        rateSelect.disabled = false;
        okBtn.disabled = false;

        // PHP側で定義済みの option のテキストを動的に更新する
        for (var j = 0; j < rateSelect.options.length; j++) {
            var opt = rateSelect.options[j];
            switch (opt.value) {
                case 'top10':
                    opt.style.display = colObj.tops ? '' : 'none';
                    opt.disabled = !colObj.tops;
                    break;
                case 'average':
                    opt.style.display = colObj.average ? '' : 'none';
                    opt.disabled = !colObj.average;
                    if (colObj.average) opt.innerHTML = '平均(' + colObj.average + ')以上';
                    break;
                case 'rate':
                    opt.innerHTML = colObj.rate + '以上';
                    break;
                case '2':
                    var isTwoDefault = (colObj.rate == 2);
                    opt.style.display = isTwoDefault ? 'none' : '';
                    opt.disabled = isTwoDefault;
                    break;
            }
        }
    };

    okBtn.onclick = function() {
        if (okBtn.disabled) return;
        var target = targetSelect.value;
        var rate = rateSelect.value;
        var colObj = (target === 'id') ? (hasIdCol ? idCol : null) : (hasWatchoiCol ? watchoiCol : null);
        if (!colObj) return;

        if (rate === 'clear') colObj.clear();
        else if (rate === 'top10') colObj.refreshColor(colObj.tops);
        else if (rate === 'average') colObj.refreshColor(colObj.average);
        else if (rate === 'rate') colObj.refreshColor(colObj.rate);
        else if (rate === '2') colObj.refreshColor(2);

        SPM.hide();
    };

    targetSelect.onchange();
};
