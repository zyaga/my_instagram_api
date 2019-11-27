<?php
// if uninstall not called from WordPress exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

// Options our plugin has created
$options = array(
    'account_id',
    'access_token',
    'updated_at',
    'posts'
);

foreach ($options as $option) {
    delete_option('my_instagram_api_' . $option);
}
