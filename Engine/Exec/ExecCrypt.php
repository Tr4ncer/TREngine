<?php

namespace TREngine\Engine\Exec;

require dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'SecurityCheck.php';

/**
 * Outil de manipulation de données pour le cryptage.
 *
 * @author Sébastien Villemain
 */
class ExecCrypt {

    const METHOD_MD5_POWER = "md5+";

    /**
     * Création d'une clé unique.
     *
     * @param int $size
     * @return string
     */
    public static function &makeNewKey($size = 32, $letter = true, $number = true, $caseSensitive = true) {
        $randKey = "";
        $string = "";
        $letters = "abcdefghijklmnopqrstuvwxyz";
        $numbers = "0123456789";

        if ($letter && $number) {
            $string = $letters . $numbers;
        } else if ($letter) {
            $string = $letters;
        } else {
            $string = $numbers;
        }

        // Initialisation
        srand(time());

        for ($i = 0; $i < $size; $i++) {
            $key = substr($string, (rand() % (strlen($string))), 1);

            if ($caseSensitive) {
                $key = (rand(0, 1) == 1) ? strtoupper($key) : strtolower($key);
            }
            $randKey .= $key;
        }
        return $randKey;
    }

    /**
     * Création d'un identifiant unique avec des chiffres, lettres et sensible à la case.
     *
     * @param int $size
     * @return string
     */
    public static function &makeIdentifier($size = 32) {
        $key = self::makeNewKey($size, true, true, true);
        return $key;
    }

    /**
     * Création d'un identifiant unique avec des chiffres.
     *
     * @param int $size
     * @return string
     */
    public static function &makeNumericIdentifier($size = 32) {
        $key = self::makeNewKey($size, false, true, false);
        return $key;
    }

    /**
     * Création d'un identifiant unique avec des lettres.
     *
     * @param int $size
     * @return string
     */
    public static function &createLetterIdentifier($size = 32) {
        $key = self::makeNewKey($size, true, false, false);
        return $key;
    }

    /**
     * Création d'un identifiant unique avec des lettres sensible à la case.
     *
     * @param int $size
     * @return string
     */
    public static function &createLetterCaseSensitiveIdentifier($size = 32) {
        $key = self::makeNewKey($size, true, false, true);
        return $key;
    }

    /**
     * Crypteur de donnée
     *
     * @param $data string donnée
     * @param $salt string clès
     * @param $method string méthode de cryptage
     * @return string
     */
    public static function &cryptData($data, $salt = "", $method = "") {
        // Réglage de la méthode utilisé
        if (empty($method))
            $method = "smd5";
        $method = strtolower($method);
        $cryptData = "";

        // Préparation du salt
        if (empty($salt))
            $salt = self::makeIdentifier(16);

        switch ($method) {
            case 'smd5':
                // Si le crypt md5 est activé
                if (defined("CRYPT_MD5") && CRYPT_MD5) {
                    $cryptData = crypt($data, "$1$" . substr($salt, 0, 8) . "$");
                    break;
                }
                // Sinon utilisation du simple md5
                $cryptData = self::cryptData($data, $salt, "md5");
                break;
            case 'md5':
                $cryptData = md5($data);
                break;
            case 'jmd5': // Joomla md5 :)
                $cryptData = md5($data . $salt) . ":" . $salt;
                break;
            case 'md5+': // TR ENGINE md5 !
                $cryptData = "TR" . md5($data . substr($salt, 0, 8));
                break;
            case 'crypt':
                $cryptData = crypt($data, substr($salt, 0, 2));
                break;
            case 'sha1':
                $cryptData = sha1($data);
                break;
            case 'ssha':
                $salt = substr($salt, 0, 4);
                $cryptData = "{SSHA}" . base64_encode(pack("H*", sha1($data . $salt)) . $salt);
                break;
            case 'my411':
                $cryptData = "*" . sha1(pack("H*", sha1($data)));
                break;
            default:
                if (CoreLoader::isCallable("CoreLogger")) {
                    CoreLogger::addException("Unsupported crypt method. Method : " . $method);
                }
                $cryptData = self::cryptData($data, $salt);
                break;
        }
        return $cryptData;
    }

    /**
     * Encodeur de chaine
     * Thanks Alexander Valyalkin @ 30-Jun-2004 08:41
     * http://fr2.php.net/manual/fr/function.md5.php
     *
     * @param $plain_text
     * @param $password
     * @param $iv_len
     * @return string
     */
    public static function &md5Encrypt($plain_text, $password, $iv_len = 16) {
        $plain_text .= "\x13";
        $n = strlen($plain_text);
        if ($n % 16)
            $plain_text .= str_repeat("\0", 16 - ($n % 16));

        $i = 0;
        $enc_text = self::getRandIv($iv_len);
        $iv = substr($password ^ $enc_text, 0, 512);
        while ($i < $n) {
            $block = substr($plain_text, $i, 16) ^ pack('H*', md5($iv));
            $enc_text .= $block;
            $iv = substr($block . $iv, 0, 512) ^ $password;
            $i += 16;
        }
        $enc_text = base64_encode($enc_text);
        return $enc_text;
    }

    /**
     * Décodeur de chaine
     * Thanks Alexander Valyalkin @ 30-Jun-2004 08:41
     * http://fr2.php.net/manual/fr/function.md5.php
     *
     * @param $enc_text
     * @param $password
     * @param $iv_len
     * @return string
     */
    public static function &md5Decrypt($enc_text, $password, $iv_len = 16) {
        $enc_text = base64_decode($enc_text);
        $n = strlen($enc_text);

        $i = $iv_len;
        $plain_text = '';
        $iv = substr($password ^ substr($enc_text, 0, $iv_len), 0, 512);
        while ($i < $n) {
            $block = substr($enc_text, $i, 16);
            $plain_text .= $block ^ pack('H*', md5($iv));
            $iv = substr($block . $iv, 0, 512) ^ $password;
            $i += 16;
        }
        $plain_text = preg_replace('/\\x13\\x00*$/', '', $plain_text);
        return $plain_text;
    }

    /**
     * Genere une valeur
     * Thanks Alexander Valyalkin @ 30-Jun-2004 08:41
     * http://fr2.php.net/manual/fr/function.md5.php
     *
     * @param $iv_len
     * @return string
     */
    private static function &getRandIv($iv_len) {
        $iv = "";
        while ($iv_len-- > 0) {
            $iv .= chr(mt_rand() & 0xff);
        }
        return $iv;
    }

}

?>