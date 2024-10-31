<?php

namespace MyFastApp;

require_once ABSPATH . "/wp-admin/includes/misc.php";
require_once ABSPATH . "/wp-admin/includes/file.php";
require_once ABSPATH . "/wp-admin/includes/class-wp-upgrader-skin.php";
require_once ABSPATH . "/wp-admin/includes/class-wp-upgrader.php";
require_once ABSPATH . "/wp-admin/includes/class-theme-upgrader.php";
require_once ABSPATH . "/wp-includes/class-wp-error.php";

require_once "class-helper.php";
require_once "class-storage.php";
require_once "class-controller.php";
require_once "class-error.php";

use MyFastApp\ErrorMessage;
use MyFastApp\Helper;
use MyFastApp\Storage;

class REST_API_Controller
{
	private $RESTroutes = [

		/*
		[route								, methods	, callback								, permission_callback]
		*/

		// APP entries
		["/mlv-config"						, "GET"		, "intercept_manifest_json"		, "appCaller_check"	],
		["/mfa-config"						, "GET"		, "intercept_manifest_json"		, "appCaller_check"	],

		["/mlv-config-old"				, "GET"		, "get_mlv_configuration"			, "appCaller_check"	],
		["/mfa-config-old"				, "GET"		, "get_mfa_configuration"			, "appCaller_check"	],

	//	["/userauth"						, "POST"		, "authenticate_user"				, "appCaller_check"	],

		// PLG entries
		["/mfa-config2"					, "GET"		, "get_mfa_configuration"			, "plgCaller_check"	],
		["/settings"						, "GET"		, "get_settings"						, "plgCaller_check"	],
		["/settings"						, "POST"		, "update_settings"					, "plgCaller_check"	],
		["/menuItems"						, "GET"		, "get_menu_items"					, "plgCaller_check"	],
		["/menuItems"						, "POST"		, "update_menu_items"				, "plgCaller_check"	],
		["/resetSettings"					, "DELETE"	, "reset_settings"					, "plgCaller_check"	],
		["/checkPendingConfigChanges"	, "GET"		, "check_pending_config_changes"	, "plgCaller_check"	],
		["/updateLiveConfig"				, "POST"		, "update_live_configuration"		, "plgCaller_check"	],
		["/updateAppConfig"				, "POST"		, "update_app_configuration"		, "plgCaller_check"	],

	];

	/* --- Checks ------------------ */

	public function appCaller_check($request)
	{
		$hash = $request->get_header("MyFastAppToken0");
		$data = Helper::get_wp_site_uid();
		$pkey = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAt/sSOXjnYqcfSasl2Weo\nzt7ljcwtWpoYtZRnK4puW5o4TySpmi4v9JNhXnNGV/gXzDDTL90ls57HorMLQuLF\n81bbGGOdn+tQPbxJXTLH/maA9zDPP3TxKTawOA5l5gSIYrxoXhQW/4Obvpnjs4Lw\n4GDKgxlAIV+V5QfP8UeCSXLhgRxn0TEnB2P7HtgiIKtum65zMjPNEX/KcOvmQjcA\n2bAL+Bv1mJG3f9C/F7ggxrWYC9VAdqbUD+M468NkBNHMnIiX0hgPTq3GLjilKlUV\nOyq9pqkVIPsOs0gvN7rMcqAhnpJ9OA3XEui9FP1l9QVNZmIf7JDyxSg4FI/2mLdl\nIwIDAQAB\n-----END PUBLIC KEY-----";
		$test = openssl_verify($data, base64_decode($hash), openssl_pkey_get_public($pkey), OPENSSL_ALGO_SHA512);
		return $test === 1;
	}

	public function plgCaller_check($request)
	{
		$test = is_user_logged_in() && current_user_can('administrator');
		return $test;
	}

	public function get_mlv_configuration($request)
	{
		try {
			$config = json_decode(Storage::get_option("plugin_myfastapp_mlv_config"));
			if (empty($config)) $config = json_decode("{}");
			return rest_ensure_response($config);
		}
		catch (\Exception $ex) {
			write_log_error($ex->getMessage());
			return rest_ensure_response(new ErrorMessage(__("Unable to get live config", "my-fastapp")));
		}
	}

