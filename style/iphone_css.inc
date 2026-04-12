<?php
echo <<<EOSTYLE
/* rep2expack - stylesheet for iPhone */

/* {{{ General */

body {
    margin: 0;
    padding: 10px;
    font-size: {$STYLE['fontsize']};
EOSTYLE;
if (!empty($STYLE['fontfamily'])) {
    echo "\n    font-family: {$STYLE['fontfamily']};";
}
echo <<<EOSTYLE

    line-height: 140%;
    color: #000;
    background-color: #fafafa;
    -webkit-text-size-adjust: none;
}

EOSTYLE;
if (!empty($STYLE['fontfamily_bold'])) {
    echo <<<EOSTYLE
b, strong {
    font-family: {$STYLE['fontfamily_bold']};
}

EOSTYLE;
}
echo <<<EOSTYLE
input,option,select,textarea{
	font-size:{$STYLE['fontsize']};
}

body > *:first-child {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

body > *:last-child {
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

div, h1, h2, h3, h4, h5, h6, li, p {
    word-break: normal;
    word-wrap: break-word;
}

h1, h2, h3, h4, h5, h6, p, form {
    margin: 8px 0;
}

fieldset {
    margin: 1em 0;
    padding: 0.5em 10px 10px 10px;
    border: 1px solid #444 !important;
}

legend {
    font-weight: bold;
    font-size: 1.1em;
    color: #333;
    padding: 0 5px;
    width: auto;
    margin-bottom: 0;
    border: none;
}

a {
    word-break: break-all;
}

a:link, a:visited {
    color: #000;
    text-decoration: underline;
}

/* 左上のTOPボタン */
a#backButton {
    position: absolute;
    overflow: hidden;
    top: 8px;
    margin: 0;
    left: 6px;
    right: auto;
    height: 30px;
    padding-right: 0px;
    padding-left: 0px;
    width: auto;
    max-width: 100px;
    font-family: Helvetica;
    line-height: 30px;
    font-size: 12px;
    font-weight: bold;
    text-shadow: rgba(0, 0, 0, 0.6) 0px -1px 0;
    text-overflow: ellipsis;
    text-decoration: none;
    white-space: nowrap;
    color: #FFFFFF;
    border-width: 0 8px 0 14px;
    /* background: #333; border-radius: 5px; min-width:46px; text-align:center */
    background: none;
    -webkit-border-image: url(iui/backButton.png?14011419) 0 8 0 14;
    border-image: url(iui/backButton.png?14011419) 0 8 0 14 fill;
    -webkit-border-image: -webkit-image-set(url(iui/backButton.png?14011419) 1x, url(iui/backButton@2x.png?14011419) 2x, url(iui/backButton@3x.png?14011419) 3x) 0 8 0 14;
    border-image: image-set(url(iui/backButton.png?14011419) 1x, url(iui/backButton@2x.png?14011419) 2x, url(iui/backButton@3x.png?14011419) 3x) 0 8 0 14;
}

div#toolbar_header {
    text-align: right;
    padding: 6px;
    height: 45px;
    box-sizing: border-box;
    -webkit-box-sizing: border-box;
}

a img {
    vertical-align: middle;
}

b.filtering, span.matched {
    font-weight: bold;
}

span.label {
    cursor: pointer;
}

div.left, td.left {
    text-align: left;
}

div.center, td.center {
    text-align: center;
}

div.right, td.right {
    text-align: right;
}

p.empty {
    margin: 0;
    padding: 7px 11px;
    background-color: #fff;
    color: #333;;
}

/* }}} *
/* {{{ Res */
textarea#MESSAGE {
    padding: 6px;
    line-height: 132%;
    width: 100%;
    box-sizing: border-box;
    -webkit-box-sizing: border-box;
}

/* }}} *
/* {{{ Forms */

form.inline {
    display: inline;
    margin: 0 5px;
}

