<?php
if (!defined('ATHENA_ROOT')) exit;

$this->loginRequired('Please log-in to add items to your cart.');

require_once 'Athena/ItemShop.php';

$id   = $params->get('id');
$shop = new Athena_ItemShop($server);
$item = $shop->getItem($id);

if ($item) {
	$server->cart->add($item);
	$session->setMessageData("{$item->shop_item_name} has been added to your cart.");
}
else {
	$session->setMessageData("Couldn't add item to your cart.");
}

$action = $params->get('cart') ? 'cart' : 'index';
$this->redirect($this->url('purchase', $action));
?>