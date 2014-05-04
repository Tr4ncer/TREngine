<?php
// Attention au double instances de Core_Secure...
if (preg_match("/secure.class.php/ie", $_SERVER['PHP_SELF'])) {
    if (!defined("TR_ENGINE_INDEX")) {
        Core_Secure::checkInstance();
    }
}

/**
 * Système de sécurité.
 * Analyse rapidement les données reçues.
 * Configure les erreurs.
 * Capture la configuration.
 *
 * @author Sébastien Villemain
 */
class Core_Secure {

    /**
     * Instance de cette classe.
     *
     * @var Core_Secure
     */
    private static $secure = null;

    /**
     * Stats et debug mode.
     *
     * @var boolean
     */
    private $debuggingMode = false;

    /**
     * Routine de sécurisation.
     */
    private function __construct($debuggingMode) {
        $this->debuggingMode = $debuggingMode;

        $this->checkError();
        $this->checkQueryString();
        $this->checkRequestReferer();

        // Si nous ne sommes pas passé par l'index
        if (!defined("TR_ENGINE_INDEX")) {
            if (!class_exists("Core_Info")) {
                require("info.class.php");
            }

            define("TR_ENGINE_INDEX", true);
            $this->throwException("badUrl");
        }

        // A exécuter uniquement après avoir vérifié l'index et le numéro de version
        $this->checkGPC();
    }

    /**
     * Retourne l'instance du gestionnaire de sécurité.
     *
     * @return Core_Secure
     */
    public static function &getInstance() {
        self::checkInstance();
        return self::$secure;
    }

    /**
     * Vérification de l'instance du gestionnaire de sécurité.
     *
     * @param boolean $debuggingMode
     */
    public static function checkInstance($debuggingMode = false) {
        if (self::$secure === null) {
            self::$secure = new self($debuggingMode);
        }
    }

    /**
     * Vérifie si le mode de statistique et de debug est actif.
     *
     * @return boolean
     */
    public static function &isDebuggingMode() {
        $rslt = false;

        if (self::$secure !== null) {
            $rslt = self::$secure->debuggingMode;
        }
        return $rslt;
    }

    /**
     * Affiche un message d'erreur au client, mettra fin à l'exécution du moteur.
     * Cette fonction est activé si une erreur est détectée.
     *
     * @param string $customMessage Message d'erreur.
     * @param Exception $ex L'exception interne levée.
     * @param array $argv Argument suplementaire d'information sur l'erreur.
     */
    public function throwException($customMessage, Exception $ex = null, array $argv = array()) {
        if (!class_exists("Core_Loader")) {
            require(TR_ENGINE_DIR . DIRECTORY_SEPARATOR . "engine" . DIRECTORY_SEPARATOR . "core" . DIRECTORY_SEPARATOR . "loader.class.php");
        }

        if ($ex === null) {
            $ex = new Fail_Engine($customMessage);
        }

        // Préparation du template debug
        $libsMakeStyle = new Libs_MakeStyle();
        $libsMakeStyle->assign("errorMessageTitle", $this->getErrorMessageTitle($customMessage));
        $libsMakeStyle->assign("errorMessage", $this->getDebugMessage($ex, $argv));

        // Affichage du template en debug si problème
        $libsMakeStyle->display("debug", true);

        // Arret du moteur
        exit();
    }

    /**
     * Retourne le type d'erreur courant sous forme de message.
     *
     * @param string $customMessage
     * @return string $errorMessageTitle
     */
    private function &getErrorMessageTitle($customMessage) {
        // Message d'erreur depuis une constante
        $errorMessageTitle = "ERROR_DEBUG_" . strtoupper($customMessage);

        if (defined($errorMessageTitle)) {
            $errorMessageTitle = Exec_Entities::entitiesUtf8(constant($errorMessageTitle));
        } else {
            $errorMessageTitle = "Stop loading (";

            if ($this->debuggingMode) {
                $errorMessageTitle .= $customMessage;
            } else {
                $errorMessageTitle .= "Fatal error unknown";
            }

            $errorMessageTitle .= ").";
        }
        return $errorMessageTitle;
    }

