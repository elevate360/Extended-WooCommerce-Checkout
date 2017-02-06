<?php
/*
Plugin Name: Elevate Extended WooCommerce Checkout
Plugin URI: https://elevate360.com.au
Description: Adjusts the checkout process to add additional custom fields to the user details section. Requested by Auction Experts. 
Version: 1.0.0
Author: Simon Codrington
Author URI: http://elevate360.com.au
Text Domain: elevate-extended-woocommerce-checkout
Domain Path: /languages
*/

class el_woocommerce_extended{
	
	//instance object
	private static $instance = null;
	
	//magic constructor
	public function __construct(){
		
		//add_action('woocommerce_email_header', array($this, 'email_header'), 10, 2);
		
		add_filter('woocommerce_get_settings_products', array($this, 'add_new_woocommerce_product_settings')); //registers a new setting in the admin menu on the 'products' page
		add_filter('woocommerce_get_settings_checkout', array($this, 'add_new_woocommerce_checkout_settings')); //registeres new settings on the 'checkout' settings page
		add_action('woocommerce_add_to_cart_redirect', array($this, 'redirect_after_add_to_cart')); //redirects straight to cart on product addition
		add_action('woocommerce_checkout_fields', array($this, 'add_new_checkout_fields'), 10, 1); //register new checkout fields to be used
		add_action('woocommerce_thankyou', array($this, 'complete_virtual_pending_orders'), 10, 1);  //auto-complete pending orders
		add_action('woocommerce_thankyou', array($this, 'redirect_after_successful_purchase'), 15, 1); //potentially redirects to a dedicated thankyou page
		add_action('init', array($this, 'remove_login_form_on_checkout')); //removes the login form on checkout page
		add_action('woocommerce_checkout_update_order_meta', array($this, 'save_custom_checkout_fields'), 10, 1); //saves our custom checkout fields to the order
		add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_custom_fields_on_order_overview'), 10, 1); //displays custom fields on the order overview panel
		add_action('woocommerce_email_customer_details_fields', array($this, 'display_custom_fields_on_admin_email'), 10, 3); //displays custom fields on the admin email thats sent out
		
		add_action('woocommerce_add_to_cart_validation', array($this, 'clear_cart_before_adding_product'), 10, 3); //clears the cart before we add items to it (ensures only one item)
		add_action('woocommerce_enable_order_notes_field', array($this, 'remove_additional_information_title_checkout')); //remove the 'Additional Information' H2 title used on the checkout
		
		add_action('woocommerce_after_order_notes', array($this, 'display_new_checkout_fields')); //displays the new checkout fields 
		add_action('woocommerce_checkout_process', array($this, 'checkout_on_submit')); //processing on form submit for validation
		add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts_and_styles')); //enqueues front facing scripts/styles
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts_and_styles')); //admin only scripts and styles
		
		//hook here to override plugin 
		do_action('el-brick-donation-construct');
	}


	//displays custom fields on the email that is sent to the site admin
	public function display_custom_fields_on_admin_email( $fields,  $sent_to_admin,  $order ){
			
		$html = '';
		
		//get meta from order
		$auction_date = get_post_meta($order->id, 'el-auction-date', true);
		$auction_type = get_post_meta($order->id, 'el-auction-type', true);
		
		$fields[] = array(
			'label'		=> 'Auction Type',
			'value'		=> $auction_type
		);
		$fields[] = array(
			'label'		=> 'Auction Date',
			'value'		=> $auction_date
		);
		
		return $fields;
		
	}

	//if we have virtual orders, immediately make them complete
	public function complete_virtual_pending_orders($order_id){
		
	
		$order = wc_get_order($order_id);
		$order_status = $order->get_status();
		
		//if our order is processing
		if($order_status == 'processing'){
			$has_virtual = false;
			
			//determine if any items in the cart are virtual
			$items = $order->get_items();
			foreach($items as $item){
	
				$product = wc_get_product($item['product_id']); 
				if($product->is_virtual()){
					$has_virtual = true;
				}
					
			}
			//if we have virtual products, immediately complete the order
			if($has_virtual){
				$order->update_status('completed');
			}
		}
	
	}

	//remove login ability on checkout, it's not needed
	public function remove_login_form_on_checkout(){
		remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
	}


