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
				$sku = $this->sanitizer->text($params->sku);
				$qty = $this->sanitizer->int((int)$params->qty);
				return $cart->addToCart($sku, $qty);
			}

			if($req->action === "update") {
				$sku = $this->sanitizer->text($params->sku);
				$qty = $this->sanitizer->int((int)$params->qty);

				return $cart->changeQuantity($sku, $qty, true); // last arg = eager loading - else lazy loading images don't appear when cart updates
			}

			if($req->action === "remove") {
				$sku = $this->sanitizer->text($params->sku);
				return $cart->removeCartItem($sku);
			}

			if($req->action === "order") {
				$ecopack = $this->sanitizer->bool($params->ecopack);
				return $cart->placeOrder($ecopack);
			}

		} else {
			return json_encode(array("success"=>false, "error"=>"Users must be logged in to use the cart"));
		}
	}
	return json_encode(array("success"=>false, "error"=>"CSRF validation error"));

}