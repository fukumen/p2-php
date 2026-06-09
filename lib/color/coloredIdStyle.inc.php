<?php

require_once __DIR__ . '/colorchange.inc.php';

/**
 * Merged from http://jiyuwiki.com/index.php?cmd=read&page=rep2%A4%C7%A3%C9%A3%C4%A4%CE%C7%D8%B7%CA%BF%A7%CA%D1%B9%B9&alias%5B%5D=pukiwiki%B4%D8%CF%A2
 *
 * @return  string
 */
function coloredIdStyle($idstr, $id, $count=0)
{
    static $idcount = array();
    static $idstyles = array();
    static $id_color_used= array() ;

    global $_conf, $STYLE;

    if ($count >= 2) {
        //[$id] >= 2　ココの数字でスレに何個以上同じＩＤが出た時に背景色を変えるか決まる
        if (isset($idstyles[$id])) {
            return $idstyles[$id];
        } else {
            // IDから色の元を抽出

            $coldiv=64; // 色相環の分割数
            if (strpos($idstr, 'ID:') === 0) { // IDが使える
                $rev_id=strrev(str_replace('.', '+', substr($id, 0, 8)));
                $raw = base64_decode($rev_id);		// 8文字をバイナリデータ6文字分に変換
                $id_hex = unpack('H12', substr($raw, 0, 6));	// バイナリデータを16進文字列に変換
                $id_bin=base_convert($id_hex[1],16,2);	// さらに2進文字列に変換
                while ($id_bin) {
                    $arr[]=base_convert(substr($id_bin,-6),2,10);
                    $id_bin=substr($id_bin,0,-6);
                }

                $colors[0]=$arr[0];// % $coldiv;

                if (!isset($id_color_used[$colors[0]])) {
                    $id_color_used[$colors[0]] = 0;
                }
                if ($id_color_used[$colors[0]]++) {
                    $colors[1]=$colors[0]+($id_color_used[$colors[0]]-1)+1;
                }
            } else { //シベリア板タイプ
                if (strpos($id, ':') !== false) {
                    // IPv6
                    $s = str_replace(array(':', '*'), '', $id);
                    $seed = hexdec(substr($s, 0, 8)) & 0x7FFFFFFF;
                } else {
                    // IPv4
                    $n = ip2long($id);
                    $seed = ($n !== false) ? ($n & 0x7FFFFFFF) : 0;
                }
                $colors[0] = $seed % $coldiv;

                if (!isset($id_color_used[$colors[0]])) {
                    $id_color_used[$colors[0]] = 0;
                }
                if ($id_color_used[$colors[0]]++) {
                    $colors[1]=$colors[0]+($id_color_used[$colors[0]]-1)+1;
                }
            }
            $color_param=array();
            // HLS色空間
            // 色相H：値域0～360（角度）
            // 輝度L(HLS)：値域0（黒）～0.5（純色）～1（白）
            // 彩度S(HLS)：値域0（灰色）～1（純色）
            foreach ($colors as $key => $color) {
                //		    		var_dump(array(/*$raw,$id_hex,$arr,$col,*/$id_top,$c1,$c2));echo "<br>";
                $color_param[$key]=array();
                $angle=deg2rad($color*180/$coldiv);

                $color_param[$key]['H']=$color*360*4/$coldiv;
                while ($color_param[$key]['H']>360) {$color_param[$key]['H']-=360;}

                $color_param[$key]['L']=0.22+sin($angle)*0.08;
                $color_param[$key]['S']=0.4+sin($angle)*0.1;

                // RGBに変換
                $color_param[$key]=HLS2RGB($color_param[$key]);
                $color_param[$key]['Y']=(
                                         $color_param[$key]['R']*299+
                                         $color_param[$key]['G']*587+
                                         $color_param[$key]['B']*114
                                        )/1000;

            }

            // CSSで色をつける
            $uline=($_conf['ktai'] || $STYLE['a_underline_none']==1) ? '' : "text-decoration:underline;";
            if ($count>=25 ) {     // 必死チェッカー発動
                $uline.="animation: p2-hissi-blink 1s step-end infinite; -webkit-animation: p2-hissi-blink 1s step-end infinite;";
            }
            $opacity=''; // "opacity:{$alpha};";
            foreach ($color_param as $area => $param) {
                $r=(int)$color_param[$area]['R'];
                $g=(int)$color_param[$area]['G'];
                $b=(int)$color_param[$area]['B'];
                $bcolor[$area]="background-color:rgb({$r},{$g},{$b});";

                // 背景色によって文字色を変える
              $y1=158;
              $y2=185;
                if ($param['Y']>=$y1) {
                    $y=($param['Y']-($param['Y']>=$y2 ? $y2 : $y1))/$param['Y'];

                        $r=(int)($r*$y);
                        $g=(int)($g*$y);
                        $b=(int)($b*$y);
                        $bcolor[$area].="color:rgb({$r},{$g},{$b});";
                } else {
                    $y1=140;
                    $y2=160;
                    if ($param['Y']<=255-$y1) {
                        $y=($param['Y']<=255-$y2 ? $y2 : $y1)/(255-$param['Y']);

                        $r+=(int)((255-$r)*$y);
                        $g+=(int)((255-$g)*$y);
                        $b+=(int)((255-$b)*$y);
                        $bcolor[$area].="color:rgb({$r},{$g},{$b});";
                    } else {
                        $bcolor[$area].="color:#fff;";
                    }
                }
            }
//            var_dump(array('id'=>$id,'bcolor'=>$bcolor));echo "<br>";
            $idstyles[$id] = $bcolor;
            /*array(
                (isset($rgb[1]) ? "{$bcolor[1]}{$border}{$uline}" : ''),
                "{$bcolor[0]}{$border}{$uline}");
*/

        }
    }
//    var_dump(array('idstyles'=>$idstyles[$id]));echo "<br>";
    return isset($idstyles[$id]) ? $idstyles[$id] : array();
}