form.inline-block {
    display: inline-block;
    margin: 0 5px;
}

/* }}} *
/* {{{ Navigations */

div.navi, div.pager, div.toolbar {
    margin: 5px 0;
}

a.button,
div.navi a[href]:not(.nobutton),
div.pager a[href]:not(.nobutton),
div.toolbar a[href]:not(.nobutton) {
    display: inline-block;
    min-width: 12px; /* 15 * 2 - (8 + 1) * 2 */
    margin: 4px 2px;
    padding: 3px 8px;
    text-align: center;
    vertical-align: middle;
    line-height: 150%;
    text-decoration: none;
    color: #000000;
    background: #ffffff url('img/iphone/btn-bg.png') repeat-x left bottom;
    border: #505050 solid 1px;
    -webkit-border-radius: 15px;
    border-radius: 15px;
}

span.autosave-info {
    font-size: xx-small;
    color: #777;
}

/* }}} *
/* {{{ SPM */

div#spm {
    display: none;
    position: absolute;
    /*width: 300px;*/
    max-width: 300px;
    top: 0;
    left: 10px;
    z-index: 1000;
    margin: 5px 0 0 5px;
    padding: 2px 4px;
    color: #ffffff;
    background-color: rgba(0, 0, 0, 0.8);
    -webkit-box-shadow: rgba(0, 0, 0, 0.7) 0 2px 5px;
    box-shadow: rgba(0, 0, 0, 0.7) 0 2px 5px;
    -webkit-border-radius: 8px;
    border-radius: 8px;
    line-height: 170%;
}

div#spm span#spm-num {
    /*color: #ffffcc;*/
    /*text-decoration: underline;;*/
}

div#spm > div {
    margin: 0;
    padding: 8px;
}

div#spm > div.spm-section {
    white-space: nowrap;
    margin: 2px;
    padding: 0px;
    border-bottom: 1px white solid;
    vertical-align: middle;
}

div#spm > div#spm-menu > span {
    display: block;
    white-space: nowrap;
    margin: 2px;
    padding: 0px 4px;
    vertical-align: middle;
    text-decoration: none;
    color: white;
    font-size: large;
}

div#spm > div#spm-action {
    text-align: center;
}

div#spm > div#spm-action > select {
    background-color: #ffffff;
}

div#spm > img#spm-closer {
    cursor: pointer;
    position: absolute;
    top: -12px;
    left: -12px;
}

/* }}} */
/* {{{ Read */

span.thread_title {
    text-align: left;
    font-weight: bold;
    font-size: {$STYLE['read_fontsize']};
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    width: 100%;
    box-sizing: border-box;
    display: inline-block;
}

td#thread_title {
    text-align: left;
    font-weight: bold;
    font-size: calc({$STYLE['read_fontsize']} - 2px);
    line-height: 120%;
}

td#thread_title > div {
    padding: 0 0 0 8px;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

span.link_right_button {
    position: absolute;
    text-align: right;
    right: 0;
    top: 0;
    padding: 3px 0;
    display: inline-block;
}

div.thread_title {
    background-color: #808080;
    position: relative;
    padding: 4px 0px 4px 4px;
}

h3 > font[color], div > font[color] > b {
    color: #252525;
}

div:not(.res-header) > span.onthefly {
    padding: 2px 4px;
    border: #00aa00 solid 1px;
    color: #00aa00;
    font-style: italic;
}

div.res:not(.aborned) {
    margin: 0;
    padding: 0;
    /*border-top: #fff solid 1px;*/
    border-top: #dcdcdc solid 1px;
    /*background-color: #FAFAFA;*/
}

div.thread > div.res:not(:first-child) {
    /*	border-top-width: 4px;*/
}

div.res.aborned {
    margin: 0;
    padding: 0;
    font-size: xx-small;
    line-height: 100%;
}

div.res > div.message {
    margin: 0;
    padding: 5px 10px;
    font-size: {$STYLE['read_fontsize']};
    line-height: 140%;
    word-break: break-all;
}

