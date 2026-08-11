<?php

if (!defined('ABSPATH')) exit;

class KDB_Repository {

	public static function get_pdf_id($datasheet_id) {
		return (int) get_post_meta($datasheet_id, KDB_META_PDF, true);
	}

	public static function get_pdf_url($datasheet_id) {
		$pdf_id = self::get_pdf_id($datasheet_id);
		if ($pdf_id <= 0) {
			return '';
		}

		$url = wp_get_attachment_url($pdf_id);
		return $url ? $url : '';
	}

	public static function get_product_ids($datasheet_id) {
		$ids = get_post_meta($datasheet_id, KDB_META_PRODUCTS, true);
		if (!is_array($ids)) {
			return array();
		}

		$ids = array_map('intval', $ids);
		$ids = array_values(array_unique(array_filter($ids)));
		return $ids;
	}

	public static function set_pdf_id($datasheet_id, $pdf_id) {
		$pdf_id = (int) $pdf_id;
		if ($pdf_id > 0) {
			update_post_meta($datasheet_id, KDB_META_PDF, $pdf_id);
		} else {
			delete_post_meta($datasheet_id, KDB_META_PDF);
		}
	}

	public static function set_product_ids($datasheet_id, array $product_ids) {
		$product_ids = array_map('intval', $product_ids);
		$product_ids = array_values(array_unique(array_filter($product_ids)));

		if ($product_ids) {
			update_post_meta($datasheet_id, KDB_META_PRODUCTS, $product_ids);
		} else {
			delete_post_meta($datasheet_id, KDB_META_PRODUCTS);
		}
	}

	public static function get_for_product($product_id) {
		$product_id = (int) $product_id;
		if ($product_id <= 0) {
			return array();
		}

		$query = new WP_Query(array(
			'post_type'              => KDB_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'meta_query'             => array(
				array(
					'key'     => KDB_META_PRODUCTS,
					'value'   => sprintf('i:%d;', $product_id),
					'compare' => 'LIKE',
				),
			),
		));

		return $query->posts;
	}

	public static function get_datasheet_ids_for_product($product_id) {
		$posts = self::get_for_product($product_id);
		return wp_list_pluck($posts, 'ID');
	}

	public static function set_datasheets_for_product($product_id, array $datasheet_ids) {
		$product_id = (int) $product_id;
		$datasheet_ids = array_values(array_unique(array_filter(array_map('intval', $datasheet_ids))));

		$all = get_posts(array(
			'post_type'      => KDB_POST_TYPE,
			'post_status'    => array('publish', 'draft', 'pending', 'private'),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		));

		foreach ($all as $datasheet_id) {
			$current = self::get_product_ids($datasheet_id);
			$has = in_array($product_id, $current, true);
			$should = in_array((int) $datasheet_id, $datasheet_ids, true);

			if ($should && !$has) {
				$current[] = $product_id;
				self::set_product_ids($datasheet_id, $current);
			} elseif (!$should && $has) {
				$current = array_values(array_diff($current, array($product_id)));
				self::set_product_ids($datasheet_id, $current);
			}
		}
	}

	/**
	 * Search datasheets by title and/or linked product name.
	 *
	 * @return WP_Query
	 */
	public static function search(array $args = array()) {
		$search         = isset($args['s']) ? sanitize_text_field($args['s']) : '';
		$posts_per_page = isset($args['posts_per_page']) ? (int) $args['posts_per_page'] : 50;
		$paged          = isset($args['paged']) ? max(1, (int) $args['paged']) : 1;

		$query_args = array(
			'post_type'      => KDB_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ($search === '') {
			return new WP_Query($query_args);
		}

		$matched_ids = array();

		$title_ids = get_posts(array(
			'post_type'      => KDB_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			's'              => $search,
		));
		$matched_ids = array_merge($matched_ids, $title_ids);

		$product_ids = get_posts(array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			's'              => $search,
		));

		foreach ($product_ids as $product_id) {
			$matched_ids = array_merge($matched_ids, self::get_datasheet_ids_for_product($product_id));
		}

		$matched_ids = array_values(array_unique(array_map('intval', $matched_ids)));

		if (empty($matched_ids)) {
			$query_args['post__in'] = array(0);
			return new WP_Query($query_args);
		}

		$query_args['post__in'] = $matched_ids;
		$query_args['orderby']  = 'title';

		return new WP_Query($query_args);
	}

	public static function format_item(WP_Post $post) {
		$pdf_id = self::get_pdf_id($post->ID);
		$product_ids = self::get_product_ids($post->ID);
		$products = array();
		$category_names = array();

		foreach ($product_ids as $product_id) {
			$product = wc_get_product($product_id);
			if (!$product) {
				continue;
			}
			$products[] = array(
				'id'   => $product_id,
				'name' => $product->get_name(),
				'url'  => get_permalink($product_id),
			);

			$terms = get_the_terms($product_id, 'product_cat');
			if (!is_wp_error($terms) && !empty($terms)) {
				foreach ($terms as $term) {
					if ($term->slug === 'uncategorized' || $term->name === 'Ukategorisert') {
						continue;
					}
					$category_names[$term->term_id] = $term->name;
				}
			}
		}

		$file_path = $pdf_id ? get_attached_file($pdf_id) : '';
		$file_size = '';
		if ($file_path && file_exists($file_path)) {
			$file_size = size_format((int) filesize($file_path), 1);
		}

		$meta_parts = array_values($category_names);
		$meta_parts = array_slice($meta_parts, 0, 2);

		$modified = get_the_modified_date('', $post);
		if ($modified) {
			$meta_parts[] = sprintf('Oppdatert %s', $modified);
		}
		if ($file_size !== '') {
			$meta_parts[] = $file_size;
		}

		return array(
			'id'          => $post->ID,
			'title'       => get_the_title($post),
			'pdf_id'      => $pdf_id,
			'pdf_url'     => self::get_pdf_url($post->ID),
			'pdf_name'    => $pdf_id ? basename(get_attached_file($pdf_id)) : '',
			'products'    => $products,
			'product_ids' => $product_ids,
			'meta_parts'  => $meta_parts,
		);
	}
}
