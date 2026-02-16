<?php
/**
 * rep2 - Ajax
 * ファイルアップローダー
 */

require_once __DIR__ . '/../init.php';

// {{{ P2UploaderInterface

interface P2UploaderInterface
{
    /**
     * @param string $localPath
     * @param string $filename
     *
     * @return string URL
     */
    public function upload($localPath, $filename);
}

// }}}
// {{{ P2DropboxUploader

class P2DropboxUploader implements P2UploaderInterface
{
    /**
     * @var Dropbox\Client
     */
    private $client;

    /**
     * @var string
     */
    private $prefix;

    /**
     * @param string $authJsonFile
     * @param string $clientIdentifier
     * @param string $prefix
     */
    public function __construct($authJsonFile, $clientIdentifier, $prefix)
    {
        $pathError = Dropbox\Path::findError($prefix . 'check');
        if ($pathError !== null) {
            throw new RuntimeException("Dropbox upload prefix error: {$pathError}");
        }

        list($appInfo, $accessToken) = Dropbox\AuthInfo::loadFromJsonFile($authJsonFile);
        $config = new Dropbox\Config($appInfo, $clientIdentifier);
        $this->client = new Dropbox\Client($config, $accessToken);
        $this->prefix = sprintf('%s%x', $prefix, time());
    }

    /**
     * @param string $localPath
     * @param string $filename
     *
     * @return string URL
     */
    public function upload($localPath, $filename)
    {
        $size = @getimagesize($localPath);
        if ($size) {
            $extension = image_type_to_extension($size[2]);
        } else {
            $extension = strrchr($filename, '.');
        }

        $metadata = $this->client->uploadFile(
            $this->prefix . hash_file('crc32b', $localPath) . $extension,
            Dropbox\WriteMode::add(),
            fopen($localPath, 'rb'),
            filesize($localPath)
        );

        if (is_array($metadata) && isset($metadata['path'])) {
            //return $this->client->createShareableLink($metadata['path']);
            $data = $this->client->createTemporaryDirectLink($metadata['path']);
            if (is_array($data)) {
                return $data[0];
            }
        }

        return null;
    }
}

// }}}
// {{{ P2ImgurUploader

class P2ImgurUploader implements P2UploaderInterface
{
    /**
     * @var string
     */
    private $client_id;

    /**
     * @param string $authJsonFile
     * @param string $clientIdentifier
     * @param string $prefix
     */
    public function __construct($client_id)
    {
        $this->client_id = $client_id;
    }

    /**
     * @param string $localPath
     * @param string $filename
     *
     * @return string URL
     */
    public function upload($localPath, $filename)
    {
    	$data = fread(fopen($localPath, "rb"), filesize($localPath));
    	$imgur_api = 'https://api.imgur.com/3/image';

    	$req = P2Commun::createHTTPRequest ($imgur_api,HTTP_Request2::METHOD_POST);

        // ヘッダ
        $req->setHeader('Authorization', 'Client-ID ' . $this->client_id);
        
        // postする内容
        $req->addPostParameter('image', base64_encode($data));

        $response = P2Commun::getHTTPResponse($req);
        
        if($response->getStatus() == 200) {
            $result = json_decode($response->getBody());
            $image_url = $result->data->link;
            $delete_url = 'https://imgur.com/delete/' .$result->data->deletehash;
            return $image_url;
        }

        throw new RuntimeException("Imgur Upload Error: " . $response->getStatus() . " " . $response->getBody());
    }
}

// }}}
// {{{ P2ImgbbUploader

class P2ImgbbUploader implements P2UploaderInterface
{
    /**
     * @var string
     */
    private $api_key;

    /**
     * @param string $api_key
     */
    public function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * @param string $localPath
     * @param string $filename
     *
     * @return string URL
     */
    public function upload($localPath, $filename)
    {
        $data = fread(fopen($localPath, "rb"), filesize($localPath));
        $imgbb_api = 'https://api.imgbb.com/1/upload';

        $req = P2Commun::createHTTPRequest($imgbb_api, HTTP_Request2::METHOD_POST);

        $req->addPostParameter('key', $this->api_key);
        $req->addPostParameter('image', base64_encode($data));

        $response = P2Commun::getHTTPResponse($req);

        if ($response->getStatus() == 200) {
            $result = json_decode($response->getBody());
            if (isset($result->data->url)) {
                return $result->data->url;
            }
        }

        throw new RuntimeException("Imgbb Upload Error: " . $response->getStatus() . " " . $response->getBody());
    }
}

// }}}
// {{{ P2CatboxUploader

class P2CatboxUploader implements P2UploaderInterface
{
    /**
     * @var string
     */
    private $userhash;

    /**
     * @param string $userhash
     */
    public function __construct($userhash = '')
    {
        $this->userhash = $userhash;
    }

    /**
     * @param string $localPath
     * @param string $filename
     *
     * @return string URL
     */
    public function upload($localPath, $filename)
    {
        $url = 'https://catbox.moe/user/api.php';
        $req = P2Commun::createHTTPRequest($url, HTTP_Request2::METHOD_POST);

        $req->addPostParameter('reqtype', 'fileupload');
        if ($this->userhash !== '') {
            $req->addPostParameter('userhash', $this->userhash);
        }
        $req->addUpload('fileToUpload', $localPath, $filename);

        $response = P2Commun::getHTTPResponse($req);

        if ($response->getStatus() == 200) {
            return trim($response->getBody());
        }
        throw new RuntimeException("Catbox Upload Error: " . $response->getStatus() . " " . $response->getBody());
    }
}