div.res > div.reslist {
    margin: 0;
    padding: 5px 10px;
    font-size: {$STYLE['read_fontsize']};
    line-height: 140%;
    word-break: break-all;
}

div.res > div.res-header {
    margin: 0;
    padding: 5px;
    font-size: {$STYLE['read_fontsize']};
    line-height: 120%;
    color: #1144AA;
    display: inline-block;
    /*border-bottom: #808080 solid 1px;*/
}

div.thread > div.res > div.res-header {
    /*background-color: #fafafa;*/
}

div.res-header > span.no {
}

div.res-header > span.no.newres {
    color: #ff6600;
}

div.res-header > span.no.onthefly {
    color: #00aa00;
}

div.res-header > span.no:before {
    content: "[";
    color: #000000;
}

div.res-header > span.no:after {
    content: "]";
    color: #000000;
}

div.res-header > span.name {
}

div.res-header > span.name:after {
    content: ":";
}

div.res-header > span.mail {
}

div.res-header > span.mail:after {
    content: ":";
}

div.res-header > span.date-id {
}

div.respop {
    position: absolute;
    top: 0;
    left: 0;
    margin: 0;
    padding: 12px 6px 0 14px;
}

div.respop > div.res {
    margin: 0 0 8px 0 !important;
    background-color: rgba(255, 255, 250, 0.95) !important;
    border: rgba(108, 108, 108, 0.8) solid 1px !important;
    -webkit-border-radius: 8px;
    border-radius: 8px;
    -webkit-box-shadow: rgba(0, 0, 0, 0.7) 0 2px 5px;
    box-shadow: rgba(0, 0, 0, 0.7) 0 2px 5px;
}

div.respop > div.res > div.res-header {
    border-bottom-color: rgba(108, 108, 108, 0.8) !important;
}

div.respop > img.close-button {
    cursor: pointer;
    position: absolute;
    top: 0;
    left: 2px;
}

div.respop > div.res > div.respop-back {
    margin: 0;
    padding: 0;
    border-top: rgba(108, 108, 108, 0.8) solid 1px;
    text-align: center;
}

div.respop > div.res > div.respop-back > a {
    display: block;
    margin: 0;
    padding: 3px;
    font-size: small;
    text-decoration: none;
    color: #252525;
}

a.aas {
    display: inline-block;
    vertical-align: middle;
    margin: 0 5px;
    padding: 0;
    border: #aaaaaa inset 3px;
}

/* }}} *
/* {{{ Subject */

ul.subject {
    display: block;
    margin: 0;
    padding: 0;
}

ul.subject > li {
    display: block;
    margin: 0;
    padding: 0;
    border-bottom: #e0e0e0 solid 1px;
}

ul.subject > li:first-child {
    border-top: #e0e0e0 solid 1px;
}

ul.subject > li > a {
    display: block;
    margin: 0;
    padding: 8px 10px;
    word-break: normal;
    font-size: {$STYLE['sb_fontsize']};
    text-decoration: none;
    color: #000000;
    background-color: #ffffff;
}

ul.subject > li > a:link, ul.subject > li > a:visited {
    color: #000000;
    background-color: #ffffff;
}

ul.subject > li > a:active {
}

ul.subject > li > a:link > span.new, ul.subject > li > a:visited > span.new {
    color: #8C99B4;
}

ul.subject > li > a:active > span.new {
    color: #ffffff;
}

ul.subject > li > a > span.info {
    display: none;
}

ul.subject > li > a > span.title {
    word-break: break-all;
    font-size: calc({$STYLE['sb_fontsize']} - 1px);
    line-height: 127%;
    font-weight: bold;
}

ul.subject > li > a > span.ita {
    white-space: nowrap;
    color: #666;
    font-size: calc({$STYLE['sb_fontsize']} - 4px);
}

