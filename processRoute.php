<?php
// Twilio api library
//require_once('twilio-php-master/Services/Twilio.php');
require_once('twilio-php/Services/Twilio.php');      
require_once('../../../wp-load.php');

//Twilio Account Details
$AccountSid = "ACee9332ba9fa2eb4becb6eb25e8a9f1eb";
$AuthToken  = "bda3517430f468419934d1ea054675a8";
 
$client = new Services_Twilio($AccountSid, $AuthToken);  

// Order Array 
$order = $_POST['order']; 
$orderid = $order[0]['orderid'];
$customer = $order[0]['customer'];
$email = $order[0]['email'];
$phonenumber = $order[0]['phonenumber'];
$distributor = $order[0]['distributors'];  
 
/*
 * Compose SMS and Log Routed Items  
 */  
global $wpdb, $woocommerce;  
$table_name = $wpdb->prefix . 'routed_order_items';  

// Get distributor by Email
$user_dist = get_user_by('email', $email);

// Compose Messages
$sms_message = "Order ID : ".$orderid." Customer: ".$customer."\n"; // SMS
$message2 = "";		// Email					
$ordertotal = 0; 

foreach($order as $item) { 
    $price = preg_replace("/\₦/", "", $item['amount']);
    $sms_message.= "Item Name: ".$item['name']." Quantity: ".$item['qty']." Amount: ₦".number_format((float)$price,2)."\n";
    
    $message2.= '<tr><td scope="col" style="text-align:left; color: #333333;">'.$item['name'].'</td>';
    $message2.= '<td scope="col" style="text-align:left; color: #333333;">'.$item['qty'].'</td>';
    $message2.= '<td scope="col" style="text-align:left; color: #333333;">₦'.number_format((float)$price,2).'</td></tr>'; 
    $ordertotal += $price;

    // Check if item already exists in order 
	$old_order = $wpdb->get_row("SELECT order_id, item_id FROM {$wpdb->prefix}routed_order_items WHERE order_id = $orderid and item_id = {$item['itemid']} LIMIT 1");
	$_order_id = $old_order->order_id; 

    // Update if order already exists 
    if($_order_id > 1){
	    // Update Log routed_items
	    $wpdb->update(
			$table_name, 
			array( 
				'distributor' => $user_dist->ID,     
				'routed' => 1
			),
			array('order_id' => $orderid),
			array( 
				'%s',	  
				'%d'	  
			), 
			array( '%d' ) 
		); 
    }else{
		// Insert new Log routed_items
	    $wpdb->insert(	
			$table_name, 
			array( 
				'distributor'=> $user_dist->ID,  
				'order_id'   => $item['orderid'], 
				'item_id'    => $item['itemid'], 
				'item_qty'   => $item['qty'], 
				'line_total' => preg_replace("/\₦/", "", $item['amount']), //
				'routed' => 1
			) 
		);
    }  
} 
$ordertotal = number_format($ordertotal, 2);
$sms_message.= "Order Total: ₦".$ordertotal."\n"." Drugstoc Team.";  

// Send Email 
$order = new WC_Order($orderid );  

$site_title = __('DrugStoc','DrugStoc');

$subject = 'New Order Notification'; 
$to = $email;     

$headers = 'From: DrugStoc <mailer@drugstoc.ng>'."\r\n";
$headers .= "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8"."\r\n"; 
$headers .= 'Bcc: adhamyehia@gmail.com'."\r\n"; 
  
$user = get_user_by( 'id', $order->customer_user ); 