	public function get_mfa_configuration($request)
	{
		try {
			$config = json_decode(Storage::get_option("plugin_myfastapp_mfa_config"));
			if (empty($config)) $config = json_decode("{}");
			return rest_ensure_response($config);
		}
		catch (\Exception $ex) {
			write_log_error($ex->getMessage());
			return rest_ensure_response(new ErrorMessage(__("Unable to get app config", "my-fastapp")));
		}
	}

	function intercept_manifest_json()
	{
		$request_uri				 = $_SERVER['REQUEST_URI'];
		$is_manifest_mlv_request = strpos($request_uri, '/mlv-config') !== false;
		$is_manifest_mfa_request = strpos($request_uri, '/mfa-config') !== false;
		if ($is_manifest_mlv_request || $is_manifest_mfa_request) {

			$json_option_name = $is_manifest_mlv_request ? "plugin_myfastapp_mlv_config"		 : "plugin_myfastapp_mfa_config"		 ;
			$etag_option_name = $is_manifest_mlv_request ? "plugin_myfastapp_mlv_config_etag" : "plugin_myfastapp_mfa_config_etag";

			$request_etag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : "";
			$request_etag = str_replace('\"', '', $request_etag);
			$json			  = null;
			$etag			  = null;

			try {
				$etag = Storage::get_option($etag_option_name);
				if ($etag === null) {
					$json = "{}";
					$etag = md5($json);
				}
				else if ($etag === $request_etag) {
					http_response_code(304);
					exit;
				}
				else {
					$json = Storage::get_option($json_option_name);
				}
			}
			catch (\Exception $ex) {
				write_log_error($ex->getMessage());
				Helper::send_problem_json(404, "Resource Not Found", "The requested resource could not be found on this server.", "manifest");
				// __("Unable to get live config", "my-fastapp")
			}

			if ($json !== null) {
				$protocol = $_SERVER['SERVER_PROTOCOL'];
				header($protocol . " 200 OK");
				header('Content-Type: application/json');
				header("ETag: \"" . $etag . "\"");
				if ($json !== "{}") {
					$rsaKey				= "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAt/sSOXjnYqcfSasl2Weo\nzt7ljcwtWpoYtZRnK4puW5o4TySpmi4v9JNhXnNGV/gXzDDTL90ls57HorMLQuLF\n81bbGGOdn+tQPbxJXTLH/maA9zDPP3TxKTawOA5l5gSIYrxoXhQW/4Obvpnjs4Lw\n4GDKgxlAIV+V5QfP8UeCSXLhgRxn0TEnB2P7HtgiIKtum65zMjPNEX/KcOvmQjcA\n2bAL+Bv1mJG3f9C/F7ggxrWYC9VAdqbUD+M468NkBNHMnIiX0hgPTq3GLjilKlUV\nOyq9pqkVIPsOs0gvN7rMcqAhnpJ9OA3XEui9FP1l9QVNZmIf7JDyxSg4FI/2mLdl\nIwIDAQAB\n-----END PUBLIC KEY-----";
					$aesKey				= openssl_random_pseudo_bytes(32);															// 32 bytes = 256-bit AES key
					$iv					= openssl_random_pseudo_bytes(16);															// 16 bytes = 128-bit IV
					$configEncrypted	= openssl_encrypt($json, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);		// encrypt the data using AES-256-CBC
					$aesKeyEncrypted  ; openssl_public_encrypt($aesKey, $aesKeyEncrypted, $rsaKey);						// encrypt the AES key using the public RSA key
					$json				= json_encode([ 'd' => base64_encode($configEncrypted), 'k' => base64_encode($aesKeyEncrypted), 'i' => base64_encode($iv) ]);
				}

				echo $json;		
				exit;
			}

		}
	}

	/* --- Common Methods ------------------ */

	 public function __construct()
	 {
		  $this->namespace = "myfastapp/v1";
		  add_filter( 'rest_authentication_errors', array( $this, 'allow_public_access_to_specific_endpoint' ) );
	 }

