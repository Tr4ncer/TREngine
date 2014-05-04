<?php
if (!defined("TR_ENGINE_INDEX")) {
    require("secure.class.php");
    Core_Secure::checkInstance();
}

/**
 * Gestionnaire d'entête et de contenu HTML.
 *
 * @author Sébastien Villemain
 */
class Core_Html {

    /**
     * Instance de cette classe.
     *
     * @var Core_Html
     */
    private static $coreHtml = null;

    /**
     * Nom du cookie de test.
     *
     * @var string
     */
    private $cookieTestName = "test";

    /**
     * Etat du javaScript chez le client.
     *
     * @var boolean
     */
    private $javaScriptEnabled = false;

    /**
     * Fonctions et codes javascript demandées.
     *
     * @var string
     */
    private $javaScriptCode = "";

    /**
     * Fonctions et codes javascript JQUERY demandées.
     *
     * @var string
     */
    private $javaScriptJquery = "";

    /**
     * Fichiers de javascript demandées.
     *
     * @var array
     */
    private $javaScriptFile = array();

    /**
     * Fichier de style CSS demandées.
     *
     * @var array
     */
    private $cssFile = array();

    /**
     * Titre de la page courante.
     *
     * @var string
     */
    private $title = "";

    /**
     * Mots clès de la page courante.
     *
     * @var array
     */
    private $keywords = array();

    /**
     * Description de la page courante.
     *
     * @var string
     */
    private $description = "";

    /**
     * Nouveau gestionnaire.
     */
    private function __construct() {
        // Configuration du préfixe accessible
        if (Core_Loader::isCallable("Core_Main")) {
            $prefix = Core_Main::getInstance()->getCookiePrefix();
        } else {
            $prefix = "tr";
        }

        // Composition du nom du cookie de test
        $this->cookieTestName = Exec_Crypt::cryptData(
        $prefix . "_" . $this->cookieTestName, self::getSalt(), "md5+"
        );

        // Vérification du javascript du client
        $this->checkJavascriptEnabled();
    }

    /**
     * Retourne et si besoin créé l'instance Core_Html.
     *
     * @return Core_Html
     */
    public static function &getInstance() {
        self::checkInstance();
        return self::$coreHtml;
    }

    /**
     * Vérification de l'instance du gestionnaire de HTML.
     */
    public static function checkInstance() {
        if (self::$coreHtml === null) {
            self::$coreHtml = new self();
        }
    }

    /**
     * Ajoute un code javaScript JQUERY à exécuter.
     *
     * @param string $javaScript
     */
    public function addJavascriptJquery($javaScript) {
        if (!empty($this->javaScriptJquery)) {
            $this->javaScriptJquery .= "\n";
        }

        $this->javaScriptJquery .= $javaScript;
    }

    /**
     * Ajoute un code javaScript pur à exécuter.
     *
     * @param string $javaScript
     */
    public function addJavascriptCode($javaScript) {
        if (!empty($this->javaScriptCode)) {
            $this->javaScriptCode .= "\n";
        }

        $this->javaScriptCode .= $javaScript;
    }

    /**
     * Ajoute un code javascript (compatible Jquery et javascript pur) à exécuter.
     *
     * @param string $javaScript
     */
    public function addJavascript($javaScript) {
        if ($this->javascriptEnabled() && Core_Loader::isCallable("Core_Main") && Core_Main::getInstance()->isDefaultLayout()) {
            $this->addJavascriptJquery($javaScript);
        } else {
            $this->addJavascriptCode($javaScript);
        }
    }

    /**
     * Retourne l'état du javascript du client.
     *
     * @return boolean
     */
    public function &javascriptEnabled() {
        return $this->javaScriptEnabled;
    }

