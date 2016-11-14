<?php

namespace TREngine\Engine\Core;

// Exceptionnellement, NE PAS inclure SecurityCheck
if (preg_match("/CoreInfo.php/ie", $_SERVER['PHP_SELF'])) {
    exit();
}

/**
 * Recherche d'information rapide sur le moteur d'exécution et son environnement coté serveur.
 *
 * Attention, il faut faire en sorte que cette classe soit compatible PHP 5.6 (date minimale 08/2014).
 *
 * @author Sébastien Villemain
 */
class CoreInfo {

    private static $initialized = false;

    private function __construct() {

    }

    /**
     * Détermine si la version PHP actuelle est compatible.
     *
     * @return bool
     */
    public static function compatibleVersion() {
        return (TR_ENGINE_PHP_VERSION >= "7.0.0");
    }

    /**
     * Création des constantes contenant les informations sur l'environnement.
     */
    public static function initialize() {
        if (!self::$initialized) {
            self::$initialized = true;

            $info = new CoreInfo();

            /**
             * Version php sous forme x.x.x.x (exemple : 5.2.9.2).
             */
            define("TR_ENGINE_PHP_VERSION", $info->getPhpVersion());

            /**
             * Chemin jusqu'à la racine.
             *
             * @var string
             */
            define("TR_ENGINE_INDEXDIR", $info->getBaseDir());

            /**
             * Adresse URL complète jusqu'à TR ENGINE.
             *
             * @var string
             */
            define("TR_ENGINE_URL", $info->getUrlAddress());

            /**
             * Le système d'exploitation qui exécute TR ENGINE.
             *
             * @var string
             */
            define("TR_ENGINE_PHP_OS", $info->getPhpOs());

            /**
             * Le retour de chariot du serveur TR ENGINE.
             *
             * @var string
             */
            define("TR_ENGINE_CRLF", $info->getCrLf());

            /**
             * Numéro de version du moteur.
             *
             * Controle de révision
             * xx -> version courante
             * xx -> fonctionnalitées ajoutées
             * xx -> bugs ou failles critiques corrigés
             * xx -> bug mineur
             *
             * @var string
             */
            define("TR_ENGINE_VERSION", "0.6.2.0");
        }
    }

    /**
     * Retourne la version du PHP.
     *
     * @return string
     */
    private function getPhpVersion() {
        return preg_replace("/[^0-9.]/", "", (preg_replace("/(_|-|[+])/", ".", phpversion())));
    }

    /**
     * Retourne le chemin jusqu'à la racine.
     *
     * @return string
     */
    private function getBaseDir() {
        $baseDir = "";

        // Recherche du chemin absolu depuis n'importe quel fichier
        if (defined("TR_ENGINE_INDEX")) {
            // Nous sommes dans l'index
            $baseDir = getcwd();
        } else {
            // Chemin de base
            $baseName = str_replace($this->getGlobalServer("SCRIPT_NAME"), "", $this->getGlobalServer("SCRIPT_FILENAME"));
            $baseName = str_replace("/", DIRECTORY_SEPARATOR, $baseName);
            $workingDirectory = getcwd();

            if (!empty($workingDirectory)) {
                // Nous isolons le chemin en plus jusqu'au fichier
                $path = str_replace($baseName, "", $workingDirectory);

                if (!empty($path)) {
                    // Suppression du slash supplémentaire
                    if ($path[0] === DIRECTORY_SEPARATOR) {
                        $path = substr($path, 1);
                    }

                    // Vérification en se repérant sur l'emplacement du fichier de configuration
                    while (!is_file($baseName . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.inc.php")) {
                        // Nous remontons d'un cran
                        $path = dirname($path);

                        // La recherche n'aboutira pas
                        if ($path === ".") {
                            break;
                        }
                    }
                }
            }

            // Verification du résultat
            if (!empty($path) && is_file($baseName . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.inc.php")) {
                $baseDir = $baseName . DIRECTORY_SEPARATOR . $path;
            } else if (is_file($baseName . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.inc.php")) {
                $baseDir = $baseName;
            } else {
                $baseDir = $baseName;
            }
        }
        return $baseDir;
    }

    /**
     * Retourne l'adresse URL complète jusqu'à TR ENGINE.
     *
     * @return string
     */
    private function getUrlAddress() {
        // Recherche de l'URL courante
        $urlTmp = $this->getGlobalServer("REQUEST_URI");

        if (substr($urlTmp, -1) === "/") {
            $urlTmp = substr($urlTmp, 0, -1);
        }

        if ($urlTmp[0] === "/") {
            $urlTmp = substr($urlTmp, 1);
        }

        $urlTmp = explode("/", $urlTmp);

        // Recherche du dossier courant
        $urlBase = explode(DIRECTORY_SEPARATOR, TR_ENGINE_INDEXDIR);

        // Construction du lien
        $urlFinal = "";
        $urlTmpCounter = count($urlTmp);
        $urlBaseCounter = count($urlBase);

        for ($i = $urlTmpCounter - 1; $i >= 0; $i--) {
            for ($j = $urlBaseCounter - 1; $j >= 0; $j--) {
                if (empty($urlBase[$j])) {
                    continue;
                }

                if ($urlTmp[$i] !== $urlBase[$j]) {
                    break;
                }

                if (empty($urlFinal)) {
                    $urlFinal = $urlTmp[$i];
                } else {
                    $urlFinal = $urlTmp[$i] . "/" . $urlFinal;
                }

                $urlBase[$j] = "";
            }
        }
        $serverName = $this->getGlobalServer("SERVER_NAME");
        return ((empty($urlFinal)) ? $serverName : $serverName . "/" . $urlFinal);
    }

    /**
     * Retourne la plateforme sur lequel est PHP.
     *
     * @return string
     */
    private function getPhpOs() {
        return strtoupper(substr(PHP_OS, 0, 3));
    }

    /**
     * Retourne le retour chariot du serveur.
     *
     * @return string
     */
    private function getCrLf() {
        $rslt = "";

        // Le retour chariot de chaque OS
        if (TR_ENGINE_PHP_OS === "WIN") {
            $rslt = "\r\n";
        } else if (TR_ENGINE_PHP_OS === "MAC") {
            $rslt = "\r";
        } else {
            $rslt = "\n";
        }
        return $rslt;
    }

    /**
     * Classe autorisée à manipuler $_SERVER.
     *
     * @param string $keyName
     * @return string
     */
    private function getGlobalServer($keyName) {
        return ${"_" . "SERVER"}[$keyName];
    }

}
