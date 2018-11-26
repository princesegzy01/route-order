<?php
/**
 * Plugin Name: Route Order
 * Description: Route a drugstoc order to designated distributor.
 * Version: 1.0.0
 * Author: Sodimu Segun & Caleb Chinga 
 * Text Domain: cpac
 * Domain Path: /languages
 * License: GPL2
 */

/*  Copyright 2014  PLUGIN_AUTHOR_NAME  (email : info@drugstoc.biz)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
defined('ABSPATH') or die("Oops you cannot access this script");  

// Create ds_routed_items_table
register_activation_hook( __FILE__, 'ds_routed_install' );
 
// Hook for adding admin menus 
add_action( 'admin_head', 'route_order_scripts' );
add_action( 'admin_menu', 'register_route_order'); 
 
// Enqueue all scripts/styles needed
function route_order_scripts() {
  wp_enqueue_style( 'ds-datatable-css', "//cdn.datatables.net/1.10.4/css/jquery.dataTables.min.css"); 
  wp_enqueue_script('jquery');
  wp_enqueue_script('ds-datatable-js', "//cdnjs.cloudflare.com/ajax/libs/datatables/1.10.3/js/jquery.dataTables.min.js",  array('jquery' ));
  
  if($_GET['page'] == 'routeorderitems')
	wp_enqueue_script('ds-pubnub-js', "//cdn.pubnub.com/pubnub-3.7.1.min.js");
  
  wp_enqueue_script('ds-route-js', plugins_url("/route-order/js/route-order.js"),  array('jquery' ), '1.0.0', true); 
}  

 
global $ds_routed_db_version;
$ds_routed_db_version = '1.0'; 

function ds_routed_install() {
	global $wpdb;
	global $ds_routed_db_version;

	// do_action('admin_notices_custom');

	$table_name = $wpdb->prefix . 'routed_order_items';
	
	/*
	 * We'll set the default character set and collation for this table.
	 * If we don't do this, some characters could end up being converted 
	 * to just ?'s when saved in our table.
	 */
	$charset_collate = '';

	if ( ! empty( $wpdb->charset ) ) {
	  $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
	}

	if ( ! empty( $wpdb->collate ) ) {
	  $charset_collate .= " COLLATE {$wpdb->collate}";
	}

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id int PRIMARY KEY AUTO_INCREMENT,
		distributor varchar(100) NOT NULL,
		order_id int NOT NULL,
		item_id int NOT NULL,
		item_qty int NOT NULL,
		line_total float NOT NULL,
		routed int NOT NULL,
		in_stock int NOT NULL DEFAULT '1',	
		notes varchar(200) DEFAULT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
	)$charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'ds_routed_db_version', $ds_routed_db_version ); 
}

// Create Route Order Admin Menu 
function register_route_order(){

	// Route Orders
    add_menu_page( 
    	'Route Orders', 
    	'Route Orders', 
    	'manage_options', 
    	'routeorder', 
    	'route_order_content', 
    	'dashicons-editor-distractionfree', 
    	7); 

    add_submenu_page(
		'routeorder',
		'Order Details',
		'Route Order Items',
		'manage_options',
		'routeorderitems',
		'order_details'
	);   
}