    /**
     * Ajoute un fichier javascript à l'entête.
     *
     * @param string $fileName
     * @param string $options
     */
    public function addJavascriptFile($fileName, $options = "") {
        if (!array_key_exists($fileName, $this->javaScriptFile)) {
            if ($fileName == "jquery.js") {
                // Fixe JQuery en 1ere position
                $this->javaScriptFile = array_merge(array(
                    $fileName => $options), $this->javaScriptFile);
            } else if ($fileName == "tr_engine.js") {
                // Fixe tr_engine en 2em position
                if (array_key_exists("jquery.js", $this->javaScriptFile)) {
                    $this->javaScriptFile = array_merge(array(
                        "jquery.js" => $this->javaScriptFile['jquery.js'],
                        $fileName => $options), $this->javaScriptFile);
                } else {
                    $this->javaScriptFile = array_merge(array(
                        $fileName => $options), $this->javaScriptFile);
                }
            } else {
                $this->javaScriptFile[$fileName] = $options;
            }
        }
    }

    /**
     * Ajoute un fichier de style .CSS provenant du dossier includes/css à l'entête.
     *
     * @param string $fileName
     * @param string $options
     */
    public function addCssInculdeFile($fileName, $options = "") {
        $this->addCssFile("includes/css/" . $fileName, $options);
    }

    /**
     * Ajoute un fichier de style .CSS provenant du dossier template à l'entête.
     *
     * @param string $fileName
     * @param string $options
     */
    public function addCssTemplateFile($fileName, $options = "") {
        if (Core_Loader::isCallable("Libs_MakeStyle")) {
            $this->addCssFile(Libs_MakeStyle::getTemplatesDir() . "/" . Libs_MakeStyle::getCurrentTemplate() . "/" . $fileName, $options);
        }
    }

    /**
     * Retourne les métas données de l'entête HTML.
     *
     * @return string
     */
    public function getMetaHeaders() {
        $title = "";

        if (Core_Loader::isCallable("Core_Main")) {
            $title .= Core_Main::getInstance()->getDefaultSiteName() . " - ";

            if (empty($this->title)) {
                $title .= Core_Main::getInstance()->getDefaultSiteSlogan();
            } else {
                $title .= $this->title;
            }
        } else {
            $title .= Core_Request::getString("SERVER_NAME", "", "SERVER");

            if (!empty($this->title)) {
                $title .= " - " . $this->title;
            }
        }

        // TODO ajouter un support RSS XML

        return "<title>" . Exec_Entities::textDisplay($title) . "</title>\n"
        . $this->getMetaKeywords()
        . "<meta name=\"generator\" content=\"TR ENGINE\" />\n"
        . "<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />\n"
        . "<meta http-equiv=\"content-script-type\" content=\"text/javascript\" />\n"
        . "<meta http-equiv=\"content-style-type\" content=\"text/css\" />\n"
        . "<link rel=\"shortcut icon\" type=\"image/x-icon\" href=\"" . Libs_MakeStyle::getTemplatesDir() . "/" . Libs_MakeStyle::getCurrentTemplate() . "/favicon.ico\" />\n"
        . $this->getMetaIncludeJavascript()
        . $this->getMetaIncludeCss();
    }

    /**
     * Retourne les métas données de bas de page HTML.
     *
     * @return string
     */
    public function getMetaFooters() {
        // TODO continuer le footer
        return $this->getMetaExecuteJavascript();
    }

    /**
     * Affecte le titre à la page courante.
     *
     * @param string $title
     */
    public function setTitle($title) {
        $this->title = strip_tags($title);
    }

    /**
     * Affecte les mots clès de la page courante.
     *
     * @param array $keywords un tableau de mots clés
     */
    public function setKeywords(array $keywords) {
        if (count($this->keywords) > 0) {
            array_push($this->keywords, $keywords);
        } else {
            $this->keywords = $keywords;
        }
    }

    /**
     * Affecte la description de la page courante.
     *
     * @param string $description
     */
    public function setDescription($description) {
        $this->description = strip_tags($description);
    }

