<?php

/*
 * kako.5ch.net HTML to DAT converter
 * いわゆるWebスクレイピングというやつ
 */

function html2dat_kako5ch($html, $outputFile) {
    $doc = new DOMDocument();
    // HTMLパース時の警告を抑制
    libxml_use_internal_errors(true);

    // HTMLファイルを読み込み
    // DOMDocumentはShift_JISのパースに失敗してDOMが壊れることがあるため、
    // コンテンツを取得してエンコーディングをHTML-ENTITIESに変換してから読み込ませます
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'SJIS-win');
    $doc->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    // スレッドタイトルの取得
    $titleNode = $xpath->query('//*[@id="threadtitle"]')->item(0);
    $title = $titleNode ? trim($titleNode->textContent) : '';

    // レスの取得
    // class="post" を持ち、かつ id 属性を持つ div 要素を取得
    $posts = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " post ") and @id]');

    $lines = [];

    // あぼーん (UTF-8: \xE3\x81\x82\xE3\x81\xBC\xE3\x83\xBC\xE3\x82\x93)
    // ソースコードのエンコーディングに依存しないようエスケープシーケンスを使用
    $abornUtf8 = "\xE3\x81\x82\xE3\x81\xBC\xE3\x83\xBC\xE3\x82\x93";

    foreach ($posts as $post) {
        // 投稿IDを取得
        $postId = $post->getAttribute('id');

        // 1. 名前 (Name)
        $nameNode = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " postusername ")]', $post)->item(0);
        $name = '';
        $email = '';

        if ($nameNode) {
            // 名前の中にリンク(mailto)があるか確認
            $aNode = $xpath->query('.//a', $nameNode)->item(0);
            if ($aNode) {
                $href = $aNode->getAttribute('href');
                if (stripos($href, 'mailto:') === 0) {
                    $email = substr($href, 7);
                }
                // 名前欄のHTMLタグ(b, small等)を保持しつつテキストを取得
                $name = processNameNode($nameNode);
            } else {
                $name = processNameNode($nameNode);
            }
        }

        // 2. メール (Email) - 上記で取得済み

        // 3. 日付・ID・BE (Date ID BE)
        $dateNode = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " date ")]', $post)->item(0);
        $uidNode = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " uid ")]', $post)->item(0);
        // BEアイコン等は class="be" を含む span
        $beNode = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " be ")]', $post)->item(0);

        $dateStr = $dateNode ? trim($dateNode->textContent) : '';
        $uidStr = $uidNode ? trim($uidNode->textContent) : '';
        $beStr = '';
        if ($beNode) {
            $beAnchor = $xpath->query('.//a', $beNode)->item(0);
            $beText = trim($beNode->textContent);
            if ($beAnchor && preg_match('/(?:i=|user\/)(\d+)/', $beAnchor->getAttribute('href'), $matches)) {
                $beStr = 'BE:' . $matches[1] . '-' . $beText;
                // 表示テキストの先頭の?を除去 (例: ?2BP(5555) -> 2BP(5555))
                if (strpos($beText, '?') === 0) {
                    $beStr = 'BE:' . $matches[1] . '-' . substr($beText, 1);
                }
            } else {
                $beStr = $beText;
            }
        }

        // 日付が「NG」や「あぼーん」の場合はあぼーん扱いにする
        if ($dateStr === 'NG' || $dateStr === $abornUtf8) {
            $name = $abornUtf8;
            $email = $abornUtf8;
            $thirdField = $abornUtf8;
            $message = $abornUtf8;
        } else {
            // 3番目のフィールドを結合
            $thirdField = $dateStr;
            if ($uidStr !== '') {
                $thirdField .= ' ' . $uidStr;
            }
            if ($beStr !== '') {
                $thirdField .= ' ' . $beStr;
            }

            // 4. 本文 (Message)
            $contentNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " post-content ")]', $post)->item(0);
            $message = '';
            if ($contentNode) {
                $message = processNode($contentNode);
            }
        }

        // フィールドのサニタイズ（改行コードの削除）
        $name = str_replace(["\n", "\r"], '', $name);
        // レス番号が1001以上の場合のみ全角数字を半角に変換
        if ((int)$postId >= 1001) {
            $name = mb_convert_kana($name, 'n', 'UTF-8');
        }
        $email = str_replace(["\n", "\r"], '', $email);
        $thirdField = str_replace(["\n", "\r"], '', $thirdField);
        $message = str_replace(["\n", "\r"], '', $message);
        $message = trim($message);
        $title = str_replace(["\n", "\r"], '', $title);

        // DAT行の生成: 名前<>メール<>日付 ID BE<>本文<>スレタイ
        $line = "$name<>$email<>$thirdField<>$message<>";

        // 1行目のみ末尾にスレッドタイトルを付与
        if (empty($lines)) {
            $line .= $title;
        }

        $lines[] = $line;
    }

    // 全行を結合
    $outputData = implode("\n", $lines) . "\n";

    // 文字コードを Shift_JIS (CP932) に変換
    $outputData = mb_convert_encoding($outputData, 'SJIS-win', 'UTF-8');

    // 出力
    $res = FileCtl::file_write_contents($outputFile, $outputData, 0);
    return $res === false ? 0 : $res;
}

/**
 * ノードを再帰的に処理し、<br>を保持しつつテキストを抽出・エスケープする関数
 */
function processNode($node) {
    global $_conf;

    $text = '';
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            // テキストノードはエスケープする
            $text .= htmlspecialchars($child->textContent, ENT_QUOTES, 'UTF-8');
        } elseif ($child->nodeType === XML_ELEMENT_NODE) {
            $nodeName = strtolower($child->nodeName);
            if ($nodeName === 'br') {
                // <br>タグはDAT用の形式に変換
                $text .= ' <br> ';
            } elseif ($nodeName === 'img') {
                // お絵カキコリンクの実装
                $src = $child->getAttribute('src');
                if ($src) {
                    if (strpos($src, '//') === 0) {
                        $src = 'http:' . $src;
                    }
                    // ssspアイコンの変換
                    if (preg_match('#^https?://img\.5ch\.net/ico/(.+)$#', $src, $m)) {
                        $src = 'sssp://img.' . $_conf['2ch_domain'] . '/ico/' . $m[1];
                    }
                    $src = str_replace('8ch.net', $_conf['2ch_domain'], $src);
                    $text .= ' ' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . ' ';
                }
            } else {
                // その他のタグ（<a>など）はタグを除去して中身のテキストのみ抽出（再帰処理）
                $text .= processNode($child);
            }
        }
    }
    return $text;
}

/**
 * 名前欄のノードを処理し、特定のタグ(b, small等)を保持する関数
 */
function processNameNode($node) {
    $text = '';
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            $text .= htmlspecialchars($child->textContent, ENT_QUOTES, 'UTF-8');
        } elseif ($child->nodeType === XML_ELEMENT_NODE) {
            $tagName = strtolower($child->nodeName);
            if (in_array($tagName, ['b', 'small', 'i', 'font'])) {
                // 許可されたタグは再構築する
                $inner = processNameNode($child);
                $text .= "<{$tagName}>{$inner}</{$tagName}>";
            } else {
                // その他のタグ（<a>など）はタグを除去して中身のみ抽出
                $text .= processNameNode($child);
            }
        }
    }
    return $text;
}
