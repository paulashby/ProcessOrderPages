<?php namespace ProcessWire;

$cart = $this->modules->get("OrderCart");

// Process checkout requests
if($input->post->qtychange) {

	return $cart->changeQuantity($input->post->sku, $input->post->qtychange);

} else if($input->post->remove) {

	return $cart->removeCartItem($input->post->sku);

} else if($input->post->submit) {

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
<p><a href='" . $pages->get('/cards/')->url() . "'>Continue shopping</a></p>";
	$out .= $cart->renderCart();
	$out .= "
	</body>
</html>";

echo $out;