ob_start(); ?>

	<html style="background:#0080FF">
		<head>
			<title><?php echo $email_subject ?></title>
		</head>
		<body>
			<div id="email_container">
				<div style="width:570px; padding:0 0 0 20px; margin:50px auto 12px auto" id="email_header">
					<span style="background:#0080FF; color:#fff; padding:12px;font-family:trebuchet ms; letter-spacing:1px;
					-moz-border-radius-topleft:5px; -webkit-border-top-left-radius:5px;
					border-top-left-radius:5px;moz-border-radius-topright:5px; -webkit-border-top-right-radius:5px;
					border-top-right-radius:5px;">
					<?php echo "DrugStoc";?>
				</div>
			</div>
			<div style="width:550px; padding:0 20px 20px 20px; background:#fff; margin:0 auto; border:2px #0080FF solid;
				moz-border-radius:5px; -webkit-border-radus:5px; border-radius:5px; color:#333333;line-height:1.5em; " id="email_content">
				<h1 style="padding:5px 0 0 0; font-family:georgia;font-weight:500;font-size:24px;color:#0080FF;padding-bottom:10px;border-bottom:1px solid #0080FF">
				<?php echo $subject ?>
				</h1>

				<?php do_action( 'woocommerce_email_header', $email_heading ); ?>

				<p style="color: #333333;">
				<?php printf( __( 'You have received an Order from %s. See below for details:', 'woocommerce' ), get_user_meta($user->ID, 'institution',true)); ?></p>
				<?php echo $client_message; ?> 

				<?php do_action( 'woocommerce_email_before_order_table', $order, true ); ?>

				<h2 style="color: #333333;"><?php printf( __( 'Order: %s', 'woocommerce'), $order->get_order_number() ); ?> (<?php printf( '<time datetime="%s">%s</time>', date_i18n( 'c', strtotime( $order->order_date ) ), date_i18n( woocommerce_date_format(), strtotime( $order->order_date ) ) ); ?>)</h2>

				<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;" bordercolor="#eee">
					<thead>
						<tr>
							<th scope="col" style="text-align:left; border: 1px solid #eee;color: #333333;"><?php _e( 'Product', 'woocommerce' ); ?></th>
							<th scope="col" style="text-align:left; border: 1px solid #eee;color: #333333;"><?php _e( 'Quantity', 'woocommerce' ); ?></th>
							<th scope="col" style="text-align:left; border: 1px solid #eee;color: #333333;"><?php _e( 'Price', 'woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php echo $message2; ?>
					</tbody>
					<tfoot> 
						<tr>
							<th scope="row" colspan="2" style="color: #333333;text-align:left; border: 1px solid #eee; border-top-width: 4px;" > Order Total: </th>
							<td style="text-align:left; color: #333333;border: 1px solid #eee; border-top-width: 4px;"><?php echo "₦$ordertotal"; ?></td>
						</tr> 
					</tfoot>
				</table>

				<?php do_action('woocommerce_email_after_order_table', $order, true); ?>

				<?php do_action( 'woocommerce_email_order_meta', $order, true ); ?>
				
				<p style="color: #333333;">
				<strong style="color: #333333;">
					<?php echo 'Order Notes:'; ?></strong> 
					<?php echo $order->customer_note;?>
				</p>

				<h2 style="color: #333333;"><?php _e( 'Customer details', 'woocommerce' ); ?></h2>

				<?php if ( $order->billing_email ) { ?>
					<p style="color: #333333;"><strong style="color: #333333;"><?php _e( 'Email:', 'woocommerce' ); ?></strong> <?php echo $user->user_email; ?></p>
				<?php } ?>

				<?php if ( $order->billing_phone ) {?>
					<p style="color: #333333;"><strong style="color: #333333;"><?php _e( 'Tel:', 'woocommerce' ); ?></strong> <?php echo get_user_meta($user->ID,'phonenumber',true);?></p>
				<?php }?> 

				<?php woocommerce_get_template( 'emails/email-addresses.php', array( 'order' => $order ) ); 

				do_action( 'woocommerce_email_footer' ); ?>

							<p style="color: #333333;">
							Thank You,<br/>
							DrugStoc Team.<br/>
							Tel: +2348096879999<br/>
							Email: info@drugstoc.com
							</p>
							<p><img src="http://drugstoc.biz/wp-content/uploads/2014/10/splash-logo-beta.png"/></p>
							<div style="text-align:center; border-top:1px solid #eee;padding:5px 0 0 0;" id="email_footer">
								<small style="font-size:11px; color:#999; line-height:14px;">
								You have received this email because you are a member of <?php echo $site_title; ?>.
								</small>
							</div>
						</div>
					</div>
				</body>
			</html>

	<?php
	$message = ob_get_contents();

	ob_end_clean(); 

//	Send Email
$rt = mail($to, $subject, wordwrap($message, 200, "\n", true), wordwrap($headers, 75, "\n", true) ); 
 
//	Send SMS
try{
	if(strlen($sms_message) < 1600){
		$sms = $client->account->messages->sendMessage("+12086960938", $phonenumber, $sms_message);
	}else{
		$sms_message = "Hi $distributor, \n You have a new bulk order from $customer: \n Order Number: $orderid \n Number of Unique Items: ".count($order)." \n Order Total: ₦$ordertotal \nPlease check your email for more details. \nDrugStoc Team.";
		$sms = $client->account->messages->sendMessage("+12086960938", $phonenumber, $sms_message);
	} 
}catch(Services_Twilio_RestException $e){
	echo $e;
	exit;
}

echo 1;
?>