ul.subject > li > a > span.num {
    white-space: nowrap;
    color: #999999;
    font-size: calc({$STYLE['sb_fontsize']} - 2px);
}

ul.subject > li > a > span.unread {
    padding: 0 4px;
    color: #ffffff !important;
    background-color: #666666;
    -webkit-border-radius: 8px;
    border-radius: 8px;
}

ul.subject > li > a > span.unread.new {
    font-weight: bold;
    background-color: #8C99B4;
}

ul.subject > li > a > span.unread.offline {
}

ul.subject > li > a > span.count {
    white-space: nowrap;
    color: #999;
    font-size: calc({$STYLE['sb_fontsize']} - 2px);
}

ul.subject > li > a > span.count:before {
    content: "(";
}

ul.subject > li > a > span.count:after {
    content: ")";
}

ul.subject > li > a > span.score {
    white-space: nowrap;
    color: #666;
    font-size: calc({$STYLE['sb_fontsize']} - 4px);
}

/* }}} */
/* {{{ Pop-up dialog */

div.popup-dialog {
    display: none;
    position: absolute;
    top: 0;
    left: 10px;
    z-index: 1001;
    margin: 5px 0 0 5px;
    padding: 0;
    max-width: 300px;
    color: #ffffff;
    background-color: rgba(0, 0, 0, 0.8);
    -webkit-box-shadow: rgba(0, 0, 0, 0.7) 0 2px 5px;
    box-shadow: rgba(0, 0, 0, 0.7) 0 2px 5px;
    -webkit-border-radius: 8px;
    border-radius: 8px;
}

div.popup-dialog > img.close-button {
    cursor: pointer;
    position: absolute;
    top: -12px;
    left: -12px;
}

div.popup-dialog > div {
    margin: 0;
    font-weight: normal;
}

div.popup-dialog > div.popup-dialog-text {
    padding: 8px 12px;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 14px !important;
    line-height: 14px !important;
}

div.popup-dialog > div.popup-dialog-buttons {
    padding: 0 12px 8px 12px;
    text-align: center;
    font-size: 14px !important;
    line-height: 14px !important;
}

div.popup-dialog > div.popup-dialog-buttons > input[type="button"] {
    margin: 0;
    padding: 0;
    width: auto;
    height: auto;
    font-size: 14px !important;
    color: #000000;
}

/* }}} */
/* {{{ Pop-in information */

ul.subject > li.info-pop {
    margin: 0;
    padding: 10px 5px;
    background-color: #333;
    color: #ffffff;
    font-size: 16px !important;
    font-weight: bold;
    line-height: 100%;
}

ul.subject > li.info-pop > div.info-pop-buttons {
    margin: 0;
    padding: 0;
    width: auto;
    text-align: center;
    font-size: 14px !important;
    line-height: 14px !important;
}

ul.subject > li.info-pop > div.info-pop-buttons:not(:first-child) {
    margin-top: 10px;
}

ul.subject > li.info-pop > div.info-pop-buttons input[type="button"] {
    margin: 0;
    padding: 0;
    width: auto;
    height: auto;
    font-size: 14px !important;
    font-weight: normal;
    color: #000000;
}

ul.subject > li.info-pop > table.info-pop-fav {
    margin: 10px auto 0 auto;
    padding: 0;
    width: auto;
}

ul.subject > li.info-pop > table.info-pop-fav tbody,
ul.subject > li.info-pop > table.info-pop-fav tr {
    margin: 0;
    padding: 0;
}

ul.subject > li.info-pop > table.info-pop-fav td {
    margin: 0;
    padding: 2px;
    line-height: 100%;
    text-align: left;
    vertical-align: middle;
}

ul.subject > li.info-pop > table.info-pop-fav td:first-child {
    padding-right: 8px;
}

/* }}} */
/* {{{ new layout */

body.nopad {
    margin: 0;
    padding: 0;
}

