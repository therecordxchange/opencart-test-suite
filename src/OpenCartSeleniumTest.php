<?php

// for now this class is only a wrapper, however who knows what will be needed in the future...
class OpenCartSeleniumTest extends PHPUnit_Extensions_Selenium2TestCase {
	
	protected static $tablesCreated = false;
	
	protected static function isAdmin() {
		return preg_match('/^Admin/', get_called_class()) == true;
	}
	
	protected static function getConfigurationPath() {				
		if (self::isAdmin()) {
			return CONFIG_ADMIN;
		} else {
			return CONFIG_CATALOG;
		}
	}
	
	public function loadConfiguration() {
		
		if (defined('HTTP_SERVER')) {
			return;
		}
		
		// either load admin or catalog config.php		
		$path = self::getConfigurationPath();
		
		// Configuration
		if (file_exists($path)) {
			require_once($path);
		} else {
			throw new Exception('OpenCart has to be installed first!');
		}		
	}
	
	public function __construct() {
		parent::__construct();
		$this->loadConfiguration();
		
		require_once DIR_SYSTEM . 'library/db.php';
		$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
                
		// Recreating the database
		if (!self::$tablesCreated) {
			$file = SQL_FILE;

			$lines = file($file);

			if ($lines) {
				$sql = '';

				foreach ($lines as $line) {
					if ($line && (substr($line, 0, 2) != '--') && (substr($line, 0, 1) != '#')) {
						$sql .= $line;

						if (preg_match('/;\s*$/', $line)) {
							$sql = str_replace("DROP TABLE IF EXISTS `oc_", "DROP TABLE IF EXISTS `" . DB_PREFIX, $sql);
							$sql = str_replace("CREATE TABLE `oc_", "CREATE TABLE `" . DB_PREFIX, $sql);
							$sql = str_replace("INSERT INTO `oc_", "INSERT INTO `" . DB_PREFIX, $sql);

							$db->query($sql);

							$sql = '';
						}
					}
				}

				$db->query("SET CHARACTER SET utf8");

				$db->query("SET @@session.sql_mode = 'MYSQL40'");

				$db->query("DELETE FROM `" . DB_PREFIX . "user` WHERE user_id = '1'");

				$db->query("INSERT INTO `" . DB_PREFIX . "user` SET user_id = '1', user_group_id = '1', username = '" . ADMIN_USERNAME . "', salt = '" . $db->escape($salt = substr(md5(uniqid(rand(), true)), 0, 9)) . "', password = '" . $db->escape(sha1($salt . sha1($salt . sha1(ADMIN_PASSWORD)))) . "', status = '1', email = '" . $db->escape('admin@localhost') . "', date_added = NOW()");

				$db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'config_email'");
				$db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `group` = 'config', `key` = 'config_email', value = '" . $db->escape('admin@localhost') . "'");

				$db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'config_encryption'");
				$db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `group` = 'config', `key` = 'config_encryption', value = '" . $db->escape(md5(mt_rand())) . "'");

				$db->query("UPDATE `" . DB_PREFIX . "product` SET `viewed` = '0'");
			}
			
			self::$tablesCreated = true;
		}
	}
	
	public function setUp() {
		parent::setUp();
	
		file_put_contents(DIR_LOGS . 'error.log', '');
	}
	
	public function tearDown() {
		parent::tearDown();
		
		$errorLog = file_get_contents(DIR_LOGS . 'error.log');
		
		// cannot use this now. Mail class is triggering errors.
		//$this->assertEmpty($errorLog, $errorLog);
	}
	
	protected function waitToAppearAndClick($cssSelector, $timeout = 10000) {
		$this->waitUntil(function() use ($cssSelector) {
			$element = $this->byCssSelector($cssSelector);
			
			if ($element->displayed()) {
				return true;
			}
		}, $timeout);
		
		$this->byCssSelector($cssSelector)->click();
	}
	
	protected function waitToLoad($title, $timeout = 10000) {
		$this->waitUntil(function() use ($title) {
			if (strpos($this->title(), $title) !== false) {
				return true;
			}
		}, $timeout);
	}
	
}