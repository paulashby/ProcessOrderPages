<?php namespace ProcessWire;

$cart = $modules->get("ProcessOrderPages");

// Process checkout requests
if($input->post->qtychange) {
	// This one's an ajax request, so need to return something
	bd($input->post);
	/*
	qtychange => "3"
	sku => "CR621"
	*/
	return $cart->changeQuantity($input->post->sku, $input->post->qtychange);
}
if($input->post->submit) {
	// Input fields are arrays so all data can be submitted with single button
	// Make order from this. We can display price /100, but probably store in original state
	$items = $input->post['sku'];
	foreach ($items as $key => $value) {
		$str = 'SKU: ' . $input->post['sku'][$key];
	    $str .= ' . Quantity: ' . $input->post['quantity'][$key];
	    $str .= ' . Price: ' . $input->post['price'][$key] * $input->post['quantity'][$key]/100;
	    bd($str);
	}
}

$out = "<!DOCTYPE html>
<html lang='en'>
<head>
	<meta charset='UTF-8'>
	<title>" . $page->title . "</title>
	<script src='https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js'></script>
	<script src='" . $config->urls->templates . "scripts/checkout.js'></script>
</head>
<body>
	<div class='cart-items'>
		<form action='' method='post'>";

$pre = $cart->getPrefix();
$user_id = $users->getCurrentUser()->id;

$cart_items = $pages->find('template=' . $pre . 'line-item, ' . $pre . 'customer=' . $user_id);

foreach ($cart_items as $item => $data) {
	
	$product = $pages->findOne('template=product, ' . $pre . 'sku=' . $data[$pre . 'sku_ref']);
	$out.= "<h2>" . $product->title . "</h2>";
	
	// Add text to debug submitted form - as we're submitting inputs as arrays - name='quantity[]' etc
	$out .= "<p>SKU: " . $product->pop_sku . ". Quantity: " . $data[$pre . 'quantity'] . ". Price: " . $product->price . "</p>

		<label for='quantity'>Quantity (Packs of 6):</label>
		<input type='number' data-action='qtychange' data-sku='{$product->pop_sku}' name='quantity[]' min='1' step='1' value='" . $data[$pre . 'quantity'] . "'>
		<input type='hidden' name='sku[]' value='" . $product->pop_sku . "'>
		<input type='hidden' name='price[]' value='" . $product->price . "'>";
}
$out.= "	<input type='submit' name='submit' value='submit'>
		  </form>
	</div>
</body>
</html>";

echo $out;