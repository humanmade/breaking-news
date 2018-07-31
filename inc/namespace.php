<?php
/**
 * Breaking news plugin.
 *
 * @package breaking-news
 */

namespace HM\Breaking_News;

use WP_Post;
use WP_Query;

/**
 * Load up all our bootstrap functions.
 */
function setup() {
	add_action( 'init', __NAMESPACE__ . '\\register_cpt' );
	add_action( 'init', __NAMESPACE__ . '\\server_rewrite_rule' );
	add_filter( 'query_vars', __NAMESPACE__ . '\\query_vars' );
	add_action( 'template_redirect', __NAMESPACE__ . '\\redirect' );
	add_action( 'add_meta_boxes_breaking', __NAMESPACE__ . '\\meta_boxes' );
	add_action( 'save_post', __NAMESPACE__ . '\\save_post', 10, 2 );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_scripts' );
	add_action( 'wp_footer', __NAMESPACE__ . '\\template' );
}

/**
 * Register a custom post type to store our API logs.
 *
 */
function register_cpt() {
	register_post_type( 'breaking', [
		'labels'              => [
			'name'               => __( 'Breaking News', 'platform-demo' ),
			'singular_name'      => __( 'Breaking News', 'platform-demo' ),
			'add_new'            => _x( 'Add New Breaking News', 'platform-demo', 'platform-demo' ),
			'add_new_item'       => __( 'Add New Breaking News', 'platform-demo' ),
			'edit_item'          => __( 'Edit Breaking News', 'platform-demo' ),
			'new_item'           => __( 'New Breaking News', 'platform-demo' ),
			'view_item'          => __( 'View Breaking News', 'platform-demo' ),
			'search_items'       => __( 'Search Breaking News', 'platform-demo' ),
			'not_found'          => __( 'No Breaking News found', 'platform-demo' ),
			'not_found_in_trash' => __( 'No Breaking News found in Trash', 'platform-demo' ),
			'parent_item_colon'  => __( 'Parent Breaking News:', 'platform-demo' ),
			'menu_name'          => __( 'Breaking News', 'platform-demo' ),
		],
		'hierarchical'        => false,
		'description'         => __( 'Breaking news headlines' ),
		'taxonomies'          => [],
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => null,
		'menu_icon'           => 'dashicons-warning',
		'show_in_nav_menus'   => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'has_archive'         => false,
		'query_var'           => true,
		'can_export'          => true,
		'rewrite'             => false,
		'capability_type'     => 'breaking',
		'supports'            => [
			'title',
		],
	] );
}

function meta_boxes() {
	add_meta_box( 'breaking-news', __( 'Breaking news options', 'breaking-news' ), __NAMESPACE__ . '\\meta_box', 'breaking', 'normal', 'high' );
}

function meta_box( WP_Post $post ) {
	remove_meta_box( 'slugdiv', 'breaking', 'normal' );

	add_settings_section( 'default', '', '__return_false', 'breaking' );

	add_settings_field( 'expiry', __( 'Expires after', 'breaking-news' ), function () use ( $post ) {
		$expiry = absint( get_post_meta( $post->ID, 'expiry', true ) ) ?: 45;
		printf( '<input name="expiry" type="number" step="1" min="1" max="360" value="%d" size="3" /> %s', $expiry, esc_html__( 'minutes', 'breaking-news' ) );
	}, 'breaking' );

	add_settings_field( 'link', __( 'Link', 'breaking-news' ), function () use ( $post ) {
		$link = esc_url_raw( get_post_meta( $post->ID, 'link', true ) );
		printf( '<input class="widefat regular-text" name="link" type="url" value="%s" />', $link );
	}, 'breaking' );

	do_settings_sections( 'breaking' );
}

function save_post( $post_id, WP_Post $post ) {
	if ( $post->post_type !== 'breaking' ) {
		return;
	}

	$expiry = filter_input( INPUT_POST, 'expiry', FILTER_SANITIZE_NUMBER_INT );
	$link   = filter_input( INPUT_POST, 'link', FILTER_SANITIZE_URL );

	if ( $expiry ) {
		update_post_meta( $post_id, 'expiry', absint( $expiry ) );
		update_post_meta( $post_id, 'expiry_time', strtotime( $post->post_date ) + ( $expiry * 60 ) );
	}

	if ( $link ) {
		update_post_meta( $post_id, 'link', esc_url_raw( $link ) );
	}

	if ( function_exists( 'batcache_clear_url' ) ) {
		batcache_clear_url( home_url( '/breaking-news/' ) );
	}
}

function server_rewrite_rule() {
	add_rewrite_rule( '^breaking-news/?$', [
		'breaking-news' => 1,
	], 'top' );
}

function query_vars( $query_vars ) {
	$query_vars[] = 'breaking-news';
	return $query_vars;
}

function redirect() {
	server();
}

function server() {
	if ( empty( get_query_var( 'breaking-news', false ) ) ) {
		return;
	}

	ob_implicit_flush();

	// Event stream headers.
	header( 'Content-Type: text/event-stream' );

	echo "\n\n";

	foreach ( get_breaking_news() as $item ) {
		ob_start();
		echo make_sse( 'newstory', $item );
		ob_end_flush();
		flush();
	}

	exit;
}

function get_breaking_news() {
	$news = new WP_Query( [
		'post_type'      => 'breaking',
		'post_status'    => 'publish',
		'posts_per_page' => 10,
		// phpcs:ignore
		'meta_query'     => [
			[
				'key'     => 'expiry_time',
				'value'   => time(),
				'compare' => '>=',
				'type'    => 'SIGNED',
			],
		],
	] );

	if ( empty( $news->found_posts ) ) {
		return [];
	}

	$news_posts = $news->posts;

	return array_map( function ( WP_Post $item ) {
		return [
			'id'      => $item->ID,
			'date'    => strtotime( $item->post_date ) * 1000,
			'title'   => html_entity_decode( get_the_title( $item->ID ) ),
			'content' => wptexturize( $item->post_content ),
			'expires' => absint( get_post_meta( $item->ID, 'expiry_time', true ) ) * 1000,
			'link'    => esc_url_raw( get_post_meta( $item->ID, 'link', true ) ),
		];
	}, $news_posts );
}

function make_sse( $event, $data, $retry = 10000 ) {
	$out  = sprintf( "id: %s\n", md5( wp_json_encode( $data ) ) );
	$out .= sprintf( "event: %s\n", sanitize_key( $event ) );
	$out .= sprintf( "data: %s\n", wp_json_encode( $data ) );
	$out .= sprintf( "retry: %d\n", absint( $retry ) );
	$out .= "\n";
	return $out;
}

function enqueue_scripts() {
	wp_register_script( 'eventsource-polyfill', plugins_url( '../assets/src/EventSource.js', __FILE__ ), [], null );
	wp_enqueue_script( 'breaking-news', plugins_url( '../assets/src/index.js', __FILE__ ), [ 'eventsource-polyfill' ], null, true );
	wp_enqueue_style( 'breaking-news', plugins_url( '../assets/src/index.css', __FILE__ ), [], null );
}

function template() {
	include __DIR__ . '/template.php';
}
