<?php
/*
Plugin Name: My FastAPP
Plugin URI: https://www.myfastapp.com/
Description: This plugin allows you to use your WordPress site as a backend to create your mobile application for iOS and Android. Configure and build your mobile applications directly from the WordPress site.
Version: 2.0.1
Author: Teamonair s.r.l.
Author URI: https://teamonair.com/
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: my-fastapp
Domain Path: /languages
*/

/**
 * Copyright (c) 2024 Teamonair s.r.l. (email: dev@teamonair.com). All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */

if (!defined("ABSPATH")) exit;

require_once "includes/class-helper.php";
require_once "includes/class-storage.php";

use MyFastApp\Helper;
use MyFastApp\Storage;

final class MyFastApp
{

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public $version = "2.0.1";

	public $activationTimeout = 45;

	private $container = array();

	public function __construct()
	{
		$this->define_constants();

		register_activation_hook  (__FILE__, array($this, "activate"  ));
		register_deactivation_hook(__FILE__, array($this, "deactivate"));

		add_action("admin_init"			, array($this, "removeDefaultStyles"	 ));
		add_action("rest_api_init"		, array($this, "initRestController"		 ));
		add_action("plugins_loaded"	, array($this, "init_plugin"				 ));
	}

	public function define_constants()
	{
		define("TOA_MYFASTAPP_VERSION", $this->version);
		define("TOA_MYFASTAPP_FILE", __FILE__);
		define("TOA_MYFASTAPP_PATH", dirname(TOA_MYFASTAPP_FILE));
		define("TOA_MYFASTAPP_INCLUDES", TOA_MYFASTAPP_PATH . "/includes");
		define("TOA_MYFASTAPP_URL", plugins_url("", TOA_MYFASTAPP_FILE));
		define("TOA_MYFASTAPP_ASSETS_URL", TOA_MYFASTAPP_URL . "/assets");
		define("TOA_MYFASTAPP_ASSETS_PATH", TOA_MYFASTAPP_PATH . "/assets");
	}

	 public static function init()
	 {
		  static $instance = false;

		  if (!$instance) {
				$instance = new MyFastApp();
		  }

		  return $instance;
	 }

	 public function removeDefaultStyles()
	 {
		  //wp_deregister_style("wp-admin");
	 }

	 public function initRestController()
	 {
		  require_once TOA_MYFASTAPP_INCLUDES . "/class-controller.php";

		  $controller = new MyFastApp\REST_API_Controller();
		  $controller->register_routes();
	 }

	 /**
	  * Magic getter to bypass referencing plugin.
	  *
	  * @param $prop
	  *
	  * @return mixed
	  */
	 public function __get($prop)
	 {
		  if (array_key_exists($prop, $this->container)) {
				return $this->container[$prop];
		  }

		  return $this->{$prop};
	 }

	 /**
	  * Magic isset to bypass referencing plugin.
	  *
	  * @param $prop
	  *
	  * @return mixed
	  */
	 public function __isset($prop)
	 {
		  return isset($this->{$prop}) || isset($this->container[$prop]);
	 }

	 /**
	  * Load the plugin after all plugis are loaded
	  *
	  * @return void
	  */
	 public function init_plugin()
	 {
		  $this->includes();
		  $this->init_hooks();
		  Storage::Initialize();
	 }

	 public function includes()
	 {
		  require_once TOA_MYFASTAPP_INCLUDES . "/class-assets.php";

		  if ($this->is_request("admin")) {
				require_once TOA_MYFASTAPP_INCLUDES . "/class-admin.php";
		  }
	 }

	 /**
	  * What type of request is this?
	  *
	  * @param string $type admin, ajax, cron or frontend.
	  *
	  * @return bool
	  */
	 private function is_request($type)
	 {
		  switch ($type) {
				case "admin":
					 return is_admin();

				case "ajax":
					 return defined("DOING_AJAX");

				case "rest":
					 return defined("REST_REQUEST");

				case "cron":
					 return defined("DOING_CRON");

				case "frontend":
					 return (!is_admin() || defined("DOING_AJAX")) && !defined("DOING_CRON");
		  }
	 }

	 public function init_hooks()
	 {
		  add_action("init", array($this, "init_classes"));

		  add_action("init", array($this, "localization_setup"));
	 }

	 public function activate()
	 {
		 if (empty(Storage::get_option("plugin_myfastapp_apitoken"))) {

			  try {
					$siteDomain = Helper::get_wp_site_uid();
					$saltDomain = bin2hex(random_bytes(16));
					$apiKey     = strtolower($saltDomain . "@" . $siteDomain);

					Storage::update_option("plugin_myfastapp_installed", time());
					Storage::update_option("plugin_myfastapp_apitoken", serialize($apiKey));

					// Convert old option plugin_myfastapp_menu_item => plugin_myfastapp_items
					$items = Storage::get_option("plugin_myfastapp_menu_item");
					if (!empty(json_decode($items, 512))) {
						Storage::update_option("plugin_myfastapp_items", $items);
					}

					$items = Storage::get_option("plugin_myfastapp_items");
					if (!empty(json_decode($items, 512))) {
						Storage::update_option("plugin_myfastapp_items", $items);
					} else {
						Storage::update_option("plugin_myfastapp_items", json_encode([]));
					}

			  } catch (Exception $ex) {
					var_dump($ex);
			  }
		 }
	 }

	 public function deactivate()
	 {
		 /*
		  Storage::delete_option("***");
		  */
	 }

	 public function init_classes()
	 {
		  if ($this->is_request("admin")) {
				$this->container["admin"] = new MyFastApp\Admin();
		  }

		  if ($this->is_request("rest")) {
				$this->container["rest"] = new MyFastApp\REST_API_Controller();
		  }

		  $this->container["assets"] = new MyFastApp\Assets();
	 }

	 public function localization_setup()
	 {
		  load_plugin_textdomain("my-fastapp", false, dirname(plugin_basename(__FILE__)) . "/languages/");
	 }
}

$baseplugin = MyFastApp::init();
