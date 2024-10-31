<?php

namespace MyFastApp;

/**
 * Admin Pages Handler
 */
class Admin
{

    public function __construct()
    {
        add_action("admin_menu", [$this, "admin_menu"]);
    }

    /**
     * Register our menu page
     *
     * @return void
     */
    public function admin_menu()
    {
        global $submenu;

        $capability = "edit_pages";
        $menu_slug = "my-fastapp";

        $hook = add_menu_page(
         // __("My FastAPP", "my-fastapp") . " (DEV)",
         // __("My FastAPP", "my-fastapp") . " (DEV)",
            __("My FastAPP"),
            __("My FastAPP"),
            $capability,
            $menu_slug,
            [$this, "plugin_page"],
            "dashicons-smartphone"
        );

        if (current_user_can($capability)) {
            $submenu[$menu_slug][] = array(__("Settings", "my-fastapp"), $capability, "admin.php?page=" . $menu_slug . "#/settings");
            $submenu[$menu_slug][] = array(__("Builds", "my-fastapp"), $capability, "admin.php?page=" . $menu_slug . "#/builds");
        }

        add_action("load-" . $hook, [$this, "init_hooks"]);
    }

    /**
     * Initialize our hooks for the admin page
     *
     * @return void
     */
    public function init_hooks()
    {
    }

    /**
     * Load scripts and styles for the app
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        wp_enqueue_media();
    }

    /**
     * Render our admin page
     *
     * @return void
     */
    public function plugin_page()
    {
        echo "<div id='mfa-root' class='mfa-container'></div>";
    }
}
