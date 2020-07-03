<?php namespace ProcessWire;

$cart = $this->modules->get("OrderCart");

if( ! $config->ajax) {

	$out = "<!DOCTYPE html>
	<html lang='en'>
	<head>
		<meta charset='UTF-8'>
		<title>$page->title</title>
	</head>
	<body>
	<p><a href='" . $pages->get('/cart/')->url() . "'>Cart</a></p>";

	$products = $pages->find('template=product');

	$out .= "<div class='products'>";

	foreach ($products as $product) {
		$out .= $cart->renderItem($product);
	}

	$out .= "</div>";	
	$out .= "<script src='" . $this->config->urls->site . "modules/OrderCart/cart.js'></script>
	</body>
	</html>";

	echo $out;

}
