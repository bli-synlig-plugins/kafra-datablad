<?php

if (!defined('ABSPATH')) exit;

class KDB_CPT {

	public static function init() {
		add_action('init', array(__CLASS__, 'register_post_type'));
	}

	public static function register_post_type() {
		register_post_type(KDB_POST_TYPE, array(
			'labels' => array(
				'name'               => 'Datablad',
				'singular_name'      => 'Datablad',
				'add_new'            => 'Legg til datablad',
				'add_new_item'       => 'Legg til datablad',
				'edit_item'          => 'Rediger datablad',
				'new_item'           => 'Nytt datablad',
				'view_item'          => 'Vis datablad',
				'search_items'       => 'Søk datablad',
				'not_found'          => 'Ingen datablad funnet',
				'not_found_in_trash' => 'Ingen datablad i papirkurven',
				'menu_name'          => 'Datablad',
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 56,
			'menu_icon'           => 'dashicons-media-document',
			'supports'            => array('title'),
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		));
	}
}
