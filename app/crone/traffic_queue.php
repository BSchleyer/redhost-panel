<?php
$currPage = 'system_traffic queue';
include BASE_PATH.'app/controller/PageController.php';

$key = $helper->protect($_GET['key']);

//every 10 minutes

if($key == env('CRONE_KEY')){

    $SQL = $db->prepare("SELECT * FROM `vm_servers` WHERE `deleted_at` IS NULL");
    $SQL->execute();
    if ($SQL->rowCount() != 0) {
        while ($row = $SQL->fetch(PDO::FETCH_ASSOC)) {

            if(is_null($row['traffic'])){
                $available_traffic = $helper->getSetting('default_traffic_limit');
            } else {
                $available_traffic = $row['traffic'];
            }

            if($available_traffic >= $row['curr_traffic']) {
                $date1 = date("m", strtotime($row['created_at']));
                $date2 = date("m", strtotime("first day of this month"));
                if ($date1 == $date2) {
                    $first = date("Y-m-d", strtotime($row['created_at']));
                } else {
                    $first = date("Y-m-d", strtotime("first day of this month"));
                }

                if ($row['api_name'] == 'VENOCIX') {

                    $bytes_in = 0;
                    $bytes_out = 0;

                    foreach ($site->getAddressesFromServer($row['id']) as $ip) {
                        $res = $venocix->getTraffic($ip['ip'], $first, 0);

                        $bytes_in = $bytes_in + $res->data->bytes_in;
                        $bytes_out = $bytes_out + $res->data->bytes_out;
                    }
                    $total_bytes = $bytes_in + $bytes_out;

                    $total_traffic = round($helper->isa_convert_bytes_to_specified($total_bytes, 'G'));
                }

                if ($total_traffic > $available_traffic) {
                    $lxc->shutdown($row['node_id'], $row['id']);

                    echo 'Traffic Suspended VPS #' . $row['id'] . '<br>';
                }

                $update = $db->prepare("UPDATE `vm_servers` SET `curr_traffic` = :curr_traffic WHERE `id` = :id");
                $update->execute(array(":curr_traffic" => $total_traffic, ":id" => $row['id']));

                echo 'vServer: #' . $row['id'] . '<br>';
                echo 'Traffic: ' . $total_traffic . 'GB / ' . $available_traffic . '<br><br><br>';

            }
        }
    }

} else {
    include BASE_PATH.'resources/sites/404.php';
}