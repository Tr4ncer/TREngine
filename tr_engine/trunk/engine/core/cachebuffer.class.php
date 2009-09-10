<?php
if (!defined("TR_ENGINE_INDEX")) {
	require("secure.class.php");
	new Core_Secure();
}

class Core_CacheBuffer {
	
	/**
	 * Instance d'un protocole d�j� initialis�
	 * 
	 * @var mixed
	 */
	private static $execProtocol = false;
	
	/**
	 * R��criture du cache
	 * 
	 * @var array
	 */
	private static $writingCache = array();
	
	/**
	 * Ecriture du cache a la suite
	 * 
	 * @var array
	 */
	private static $addCache = array();
	
	/**
	 * Suppression du cache
	 * 
	 * @var array
	 */
	private static $removeCache = array();
	
	/**
	 * Mise � jour de derniere modification du cache
	 * 
	 * @var array
	 */
	private static $updateCache = array();
	
	/**
	 * Tableau avec les chemins des differentes rubriques
	 * 
	 * @var array
	 */
	private static $sectionDir = array(
		"tmp" => "tmp",
		"log" => "tmp/log",
		"sessions" => "tmp/sessions",
		"lang" => "tmp/lang",
		"menus" => "tmp/menus",
		"modules" => "tmp/modules",
		"fileList" => "tmp/fileList"
	);
	
	/**
	 * Nom de la section courante
	 * 
	 * @var String
	 */
	private static $sectionName = "";
	
	/**
	 * Etat des modes de gestion des fichiers
	 * 
	 * @var arry
	 */
	private static $modeActived = array (
		"php" => false,
		"ftp" => false,
		"sftp" => false
	);
	
	/**
	 * Donn�e du ftp
	 * 
	 * @var array
	 */
	private static $ftp = array();
	
	/**
	 * Modifier le nom de la section courante
	 * 
	 * @param $sectionName
	 */
	public static function setSectionName($sectionName = "") {
		if (!empty($sectionName) && isset(self::$sectionDir[$sectionName])) {
			self::$sectionName = $sectionName;
		} else {
			self::$sectionName = "tmp";
		}
	}
	
	/**
	 * Retourne le chemin de la section courante
	 * 
	 * @return String
	 */
	private static function &getSectionPath() {
		// Si pas de section, on met par d�faut
		if (empty(self::$sectionName)) self::setSectionName();
		// Chemin de la section courante
		return self::$sectionDir[self::$sectionName];
	}
	
	/**
	 * Nom de la section courante
	 * 
	 * @return String Section courante
	 */
	public static function &getSectionName() {
		return self::$sectionName;
	}
	
	/**
	 * Ecriture du fichier cache
	 * 
	 * @param $path chemin complet
	 * @param $content donn�e a �crire
	 * @param $overWrite boolean true r��criture complete, false �criture a la suite
	 */
	public static function writingCache($path, $content, $overWrite = true) {
		if (!is_string($content)) {
			$content = self::linearizeCache($content);
		}
		// Mise en forme de la cles
		$key = self::encodePath(self::getSectionPath(). "/" . $path);
		// Ajout dans le cache
		if ($overWrite) self::$writingCache[$key] = $content;
		else self::$addCache[$key] = $content;
	}
	
	/**
	 * Supprime un fichier ou supprime tout fichier trop vieux
	 * 
	 * @param $dir chemin vers le fichier ou le dossier
	 * @param $timeLimit limite de temps
	 */
	public static function removeCache($dir, $timeLimit = 0) {
		// Configuration du path
		if (!empty($dir)) $dir = "/" . $dir;
		$dir = self::encodePath(self::getSectionPath() . $dir);
		self::$removeCache[$dir] = $timeLimit;
	}
	
	/**
	 * Parcours r�cursivement le dossier cache courant afin de supprimer les fichiers trop vieux
	 * Nettoie le dossier courant du cache
	 * 
	 * @param $timeLimit La limite de temps
	 */
	public static function cleanCache($timeLimit) {
		// V�rification de la validit� du checker
		if (!self::checked($timeLimit)) {
			// Mise � jour ou creation du fichier checker
			self::touchChecker();
			// Suppression du cache p�rim�
			self::removeCache("", $timeLimit);
		}
	}
	
	/**
	 * Mise � jour de la date de derni�re modification
	 * 
	 * @param $path chemin vers le fichier cache
	 */
	public static function touchCache($path) {
		self::$updateCache[self::encodePath(self::getSectionPath() . "/" . $path)] = time();
	}
	
