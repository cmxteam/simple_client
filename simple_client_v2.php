<?php
error_reporting(1);
error_reporting(E_ALL);


class ApiClient
{
    /**
     *
     * Simple coin.mx client which covers some basic functionality
     *
     */

    private $nonce;
    private $auth_key;
    private $auth_secret;
    private $path;
    private $api_prefix;
    private $api_host;


    public function __construct($auth_key, $auth_secret, $api_host=Null, $nonce=Null){
        if($nonce == Null)
            $nonce = 1;
        $this->nonce = $nonce;
        $this->auth_key = $auth_key;
        $this->auth_secret = $auth_secret;
        $this->path = Null;
        $this->api_prefix = '/api/v2';
        $this->api_host = $api_host ? $api_host : 'https://coin.mx';
    }

    private function build_query($req){
        $post_data = http_build_query($req);
        $headers = [];
        $headers[] = 'User-Agent: NewBot';
        $headers[] = 'API_KEY: '.$this->auth_key;
        $headers[] = 'SIGNED_DATA: '.$this->sign_data($req);
        return array('post_data' => $post_data, 'headers' => $headers);
    }

    private function sign_data($data=Null){
        $str_for_hash='';
        if (!empty($data)){
            $sorted_data = [];
            ksort($data);
            foreach ($data as $key => $value) {
                $sorted_data[] = $key.'='.$value;
            }
            $str_for_hash = implode("&", $sorted_data);
        }
        return base64_encode(hash_hmac('sha512', $this->path.$str_for_hash, $this->auth_secret, true));
    }

    private function perform($args=Null, $req_type='GET', $use_nonce=False, $inner=Null){
        if(!$args)
            $args=[];

        if($use_nonce){
            $args['nonce'] = $this->nonce;
            $this->nonce += 1;
        }

        $data = $this->build_query($args);
        if($req_type == 'GET'){
            $ch = curl_init($this->api_host.$this->path.'?'.$data['post_data']);
        }else{
            $ch = curl_init($this->api_host.$this->path);
            curl_setopt_array($ch, array(
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $data['post_data']
            ));
        }

        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER => $data['headers'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => 1
        ));


        $result = json_decode(curl_exec($ch),true);
        curl_close($ch);
        if (json_last_error() == JSON_ERROR_NONE AND array_key_exists('error',$result) and false !== strpos($result['error'], 'invalid nonce') AND !$inner){
            $this->nonce = $result['nonce'];
            return $this->perform($args, $req_type, $use_nonce, True);
        }

        return $result;
    }

    private function set_path($path){
        $this->path = $this->api_prefix.$path;
        return ;
    }

    public function exchange_depth($platform=Null, $limit=100){
        $this->set_path('/exchange/depth');
        return $this->perform(array('platform' => $platform, 'limit' => $limit), 'GET');
    }

    public function exchange_history($platform=Null, $limit=100){
        $this->set_path('/exchange/history');
        return $this->perform(array('platform' => $platform, 'limit' => $limit), 'GET');
    }

    public function exchange_ticker($platform=Null){
        $this->set_path('/exchange/ticker');
        return $this->perform(array('platform' => $platform), 'GET');
    }

    public function  exchange_info(){
        $this->set_path('/exchange/info');
        return $this->perform();
    }

    public function trader_info(){
        $this->set_path('/trader/info');
        return $this->perform(array(),'GET',true);
    }

    public function list_active_orders($limit=100, $to_id=Null){
        $request_data['limit'] = $limit;
        if($to_id)
            $request_data['to_id'] = $to_id;

        $this->set_path('/trader/orders');
        return $this->perform($request_data, 'GET', True);
    }

    public function create_order($amount=Null, $cost=Null, $order_type=Null, $platform=Null){
        $this->set_path('/trader/orders');
        return $this->perform(array(
            'amount' => $amount,
            'cost' => $cost,
            'type' => $order_type,
            'platform' => $platform
        ), 'POST', True);

    }

    public function remove_order($order_id){
        $this->set_path('/trader/remove_order');
        return $this->perform(array(
            'order_id' => $order_id
        ), 'POST',True);
    }

    public function get_trade_history($limit=100, $to_id=Null){
        $request_data['limit'] = $limit;
        if($to_id)
            $request_data['to_id'] = $to_id;

        $this->set_path('/trader/orders');
        return $this->perform($request_data, 'GET', True);
    }

}

/////////////////////////////////////////////////////Your Data//////////////////////////////////////////////////////////
$auth_key = '86a6a2e66e586bf937cc';
$secret = 'ebef82aa5cf823a8751132daa948161cfb60f77a';
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$client = new ApiClient($auth_key, $secret, 'http://localhost:8000');


echo "\n--------------------------------depth------------------------------------\n";
print_r($client->exchange_depth('BTCUSD', 10));

echo "\n--------------------------------last 10 operations------------------------------------\n";
print_r($client->exchange_history('BTCUSD', 10));

echo "\n--------------------------------ticker------------------------------------\n";
print_r($client->exchange_ticker('BTCUSD'));

echo "\n--------------------------------exchange info------------------------------------\n";
print_r($client->exchange_info());

echo "\n--------------------------------list active orders------------------------------------\n";
print_r($client->list_active_orders());

echo "\n--------------------------------create limit order------------------------------------\n";
$order = $client->create_order(1, 1, 'BUY', 'BTCUSD');
print_r($order);

echo "\n--------------------------------remove order------------------------------------\n";
print_r($client->remove_order(123456));

echo "\n--------------------------------trader info------------------------------------\n";
print_r($client->trader_info());

echo "\n--------------------------------trade history------------------------------------\n";
print_r($client->get_trade_history(10));