// Order List
function route_order_content(){
	global $wpdb;   

	$args = array(
	  'post_type'   => 'shop_order',
	  'post_status' => 'publish',
	  'meta_key' 	=> '_customer_user',
	  'posts_per_page' => '-1',
	  'orderby'=> 'date',
	  'order' => 'desc'
	);

	$my_query = new WP_Query($args);

	$customer_orders = $my_query->posts; // Display all customer orders

	$html = '<h3>Route Orders</h3>
		<i><h4>Select an order and route items to redistributors</h4></i><br/>
		<table class="wp-list-table widefat fixed posts" id="ordertable">
		<thead>
			<tr>
				<th scope="col" id="cb" class="manage-column column-cb check-column" style="">
					<label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox"></th>
				<th scope="col" id="order_status" class="manage-column column-order_status" style=""><span class="status_head tips">Status</span></th>
				<th scope="col" id="order_title" class="manage-column column-order_title" style=""><span>Order</span><span class="sorting-indicator"></span></th>
				<th scope="col" id="order_items" class="manage-column column-order_items" style="">Purchased</th>
				<th scope="col" id="shipping_address" class="manage-column column-shipping_address" style="">Primary Distributor</th>
				<th scope="col" id="customer_message" class="manage-column column-customer_message" style=""><span class="notes_head tips">Customer Message</span></th>
				<th scope="col" id="order_notes" class="manage-column column-order_notes" style=""><span class="order-notes_head tips">Order Notes</span></th>
				<th scope="col" id="order_date" class="manage-column column-order_date sortable desc" style=""><span>Date</span><span class="sorting-indicator"></span></th>
				<th scope="col" id="order_total" class="manage-column column-order_total sortable desc" style=""><span>Total</span><span class="sorting-indicator"></span></th>
				<th></th>
			</tr>
		</thead>
		<tbody>';

	foreach ($customer_orders as $customer_order) {
		$order = new WC_Order();

		$order->populate($customer_order);
		$orderdata = (array) $order; 

		// User
		$user = get_user_by( 'id', $order->customer_user );
		$user_primary_distributor = get_user_meta($user->ID,'primary_distributor',true);
		// echo $user_primary_distributor.'>>>';
		// var_dump($order);
		$html .= '<tr>
					<td><input type="checkbox" name="post[]" value="'.esc_html( $order->get_order_number() ).'"></td>
					<td>'.$order->status.'</td>
					<td><a href="'.menu_page_url('routeorderitems',false).'&order='.$order->id.'"><b>'.esc_html( $order->get_order_number() ).'<b/> by '.esc_html( $user->display_name ).'</a></td>
					<td>'.$order->get_item_count().'</td>
					<td>'.$user_primary_distributor	.'</td>
					<td>'.(isset($order->customer_message)? $order->customer_message:'None').'</td>
					<td>'.(isset($order->customer_note)? $order->customer_note:'None').'</td>
					<td>'.date("d M Y H:m", strtotime($order->order_date)).'</td>
					<td>'.$order->get_formatted_order_total().'</td> 
					<td><a title="View PDF Invoice" alt="View PDF Invoice" target="_blank" href='.wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&template_type=invoice&order_ids=' . $order->id ), 'generate_wpo_wcpdf' ).
						'<img src="'.plugins_url('/route-order/img/invoice.png').'" alt="View PDF Invoice" width="16px" />
					</a></td>
				</tr>';
	}

	$html .= '</tbody>
		<tfoot> 
			<tr>
				<th scope="col" id="cb" class="manage-column column-cb check-column" style="">
					<label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox"></th>
				<th scope="col" id="order_status" class="manage-column column-order_status" style=""><span class="status_head tips">Status</span></th>
				<th scope="col" id="order_title" class="manage-column column-order_title sortable desc" style=""><span>Order</span><span class="sorting-indicator"></span></th>
				<th scope="col" id="order_items" class="manage-column column-order_items" style="">Purchased</th>
				<th scope="col" id="shipping_address" class="manage-column column-shipping_address" style="">Primary Distributor</th>
				<th scope="col" id="customer_message" class="manage-column column-customer_message" style=""><span class="notes_head tips">Customer Message</span></th>
				<th scope="col" id="order_notes" class="manage-column column-order_notes" style=""><span class="order-notes_head tips">Order Notes</span></th>
				<th scope="col" id="order_date" class="manage-column column-order_date sortable desc" style=""><span>Date</span><span class="sorting-indicator"></span></th>
				<th scope="col" id="order_total" class="manage-column column-order_total sortable desc" style=""><span>Total</span><span class="sorting-indicator"></span></th>
				<th></th>
			</tr> 
		</tfoot>
	</table>'; 

	echo $html; 
}

