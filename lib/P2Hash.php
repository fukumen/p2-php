<?php

class P2Hash
{
    private const ALGO = 'sha256';
    private static $instance;
    private $password_options = [];

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new P2Hash();
        }
        return self::$instance;
    }

    public function __construct()
    {
        global $_conf;

        if ($_conf['password_hash_low_spec']) {
            $this->get_low_spec_options();
        }
    }

    private function get_low_spec_options()
    {    
        $this->password_options = match (PASSWORD_DEFAULT) {
            PASSWORD_BCRYPT => [
                'cost' => 9,
            ],

            // ‚»‚êˆÈŠO‚ÌƒAƒ‹ƒSƒŠƒYƒ€i«—ˆ‚ÌPASSWORD_DEFAULTŠÜ‚Þj‚ª—ˆ‚½ê‡‚ÍA
            // ¡‚Ì’mŽ¯‚ÅŒy—Ê‰»‚µ‚æ‚¤‚Æ‚¹‚¸ˆê’UPHP‚Ì•W€‚É”C‚¹A
            // PASSWORD_DEFAULT‚ªØ‚è‘Ö‚í‚Á‚½‚Æ‚«‚É‚»‚ÌƒAƒ‹ƒSƒŠƒYƒ€—p‚ÌŒy—ÊÝ’è‚ð’Ç‰Á‚·‚é‚Æ‚¢‚¤‰^—p‚ð‘z’èB
            default => [], 
        };
    }

    public function password_hash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT, $this->password_options);
    }

    public function password_verify($password, $hash)
    {
        return password_verify($password, $hash);
    }

    public function password_needs_rehash($hash)
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    static public function hash($data, $binary = false)
    {
        return hash(self::ALGO, $data, $binary);
    }
}