    /**
     * Analyse l'erreur et prépare l'affichage de l'erreur.
     *
     * @param Exception $ex L'exception interne levée.
     * @param array $argv Argument supplémentaire d'information sur l'erreur.
     * @return array $errorMessage
     */
    private function &getDebugMessage(Exception $ex = null, array $argv = array()) {
        // Tableau avec les lignes d'erreurs
        $errorMessage = array();

        // Analyse de l'exception
        if ($ex !== null) {
            if ($this->debuggingMode) {
                if ($ex instanceof Fail_Base) {
                    $errorMessage[] = $ex->getFailInformation();
                } else {
                    $errorMessage[] = "Exception PHP (" . $ex->getCode() . ") : " . $ex->getMessage();
                }
            }

            foreach ($ex->getTrace() as $traceValue) {
                $errorLine = "";

                if (is_array($traceValue)) {
                    foreach ($traceValue as $key => $value) {
                        if ($key == "file" || $key == "function") {
                            $value = preg_replace("/([a-zA-Z0-9._]+).php/", "<b>\\1</b>.php", $value);
                            $errorLine .= " <b>" . $key . "</b> " . $value;
                        } else if ($key == "line" || $key == "class") {
                            $errorLine .= " in <b>" . $key . "</b> " . $value;
                        }
                    }
                }

                if (!empty($errorLine)) {
                    $errorMessage[] = $errorLine;
                }
            }
        }

        // Fusion des informations supplémentaires
        if (!empty($argv)) {
            $errorMessage[] = " ";
            $errorMessage[] = "<b>Additional information about the error:</b>";
            $errorMessage = array_merge($errorMessage, $argv);
        }

        if (Core_Loader::isCallable("Core_Session") && Core_Loader::isCallable("Core_Sql")) {
            if (Core_Sql::hasConnection()) {
                if (Core_Session::getInstance()->userRank > 1) {
                    $sqlErrors = Core_Sql::getInstance()->getLastError();

                    if (!empty($sqlErrors)) {
                        $errorMessage[] = " ";
                        $errorMessage[] = "<b>Last Sql error message:</b>";
                        $errorMessage = array_merge($errorMessage, $sqlErrors);
                    }
                }
            }
        }

        if (Core_Loader::isCallable("Core_Logger")) {
            $loggerExceptions = Core_Logger::getExceptions();

            if (!empty($loggerExceptions)) {
                $errorMessage[] = " ";
                $errorMessage[] = "<b>Exceptions logged:</b>";
                $errorMessage = array_merge($errorMessage, $loggerExceptions);
            }
        }
        return $errorMessage;
    }

    /**
     * Réglages des sorties d'erreurs.
     */
    private function checkError() {
        // Réglages des sorties d'erreur
        $errorReporting = E_ERROR | E_WARNING | E_PARSE;

        if ($this->debuggingMode) {
            $errorReporting = E_ALL;
        }

        error_reporting($errorReporting);
    }

    /**
     * Vérification des données reçues (Query string).
     */
    private function checkQueryString() {
        $queryString = strtolower(rawurldecode($_SERVER['QUERY_STRING']));

        $badString = array(
            "SELECT",
            "UNION",
            "INSERT",
            "UPDATE",
            "AND",
            "%20union%20",
            "/*",
            "*/union/*",
            "+union+",
            "load_file",
            "outfile",
            "document.cookie",
            "onmouse",
            "<script",
            "<iframe",
            "<applet",
            "<meta",
            "<style",
            "<form",
            "<img",
            "<body",
            "<link");

        foreach ($badString as $stringValue) {
            if (strpos($queryString, $stringValue)) {
                $this->throwException("badQueryString");
            }
        }
    }

    /**
     * Vérification des envois POST.
     */
    private function checkRequestReferer() {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (!empty($_SERVER['HTTP_REFERER'])) {
                if (!preg_match("/" . $_SERVER['HTTP_HOST'] . "/", $_SERVER['HTTP_REFERER'])) {
                    $this->throwException("badRequestReferer");
                }
            }
        }
    }

    /**
     * Fonction de substitution pour MAGIC_QUOTES_GPC.
     */
    private function checkGPC() {
        if (TR_ENGINE_PHP_VERSION < "5.3.0" && function_exists("set_magic_quotes_runtime")) {
            set_magic_quotes_runtime(0);
        }

        $this->addSlashesForQuotes($_GET);
        $this->addSlashesForQuotes($_POST);
        $this->addSlashesForQuotes($_COOKIE);
    }

    /**
     * Ajoute un antislash pour chaque quote.
     *
     * @param array $key objet sans antislash
     */
    private function addSlashesForQuotes(&$key) {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                if (is_array($key[$k])) {
                    $this->addSlashesForQuotes($key[$k]);
                } else {
                    $key[$k] = addslashes($v);
                }
            }

            reset($key);
        } else {
            $key = addslashes($key);
        }
    }

}
