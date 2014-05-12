<?php
if (!defined("TR_ENGINE_INDEX")) {
    require("secure.class.php");
    Core_Secure::checkInstance();
}

/**
 * Gestionnaire des accès et autorisation.
 *
 * @author Sébastien Villemain
 */
class Core_Access {

    /**
     * Vérifie si le client a les droits suffisant pour acceder au module
     *
     * @param $zoneIdentifiant string module ou page administrateur (module/page) ou id du block sous forme block + Id
     * @param $userIdAdmin string Id de l'administrateur a vérifier
     * @return boolean true le client visé a la droit
     */
    public static function moderate($zoneIdentifiant, $userIdAdmin = "") {
        // Rank 3 exigé !
        if (Core_Session::getInstance()->userRank === 3) {
            // Recherche des droits admin
            $right = self::getAdminRight($userIdAdmin);
            $nbRights = count($right);
            $zone = "";
            $identifiant = "";

            // Si les réponses retourné sont correcte
            if ($nbRights > 0 && self::accessType($zoneIdentifiant, $zone, $identifiant)) {
                // Vérification des droits
                if ($right[0] === "all") { // Admin avec droit suprême
                    return true;
                } else {
                    // Analyse des droits, un par un
                    for ($i = 0; $i <= $nbRights; $i++) {
                        // Droit courant étudié
                        $currentRight = $right[$i];

                        // Si c'est un droit de module
                        if ($zone === "MODULE") {
                            if (is_numeric($currentRight) && $identifiant === $currentRight) {
                                return true;
                            }
                        } else { // Si c'est un droit spécial
                            // Affectation des variables pour le droit courant
                            $zoneIdentifiantRight = $currentRight;
                            $zoneRight = "";
                            $identifiantRight = "";

                            // Vérification de la validité du droit
                            if (self::accessType($zoneIdentifiantRight, $zoneRight, $identifiantRight)) {
                                // Vérification suivant le type de droit
                                if ($zone === "BLOCK") {
                                    if ($zoneRight === "BLOCK" && is_numeric($identifiantRight)) {
                                        if ($identifiant === $identifiantRight) {
                                            return true;
                                        }
                                    }
                                } else if ($zone === "PAGE") {
                                    if ($zoneRight === "PAGE" && $zoneIdentifiant === $zoneIdentifiantRight && $identifiant === $identifiantRight) {
                                        if (Libs_Module::getInstance()->isModule($zoneIdentifiant, $identifiant)) {
                                            return true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Retourne l'erreur d'acces liée au module
     *
     * @param $mod
     * @return string
     */
    public static function &getModuleAccesError($mod) {
        // Recherche des infos du module
        $moduleRank = -2;
        if (Core_Loader::isCallable("Libs_Module")) {
            $moduleRank = Libs_Module::getInstance()->getInfoModule($mod)->getRank();
        }

        $error = ERROR_ACCES_FORBIDDEN;
        // Si on veut le type d'erreur pour un acces
        if ($moduleRank > -2) {
            if ($moduleRank === -1)
                $error = ERROR_ACCES_OFF;
            else if ($moduleRank === 1 && Core_Session::getInstance()->userRank === 0)
                $error = ERROR_ACCES_MEMBER;
            else if ($moduleRank > 1 && Core_Session::getInstance()->userRank < $rank)
                $error = ERROR_ACCES_ADMIN;
        }
        return $error;
    }

    /**
     * Autorise ou refuse l'accès a la ressource cible
     *
     * @param $zoneIdentifiant string block+Id ou module/page.php ou module
     * @param $zoneRank int
     * @return boolean true accès autorisé
     */
    public static function &autorize($zoneIdentifiant, $zoneRank = -2) {
        $access = false;
        // Si ce n'est pas un block ou une page particuliere
        if (substr($zoneIdentifiant, 0, 5) !== "block" && $zoneRank < -1) {
            // Recherche des infos du module
            $moduleInfo = array();
            if (Core_Loader::isCallable("Libs_Module")) {
                $moduleInfo = Libs_Module::getInstance()->getInfoModule($zoneIdentifiant);
            }

            if (!empty($moduleInfo)) {
                $zoneIdentifiant = $moduleInfo['name'];
                $zoneRank = $moduleInfo['rank'];
            }
        }

        if ($zoneRank === 0)
            $access = true; // Accès public
        else if ($zoneRank > 0 && $zoneRank < 3 && Core_Session::getInstance()->userRank >= $zoneRank)
            $access = true; // Accès membre ou admin
        else if ($zoneRank === 3 && self::moderate($zoneIdentifiant))
            $access = true; // Accès admin avec droits
        return $access;
    }

    /**
     * Retourne les droits de l'admin ciblé
     *
     * @param $userIdAdmin string userId
     * @return array liste des droits
     */
    public static function &getAdminRight($userIdAdmin = "") {
        if (!empty($userIdAdmin))
            $userIdAdmin = Exec_Entities::secureText($userIdAdmin);
        else
            $userIdAdmin = Core_Session::getInstance()->userId;

        $admin = array();
        $admin = Core_Sql::getInstance()->getBuffer("getAdminRight");
        if (empty($admin)) { // Si la requête n'est pas en cache
            Core_Sql::getInstance()->select(
            Core_Table::USERS_ADMIN_TABLE, array(
                "rights"), array(
                "user_id = '" . $userIdAdmin . "'")
            );

            if (Core_Sql::getInstance()->affectedRows() > 0) {
                Core_Sql::getInstance()->addArrayBuffer("getAdminRight");
                $admin = Core_Sql::getInstance()->getBuffer("getAdminRight");
            }
        }

        $rights = array();
        if (!empty($admin)) {
            $rights = explode("|", $admin[0]['rights']);
        }
        return $rights;
    }

    /**
     * Identifie le type d'acces lié a l'identifiant entré
     *
     * @param $zoneIdentifiant string module ou page administrateur (module/page) ou id du block sous forme block + Id
     * @param $zone string la zone type trouvée (BLOCK/PAGE/MODULE)
     * @param $identifiant string l'identifiant lié au type trouvé
     * @return boolean true identifiant valide
     */
    public static function &accessType(&$zoneIdentifiant, &$zone, &$identifiant) {
        $access = false;
        if (substr($zoneIdentifiant, 0, 5) === "block") {
            $zone = "BLOCK";
            $identifiant = substr($zoneIdentifiant, 5, strlen($zoneIdentifiant));
            $access = true;
        } else if (($pathPos = strrpos($zoneIdentifiant, "/")) !== false) {
            $module = substr($zoneIdentifiant, 0, $pathPos);
            $page = substr($zoneIdentifiant, $pathPos + 1, strlen($zoneIdentifiant));

            if (Core_Loader::isCallable("Libs_Module") && Libs_Module::getInstance()->isModule($module, $page)) {
                $zone = "PAGE";
                $zoneIdentifiant = $module;
                $identifiant = $page;
                $access = true;
            }
        } else if (!is_numeric($zoneIdentifiant)) {
            // Recherche d'informations sur le module
            $moduleInfo = array();
            if (Core_Loader::isCallable("Libs_Module")) {
                $moduleInfo = Libs_Module::getInstance()->getInfoModule($zoneIdentifiant);
            }

            if (!empty($moduleInfo)) {
                if (is_numeric($moduleInfo['mod_id'])) {
                    $zone = "MODULE";
                    $identifiant = $moduleInfo['mod_id'];
                    $access = true;
                }
            }
        }
        return $access;
    }

    /**
     * Retourne le type d'acces avec la traduction.
     *
     * @param int $rank
     * @return string accès traduit (si possible).
     */
    public static function &getRankAsLitteral($rank) {
        if (!is_numeric($rank)) {
            Core_Secure::getInstance()->throwException("accessRank", null, array(
                "Invalid rank value: " . $rank));
        }

        $rankLitteral = "";

        switch ($rank) {
            case -1:
                $rankLitteral = ACCESS_NONE;
                break;
            case 0:
                $rankLitteral = ACCESS_PUBLIC;
                break;
            case 1:
                $rankLitteral = ACCESS_REGISTRED;
                break;
            case 2:
                $rankLitteral = ACCESS_ADMIN;
                break;
            case 3:
                $rankLitteral = ACCESS_ADMIN_RIGHT;
                break;
            default :
                Core_Secure::getInstance()->throwException("accessRank", null, array(
                    "Numeric rank: " . $rank));
        }

        $rankLitteral = defined($rankLitteral) ? constant($rankLitteral) : $rankLitteral;
        return $rankLitteral;
    }

    /**
     * Liste des niveaux d'accès disponibles.
     *
     * @return array array("numeric" => identifiant int, "letters" => nom du niveau)
     */
    public static function &getRankList() {
        $rankList = array();

        for ($i = -1; $i < 4; $i++) {
            $rankList[] = array(
                "numeric" => $i,
                "letters" => self::getRankAsLitteral($i));
        }
        return $rankList;
    }

}
