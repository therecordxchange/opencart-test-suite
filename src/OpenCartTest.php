<?php

abstract class OpenCartTest extends \PHPUnit\Framework\TestCase {

    protected $registry;
    protected $front;
    protected static $tablesCreated = false;

    public function __construct(string $name = '')
    {
        parent::__construct($name);

        $this->init();
    }

    protected static function isAdmin()
    {
        return preg_match('/^Admin/', get_called_class()) == true;
    }

    protected static function getConfigurationPath()
    {
        if (self::isAdmin()) {
            return CONFIG_ADMIN;
        } else {
            return CONFIG_CATALOG;
        }
    }

    public function __get($key)
    {
        return $this->registry->get($key);
    }

    public function __set($key, $value)
    {
        $this->registry->set($key, $value);
    }

    public function loadConfiguration()
    {

        if (defined('HTTP_SERVER')) {
            return;
        }

        // either load admin or catalog config.php		
        $path = self::getConfigurationPath();
//print "path:$path\n";		exit;
        // Configuration
        if (file_exists($path)) {
            require_once($path);
        } else {
            throw new Exception('OpenCart has to be installed first!');
        }
    }

    public function init() : void
    {
        $this->loadConfiguration();

        // VirtualQMOD
        if (defined('USE_VQMOD')) {
            require_once(APP_ROOT . '/vqmod/vqmod.php');
            VQMod::bootup();

            // VQMODDED Startup
            require_once(VQMod::modCheck(DIR_SYSTEM . 'startup.php'));
        } else {

            // Startup
            require_once(DIR_SYSTEM . 'startup.php');

            // Application Classes
            require_once(modification(DIR_SYSTEM . 'library/customer.php'));
            require_once(modification(DIR_SYSTEM . 'library/affiliate.php'));
            require_once(modification(DIR_SYSTEM . 'library/currency.php'));
            require_once(modification(DIR_SYSTEM . 'library/tax.php'));
            require_once(modification(DIR_SYSTEM . 'library/weight.php'));
            require_once(modification(DIR_SYSTEM . 'library/length.php'));
            require_once(modification(DIR_SYSTEM . 'library/cart.php'));
        }

        // Registry
        $this->registry = new Registry();

        // Loader
        $loader = new Loader($this->registry);
        $this->registry->set('load', $loader);

        // Config
        $config = new Config();
        $this->registry->set('config', $config);
//print "DB:" .  DB_DRIVER . " " . DB_HOSTNAME . " " . DB_USERNAME . " " . DB_PASSWORD . " " . DB_DATABASE; exit;
        // Database
        $db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
        $this->registry->set('db', $db);

        // Recreating the database
        if (!self::$tablesCreated) {
            $lines = null;

            if(defined('SQL_FILE')) {
                $file = SQL_FILE;
                $lines = file($file);
            }

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
                /**
                $db->query("SET @@session.sql_mode = 'MYSQL40'");

                $db->query("DELETE FROM `" . DB_PREFIX . "user` WHERE user_id = '1'");
                $db->query("INSERT INTO `" . DB_PREFIX . "user` SET user_id = '1', user_group_id = '1', username = 'admin', salt = '" . $db->escape($salt = substr(md5(uniqid(rand(), true)), 0, 9)) . "', password = '" . $db->escape(sha1($salt . sha1($salt . sha1('admin')))) . "', status = '1', email = '" . $db->escape('admin@localhost') . "', date_added = NOW()");

                $db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'config_email'");
                $db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `group` = 'config', `key` = 'config_email', value = '" . $db->escape('admin@localhost') . "'");

                $db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'config_url'");
                $db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `group` = 'config', `key` = 'config_url', value = '" . $db->escape($_SERVER['HTTP_HOST']) . "'");

                $db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'config_encryption'");
                $db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `group` = 'config', `key` = 'config_encryption', value = '" . $db->escape(md5(mt_rand())) . "'");
                 * 
                 */
                $db->query("UPDATE `" . DB_PREFIX . "product` SET `viewed` = '0'");
            }

            self::$tablesCreated = true;
        }

