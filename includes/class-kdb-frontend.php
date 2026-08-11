<?php

if (!defined('ABSPATH')) exit;

class KDB_Frontend {

	public static function init() {
		add_shortcode('kafra_datablad', array(__CLASS__, 'shortcode_product'));
		add_shortcode('kafra_datablader', array(__CLASS__, 'shortcode_product'));
		add_shortcode('kafra_dokumenter', array(__CLASS__, 'shortcode_archive'));
		add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
	}

	public static function register_assets() {
		wp_register_style(
			'kdb-frontend',
			KDB_PLUGIN_URL . 'assets/frontend.css',
			array(),
			KDB_VERSION
		);
	}

	public static function shortcode_product($atts) {
		$atts = shortcode_atts(array(
			'product_id' => 0,
			'title'      => '',
		), $atts, 'kafra_datablad');

		$product_id = (int) $atts['product_id'];
		if ($product_id <= 0 && function_exists('is_product') && is_product()) {
			$product_id = get_the_ID();
		}

		if ($product_id <= 0) {
			return '';
		}

		$posts = KDB_Repository::get_for_product($product_id);
		if (empty($posts)) {
			return '';
		}

		wp_enqueue_style('kdb-frontend');

		ob_start();
		echo '<div class="kdb-product-docs">';
		if ($atts['title'] !== '') {
			echo '<h3 class="kdb-product-docs__title">' . esc_html($atts['title']) . '</h3>';
		}
		echo '<div class="kdb-docs kdb-docs--product">';
		foreach ($posts as $post) {
			self::render_doc_card($post, false, true);
		}
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}

	public static function shortcode_archive($atts) {
		$atts = shortcode_atts(array(
			'title' => '',
		), $atts, 'kafra_dokumenter');

		$search = isset($_GET['kdb_q']) ? sanitize_text_field(wp_unslash($_GET['kdb_q'])) : '';
		$paged  = max(1, (int) get_query_var('paged'), isset($_GET['kdb_page']) ? (int) $_GET['kdb_page'] : 1);

		$query = KDB_Repository::search(array(
			's'              => $search,
			'posts_per_page' => 30,
			'paged'          => $paged,
		));

		wp_enqueue_style('kdb-frontend');

		ob_start();
		?>
		<div class="kdb-archive">
			<?php if ($atts['title'] !== '') : ?>
				<h2 class="kdb-archive__title"><?php echo esc_html($atts['title']); ?></h2>
			<?php endif; ?>

			<form class="kdb-archive__filters" method="get" action="">
				<div class="kdb-archive__field kdb-archive__field--search">
					<label class="screen-reader-text" for="kdb-q">Søk</label>
					<input
						type="search"
						id="kdb-q"
						name="kdb_q"
						value="<?php echo esc_attr($search); ?>"
						placeholder="Søk etter datablad eller produkt…"
					>
				</div>
				<button type="submit" class="kdb-archive__submit">Søk</button>
			</form>

			<?php if (!$query->have_posts()) : ?>
				<p class="kdb-archive__empty">Ingen datablad funnet.</p>
			<?php else : ?>
				<div class="kdb-docs kdb-docs--archive">
					<?php
					while ($query->have_posts()) {
						$query->the_post();
						self::render_doc_card(get_post(), true);
					}
					wp_reset_postdata();
					?>
				</div>

				<?php
				$total_pages = (int) $query->max_num_pages;
				if ($total_pages > 1) :
					echo '<nav class="kdb-archive__pagination">';
					echo paginate_links(array(
						'total'    => $total_pages,
						'current'  => $paged,
						'format'   => '?kdb_page=%#%',
						'add_args' => array(
							'kdb_q' => $search !== '' ? $search : false,
						),
					));
					echo '</nav>';
				endif;
				?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_doc_card($post, $show_products = true, $compact = false) {
		$item = KDB_Repository::format_item($post);
		$url  = $item['pdf_url'];
		if ($url === '') {
			return;
		}

		$product_count = count($item['products']);
		$classes = 'kdb-card';
		if ($compact) {
			$classes .= ' kdb-card--compact';
		}
		?>
		<article class="<?php echo esc_attr($classes); ?>">
			<div class="kdb-card__main">
				<span class="kdb-card__icon" aria-hidden="true">
					<?php echo self::icon_svg('file-text'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="kdb-card__body">
					<span class="kdb-card__title"><?php echo esc_html($item['title']); ?></span>
					<?php if (!empty($item['pdf_name'])) : ?>
						<span class="kdb-card__file"><?php echo esc_html($item['pdf_name']); ?></span>
					<?php endif; ?>
					<?php if (!$compact && !empty($item['meta_parts'])) : ?>
						<span class="kdb-card__meta"><?php echo esc_html(implode(' · ', $item['meta_parts'])); ?></span>
					<?php endif; ?>
				</span>
			</div>

			<a
				class="kdb-card__download"
				href="<?php echo esc_url($url); ?>"
				download
				aria-label="<?php echo esc_attr(sprintf('Last ned %s', $item['title'])); ?>"
				title="Last ned PDF"
			>
				<?php echo self::icon_svg('download'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>

			<?php if ($show_products && $product_count > 0) : ?>
				<div class="kdb-card__products">
					<div class="kdb-card__products-label">
						<?php echo self::icon_svg('link'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span>Relevante produkter (<?php echo esc_html((string) $product_count); ?>)</span>
					</div>
					<div class="kdb-card__product-list">
						<?php foreach ($item['products'] as $product) : ?>
							<a class="kdb-card__product" href="<?php echo esc_url($product['url']); ?>">
								<span><?php echo esc_html($product['name']); ?></span>
								<?php echo self::icon_svg('arrow-up-right'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<a
				class="kdb-card__open"
				href="<?php echo esc_url($url); ?>"
				target="_blank"
				rel="noopener"
				aria-label="<?php echo esc_attr(sprintf('Åpne %s', $item['title'])); ?>"
			></a>
		</article>
		<?php
	}

	private static function icon_svg($name) {
		$icons = array(
			'file-text' => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
			'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
			'arrow-up-right' => '<path d="M7 7h10v10"/><path d="M7 17 17 7"/>',
			'external-link' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
			'download' => '<path d="M12 15V3"/><path d="m7 10 5 5 5-5"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>',
		);

		if (!isset($icons[$name])) {
			return '';
		}

		return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="kdb-icon kdb-icon--' . esc_attr($name) . '" aria-hidden="true">' . $icons[$name] . '</svg>';
	}
}
