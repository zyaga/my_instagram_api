<?php

/**
 * @package My Instagram API
 */
/*
Plugin Name: My Instagram API
Plugin URI: www.zyaga.com
Description: Adds your Instagram posts to the WordPress REST API.
Version: 1.0
Author: Drew Kochanowski @ Zyaga
Author URI: https://zyaga.com/
Text Domain: my_instagram_api
*/

if (!class_exists('My_Instagram_API')) {
    class My_Instagram_API
    {
        private $apiHost = "graph.facebook.com";
        private $apiVersion = "v5.0";
        private $name = "my_instagram_api";
        private $version = "v1";

        /**
         * Hook into the admin menu and REST API init
         */
        public function __construct()
        {
            // Hook into the admin menu
            add_action('admin_menu', array($this, 'create_plugin_settings_page'));
            add_action('admin_init', array($this, 'setup_sections'));
            add_action('admin_init', array($this, 'setup_fields'));
            add_action('rest_api_init', function () {
                register_rest_route($this->name . '/' . $this->version, '/posts', array(
                    'methods' => 'GET',
                    'callback' => array($this, 'get_posts'),
                ));
            });
        }

        /**
         * Set up our sections on the settings page
         *
         * @return void
         */
        public function setup_sections()
        {
            add_settings_section(
                $this->name . '_account_settings',
                'Account Settings',
                false,
                'my_instagram_api_fields'
            );
        }

        /**
         * Set up our fields for the sections
         *
         * @return void
         */
        public function setup_fields()
        {
            add_settings_field(
                $this->name . '_account_id',
                'Instagram Account ID',
                array($this, 'account_id_field_callback'),
                $this->name . '_fields',
                $this->name . '_account_settings'
            );
            register_setting($this->name . '_fields', $this->name . '_account_id');

            add_settings_field(
                $this->name . '_access_token',
                'Facebook Access Token',
                array($this, 'access_token_field_callback'),
                $this->name . '_fields',
                $this->name . '_account_settings'
            );
            register_setting($this->name . '_fields', $this->name . '_access_token');
        }

        /**
         * Callback for account id field
         *
         * @param [type] $arguments
         *
         * @return void
         */
        public function account_id_field_callback($arguments)
        {
            echo '<input name="' . $this->name . '_account_id" id="' . $this->name . '_account_id" type="text" size="50" value="' . get_option($this->name . '_account_id') . '" />';
        }


        /**
         * Callback for access token field
         *
         * @param [type] $arguments
         *
         * @return void
         */
        public function access_token_field_callback($arguments)
        {
            echo '<input name="' . $this->name . '_access_token" id="' . $this->name . '_access_token" type="text" size="50" value="' . get_option($this->name . '_access_token') . '" />';
        }

        /**
         * Create the plugin settings page under the general settings menu
         *
         * @return void
         */
        public function create_plugin_settings_page()
        {
            // Add the menu item and page
            $page_title = 'My Instagram API Settings';
            $menu_title = 'My Instagram API';
            $capability = 'manage_options';
            $slug = $this->name . '_fields';
            $callback = array($this, 'plugin_settings_page_content');
            $icon = 'dashicons-admin-plugins';
            $position = 100;
            add_submenu_page('options-general.php', $page_title, $menu_title, $capability, $slug, $callback);
        }

        /**
         * The content for the settings page
         *
         * @return void
         */
        public function plugin_settings_page_content()
        {

            echo '
            <div class="wrap">
                <h2>My Instagram API Settings</h2>
                <form method="post" action="options.php">
        ';

            settings_fields($this->name . '_fields');
            do_settings_sections($this->name . '_fields');

            $url = get_bloginfo('url') . '/wp-json/' . $this->name . '/' . $this->version . '/posts';
            echo "You can find your Instagram posts here: <a target='_blank' href='{$url}'>{$url}</a>";

            submit_button();

            echo '
                </form>
            </div>
        ';
        }

        /**
         * Get our users instagram posts
         *
         * @return void
         */
        function get_posts()
        {
            // Settings
            $accountID = get_option($this->name . '_account_id');
            $accessToken = get_option($this->name . '_access_token');
            $howOftenToRefresh = "2 seconds";
            $postFields = array(
                'caption',
                'children',
                'comments',
                'comments_count',
                'id',
                'ig_id',
                'is_comment_enabled',
                'like_count',
                'media_type',
                'media_url',
                'owner',
                'permalink',
                'shortcode',
                'thumbnail_url',
                'timestamp',
                'username'
            );
            $postFields = implode(',', $postFields);
            $needsRefresh = false; // Whether we need to refresh or not
            $now = new DateTime();
            $previous = clone $now; // Go back in time a bit
            $previous->modify("-" . $howOftenToRefresh);

            // Check if we know when we last cached
            $lastUpdated = get_option($this->name . '_updated_at');
            if ($lastUpdated) {
                // Check if we should refresh
                if (strtotime($lastUpdated) < strtotime($previous->format("Y-m-d H:i:s"))) {
                    // Time to renew our cache
                    $needsRefresh = true;
                }
            } else {
                // We've never cached before, so we need to refresh
                $needsRefresh = true;
            }

            if ($needsRefresh) {
                // Let's grab a fresh copy
                update_option($this->name . '_updated_at', $now->format("Y-m-d H:i:s"));

                $response = wp_remote_get("https://" . $this->apiHost . "/" . $this->apiVersion . "/{$accountID}/media?fields={$postFields}&access_token={$accessToken}");
                $instagrams = json_decode($response['body'], true);
                $instagrams = $instagrams['data'];

                update_option($this->name . '_posts', $instagrams);
            } else {
                // We're going to use cache
                $instagrams = get_option($this->name . '_posts');
            }

            return $instagrams;
        }
    }

    new My_Instagram_API();
}
