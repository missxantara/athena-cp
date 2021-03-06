<?php
if (!defined('ATHENA_ROOT')) exit; 

$this->loginRequired();

$title = 'Add Item to Shop';

require_once 'Athena/TemporaryTable.php';
require_once 'Athena/ItemShop.php';

$itemID = $params->get('id');

$category   = null;
$categories = Athena::config('ShopCategories')->toArray();
$tableName  = "{$server->charMapDatabase}.items";
$fromTables = array("{$server->charMapDatabase}.item_db", "{$server->charMapDatabase}.item_db2");
$tempTable  = new Athena_TemporaryTable($server->connection, $tableName, $fromTables);
$shopTable  = Athena::config('AthenaTables.ItemShopTable');

$col = "id AS item_id, name_japanese AS item_name, type";
$sql = "SELECT $col FROM $tableName WHERE items.id = ?";
$sth = $server->connection->getStatement($sql);

$sth->execute(array($itemID));
$item = $sth->fetch();

$stackable = false;
if ($item && Athena::isStackableItemType($item->type)) {
	$stackable = true;
}

if ($item && count($_POST)) {
	$maxCost     = (int)Athena::config('ItemShopMaxCost');
	$maxQty      = (int)Athena::config('ItemShopMaxQuantity');
	$category    = $params->get('category');
	$shop        = new Athena_ItemShop($server);
	$cost        = (int)$params->get('cost');
	$quantity    = (int)$params->get('qty');
	$info        = trim($params->get('info'));
	$image       = $files->get('image');
	$useExisting = (int)$params->get('use_existing');
	
	if (!$cost) {
		$errorMessage = 'You must input a credit cost greater than zero.';
	}
	elseif ($cost > $maxCost) {
		$errorMessage = "The credit cost must not exceed $maxCost.";
	}
	elseif (!$quantity) {
		$errorMessage = 'You must input a quantity greater than zero.';
	}
	elseif ($quantity > 1 && !$stackable) {
		$errorMessage = 'This item is not stackable. Quantity must be 1.';
	}
	elseif ($quantity > $maxQty) {
		$errorMessage = "The item quantity must not exceed $maxQty.";
	}
	elseif (!$info) {
		$errorMessage = 'You must input at least some info text.';
	}
	else {
		if ($id=$shop->add($itemID, $category, $cost, $quantity, $info, $useExisting)) {
			$message = 'Item has been successfully added to the shop';
			if ($image && $image->get('size') && !$shop->uploadShopItemImage($id, $image)) {
				$message .= ', but the image failed to upload. You can re-attempt by modifying.';
			}
			else {
				$message .= '.';
			}
			$session->setMessageData($message);
			$this->redirect($this->url('purchase'));	
		}
		else {
			$errorMessage = 'Failed to add the item to the shop.';
		}
	}
}

if (!$stackable) {
	$params->set('qty', 1);
}
?>