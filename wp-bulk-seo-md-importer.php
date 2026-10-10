<?php
/**
 * Plugin Name: Bulk SEO Markdown Importer
 * Plugin URI: https://ворд-пресс.рф
 * Description: Массовый импорт записей WordPress из одного Markdown-файла с предпросмотром, полями Yoast SEO и датами публикации из файла.
 * Version: 1.0.0
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