function coloredWatchoiStyle($wid32, $count)
{
    static $watchoistyles = array();

    global $_conf, $STYLE;

    if ($count < 2) {
        return '';
    }

    if (isset($watchoistyles[$wid32])) {
        return $watchoistyles[$wid32];
    }

    $coldiv = 64;
    $color = hexdec($wid32) % $coldiv;

    $color_param = array();
    // HLS色空間
    // 色相H：値域0～360（角度）
    // 輝度L(HLS)：値域0（黒）～0.5（純色）～1（白）
    // 彩度S(HLS)：値域0（灰色）～1（純色）
    $angle = deg2rad($color * 180 / $coldiv);

    $color_param['H'] = $color * 360 * 4 / $coldiv;
    while ($color_param['H'] > 360) {
        $color_param['H'] -= 360;
    }

    $color_param['L'] = 0.22 + sin($angle) * 0.08;
    $color_param['S'] = 0.4 + sin($angle) * 0.1;

    // RGBに変換
    $rgb = HLS2RGB($color_param);
    $y_val = ($rgb['R'] * 299 + $rgb['G'] * 587 + $rgb['B'] * 114) / 1000;

    // CSSで色をつける
    $uline = ($_conf['ktai'] || $STYLE['a_underline_none'] == 1) ? '' : "text-decoration:underline;";
    if ($count >= 25) {     // 必死チェッカー発動
        $uline .= "animation: p2-hissi-blink 1s step-end infinite; -webkit-animation: p2-hissi-blink 1s step-end infinite;";
    }

    $r = (int)$rgb['R'];
    $g = (int)$rgb['G'];
    $b = (int)$rgb['B'];
    $bcolor = "background-color:rgb({$r},{$g},{$b});";

    // 背景色によって文字色を変える
    $y1 = 158;
    $y2 = 185;
    if ($y_val >= $y1) {
        $y = ($y_val - ($y_val >= $y2 ? $y2 : $y1)) / $y_val;
        $r = (int)($r * $y);
        $g = (int)($g * $y);
        $b = (int)($b * $y);
        $bcolor .= "color:rgb({$r},{$g},{$b});";
    } else {
        $y1 = 140;
        $y2 = 160;
        if ($y_val <= 255 - $y1) {
            $y = ($y_val <= 255 - $y2 ? $y2 : $y1) / (255 - $y_val);
            $r += (int)((255 - $r) * $y);
            $g += (int)((255 - $g) * $y);
            $b += (int)((255 - $b) * $y);
            $bcolor .= "color:rgb({$r},{$g},{$b});";
        } else {
            $bcolor .= "color:#fff;";
        }
    }

    $style = "{$bcolor}{$uline}";
    $watchoistyles[$wid32] = $style;

    return $style;
}
