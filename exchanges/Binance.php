<?php

class Binance
{
    protected $apiKey;
    protected $apiSecret;
    protected $urlPublic;

    public function __construct()
    {
        $this->urlPublic = 'https://api.binance.com/api/v1/';
    }

    /**
     * @param User $user
     */
    public function setUser($api_key, $api_secret_key)
    {
        $this->apiKey = $api_key;
        $this->apiSecret = $api_secret_key;
    }

    /**
     * @param string $url
     * @return mixed
     */
    protected function retrieveJSON($url)
    {
        $opts = [
            'http' =>
                [
                    'method' => 'GET',
                    'timeout' => 10
                ]
        ];

        $context = stream_context_create($opts);
        $feed = file_get_contents($url, false, $context);
        $json = json_decode($feed, true);
        return $json;
    }

    /**
     * @param string $side
     * @param string $symbol
     * @param double $quantity
     * @param double $price
     * @param string $type
     * @return mixed
     */
    public function order($side, $symbol, $quantity, $price, $type = "LIMIT")
    {
        $options = [
            "symbol" => $symbol,
            "side" => $side,
            "type" => $type,
            "quantity" => $quantity,
            "recvWindow" => 60000
        ];

        if (gettype($price) !== "string") {
            $price = number_format($price, 8, '.', '');
        }

        $options["price"] = $price;
        $options["timeInForce"] = "GTC";

        $queryString = "order";
        return $this->httpRequest($queryString, "POST", $options, true);
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $params
     * @param bool $signed
     * @return mixed
     */
    protected function httpRequest($url, $method = "GET", $params = [], $signed = false)
    {
        $curl = curl_init();
        $ts = round((microtime(true) * 1000), 0);
        $params['timestamp'] = $ts;
        $query = http_build_query($params, '', '&');

        if ($signed === true) {
            $base = $this->urlPublic;
            $signature = hash_hmac('sha256', $query, $this->apiSecret);
            $endpoint = $base . $url . '?' . $query . '&signature=' . $signature;
            curl_setopt($curl, CURLOPT_URL, $endpoint);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'X-MBX-APIKEY: ' . $this->apiKey,
            ]);
        }

        curl_setopt($curl, CURLOPT_USERAGENT, "User-Agent: Mozilla/4.0 (compatible; PHP Binance API)");

