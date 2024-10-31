<?php

namespace MyFastApp;

class Storage
{
	private static $last_plugin_myfastapp_mlv_config;
	private static $last_plugin_myfastapp_mlv_config_etag;

	private static $last_plugin_myfastapp_mfa_config;
	private static $last_plugin_myfastapp_mfa_config_etag;


	public static function initialize()
	{
		update_option("plugin_myfastapp_version", TOA_MYFASTAPP_VERSION);

		delete_option("plugin_myfastapp_external_api_uri");

		$config = get_option("plugin_myfastapp_live_config");
		if ($config != null) update_option("plugin_myfastapp_mlv_config", $config);
		delete_option("plugin_myfastapp_live_config");

		$config = get_option("plugin_myfastapp_app_config");
		if ($config != null) update_option("plugin_myfastapp_mfa_config", $config);
		delete_option("plugin_myfastapp_app_config");

		self::$last_plugin_myfastapp_mlv_config		 = get_option("plugin_myfastapp_mlv_config"		);
		self::$last_plugin_myfastapp_mlv_config_etag = get_option("plugin_myfastapp_mlv_config_etag");
		self::$last_plugin_myfastapp_mfa_config		 = get_option("plugin_myfastapp_mfa_config"		);
		self::$last_plugin_myfastapp_mfa_config_etag = get_option("plugin_myfastapp_mfa_config_etag");
	}

	public static function get_option($name)
	{
		if ($name === "plugin_myfastapp_mlv_config"		) return self::$last_plugin_myfastapp_mlv_config;
		if ($name === "plugin_myfastapp_mlv_config_etag") return self::$last_plugin_myfastapp_mlv_config_etag;		
		if ($name === "plugin_myfastapp_mfa_config"		) return self::$last_plugin_myfastapp_mfa_config;
		if ($name === "plugin_myfastapp_mfa_config_etag") return self::$last_plugin_myfastapp_mfa_config_etag;		
		return get_option($name);
	}

	public static function delete_option($name)
	{
		return delete_option($name);
	}

	public static function update_option($name, $data)
	{
		update_option($name, $data);
		if ($name === "plugin_myfastapp_mlv_config"		) self::$last_plugin_myfastapp_mlv_config	   = $data;
		if ($name === "plugin_myfastapp_mlv_config_etag") self::$last_plugin_myfastapp_mlv_config_etag = $data;
		if ($name === "plugin_myfastapp_mfa_config"		) self::$last_plugin_myfastapp_mfa_config	   = $data;
		if ($name === "plugin_myfastapp_mfa_config_etag") self::$last_plugin_myfastapp_mfa_config_etag = $data;
	}
}