    /**
     * Retourne un lien cliquable sans javascript.
     *
     * @param string $link Adresse URL de base.
     * @param string $displayContent Données à afficher (texte simple ou code html)
     * @param boolean $layout true ajouter le layout.
     * @param string $onclick Données à exécuter lors du clique
     * @param string $addons Code additionnel
     * @return string
     */
    public static function &getLink($link, $displayContent = "", $layout = false, $onclick = "", $addons = "") {
        $htmlLink = "<a href=\"" . Core_UrlRewriting::getLink($link, $layout) . "\"";

        // TODO A vérifier
        if (preg_match("/^[A-Za-z0-9_-+. ]/ie", $displayContent)) {
            $htmlLink .= " title=\"" . $displayContent . "\"";
        }

        if (!empty($onclick)) {
            $htmlLink .= " onclick=\"" . $onclick . "\"";
        }

        if (!empty($addons)) {
            $htmlLink .= " " . $addons;
        }

        $htmlLink .= ">";

        if (!empty($displayContent)) {
            $htmlLink .= $displayContent;
        }

        $htmlLink .= "</a>";
        return $htmlLink;
    }

    /**
     * Retourne un lien cliquable utilisable avec et sans javascript.
     *
     * @param string $linkWithoutJavascript
     * @param string $linkForJavascript
     * @param string $divId
     * @param string $displayContent
     * @param string $addons Code additionnel
     * @return string
     */
    public static function &getLinkWithAjax($linkWithoutJavascript, $linkForJavascript, $divId, $displayContent, $addons = "") {
        // TODO A vérifier
        self::getInstance()->addJavascriptFile("jquery.js");
        return self::getLink($linkWithoutJavascript, $displayContent, false, "validLink('" . $divId . "', '" . Core_UrlRewriting::getLink($linkForJavascript, true) . "');return false;", $addons);
    }

    /**
     * Redirection ou chargement via javascript vers une page.
     *
     * @param string $url La page demandée a chargé.
     * @param int $tps Temps avant le chargement de la page.
     * @param string $method Block de destination si ce n'est pas toute la page.
     */
    public function redirect($url = "", $tps = 0, $method = "window") {
        // Configuration du temps
        $tps = ((!is_numeric($tps)) ? 0 : $tps) * 1000;

        // Configuration de l'url
        if (empty($url) || $url == "index.php?") {
            $url = "index.php";
        }

        // Redirection
        if ($this->javascriptEnabled() && ($tps > 0 || $method != "windows")) {
            if (Core_Request::getString("REQUEST_METHOD", "", "SERVER") == "POST" && $method != "window") {
                // Commande ajax pour la redirection
                $this->addJavascriptCode("setTimeout(function(){ $('" . $method . "').load('" . $url . "'); }, $tps);");
            } else {
                // Commande par défaut
                $this->addJavascriptCode("setTimeout('window.location = \"" . $url . "\"','" . $tps . "');");
            }
        } else {
            // Redirection php sans timeout
            header("Location: " . $url);
        }
    }

    /**
     * Inclus et exécute le javascript de facon autonome.
     */
    public function selfJavascript() {
        echo $this->getMetaIncludeJavascript(true) . $this->getMetaExecuteJavascript();
    }

    /**
     * Retourne l'icône de chargement animée.
     *
     * @return string
     */
    public function &getLoader() {
        $rslt = "";

        if ($this->javascriptEnabled()) {
            $rslt = "<div id=\"loader\"></div>";
        }
        return $rslt;
    }

    /**
     * Retourne la combinaison de clés pour le salt.
     *
     * @return string
     */
    private function &getSalt() {
        // Configuration de la clès si accessible
        if (Core_Loader::isCallable("Core_Main")) {
            $key = Core_Main::getInstance()->getCryptKey();
        } else {
            $key = "A4bT9D4V";
        }
        return $key;
    }

    /**
     * Détection du javascript chez le client.
     */
    private function checkJavascriptEnabled() {
        // Récuperation du cookie en php
        $cookieTest = Exec_Cookie::getCookie($this->cookieTestName);

        // Vérification de l'existance du cookie
        $this->javaScriptEnabled = ($cookieTest == 1) ? true : false;
    }

