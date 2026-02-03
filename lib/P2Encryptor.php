<?php

class P2Encryptor
{
    private const GETENV_NAME = 'SECRET_KEY';
    private const METHOD = 'aes-256-gcm';
    private static $instance;
    private $key;
    private $ivLength;

    public static function getInstance()
    {
        if (!self::$instance) {
            try {
                self::$instance = new P2Encryptor();
            } catch (Exception $e) {
                p2die($e->getMessage());
            }
        }
        return self::$instance;
    }

    public function __construct()
    {
        $hex = getenv(self::GETENV_NAME);
        if (strlen($hex) !== 64) {
            throw new Exception('環境変数の' . self::GETENV_NAME . 'に32バイトの16進の文字列(暗号キー)を設定してください');
        }
        $this->key = pack('H*', $hex);

        $length = openssl_cipher_iv_length(self::METHOD);
        if ($length === false) {
            throw new Exception('サポートしていない暗号化方式[' . self::METHOD . ']が指定されています');
        }
        $this->ivLength = $length;
    }

    public function encrypt($plaintext)
    {
        $iv = random_bytes($this->ivLength);

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $ciphertext !== false ? base64_encode($iv . $tag . $ciphertext) : null;
    }

    public function decrypt($base64Data)
    {
        $binary = base64_decode($base64Data, true);
        if ($binary === false) {
            return null;
        }

        $tagLength = 16;
        
        if (strlen($binary) < $this->ivLength + $tagLength) {
            return null;
        }

        $iv = substr($binary, 0, $this->ivLength);
        $tag = substr($binary, $this->ivLength, $tagLength);
        $ciphertext = substr($binary, $this->ivLength + $tagLength);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext !== false ? $plaintext : null;
    }
}
