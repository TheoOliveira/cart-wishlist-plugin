<?php
/*
Plugin Name: Woocommerce wishlist On Cart page
Plugin URI: https://www.theooliveira.netlify.app
Description: Ajax wishlist for WooCommerce added on cart page
Author: Theo
Version: 1.1
Author URI: http://theooliveira.netlify.app
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('init', 'plugin_init');
function plugin_init()
{
    if (class_exists("Woocommerce")) {
        function wishlist_plugin_scripts_styles()
        {
            wp_enqueue_style('cart-wishlist-style', plugins_url('/css/style.css', __FILE__), null, '2.1', 'all');
            wp_enqueue_script('cart-wishlist-main', plugins_url('/js/main.js', __FILE__), array('jquery'), '1.6', true);
            wp_localize_script(
                'main',
                'opt',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'ajaxPost' => admin_url('admin-post.php'),
                    'restUrl' => rest_url('wp/v2/product'),
                    'shopName' => sanitize_title_with_dashes(sanitize_title_with_dashes(get_bloginfo('name'))),
                    'inWishlist' => esc_html__("Already in Save for later list", "text-domain"),
                    'removeWishlist' => esc_html__("Remove from Save for later list", "text-domain"),
                    'buttonText' => esc_html__("Details", "text-domain"),
                    'error' => esc_html__("Something went wrong, could not add to Save for later list", "text-domain"),
                    'noWishlist' => esc_html__("No items found on the save for later list found", "text-domain"),
                )
            );
        }

        // Wishlist table shortcode
        add_shortcode('wishlist', 'wishlist');
        function wishlist($atts, $content = null)
        {

            extract(shortcode_atts(array(), $atts));

            return '<div class="save-later-list loading">
                <div class="save-later-item">
                <div class="name-price">
                    <h2 class="title">' . esc_html__("Name", "text-domain") . '</h2>
                    <span class="from-wishlist">from</span>
                    <h3>' . esc_html__("Price", "text-domain") . '</h3>
                    </div>
                    <div>' . esc_html__("Short Description", "text-domain") . '</div>
                    <div><a href="#"><img width="50" src="https://labme.us.mywoocart.com/wp-content/uploads/2020/11/cart-simple-add.svg" alt=""></a></div>
                </div>
            </div>';
        }
        add_action('wp_enqueue_scripts', 'wishlist_plugin_scripts_styles');

        // Wishlist option in the user profile
        add_action('show_user_profile', 'wishlist_user_profile_field');
        add_action('edit_user_profile', 'wishlist_user_profile_field');
        function wishlist_user_profile_field($user)
        { ?>
            <table class="form-table wishlist-data">
                <tr>
                    <th><?php echo esc_attr__("Save for later Tests", "text-domain"); ?></th>
                    <td>
                        <input type="text" name="wishlist" id="wishlist" value="<?php echo esc_attr(get_the_author_meta('wishlist', $user->ID)); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
<?php }

        add_action('personal_options_update', 'save_wishlist_user_profile_field');
        add_action('edit_user_profile_update', 'save_wishlist_user_profile_field');
        function save_wishlist_user_profile_field($user_id)
        {
            if (!current_user_can('edit_user', $user_id)) {
                return false;
            }
            update_user_meta($user_id, 'wishlist', $_POST['wishlist']);
        }


        // Get current user data
        function fetch_user_data()
        {
            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
                $current_user_wishlist = get_user_meta($current_user->ID, 'wishlist', true);
                echo json_encode(array('user_id' => $current_user->ID, 'wishlist' => $current_user_wishlist));
            }
            die();
        }
        add_action('wp_ajax_fetch_user_data', 'fetch_user_data');
        add_action('wp_ajax_nopriv_fetch_user_data', 'fetch_user_data');

        function update_wishlist_ajax()
        {
            if (isset($_POST["user_id"]) && !empty($_POST["user_id"])) {
                $user_id   = $_POST["user_id"];
                $user_obj = get_user_by('id', $user_id);
                if (!is_wp_error($user_obj) && is_object($user_obj)) {
                    update_user_meta($user_id, 'wishlist', $_POST["wishlist"]);
                }
            }
            die();
        }
        add_action('admin_post_nopriv_user_wishlist_update', 'update_wishlist_ajax');
        add_action('admin_post_user_wishlist_update', 'update_wishlist_ajax');


        // Extend REST API
        function rest_register_fields()
        {

            register_rest_field(
                'product',
                'price',
                array(
                    'get_callback'    => 'rest_price',
                    'update_callback' => null,
                    'schema'          => null
                )
            );

            register_rest_field(
                'product',
                'stock',
                array(
                    'get_callback'    => 'rest_stock',
                    'update_callback' => null,
                    'schema'          => null
                )
            );

            register_rest_field(
                'product',
                'image',
                array(
                    'get_callback'    => 'rest_img',
                    'update_callback' => null,
                    'schema'          => null
                )
            );
        }
        add_action('rest_api_init', 'rest_register_fields');

        function rest_price($object, $field_name, $request)
        {

            global $product;

            $id = $product->get_id();

            if ($id == $object['id']) {
                return $product->get_price();
            }
        }

        function rest_stock($object, $field_name, $request)
        {

            global $product;

            $id = $product->get_id();

            if ($id == $object['id']) {
                return $product->get_stock_status();
            }
        }

        function rest_img($object, $field_name, $request)
        {

            global $product;

            $id = $product->get_id();

            if ($id == $object['id']) {
                return $product->get_image();
            }
        }

        function maximum_api_filter($query_params)
        {
            $query_params['per_page']["maximum"] = 100;
            return $query_params;
        }
        add_filter('rest_product_collection_params', 'maximum_api_filter');
    }
}