	 public function allow_public_access_to_specific_endpoint( $result ) {
		 // Allow public access to the endpoint 'wp-json/my-plugin/v1/open-endpoint'
		 if ( strpos( $_SERVER['REQUEST_URI'], '/myfastapp/v1/' ) !== false ) {
			  return true; // Bypass authentication for this endpoint
		 }

		 // For all other endpoints, follow the default authentication rules
		 return $result;
	}

	 public function register_routes()
	 {
		  foreach ($this->RESTroutes as $route) {
				$this->register_REST_route($route);
		  }
	 }

	 private function register_REST_route($route)
	 {
		  register_rest_route(
				$this->namespace,
				$route[0],
				array(
					 array(
						  "methods" => $route[1],
						  "callback" => array($this, $route[2]),
						  "permission_callback" => array($this, $route[3]),
					 )
				)
		  );
	 }

	 /* --- Security Methods ------------------ */

	 public function authenticate_user($request)
	 {
		  try {

				$credentials = json_decode($request->get_body(), true);

				$encryptedUsername = $credentials["username"];
				$encryptedPassword = $credentials["password"];

				/*
				$salt = $this->get_today_salt();
				$username = $this->decrypt_text($salt, $encryptedUsername);
				$password = $this->decrypt_text($salt, $encryptedPassword);
				*/

				$username = $encryptedUsername;
				$password = $encryptedPassword;

				$user = wp_authenticate($username, $password);

				if ($user == null) {
					 return new \WP_Error('user_unknown', 'User is unknown or invalid', array('status' => 401));
				}
				if (is_wp_error($user)) {
					 return new \WP_Error('wrong_credentials', 'User is not authorized', array('status' => 401));
				}

				// {
				//     "data": {
				//         "ID": "3",
				//         "user_login": "",
				//         "user_pass": "",
				//         "user_nicename": "",
				//         "user_email": "",
				//         "user_url": "",
				//         "user_registered": "2022-02-10 20:43:32",
				//         "user_activation_key": "",
				//         "user_status": "",
				//         "display_name": ""
				//     },
				//     "ID": 3,
				//     "caps": {
				//         "contributor": true
				//     },
				//     "cap_key": "wp_capabilities",
				//     "roles": [
				//         "contributor"
				//     ],
				//     "allcaps": {
				//         "edit_posts": true,
				//         "read": true,
				//         "level_1": true,
				//         "level_0": true,
				//         "delete_posts": true,
				//         "contributor": true
				//     },
				//     "filter": null
				// }

				$response = [
					 "UserId" => $user->ID,
					 "Username" => $user->user_login,
					 "UserEmail" => $user->user_email,
					 "UserDisplayName" => $user->display_name,
					 "UserRoles" => $user->roles,
				];

				return rest_ensure_response($response);
		  } catch (\Exception $ex) {
				write_log_error($ex->getMessage());
				return rest_ensure_response(new ErrorMessage(__("Unable to authenticate user", "my-fastapp")));
		  }
	 }

	 /* ---------------------------------------- */

		public function safeDecode($json2)
		{
			$data2 = json_decode($json2);
			$data1 = $data2->envelope;
			$json1 = $this->hexToString($data1);
			return $json1;
		}

		public function hexToString($hex)
		{
			$str = '';
			for ($i = 0; $i < strlen($hex); $i += 2) $str .= chr(hexdec(substr($hex, $i, 2)));
			return $str;
		}

	 /* --- Settings Methods  ------------------ */

	 public function get_settings($request)
	 {
		  try {
				$settings = json_decode(Storage::get_option("plugin_myfastapp_settings"));
				if (empty($settings))
					 $settings = json_decode("{}");

				return rest_ensure_response($settings);
		  } catch (\Exception $ex) {
				write_log_error($ex->getMessage());
				return (new ErrorMessage(__("Unable to obtain settings", "my-fastapp")));
		  }
	 }

		public function update_settings($request)
		{
			try {
				$json = $this->safeDecode($request->get_body());
				Storage::update_option("plugin_myfastapp_settings", $json);
			}
			catch (\Exception $ex) {
				write_log_error($ex->getMessage());
				return rest_ensure_response(new ErrorMessage(__("Unable to update settings", "my-fastapp")));
			}
		}

