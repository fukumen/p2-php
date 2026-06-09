<?php
/**
 * Version.20081215
 *   ID本体とは別に、ID:の部分に別の背景色を適用
 *   色変換処理をcolorchange.phpにまとめた。
 *   その他、こまごまとした修正
 * Version.20081216
 *   HSV,HLSに加え、L*C*h表色系にも対応（ライブラリも修正）
 * Version.20081224 設定で変換前と変換後のカラーコードを表示できるようにした（デバッグ用？）
 * Version.20081228 色パラメータ設定を色空間別に個別化
 */

require_once __DIR__ . '/colorchange.inc.php';

// {{{ coloredIdStyle0()

/**
 * ID文字列からカラースタイルを算出して返す
 *
 * @param   string  $id     xxxxxxxxxx
 * @param   string  $count  ID出現数
 * @return  array(style1, style2 [, debug])
 */
function coloredIdStyle0($idstr, $id, $count)
{
    global $_conf, $STYLE;

    // IDから色の元を抽出
    $coldiv=360; // 色相環の分割数

    if (strpos($idstr, 'ID:') === 0) {
        $id8 = substr(str_pad($id, 8, 'A', STR_PAD_RIGHT), 0, 8);
        $arr1 = unpack('N', pack('H', 0) .
            base64_decode(str_replace('.', '+', substr($id8, 0, 4))));
        $arr2 = unpack('N', pack('H', 0) .
            base64_decode(str_replace('.', '+', substr($id8, 4, 4))));
    } else {
        if (strpos($id, ':') !== false) {
            // IPv6
            $s = str_replace(array(':', '*'), '', $id);
            $arr1 = array(1 => hexdec(str_pad(substr($s, 0, 6), 6, '0', STR_PAD_RIGHT)));
            $arr2 = array(1 => hexdec(str_pad(substr($s, 6, 6), 6, '0', STR_PAD_RIGHT)));
        } else {
            // IPv4
            $n = ip2long($id);
            $seed = ($n !== false) ? ($n & 0x7FFFFFFF) : 0;
            $arr1 = array(1 => ($seed >> 7) & 0xFFFFFF);
            $arr2 = array(1 => $seed & 0x7F);
        }
    }

    $color=$arr1[1] % $coldiv;
    $color2=$arr2[1] % $coldiv;


    // HSV（またはHLS）パラメータ設定
    // レス数が増えるほど、色が濃く、暗くなる

    // 色相H：値域0～360（角度）
    $h= $color*360/$coldiv;
    $h2=$color2*360/$coldiv;

    $colorMode=2;       // 0:HSV,1:HSL,2:L*C*h
    switch ($colorMode) {
    case 0:     // HSV色空間
        // 彩度S(HSV)：値域0（淡い）～1（濃い)
        $S=$count*0.05;
        if ($S>1) {$S=1;}

        // 明度V(HSV)：値域0（暗い）～1（明るい）
        $V=1   -$count*0.025;
        if ($V<0.1) {$V=0.1;}

        $color_param=array(
            array($h,$S,$V,$colorMode), // 背景色（ID本体）
            array($h2,1,0.6,$colorMode)  // 背景色（ID:部分）
        );
        break;
    case 1:  // HLS色空間
        // 輝度L(HLS)：値域0（黒）～0.5（純色）～1（白）
        $L=0.95   -$count*0.025;
        if ($L<0.2) {$L=0.2;}

        // 彩度S(HLS)：値域0（灰色）～1（純色）
        $S=$count*0.05;
        if ($S>1) {$S=1;}

        $color_param=array(
            array($h,$L,$S,$colorMode), // 背景色（ID本体）
            array($h2,0.6,0.5,$colorMode)  // 背景色（ID:部分）
        );
        break;
    case 2:  // L*C*h色空間
        // 明度L*(L*C*h)：値域0（黒）～50（純色）～100（白）
        $L=100   -$count*2.5;
        if ($L<20) {$L=20;}

        // 彩度C*(L*C*h)：値域0（灰色）～100（純色）
        $C=floor(40*sin(deg2rad(min($count,25)*180/50)) + 8);

        $color_param=array(
            array($L,$C,$h,$colorMode), // 背景色（ID本体）
            array(50,60,$h2,$colorMode)  // 背景色（ID:部分）
        );
        break;
    }

    // 色空間に関する参考資料
    // HSV,HLS色空間 http://tt.sakura.ne.jp/~hiropon/lecture/color.html
    // L*C*h表色系 http://konicaminolta.jp/instruments/colorknowledge/part1/08.html
    // L*a*b*表色系 http://konicaminolta.jp/instruments/colorknowledge/part1/07.html
    // RGBに変換
    $rgb=array();
    for($key=0;$key<count($color_param);$key++) {
        $colorMode=$color_param[$key][3];
        if ($colorMode==2) {
            array_push($rgb,LCh2RGB($color_param[$key]));
        } else {
            array_push($rgb,$colorMode
                ? HLS2RGB($color_param[$key])
                : HSV2RGB($color_param[$key])
            );
            //  unset($color_param[$key]);
        }
    }

    // CSSで色をつける
    $uline=($_conf['ktai'] || $STYLE['a_underline_none']==1) ? '' : "text-decoration:underline;";
    $bcolor=array();
    $LCh=array();
    for ($i=0;$i<count($rgb);$i++) {
        if (array_key_exists('type', $rgb) && $rgb['type']==='L*C*h') {
            $LCh[$i]=$color_param[$i];
        } else {
            $LCh[$i]=RGB2LCh($rgb[$i]);
           /*  if ($LCh[$i][0]<70 && $LCh[$i][0]>40) {
              $LCh[$i][0]-=30;
              $rgb[$i]=LCh2RGB($LCh[$i]);
           }*/
        }
        $colorcode=$rgb[$i]['color'];
        $bcolor[$i]="background-color:{$colorcode};";

        if      ($LCh[$i][0]>60) {$bcolor[$i].="color:#000;";}
        else //if ($LCh[$i][0]<40)
        {$bcolor[$i].="color:#fff;";}
    }

    if ($_conf['coloredid.rate.hissi.times'] > 0 && $count>=$_conf['coloredid.rate.hissi.times']) {     // 必死チェッカー発動
        $uline.="animation: p2-hissi-blink 1s step-end infinite; -webkit-animation: p2-hissi-blink 1s step-end infinite;";
    }

    return array(
        (isset($rgb[1]) ? "{$bcolor[1]}{$uline}" : ''),
        "{$bcolor[0]}{$uline}");
}