div.info {
    margin: 0;
    padding: 5px 10px;
    border-top: #808080 solid 1px;
    background-color: #ccf;
    color: #000;
    font-size: 14px;
    line-height: 16px;
}

a.hoverable {
    -webkit-tap-highlight-color: transparent;
}

a > img {
    border: none;
}

/* }}} */
/* {{{ new toolbar */

div.ntoolbar {
    min-width: 320px;
    margin: 0;
    padding: 0;
    background-color: #333;
    color: #fff;
}

div.ntoolbar#pager {
    background-color: #808080;
    color: #fff;
}

div.ntoolbar#header {
}

div.ntoolbar#footer {
}

h1.ptitle {
    margin: 0;
    padding: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
    font-weight: bold;
    font-size: 16px;
    line-height: 16px;
}

h1.ptitle > span.thin {
    font-weight: normal;
    font-size: 14px;
}

div.ntoolbar > form,
div.ntoolbar > div > form {
    margin: 0;
    padding: 5px 10px;
    border-top: #808080 solid 1px;
    font-size: 14px;
    line-height: 16px;
}

div.ntoolbar > div {
    margin: 0;
    padding: 0;
    font-size: 14px;
    line-height: 16px;
}

div.ntoolbar > div.hits {
    padding: 5px 10px;
    border-top: #808080 solid 1px;
    background-color: #ffc;
    color: #000;
}

div.ntoolbar > div.extra {
    display: none;
}

div.ntoolbar table {
    width: 100%;
    margin: 0;
    padding: 0;
    border-collapse: collapse;
    empty-cells: show;
    table-layout: fixed;
}

div.ntoolbar td {
    position: relative;
    width: 20%;
    min-width: 64px;
    margin: 0;
    padding: 4px 0;
    text-align: center;
    vertical-align: middle;
    border-top: #808080 solid 1px;
    font-size: 11px;
    line-height: 11px;
}

div.ntoolbar#pager td {
    padding: 0;
    border-top: none;
}

div.ntoolbar td[colspan='2'] {
    width: 40%;
}

div.ntoolbar td[colspan='3'] {
    width: 60%;
}

div.ntoolbar td[colspan='4'] {
    width: 80%;
}

div.ntoolbar td[colspan='5'] {
    width: 100%;
}

div.ntoolbar td > a {
    display: inline-block;
    position: relative;
    width: 60px;
    margin: 0 auto;
    padding: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
    text-decoration: none;
}

div.ntoolbar td[colspan] > a {
    width: 120px;
}

div.ntoolbar a {
    text-decoration: none;
}

div.ntoolbar a:link,
div.ntoolbar a:visited {
    color: #fff;
}

div.ntoolbar a.inactive {
    color: #fff;
}

div.ntoolbar span.available {
    display: inline-block;
}

div.ntoolbar span.unavailable {
    color: #555;
    display: inline-block;
}

div.ntoolbar a.hover,
div.ntoolbar a.active,
div.ntoolbar a:hover,
div.ntoolbar a:active {
    color: #b4b4b4;
}

/* バッジの位置決めのためだけ */
div#matome {
    display: inline-block;
    position: relative;
}

a > span.badge {
    display: inline-block;
    position: absolute;
    top: 0;
    right: 8px;
    width: auto;
    height: 12px;
    margin: 0;
    padding: 2px 5px;
    -webkit-border-radius: 8px;
    border-radius: 8px;
    background-color: #8C99B4;
    color: #fff;
    font-weight: bold;
    font-size: 12px;
    line-height: 12px;
}

a > span.badge.l2 {
    right: 5px;
}

a > span.badge.l3 {
    right: 3px;
}

a > span.badge.l4 {
    right: 0px;
}

a > span.badge.l0 {
    display: none;
}

a.hover > span.badge,
a.active > span.badge,
a:hover > span.badge,
a:active > span.badge {
    background-color: #36f;
}

