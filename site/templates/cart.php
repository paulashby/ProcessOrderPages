<?php namespace ProcessWire;

$cart = $this->modules->get("OrderCart");

if( ! $config->ajax) {
	
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