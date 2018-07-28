<?php
/*
 * Plugin Name: Breaking News
 * Description: Display a breaking news ticker on the website.
 * Version:     0.1.0
 * Author:      Human Made
 * Author URI:  https://hmn.md/
 *
 */
namespace HM\Breaking_News;

require_once( __DIR__ . '/inc/namespace.php' );

add_action( 'plugins_loaded', __NAMESPACE__ . '\\setup' );