    /**
     * Ajoute un fichier de style .CSS à l'entête.
     *
     * @param string $filePath
     * @param string $options
     */
    private function addCssFile($filePath, $options = "") {
        if (is_file(TR_ENGINE_DIR . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $filePath))) {
            if (!array_key_exists($filePath, $this->cssFile)) {
                $this->cssFile[$filePath] = $options;
            }
        }
    }

    /**
     * Retourne les mots clès et la description de la page.
     *
     * @return string
     */
    private function getMetaKeywords() {
        $keywords = "";

        if (is_array($this->keywords) && count($this->keywords) > 0) {
            $keywords = implode(", ", $this->keywords);
        }

        if (Core_Loader::isCallable("Core_Main")) {
            if (empty($this->description)) {
                $this->description = Core_Main::getInstance()->getDefaultDescription();
            }

            if (empty($keywords)) {
                $keywords = Core_Main::getInstance()->getDefaultKeyWords();
            }
        }

        $keywords = strip_tags($keywords);

        // 500 caractères maximum
        $keywords = (strlen($keywords) > 500) ? substr($keywords, 0, 500) : $keywords;

        return "<meta name=\"description\" content=\"" . Exec_Entities::textDisplay($this->description) . "\" />\n"
        . "<meta name=\"keywords\" content=\"" . Exec_Entities::textDisplay($keywords) . "\" />\n";
    }

    /**
     * Retourne les scripts à inclure.
     *
     * @param boolean $forceIncludes Pour forcer l'inclusion des fichiers javascript.
     * @return string
     */
    private function &getMetaIncludeJavascript($forceIncludes = false) {
        if (Core_Request::getRequestMethod() != "POST" || $forceIncludes) {
            $fullScreen = Core_Loader::isCallable("Core_Main") ? Core_Main::getInstance()->isDefaultLayout() : true;

            if (($fullScreen || $forceIncludes) && $this->javascriptEnabled()) {
                if (!empty($this->javaScriptJquery)) {
                    $this->addJavascriptFile("jquery.js");
                }

                $this->addJavascriptFile("tr_engine.js");
            } else {
                $this->resetJavascript();
            }

            // Lorsque l'on ne force pas l'inclusion on fait un nouveau test
            if (!$forceIncludes) {
                if (!$this->javascriptEnabled()) {
                    $this->addJavascriptFile("javascriptenabled.js");
                    $this->addJavascriptCode("javascriptEnabled('" . $this->cookieTestName . "');");
                }
            }

            if (Core_Loader::isCallable("Exec_Agent") && Exec_Agent::$userBrowserName == "Internet Explorer" && Exec_Agent::$userBrowserVersion < "7") {
                $this->addJavascriptFile("pngfix.js", "defer");
            }
        } else {
            $this->resetJavascript();
        }

        $meta = "";

        // Conception de l'entête
        foreach ($this->javaScriptFile as $fileName => $options) {
            $meta .= "<script" . ((!empty($options)) ? " " . $options : "") . " type=\"text/javascript\" src=\"includes/js/" . $fileName . "\"></script>\n";
        }
        return $meta;
    }

    /**
     * Reset des codes et fichier inclus javascript.
     */
    private function resetJavascript() {
        $this->javaScriptCode = "";
        $this->javaScriptJquery = "";
        $this->javaScriptFile = array();
    }

    /**
     * Retourne les fichiers de css à inclure.
     *
     * @return string
     */
    private function &getMetaIncludeCss() {
        $this->addCssInculdeFile("default.css");

        $meta = "";

        // Conception de l'entête
        foreach ($this->cssFile as $filePath => $options) {
            if (!empty($options)) {
                $options = " " . $options;
            }

            $meta .= "<link rel=\"stylesheet\" href=\"" . $filePath . "\" type=\"text/css\" />\n";
        }
        return $meta;
    }

    /**
     * Execute les fonctions javascript demandées.
     *
     * @return string
     */
    private function &getMetaExecuteJavascript() {
        $script = "<script type=\"text/javascript\">\n";

        if (!empty($this->javaScriptCode)) {
            $script .= $this->javaScriptCode;
        }

        if (!empty($this->javaScriptJquery)) {
            $script .= "$(document).ready(function(){";
            $script .= $this->javaScriptJquery;
            $script .= "});";
        }

        $script .= "</script>\n";
        return $script;
    }

}