	//after successfully purchasing, redirect the user to the defined thank you page (if set in settings)
	public function redirect_after_successful_purchase($order_id ){
		
		//execute on successful status
		$order = new WC_Order( $order_id );
	    if ( $order->status != 'failed' ) {
	    	
			//determine based on settings if we want to redirect (and if we have a URL set)
			$checkout_page_redirect = get_option('el-checkout-redirect');
			$checkout_page_url = get_option('el-checkout-page');
			
			if($checkout_page_redirect == 'yes' && !empty($checkout_page_url)){
				$url = $checkout_page_url;
				//TODO: unsure how this works when the hook is after content
				wp_redirect($url);
			}
	        
	    }
		
		return $url;
	}
	
	//add new options to the 'Checkout' page for woocommerce
	public function add_new_woocommerce_checkout_settings( $settings){
		

			
		//TODO: Figure out how to make these conditional (like how WC does it)
		$new_settings[] = array(
			'title'		=> 'Redirect After Checkout',
			'desc_top'	=> 'asdasadsa',
			'id'		=> 'el-checkout-redirect',
			'type'		=> 'checkbox',
			'default'	=> 'no',
			'desc'		=> 'Enable Checkout Redirect',
			'desc_tip'	=> __('After successfully purchasing, redirect the user back to a specifed thank you page (bypassing the thank you template)', 'elevate-extended-woocommerce-checkout')
		);
		
		$default_url = get_home_url();
		$new_settings['el-checkout-page'] = array(
			'title'		=> 'Redirection Page',
			'desc_top'	=> '',
			'id'		=> 'el-checkout-page',
			'type'		=> 'text',
			'default'	=> $default_url,
			'desc'		=> __('Choose the page you want to redirect to after successfully purchasing', 'elevate-extended-woocommerce-checkout'),
			'desc_tip'	=> 'Select where you want to be directed',
			'class'		=> 'long-field'
		);
		
		$counter = 0;
		$position = '';
		$search = 'woocommerce_force_ssl_checkout';
		foreach($settings as $setting){
			if($setting['id'] == 'woocommerce_force_ssl_checkout'){
				$position = $counter+1;
			}
			$counter++;
		}
		
		//insert new options into correct place
		array_splice($settings, $position, 0, $new_settings);
	
		
		return $settings;
	}
	
	//remove the 'Additional Information' title used on the checkout, this title isnt needed
	public function remove_additional_information_title_checkout(){
		return false;
	}
	
