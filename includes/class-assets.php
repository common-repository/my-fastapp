<?php

namespace MyFastApp;

require_once "class-helper.php";

use MyFastApp\Helper;

/**
 * Scripts and Styles Class
 */
class Assets
{

    function __construct()
    {
        if (is_admin()) {
            add_action("admin_enqueue_scripts", [$this, "enqueue"], 5);
        }
    }

    /**
     * Register our app scripts and styles
     *
     * @return void
     */
    public function enqueue()
    {
        $screen = get_current_screen();
        $base = $screen->base;
        if (strpos($base, "page_my-fastapp") !== false) {

            $pluginSettingsJson = json_encode(array(

                "siteUrl"        => Helper::get_wp_site_url(),
                "siteUid"        => Helper::get_wp_site_uid(),
                "restUrl"        => Helper::get_wp_rest_url(),

                "internalAPIUrl" => Helper::get_wp_rest_url(),
                "externalAPIUrl" => Helper::get_az_rest_url(),

                "nonce" => wp_create_nonce("wp_rest"),
                "platformVersion" => "WordPress " . get_bloginfo("version"),
                "platform" => "Wordpress",
                "wp_locale" => get_locale(),
                "pluginVersion" => Storage::get_option("plugin_myfastapp_version"),    // TOA_MYFASTAPP_VERSION
                "assetsUrl" => TOA_MYFASTAPP_ASSETS_URL,
                "adminEmail" => Storage::get_option('admin_email'),
                "apiKey" => unserialize(Storage::get_option("plugin_myfastapp_apitoken")),
            ));

            wp_register_script("dummy-handle-header", "");
            wp_enqueue_script("dummy-handle-header");
            wp_add_inline_script(
                "dummy-handle-header",
                "var wpApAdminSettings = " . $pluginSettingsJson
            );

            $this->enqueue_scripts($this->get_scripts());
            $this->enqueue_styles($this->get_styles());
        }
    }

    /**
     * Register scripts
     *
     * @param array $scripts
     *
     * @return void
     */
    private function enqueue_scripts($scripts)
    {
        foreach ($scripts as $handle => $script) {
            $deps = isset($script["deps"]) ? $script["deps"] : false;
            $in_footer = isset($script["in_footer"]) ? $script["in_footer"] : false;
            $version = isset($script["version"]) ? $script["version"] : TOA_MYFASTAPP_VERSION;

            wp_register_script($handle, $script["src"], $deps, $version, $in_footer);
            wp_enqueue_script($handle, $script["src"], "", mt_rand(10, 1000), true);
        }
    }

    /**
     * Get all registered scripts
     *
     * @return array
     */
    public function get_scripts()
    {
        $scripts = [];
        if (in_array($_SERVER["REMOTE_ADDR"], array("127.0.0.1"))) {
            $scripts["bundle.js"] = [
                "src" => "http://localhost:3000/static/js/bundle.js",
                "in_footer" => true
            ];
            $scripts["vendors~main.chunk.js"] = [
                "src" => "http://localhost:3000/static/js/vendors~main.chunk.js",
                "in_footer" => true
            ];
            $scripts["main.chunk.js"] = [
                "src" => "http://localhost:3000/static/js/main.chunk.js",
                "in_footer" => true
            ];
        } else {
            $jsfiles = scandir(TOA_MYFASTAPP_ASSETS_PATH . "/frontend/build/static/js/");
            foreach ($jsfiles as $filename) {
                if (strpos($filename, ".js") && (!strpos($filename, ".js.map") || !strpos($filename, ".txt"))) {
                    $scripts[$filename] = [
                        "src" => TOA_MYFASTAPP_ASSETS_URL . "/frontend/build/static/js/" . $filename,
                        "version" => filemtime(TOA_MYFASTAPP_ASSETS_PATH . "/frontend/build/static/js/" . $filename),
                        "in_footer" => true
                    ];
                }
            }
        }

        return $scripts;
    }

    /**
     * Register styles
     *
     * @param array $styles
     *
     * @return void
     */
    public function enqueue_styles($styles)
    {
        foreach ($styles as $handle => $style) {
            $deps = isset($style["deps"]) ? $style["deps"] : false;
            wp_register_style($handle, $style["src"], $deps, TOA_MYFASTAPP_VERSION);
            wp_enqueue_style($handle, $style["src"]);
        }
    }

    /**
     * Get registered styles
     *
     * @return array
     */
    public function get_styles()
    {
        $styles = [];
        if (in_array($_SERVER["REMOTE_ADDR"], array("127.0.0.1"))) {
        } else {
            $CSSfiles = scandir(TOA_MYFASTAPP_ASSETS_PATH . "/frontend/build/static/css/");
            foreach ($CSSfiles as $filename) {
                if (strpos($filename, ".css") && !strpos($filename, ".css.map")) {
                    $styles[$filename] = ["src" => TOA_MYFASTAPP_ASSETS_URL . "/frontend/build/static/css/" . $filename];
                }
            }
        }

        return $styles;
    }
}
