<?php
function sc_curl_post_req($url,$data){
    $curl = curl_init();
    curl_setopt($curl,CURLOPT_URL,$url);
    curl_setopt($curl,CURLOPT_POST,true);
    curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($data));
    curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
    $result = curl_exec($curl);
    curl_close($curl);
    return json_decode($result,true);
}

$data = array(
    'order-id'=>1009,
    'tracking_url'=>'https://tracking-site.com/track/', // Tracking URL
    'tracking_num'=>'ABCXYZ134545', // Tracking Number
    'items'=>array( // Shipped items in current shipment
        array(
            'sku'=>'SK01',  // Shipped item  SKU
            'qty'=>1,        // Shipped item Qty
        ),
        array(
            'sku'=>'SK02',
            'qty'=>1,
        ),
    )
);

$url = 'https://your-site.com/wp-json/wxp-shipment-data/wxp-data/?key=44a298073f287782f48c6bd01bdcb5dd&action=update';
//action=update if action parameter passed in URL, it will create new shipment on every request
//if "action=update" removed from URL then it will return all existing shipment array and won't create any new shipment


$json = sc_curl_post_req($url,$data);
echo '<pre>'; print_r($json); echo '</pre>';
die;