	public function remove_additional_info_tab($tabs){
		unset($tabs['additional_information']);
		return $tabs;
	}
	
	
	//enqueue front facing elements
	public function enqueue_public_scripts_and_styles(){
		$directory = plugin_dir_url(__FILE__);
		
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_style('jquery-ui-styles', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
		wp_enqueue_script('elevate-extended-woocommerce-checkout-public-script', $directory . '/js/elevate-public-scripts.js', array('jquery','jquery-ui-datepicker'));
		wp_enqueue_style('elevate-extended-woocommerce-checkout-public-styles', $directory . '/css/elevate-public-styles.css');
	}
	
	public function enqueue_admin_scripts_and_styles(){
		$directory = plugin_dir_url(__FILE__);	
		wp_enqueue_script('elevate-extended-woocommerce-checkout-admin-script', $directory . '/js/elevate-admin-scripts.js', array('jquery'));
	}
	
	//add new checkout fields 
	public function add_new_checkout_fields($fields){
		
		$new_fields = array();
		$new_fields['additional'] = array(); 

		//Auction type select element
		$new_fields['additional']['el-auction-type'] = array(
			'label'		=> __('Auction Type', 'elevate-extended-woocommerce-checkout'),
			'type'		=> 'select',
			'required'	=> true,
			'options'	=> array(
				'residential'	=> 'Residential',
				'commercial'	=> 'Commercial',
				'retail'		=> 'Retail',
				'specialist'	=> 'Specialist'	
			)
		);
		
		//Auction date (jQuery datepicker, fallsbacks to text input)
		$new_fields['additional']['el-auction-date'] = array(
			'label'			=> __('Auction Date', 'elevate-extended-woocommerce-checkout'),
			'type'			=> 'text',
			'required'		=> true,
			'input_class'	=> array('elevate-datepicker')
		);
			
		
		$fields = array_merge($fields, $new_fields );
		
		return $fields;
		
	}
	
	//displays our new checkout fields (displayed after billing and shipping)
	public function display_new_checkout_fields($checkout){
		
		echo '<div class="additional-fields">';
		echo '<h3>' . __('Auction Information', 'elevate-extended-woocommerce-checkout') . '</h3>';
		echo '<p>' . __('Please complete the following fields', 'elevate-extended-woocommerce-checkout') . '</p>';
		
		//get all fields
		$additional_info = $checkout->checkout_fields['additional'];
		
		//loop through all fields and output them
		foreach($additional_info as $key => $field){
			woocommerce_form_field($key, $field);
		}
		
		echo '</div>';
		
	}
	
	//saves new custom fields to the order
	public function save_custom_checkout_fields($order_id){
		
		//update auction type
		if(isset($_POST['el-auction-type'])){
			if(!empty($_POST['el-auction-type'])){
				update_post_meta($order_id, 'el-auction-type', sanitize_text_field($_POST['el-auction-type']));
			}
		}
		
		//update auction date
		if(isset($_POST['el-auction-date'])){
			if(!empty($_POST['el-auction-date'])){
				update_post_meta($order_id, 'el-auction-date', sanitize_text_field($_POST['el-auction-date']));
			}
		}
		
	}

	//displays new metafields on the order overview panel
	function display_custom_fields_on_order_overview($order){

		$auction_date = get_post_meta($order->id, 'el-auction-date', true);
		$auction_type = get_post_meta($order->id, 'el-auction-type', true);
		
		if(!empty($auction_date)){
			echo '<p><strong>' .  __('Auction Date', 'elevate-extended-woocommerce-checkout') . ':</strong> ' . $auction_date . '</p>';
		}
		if(!empty($auction_type)){
			echo '<p><strong>' .  __('Auction Type', 'elevate-extended-woocommerce-checkout') . ':</strong> ' . $auction_type . '</p>';
		}
		
	}
	
	//add our new settings to the WooCommerce settings menu
	public function add_new_woocommerce_product_settings($settings){

		//trigger only on the 'display' settings page
		if(isset($_GET['section']) && $_GET['section'] == 'display'){
				
			$new_settings[] = array(
				'name'		=> '',
				'desc_top'	=> '',
				'id'		=> 'el-bypassing-basket',
				'type'		=> 'checkbox',
				'desc'		=> __('When adding a product this will bypass the basket screen and take your users straight to the checkout', 'elevate-extended-woocommerce-checkout'),
				'checkboxgroup'	=> 'start'
			);
			
			//insert our new setting in the correct position on this page
			$counter = 0;
			$position = '';
			foreach($settings as $setting){
				if($setting['id'] == 'woocommerce_cart_redirect_after_add'){
					$position = $counter +1;
				}
				$counter++;
			}
			
			//insert our new setting in the correct place
			array_splice($settings, $position, 0, $new_settings);

		}

		//return settings
		return $settings;
	}
	
	//check to ensure we have our custom checkboxes selected to continue
	public function checkout_on_submit(){
		
		if(!isset($_POST['el-auction-date'])){
			wc_add_notice(__('You must indicate an auction date to proceed', 'elevate-extended-woocommerce-checkout'), 'error');
		}
		if(!isset($_POST['el-auction-type'])){
			wc_add_notice(__('You must select the type of property to proceed', 'elevate-extended-woocommerce-checkout'), 'error');
		}
		
	}
	
	//for our situation we want only one item in the checkout at a time and we don't want to interact with the 'basket'.
	//on successful product add, remove all other elements in the cart
	public function clear_cart_before_adding_product($passed, $product_id, $quantity){
		
		$woocommerce = Woocommerce::instance();
		if(isset($woocommerce->cart)){
			$woocommerce->cart->empty_cart();
		}
		
		//return true, good to add to cart
		return $passed;
	}

	//when a user adds a product to the cart, redirect directly to the cart (bypadding basket)
	public function redirect_after_add_to_cart($wc){
	
		//redirect only if our setting is enabled (settings > display)
		$bypass_basket = get_option('el-bypassing-basket');
		$woocommerce = Woocommerce::instance();
	
		if($bypass_basket == 'yes'){
			//clear notices (so we don't see "successfully added to basket" on checkout)
			$cart_url = $woocommerce->cart->get_checkout_url();
			wc_clear_notices();
			
			return $cart_url;
		}else{
			return;
		}
		
	}
	
	//returns the singleton of this class
	public static function getInstance(){
		if(is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}
	
}
$el_woocommerce_extended = el_woocommerce_extended::getInstance();



?>