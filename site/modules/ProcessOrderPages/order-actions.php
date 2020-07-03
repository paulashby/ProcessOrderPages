<?php namespace ProcessWire;

if($config->ajax) {

	if ($session->CSRF->hasValidToken('oc_token')) {

		$_input = file_get_contents("php://input");

		if($_input && $user->isLoggedin()) {

			$req = json_decode($_input);
			$cart = $this->modules->get("OrderCart");

			if(property_exists($req, "params")) {
				$params = $req->params;
			}

			if($req->action === "add") {
				return $cart->addToCart($params->sku, $params->qty);
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
			return json_encode(array("success"=>false, "error"=>"Users must be logged in to use the cart"));
		}
	}
	return json_encode(array("success"=>false, "error"=>"CSRF validation error"));

}