        // assume a HTTP connection
        $sql = "SELECT * FROM " . DB_PREFIX . "store WHERE REPLACE(`url`, 'www.', '') = '" . $db->escape('http://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'";
        $store_query = $db->query($sql);

        if ($store_query->num_rows) {
            $config->set('config_store_id', $store_query->row['store_id']);
        } else {
            $config->set('config_store_id', 0);
        }

        // Settings
        $query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '0' OR store_id = '" . (int) $config->get('config_store_id') . "' ORDER BY store_id ASC");

        foreach ($query->rows as $setting) {
            if (!$setting['serialized']) {
                $config->set($setting['key'], $setting['value']);
            } else {
                $config->set($setting['key'], unserialize($setting['value']));
            }
        }

        if (!$store_query->num_rows) {
            $config->set('config_url', HTTP_SERVER);
            $config->set('config_ssl', HTTPS_SERVER);
        }

        // Url
        $url = new Url($config->get('config_url'), $config->get('config_secure') ? $config->get('config_ssl') : $config->get('config_url'));
        $this->registry->set('url', $url);

        // Request
        $request = new Request();
        $this->registry->set('request', $request);

        // Response - Using Test Response - Redirects are disabled.
        $response = new TestResponse();

        $response->addHeader('Content-Type: text/html; charset=utf-8');
        $response->setCompression($config->get('config_compression'));
        $this->registry->set('response', $response);

        // Cache
        $cache = new Cache('file', -1);
        $this->registry->set('cache', $cache);

        // Session
        $session = new Session();
        $this->registry->set('session', $session);

        // TRX Custom - filemanager provider
        $filemanager = new TrxFileManager($this->registry);
        $this->registry->set('filemanager', $filemanager);

        // Language Detection
        $languages = array();

        $query = $db->query("SELECT * FROM `" . DB_PREFIX . "language` WHERE status = '1'");

        foreach ($query->rows as $result) {
            $languages[$result['code']] = $result;
        }

        $detect = '';

        if (isset($request->server['HTTP_ACCEPT_LANGUAGE']) && $request->server['HTTP_ACCEPT_LANGUAGE']) {
            $browser_languages = explode(',', $request->server['HTTP_ACCEPT_LANGUAGE']);

            foreach ($browser_languages as $browser_language) {
                foreach ($languages as $key => $value) {
                    if ($value['status']) {
                        $locale = explode(',', $value['locale']);

                        if (in_array($browser_language, $locale)) {
                            $detect = $key;
                        }
                    }
                }
            }
        }

        if (isset($session->data['language']) && array_key_exists($session->data['language'], $languages) && $languages[$session->data['language']]['status']) {
            $code = $session->data['language'];
        } elseif (isset($request->cookie['language']) && array_key_exists($request->cookie['language'], $languages) && $languages[$request->cookie['language']]['status']) {
            $code = $request->cookie['language'];
        } elseif ($detect) {
            $code = $detect;
        } else {
            $code = $config->get('config_language');
        }

        if (!isset($session->data['language']) || $session->data['language'] != $code) {
            $session->data['language'] = $code;
        }

        if (!isset($request->cookie['language']) || $request->cookie['language'] != $code) {
            setcookie('language', $code, time() + 60 * 60 * 24 * 30, '/', $request->server['HTTP_HOST']);
        }

        $config->set('config_language_id', $languages[$code]['language_id']);
        $config->set('config_language', $languages[$code]['code']);

        // Language
        $language = new Language($languages[$code]['directory']);
        //$language->load($languages[$code]['filename']);
        $language->load($languages[$code]['directory']);

        $this->registry->set('language', $language);

        // Document
        $this->registry->set('document', new Document());

        // Affiliate
        $this->registry->set('affiliate', new Affiliate($this->registry));

        if (isset($request->get['tracking'])) {
            setcookie('tracking', $request->get['tracking'], time() + 3600 * 24 * 1000, '/');
        }

        // Currency
        $this->registry->set('currency', new Currency($this->registry));

        // Tax
        $this->registry->set('tax', new Tax($this->registry));

        // Weight
        $this->registry->set('weight', new Weight($this->registry));

        // Length
        $this->registry->set('length', new Length($this->registry));

        // Event
        $this->registry->set('event', new Event($this->registry));

        // Mail
        $this->registry->set('mail', new TestMail($this->registry));

        // Encryption
        $this->registry->set('encryption', new Encryption($config->get('config_encryption')));

        // Log
        $this->registry->set('log', new Log($config->get('config_error_filename')));

        // Front Controller
        $this->front = new Front($this->registry);

        //Codeigniter Helpers
        foreach (glob(DIR_SYSTEM . "helper/*_helper.php") as $filename) {
            require_once($filename);
        }

        $this->request->server['REMOTE_ADDR'] = '127.0.0.1';

        if (self::isAdmin()) {
            $this->request->get['token'] = 'token';
            $this->session->data['token'] = 'token';

            $user = new User($this->registry);
            $this->registry->set('user', $user);
            $user->login(ADMIN_USERNAME, ADMIN_PASSWORD);

            $this->front->addPreAction(new Action('common/login/check'));
            $this->front->addPreAction(new Action('error/permission/check'));
        } else {
            $this->registry->set('cart', new Cart($this->registry));
            $this->registry->set('customer', new Customer($this->registry));

            $this->front->addPreAction(new Action('common/seo_url'));
        }
       
        // Maintenance Mode
        // $this->front->addPreAction(new Action('common/maintenance'));
    }

    public function customerLogin($user, $password, $override = false)
    {
        $logged = $this->customer->login($user, $password, $override);

        //required for ACL 
        //@see oc_events
        //@see catalog/controller/trx/auth.php
        $this->event->trigger('post.customer.login');
        
        if (!$logged) {
            throw new Exception('Could not login customer');
        }
    }

    public function customerLogout()
    {
        if ($this->customer->isLogged()) {
            $this->customer->logout();
        }
    }

    // legal hack to access a private property, this is only neccessary because
    // my pull request was rejected: https://github.com/opencart/opencart/pull/607
    public function getOutput()
    {

        $class = new ReflectionClass("Response");
        $property = $class->getProperty("output");
        $property->setAccessible(true);
        return $property->getValue($this->response);
    }

    public function dispatchAction($route)
    {

        // Router
        if (!empty($route)) {
            $action = new Action($route);
        } else {
            $action = new Action('common/home');
        }

        // Set request:
        $request = $this->registry->get('request');
        $request->get['route'] = $route;
        $this->registry->set('request', $request);

        // Dispatch
        $this->front->dispatch($action, new Action('error/not_found'));

        return $this->response;
    }

    public function loadModelByRoute($route)
    {
        $this->load->model($route);
        $parts = explode("/", $route);

        $model = 'model';

        foreach ($parts as $part) {
            $model .= "_" . $part;
        }

        return $this->$model;
    }
}
