<?php
/**
 * rep2 - markdown を 表示
 */

require_once __DIR__ . '/../init.php';

$_login->authorize();

if (!isset($_GET['file'])) {
    p2die('file is not specified');
}

$file = $_GET['file'];

$readable_files = array(
    'README.md',
    'doc/README-donguri.md',
    'doc/README-lockout.md',
    'doc/README-login5ch.md',
    'doc/README-SECRET_KEY.md'
);

$githubBase = 'https://github.com/fukumen/p2-php/blob/master/';

// 絵文字を使用するときはここの定義に追加してください
$emoji_replacements = array(
    ':warning:' => '\u26A0\uFE0F'
);

if ($readable_files && $file && (!in_array($file, $readable_files))) {
    p2die("error: cannot view '{$file}'");
}

$filename = basename($file);
$ptitle = $filename;

$cont = FileCtl::file_read_contents(P2_BASE_DIR . DIRECTORY_SEPARATOR . $file);
if ($cont === false) {
    p2die("error: file not found '{$file}'");
}

if (strncmp($cont, "\xEF\xBB\xBF", 3) === 0) {
    $cont = substr($cont, 3);
}

$cont_json = json_encode($cont, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$file_dir = dirname($file);
$base_dir_js = ($file_dir === '.' || $file_dir === '/') ? '' : $file_dir . '/';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="Shift_JIS">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    <title><?php echo p2h($ptitle); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-markdown-css/5.8.1/github-markdown.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
        }
        .markdown-body {
            box-sizing: border-box;
            min-width: 200px;
            padding: 45px;
        }
        @media (max-width: 767px) {
            .markdown-body {
                padding: 15px;
            }
        }
    </style>
</head>
<body onload="top.document.title=self.document.title;">
    <article class="markdown-body" id="content">
    </article>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/16.3.0/lib/marked.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked-alert@2.1.2/dist/index.umd.min.js"></script>
    <script>
        const rawContent = <?php echo $cont_json; ?>;
        const baseDir = '<?php echo $base_dir_js; ?>';
        const extWinAttr = '<?php echo $_conf['ext_win_target_at']; ?>';
        const githubBase = '<?php echo $githubBase; ?>';

        const renderer = {
            link({ href, title, text }) {
                const isAbsolute = href && href.match(/^https?:\/\//);
                
                if (isAbsolute) {
                    const titleAttr = title ? ` title="${title}"` : '';
                    return `<a href="${href}"${titleAttr}${extWinAttr}>${text}</a>`;
                }

                if (href) {
                    // 相対パスの解決
                    let resolvedPath = href;
                    if (!href.startsWith('/') && !href.match(/^https?:\/\//)) {
                        resolvedPath = baseDir + href;
                    }
                    // 先頭の ./ 等を掃除
                    resolvedPath = resolvedPath.replace(/^\.\//, '');
                    
                    if (resolvedPath.endsWith('.md')) {
                        return `<a href="viewmd.php?file=${resolvedPath}">${text}</a>`;
                    }
                    if (resolvedPath.endsWith('.txt')) {
                        return `<a href="viewtxt.php?file=${resolvedPath}">${text}</a>`;
                    }
                    return `<a href="${githubBase}${resolvedPath}"${extWinAttr}>${text}</a>`;
                }
                return false;
            }
        };

        const emojiReplacements = {
<?php
        $emoji_lines = array();
        foreach ($emoji_replacements as $key => $val) {
            $emoji_lines[] = '            ' . json_encode($key) . ': \'' . $val . '\'';
        }
        echo implode(",\n", $emoji_lines) . "\n";
?>
        };

        let contentWithEmoji = rawContent;
        for (const key in emojiReplacements) {
            contentWithEmoji = contentWithEmoji.split(key).join(emojiReplacements[key]);
        }

        const alertExt = (typeof markedAlert === 'function') ? markedAlert() : (markedAlert && markedAlert.markedAlert && markedAlert.markedAlert());
        if (alertExt) marked.use(alertExt);
        marked.use({ renderer });

        document.getElementById('content').innerHTML = marked.parse(contentWithEmoji);
    </script>
</body>
</html>

<?php
/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:
