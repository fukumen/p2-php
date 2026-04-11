<?php
/**
 * rep2 - スマホモード共通ダークモードスタイル
 */

if ($_conf['mobile_dark_mode'] == 1) return; // 昼固定なら何も出力しない

if ($_conf['mobile_dark_mode'] == 0) echo "@media (prefers-color-scheme: dark) {\n";

echo <<<EODARK
    /* General / iUI Base */
    body { background: #121212 !important; color: #e0e0e0 !important; }
    body > ul > li { border-bottom: 1px solid #333 !important; }
    body > ul > li.group {
        background: #222 !important;
        color: #bbb !important;
        border-top: 1px solid #444 !important;
        border-bottom: 1px solid #111 !important;
        text-shadow: none !important;
    }
    body > .panel { background: #1a1a1a !important; }
    .panel > fieldset { background: #2c2c2c !important; border-color: #444 !important; }
    .panel > h2 { color: #aaa !important; text-shadow: none !important; }
    .row { border-bottom: 1px solid #444 !important; }
    input:not([type|="submit"]):not([type|="radio"]):not([type|="checkbox"]) {
        background: #222 !important; color: #e0e0e0 !important; border: 1px solid #444 !important;
    }
    .dialog > fieldset { background: #222 !important; border-top: 1px solid #444 !important; }
    .dialog > fieldset > h1 { color: #e0e0e0 !important; text-shadow: none !important; }
    .dialog > fieldset > label { color: #aaa !important; }
    li.info-pop { background-color: #222 !important; color: #e0e0e0 !important; }
    div.info-pop-buttons input[type="button"] { color: #e0e0e0 !important; }
    table.info-pop-fav td { color: #e0e0e0 !important; }
    a[target="_replace"] { color: #6699ff !important; background-color: #121212 !important; }

    /* toggle-only.css */
    .toggle { background-color: #333 !important; border-color: #555 !important; }
    .toggleOff { color: #888 !important; }
    .thumb { background-color: #555 !important; border-color: #666 !important; }

    /* Links */
    a:link, a:visited { color: #e0e0e0 !important; }
    
    /* iUI Menu List Links & Subject List Titles (specific override) */
    body > ul > li > a:link,
    body > ul > li > a:visited,
    ul.subject > li > a:link,
    ul.subject > li > a:visited {
        color: #e0e0e0 !important;
    }
    ul.subject > li > a, ul.subject > li > a:link, ul.subject > li > a:visited { background-color: #1a1a1a !important; }

    /* Subject / Read List UI */
    ul.subject > li { border-bottom: 1px solid #333 !important; }
    ul.subject > li:first-child { border-top: 1px solid #333 !important; }
    ul.subject > li > a > span.ita, ul.subject > li > a > span.num, ul.subject > li > a > span.count, ul.subject > li > a > span.score { color: #888 !important; }
    ul.subject > li > a > span.unread { background-color: #444 !important; color: #e0e0e0 !important; }
    ul.subject > li > a > span.unread.new { background-color: #335588 !important; color: #fff !important; }
    div.thread_title { background-color: #333 !important; color: #e0e0e0 !important; }
    h3 > font[color], div > font[color] > b { color: #e0e0e0 !important; }
    div.res:not(.aborned) { border-top: 1px solid #333 !important; }
    div.res > div.res-header { color: #6699ff !important; }
    div.res-header > span.no.newres { color: #ff8844 !important; }
    div.res-header > span.no:before, div.res-header > span.no:after { color: #888 !important; }

    /* Dialogs / Popups */
    div.respop > div.res {
        background-color: rgba(30, 30, 30, 0.95) !important;
        border: rgba(80, 80, 80, 0.8) solid 1px !important;
    }
    div.respop > div.res > div.res-header { border-bottom-color: rgba(80, 80, 80, 0.8) !important; }
    div.respop > div.res > div.respop-back { border-top: rgba(80, 80, 80, 0.8) solid 1px !important; }
    div.respop > div.res > div.respop-back > a { color: #e0e0e0 !important; }
    
    div.popup-dialog, .popup { background-color: rgba(30, 30, 30, 0.9) !important; color: #e0e0e0 !important; }
    div.popup-dialog > div.popup-dialog-buttons > input[type="button"] { color: #e0e0e0 !important; }

    /* Toolbars */
    div.ntoolbar, div.ntoolbar#pager { background-color: #222 !important; color: #e0e0e0 !important; border-color: #444 !important; }
    div.ntoolbar td { border-top: 1px solid #444 !important; }
    div.ntoolbar > form, div.ntoolbar > div > form, div.ntoolbar > div.hits { border-top: 1px solid #444 !important; }
    div.ntoolbar > div.hits { background-color: #333 !important; color: #e0e0e0 !important; }
    div.info { background-color: #2a2a40 !important; color: #e0e0e0 !important; border-top: 1px solid #444 !important; }
    div.mtoolbar { background-color: #222 !important; color: #e0e0e0 !important; }
    div.mtoolbar_top { border-top: 2px solid #444 !important; }
    div.mtoolbar_bottom, div.mtoolbar_bottom > table { border-bottom: 1px solid #444 !important; }
    
    /* Buttons / Links styled as buttons */
    .btn { background-color: #444 !important; color: #e0e0e0 !important; }
    a.button, div.navi a[href]:not(.nobutton), div.pager a[href]:not(.nobutton), div.toolbar a[href]:not(.nobutton) {
        background: #333 !important; color: #e0e0e0 !important; border-color: #555 !important;
    }
    
    /* Form elements */
    fieldset { border: 1px solid #444 !important; }
    legend { color: #e0e0e0 !important; }
    input, select, textarea, button {
        background-color: #222 !important; color: #e0e0e0 !important; border: 1px solid #444 !important;
    }
    input[type="checkbox"], input[type="radio"] { background-color: initial !important; }
    
    /* rules */
    hr { border-color: #444 !important; background-color: #444 !important; }

    /* iv2_iphone.css / IC2 Viewer */
    body > h1:first-child { background-color: #333 !important; }
    div#iv2-edit-form, div#iv2-remove-form { border-bottom: 1px solid #444 !important; }
    div#iv2-edit-form > h3, div#iv2-remove-form > h3 { background-color: #333 !important; }
    div#iv2-edit-form > div, div#iv2-remove-form > div { background-color: #1a1a1a !important; }
    table#iv2-images { background-color: #444 !important; }
    tr.iv2-image > td.iv2-image-thumb { background-color: #000 !important; }
    tr.iv2-image > td.iv2-image-info { background-color: #1a1a1a !important; color: #e0e0e0 !important; }
    tr.iv2-image > td.iv2-image-info > span.iv2-image-size { color: #aaa !important; }
EODARK;

if ($_conf['mobile_dark_mode'] == 0) echo "}\n";
