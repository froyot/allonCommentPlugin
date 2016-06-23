<?php
/*
Plugin Name: Allon Comment
Plugin URI: http://www.aiaiaini.com/plugins/
Description: wordpress评论叠千层塔式嵌套评论显示，同时添加评论的点赞功能，显示每条评论点赞数。后台可以自由开关两个功能
Version: 1.0.0
Author: Allon
Author URI: http://www.aiaiaini.com/
License: GPL
 */

// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define('AL_VERSION', '1.0.0');
define('ALLON_COMMENT_DIR', plugin_dir_path(__FILE__));
define('ALLON_COMMENT_URL', plugins_url('', __FILE__));
define('ALLON_COMMENT_ADMIN_URL', admin_url());

require_once ALLON_COMMENT_DIR . 'AllonComment.php';

$plugin = new AllonComment();
//初始化插件
add_action('init', array($plugin, 'init'));
register_activation_hook(__FILE__, array($plugin, 'plugin_activation'));
register_deactivation_hook(__FILE__, array($plugin, 'plugin_deactivation'));

if (is_admin()) {

	add_action('init', array($plugin, 'initAdmin'));
}

?>