	 public function get_menu_items($request)
	 {
		  try {
				$menuItems = json_decode(Storage::get_option("plugin_myfastapp_items"));
				if (empty($menuItems))
					 $menuItems = json_decode("[]");

				return rest_ensure_response($menuItems);
		  } catch (\Exception $ex) {
				write_log_error($ex->getMessage());
				return rest_ensure_response(new ErrorMessage(__("Unable to obtain menu items", "my-fastapp")));
		  }
	 }

	 public function update_menu_items($request)
	 {
		  try {
				$json = $this->safeDecode($request->get_body());
				Storage::update_option("plugin_myfastapp_items", $json);
		  } catch (\Exception $ex) {
				write_log_error($ex->getMessage());
				return rest_ensure_response(new ErrorMessage(__("Unable to update menu items", "my-fastapp")));
		  }
	 }

	 public function reset_settings($request)
	 {
		  try {
				Storage::update_option("plugin_myfastapp_settings", NULL);
				Storage::update_option("plugin_myfastapp_items", NULL);
		  } catch (\Exception $ex) {
				write_log_error($ex->getMessage());
				return rest_ensure_response(new ErrorMessage(__("Unable to update settings", "my-fastapp")));
		  }
	 }

	 public function update_live_configuration()
	 {
		  try {
				$settings = json_decode(Storage::get_option("plugin_myfastapp_settings"), true);
				$menuItems = json_decode(Storage::get_option("plugin_myfastapp_items"), true);
				$config  = $this->create_configuration($settings, $menuItems);
				$json    = json_encode($config);
				$etag		= md5($json);
				Storage::update_option("plugin_myfastapp_mlv_config"		, $json);
				Storage::update_option("plugin_myfastapp_mlv_config_etag", $etag);
				$storedFile = json_decode("{}");
				return rest_ensure_response($storedFile);

		  } catch (\Exception $ex) {
				write_log_error($ex->getMessage());
				return rest_ensure_response(new ErrorMessage(__("Unable to update live config", "my-fastapp")));
		  }
	 }

	 public function update_app_configuration()
	 {
		  try {
				$settings = json_decode(Storage::get_option("plugin_myfastapp_settings"), true);
				$menuItems = json_decode(Storage::get_option("plugin_myfastapp_items"), true);
				$config  = $this->create_configuration($settings, $menuItems);
				$json    = json_encode($config);
				$etag		= md5($json);
				Storage::update_option("plugin_myfastapp_mfa_config"		, $json);
				Storage::update_option("plugin_myfastapp_mfa_config_etag", $etag);
				$storedFile = json_decode("{}");
				return rest_ensure_response($storedFile);

		  } catch (\Exception $ex) {
				write_log_error($ex->getMessage());
				return rest_ensure_response(new ErrorMessage(__("Unable to update app config", "my-fastapp")));
		  }
	 }

	 public function check_pending_config_changes()
	 {
		  try {

				$settings = json_decode(Storage::get_option("plugin_myfastapp_settings"), true);
				if (empty($settings))
					 $settings = json_decode("{}");

				$menuItems = json_decode(Storage::get_option("plugin_myfastapp_items"), true);
				if (empty($menuItems))
					 $menuItems = json_decode("[]");

				$currentConfig  = json_encode($this->create_configuration($settings, $menuItems));

				$liveConfig = Storage::get_option("plugin_myfastapp_mlv_config");
				$isLiveConfigUpdated = $currentConfig == $liveConfig;

				$appConfig = Storage::get_option("plugin_myfastapp_mfa_config");
				$isAppConfigUpdated = $currentConfig == $appConfig;

				$configStatus = [
					 "isLiveConfigUpdated" => $isLiveConfigUpdated,
					 "isAppConfigUpdated" => $isAppConfigUpdated
				];
				return rest_ensure_response($configStatus);
		  } catch (\Exception $ex) {
				write_log_error($ex->getMessage());
				return rest_ensure_response(new ErrorMessage(__("Unable to check pending config", "my-fastapp")));
		  }
	 }