// }}}
// {{{ coloredIdStyle0()

/**
 * ワッチョイのハッシュからカラースタイルを算出して返す
 *
 * @param   string  $wid32  ワッチョイのハッシュ
 * @param   string  $count  ID出現数
 * @return  string  style
 */
function coloredWatchoiStyle0($wid32, $count)
{
    global $_conf, $STYLE;

    // IDから色の元を抽出
    $coldiv=360; // 色相環の分割数
    $color=hexdec($wid32) % $coldiv;

    // HSV（またはHLS）パラメータ設定
    // レス数が増えるほど、色が濃く、暗くなる

    // 色相H：値域0～360（角度）
    $h= $color*360/$coldiv;

    $colorMode=2;       // 0:HSV,1:HSL,2:L*C*h
    switch ($colorMode) {
    case 0:     // HSV色空間
        // 彩度S(HSV)：値域0（淡い）～1（濃い)
        $S=$count*0.05;
        if ($S>1) {$S=1;}

        // 明度V(HSV)：値域0（暗い）～1（明るい）
        $V=1   -$count*0.025;
        if ($L<0.1) {$L=0.1;}

        $color_param=array(
            array($h,$S,$V,$colorMode) // 背景色
        );
        break;
    case 1:  // HLS色空間
        // 輝度L(HLS)：値域0（黒）～0.5（純色）～1（白）
        $L=0.95   -$count*0.025;
        if ($L<0.1) {$L=0.1;}

        // 彩度S(HLS)：値域0（灰色）～1（純色）
        $S=$count*0.05;
        if ($S>1) {$S=1;}

        $color_param=array(
            array($h,$L,$S,$colorMode) // 背景色
        );
        break;
    case 2:  // L*C*h色空間
        // 明度L*(L*C*h)：値域0（黒）～50（純色）～100（白）
        $L=100   -$count*2.5;
        if ($L<10) {$L=10;}

        // 彩度C*(L*C*h)：値域0（灰色）～100（純色）
        $C=floor(40*sin(deg2rad(min($count,25)*180/50)) + 8);

        $color_param=array(
            array($L,$C,$h,$colorMode) // 背景色
        );
        break;
    }

    // 色空間に関する参考資料
    // HSV,HLS色空間 http://tt.sakura.ne.jp/~hiropon/lecture/color.html
    // L*C*h表色系 http://konicaminolta.jp/instruments/colorknowledge/part1/08.html
    // L*a*b*表色系 http://konicaminolta.jp/instruments/colorknowledge/part1/07.html
    // RGBに変換
    $rgb=array();
    for($key=0;$key<count($color_param);$key++) {
        $colorMode=$color_param[$key][3];
        if ($colorMode==2) {
            array_push($rgb,LCh2RGB($color_param[$key]));
        } else {
            array_push($rgb,$colorMode
                ? HLS2RGB($color_param[$key])
                : HSV2RGB($color_param[$key])
            );
            //  unset($color_param[$key]);
        }
    }

    // CSSで色をつける
    $uline=($_conf['ktai'] || $STYLE['a_underline_none']==1) ? '' : "text-decoration:underline;";
    $bcolor=array();
    $LCh=array();
    for ($i=0;$i<count($rgb);$i++) {
        if (array_key_exists('type', $rgb) && $rgb['type']==='L*C*h') {
            $LCh[$i]=$color_param[$i];
        } else {
            $LCh[$i]=RGB2LCh($rgb[$i]);
           /*  if ($LCh[$i][0]<70 && $LCh[$i][0]>40) {
              $LCh[$i][0]-=30;
              $rgb[$i]=LCh2RGB($LCh[$i]);
           }*/
        }
        $colorcode=$rgb[$i]['color'];
        $bcolor[$i]="background-color:{$colorcode};";

        if      ($LCh[$i][0]>60) {$bcolor[$i].="color:#000;";}
        else //if ($LCh[$i][0]<40)
        {$bcolor[$i].="color:#fff;";}
    }

    if ($_conf['coloredid.rate.hissi.times'] > 0 && $count>=$_conf['coloredid.rate.hissi.times']) {     // 必死チェッカー発動
        $uline.="animation: p2-hissi-blink 1s step-end infinite; -webkit-animation: p2-hissi-blink 1s step-end infinite;";
    }

    return "{$bcolor[0]}{$uline}";
}

// }}}
