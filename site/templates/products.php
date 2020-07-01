<?php namespace ProcessWire;

$cart = $this->modules->get("OrderCart");
$settings = $this->modules->get("ProcessOrderPages");
$sku_field = $settings['f_sku'];

if($config->ajax) {

	$_input = file_get_contents("php://input");

	if($_input && $user->isLoggedin()) {

		$req = json_decode($_input);

		if(property_exists($req, "params")) {
			$params = $req->params;
		}

		if($req->action === "add") {
			return $cart->addToCart($params->sku, $params->qty);
		}

	} else {
		return json_encode(array("success"=>false));
	}

} else {

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