	 private function create_configuration($settings, $menuItems)
	 {

		  if ($settings == json_decode("{}", true) || $settings == json_decode("{}", false) || $menuItems == json_decode("[]", true) || $menuItems == json_decode("[]", false))
				return [];

		  if (!array_key_exists("appTitle", $settings))
				return [];

		  $result = [
				"Version" => 1,
				"ApiVersion" => "v1",
				"Data" => [
					 "ShareAppAndroid" => $this->get_string_or_default("shareAppAndroid", $settings),
					 "ShareAppIos" => $this->get_string_or_default("shareAppIos", $settings),
					 "Environment" => [
						  "BundleId" => $this->get_string_or_default("bundleId", $settings),
						  "VersionName" => $this->get_string_or_default("versionNumber", $settings),
						  "AppPrimaryColor" => $this->get_color_or_default("appPrimaryColor", $settings),
						  "AppSecondaryColor" => $this->get_color_or_default("appSecondaryColor", $settings),
						  "EntryTextColor" => $this->get_color_or_default("entryTextColor", $settings),
						  "EntryBackgroundColor" => $this->get_color_or_default("entryBackgroundColor", $settings),
						  "ErrorTextColor" => $this->get_color_or_default("errorTextColor", $settings),
						  "AppTitleColor" => $this->get_color_or_default("appTitleColor", $settings),
						  "AppTitle" => $this->get_string_or_default("appTitle", $settings),
						  "AppMenuHeaderTextColor" => $this->get_color_or_default("colorHeaderMenu", $settings),
						  "AppMenuHeaderBackgroundColor" => $this->get_color_or_default("menuTitleBackgroundColor", $settings),
						  "AppMenuHeaderBackgroundImage" => $this->get_string_or_default("menuHeaderBackgroundImage", $settings),
						  "AppMenuPrimaryColor" => $this->get_color_or_default("menuItemsTextColor", $settings),
						  "AppMenuSecondaryColor" => $this->get_color_or_default("menuItemsBackgroundColor", $settings),
						  "AppMenuBackgroundColor" => $this->get_color_or_default("menuBackgroundColor", $settings),
						  "AppMenuTitle" => $this->get_string_or_default("menuTitle", $settings),
						  "AppMenuDisabled" => !$this->get_bool_or_default("enableAppMenu", $settings),
						  "AppMenuLayout" => $this->get_value_or_default("appMenuLayout", $settings, "Drawer"),
						  "HomeUrl" => $this->get_string_or_default("homeUrl", $settings),
						  "AppSplashScreenUrl" => $this->get_string_or_default("appSplashImage", $settings),
						  "OneSignalAppId" => $this->get_string_or_default("oneSignalAppId", $settings),
						  "SendGridApiKey" => $this->get_string_or_default("sendGripApiKey", $settings),
						  "UseLightStatusBar" => $this->get_bool_or_default("useLightStatusBar", $settings),
						  "UseUserLogin" => $this->get_bool_or_default("useUserLogin", $settings),
						  "UserLoginType" => $this->get_value_or_default("userLoginType", $settings, "Always"),
						  "LoginPageImage" => $this->get_string_or_default("loginPageImage", $settings),
					 ],
					 "Menus" => [],
				],
		  ];

		  $parsedMenuitems = [];
		  foreach ($menuItems as $menuItem) {
				if ($menuItem["isVisible"] == true) {
					 $parsedMenuItem = [
						  "Title" => $this->get_string_or_default("title", $menuItem),
						  "BackgroundColor" => $this->get_color_or_default("backgroundColor", $menuItem),
						  "BackgroundImage" => $this->get_string_or_default("backgroundImage", $menuItem),
						  "TextColor" => $this->get_color_or_default("textColor", $menuItem),
						  "FontSize" => $this->get_string_or_default("fontSize", $menuItem),
						  "FontStyle" => $this->get_string_or_default("fontStyle", $menuItem),
						  "Thickness" => $this->get_value_or_default("type", $menuItem, "OnLineWebView") == "Separator" ? $menuItem["props"]["thickness"] : 0,
						  "IconName" => "\u" . $this->get_value_or_default("iconCode", $menuItem, "f000"),
						  "ActionType" => $this->get_value_or_default("type", $menuItem, "OnLineWebView"),
						  "TargetParam" => $this->get_menuItem_targetParam($menuItem),
						  "Props" => array_key_exists("props", $menuItem) ? json_encode($menuItem["props"]) : null,
						  "TargetPlatform" => $this->get_value_or_default("platform", $menuItem, "ios, android"),
						  "IsProtected" => $this->get_bool_or_default("isProtected", $menuItem, false),
						  "HideIcon" => $this->get_bool_or_default("hideIcon", $menuItem, false),
						  "HideTitle" => $this->get_bool_or_default("hideTitle", $menuItem, false),
						  // We are forcing this to support some features
						  //"SendCustomHeaders" => array_key_exists("sendCustomHeaders", $menuItem) ? $menuItem["sendCustomHeaders"] : false
						  "SendCustomHeaders" => true
					 ];

					 if (
						  $menuItem["type"] == "EmptyLine"
						  || $menuItem["type"] == "Separator"
					 ) {

						  $parsedMenuItem["Title"] = null;
						  $parsedMenuItem["IconName"] = null;
					 }

					 $parsedMenuitems[] = $parsedMenuItem;
				}
		  }

		  $result["Data"]["Menus"] = $parsedMenuitems;

		  return $result;
	 }