// }}}
// {{{ P2LitterboxUploader

class P2LitterboxUploader implements P2UploaderInterface
{
    /**
     * @var string
     */
    private $time;

    /**
     * @param string $time
     */
    public function __construct($time)
    {
        $this->time = $time;
    }

    /**
     * @param string $localPath
     * @param string $filename
     *
     * @return string URL
     */
    public function upload($localPath, $filename)
    {
        $url = 'https://litterbox.catbox.moe/resources/internals/api.php';
        $req = P2Commun::createHTTPRequest($url, HTTP_Request2::METHOD_POST);

        $req->addPostParameter('reqtype', 'fileupload');
        $req->addPostParameter('time', $this->time);
        $req->addUpload('fileToUpload', $localPath, $filename);

        $response = P2Commun::getHTTPResponse($req);

        if ($response->getStatus() == 200) {
            return trim($response->getBody());
        }
        throw new RuntimeException("Litterbox Upload Error: " . $response->getStatus() . " " . $response->getBody());
    }
}

// }}}
// {{{ handle_uploaded_file()

/**
 * @param P2UploaderInterface $uploader
 * @param array $file
 *
 * @return string URL
 */
function handle_uploaded_file(P2UploaderInterface $uploader, array $file)
{
    if ($file['error'] !== UPLOAD_ERR_OK
        || !file_exists($file['tmp_name'])
        || filesize($file['tmp_name']) !== $file['size']) {
        throw new RuntimeException("failed to upload file '{$file['name']}'.");
    }

    return $uploader->upload($file['tmp_name'], $file['name']);
}

// }}}
// {{{ メインルーチン

$result = array('urls' => array());
$error = '';

ob_start();

// {{{ アップローダーをセットアップ

try {
    if (isset($_GET['mode'])) {
        if ($_GET['mode'] === 'imgur') {
            $client_id = isset($_conf['upload_imgur_clientid']) ? $_conf['upload_imgur_clientid'] : '';
            $uploader = new P2ImgurUploader($client_id);
        } elseif ($_GET['mode'] === 'imgbb') {
            $api_key = isset($_conf['upload_imgbb_apikey']) ? $_conf['upload_imgbb_apikey'] : '';
            $uploader = new P2ImgbbUploader($api_key);
        } elseif ($_GET['mode'] === 'catbox') {
            $userhash = isset($_conf['upload_catbox_userhash']) ? $_conf['upload_catbox_userhash'] : '';
            $uploader = new P2CatboxUploader($userhash);
        } elseif ($_GET['mode'] === 'litterbox') {
            $time = isset($_conf['upload_litterbox_time']) ? $_conf['upload_litterbox_time'] : '';
            $uploader = new P2LitterboxUploader($time);
        } else {
            $uploader = null;
        }
    } else {
        $uploader = null;
    }
} catch (Exception $e) {
    $uploader = null;
    $error .= $e->getMessage() . "\n";
}

// }}}

if (!$uploader) {
    // アップローダーのセットアップ失敗
} elseif (!isset($_GET['token'], $_SESSION['upload_token'])
    || $_GET['token'] !== $_SESSION['upload_token']) {
    // CSRFトークン不一致
    $result['error'] = "invalid token.\n";
} elseif (!isset($_FILES['upload']['name'])) {
    // ファイルなし
    $result['error'] = "no files.\n";
} elseif (is_array($_FILES['upload']['name'])) {
    // {{{ マルチファイルアップロード

    $fileCount = count($_FILES['upload']['name']);
    $keys = array('name', 'tmp_name', 'type', 'error', 'size');
    for ($index = 0; $index < $fileCount; $index++) {
        $file = array();
        foreach ($keys as $key) {
            if (isset($_FILES['upload'][$key][$index])) {
                $file[$key] = $_FILES['upload'][$key][$index];
            } else {
                $error .= "file #{$index} is not valid.\n";
                continue;
            }
        }
        try {
            $url = handle_uploaded_file($uploader, $file);
            if ($url) {
                $result['urls'][] = $url;
            }
        } catch (Exception $e) {
            $error .= $e->getMessage() . "\n";
        }
    }

    // }}}
} else {
    // {{{ 単体ファイルアップロード

    try {
        $url = handle_uploaded_file($uploader, $_FILES['upload']);
        if ($url) {
            $result['urls'][] = $url;
        }
    } catch (Exception $e) {
        $error .= $e->getMessage() . "\n";
    }

    // }}}
}

if (strlen($error)) {
    $result['error'] = rtrim($error);
}
$error .= ob_get_clean();
if (strlen($error)) {
    error_log($error);
}

// }}}
// {{{ 出力

header('Content-Type: application/json');
mb_convert_variables('UTF-8', 'SJIS-win', $result);
$json = json_encode($result);
//error_log($json);
echo $json;

// }}}

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
