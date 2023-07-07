<?php
/*
Plugin Name: WPC Fly Cart for WooCommerce
Plugin URI: https://wpclever.net/
Description: WooCommerce interaction mini cart with many styles and effects.
Version: 5.5.3
Author: WPClever
Author URI: https://wpclever.net
Text Domain: woo-fly-cart
Domain Path: /languages/
Requires at least: 4.0
Tested up to: 6.2
WC requires at least: 3.0
WC tested up to: 7.8
*/

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

! defined( 'WOOFC_VERSION' ) && define( 'WOOFC_VERSION', '5.5.3' );
! defined( 'WOOFC_FILE' ) && define( 'WOOFC_FILE', __FILE__ );
! defined( 'WOOFC_URI' ) && define( 'WOOFC_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOFC_DIR' ) && define( 'WOOFC_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WOOFC_REVIEWS' ) && define( 'WOOFC_REVIEWS', 'https://wordpress.org/support/plugin/woo-fly-cart/reviews/?filter=5' );
! defined( 'WOOFC_CHANGELOG' ) && define( 'WOOFC_CHANGELOG', 'https://wordpress.org/plugins/woo-fly-cart/#developers' );
! defined( 'WOOFC_DISCUSSION' ) && define( 'WOOFC_DISCUSSION', 'https://wordpress.org/support/plugin/woo-fly-cart' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WOOFC_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';

if ( ! function_exists( 'woofc_init' ) ) {
	add_action( 'plugins_loaded', 'woofc_init', 11 );

	function woofc_init() {
		// load text-domain
		load_plugin_textdomain( 'woo-fly-cart', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'woofc_notice_wc' );

			return;
		}

		if ( ! class_exists( 'WPCleverWoofc' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWoofc {
				protected static $settings = [];
				protected static $localization = [];
				protected static $instance = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings     = (array) get_option( 'woofc_settings', [] );
					self::$localization = (array) get_option( 'woofc_localization', [] );

					if ( empty( self::$localization ) ) {
						// version < 5.2
						self::$localization = (array) get_option( '_woofc_localization', [] );
					}

					add_action( 'wp_footer', [ $this, 'footer' ] );
					add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );
					add_filter( 'wp_nav_menu_items', [ $this, 'nav_menu_items' ], 99, 2 );
					add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'cart_fragment' ] );
					add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'cart_fragment' ] );
					add_filter( 'wpcsm_locations', [ $this, 'wpcsm_locations' ] );

					// ajax
					add_action( 'wp_ajax_woofc_update_qty', [ $this, 'update_qty' ] );
					add_action( 'wp_ajax_nopriv_woofc_update_qty', [ $this, 'update_qty' ] );
					add_action( 'wp_ajax_woofc_remove_item', [ $this, 'remove_item' ] );
					add_action( 'wp_ajax_nopriv_woofc_remove_item', [ $this, 'remove_item' ] );
					add_action( 'wp_ajax_woofc_undo_remove', [ $this, 'undo_remove' ] );
					add_action( 'wp_ajax_nopriv_woofc_undo_remove', [ $this, 'undo_remove' ] );
					add_action( 'wp_ajax_woofc_empty_cart', [ $this, 'empty_cart' ] );
					add_action( 'wp_ajax_nopriv_woofc_empty_cart', [ $this, 'empty_cart' ] );

					// HPOS compatibility
					add_action( 'before_woocommerce_init', function () {
						if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
							FeaturesUtil::declare_compatibility( 'custom_order_tables', WOOFC_FILE );
						}
					} );
				}

				public static function get_settings() {
					return apply_filters( 'woofc_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) ) {
						if ( isset( self::$settings[ $name ] ) ) {
							$setting = self::$settings[ $name ];
						} else {
							$setting = $default;
						}
					} else {
						$setting = get_option( '_woofc_' . $name, $default );
					}

					return apply_filters( 'woofc_get_setting', $setting, $name, $default );
				}

				public static function localization( $key = '', $default = '' ) {
					$str = '';

					if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
						$str = self::$localization[ $key ];
					} elseif ( ! empty( $default ) ) {
						$str = $default;
					}

					return apply_filters( 'woofc_localization_' . $key, $str );
				}

				function enqueue_scripts() {
					// disable on some pages
					if ( apply_filters( 'woofc_disable', false ) ) {
						return;
					}

					// hint css
					wp_enqueue_style( 'hint', WOOFC_URI . 'assets/hint/hint.min.css' );

					// perfect srollbar
					if ( apply_filters( 'woofc_perfect_scrollbar', self::get_setting( 'perfect_scrollbar', 'yes' ) ) === 'yes' ) {
						wp_enqueue_style( 'perfect-scrollbar', WOOFC_URI . 'assets/perfect-scrollbar/css/perfect-scrollbar.min.css' );
						wp_enqueue_style( 'perfect-scrollbar-wpc', WOOFC_URI . 'assets/perfect-scrollbar/css/custom-theme.css' );
						wp_enqueue_script( 'perfect-scrollbar', WOOFC_URI . 'assets/perfect-scrollbar/js/perfect-scrollbar.jquery.min.js', [ 'jquery' ], WOOFC_VERSION, true );
					}

					// slick
					if ( ( apply_filters( 'woofc_slick', self::get_setting( 'suggested_carousel', 'yes' ) ) === 'yes' ) && ! empty( self::get_setting( 'suggested', [] ) ) ) {
						wp_enqueue_style( 'slick', WOOFC_URI . 'assets/slick/slick.css' );
						wp_enqueue_script( 'slick', WOOFC_URI . 'assets/slick/slick.min.js', [ 'jquery' ], WOOFC_VERSION, true );
					}

					// main
					if ( ! apply_filters( 'woofc_disable_font_icon', false ) ) {
						wp_enqueue_style( 'woofc-fonts', WOOFC_URI . 'assets/css/fonts.css' );
					}

					// css
					wp_enqueue_style( 'woofc-frontend', WOOFC_URI . 'assets/css/frontend.css', [], WOOFC_VERSION );
					$color      = self::get_setting( 'color', '#cc6055' );
					$bg_image   = self::get_setting( 'bg_image', '' ) !== '' ? wp_get_attachment_url( self::get_setting( 'bg_image', '' ) ) : '';
					$inline_css = ".woofc-area.woofc-style-01 .woofc-inner, .woofc-area.woofc-style-03 .woofc-inner, .woofc-area.woofc-style-02 .woofc-area-bot .woofc-action .woofc-action-inner > div a:hover, .woofc-area.woofc-style-04 .woofc-area-bot .woofc-action .woofc-action-inner > div a:hover {
                            background-color: {$color};
                        }

                        .woofc-area.woofc-style-01 .woofc-area-bot .woofc-action .woofc-action-inner > div a, .woofc-area.woofc-style-02 .woofc-area-bot .woofc-action .woofc-action-inner > div a, .woofc-area.woofc-style-03 .woofc-area-bot .woofc-action .woofc-action-inner > div a, .woofc-area.woofc-style-04 .woofc-area-bot .woofc-action .woofc-action-inner > div a {
                            outline: none;
                            color: {$color};
                        }

                        .woofc-area.woofc-style-02 .woofc-area-bot .woofc-action .woofc-action-inner > div a, .woofc-area.woofc-style-04 .woofc-area-bot .woofc-action .woofc-action-inner > div a {
                            border-color: {$color};
                        }

                        .woofc-area.woofc-style-05 .woofc-inner{
                            background-color: {$color};
                            background-image: url('{$bg_image}');
                            background-size: cover;
                            background-position: center;
                            background-repeat: no-repeat;
                        }
                        
                        .woofc-count span {
                            background-color: {$color};
                        }";
					wp_add_inline_style( 'woofc-frontend', $inline_css );

					// js
					wp_enqueue_script( 'woofc-frontend', WOOFC_URI . 'assets/js/frontend.js', [
						'jquery',
						'wc-cart-fragments'
					], WOOFC_VERSION, true );
					wp_localize_script( 'woofc-frontend', 'woofc_vars', [
							'ajax_url'              => admin_url( 'admin-ajax.php' ),
							'nonce'                 => wp_create_nonce( 'woofc-security' ),
							'scrollbar'             => self::get_setting( 'perfect_scrollbar', 'yes' ),
							'auto_show'             => self::get_setting( 'auto_show_ajax', 'yes' ),
							'undo_remove'           => self::get_setting( 'undo_remove', 'yes' ),
							'confirm_remove'        => self::get_setting( 'confirm_remove', 'no' ),
							'instant_checkout'      => self::get_setting( 'instant_checkout', 'no' ),
							'instant_checkout_open' => self::get_setting( 'instant_checkout_open', 'no' ),
							'confirm_empty'         => self::get_setting( 'confirm_empty', 'no' ),
							'confirm_empty_text'    => self::localization( 'empty_confirm', esc_html__( 'Do you want to empty the cart?', 'woo-fly-cart' ) ),
							'confirm_remove_text'   => self::localization( 'remove_confirm', esc_html__( 'Do you want to remove this item?', 'woo-fly-cart' ) ),
							'undo_remove_text'      => self::localization( 'remove_undo', esc_html__( 'Undo?', 'woo-fly-cart' ) ),
							'removed_text'          => self::localization( 'removed', esc_html__( '%s was removed.', 'woo-fly-cart' ) ),
							'manual_show'           => self::get_setting( 'manual_show', '' ),
							'reload'                => self::get_setting( 'reload', 'no' ),
							'slick'                 => apply_filters( 'woofc_slick', self::get_setting( 'suggested_carousel', 'yes' ) ),
							'slick_params'          => apply_filters( 'woofc_slick_params', json_encode( apply_filters( 'woofc_slick_params_arr', [
								'slidesToShow'   => 1,
								'slidesToScroll' => 1,
								'dots'           => true,
								'arrows'         => false,
								'autoplay'       => false,
								'autoplaySpeed'  => 3000,
								'rtl'            => is_rtl()
							] ) ) ),
							'is_cart'               => is_cart(),
							'is_checkout'           => is_checkout(),
							'cart_url'              => ( ( self::get_setting( 'hide_cart_checkout', 'no' ) === 'yes' ) && ( is_cart() || is_checkout() ) ) ? wc_get_cart_url() : '',
							'hide_count_empty'      => self::get_setting( 'count_hide_empty', 'no' ),
							'wc_checkout_js'        => defined( 'WC_PLUGIN_FILE' ) ? plugins_url( 'assets/js/frontend/checkout.js', WC_PLUGIN_FILE ) : '',
						]
					);
				}

				function admin_enqueue_scripts( $hook ) {
					wp_enqueue_style( 'woofc-backend', WOOFC_URI . 'assets/css/backend.css', [], WOOFC_VERSION );

					if ( strpos( $hook, 'woofc' ) ) {
						add_thickbox();
						wp_enqueue_media();
						wp_enqueue_style( 'wp-color-picker' );
						wp_enqueue_style( 'fonticonpicker', WOOFC_URI . 'assets/fonticonpicker/css/jquery.fonticonpicker.css' );
						wp_enqueue_script( 'fonticonpicker', WOOFC_URI . 'assets/fonticonpicker/js/jquery.fonticonpicker.min.js', [ 'jquery' ] );
						wp_enqueue_style( 'woofc-fonts', WOOFC_URI . 'assets/css/fonts.css' );
						wp_enqueue_script( 'woofc-backend', WOOFC_URI . 'assets/js/backend.js', [
							'jquery',
							'wp-color-picker'
						] );
					}
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings             = '<a href="' . admin_url( 'admin.php?page=wpclever-woofc&tab=settings' ) . '">' . esc_html__( 'Settings', 'woo-fly-cart' ) . '</a>';
						$links['wpc-premium'] = '<a href="' . admin_url( 'admin.php?page=wpclever-woofc&tab=premium' ) . '">' . esc_html__( 'Premium Version', 'woo-fly-cart' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="' . esc_url( WOOFC_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'woo-fly-cart' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function register_settings() {
					// settings
					register_setting( 'woofc_settings', 'woofc_settings' );

					// localization
					register_setting( 'woofc_localization', 'woofc_localization' );
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', esc_html__( 'WPC Fly Cart', 'woo-fly-cart' ), esc_html__( 'Fly Cart', 'woo-fly-cart' ), 'manage_options', 'wpclever-woofc', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
					?>
					<div class="wpclever_settings_page wrap">
						<h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Fly Cart', 'woo-fly-cart' ) . ' ' . WOOFC_VERSION; ?></h1>
						<div class="wpclever_settings_page_desc about-text">
							<p>
								<?php printf( esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'woo-fly-cart' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
								<br/>
								<a href="<?php echo esc_url( WOOFC_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'woo-fly-cart' ); ?></a> |
								<a href="<?php echo esc_url( WOOFC_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'woo-fly-cart' ); ?></a> |
								<a href="<?php echo esc_url( WOOFC_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'woo-fly-cart' ); ?></a>
							</p>
						</div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
							<div class="notice notice-success is-dismissible">
								<p><?php esc_html_e( 'Settings updated.', 'woo-fly-cart' ); ?></p>
							</div>
						<?php } ?>
						<div class="wpclever_settings_page_nav">
							<h2 class="nav-tab-wrapper">
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-woofc&tab=settings' ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Settings', 'woo-fly-cart' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-woofc&tab=localization' ); ?>" class="<?php echo esc_attr( $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Localization', 'woo-fly-cart' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-woofc&tab=premium' ); ?>" class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>" style="color: #c9356e">
									<?php esc_html_e( 'Premium Version', 'woo-fly-cart' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-kit' ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'woo-fly-cart' ); ?>
								</a>
							</h2>
						</div>
						<div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'settings' ) {
								$default_style           = apply_filters( 'woofc_default_style', '01' );
								$auto_show_ajax          = self::get_setting( 'auto_show_ajax', 'yes' );
								$auto_show_normal        = self::get_setting( 'auto_show_normal', 'yes' );
								$reverse_items           = self::get_setting( 'reverse_items', 'yes' );
								$overlay_layer           = self::get_setting( 'overlay_layer', 'yes' );
								$perfect_scrollbar       = self::get_setting( 'perfect_scrollbar', 'yes' );
								$position                = self::get_setting( 'position', '05' );
								$effect                  = self::get_setting( 'effect', 'yes' );
								$style                   = self::get_setting( 'style', $default_style );
								$close                   = self::get_setting( 'close', 'yes' );
								$link                    = self::get_setting( 'link', 'yes' );
								$price                   = self::get_setting( 'price', 'price' );
								$data                    = self::get_setting( 'data', 'no' );
								$estimated_delivery_date = self::get_setting( 'estimated_delivery_date', 'no' );
								$plus_minus              = self::get_setting( 'plus_minus', 'yes' );
								$remove                  = self::get_setting( 'remove', 'yes' );
								$save_for_later          = self::get_setting( 'save_for_later', 'yes' );
								$subtotal                = self::get_setting( 'subtotal', 'yes' );
								$coupon                  = self::get_setting( 'coupon', 'no' );
								$coupon_listing          = self::get_setting( 'coupon_listing', 'no' );
								$shipping_cost           = self::get_setting( 'shipping_cost', 'no' );
								$shipping_calculator     = self::get_setting( 'shipping_calculator', 'no' );
								$free_shipping_bar       = self::get_setting( 'free_shipping_bar', 'yes' );
								$total                   = self::get_setting( 'total', 'yes' );
								$buttons                 = self::get_setting( 'buttons', '01' );
								$instant_checkout        = self::get_setting( 'instant_checkout', 'no' );
								$instant_checkout_open   = self::get_setting( 'instant_checkout_open', 'no' );
								$suggested               = self::get_setting( 'suggested', [] );
								$suggested_carousel      = self::get_setting( 'suggested_carousel', 'yes' );
								$empty                   = self::get_setting( 'empty', 'no' );
								$confirm_empty           = self::get_setting( 'confirm_empty', 'no' );
								$share                   = self::get_setting( 'share', 'yes' );
								$continue                = self::get_setting( 'continue', 'yes' );
								$confirm_remove          = self::get_setting( 'confirm_remove', 'no' );
								$undo_remove             = self::get_setting( 'undo_remove', 'yes' );
								$reload                  = self::get_setting( 'reload', 'no' );
								$hide_cart_checkout      = self::get_setting( 'hide_cart_checkout', 'no' );
								$count                   = self::get_setting( 'count', 'yes' );
								$count_position          = self::get_setting( 'count_position', 'bottom-left' );
								$count_hide_empty        = self::get_setting( 'count_hide_empty', 'no' );
								?>
								<form method="post" action="options.php">
									<table class="form-table">
										<tr class="heading">
											<th><?php esc_html_e( 'General', 'woo-fly-cart' ); ?></th>
											<td><?php esc_html_e( 'General settings for the fly cart.', 'woo-fly-cart' ); ?></td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Open on AJAX add to cart', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[auto_show_ajax]">
													<option value="yes" <?php selected( $auto_show_ajax, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $auto_show_ajax, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php printf( esc_html__( 'The fly cart will be opened immediately after whenever click to AJAX Add to cart buttons? See %s "Add to cart behaviour" setting %s', 'woo-fly-cart' ), '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=products&section=display' ) . '" target="_blank">', '</a>.' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Open on normal add to cart', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[auto_show_normal]">
													<option value="yes" <?php selected( $auto_show_normal, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $auto_show_normal, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'The fly cart will be opened immediately after whenever click to normal Add to cart buttons (AJAX is not enable) or Add to cart button in single product page?', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Reverse items', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[reverse_items]">
													<option value="yes" <?php selected( $reverse_items, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $reverse_items, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Overlay layer', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[overlay_layer]">
													<option value="yes" <?php selected( $overlay_layer, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $overlay_layer, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'If you hide the overlay layer, the buyer still can work on your site when the fly cart is opening.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row"><?php esc_html_e( 'Use perfect-scrollbar', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[perfect_scrollbar]">
													<option value="yes" <?php selected( $perfect_scrollbar, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $perfect_scrollbar, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php printf( esc_html__( 'Read more about %s', 'woo-fly-cart' ), '<a href="https://github.com/mdbootstrap/perfect-scrollbar" target="_blank">perfect-scrollbar</a>' ); ?>.</span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Position', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[position]">
													<option value="01" <?php selected( $position, '01' ); ?>><?php esc_html_e( 'Right', 'woo-fly-cart' ); ?></option>
													<option value="02" <?php selected( $position, '02' ); ?>><?php esc_html_e( 'Left', 'woo-fly-cart' ); ?></option>
													<option value="03" <?php selected( $position, '03' ); ?>><?php esc_html_e( 'Top', 'woo-fly-cart' ); ?></option>
													<option value="04" <?php selected( $position, '04' ); ?>><?php esc_html_e( 'Bottom', 'woo-fly-cart' ); ?></option>
													<option value="05" <?php selected( $position, '05' ); ?>><?php esc_html_e( 'Center', 'woo-fly-cart' ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Effect', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[effect]">
													<option value="yes" <?php selected( $effect, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $effect, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Enable/disable slide effect.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Style', 'woo-fly-cart' ); ?></th>
											<td>
												<?php
												$styles = apply_filters( 'woofc_styles', [
													'01' => esc_html__( 'Color background', 'woo-fly-cart' ),
													'02' => esc_html__( 'White background', 'woo-fly-cart' ),
													'03' => esc_html__( 'Color background, no thumbnail', 'woo-fly-cart' ),
													'04' => esc_html__( 'White background, no thumbnail', 'woo-fly-cart' ),
													'05' => esc_html__( 'Background image', 'woo-fly-cart' ),
												] );

												echo '<select name="woofc_settings[style]" class="woofc_style">';

												foreach ( $styles as $k => $s ) {
													echo '<option value="' . esc_attr( $k ) . '" ' . selected( $style, $k, false ) . '>' . esc_html( $s ) . '</option>';
												}

												echo '</select>';
												?>
											</td>
										</tr>
										<tr class="woofc_hide_if_style woofc_show_if_style_01 woofc_show_if_style_02 woofc_show_if_style_03 woofc_show_if_style_04">
											<th><?php esc_html_e( 'Color', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" name="woofc_settings[color]" id="woofc_color" value="<?php echo self::get_setting( 'color', '#cc6055' ); ?>" class="woofc_color_picker"/>
												<span class="description"><?php printf( esc_html__( 'Background or text color of selected style, default %s', 'woo-fly-cart' ), '<code>#cc6055</code>' ); ?></span>
											</td>
										</tr>
										<tr class="woofc_hide_if_style woofc_show_if_style_05">
											<th><?php esc_html_e( 'Background image', 'woo-fly-cart' ); ?></th>
											<td>
												<div class="woofc_image_preview" id="woofc_image_preview">
													<?php if ( self::get_setting( 'bg_image', '' ) !== '' ) {
														echo '<img src="' . wp_get_attachment_url( self::get_setting( 'bg_image', '' ) ) . '"/>';
													} ?>
												</div>
												<input id="woofc_upload_image_button" type="button" class="button" value="<?php esc_html_e( 'Upload image', 'woo-fly-cart' ); ?>"/>
												<input type="hidden" name="woofc_settings[bg_image]" id="woofc_image_attachment_url" value="<?php echo self::get_setting( 'bg_image', '' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Close button', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[close]">
													<option value="yes" <?php selected( $close, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $close, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Show/hide the close button.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Link to individual product', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[link]">
													<option value="yes" <?php selected( $link, 'yes' ); ?>><?php esc_html_e( 'Yes, open in the same tab', 'woo-fly-cart' ); ?></option>
													<option value="yes_blank" <?php selected( $link, 'yes_blank' ); ?>><?php esc_html_e( 'Yes, open in the new tab', 'woo-fly-cart' ); ?></option>
													<option value="yes_popup" <?php selected( $link, 'yes_popup' ); ?>><?php esc_html_e( 'Yes, open quick view popup', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $link, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select> <span class="description">If you choose "Open quick view popup", please install <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Smart Quick View">WPC Smart Quick View</a> to make it work.</span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Item data', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[data]">
													<option value="yes" <?php selected( $data, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $data, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Show/hide the item data under title.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Item price', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[price]">
													<option value="no" <?php selected( $price, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
													<option value="price" <?php selected( $price, 'price' ); ?>><?php esc_html_e( 'Price', 'woo-fly-cart' ); ?></option>
													<option value="subtotal" <?php selected( $price, 'subtotal' ); ?>><?php esc_html_e( 'Subtotal', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Show/hide the item price or subtotal under title.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Item estimated delivery date', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[estimated_delivery_date]">
													<option value="yes" <?php selected( $estimated_delivery_date, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $estimated_delivery_date, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Show/hide the item estimated delivery date.', 'woo-fly-cart' ); ?> Please install <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-estimated-delivery-date&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Estimated Delivery Date">WPC Estimated Delivery Date</a> to make it work.</span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Plus/minus button', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[plus_minus]">
													<option value="yes" <?php selected( $plus_minus, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $plus_minus, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Show/hide the plus/minus button.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Item remove', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[remove]">
													<option value="yes" <?php selected( $remove, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $remove, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Show/hide the remove button for each item.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Save for later', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[save_for_later]">
													<option value="yes" <?php selected( $save_for_later, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $save_for_later, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select> <span class="description">Show/hide the save for later button for each product. If you enable this option, please install and activate <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wc-save-for-later&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Save For Later">WPC Save For Later</a> to make it work.</span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Subtotal', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[subtotal]">
													<option value="yes" <?php selected( $subtotal, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $subtotal, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Coupon', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[coupon]">
													<option value="yes" <?php selected( $coupon, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $coupon, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
												<span style="color: #c9356e">This feature is available for Premium Version only.</span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Coupon listing', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[coupon_listing]">
													<option value="yes" <?php selected( $coupon_listing, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $coupon_listing, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select> <span class="description">If you enable this option, please install and activate <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-coupon-listing&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Coupon Listing">WPC Coupon Listing</a> to make it work.</span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Shipping cost', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[shipping_cost]">
													<option value="yes" <?php selected( $shipping_cost, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $shipping_cost, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
												<span style="color: #c9356e">This feature is available for Premium Version only.</span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Shipping calculator', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[shipping_calculator]">
													<option value="yes" <?php selected( $shipping_calculator, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $shipping_calculator, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
												<span style="color: #c9356e">This feature is available for Premium Version only.</span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Free shipping bar', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[free_shipping_bar]">
													<option value="yes" <?php selected( $free_shipping_bar, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $free_shipping_bar, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select> <span class="description">If you enable this option, please install and activate <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-free-shipping-bar&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Free Shipping Bar">WPC Free Shipping Bar</a> to make it work.</span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Total', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[total]">
													<option value="yes" <?php selected( $total, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $total, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Action buttons', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[buttons]">
													<option value="01" <?php selected( $buttons, '01' ); ?>><?php esc_html_e( 'Cart & Checkout', 'woo-fly-cart' ); ?></option>
													<option value="02" <?php selected( $buttons, '02' ); ?>><?php esc_html_e( 'Cart only', 'woo-fly-cart' ); ?></option>
													<option value="03" <?php selected( $buttons, '03' ); ?>><?php esc_html_e( 'Checkout only', 'woo-fly-cart' ); ?></option>
													<option value="hide" <?php selected( $buttons, 'hide' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Instant checkout', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[instant_checkout]" class="woofc_instant_checkout">
													<option value="yes" <?php selected( $instant_checkout, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $instant_checkout, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'If enable this option, buyer can checkout directly on the fly cart.', 'woo-fly-cart' ); ?></span>
												<span style="color: #c9356e">This feature is available for Premium Version only.</span>
											</td>
										</tr>
										<tr class="woofc_hide_if_instant_checkout woofc_show_if_instant_checkout_yes">
											<th><?php esc_html_e( 'Open instant checkout immediately', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[instant_checkout_open]">
													<option value="yes" <?php selected( $instant_checkout_open, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $instant_checkout_open, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Open instant checkout form immediately after adding a product to the cart.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Suggested products', 'woo-fly-cart' ); ?></th>
											<td>
												<?php
												// backward compatibility before 5.2.2
												if ( ! is_array( $suggested ) ) {
													switch ( (string) $suggested ) {
														case 'cross_sells':
															$suggested = [ 'cross_sells' ];
															break;
														case 'related':
															$suggested = [ 'related' ];
															break;
														case 'both':
															$suggested = [ 'related', 'cross_sells' ];
															break;
														case 'none':
															$suggested = [];
															break;
														default:
															$suggested = [];
													}
												}
												?>
												<label><input type="checkbox" name="woofc_settings[suggested][]" value="related" <?php echo esc_attr( in_array( 'related', $suggested ) ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Related products', 'woo-fly-cart' ); ?>
												</label><br/>
												<label><input type="checkbox" name="woofc_settings[suggested][]" value="up_sells" <?php echo esc_attr( in_array( 'up_sells', $suggested ) ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Upsells products', 'woo-fly-cart' ); ?>
												</label><br/>
												<label><input type="checkbox" name="woofc_settings[suggested][]" value="cross_sells" <?php echo esc_attr( in_array( 'cross_sells', $suggested ) ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Cross-sells products', 'woo-fly-cart' ); ?>
												</label>
												<p><span class="description">You can use
													<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-custom-related-products&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Custom Related Products">WPC Custom Related Products</a> or
														<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-smart-linked-products&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Smart Linked Products">WPC Smart Linked Products</a> plugin to configure related/upsells/cross-sells in bulk with smart conditions.</span>
												</p>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Suggested products limit', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="number" min="1" step="1" name="woofc_settings[suggested_limit]" value="<?php echo esc_attr( self::get_setting( 'suggested_limit', 10 ) ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Suggested products carousel', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[suggested_carousel]">
													<option value="yes" <?php selected( $suggested_carousel, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $suggested_carousel, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Empty cart', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[empty]">
													<option value="yes" <?php selected( $empty, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $empty, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Show/hide the empty cart button under the product list.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Confirm empty', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[confirm_empty]">
													<option value="yes" <?php selected( $confirm_empty, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $confirm_empty, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Enable/disable confirm before emptying the cart.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Share cart', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[share]">
													<option value="yes" <?php selected( $share, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $share, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select> <span class="description">If you enable this option, please install and activate <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-share-cart&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Share Cart">WPC Share Cart</a> to make it work.</span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Continue shopping', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[continue]">
													<option value="yes" <?php selected( $continue, 'yes' ); ?>><?php esc_html_e( 'Show', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $continue, 'no' ); ?>><?php esc_html_e( 'Hide', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Show/hide the continue shopping button at the end of fly cart.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Continue shopping URL', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="url" class="regular-text code" name="woofc_settings[continue_url]" value="<?php echo self::get_setting( 'continue_url', '' ); ?>"/>
												<span class="description"><?php esc_html_e( 'Custom URL for "continue shopping" button. By default, only close the fly cart when clicking on this button.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Confirm remove', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[confirm_remove]">
													<option value="yes" <?php selected( $confirm_remove, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $confirm_remove, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Enable/disable confirm before removing a product.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Undo remove', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[undo_remove]">
													<option value="yes" <?php selected( $undo_remove, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $undo_remove, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Enable/disable undo after removing a product.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Reload the cart', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[reload]">
													<option value="yes" <?php selected( $reload, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $reload, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'The cart will be reloaded when opening the page? If you use the cache for your site, please turn on this option.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Hide on Cart & Checkout', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[hide_cart_checkout]">
													<option value="yes" <?php selected( $hide_cart_checkout, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $hide_cart_checkout, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Hide the fly cart on the Cart and Checkout page.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr class="heading">
											<th><?php esc_html_e( 'Bubble', 'woo-fly-cart' ); ?></th>
											<td><?php esc_html_e( 'Settings for the bubble.', 'woo-fly-cart' ); ?></td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Enable', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[count]">
													<option value="yes" <?php selected( $count, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $count, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Position', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[count_position]">
													<option value="top-left" <?php selected( $count_position, 'top-left' ); ?>><?php esc_html_e( 'Top Left', 'woo-fly-cart' ); ?></option>
													<option value="top-right" <?php selected( $count_position, 'top-right' ); ?>><?php esc_html_e( 'Top Right', 'woo-fly-cart' ); ?></option>
													<option value="bottom-left" <?php selected( $count_position, 'bottom-left' ); ?>><?php esc_html_e( 'Bottom Left', 'woo-fly-cart' ); ?></option>
													<option value="bottom-right" <?php selected( $count_position, 'bottom-right' ); ?>><?php esc_html_e( 'Bottom Right', 'woo-fly-cart' ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Icon', 'woo-fly-cart' ); ?></th>
											<td>
												<select id="woofc_count_icon" name="woofc_settings[count_icon]">
													<?php
													for ( $i = 1; $i <= 16; $i ++ ) {
														if ( self::get_setting( 'count_icon', 'woofc-icon-cart7' ) === 'woofc-icon-cart' . $i ) {
															echo '<option value="woofc-icon-cart' . $i . '" selected>woofc-icon-cart' . $i . '</option>';
														} else {
															echo '<option value="woofc-icon-cart' . $i . '">woofc-icon-cart' . $i . '</option>';
														}
													}
													?>
												</select>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Hide if empty', 'woo-fly-cart' ); ?></th>
											<td>
												<select name="woofc_settings[count_hide_empty]">
													<option value="yes" <?php selected( $count_hide_empty, 'yes' ); ?>><?php esc_html_e( 'Yes', 'woo-fly-cart' ); ?></option>
													<option value="no" <?php selected( $count_hide_empty, 'no' ); ?>><?php esc_html_e( 'No', 'woo-fly-cart' ); ?></option>
												</select>
												<span class="description"><?php esc_html_e( 'Hide the bubble if the cart is empty?', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr class="heading">
											<th><?php esc_html_e( 'Menu', 'woo-fly-cart' ); ?></th>
											<td><?php esc_html_e( 'Settings for cart menu item.', 'woo-fly-cart' ); ?></td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Menu', 'woo-fly-cart' ); ?></th>
											<td>
												<?php
												$nav_args    = [
													'hide_empty' => false,
													'fields'     => 'id=>name',
												];
												$nav_menus   = get_terms( 'nav_menu', $nav_args );
												$saved_menus = self::get_setting( 'menus', [] );

												foreach ( $nav_menus as $nav_id => $nav_name ) {
													echo '<label><input type="checkbox" name="woofc_settings[menus][]" value="' . esc_attr( $nav_id ) . '" ' . ( is_array( $saved_menus ) && in_array( $nav_id, $saved_menus, false ) ? 'checked' : '' ) . '/> ' . esc_html( $nav_name ) . '</label><br/>';
												}
												?>
												<span class="description"><?php esc_html_e( 'Choose the menu(s) you want to add the cart at the end.', 'woo-fly-cart' ); ?></span>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Custom menu', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_settings[manual_show]" value="<?php echo self::get_setting( 'manual_show', '' ); ?>" placeholder="<?php esc_attr_e( 'button class or id', 'woo-fly-cart' ); ?>"/>
												<span class="description"><?php printf( esc_html__( 'The class or id of the custom menu. When clicking on it, the fly cart will show up. Example %s or %s', 'woo-fly-cart' ), '<code>.fly-cart-btn</code>', '<code>#fly-cart-btn</code>' ); ?></span>
											</td>
										</tr>
										<tr class="heading">
											<th colspan="2"><?php esc_html_e( 'Suggestion', 'woo-fly-cart' ); ?></th>
										</tr>
										<tr>
											<td colspan="2">
												To display custom engaging real-time messages on any wished positions, please install
												<a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages for WooCommerce</a> plugin. It's free!
											</td>
										</tr>
										<tr class="submit">
											<th colspan="2">
												<?php settings_fields( 'woofc_settings' ); ?><?php submit_button(); ?>
											</th>
										</tr>
									</table>
								</form>
							<?php } elseif ( $active_tab === 'localization' ) { ?>
								<form method="post" action="options.php">
									<table class="form-table">
										<tr class="heading">
											<th scope="row"><?php esc_html_e( 'Localization', 'woo-fly-cart' ); ?></th>
											<td>
												<?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'woo-fly-cart' ); ?>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Cart heading', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[heading]" value="<?php echo esc_attr( self::localization( 'heading' ) ); ?>" placeholder="<?php esc_attr_e( 'Shopping cart', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Close', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[close]" value="<?php echo esc_attr( self::localization( 'close' ) ); ?>" placeholder="<?php esc_attr_e( 'Close', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Remove', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[remove]" value="<?php echo esc_attr( self::localization( 'remove' ) ); ?>" placeholder="<?php esc_attr_e( 'Remove', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Confirm remove', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[remove_confirm]" value="<?php echo esc_attr( self::localization( 'remove_confirm' ) ); ?>" placeholder="<?php esc_attr_e( 'Do you want to remove this item?', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Undo remove', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[remove_undo]" value="<?php echo esc_attr( self::localization( 'remove_undo' ) ); ?>" placeholder="<?php esc_attr_e( 'Undo?', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Removed', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[removed]" value="<?php echo esc_attr( self::localization( 'removed' ) ); ?>" placeholder="<?php esc_attr_e( '%s was removed.', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Empty cart', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[empty]" value="<?php echo esc_attr( self::localization( 'empty' ) ); ?>" placeholder="<?php esc_attr_e( 'Empty cart', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Confirm empty', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[empty_confirm]" value="<?php echo esc_attr( self::localization( 'empty_confirm' ) ); ?>" placeholder="<?php esc_attr_e( 'Do you want to empty the cart?', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Share cart', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[share]" value="<?php echo esc_attr( self::localization( 'share' ) ); ?>" placeholder="<?php esc_attr_e( 'Share cart', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Subtotal', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[subtotal]" value="<?php echo esc_attr( self::localization( 'subtotal' ) ); ?>" placeholder="<?php esc_attr_e( 'Subtotal', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Coupon code', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[coupon_code]" value="<?php echo esc_attr( self::localization( 'coupon_code' ) ); ?>" placeholder="<?php esc_attr_e( 'Coupon code', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Coupon apply', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[coupon_apply]" value="<?php echo esc_attr( self::localization( 'coupon_apply' ) ); ?>" placeholder="<?php esc_attr_e( 'Apply', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Shipping', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[shipping]" value="<?php echo esc_attr( self::localization( 'shipping' ) ); ?>" placeholder="<?php esc_attr_e( 'Shipping', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Total', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[total]" value="<?php echo esc_attr( self::localization( 'total' ) ); ?>" placeholder="<?php esc_attr_e( 'Total', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Cart', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[cart]" value="<?php echo esc_attr( self::localization( 'cart' ) ); ?>" placeholder="<?php esc_attr_e( 'Cart', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Checkout', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[checkout]" value="<?php echo esc_attr( self::localization( 'checkout' ) ); ?>" placeholder="<?php esc_attr_e( 'Checkout', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Continue shopping', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[continue]" value="<?php echo esc_attr( self::localization( 'continue' ) ); ?>" placeholder="<?php esc_attr_e( 'Continue shopping', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'Suggested products', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[suggested]" value="<?php echo esc_attr( self::localization( 'suggested' ) ); ?>" placeholder="<?php esc_attr_e( 'You may be interested in&hellip;', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr>
											<th><?php esc_html_e( 'There are no products', 'woo-fly-cart' ); ?></th>
											<td>
												<input type="text" class="regular-text" name="woofc_localization[no_products]" value="<?php echo esc_attr( self::localization( 'no_products' ) ); ?>" placeholder="<?php esc_attr_e( 'There are no products in the cart!', 'woo-fly-cart' ); ?>"/>
											</td>
										</tr>
										<tr class="submit">
											<th colspan="2">
												<?php settings_fields( 'woofc_localization' ); ?><?php submit_button(); ?>
											</th>
										</tr>
									</table>
								</form>
							<?php } elseif ( $active_tab === 'premium' ) { ?>
								<div class="wpclever_settings_page_content_text">
									<p>Get the Premium Version just $29!
										<a href="https://wpclever.net/downloads/fly-cart?utm_source=pro&utm_medium=woofc&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/fly-cart</a>
									</p>
									<p><strong>Extra features for Premium Version:</strong></p>
									<ul style="margin-bottom: 0">
										<li>- Enable coupon form.</li>
										<li>- Enable shipping cost and shipping calculator.</li>
										<li>- Enable instant checkout.</li>
										<li>- Get lifetime update & premium support.</li>
									</ul>
								</div>
							<?php } ?>
						</div>
					</div>
					<?php
				}

				function update_qty() {
					if ( isset( $_POST['cart_item_key'], $_POST['cart_item_qty'] ) && ! empty( $_POST['cart_item_key'] ) ) {
						if ( WC()->cart->get_cart_item( sanitize_text_field( $_POST['cart_item_key'] ) ) ) {
							if ( (float) sanitize_text_field( $_POST['cart_item_qty'] ) > 0 ) {
								WC()->cart->set_quantity( sanitize_text_field( $_POST['cart_item_key'] ), (float) sanitize_text_field( $_POST['cart_item_qty'] ) );
							} else {
								WC()->cart->remove_cart_item( sanitize_text_field( $_POST['cart_item_key'] ) );
							}
						}

						wp_send_json( [ 'action' => 'update_qty' ] );
					}

					wp_die();
				}

				function remove_item() {
					if ( isset( $_POST['cart_item_key'] ) ) {
						WC()->cart->remove_cart_item( sanitize_text_field( $_POST['cart_item_key'] ) );
						WC_AJAX::get_refreshed_fragments();
					}

					wp_die();
				}

				function undo_remove() {
					if ( isset( $_POST['item_key'] ) ) {
						if ( WC()->cart->restore_cart_item( sanitize_text_field( $_POST['item_key'] ) ) ) {
							echo 'true';
						} else {
							echo 'false';
						}
					}

					wp_die();
				}

				function empty_cart() {
					WC()->cart->empty_cart();
					WC_AJAX::get_refreshed_fragments();

					wp_die();
				}

				function get_cart_area() {
					if ( ! isset( WC()->cart ) ) {
						return '';
					}

					// settings
					$link               = self::get_setting( 'link', 'yes' );
					$plus_minus         = self::get_setting( 'plus_minus', 'yes' ) === 'yes';
					$remove             = self::get_setting( 'remove', 'yes' ) === 'yes';
					$suggested          = self::get_setting( 'suggested', [] );
					$suggested_products = [];

					// backward compatibility before 5.2.2
					if ( ! is_array( $suggested ) ) {
						switch ( (string) $suggested ) {
							case 'cross_sells':
								$suggested = [ 'cross_sells' ];
								break;
							case 'related':
								$suggested = [ 'related' ];
								break;
							case 'both':
								$suggested = [ 'related', 'cross_sells' ];
								break;
							case 'none':
								$suggested = [];
								break;
							default:
								$suggested = [];
						}
					}

					ob_start();

					// global product
					global $product;
					$global_product = $product;

					echo '<div class="woofc-inner woofc-cart-area">';

					do_action( 'woofc_above_area' );
					echo apply_filters( 'woofc_above_area_content', '' );

					echo '<div class="woofc-area-top"><span class="woofc-area-heading">' . self::localization( 'heading', esc_html__( 'Shopping cart', 'woo-fly-cart' ) ) . '<span class="woofc-area-count">' . WC()->cart->get_cart_contents_count() . '</span></span>';

					if ( self::get_setting( 'close', 'yes' ) === 'yes' ) {
						echo '<div class="woofc-close hint--left" aria-label="' . esc_attr( self::localization( 'close', esc_html__( 'Close', 'woo-fly-cart' ) ) ) . '"><i class="woofc-icon-icon10"></i></div>';
					}

					echo '</div><!-- woofc-area-top -->';
					echo '<div class="woofc-area-mid woofc-items">';

					do_action( 'woofc_above_items' );
					echo apply_filters( 'woofc_above_items_content', '' );

					// notices
					if ( apply_filters( 'woofc_show_notices', true ) ) {
						$notices = wc_print_notices( true );

						if ( ! empty( $notices ) ) {
							echo '<div class="woofc-notices">' . $notices . '</div>';
						}
					}

					$items = WC()->cart->get_cart();

					if ( is_array( $items ) && ( count( $items ) > 0 ) ) {
						if ( apply_filters( 'woofc_cart_items_reverse', self::get_setting( 'reverse_items', 'yes' ) === 'yes' ) ) {
							$items = array_reverse( $items );
						}

						foreach ( $items as $cart_item_key => $cart_item ) {
							if ( ! isset( $cart_item['bundled_by'] ) && apply_filters( 'woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
								$product      = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
								$product_id   = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
								$product_link = apply_filters( 'woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
								$item_class   = $remove ? 'woofc-item woofc-item-has-remove' : 'woofc-item';

								// add suggested products
								if ( is_array( $suggested ) && ! empty( $suggested ) ) {
									if ( in_array( 'related', $suggested ) ) {
										$suggested_products = array_merge( $suggested_products, wc_get_related_products( $product_id ) );
									}

									if ( in_array( 'cross_sells', $suggested ) ) {
										$suggested_products = array_merge( $suggested_products, $product->get_cross_sell_ids() );
									}

									if ( in_array( 'up_sells', $suggested ) ) {
										$suggested_products = array_merge( $suggested_products, $product->get_upsell_ids() );
									}
								}

								echo '<div class="' . esc_attr( apply_filters( 'woocommerce_cart_item_class', $item_class, $cart_item, $cart_item_key ) ) . '" data-key="' . esc_attr( $cart_item_key ) . '" data-name="' . esc_attr( $product->get_name() ) . '">';

								do_action( 'woofc_above_item', $cart_item );
								echo apply_filters( 'woofc_above_item_inner', '', $cart_item );

								echo '<div class="woofc-item-inner">';
								echo '<div class="woofc-item-thumb">';

								if ( ( $link !== 'no' ) && ! empty( $product_link ) ) {
									$cart_item_thumbnail = sprintf( '<a ' . ( $link === 'yes_popup' ? 'class="woosq-link" data-id="' . esc_attr( $product_id ) . '" data-context="woofc"' : '' ) . ' href="%s" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>%s</a>', esc_url( $product_link ), $product->get_image() );
								} else {
									$cart_item_thumbnail = $product->get_image();
								}

								echo apply_filters( 'woocommerce_cart_item_thumbnail', $cart_item_thumbnail, $cart_item, $cart_item_key );
								echo '</div><!-- /.woofc-item-thumb -->';

								echo '<div class="woofc-item-info">';

								do_action( 'woofc_above_item_info', $product, $cart_item );
								//echo apply_filters( 'woofc_above_item_info', '', $product, $cart_item );

								do_action( 'woofc_above_item_name', $product, $cart_item );

								echo '<span class="woofc-item-title">';

								if ( ( $link !== 'no' ) && ! empty( $product_link ) ) {
									$cart_item_name = sprintf( '<a ' . ( $link === 'yes_popup' ? 'class="woosq-link" data-id="' . esc_attr( $product_id ) . '" data-context="woofc"' : '' ) . ' href="%s" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>%s</a>', esc_url( $product_link ), $product->get_name() );
								} else {
									$cart_item_name = $product->get_name();
								}

								echo apply_filters( 'woocommerce_cart_item_name', $cart_item_name, $cart_item, $cart_item_key );
								echo '</span><!-- /.woofc-item-title -->';

								do_action( 'woofc_below_item_name', $product, $cart_item );

								if ( self::get_setting( 'data', 'no' ) === 'yes' ) {
									echo apply_filters( 'woofc_cart_item_data', '<span class="woofc-item-data">' . wc_get_formatted_cart_item_data( $cart_item, apply_filters( 'woofc_cart_item_data_flat', true ) ) . '</span>', $cart_item );
								}

								if ( self::get_setting( 'price', 'price' ) === 'price' ) {
									echo '<span class="woofc-item-price">' . apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $product ), $cart_item, $cart_item_key ) . '</span>';
								} elseif ( self::get_setting( 'price', 'price' ) === 'subtotal' ) {
									echo '<span class="woofc-item-price">' . apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ), $cart_item, $cart_item_key ) . '</span>';
								}

								if ( ( self::get_setting( 'estimated_delivery_date', 'no' ) === 'yes' ) && class_exists( 'WPCleverWpced' ) ) {
									echo apply_filters( 'woofc_cart_item_estimated_delivery_date', '<span class="woofc-item-estimated-delivery-date">' . do_shortcode( '[wpced]' ) . '</span>', $cart_item );
								}

								if ( ( self::get_setting( 'save_for_later', 'yes' ) === 'yes' ) && class_exists( 'WPCleverWoosl' ) ) {
									if ( $cart_item['data']->is_type( 'variation' ) && is_array( $cart_item['variation'] ) ) {
										$variation = htmlspecialchars( json_encode( $cart_item['variation'] ), ENT_QUOTES, 'UTF-8' );
									} else {
										$variation = '';
									}

									echo '<span class="woofc-item-save">' . do_shortcode( '[woosl_btn product_id="' . $cart_item['product_id'] . '" variation_id="' . $cart_item['variation_id'] . '" price="' . $cart_item['data']->get_price() . '" variation="' . $variation . '" cart_item_key="' . $cart_item_key . '" context="woofc"]' ) . '</span>';
								}

								do_action( 'woofc_below_item_info', $product, $cart_item );
								//echo apply_filters( 'woofc_below_item_info', '', $product, $cart_item );

								echo '</div><!-- /.woofc-item-info -->';

								$min_value = apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product );
								$max_value = apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product );

								if ( $product->is_sold_individually() || ( $max_value && $min_value === $max_value ) || ! empty( $cart_item['woosb_parent_id'] ) || ! empty( $cart_item['wooco_parent_id'] ) || ! empty( $cart_item['woofs_parent_id'] ) ) {
									$cart_item_quantity = $cart_item['quantity'];
								} else {
									$cart_item_qty            = isset( $cart_item['quantity'] ) ? wc_stock_amount( wp_unslash( $cart_item['quantity'] ) ) : $product->get_min_purchase_quantity();
									$cart_item_quantity_input = woocommerce_quantity_input( [
										'classes'     => [ 'input-text', 'woofc-qty', 'qty', 'text' ],
										'input_name'  => 'woofc_qty_' . $cart_item_key,
										'input_value' => $cart_item_qty,
										'min_value'   => $min_value,
										'max_value'   => $max_value,
										'woofc_qty'   => [
											'input_value' => $cart_item_qty,
											'min_value'   => $min_value,
											'max_value'   => $max_value
										]
									], $product, false );

									if ( $plus_minus ) {
										$cart_item_quantity = '<span class="woofc-item-qty-minus">-</span>' . $cart_item_quantity_input . '<span class="woofc-item-qty-plus">+</span>';
									} else {
										$cart_item_quantity = $cart_item_quantity_input;
									}
								}

								echo '<div class="woofc-item-qty ' . ( $plus_minus ? 'woofc-item-qty-plus-minus' : '' ) . '"><div class="woofc-item-qty-inner">' . apply_filters( 'woocommerce_cart_item_quantity', $cart_item_quantity, $cart_item_key, $cart_item ) . '</div></div><!-- /.woofc-item-qty -->';

								if ( $remove ) {
									echo apply_filters( 'woocommerce_cart_item_remove_link', '<span class="woofc-item-remove"><span class="hint--left" aria-label="' . esc_attr( self::localization( 'remove', esc_html__( 'Remove', 'woo-fly-cart' ) ) ) . '"><i class="woofc-icon-icon10"></i></span></span>', $cart_item_key );
								}

								echo '</div><!-- /.woofc-item-inner -->';

								do_action( 'woofc_below_item', $cart_item );
								echo apply_filters( 'woofc_below_item_inner', '', $cart_item );

								echo '</div><!-- /.woofc-item -->';
							}
						}
					} else {
						echo '<div class="woofc-no-item">' . self::localization( 'no_products', esc_html__( 'There are no products in the cart!', 'woo-fly-cart' ) ) . '</div>';

						if ( ( self::get_setting( 'save_for_later', 'yes' ) === 'yes' ) && class_exists( 'WPCleverWoosl' ) ) {
							echo '<div class="woofc-save-for-later">' . do_shortcode( '[woosl_list context="woofc"]' ) . '</div>';
						}
					}

					do_action( 'woofc_below_items' );
					echo apply_filters( 'woofc_below_items_content', '' );

					echo '</div><!-- woofc-area-mid -->';

					echo '<div class="woofc-area-bot">';

					do_action( 'woofc_above_bottom' );
					echo apply_filters( 'woofc_above_bottom_content', '' );

					if ( ! empty( $items ) ) {
						if ( self::get_setting( 'empty', 'no' ) === 'yes' || self::get_setting( 'share', 'yes' ) === 'yes' ) {
							// enable empty or share
							echo '<div class="woofc-link">';

							if ( self::get_setting( 'empty', 'no' ) === 'yes' ) {
								echo '<div class="woofc-empty"><span class="woofc-empty-cart">' . self::localization( 'empty', esc_html__( 'Empty cart', 'woo-fly-cart' ) ) . '</span></div>';
							}

							if ( self::get_setting( 'share', 'yes' ) === 'yes' ) {
								echo '<div class="woofc-share"><span class="woofc-share-cart wpcss-btn" data-hash="' . esc_attr( WC()->cart->get_cart_hash() ) . '">' . self::localization( 'share', esc_html__( 'Share cart', 'woo-fly-cart' ) ) . '</span></div>';
							}

							echo '</div>';
						}

						if ( self::get_setting( 'subtotal', 'yes' ) === 'yes' ) {
							echo apply_filters( 'woofc_above_subtotal_content', '' );
							echo '<div class="woofc-data"><div class="woofc-data-left">' . self::localization( 'subtotal', esc_html__( 'Subtotal', 'woo-fly-cart' ) ) . '</div><div id="woofc-subtotal" class="woofc-data-right">' . apply_filters( 'woofc_get_subtotal', WC()->cart->get_cart_subtotal() ) . '</div></div>';
							echo apply_filters( 'woofc_below_subtotal_content', '' );
						}

						if ( class_exists( 'WPCleverWpcfb' ) && ( self::get_setting( 'free_shipping_bar', 'yes' ) === 'yes' ) ) {
							echo '<div class="woofc-data">' . do_shortcode( '[wpcfb]' ) . '</div>';
						}

						if ( self::get_setting( 'total', 'yes' ) === 'yes' ) {
							echo apply_filters( 'woofc_above_total_content', '' );
							echo '<div class="woofc-data"><div class="woofc-data-left">' . self::localization( 'total', esc_html__( 'Total', 'woo-fly-cart' ) ) . '</div><div id="woofc-total" class="woofc-data-right">' . apply_filters( 'woofc_get_total', WC()->cart->get_total() ) . '</div></div>';
							echo apply_filters( 'woofc_below_total_content', '' );
						}

						do_action( 'woofc_above_buttons' );

						if ( self::get_setting( 'buttons', '01' ) === '01' ) {
							// both buttons
							echo '<div class="woofc-action"><div class="woofc-action-inner"><div class="woofc-action-left"><a class="woofc-action-cart" href="' . wc_get_cart_url() . '">' . self::localization( 'cart', esc_html__( 'Cart', 'woo-fly-cart' ) ) . '</a></div><div class="woofc-action-right"><a class="woofc-action-checkout" href="' . wc_get_checkout_url() . '">' . self::localization( 'checkout', esc_html__( 'Checkout', 'woo-fly-cart' ) ) . '</a></div></div></div>';
						} else {
							if ( self::get_setting( 'buttons', '01' ) === '02' ) {
								// cart
								echo '<div class="woofc-action"><div class="woofc-action-inner"><div class="woofc-action-full"><a class="woofc-action-cart" href="' . wc_get_cart_url() . '">' . self::localization( 'cart', esc_html__( 'Cart', 'woo-fly-cart' ) ) . '</a></div></div></div>';
							}

							if ( self::get_setting( 'buttons', '01' ) === '03' ) {
								// checkout
								echo '<div class="woofc-action"><div class="woofc-action-inner"><div class="woofc-action-full"><a class="woofc-action-checkout" href="' . wc_get_checkout_url() . '">' . self::localization( 'checkout', esc_html__( 'Checkout', 'woo-fly-cart' ) ) . '</a></div></div></div>';
							}
						}

						do_action( 'woofc_below_buttons' );

						if ( ( self::get_setting( 'save_for_later', 'yes' ) === 'yes' ) && class_exists( 'WPCleverWoosl' ) ) {
							echo '<div class="woofc-save-for-later">' . do_shortcode( '[woosl_list context="woofc"]' ) . '</div>';
						}

						if ( ! empty( $suggested ) ) {
							$suggested_products = array_unique( $suggested_products );
							$suggested_limit    = (int) self::get_setting( 'suggested_limit', 10 );

							if ( $suggested_limit ) {
								$suggested_products = array_slice( $suggested_products, 0, $suggested_limit );
							}

							$suggested_products = apply_filters( 'woofc_suggested_products', $suggested_products );

							if ( ! empty( $suggested_products ) ) {
								do_action( 'woofc_above_suggested', $suggested_products );
								echo apply_filters( 'woofc_above_suggested_content', '' );
								echo '<div class="woofc-suggested">';
								echo '<div class="woofc-suggested-heading"><span>' . self::localization( 'suggested', esc_html__( 'You may be interested in&hellip;', 'woo-fly-cart' ) ) . '</span></div>';
								echo '<div class="woofc-suggested-products ' . ( ( count( $suggested_products ) > 1 ) && ( apply_filters( 'woofc_slick', self::get_setting( 'suggested_carousel', 'yes' ) ) === 'yes' ) ? 'woofc-suggested-products-slick' : '' ) . '">';

								foreach ( $suggested_products as $suggested_product_id ) {
									$suggested_product = wc_get_product( $suggested_product_id );

									if ( $suggested_product ) {
										$suggested_product_link = $suggested_product->is_visible() ? $suggested_product->get_permalink() : '';

										echo '<div class="woofc-suggested-product">';
										echo '<div class="woofc-suggested-product-image">';

										if ( ( $link !== 'no' ) && ! empty( $suggested_product_link ) ) {
											echo sprintf( '<a ' . ( $link === 'yes_popup' ? 'class="woosq-link" data-id="' . esc_attr( $suggested_product_id ) . '" data-context="woofc"' : '' ) . ' href="%s" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>%s</a>', esc_url( $suggested_product_link ), $suggested_product->get_image() );
										} else {
											echo $suggested_product->get_image();
										}

										echo '</div>';
										echo '<div class="woofc-suggested-product-info">';
										echo '<div class="woofc-suggested-product-name">';

										if ( ( $link !== 'no' ) && ! empty( $suggested_product_link ) ) {
											echo sprintf( '<a ' . ( $link === 'yes_popup' ? 'class="woosq-link" data-id="' . esc_attr( $suggested_product_id ) . '" data-context="woofc"' : '' ) . ' href="%s" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ) . '>%s</a>', esc_url( $suggested_product_link ), $suggested_product->get_name() );
										} else {
											echo $suggested_product->get_name();
										}

										echo '</div>';
										echo '<div class="woofc-suggested-product-price">' . $suggested_product->get_price_html() . '</div>';
										echo '<div class="woofc-suggested-product-atc">' . do_shortcode( '[add_to_cart style="" show_price="false" id="' . esc_attr( $suggested_product->get_id() ) . '"]' ) . '</div>';
										echo '</div>';
										echo '</div>';
									}
								}

								echo '</div></div>';
								echo apply_filters( 'woofc_below_suggested_content', '' );
								do_action( 'woofc_below_suggested', $suggested_products );
							}
						}
					}

					if ( self::get_setting( 'continue', 'yes' ) === 'yes' ) {
						echo '<div class="woofc-continue"><span class="woofc-continue-url" data-url="' . esc_url( self::get_setting( 'continue_url', '' ) ) . '">' . self::localization( 'continue', esc_html__( 'Continue shopping', 'woo-fly-cart' ) ) . '</span></div>';
					}

					do_action( 'woofc_below_bottom' );
					echo apply_filters( 'woofc_below_bottom_content', '' );

					echo '</div><!-- woofc-area-bot -->';

					do_action( 'woofc_below_area' );
					echo apply_filters( 'woofc_below_area_content', '' );

					echo '</div>';

					$product = $global_product;

					return ob_get_clean();
				}

				function get_cart_count() {
					if ( ! isset( WC()->cart ) ) {
						return '';
					}

					$count       = WC()->cart->get_cart_contents_count();
					$icon        = self::get_setting( 'count_icon', 'woofc-icon-cart7' );
					$count_class = 'woofc-count woofc-count-' . self::get_setting( 'count_position', 'bottom-left' );

					if ( ( self::get_setting( 'hide_cart_checkout', 'no' ) === 'yes' ) ) {
						$count_class .= ' woofc-count-hide-cart-checkout';
					}

					if ( ( self::get_setting( 'count_hide_empty', 'no' ) === 'yes' ) && ( $count <= 0 ) ) {
						$count_class .= ' woofc-count-hide-empty';
					}

					ob_start();

					echo '<div id="woofc-count" class="' . esc_attr( $count_class ) . '">';
					echo '<i class="' . esc_attr( $icon ) . '"></i>';
					echo '<span id="woofc-count-number" class="woofc-count-number">' . esc_attr( $count ) . '</span>';
					echo '</div>';

					return apply_filters( 'woofc_cart_count', ob_get_clean(), $count, $icon );
				}

				function get_cart_menu() {
					if ( ! isset( WC()->cart ) ) {
						return '';
					}

					$count     = WC()->cart->get_cart_contents_count();
					$subtotal  = WC()->cart->get_cart_subtotal();
					$icon      = self::get_setting( 'count_icon', 'woofc-icon-cart7' );
					$cart_menu = '<li class="' . apply_filters( 'woofc_cart_menu_class', 'menu-item woofc-menu-item menu-item-type-woofc' ) . '"><a href="' . wc_get_cart_url() . '"><span class="woofc-menu-item-inner" data-count="' . esc_attr( $count ) . '"><i class="' . esc_attr( $icon ) . '"></i> <span class="woofc-menu-item-inner-subtotal">' . $subtotal . '</span></span></a></li>';

					return apply_filters( 'woofc_cart_menu', $cart_menu, $count, $subtotal, $icon );
				}

				function nav_menu_items( $items, $args ) {
					$selected    = false;
					$saved_menus = self::get_setting( 'menus', [] );

					if ( ! is_array( $saved_menus ) || empty( $saved_menus ) || ! property_exists( $args, 'menu' ) ) {
						return $items;
					}

					if ( $args->menu instanceof WP_Term ) {
						// menu object
						if ( in_array( $args->menu->term_id, $saved_menus, false ) ) {
							$selected = true;
						}
					} elseif ( is_numeric( $args->menu ) ) {
						// menu id
						if ( in_array( $args->menu, $saved_menus, false ) ) {
							$selected = true;
						}
					} elseif ( is_string( $args->menu ) ) {
						// menu slug or name
						$menu = get_term_by( 'name', $args->menu, 'nav_menu' );

						if ( ! $menu ) {
							$menu = get_term_by( 'slug', $args->menu, 'nav_menu' );
						}

						if ( $menu && in_array( $menu->term_id, $saved_menus ) ) {
							$selected = true;
						}
					}

					if ( $selected ) {
						$items .= self::get_cart_menu();
					}

					return $items;
				}

				function footer() {
					if ( apply_filters( 'woofc_disable', false ) ) {
						return;
					}

					if ( ( self::get_setting( 'hide_cart_checkout', 'no' ) === 'yes' ) && ( is_cart() || is_checkout() ) ) {
						return;
					}

					// use 'woofc-position-' instead of 'woofc-effect-' from 5.3
					$area_class = apply_filters( 'woofc_area_class', 'woofc-area woofc-position-' . esc_attr( self::get_setting( 'position', '05' ) ) . ' woofc-effect-' . esc_attr( self::get_setting( 'position', '05' ) ) . ' woofc-slide-' . esc_attr( self::get_setting( 'effect', 'yes' ) ) . ' woofc-style-' . esc_attr( self::get_setting( 'style', '01' ) ) );

					echo '<div id="woofc-area" class="' . esc_attr( $area_class ) . '">';
					echo self::get_cart_area();

					echo '</div>';

					if ( self::get_setting( 'count', 'yes' ) === 'yes' ) {
						echo self::get_cart_count();
					}

					if ( self::get_setting( 'overlay_layer', 'yes' ) === 'yes' ) {
						echo '<div class="woofc-overlay"></div>';
					}

					if ( self::get_setting( 'auto_show_normal', 'yes' ) === 'yes' ) {
						$requests = apply_filters( 'woofc_auto_show_requests', [
							'add-to-cart',
							'product_added_to_cart',
							'added_to_cart',
							'set_cart',
							'fill_cart'
						] );

						if ( is_array( $requests ) && ! empty( $requests ) ) {
							foreach ( $requests as $request ) {
								if ( isset( $_REQUEST[ $request ] ) ) {
									?>
									<script>
                                      jQuery(document).ready(function() {
                                        setTimeout(function() {
                                          if (woofc_vars.instant_checkout === 'yes' &&
                                              woofc_vars.instant_checkout_open === 'yes') {
                                            woofc_show_cart('checkout');
                                          } else {
                                            woofc_show_cart();
                                          }
                                        }, 1000);
                                      });
									</script>
									<?php
									break;
								}
							}
						}
					}
				}

				function cart_fragment( $fragments ) {
					ob_start();
					echo self::get_cart_count();
					$fragments['.woofc-count'] = ob_get_clean();

					ob_start();
					echo self::get_cart_menu();
					$fragments['.woofc-menu-item'] = ob_get_clean();

					ob_start();
					echo self::get_cart_link();
					$fragments['.woofc-cart-link'] = ob_get_clean();

					ob_start();
					echo self::get_cart_area();
					$fragments['.woofc-cart-area'] = ob_get_clean();

					return $fragments;
				}

				function wpcsm_locations( $locations ) {
					$locations['WPC Fly Cart'] = [
						'woofc_above_area'      => esc_html__( 'Before cart', 'woo-fly-cart' ),
						'woofc_below_area'      => esc_html__( 'After cart', 'woo-fly-cart' ),
						'woofc_above_items'     => esc_html__( 'Before cart items', 'woo-fly-cart' ),
						'woofc_below_items'     => esc_html__( 'After cart items', 'woo-fly-cart' ),
						'woofc_above_item'      => esc_html__( 'Before cart item', 'woo-fly-cart' ),
						'woofc_below_item'      => esc_html__( 'After cart item', 'woo-fly-cart' ),
						'woofc_above_item_info' => esc_html__( 'Before cart item info', 'woo-fly-cart' ),
						'woofc_below_item_info' => esc_html__( 'After cart item info', 'woo-fly-cart' ),
						'woofc_above_item_name' => esc_html__( 'Before cart item name', 'woo-fly-cart' ),
						'woofc_below_item_name' => esc_html__( 'After cart item name', 'woo-fly-cart' ),
						'woofc_above_suggested' => esc_html__( 'Before suggested products', 'woo-fly-cart' ),
						'woofc_below_suggested' => esc_html__( 'After suggested products', 'woo-fly-cart' ),
						'woofc_above_buttons'   => esc_html__( 'Before buttons', 'woo-fly-cart' ),
						'woofc_below_buttons'   => esc_html__( 'After buttons', 'woo-fly-cart' ),
					];

					return $locations;
				}

				public static function get_cart_link( $echo = false ) {
					if ( ! isset( WC()->cart ) ) {
						return '';
					}

					$count     = WC()->cart->get_cart_contents_count();
					$subtotal  = WC()->cart->get_cart_subtotal();
					$icon      = self::get_setting( 'count_icon', 'woofc-icon-cart7' );
					$cart_link = '<span class="woofc-cart-link"><a href="' . wc_get_cart_url() . '"><span class="woofc-cart-link-inner" data-count="' . esc_attr( $count ) . '"><i class="' . esc_attr( $icon ) . '"></i> <span class="woofc-cart-link-inner-subtotal">' . $subtotal . '</span></span></a></span>';
					$cart_link = apply_filters( 'woofc_cart_link', $cart_link, $count, $subtotal, $icon );

					if ( $echo ) {
						echo $cart_link;
					} else {
						return $cart_link;
					}
				}

				public static function woofc_get_cart_link( $echo = false ) {
					self::get_cart_link( $echo );
				}
			}

			return WPCleverWoofc::instance();
		}
	}
}

if ( ! function_exists( 'woofc_notice_wc' ) ) {
	function woofc_notice_wc() {
		?>
		<div class="error">
			<p><strong>WPC Fly Cart</strong> requires WooCommerce version 3.0 or greater.</p>
		</div>
		<?php
	}
}