// Check if an Item is routed to a distributor
function isRouted($orderid, $itemid){
	global $wpdb;

	$item = $wpdb->get_row("SELECT distributor, notes, in_stock FROM {$wpdb->prefix}routed_order_items
		WHERE item_id = $itemid and order_id = $orderid ORDER BY created_at DESC LIMIT 1");

	return isset($item->distributor)? array($item->distributor, $item->notes, $item->in_stock): array("None","None",0);//$item->distributor":"None";
}

// Change Number to Money format
function show_price($value){
	return number_format((float)$value, 2);
} 

// Order Data + Item list(varname)
function order_details($id)
{ 
	if(isset($_GET['order']) && $_GET['order'] > 0){ 
		$orderid = $_GET['order'];  

		global $wpdb, $thepostid, $theorder, $woocommerce;

		$order = new WC_Order($orderid);
		$distributors = new WP_Query('post_type=redistributor');
		$redistributors = '';

		if($distributors->have_posts()){
			foreach ($distributors->posts as $key => $value) {
			  	$name = get_post_meta($value->ID, 'wpcf-contact-name', true);
				$phonenumber = get_post_meta($value->ID, 'wpcf-phone-number', true);
				$email = get_post_meta($value->ID, 'wpcf-email', true);   
				$redistributors .='<option value="'.$name.'" data-email="'.$email.'" data-phonenumber="'.$phonenumber.'">'.$name.'</option>';
			}
		}
		$user = get_user_by( 'id', $order->customer_user );
		$user_primary_distributor = get_user_meta($user->ID,'primary_distributor',true);
		?>
			<div id="order_data" class="panel">
				<h2>Order Details</h2>
				<p class="order_number">Order number #<?php echo $order->id;?></p>

				<div class="order_data_column_container">
					<div class="order_data_column">
						<table>  
							<tr>
								<td align="right" valign="middle" class="form-field"><h4>General Details&nbsp;&nbsp;</h4></td>
								<td>
									<p class="form-field"><label for="order_date">Order date:</label>
										<span><?php echo date('d M Y h:m:s A', strtotime($order->order_date));?></span> 
									</p>
									<p class="form-field form-field-wide"><label>Order status:</label><span><?php echo $order->status;?></span></p>
									<p class="form-field form-field-wide">
										<label for="customer_user">Customer: </label>
										<?php echo esc_html( $user->display_name );?> 
									</p>  
									<p class="form-field form-field-wide"><label>Customer Phone Number: </label><span><?php echo get_user_meta($user->ID,'phonenumber',true);?></span></p>
									<p class="form-field form-field-wide"><label>Primary Distributor: </label><span><?php echo get_user_meta($user->ID,'primary_distributor',true);?></span></p>
									<p class="form-field form-field-wide"><label>Order Total: </label><h3><?php echo $order->get_formatted_order_total() ;?></h3></p>
									<p class="form-field form-field-wide"><label><a href="<?php echo menu_page_url('ds-commission-item',false).'&order='.$order->id;?>">View Commission per line item</a></label></p>
								</td>	
							</tr> 
							<tr>
								<td align="right" valign="top" class="form-field"><h4>Shipping Address&nbsp;&nbsp;</h4></td>
								<td><?php echo $order->get_formatted_shipping_address();?></td>
							</tr>
						</table> 
						<br/><br/>
					</div> 
				</div>
				<form>
					<p style="float: left; clear: right">
						<select id="myExternalSelect">
							<option value="">- Select a distributor -</option>'.
							<?php echo $redistributors;?>
						</select>
						<button id="route1" class="button">Route Selected Item(s)</button>
					</p>
				</form>
				<div class="clear"></div> 
			</div>
			<?php $order_items = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', array( 'line_item', 'fee' ) ) );  ?> 
			<div id="woocommerce-order-items" class="postbox " >
				<div class="handlediv" title="Click to toggle"><br /></div><h3 class="hndle"><span id="orderitems">&nbsp;&nbsp;Order Items</span></h3>
				<div class="inside">
					<table class="wp-list-table widefat fixed posts" id="ordertable">
						<thead>
							<tr>
								<th><input type="checkbox" class="select_all" name="selectall" /></th>
								<th>Item</th>
								<th><b>Routed to</b></th> 
								<th>Price</th>
								<th>Distributor Price(s)</th>	
								<th>Quantity</th>
								<th>Total</th> 
								<th>Distributor Notes</th>
								<th>In stock</th>
							</tr>
						</thead>
						<tbody>
				<?php   
				foreach ($order_items as $key => $item) {
					$product = $order->get_product_from_item( $item );

					$meta = new WC_Product( $product );  
					$route = isRouted($order->id, $item['product_id']); 
					$distr = strtoupper ( substr( get_user_meta( $route[0], 'primary_distributor', true ), 0, -6) );

					// product id of order items returned are orphaned 
		 			?><tr class="item" data-item-id="<?php echo $item['product_id']; ?>">
			 			<td><input type="checkbox" class="case" name="case[]" /></td>
						<td class="name"><a href="<?php echo admin_url('post.php?post='.$item['product_id'].'&action=edit');?>"><?php echo $item['name'];?></a></td>
						<td class="dist"><?php echo $distr;?></td>
						<td>&#8358;<?php echo get_post_meta($item['product_id'],'_price',true);?></td>
						<td>
							<div>
								NHC | &#8358;<?php echo show_price(get_post_meta($item['product_id'],'nhc_price',true)); ?>
								<br/>Elfimo | &#8358;<?php echo show_price(get_post_meta($item['product_id'],'elfimo_price',true));?>
								<br/>DS_X | &#8358;<?php echo show_price(get_post_meta($item['product_id'],'dsx_price',true));?>
							</div>
						</td>
						<td class="quantity"><?php echo $item['qty'];?></td>
						<td class="line_cost">&#8358;<?php echo $item['line_total'];?></td>
						<td class="notes"><textarea class="notes" rows="2" cols="15" placeholder="Notes" readonly ><?php echo $route[1];?></textarea></td>
						<td>
				 			<?php if($route[2] == 1) {?>
								<p>Yes</p>
							<?php }else{ ?>
								<p>No</p>
							<?php }?>
			 			</td>
					</tr>
				<?php   
				}		  

		?> </tbody>
			</table> 
 	  	</div>
 	  	<form>
			<p style="float: left; clear: right">
				<select id="myExternalSelect">
					<option value="">- Select a distributor -</option>'.
					<?php echo $redistributors;?>
				</select>
				<button id="route2" class="button">Route Selected Item(s)</button>
			</p>
			</form>
 	  	</div><?php

		// echo $html;
	}else{
		echo '<h3>Please select an Order to route!</h3>';
	}?>
	<script type="text/javascript"> 
	(function ($) {    
		var globalVar = [];  
	 
		jQuery("#route1, #route2").on('click', function(e){
			e.preventDefault();

			var distributorName = jQuery.trim(jQuery("#myExternalSelect").val());

			if(distributorName == ""){
				alert('You must select a distributor to route this order to');
			 	return false;
			}
		 
			var ourTr = jQuery('tr.item .case:checked');

		    jQuery(ourTr).each(function(i, jqO) { 
		       	var data = jQuery.trim(jQuery("#customer_user option:selected").text());
		       	var data_ = data.split(" ");
		       	var customer = data.split(" ").length > 0 ?  data_[0]+" ("+data_[data_.length-1].replace(/\)/g,'')+")" : data;

				var innerJson = {
					"orderid":  "<?php echo $order->id;?>",
					"customer": "<?php echo esc_html( $user->display_name);?>",
					"itemid": jQuery.trim(jQuery(jqO).parents('tr.item').data("itemId")),
					"name": jQuery.trim(jQuery(jqO).parents('tr.item').find('.name').text()),
					"qty": jQuery.trim(jQuery(jqO).parents('tr.item').find('.quantity').text()),
					"amount": jQuery.trim(jQuery(jqO).parents('tr.item').find('.line_cost').text()),
					"distributors": jQuery.trim(jQuery("#myExternalSelect").val()),
					"phonenumber": jQuery.trim(jQuery("#myExternalSelect option:selected").data("phonenumber")),
					"email": jQuery.trim(jQuery("#myExternalSelect option:selected").data("email"))
				};
				globalVar.push(innerJson);  
		   		jQuery(jqO).parents('tr.item').find('.dist').html(jQuery("#myExternalSelect").val());
		   	});
		  
			// console.log("My Global Json Variable is : ");
			// console.log(globalVar); 

			if(globalVar.length == 0){ 
				alert('You must select at least one product to route this order');
			 	return false;
			}

			// var data = JSON.stringify(globalVar);  
			var data = globalVar;
			// console.log(data);

			$.ajax({
			    type: 'POST',
			    url: '<?php echo plugins_url("route-order/processRoute.php",false);?>',
			    data: { "order": data }, 
			    beforeSend: function(){
			    	$("#orderitems").html("&nbsp;&nbsp;Routing Order Items...");
			    },
			    success: function(msg) {
				    globalVar = []; 
				    jQuery('tr.item input[type=checkbox]').attr("checked",false);
				    if(msg == 1){
				    	alert('Done \nOrder successfully routed to ' + distributorName);
				    	// Push Order to Mobile App
				    	var pubnub = PUBNUB.init({
			                publish_key: 'pub-c-10336f2b-48f9-4da0-9556-a4be9539b821',
			                subscribe_key: 'sub-c-f45f8864-b627-11e4-b2c9-02ee2ddab7fe'
			            });

		                pubnub.subscribe({
		                    channel: "drugstoc",
		                    message: function (message, env, channel) {
			                    document.getElementById('text').innerHTML =
			                        "Message Received." + '<br>' +
			                        "Channel: " + channel + '<br>' +
			                        "Message: " + JSON.stringify(message) + '<br>' +
			                        "Raw Envelope: " + JSON.stringify(env) + '<br>'
		   	                    },
		                    connect: function(){
			                    pubnub.publish({
			                        channel: "drugstoc",
			                        message: "<?php echo $order->id;?>",
			                        callback: function (m) {
			                            console.log(m)
			                        }
		                    })
		                  } 
		               });
				    }else{
				    	alert("Oops! Something went wrong. Your Order cannot be routed! \n Contact Administrator.");
				    	console.log(msg);
				    }
				    $("#orderitems").html("&nbsp;&nbsp;Order Items");  
			    }
			});

			event.preventDefault();
		});
	}(jQuery)); 
	</script> 
<?php
} 