<?php

if (!defined('ABSPATH')) exit;

class KDB_Admin {

	public static function init() {
		add_action('add_meta_boxes', array(__CLASS__, 'register_meta_boxes'));
		add_action('save_post_' . KDB_POST_TYPE, array(__CLASS__, 'save_datasheet'), 10, 2);
		add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
		add_action('wp_ajax_kdb_search_products', array(__CLASS__, 'ajax_search_products'));
		add_filter('manage_' . KDB_POST_TYPE . '_posts_columns', array(__CLASS__, 'columns'));
		add_action('manage_' . KDB_POST_TYPE . '_posts_custom_column', array(__CLASS__, 'column_content'), 10, 2);
	}

	public static function enqueue_assets($hook) {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen) {
			return;
		}

		$load = (
			$screen->post_type === KDB_POST_TYPE
			|| $screen->id === 'product'
			|| $screen->id === 'edit-product'
		);

		if (!$load) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'kdb-admin',
			KDB_PLUGIN_URL . 'assets/admin.css',
			array(),
			KDB_VERSION
		);

		wp_enqueue_script(
			'kdb-admin',
			KDB_PLUGIN_URL . 'assets/admin.js',
			array('jquery'),
			KDB_VERSION,
			true
		);

		wp_localize_script('kdb-admin', 'kdbAdmin', array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('kdb_admin'),
			'i18n'    => array(
				'selectPdf'     => 'Velg PDF',
				'changePdf'     => 'Bytt PDF',
				'removePdf'     => 'Fjern PDF',
				'searchProduct' => 'Søk produkter…',
				'noProducts'    => 'Ingen produkter funnet',
				'remove'        => 'Fjern',
			),
		));
	}

	public static function register_meta_boxes() {
		add_meta_box(
			'kdb_pdf',
			'PDF-fil',
			array(__CLASS__, 'render_pdf_metabox'),
			KDB_POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'kdb_products',
			'Koble til produkter',
			array(__CLASS__, 'render_products_metabox'),
			KDB_POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'kdb_product_datasheets',
			'Datablad',
			array(__CLASS__, 'render_product_metabox'),
			'product',
			'side',
			'default'
		);
	}

	public static function render_pdf_metabox($post) {
		wp_nonce_field('kdb_save_datasheet', 'kdb_datasheet_nonce');
		$pdf_id = KDB_Repository::get_pdf_id($post->ID);
		$url = $pdf_id ? wp_get_attachment_url($pdf_id) : '';
		$name = $pdf_id ? basename(get_attached_file($pdf_id)) : '';
		?>
		<div class="kdb-pdf" data-kdb-pdf>
			<input type="hidden" name="kdb_pdf_id" value="<?php echo esc_attr($pdf_id); ?>" data-kdb-pdf-id>
			<div class="kdb-pdf__preview" data-kdb-pdf-preview <?php echo $pdf_id ? '' : 'hidden'; ?>>
				<span class="dashicons dashicons-media-document"></span>
				<div>
					<strong data-kdb-pdf-name><?php echo esc_html($name); ?></strong>
					<?php if ($url) : ?>
						<div><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" data-kdb-pdf-link>Åpne PDF</a></div>
					<?php else : ?>
						<div><a href="#" target="_blank" rel="noopener" data-kdb-pdf-link hidden>Åpne PDF</a></div>
					<?php endif; ?>
				</div>
			</div>
			<p class="kdb-pdf__actions">
				<button type="button" class="button button-primary" data-kdb-select-pdf>
					<?php echo $pdf_id ? 'Bytt PDF' : 'Velg PDF fra mediebiblioteket'; ?>
				</button>
				<button type="button" class="button" data-kdb-remove-pdf <?php echo $pdf_id ? '' : 'hidden'; ?>>Fjern PDF</button>
			</p>
			<p class="description">Last opp eller velg en PDF-fil. Filen lagres i mediebiblioteket.</p>
		</div>
		<?php
	}

	public static function render_products_metabox($post) {
		$product_ids = KDB_Repository::get_product_ids($post->ID);
		?>
		<div class="kdb-products" data-kdb-products>
			<p>
				<label class="screen-reader-text" for="kdb-product-search">Søk produkter</label>
				<input type="search" id="kdb-product-search" class="kdb-products__search" placeholder="Søk produkter…" data-kdb-product-search autocomplete="off">
			</p>
			<div class="kdb-products__results" data-kdb-product-results hidden></div>
			<ul class="kdb-products__selected" data-kdb-product-selected>
				<?php foreach ($product_ids as $product_id) :
					$product = wc_get_product($product_id);
					if (!$product) {
						continue;
					}
					?>
					<li data-product-id="<?php echo esc_attr($product_id); ?>">
						<input type="hidden" name="kdb_product_ids[]" value="<?php echo esc_attr($product_id); ?>">
						<span><?php echo esc_html($product->get_name()); ?></span>
						<button type="button" class="button-link-delete" data-kdb-remove-product>Fjern</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="description">Ett datablad kan kobles til flere produkter.</p>
		</div>
		<?php
	}

	public static function render_product_metabox($post) {
		wp_nonce_field('kdb_save_product', 'kdb_product_nonce');
		$selected = KDB_Repository::get_datasheet_ids_for_product($post->ID);
		$datasheets = get_posts(array(
			'post_type'      => KDB_POST_TYPE,
			'post_status'    => array('publish', 'draft'),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		));
		?>
		<div class="kdb-product-sheets">
			<?php if (empty($datasheets)) : ?>
				<p class="description">Ingen datablad ennå. Opprett dem under <strong>Datablad</strong>.</p>
			<?php else : ?>
				<ul class="kdb-product-sheets__list">
					<?php foreach ($datasheets as $sheet) : ?>
						<li>
							<label>
								<input
									type="checkbox"
									name="kdb_datasheet_ids[]"
									value="<?php echo esc_attr($sheet->ID); ?>"
									<?php checked(in_array($sheet->ID, $selected, true)); ?>
								>
								<span><?php echo esc_html(get_the_title($sheet)); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p class="description">
				<a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . KDB_POST_TYPE)); ?>">Legg til nytt datablad</a>
			</p>
		</div>
		<?php
	}

	public static function save_datasheet($post_id, $post) {
		if (!isset($_POST['kdb_datasheet_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kdb_datasheet_nonce'])), 'kdb_save_datasheet')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$pdf_id = isset($_POST['kdb_pdf_id']) ? (int) $_POST['kdb_pdf_id'] : 0;
		if ($pdf_id > 0) {
			$mime = get_post_mime_type($pdf_id);
			if ($mime !== 'application/pdf') {
				$pdf_id = 0;
			}
		}
		KDB_Repository::set_pdf_id($post_id, $pdf_id);

		$product_ids = array();
		if (!empty($_POST['kdb_product_ids']) && is_array($_POST['kdb_product_ids'])) {
			$product_ids = array_map('intval', wp_unslash($_POST['kdb_product_ids']));
		}
		KDB_Repository::set_product_ids($post_id, $product_ids);
	}

	public static function save_product($product_id) {
		if (!isset($_POST['kdb_product_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kdb_product_nonce'])), 'kdb_save_product')) {
			return;
		}

		$ids = array();
		if (!empty($_POST['kdb_datasheet_ids']) && is_array($_POST['kdb_datasheet_ids'])) {
			$ids = array_map('intval', wp_unslash($_POST['kdb_datasheet_ids']));
		}

		KDB_Repository::set_datasheets_for_product($product_id, $ids);
	}

	public static function ajax_search_products() {
		check_ajax_referer('kdb_admin', 'nonce');

		if (!current_user_can('edit_products')) {
			wp_send_json_error(array('message' => 'Forbidden'), 403);
		}

		$term = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
		if (strlen($term) < 2) {
			wp_send_json_success(array('items' => array()));
		}

		$query = new WP_Query(array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			's'              => $term,
			'orderby'        => 'relevance',
		));

		$items = array();
		foreach ($query->posts as $post) {
			$product = wc_get_product($post->ID);
			if (!$product) {
				continue;
			}
			$items[] = array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
				'sku'  => $product->get_sku(),
			);
		}

		wp_send_json_success(array('items' => $items));
	}

	public static function columns($columns) {
		$new = array();
		foreach ($columns as $key => $label) {
			$new[$key] = $label;
			if ($key === 'title') {
				$new['kdb_pdf'] = 'PDF';
				$new['kdb_products'] = 'Produkter';
			}
		}
		return $new;
	}

	public static function column_content($column, $post_id) {
		if ($column === 'kdb_pdf') {
			$url = KDB_Repository::get_pdf_url($post_id);
			if ($url) {
				echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">PDF</a>';
			} else {
				echo '—';
			}
			return;
		}

		if ($column === 'kdb_products') {
			$ids = KDB_Repository::get_product_ids($post_id);
			echo $ids ? esc_html((string) count($ids)) : '—';
		}
	}
}
