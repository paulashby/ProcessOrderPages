<?php namespace ProcessWire;

$cart = $this->modules->get("OrderCart");
$settings = $this->modules->get("ProcessOrderPages");
$sku_field = $settings['f_sku'];

// Process 'add to cart' requests
if($input->post->submit) {

	if($user->isLoggedin()) {

		$cart->addToCart($input->post);
    }	
}

if($config->ajax) {

	$_input = file_get_contents("php://input");

	if($_input) {

		$req = json_decode($_input);
		$params = $req->params;

		if($req->action === "qtychange") {

			// return $cart->changeQuantity($params->sku, $params->qty);
			$cart->addToCart($input->post);
		}

	} else {
		return json_encode(array("success"=>false));
	}

} else {

}

$out = 
"<!DOCTYPE html>
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

	$out .= "<form action='' method='post'>
		<h2>" . $product->title . "</h2>
		<label class='.form__label' for='quantity'>Quantity (Packs of 6):</label>
		<input class='.form__quantity' type='number' id='quantity' name='quantity' min='1' step='1' value='1'>
		<input type='hidden' id='sku' name='sku' value='" . $product[$sku_field] . "'>
		<input type='hidden' id='price' name='price' value='" . $product->price . "'>
		  <input class='form__button form__button--submit' type='submit' name='submit' value='submit' data-action='submit'> 
	</form>";

}

$out .= "</div>";	
$out .= "</body>
</html>";

echo $out;
