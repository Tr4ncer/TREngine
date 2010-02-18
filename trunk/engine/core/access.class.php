<?php
if (!defined("TR_ENGINE_INDEX")) {
	require("secure.class.php");
	new Core_Secure();
}

/**
 * Gestionnaire d'acc�s
 * 
 * @author Sebastien Villemain
 *
 */
class Core_Access  {
	
	/**
	 * V�rifie si le client a les droits suffisant pour acceder au module
	 * 
	 * @param $zoneIdentifiant String module ou page administrateur (module/page) ou id du block sous forme block + Id
	 * @param $userIdAdmin String Id de l'administrateur a v�rifier
	 * @return boolean true le client vis� a la droit
	 */
	public static function moderate($zoneIdentifiant, $userIdAdmin = "") {
		// Rang 3 exig� !
		if (Core_Session::$userRang == 3) {
			// Recherche des droits admin
			$right = self::getAdminRight($userIdAdmin);
			$nbRights = count($right);
			$zone = "";
			$identifiant = "";
			
			// Si les r�ponses retourn� sont correcte
			if ($nbRights > 0 && self::accessType($zoneIdentifiant, $zone, $identifiant)) {
				// V�rification des droits
				if ($right[0] == "all") { // Admin avec droit supr�me
					return true;
				} else {
					// Analyse des droits, un par un
					for ($i = 0; $i <= $nbRights; $i++) {
						// Droit courant �tudi�
						$currentRight = $right[$i];
						
						// Si c'est un droit de module
						if ($zone == "MODULE") {
							if (is_numeric($currentRight) && $identifiant == $currentRight) {
								return true;
							}
						} else { // Si c'est un droit sp�cial
							// Affectation des variables pour le droit courant
							$zoneIdentifiantRight = $currentRight;
							$zoneRight = "";
							$identifiantRight = "";
							
							// V�rification de la validit� du droit
							if (self::accessType($zoneIdentifiantRight, $zoneRight, $identifiantRight)) {
								// V�rification suivant le type de droit
								if ($zone == "BLOCK") {
									if ($zoneRight == "BLOCK" && is_numeric($identifiantRight)) {
										if ($identifiant == $identifiantRight) {
											return true;
										}
									}
								} else if ($zone == "PAGE") {
									if ($zoneRight == "PAGE" && $zoneIdentifiant == $zoneIdentifiantRight && $identifiant == $identifiantRight) {
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
	 * Retourne l'erreur d'acces li�e au module
	 * 
	 * @param $mod
	 * @return String
	 */
	public static function &getModuleAccesError($mod) {
		// Recherche des infos du module
		$moduleRang = -2;
		if (Core_Loader::isCallable("Libs_Module")) {
			$moduleRang = Libs_Module::getInstance()->getRang($mod);
		}
		
		$error = ERROR_ACCES_FORBIDDEN;
		// Si on veut le type d'erreur pour un acces
		if ($moduleRang > -2) {
			if ($moduleRang == -1) $error = ERROR_ACCES_OFF;
			else if ($moduleRang == 1 && Core_Session::$userRang == 0) $error = ERROR_ACCES_MEMBER;
			else if ($moduleRang > 1 && Core_Session::$userRang < $rang) $error = ERROR_ACCES_ADMIN;
		}
		return $error;
	}
	
	/**
	 * Autorise ou refuse l'acc�s a la ressource cible
	 * 
	 * @param $zoneIdentifiant String block+Id ou module/page.php ou module
	 * @param $zoneRang int
	 * @return boolean true acc�s autoris�
	 */
	public static function &autorize($zoneIdentifiant, $zoneRang = -2) {
		$access = false;
		// Si ce n'est pas un block ou une page particuliere
		if (substr($zoneIdentifiant, 0, 5) != "block" && $zoneRang < -1) {
			// Recherche des infos du module
			$moduleInfo = array();
			if (Core_Loader::isCallable("Libs_Module")) {
				$moduleInfo = Libs_Module::getInstance()->getInfoModule($zoneIdentifiant);
			}
			
			if (!empty($moduleInfo)) {
				$zoneIdentifiant = $moduleInfo['name'];
				$zoneRang = $moduleInfo['rang'];
			}
		}
		
		if ($zoneRang == 0) $access = true; // Acc�s public
		else if ($zoneRang > 0 && $zoneRang < 3 && Core_Session::$userRang >= $zoneRang) $access = true; // Acc�s membre ou admin
		else if ($zoneRang == 3 && self::moderate($zoneIdentifiant)) $access = true; // Acc�s admin avec droits
		return $access;
	}
	
	/**
	 * Retourne les droits de l'admin cibl�
	 * 
	 * @param $userIdAdmin String userId
	 * @return array liste des droits
	 */
	public static function &getAdminRight($userIdAdmin = "") {
		if (!empty($userIdAdmin)) $userIdAdmin = Exec_Entities::secureText($userIdAdmin);
		else $userIdAdmin = Core_Session::$userId;
		
		$admin = array();
		$admin = Core_Sql::getBuffer("getAdminRight");
		if (empty($admin)) { // Si la requ�te n'est pas en cache
			Core_Sql::select(
				Core_Table::$USERS_ADMIN_TABLE,
				array("rights"),
				array("user_id = '" . $userIdAdmin . "'")
			);
			Core_Sql::addBuffer("getAdminRight");
			$admin = Core_Sql::getBuffer("getAdminRight");
		}
		
		$rights = array();
		if (!empty($admin)) {
			$rights = explode("|", $admin[0]->rights);
		}
		return $rights;
	}
	
	/**
	 * Identifie le type d'acces li� a l'identifiant entr�
	 * 
	 * @param $zoneIdentifiant String module ou page administrateur (module/page) ou id du block sous forme block + Id 
	 * @param $zone String la zone type trouv�e (BLOCK/PAGE/MODULE)
	 * @param $identifiant String l'identifiant li� au type trouv�
	 * @return boolean true identifiant valide
	 */
	public static function &accessType(&$zoneIdentifiant, &$zone, &$identifiant) {
		$access = false;
		if (substr($zoneIdentifiant, 0, 5) == "block") {
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
}

?>