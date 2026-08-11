<?php

if (!defined('ABSPATH')) exit;

class KDB_Plugin {

	public static function init() {
		if (!self::dependencies_met()) {
			add_action('admin_notices', array(__CLASS__, 'missing_dependencies_notice'));
			return;
		}

		KDB_CPT::init();
		KDB_Admin::init();
		KDB_Frontend::init();
	}

	public static function activate() {
		KDB_CPT::register_post_type();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function dependencies_met() {
		return class_exists('WooCommerce');
	}

	public static function missing_dependencies_notice() {
		if (!current_user_can('activate_plugins')) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html('Kafra Datablad krever at WooCommerce er installert og aktivert.');
		echo '</p></div>';
	}
}