	/**
	 * V�rifie si le fichier est en cache
	 * 
	 * @param $path chemin vers le fichier cache
	 * @return boolean true le fichier est en cache
	 */
	public static function &cached($path) {
		return is_file(self::getPath($path));
	}
	
	/**
	 * Date de derni�re modification
	 * 
	 * @param $path chemin vers le fichier cache
	 * @return int Unix timestamp ou false
	 */
	public static function &cacheMTime($path) {
		return filemtime(self::getPath($path));
	}
	
	/**
	 * V�rifie la pr�sence du checker et sa validit�
	 * 
	 * @return boolean true le checker est valide
	 */
	public static function &checked($timeLimit = 0) {
		if (self::cached("checker.txt")) {
			// On a demand� un comparaison de temps
			if ($timeLimit > 0) {
				if ($timeLimit < self::checkerMTime()) return true;
			} else {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Ecriture du checker
	 */
	private static function writingChecker() {
		self::writingCache("checker.txt", "1");
	}
	
	/**
	 * Mise � jour du checker
	 */
	public static function touchChecker() {
		if (!self::cached("checker.txt")) self::writingChecker();
		else self::touchCache("checker.txt");
	}
	
	/**
	 * Date de derni�re modification du checker
	 * 
	 * @return int Unix timestamp ou false
	 */
	public static function &checkerMTime() {
		return self::cacheMTime("checker.txt");
	}
	
	/**
	 * Encode un chemin
	 * 
	 * @param String $path
	 * @return String
	 */
	private static function &encodePath($path) {
		return $path;
	}
	
	/**
	 * D�code un chemin
	 * 
	 * @param String $encodePath
	 * @return String
	 */
	private static function &decodePath($encodePath) {
		return $encodePath;
	}
	
	/**
	 * Retourne le chemin complet vers le fichier cache
	 * 
	 * @param $path chemin du fichier
	 * @return String chemin complet
	 */
	public static function &getPath($path) {
		return TR_ENGINE_DIR . "/" . self::getSectionPath() . "/" . $path;
	}
	
	/**
	 * Capture le cache cibl� dans un tableau
	 * 
	 * @param $path Chemin du cache
	 * @return mixed
	 */
	public static function &getCache($path) {
		// R�glage avant capture
		$variableName = self::$sectionName;
		// Rend la variable global a la fonction
		${$variableName} = "";
		
		// Capture du fichier
		if (self::cached($path)) {
			require(self::getPath($path));
		}
		return ${$variableName};
	}
	
	/**
	 * Recherche si le cache a besoin de g�n�re une action
	 * 
	 * @param array $required
	 * @return boolean true action demand�e
	 */
	private static function &cacheRequired($required) {
		if (is_array($required) && count($required) > 0) {
			return true;
		}
		return false;
	}
	
	/**
	 * Test si le chemin est celui d'un dossier
	 * 
	 * @param $path
	 * @return boolean true c'est un dossier
	 */
	public static function &isDir($path) {
		$pathIsDir = false;
		
		if (substr($path, -1) == "/") {
			// Nettoyage du path qui est enfaite un dir
			$path = substr($path, 0, -1);
			$pathIsDir = true;
		} else {
			// Recherche du bout du path
			$pos = strrpos("/", $path);
			if (!is_bool($pos)) {
				$last = substr($path, $pos);
			} else {
				$last = $path;
			}
			
			// Si ce n'est pas un fichier (avec ext.)
			if (strpos($last, ".") === false) {
				$pathIsDir = true;
			}
		}
		return $pathIsDir;
	}
	
	/**
	 * Ecriture des ent�tes de fichier
	 * 
	 * @param $pathFile
	 * @param $content
	 * @return String $content
	 */
	public static function &getHeader($pathFile, $content) {
		$ext = substr($pathFile, -3);
		// Ent�te des fichier PHP
		if ($ext == "php") {
			// Recherche du dossier parent
			$dirBase = "";
			$nbDir = count(explode("/", $pathFile));
			for($i = 1; $i < $nbDir; $i++) { $dirBase .= "../"; }
			
			// Ecriture de l'ent�te
			$content = "<?php\n"
			. "if (!defined(\"TR_ENGINE_INDEX\")){"
			. "if(!class_exists(\"Core_Secure\")){"
			. "include(\"" . $dirBase . "engine/core/secure.class.php\");"
			. "}new Core_Secure();}"
			. "// Generated on " . date('Y-m-d H:i:s') . "\n"
			. $content
			. "\n?>";
		}
		return $content;
	}
	
	/**
	 * Retourne une chaine de carat�re l'integalit� d'un tableau
	 * 
	 * @param $array array
	 * @param $lastKey String
	 * @return String
	 */
	public static function &linearizeCache($array, $lastKey = "") {
		$content = "";
		foreach($array as $key => $value) {
			if (is_array($value)) {
				$lastKey .= "['" . $key . "']";
				$content .= self::linearizeCache($value, $lastKey);
			} else {
				$content .= "$" . self::getSectionName() . $lastKey . "['" . $key . "'] = \"" . $value . "\"; ";
			}
		}
		return $content;
	}
	
	/**
	 * Active les modes de cache disponible
	 * 
	 * @param $modes array
	 */
	public static function setModeActived($modes = array()) {
		if (!is_array($modes)) $modes = array($modes);

		foreach($modes as $mode) {
			if (isset(self::$modeActived[$mode])) {
				self::$modeActived[$mode] = true;
			}
		}
	}
	
	/**
	 * Injecter les donn�es du FTP
	 * 
	 * @param array
	 */
	public static function setFtp($ftp = array()) {
		self::$ftp = $ftp;
	}
	
	/**
	 * Retourne le listing avec uniquement les fichiers pr�sent
	 * 
	 * @param $dirPath
	 * @return array
	 */
	public static function &listNames($dirPath) {
		self::setSectionName("fileList");
		$fileName = str_replace("/", "_", $dirPath) . ".php";
		if (Core_CacheBuffer::cached($fileName)) {
			return self::getCache($fileName);
		} else {
			$execProtocol = self::getExecProtocol();
			if ($execProtocol != null) {
				$dirList = $execProtocol->listNames($dirPath);
				self::writingCache($fileName, $dirList);
				return $dirList;
			}
		}
		return array();
	}
	
	/**
	 * D�marre et retourne le protocole du cache
	 * 
	 * @return mixed
	 */
	private static function &getExecProtocol() {
		if (self::$execProtocol == false) {
			if (self::$modeActived['php']) {
				// D�marrage du gestionnaire de fichier
				Core_Loader::classLoader("Libs_FileManager");
				self::$execProtocol = new Exec_FileManager();
			} else if (self::$modeActived['ftp']) {
				// D�marrage du gestionnaire FTP
				Core_Loader::classLoader("Libs_FtpManager");
				self::$execProtocol = new Exec_FtpManager();
				self::$execProtocol->setFtp(self::$ftp);
			} else if (self::$modeActived['sftp']) {
				// D�marrage du gestionnaire SFTP
				Core_Loader::classLoader("Libs_SftpManager");
				self::$execProtocol = new Exec_SftpManager();
				self::$execProtocol->setFtp(self::$ftp);
			} else {
				Core_Exception::setException("No protocol actived for cache.");
				return null;
			}
		}
		return self::$execProtocol;
	}
	
	/**
	 * Execute la routine du cache
	 */
	public static function valideCacheBuffer() {
		// Si le cache a besoin de g�n�rer une action
		if (self::cacheRequired(self::$removeCache)
			|| self::cacheRequired(self::$writingCache)
			|| self::cacheRequired(self::$addCache)
			|| self::cacheRequired(self::$updateCache)) {
			// Protocole a utiliser
			$execProtocol = self::getExecProtocol();
			
			if ($execProtocol != null) {
				// Suppression de cache demand�e
				if (self::cacheRequired(self::$removeCache)) {
					foreach(self::$removeCache as $dir => $timeLimit) {
						$execProtocol->removeCache($dir, $timeLimit);
					}
				}
				
				// Ecriture de cache demand�e
				if (self::cacheRequired(self::$writingCache)) {
					foreach(self::$writingCache as $path => $content) {
						$execProtocol->writingCache($path, $content, true);
					}
				}
				
				// Ecriture � la suite de cache demand�e
				if (self::cacheRequired(self::$addCache)) {
					foreach(self::$addCache as $path => $content) {
						$execProtocol->writingCache($path, $content, false);
					}
				}
				
				// Mise � jour de cache demand�e
				if (self::cacheRequired(self::$updateCache)) {
					foreach(self::$updateCache as $path => $updateTime) {
						$execProtocol->touchCache($path, $updateTime);
					}
				}
			}
			// Destruction du gestionnaire
			unset($execProtocol);
		}
	}
}
?>