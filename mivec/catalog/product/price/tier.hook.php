<?php
require 'config.php';

$_date = date("ymd");
$_toFile = "tier.$_date.csv";
//get tier price
$sql = "SELECT a.entity_id,a.sku,b.attribute_id,b.`value` AS price,c.`value` AS tier_price FROM "
    .__TABLE_PRODUCT__ . " a LEFT JOIN "
    .__TABLE_PRICE__ . " b ON(a.entity_id=b.entity_id) LEFT JOIN "
    .__TABLE_PRICE_TIER__ . " c ON a.entity_id=c.entity_id"
    ." WHERE b.attribute_id=75"
    ." AND c.`value` != 0"
    //." AND b.`value` != c.`value`"
    ." ORDER BY entity_id DESC";

if ($row = $db->fetchAll($sql)) {
    $fp = fopen(__DATA_PATH__ . $_toFile , "wb+");
    $header = array("id" , "SKU" , "price" , "tier_price");
    fputcsv($fp , $header);

    foreach ($row as $rs) {
        $data = array(
            $rs['entity_id'] , $rs['sku'] , $rs["price"] , $rs['tier_price']
        );
        if (fputcsv($fp , $data)) {
            echo $rs['sku'] . " was generate tier price</p>";
        }
    }

//destroy
destroyTierPrice();

fclose($fp);
}
/**
 * //cron
 * // *\1 * * * 0 0 * * * php -f /var/www/gsp/www/mivec/catalog/product/price/tier.hook.php
 *
 * */