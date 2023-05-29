<?php
/**
 * Select Quantity By Attribute For WooCommerce
 *
 * @package   select_quantity_by_attribute_for_woocommerce
 * @author    Mohammad Mahdi Kabir <2m.kabir@gmail.com>
 * @license   GPL-2.0+
 * @link      https://github.com/2m.kabir/select-quantity-by-attribute-for-woocommerce
 * @copyright 2023 Mohammad Mahdi Kabir
 *
 * @wordpress-plugin
 * Plugin Name:       Select Quantity By Attribute For WooCommerce
 * Plugin URI:        https://github.com/2m.kabir/select-quantity-by-attribute-for-woocommerce
 * Description:       By utilizing attributes of WooCommerce, compel the customer to purchase a product based on a defined quantity. In other words, enable the sale of bulk/pack products.
 * Version:           1.0.0
 * Author:            Mohammad Mahdi Kabir
 * Author URI:        https://www.linkedin.com/in/mohammad-mahdi-kabir/
 * Text Domain:       select-quantity-by-attribute-for-woocommerce
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/2m.kabir/select-quantity-by-attribute-for-woocommerce
 * GitHub Branch:     master
 * WC requires at least: 5.0.0
 * WC tested up to: 7.7.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'select_quantity_by_attribute_for_woocommerce_Ya59' ) ) {
	class select_quantity_by_attribute_for_woocommerce_Ya59 {
		static $instance = false;

		private function __construct() {
            add_action( 'woocommerce_loaded', array($this, 'woocommerce_loaded') );
		}

		public static function getInstance() {
			if ( ! self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}

        public function woocommerce_loaded() {
            // Admin filters
            add_filter( 'product_attributes_type_selector',         array($this, 'add_attribute_type') );
            add_filter( 'pre_insert_term',                          array($this, 'validate_insert_term'), 10, 3 );

            // Admin actions
            add_action( 'admin_footer-edit-tags.php',               array($this, 'js_for_handle_quantity_quick_edit') );
            add_action( 'quick_edit_custom_box',                    array($this, 'form_field_quantity_quick_edit') );
            add_action( 'woocommerce_product_option_terms',         array($this, 'values_selector'), 10, 3 );

            // Front-end filters
            add_filter( 'woocommerce_quantity_input_args',          array($this, 'handle_quantity_input'), 10, 2 );
            add_filter( 'woocommerce_get_item_data',                array($this, 'remove_quantity_variation_from_cart') );
            add_filter( 'woocommerce_display_item_meta',            array($this, 'remove_quantity_variation_from_order'), 10, 3 );

            // Front-end actions
            add_action( 'woocommerce_before_calculate_totals',      array($this, 'set_quantity_in_cart') );

            // Dynamic Admin Hooks
            $attribute_taxonomies = wc_get_attribute_taxonomies();
            foreach ($attribute_taxonomies as $attribute_taxonomy) {
                if(isset($attribute_taxonomy->attribute_type) && $attribute_taxonomy->attribute_type === '_sqbafw_quantity') {
                    $attribute_taxonomy_name = wc_attribute_taxonomy_name($attribute_taxonomy->attribute_name);
                    add_action( "{$attribute_taxonomy_name}_add_form_fields",               array($this, 'display_add_quantity_form_field') );
                    add_action( "{$attribute_taxonomy_name}_edit_form_fields",              array($this, 'display_edit_quantity_form_field') );
                    add_action( "create_{$attribute_taxonomy_name}",                        array($this, 'on_create_attribute'), 10, 3 );
                    add_action( "edit_{$attribute_taxonomy_name}",                          array($this, 'on_edit_attribute'), 10, 3 );
                    add_action( "delete_{$attribute_taxonomy_name}",                        array($this, 'on_delete_attribute') );
                    add_filter( "manage_edit-{$attribute_taxonomy_name}_columns",           array($this, 'display_quantity_header_column') );
                    add_filter( "manage_edit-{$attribute_taxonomy_name}_sortable_columns",  array($this, 'display_quantity_header_column') );
                    add_filter( "manage_{$attribute_taxonomy_name}_custom_column",          array($this, 'display_quantity_value_column'), 10, 3 );
                }
            }

        }

        /**
         * Add "Quantity selector" attribute type
         * @param $types
         * @return mixed
         */
        public function add_attribute_type($types) {
            $types['_sqbafw_quantity'] = __( 'Quantity selector', 'select-quantity-by-attribute-for-woocommerce' );
            return $types;
        }

        /**
         * Validate quantity data before create
         * We need a filter for validation before edit but don't exist. Reported in https://core.trac.wordpress.org/ticket/58404
         * @param $term
         * @param $taxonomy
         * @param $args
         * @return mixed|WP_Error
         */
        public function validate_insert_term($term, $taxonomy, $args) {
            $attribute = wc_get_attribute( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
            if( isset($attribute) && $attribute->type === '_sqbafw_quantity'){
                $quantity = isset($args['_sqbafw_quantity']) ? absint($args['_sqbafw_quantity']) : 0;
                if( $quantity <= 0 ){
                    $error = new WP_Error();
                    $error->add('_sqbafw_empty_quantity', __('Insert quantity is required for this term.', 'select-quantity-by-attribute-for-woocommerce') );
                    return $error;
                }
            }
            return $term;
        }

        /**
         * Display quantity header column in the attribute admin page
         * @param $columns
         * @return array
         */
        public function display_quantity_header_column($columns) {
            $new_columns = array();
            foreach ($columns as $key => $value){
                $new_columns[$key] = $value;
                if($key === 'name'){
                    $new_columns['_sqbafw_quantity'] = __( 'Quantity', 'select-quantity-by-attribute-for-woocommerce' );
                }
            }
            return $new_columns;
        }

        /**
         * Display quantity value column in the attribute admin page
         * @param $value
         * @param $column_name
         * @param $term_id
         * @return int
         */
        public function display_quantity_value_column($value, $column_name, $term_id) {
            return absint(get_term_meta($term_id, $column_name, true));
        }

        /**
         * Display quantity form field in add term in the attribute admin page
         * @return void
         */
        public function display_add_quantity_form_field() {
            ?>
            <div class="form-field form-required term-quantity-wrap">
                <label for="tag-name">
                    <?php _e( 'Quantity', 'select-quantity-by-attribute-for-woocommerce' ); ?>
                </label>
                <input name="_sqbafw_quantity" id="_sqbafw_quantity" type="number" value="" step="1" min="1" size="40" aria-required="true" aria-describedby="_sqbafw_quantity-description" />
                <p id="name-description"><?php _e( "When a customer selects this variation for a product, this quantity will be added to the customer's shopping cart.", 'select-quantity-by-attribute-for-woocommerce' ); ?></p>
            </div>
            <?php
        }

        /**
         * Display quantity form field in edit term in the attribute admin page
         * @param $tag
         * @return void
         */
        public function display_edit_quantity_form_field($tag) {
            ?>
            <tr class="form-field form-required term-name-wrap">
                <th scope="row">
                    <label for="_sqbafw_quantity">
                        <?php _e( 'Quantity', 'select-quantity-by-attribute-for-woocommerce' ); ?>
                    </label>
                </th>
                <td>
                    <input name="_sqbafw_quantity" id="_sqbafw_quantity" type="number" value="<?php echo absint(get_term_meta($tag->term_id, '_sqbafw_quantity', true)); ?>" step="1" min="1" size="40" aria-required="true" aria-describedby="_sqbafw_quantity-description" />
                    <p class="description" id="_sqbafw_quantity-description"><?php _e( "When a customer selects this variation for a product, this quantity will be added to the customer's shopping cart.", 'select-quantity-by-attribute-for-woocommerce' ); ?></p>
                </td>
            </tr>
            <?php
        }

        /**
         * Add quantity term meta on create attribute
         * @param $term_id
         * @param $tt_id
         * @param $args
         * @return void
         */
        public function on_create_attribute($term_id, $tt_id, $args) {
            if( isset($args['_sqbafw_quantity']) && $args['_sqbafw_quantity'] > 0 ){
                add_term_meta($term_id, '_sqbafw_quantity', $args['_sqbafw_quantity'], true);
            }
        }

        /**
         * Update quantity term meta on edit attribute
         * @param $term_id
         * @param $tt_id
         * @param $args
         * @return void
         */
        public function on_edit_attribute($term_id, $tt_id, $args) {
            if( isset($args['_sqbafw_quantity']) && $args['_sqbafw_quantity'] > 0 ){
                update_term_meta($term_id, '_sqbafw_quantity', $args['_sqbafw_quantity']);
            }
        }

        /**
         * Delete quantity term meta on delete attribute
         * @param $term_id
         * @return void
         */
        public function on_delete_attribute($term_id){
            delete_term_meta($term_id, '_sqbafw_quantity');
        }

        /**
         * add a form field for quantity in quick edit
         * @param $column_name
         * @return void
         */
        public function form_field_quantity_quick_edit($column_name) {
            if($column_name === '_sqbafw_quantity') {
                ?>
                <fieldset style="padding-right: 12px;">
                    <div class="inline-edit-col">
                        <label style="margin: 0;">
                            <span class="title"><?php _e( 'Quantity', 'select-quantity-by-attribute-for-woocommerce' ); ?></span>
                            <span class="input-text-wrap">
                            <input type="number" name="<?php echo $column_name; ?>" class="ptitle" step="1" min="1" value="" style="width: 100%;" />
                        </span>
                        </label>
                    </div>
                </fieldset>
                <?php
            }
        }

        /**
         * add a js code to admin footer in edit-tags.php for handle quantity quick edit
         * @return void
         */
        public function js_for_handle_quantity_quick_edit(){
            ?>
            <script>
                ( function( $ ) {
                    'use strict';
                    $( document ).on( 'ready', function() {
                        $( '#the-list' ).on( 'click', '.editinline', function() {
                            var row = $(this).closest('tr');
                            var value = row.find('td._sqbafw_quantity').text();
                            $('tr.inline-edit-row').find('input[name="_sqbafw_quantity"]').val(value);
                        });
                    } );
                } ( jQuery ) );
            </script>
            <?php
        }

        /**
         * Show values selector for quantity attribute in the admin edit product page
         * @param $attribute_taxonomy
         * @param $i
         * @param $attribute
         * @return void
         */
        public function values_selector($attribute_taxonomy, $i, $attribute){
            if ( '_sqbafw_quantity' === $attribute_taxonomy->attribute_type ) {
                $attribute_orderby = ! empty( $attribute_taxonomy->attribute_orderby ) ? $attribute_taxonomy->attribute_orderby : 'name';
                ?>
                <select multiple="multiple"
                        data-minimum_input_length="0"
                        data-limit="50" data-return_id="id"
                        data-placeholder="<?php esc_attr_e( 'Select terms', 'woocommerce' ); ?>"
                        data-orderby="<?php echo esc_attr( $attribute_orderby ); ?>"
                        class="multiselect attribute_values wc-taxonomy-term-search"
                        name="attribute_values[<?php echo esc_attr( $i ); ?>][]"
                        data-taxonomy="<?php echo esc_attr( $attribute->get_taxonomy() ); ?>">
                    <?php
                    $selected_terms = $attribute->get_terms();
                    if ( $selected_terms ) {
                        foreach ( $selected_terms as $selected_term ) {
                            echo '<option value="' . esc_attr( $selected_term->term_id ) . '" selected="selected">' . esc_html( apply_filters( 'woocommerce_product_attribute_term_name', $selected_term->name, $selected_term ) ) . '</option>';
                        }
                    }
                    ?>
                </select>
                <button class="button plus select_all_attributes"><?php esc_html_e( 'Select all', 'woocommerce' ); ?></button>
                <button class="button minus select_no_attributes"><?php esc_html_e( 'Select none', 'woocommerce' ); ?></button>
                <?php
            }
        }


        /**
         * Set quantity in cart items that have quantity variation
         * @param $cart
         * @return void
         */
        public function set_quantity_in_cart($cart) {
            foreach ($cart->get_cart() as $cart_item){
                foreach ($cart_item['variation'] as $variation_key => $variation_value){
                    if($this->is_variation_quantity( $variation_key )){
                        $term_id = $this->get_term_id_of_variation($cart_item['product_id'], $variation_key, $variation_value);
                        $quantity = absint(get_term_meta($term_id, '_sqbafw_quantity', true));
                        $cart->set_quantity( $cart_item['key'], $quantity, false );
                    }

                }
            }
        }

        /**
         * Handle quantity input in product page and cart page
         * @param $args
         * @param $product
         * @return mixed
         */
        public function handle_quantity_input($args, $product) {
            foreach ($product->get_attributes('edit') as $attribute_key => $attribute_value) {
                if($this->is_variation_quantity( $attribute_key )){
                    if(is_page( 'cart' ) || is_cart()){
                        // readonly in cart page
                        $args['readonly'] = true;
                    } else {
                        // hidden in product page
                        $args['min_value'] = 1;
                        $args['max_value'] = 1;
                    }
                    break;
                }
            }
            return $args;
        }

        /**
         * Remove quantity variation from cart
         * @param $item_data
         * @return mixed
         */
        public function remove_quantity_variation_from_cart($item_data) {
            foreach ($item_data as $index => $variation){
                if($this->is_variation_quantity( $variation['key'] ) ) {
                    unset( $item_data[ $index ] );
                }
            }
            return $item_data;
        }

        /**
         * Remove quantity variation from order
         * This is rewrite of wc_display_item_meta function
         * @param $html
         * @param $item
         * @param $args
         * @return string
         */
        public function remove_quantity_variation_from_order($html, $item, $args){
            $strings = array();
            $html    = '';
            foreach ( $item->get_all_formatted_meta_data() as $meta_id => $meta ) {
                if( !$this->is_variation_quantity( $meta->key ) ) {
                    $value = $args['autop'] ? wp_kses_post($meta->display_value) : wp_kses_post(make_clickable(trim($meta->display_value)));
                    $strings[] = $args['label_before'] . wp_kses_post($meta->display_key) . $args['label_after'] . $value;
                }
            }
            if ( $strings ) {
                $html = $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
            }
            return $html;
        }

        /**
         * Get term id of variation for call get_term_meta function
         * @param $product_id
         * @param $variation_key
         * @param $variation_value
         * @return int
         */
        private function get_term_id_of_variation($product_id, $variation_key, $variation_value){
            $product = wc_get_product($product_id);
            foreach ($product->get_attributes('edit') as $key => $attribute){
                if(str_replace('attribute_', '', $variation_key) === $key){
                    foreach ($attribute->get_terms() as $term){
                        if($term->slug === $variation_value){
                            return $term->term_id;
                        }
                    }
                }
            }
            return 0;
        }

        /**
         * Check type of variation key is quantity
         * @param $variation_key string|null prefix is attribute_pa_* or pa_* or non-prefix
         * @return bool
         */
        private function is_variation_quantity( $variation_key = null ) {
            if(empty($variation_key)){
                return false;
            }
            $attribute_taxonomies = wc_get_attribute_taxonomies();
            foreach ($attribute_taxonomies as $attribute) {
                if( strpos($variation_key, 'attribute_pa_') === 0 ){
                    if($variation_key === 'attribute_pa_' . $attribute->attribute_name) {
                        return $attribute->attribute_type === '_sqbafw_quantity';
                    }
                } elseif ( strpos($variation_key, 'pa_') === 0 ) {
                    if($variation_key === 'pa_' . $attribute->attribute_name) {
                        return $attribute->attribute_type === '_sqbafw_quantity';
                    }
                } else {
                    if($variation_key === $attribute->attribute_name || $variation_key === $attribute->attribute_label) {
                        return $attribute->attribute_type === '_sqbafw_quantity';
                    }
                }
            }
            return false;
        }
	}

	$select_quantity_by_attribute_for_woocommerce = select_quantity_by_attribute_for_woocommerce_Ya59::getInstance();
}