        if ($method === "POST") {
            curl_setopt($curl, CURLOPT_POST, true);
        }
        if ($method === "DELETE") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        }
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $output = curl_exec($curl);
        curl_close($curl);
        $json = json_decode($output, true);

        return $json;
    }


    //___________________________________________ methods public _______________________________________________

    /**
     * Получает ask
     * @param  double $pair
     * @return double
     */
    public function getAskTop($pair)
    {
        $result = $this->retrieveJSON($this->urlPublic . 'ticker/bookTicker?symbol=' . $pair);
        return $result['askPrice'];
    }

    /**
     * Получает bid
     * @param   double $pair
     * @return  double
     */
    public function getBidTop($pair)
    {
        $result = $this->retrieveJSON($this->urlPublic . 'ticker/bookTicker?symbol=' . $pair);
        return $result['askPrice'];
    }

    /**
     * Получает ask стакан (валютная пара, глубина)
     * @param   string $pair
     * @param  int $depth
     * @return mixed
     */
    public function getAskDepth($pair, $depth)
    {
        $result = $this->retrieveJSON($this->urlPublic . 'depth?symbol=' . $pair . '&limit=' . $depth);
        $result = $result['asks'];
        return $result;
    }

    /**
     * Получает bid стакан  (валютная пара, глубина)
     * @param   string $pair
     * @param  int $depth
     * @return mixed
     */
    public function getBidDepth($pair, $depth)
    {
        $result = $this->retrieveJSON($this->urlPublic . 'depth?symbol=' . $pair . '&limit=' . $depth);
        $result = $result['bids'];
        return $result;
    }

    /**
     * Получает список торговых пар
     * @return mixed
     */
    public function getTradePairs()
    {
        $resultArr = [];
        $result = $this->retrieveJSON($this->urlPublic . 'ticker/price');
        if (is_array($result) == true) {
            foreach ($result as $key => $element) {
                $value = $result[$key];
                $resultArr[$key] = $value['symbol'];
            }
        }
        return $resultArr;
    }

    /**
     * Получает ask и bid по всем парам за один  запрос
     * @return mixed
     */
    public function getAllAskBid()
    {
        $resultArr = [];
        $result = $this->retrieveJSON($this->urlPublic . 'ticker/bookTicker');
        if (is_array($result)) {
            foreach ($result as $key => $element) {
                $resultArr[$key] = [
                    'symbol' => $element['symbol'],
                    'ask' => $element['askPrice'],
                    'bid' => $element['bidPrice']
                ];
            }
        }
        return $resultArr;
    }

    /**
     * Получает информацию о суточных объёмах по инструменту
     * @return mixed ['symbol', 'volume']
     */
    public function getDailyAmount()
    {
        $resultArr = [];
        $result = $this->retrieveJSON('https://api.binance.com/api/v1/ticker/24hr');

        if (is_array($result)) {
            foreach ($result as $key => $element) {
                if(strripos(substr($element['symbol'],-3), 'BTC') !== false)
                {
                    $resultArr[$key] = [
                        'symbol' => $element['symbol'],
                        'volume' => $element['quoteVolume']
                    ];
                }
                if(strripos(substr($element['symbol'],0,3), 'BTC') !== false)
                {
                    $resultArr[$key] = [
                        'symbol' => $element['symbol'],
                        'volume' => $element['volume']
                    ];
                }
            }
        }

        return $resultArr;
    }
    //___________________________________________ methods private _______________________________________________

    /**
     * Создаёт ордер (объём базовой валюты, валютная пара, тип сделки (buy sell), цена)
     * @param   double $amount
     * @param   string $pair
     * @param   string $direction
     * @param   double $price
     * @return  mixed
     */
    public function createOrder($amount, $pair, $direction, $price)
    {
        $arrResult = ['status' => '', 'OrderID' => '', 'error' => ''];
        $result = $this->order(strtoupper($direction), $pair, $amount, $price, "LIMIT");


        if (!empty($result['orderId'])) {
            $arrResult['OrderID'] = $result['orderId'];
            $arrResult['status'] = true;
        }
        if (!empty($result['msg'])) {
            $arrResult['error'] = $result['msg'];
        }
        return $arrResult;
    }

    /**
     * Отменяет ордер по номеру
     * @param   int $orderId
     * @param   $param = []
     * @return  true/false
     */
    public function cancelOrder($orderId, $param = [])
    {
        return $this->httpRequest("order", "DELETE", [
            "symbol" => $param['pair'],
            "orderId" => $orderId,
        ], true);
    }

    /**
     * Получает балансы по валютам
     * @param   $param = []
     * @return  mixed ["Symbol" => "Total", ...]
     */
    public function getBalances($param = [])
    {
        $arrResult = [];
        $result = $this->httpRequest('account', "GET", [], true);
        if (is_array($result) == true) {
            $result = $result['balances'];
            foreach ($result as $key => $element) {
                if ($element['asset'] != '123' && $element['asset'] != '456') { //заглушка двух ошибок от апи
                    $arrResult[$element['asset']] = $element['free'];
                }
            }
        }
        return $arrResult;
    }

    /**
     * Получает открытые ордера
     * @param   $param = []
     * @return  mixed [['OrderID', 'Symbol', 'Price', 'OrderType'], ...]
     */
    public function returnOpenOrders($param = [])
    {
        $arrResult = [];
        $result = $this->httpRequest('openOrders', "GET", [], true);
        if (is_array($result) == true) {
            foreach ($result as $key => $element) {
                $arrResult[$element['orderId']] = [
                    'OrderID' => $element['orderId'],
                    'Symbol' => $element['symbol'],
                    'Price' => $element['price'],
                    'OrderType' => $element['side']
                ];
            }
        }
        return $arrResult;
    }

    /**
     * получает заказ с помощью id
     * @param  $orderId
     * @param  $param = []
     * @return mixed ['status' => true/false, 'OrderID']
     */
    public function getOrder($orderId, $param = [])
    {
        $arrResult = ['status' => 0, 'OrderID' => ''];
        $result = $this->httpRequest('order', "GET", [
            "orderId" => $orderId,
            "symbol" => $param['symbol']
        ], true);
        $resultGetOrder = 0;
        if (is_array($result) == true) {
            if ($result['status'] === 'NEW') {
                $resultGetOrder = 1;
            }
            $arrResult = ['status' => $resultGetOrder, 'OrderID' => $result['orderId']];
        }

        return $arrResult;
    }
}
