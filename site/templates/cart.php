<?php namespace ProcessWire;

$cart = $this->modules->get("OrderCart");

// Put this is cart


if($config->ajax) {

	$_input = file_get_contents("php://input");

	if($_input) {

		$req = json_decode($_input);
		$params = $req->params;

		if($req->action === "qtychange") {

			return $cart->changeQuantity($params->sku, $params->qty);
		}

		if($req->action === "remove") {

			return $cart->removeCartItem($params->sku);
		}

		if($req->action === "submit") {

			// From prev version - might need work
			$cart->placeOrder();
		}

	} else {
		return json_encode(array("success"=>false));
	}



	bd("ajax");
	// Process checkout requests
	if($input->post->qtychange) {
		
		return $cart->changeQuantity($input->post->sku, $input->post->qtychange);

	} else if($input->post->remove) {

		return $cart->removeCartItem($input->post->sku);

	} else if($input->post->submit) {

		$cart->placeOrder();
	}
} else {
	bd("not ajax");
	$out = "<!DOCTYPE html>
	<html lang='en'>
	<head>
		<meta charset='UTF-8'>
		<title>" . $page->title . "</title>";
		// <script src='https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js'></script>
	$out .= "</head>
	<body>
	<p><a href='" . $pages->get('/cards/')->url() . "'>Continue shopping</a></p>";
		$out .= $cart->renderCart();
		$out .= "
		</body>
	</html>";

	echo $out;

}