	 private function get_menuItem_targetParam($menuItem)
	 {
		  if ($menuItem["props"] == null)
				return null;

		  switch ($menuItem["type"]) {
				case "HomePage":
					 return $this->url_or_wp_url($menuItem);
				case "BusinessCard":
					 return $menuItem["props"]["templateId"];
				case "MailTo":
					 return $menuItem["props"]["email"];
				case "EmptyLine":
					 return null;
				case "OpenURL":
					 return $this->url_or_wp_url($menuItem);
				case "PhoneCall":
					 return $menuItem["props"]["phoneNumber"];
				case "PhotoEditor":
					 return null;
				case "PhotoSend":
					 return $menuItem["props"]["email"];
				case "QR":
					 return $menuItem["props"]["url"];
				case "Separator":
					 return null;
				case "SendSms":
					 return $menuItem["props"]["phoneNumber"];
				case "OnLineWebView":
					 return $this->url_or_wp_url($menuItem);
		  }
	 }

	 private function url_or_wp_url($menuItem)
	 {
		  return $menuItem["props"]["isAbsoluteUrl"] ?
				$menuItem["props"]["url"] : (array_key_exists("wpUrl", $menuItem["props"]) ? $menuItem["props"]["wpUrl"] : $menuItem["props"]["url"]);
	 }

	 private function get_string_or_default($settingsKey, $settings)
	 {
		  return array_key_exists($settingsKey, $settings) ? $settings[$settingsKey] : null;
	 }

	 private function get_number_or_default($settingsKey, $settings)
	 {
		  return array_key_exists($settingsKey, $settings) ? $settings[$settingsKey] : 0;
	 }

	 private function get_bool_or_default($settingsKey, $settings)
	 {
		  return array_key_exists($settingsKey, $settings) ? $settings[$settingsKey] : false;
	 }

	 private function get_color_or_default($settingsKey, $settings)
	 {
		  return array_key_exists($settingsKey, $settings) ? $this->hex_color_to_xamarin($settings[$settingsKey]) : "#FFFFFFFF";
	 }

	 private function get_value_or_default($settingsKey, $settings, $default)
	 {
		  return array_key_exists($settingsKey, $settings) ? $settings[$settingsKey] : $default;
	 }

	 private function hex_color_to_xamarin($color)
	 {
		  if (strlen($color) == 4)
				return $color;

		  $rgb = substr($color, 1, 6);
		  $alpha = substr($color, 7, 2);

		  return sprintf("#%s%s", $alpha, $rgb);
	 }

	 private function decrypt_text($salt, $cipherText)
	 {
		  $key = $salt;
		  $iv =  $salt;
		  return preg_replace('/\x03/', '', openssl_decrypt(base64_decode($cipherText), 'AES-128-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv));
	 }
}

if (!function_exists('write_log')) {
	 function write_log($log)
	 {
		  if (is_array($log) || is_object($log)) {
				error_log(print_r($log, true));
		  } else {
				error_log($log);
		  }
	 }

	 function write_log_error($log)
	 {
		  write_log("MyFastError: " . $log);
	 }
}