div.ntoolbar a.inactive > img {
    filter: alpha(opacity=50);
    -ms-filter: "alpha(opacity=50)";
    -moz-opacity: 0.5;
    opacity: 0.5;
}

div.ntoolbar span.unavailable > img {
    filter: alpha(opacity=20);
    -ms-filter: "alpha(opacity=20)";
    -moz-opacity: 0.2;
    opacity: 0.2;
}

div.ntoolbar a.hover > img,
div.ntoolbar a.active > img,
div.ntoolbar a:hover > img,
div.ntoolbar a:active > img {
    filter: alpha(opacity=70);
    -ms-filter: "alpha(opacity=70)";
    -moz-opacity: 0.7;
    opacity: 0.7;
}

div.ntoolbar td > select {
    font-size: 16px;
}

div.ntoolbar td > span.large {
    font-size: 14px;
}

/* }}} */
/* {{{ new toolbar / matome */

div.mtoolbar {
    position: relative;
    min-width: 320px;
    margin: 0;
    padding: 0;
    background-color: #808080;
    color: #fff;
}

div.mtoolbar > .ttitle {
    margin: 0 56px 0 0;
    padding: 10px 0 10px 10px;
    font-weight: bold;
}

div.mtoolbar > h2.ttitle {
    font-size: 14px;
    line-height: 16px;
}

div.mtoolbar > div.ttitle {
    font-size: 12px;
    line-height: 14px;
}

div.mtoolbar_top {
    border-top: #808080 solid 2px;
}

div.mtoolbar_bottom {
    border-bottom: #808080 solid 1px;
}

div.mtoolbar_bottom > table {
    border-bottom: #808080 solid 1px;
}

div.mtoolbar > div.mover {
    position: absolute;
    right: 8px;
}

div.mtoolbar_top > div.mover {
    top: 0;
}

div.mtoolbar_bottom > div.mover {
    bottom: 2px;
}

div.mtoolbar > .ttitle a {
    text-decoration: underline;
}

div.mtoolbar > .ttitle > span.btitle {
    display: inline-block;
    font-weight: normal;
}

/* }}} */
/* {{{ Media Queries for res-popups */

@media screen and (max-width: 320px) {
    div.respop {
        padding-right: 0;
    }

    div.respop > div.res {
        border-right-width: 0 !important;
        -webkit-border-top-right-radius: 0;
        border-top-right-radius: 0;
        -webkit-border-right-left-radius: 0;
        border-bottom-right-radius: 0;
    }
}

/* }}} */
/* {{{ Media Queries for toolbar items */

@media screen and (min-width: 340px) {
    div.ntoolbar td > a {
        width: 64px;
    }
}

@media screen and (min-width: 360px) {
    div.ntoolbar td > a {
        width: 68px;
    }

    a > span.badge {
        right: 8px !important;
    }
}

@media screen and (min-width: 400px) {
    div.ntoolbar td > a {
        width: 76px;
    }

    a > span.badge {
        right: 16px !important;
    }
}

@media screen and (min-width: 480px) {
    div.ntoolbar td > a {
        width: 92px;
    }

    a > span.badge {
        right: 24px !important;
    }
}

.btn {
    background-color: #999999;
}

.maxwidth {
    width: 98%;
}

.popup {
    background-color: rgba(0, 0, 0, 0.8);
}

div.ntoolbar .disabled a:visited {
    color: #555;!important
}

.disabled > a > img{
    filter: alpha(opacity=20);
    -ms-filter: "alpha(opacity=20)";
    -moz-opacity: 0.2;
    opacity: 0.2;
}

/* }}} */


EOSTYLE;

// スタイルの上書き
if (isset($MYSTYLE) && is_array($MYSTYLE)) {
    if (isset($MYSTYLE['iphone'])) {
        disp_mystyle('iphone');
    }
}

/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: t
 * End:
 */
// vim: set syn=php fenc=cp932 ai noet ts=4 sw=4 sts=4 fdm=marker:
