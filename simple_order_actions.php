<?php
/*
*---------------------------------------------------------
*
*	CartET - Open Source Shopping Cart Software
*	http://www.cartet.org
*
*---------------------------------------------------------
*/

// AJAX запрос
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
{
	if (isset($_POST['username']) && !empty($_POST['username']))
	{
		// Пришел бот
		echo 'OK';
	}
	else
	{
		// некоторые данные покупателя
		$firstname = ($_SESSION['customer_id']) ? $_SESSION['customer_first_name'] : os_db_prepare_input($_POST['so_name']);
		$lastname = ($_SESSION['customer_id']) ? $_SESSION['customer_last_name'] : '';
		$cname = ($_SESSION['customer_id']) ? $firstname.' '.$lastname : os_db_prepare_input($_POST['so_name']);
		$secondname = '';
		$email_address = os_db_prepare_input($_POST['so_email']);
		$dob = '';
		$company = '';
		$vat = '';
		$street_address = '';
		$suburb = '';
		$postcode = '';
		$city = '';
		$state = '';
		$country = STORE_COUNTRY;
		$telephone = os_db_prepare_input($_POST['so_phone']);
		$so_product = (int)$_POST['products_id'];
		$comment = '';
		$customers_status = DEFAULT_CUSTOMERS_STATUS_ID;
		$newsletter = 0;

		if (empty($email_address) && empty($telephone))
		{
			echo json_encode(array('errors' => SO_FORM_ERROR_EMAIL_OR_PHONE));
			die();
		}

		if (SO_EMAIL == 'true' && !empty($email_address))
		{
			$aCheck[] = "customers_email_address = '".os_db_input($email_address)."'";
		}

		if (SO_PHONE == 'true' && !empty($telephone))
		{
			$aCheck[] = "customers_telephone = '".os_db_input($telephone)."'";
		}

		$check_customer_query = os_db_query("select customers_email_address, customers_telephone from ".TABLE_CUSTOMERS." where (".join(' OR ', $aCheck).") AND account_type = '0'");
		if (os_db_num_rows($check_customer_query) > 0)
		{
			echo json_encode(array('errors' => SO_FORM_ERROR_EMAIL_OR_PHONE_EXISTS));
			die();
		}

		if (!$_SESSION['customer_id'])
		{
			// Запомним данные, чтобы покупатель не вводил их постоянно
			$_SESSION['so_customer_info'] = array(
				'so_name' => $_POST['so_name'],
				'so_phone' => $_POST['so_phone'],
				'so_email' => $_POST['so_email'],
			);

			// пишем в БД нового покупателя
			$sql_data_array = array(
				'customers_vat_id' => $vat,
				'customers_vat_id_status' => $customers_vat_id_status,
				'customers_status' => $customers_status,
				'customers_firstname' => $firstname,
				'customers_secondname' => $secondname,
				'customers_lastname' => $lastname,
				'customers_email_address' => $email_address,
				'customers_telephone' => $telephone,
				'customers_fax' => $fax,
				'orig_reference' => $html_referer,
				'login_reference' => $html_referer,
				'customers_newsletter' => $newsletter,
				'customers_password' => os_encrypt_password($password),
				'customers_date_added' => 'now()',
				'customers_last_modified' => 'now()'
			);
			os_db_perform(TABLE_CUSTOMERS, $sql_data_array);
			$customers_id = os_db_insert_id();// ID покупателя

			// Customer profile
			$customerProfileArray = array(
				'customers_id' => $customers_id,
			);
			customerProfile($customerProfileArray, 'new');

			// адрес покупателя
			$sql_data_array = array(
				'customers_id' => $customers_id,
				'entry_firstname' => $firstname,
				'entry_secondname' => $secondname,
				'entry_lastname' => $lastname,
				'entry_street_address' => $street_address,
				'entry_postcode' => $postcode,
				'entry_city' => $city,
				'entry_country_id' => $country,
				'address_date_added' => 'now()',
				'address_last_modified' => 'now()'
			);
			os_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);
			$address_id = os_db_insert_id();

			// обновляем таблицу покупателя и заполняем необходимые поля
			os_db_query("update ".TABLE_CUSTOMERS." set customers_default_address_id = '".$address_id."' where customers_id = '".(int)$customers_id."'");
			// добавляем нужную инфу
			os_db_query("insert into ".TABLE_CUSTOMERS_INFO." (customers_info_id, customers_info_number_of_logons, customers_info_date_account_created) values ('".(int)$customers_id."', '0', now())");
		}
		else
		{
			$customers_id = $_SESSION['customer_id'];
		}

	/*----------------------------------------------------------------------------*/
	/*----------------------------------------------------------------------------*/
	/*----------------------------------------------------------------------------*/
		
		// методы оплаты и доставки
		$modShipping = 'sogl';
		$modPayment = 'soglas';

		// load selected payment module
		require (_CLASS.'payment.php');
		$payment_modules = new payment($modPayment);

		$tmp_status = 1;
		$shipping_cost = '';
		$discount = '0.00';
		$customers_ip = os_get_ip_address();
		$getCurrency = 'RUR';
		$customers_status_show_price_tax = 1;
		$languages_id = 1;
		$languages_name = 'ru';

		//require (_CLASS.'price.php');
		//$osPrice = new osPrice($getCurrency, 2);
		global $osPrice;
	/////////////////////////////////////////////////////////////
	//////////// получаем инфо о товарах
	/////////////////////////////////////////////////////////////

		$product = new product($so_product);
		$productInfo = $product->data;

		// товары
		$preorderProducts = array();
		$productsId = array();

		//$finalPrice = $osPrice->Format($productInfo['products_price'], false);
		$products_price = $osPrice->GetPrice($productInfo['products_id'], true, 1, $productInfo['products_tax_class_id'], $productInfo['products_price'], 1, 0, $productInfo['products_discount_allowed']);
		$finalPrice = $products_price['price']['plain'];

		$preorderProducts[] = array(
			'qty' => 1,
			'name' => $productInfo['products_name'],
			'model' => $productInfo['products_model'],
			'tax_class_id' => $productInfo['products_tax_class_id'],
			'tax' => '0',
			'tax_description' => 'Неизвестная налоговая ставка',
			'price' => $osPrice->Format($finalPrice,false),
			'final_price' => $osPrice->Format($finalPrice,false),
			'shipping_time' => '3-4 дня',
			'weight' => $productInfo['products_weight'],
			'id' => $so_product,
		);

	/////////////////////////////////////////////////////////////
	//////////// формирование итоговых цен
	/////////////////////////////////////////////////////////////
		// цена товара + количество + атрибуты
		$productsPriceTotal = $finalPrice+$shipping_cost;
		// с валютой
		$productsPrice = $osPrice->Format($productsPriceTotal,true);

	/////////////////////////////////////////////////////////////
	//////////// формирование заказа
	/////////////////////////////////////////////////////////////
		// нужно название страны и iso_code_2
		if (os_not_null($country))
		{
			$countries = os_db_query("select countries_name, countries_iso_code_2, countries_iso_code_3 from ".TABLE_COUNTRIES." where countries_id = '".(int)$country."' and status = '1' LIMIT 1");
			$countries_values = os_db_fetch_array($countries);
		}

		$sql_data_array = array(
			'customers_id' => $customers_id,
			'customers_name' => $cname,
			'customers_firstname' => $firstname,
			'customers_secondname' => $secondname,
			'customers_lastname' => $lastname,
			'customers_cid' => '',
			'customers_vat_id' => $vat,
			'customers_company' => '',
			'customers_status' => 2,
			'customers_status_name' => 'Покупатель',
			'customers_status_image' => 'customer_status.gif',
			'customers_status_discount' => $discount,
			'customers_street_address' => $street_address,
			'customers_suburb' => $suburb,
			'customers_city' => $city,
			'customers_postcode' => $postcode,
			'customers_state' => $state,
			'customers_country' => $countries_values['countries_name'],
			'customers_telephone' => $telephone,
			'customers_email_address' => $email_address,
			'customers_address_format_id' => 1,
			'delivery_name' => $cname,
			'delivery_firstname' => $firstname,
			'delivery_secondname' => $secondname,
			'delivery_lastname' => $lastname,
			'delivery_company' => '',
			'delivery_street_address' => $street_address,
			'delivery_suburb' => $suburb,
			'delivery_city' => $city,
			'delivery_postcode' => $postcode,
			'delivery_state' => $state,
			'delivery_country' => $countries_values['countries_name'],
			'delivery_country_iso_code_2' => $countries_values['countries_iso_code_2'],
			'delivery_address_format_id' => 1,
			'billing_name' => $cname,
			'billing_firstname' => $firstname,
			'billing_secondname' => $secondname,
			'billing_lastname' => $lastname,
			'billing_company' => '',
			'billing_street_address' => $street_address,
			'billing_suburb' => $suburb,
			'billing_city' => $city,
			'billing_postcode' => $postcode,
			'billing_state' => $state,
			'billing_country' => $countries_values['countries_name'],
			'billing_country_iso_code_2' => $countries_values['countries_iso_code_2'],
			'billing_address_format_id' => 1,
			'payment_method' => $modPayment,
			'payment_class' => $modPayment,
			'shipping_method' => 'По согласованию',
			'shipping_class' => $modShipping.'_'.$modShipping,
			'date_purchased' => 'now()',
			'orders_status' => $tmp_status,
			'currency' => $getCurrency,
			'currency_value' => '1.000000',
			'customers_ip' => $customers_ip,
			'language' => $languages_name,
			'comments' => $comment,
			'orig_reference' => '',
			'login_reference' => ''
		);
		os_db_perform(TABLE_ORDERS, $sql_data_array);
		$newOrderId = os_db_insert_id();

	/////////////////////////////////////////////////////////////
	//////////// формируем Итого
	/////////////////////////////////////////////////////////////
		$order_totals = array(
			array(
				'code' => 'ot_subtotal',
				'title' => 'Стоимость товара:',
				'text' =>  $productsPrice,
				'value' => $productsPriceTotal,
				'sort_order' => '10',
			),
			array(
				'code' => 'ot_shipping',
				'title' => 'По согласованию (По согласованию с администрацией):',
				'text' =>  $osPrice->Format($shipping_cost,true),
				'value' => $shipping_cost,
				'sort_order' => '30',
			),
			array(
				'code' => 'ot_total',
				'title' => '<b>Всего</b>:',
				'text' => '<b> '.$productsPrice.'</b>',
				'value' => $productsPriceTotal,
				'sort_order' => '99',
			)
		);

		for ($i = 0, $n = sizeof($order_totals); $i < $n; $i ++)
		{
			$sql_data_array = array(
				'orders_id' => $newOrderId,
				'title' => $order_totals[$i]['title'],
				'text' => $order_totals[$i]['text'],
				'value' => $order_totals[$i]['value'],
				'class' => $order_totals[$i]['code'],
				'sort_order' => $order_totals[$i]['sort_order']
			);
			os_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
		}

	/////////////////////////////////////////////////////////////
	//////////// история заказа
	/////////////////////////////////////////////////////////////
		$sql_data_array = array(
			'orders_id' => $newOrderId,
			'orders_status_id' => $tmp_status,
			'date_added' => 'now()',
			'customer_notified' => ((SEND_EMAILS == 'true') ? '1' : '0'),
			'comments' => $comment
		);
		os_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

	/////////////////////////////////////////////////////////////
	//////////// пишем в БД товары заказа
	/////////////////////////////////////////////////////////////
		$total_tax = 0;
		for ($i = 0, $n = sizeof($preorderProducts); $i < $n; $i ++)
		{
			// Stock Update - Joao Correia
			if (STOCK_LIMITED == 'true')
			{
				if (DOWNLOAD_ENABLED == 'true')
				{
					$stock_query_raw = "
					SELECT 
						products_quantity, pad.products_attributes_filename
					FROM 
						".TABLE_PRODUCTS." p
							LEFT JOIN ".TABLE_PRODUCTS_ATTRIBUTES." pa ON p.products_id=pa.products_id
							LEFT JOIN ".TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD." pad ON pa.products_attributes_id=pad.products_attributes_id
					WHERE 
						p.products_id = '".os_get_prid($preorderProducts[$i]['id'])."'
					";
					// Will work with only one option for downloadable products
					// otherwise, we have to build the query dynamically with a loop
					$products_attributes = $preorderProducts[$i]['attributes'];
					
					if (is_array($products_attributes))
					{
						$stock_query_raw .= " AND pa.options_id = '".$products_attributes[0]['option_id']."' AND pa.options_values_id = '".$products_attributes[0]['value_id']."'";
					}
					$stock_query = os_db_query($stock_query_raw);
				}
				else
				{
					$stock_query = os_db_query("select products_quantity from ".TABLE_PRODUCTS." where products_id = '".os_get_prid($preorderProducts[$i]['id'])."'");
				}

				if (os_db_num_rows($stock_query) > 0)
				{
					$stock_values = os_db_fetch_array($stock_query);
					// do not decrement quantities if products_attributes_filename exists
					if ((DOWNLOAD_ENABLED != 'true') || (!$stock_values['products_attributes_filename']))
						$stock_left = $stock_values['products_quantity'] - $preorderProducts[$i]['qty'];
					else
						$stock_left = $stock_values['products_quantity'];

					os_db_query("update ".TABLE_PRODUCTS." set products_quantity = '".$stock_left."' where products_id = '".os_get_prid($preorderProducts[$i]['id'])."'");
					if (($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false'))
					{
						os_db_query("update ".TABLE_PRODUCTS." set products_status = '0' where products_id = '".os_get_prid($preorderProducts[$i]['id'])."'");
					}
				}
			}

			// Update products_ordered (for bestsellers list)
			os_db_query("update ".TABLE_PRODUCTS." set products_ordered = products_ordered + ".sprintf('%d', $preorderProducts[$i]['qty'])." where products_id = '".os_get_prid($preorderProducts[$i]['id'])."'");

			$sql_data_array = array(
				'orders_id' => $newOrderId,
				'products_id' => os_get_prid($preorderProducts[$i]['id']),
				'products_model' => $preorderProducts[$i]['model'],
				'products_name' => $preorderProducts[$i]['name'],
				'products_shipping_time'=>$preorderProducts[$i]['shipping_time'],
				'products_price' => $preorderProducts[$i]['price'],
				'final_price' => $preorderProducts[$i]['final_price'],
				'products_tax' => $preorderProducts[$i]['tax'],
				'products_discount_made' => $preorderProducts[$i]['discount_allowed'],
				'products_quantity' => $preorderProducts[$i]['qty'],
				'allow_tax' => $customers_status_show_price_tax
			);

			os_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);
			$order_products_id = os_db_insert_id();

			// Aenderung Specials Quantity Anfang
			$specials_result = os_db_query("SELECT products_id, specials_quantity from ".TABLE_SPECIALS." WHERE products_id = '".os_get_prid($preorderProducts[$i]['id'])."' ");
			if (os_db_num_rows($specials_result))
			{
				$spq = os_db_fetch_array($specials_result);

				$new_sp_quantity = ($spq['specials_quantity'] - $preorderProducts[$i]['qty']);

				if ($new_sp_quantity >= 1)
					os_db_query("update ".TABLE_SPECIALS." set specials_quantity = '".$new_sp_quantity."' where products_id = '".os_get_prid($preorderProducts[$i]['id'])."' ");
				else
					os_db_query("update ".TABLE_SPECIALS." set status = '0', specials_quantity = '".$new_sp_quantity."' where products_id = '".os_get_prid($preorderProducts[$i]['id'])."' ");
			}
			// Aenderung Ende

		}

		$customers_query = os_db_query("SELECT refferers_id as ref FROM ".TABLE_CUSTOMERS." WHERE customers_id='".$customers_id."'");
		$customers_data = os_db_fetch_array($customers_query);
		if (os_db_num_rows($customers_query))
		{
			os_db_query("update ".TABLE_ORDERS." set refferers_id = '".$customers_data['ref']."' where orders_id = '".$newOrderId."'");
			// check if late or direct sale
			$customers_logon_query = "SELECT customers_info_number_of_logons
			FROM ".TABLE_CUSTOMERS_INFO."
			WHERE customers_info_id  = '".$customers_id."'";
			$customers_logon_query = os_db_query($customers_logon_query);
			$customers_logon = os_db_fetch_array($customers_logon_query);

			if ($customers_logon['customers_info_number_of_logons'] == 0)
			{
				// direct sale
				os_db_query("update ".TABLE_ORDERS." set conversion_type = '1' where orders_id = '".$newOrderId."'");
			}
			else
			{
				// late sale
				os_db_query("update ".TABLE_ORDERS." set conversion_type = '2' where orders_id = '".$newOrderId."'");
			}
		}

		global $osTemplate, $main;

		$osTemplate->assign('customer_name', $cname);
		$osTemplate->assign('customer_telephone', $telephone);
		$osTemplate->assign('customer_email', $email_address);
		$osTemplate->assign('product_name', $productInfo['products_name']);
		$osTemplate->assign('products_shippingtime', $main->getShippingStatusName($productInfo['products_shippingtime']));
		$osTemplate->assign('order_id', $newOrderId);

		$osTemplate->caching = false;
		$html_mail = $osTemplate->fetch(dirname(__FILE__).'/mail/'.$_SESSION['language'].'/order_admin.html');
		$txt_mail = $osTemplate->fetch(dirname(__FILE__).'/mail/'.$_SESSION['language'].'/order_admin.txt');

		os_php_mail(EMAIL_SUPPORT_ADDRESS, EMAIL_SUPPORT_NAME, EMAIL_SUPPORT_ADDRESS, STORE_NAME, '', EMAIL_SUPPORT_REPLY_ADDRESS, EMAIL_SUPPORT_REPLY_ADDRESS_NAME, '', '', SO_MAIL_SUBJECT, $html_mail, $txt_mail);

		if ($email_address)
		{
			$c_html_mail = $osTemplate->fetch(dirname(__FILE__).'/mail/'.$_SESSION['language'].'/order.html');
			$c_txt_mail = $osTemplate->fetch(dirname(__FILE__).'/mail/'.$_SESSION['language'].'/order.txt');

			os_php_mail(EMAIL_BILLING_ADDRESS, EMAIL_BILLING_NAME, $email_address, $cname, '', EMAIL_BILLING_REPLY_ADDRESS, EMAIL_BILLING_REPLY_ADDRESS_NAME, '', '', SO_MAIL_SUBJECT, $c_html_mail, $c_txt_mail);
		}

		$data = SO_ORDER_SUCCESS;
		echo json_encode($data);
		die();
	}
}