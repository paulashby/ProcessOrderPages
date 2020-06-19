<?php namespace ProcessWire;

$cart = $modules->get("ProcessOrderPages");

// Process checkout requests
if($input->post->qtychange) {

	return $cart->changeQuantity($input->post->sku, $input->post->qtychange);

} else if($input->post->remove) {

	return $cart->removeCartItem($input->post->sku);

} else if($input->post->submit) {

	// Input fields are arrays so all data can be submitted with single button
	// Make order from this. We can display price /100, but probably store in original state
	// $items = $input->post['sku'];
	// foreach ($items as $key => $value) {
	// 	$str = 'SKU: ' . $input->post['sku'][$key];
	//     $str .= ' . Quantity: ' . $input->post['quantity'][$key];
	//     $str .= ' . Price: ' . $input->post['price'][$key] * $input->post['quantity'][$key]/100;
	//     bd($str);
	// }
	$cart->placeOrder();
}

$out = "<!DOCTYPE html>
<html lang='en'>
<head>
	<meta charset='UTF-8'>
	<title>" . $page->title . "</title>
	<script src='https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js'></script>
	<script src='" . $config->urls->templates . "scripts/cart.js'></script>
</head>
<body>
<p><a href='" . $pages->get('/products/')->url() . "'>Continue shopping</a></p>";
	$out .= $cart->renderCart();
	$out .= "
	</body>
</html>";

echo $out;