<?php
// Homebrew formula の生成スクリプト

$usage = '使い方: php generate-formula.php <出力先> [VERSION] [FILE_ARM64] [SHA_ARM64] [FILE_X86_64] [SHA_X86_64] [LOCAL_TARBALL_URL]';
if ($argc < 2) {
    fwrite(STDERR, $usage . PHP_EOL);
    exit(1);
}

$scriptDir = __DIR__;
$templateFile = $scriptDir . '/homebrew-formula.rb.template';
if (!is_file($templateFile)) {
    fwrite(STDERR, "テンプレートが見つかりません: {$templateFile}" . PHP_EOL);
    exit(1);
}

$buildInfoFile = $scriptDir . '/../dist/build_info';

$out = $argv[1];
$version = $argv[2] ?? null;
$fileArm64 = $argv[3] ?? '@@FILE_ARM64@@';
$shaArm64 = $argv[4] ?? '@@SHA_ARM64@@';
$fileX8664 = $argv[5] ?? '@@FILE_X86_64@@';
$shaX8664 = $argv[6] ?? '@@SHA_X86_64@@';
$localUrl = $argv[7] ?? null;

$injectedFiles = [
    '@@BUILD_INFO@@' => $buildInfoFile,
    '@@PHP_LOCAL_INI@@' => $scriptDir . '/php-local.ini',
    '@@PHP_FPM_LOCAL_CONF@@' => $scriptDir . '/php-fpm-local.conf',
    '@@CADDYFILE@@' => $scriptDir . '/Caddyfile',
    '@@DEFAULT@@' => $scriptDir . '/../conf/default',
    '@@FPMCONF@@' => $scriptDir . '/rep2-php-fpm.conf',
];
foreach ($injectedFiles as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "注入元ファイルが見つかりません: {$file}" . PHP_EOL);
        exit(1);
    }
}

$template = file_get_contents($templateFile);
if ($template === false) {
    fwrite(STDERR, "テンプレートを読めません: {$templateFile}" . PHP_EOL);
    exit(1);
}

if ($localUrl !== null && $localUrl !== '') {
    $search = [
        'url "https://github.com/fukumen/p2-php/releases/download/latest/@@FILE_ARM64@@"',
        'url "https://github.com/fukumen/p2-php/releases/download/latest/@@FILE_X86_64@@"',
    ];
    $template = str_replace($search, ['url "' . $localUrl . '"', 'url "' . $localUrl . '"'], $template);
}

if ($version !== null && $version !== '') {
    $template = str_replace('@@VERSION@@', $version, $template);
}
$template = str_replace('@@FILE_ARM64@@', $fileArm64, $template);
$template = str_replace('@@SHA_ARM64@@', $shaArm64, $template);
$template = str_replace('@@FILE_X86_64@@', $fileX8664, $template);
$template = str_replace('@@SHA_X86_64@@', $shaX8664, $template);

foreach ($injectedFiles as $marker => $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        fwrite(STDERR, "注入元ファイルを読めません: {$file}" . PHP_EOL);
        exit(1);
    }
    $template = str_replace('    ' . $marker, rtrim($content, "\n"), $template);
}

if (file_put_contents($out, $template) === false) {
    fwrite(STDERR, "出力ファイルを書き込めません: {$out}" . PHP_EOL);
    exit(1);
}
