<?php
/*
	Plugin Name: Заказ в один клик
	Plugin URI: http://osc-cms.com/store/plugins/simple-order
	Version: 1.3
	Description: Плагин Заказ в один клик
	Author: CartET
	Author URI: http://osc-cms.com
	Plugin Group: Products
*/

global $p;
if ($p->info['simple_order']['status'] == 1)
{
	define('SO_NAME', get_option('so_name'));
	define('SO_PHONE', get_option('so_phone'));
	define('SO_EMAIL', get_option('so_email'));
	define('SO_SMS_ADMIN', get_option('so_sms_admin'));
	define('SO_SMS', get_option('so_sms'));
}

add_filter('build_products', 'simple_order_products_listing');
add_action('products_info', 'simple_order_page_info');
add_action('page', 'simple_order_products_form');
add_action('page', 'simple_order_actions');

function simple_order_products_listing($value)
{
	$products_id = @$value['PRODUCTS_ID'];

	include(dirname(__FILE__).'/lang/'.$_SESSION['language'].'.php');

	$value['simple_order'] = '<a href="'._HTTP.'index.php?page=simple_order_products_form&pid='.$products_id.'" rel="modal:open">'.SO_TITLE.'</a>';

	return $value;
}

function simple_order_page_info()
{
	global $product;

	include(dirname(__FILE__).'/lang/'.$_SESSION['language'].'.php');

	$simple_order_link = '<a class="pl-ooc2" href="'._HTTP.'index.php?page=simple_order_products_form&pid='.$product->data['products_id'].'" rel="modal:open">'.SO_TITLE.'</a>';

	return array('name' => 'simple_order', 'value' => $simple_order_link);
}

// Форма заказа
function simple_order_products_form()
{
	global $osTemplate;

	$product_id = (int)$_GET['pid'];
	$product = new product($product_id);

	if (!is_object($product) || !$product->isProduct())
	{
		echo 'product not found';
	}
	else
	{
		include(dirname(__FILE__).'/lang/'.$_SESSION['language'].'.php');
		$osTemplate->assign('linkAction', _HTTP.'index.php?page=simple_order_actions');
		$osTemplate->assign('plugurl', _HTTP.'modules/plugins/simple_order/');
		$osTemplate->assign('products_id', $product->data['products_id']);
		$osTemplate->assign('products_name', $product->data['products_name']);

		if (isset($_SESSION['customer_id']))
		{
			$getCustomerData = os_db_query("SELECT * FROM ".TABLE_CUSTOMERS." WHERE customers_id = '".(int)$_SESSION['customer_id']."'");
			$customerData = os_db_fetch_array($getCustomerData);
			$osTemplate->assign('customerData', $customerData);
		}
		else
			$osTemplate->assign('customerName', $_SESSION['so_customer_info']['so_name']);

		$osTemplate->display(dirname(__FILE__).'/simple_order.html');
	}
}

// Создание заказа
function simple_order_actions()
{
	include(dirname(__FILE__).'/lang/'.$_SESSION['language'].'.php');
	include(dirname(__FILE__).'/simple_order_actions.php');
}

// Установка
function simple_order_install()
{
	add_option('so_name', 'true', 'radio', "array('true', 'false')");
	add_option('so_phone', 'true', 'radio', "array('true', 'false')");
	add_option('so_email', 'false', 'radio', "array('true', 'false')");
	add_option('so_sms_admin', 'false', 'radio', "array('true', 'false')");
	add_option('so_sms', 'false', 'radio', "array('true', 'false')");
}