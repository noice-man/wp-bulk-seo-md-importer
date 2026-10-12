<?php
/**
 * Plugin Name: Bulk SEO Markdown Importer
 * Plugin URI: https://ворд-пресс.рф
 * Update URI: https://xn----ctbgf6biedka.xn--p1ai/plugins/wp-bulk-seo-md-importer/
 * Description: Массовый импорт записей WordPress из одного Markdown-файла с предпросмотром, полями Yoast SEO и датами публикации из файла.
 * Version: 1.0.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Valery Molodtsov
 * Author URI: https://ворд-пресс.рф
 * License: GPL-2.0-or-later
 * Text Domain: wp-bulk-seo-md-importer
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'WBSMI_PATH', plugin_dir_path( __FILE__ ) );
require_once WBSMI_PATH . 'includes/parser.php';
require_once WBSMI_PATH . 'includes/markdown.php';
require_once WBSMI_PATH . 'includes/import.php';
require_once WBSMI_PATH . 'includes/admin.php';
add_action( 'admin_menu', 'wbsmi_admin_menu' );

add_action( 'wp_ajax_wbsmi_chunk_import', 'wbsmi_ajax_chunk_import' );

/** WPR Update Hub v1.1: opt-in install telemetry, no forced auto-updates. */
require_once __DIR__ . '/includes/wpr-updates-client.php';
WPR_Updates_Client_V2::register(__FILE__, array(
    'slug' => 'wp-bulk-seo-md-importer',
    'server' => 'https://xn----ctbgf6biedka.xn--p1ai',
    'telemetry' => true,
));
