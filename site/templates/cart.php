<?php namespace ProcessWire;

$cart = $this->modules->get("OrderCart");

if($config->ajax) {

	$_input = file_get_contents("php://input");

	if($_input && $user->isLoggedin()) {

		$req = json_decode($_input);

		if(property_exists($req, "params")) {
			$params = $req->params;
		}

		if($req->action === "update") {
			return $cart->changeQuantity($params->sku, $params->qty);
		}

		if($req->action === "remove") {
			return $cart->removeCartItem($params->sku);
		}

		if($req->action === "order") {
			return $cart->placeOrder();
		}

	} else {
		return json_encode(array("success"=>false));
	}

} else {
	
	$out = "<!DOCTYPE html>
	<html lang='en'>
	<head>
		<meta charset='UTF-8'>
		<title>" . $page->title . "</title>";
	$out .= "</head>
	<body>
	<p><a href='" . $pages->get('/cards/')->url() . "'>Continue shopping</a></p>";
		$out .= $cart->renderCart();
		$out .= "
		</body>
	</html>";

	echo $out;

}