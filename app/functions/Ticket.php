<?php

$ticket = new Ticket();
class Ticket extends Controller
{

    public function formatProduct($product_id = null)
    {

        if(is_null($product_id) || $product_id == 0){
            return 'Kein Produkt zugewiesen';
        }

        $string = explode('_',$product_id);
        if($string['0'] == 'domain'){
            $SQL = self::db()->prepare("SELECT * FROM `domain_pricelist` WHERE `tld` = :tld");
            $SQL->execute(array(":tld" => $tld));
            $response = $SQL->fetch(PDO::FETCH_ASSOC);

            return $response['domainName'].' (#'.$string[1].')';
        } else {
            return ucfirst($string['0'].' #'.$string['1']);
        }

    }

}