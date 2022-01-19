<?php

class Iiko
{
    private $modx;
    private $config;

    public function __construct($modx, $config = [])
    {
        $this->modx = $modx;
        $this->config = $config;
    }

    public function process($msOrder)
    {
        // get token
        $params = ['apiLogin' => $this->config['apiLogin']];
        $tokenJson = $this->getResponse('api/1/access_token', $params);

        if ($tokenJson) {
            $response_arr = json_decode($tokenJson, true);

            if ($response_arr['token']) {
                $token = $response_arr['token'];
            } else {
                $this->modx->log(1, '[iiko]: Error to get token ' . print_r($response_arr, 1));
                return;
            }
        }


        // если нет токена, то ничего и не отправляем
        if ($this->config['token']) {
            $data = [];
            $customer = [];
            $products = [];
            $address = [];

            $orderData = [
                'order' => $msOrder->toArray(),
                'delivery' => $msOrder->Delivery->toArray(),
                'payment' => $msOrder->Payment->toArray(),
                'address' => $msOrder->Address->toArray(),
                'user' => $msOrder->User->toArray(),
                'user_profile' => $msOrder->UserProfile->toArray(),
            ];

            // получаем данные покупателя
            $customer['name'] = $orderData['address']['receiver'];
            $customer['email'] = $orderData['user_profile']['email'];
            $data['customer'] = $customer;


            // получаем телефон заказчика
            // Телефон должен передаваться с кодом страны
            $data['phone'] = '+7' . $orderData['address']['phone'];
            $data['phone'] = str_replace('+7+7', '+7', $data['phone']);


            // уникальный идентификатор, чтобы отличать в системе заказы
            $data['sourceKey'] = 'Site online';


            // информация о способе оплаты
            $payment_id = $orderData['order']['payment'];
            switch ($payment_id) {
                case 1:
                    $paymentType = 'Cash'; // наличка
                    $paymentTypeId = 'xxxxxx-xxxxxxx-xxx-xxxxxx-xx';// получаем один раз через api
                    $payTitle = 'Наличные';
                    break;
                case 3:
                    $paymentType = 'Card'; // карта курьеру
                    $paymentTypeId = 'exxxxxx-xxxxxxx-xxx-xxxxxx-xx';// получаем один раз через api
                    $payTitle = 'Картой курьеру';
                    break;
                case 4:
                    $paymentType = 'Card'; // онлайн оплата
                    $paymentTypeId = 'xxxxxx-xxxxxxx-xxx-xxxxxx-xx';// получаем один раз через api
                    $payTitle = 'Онлайн оплата на сайте';
                    break;
            }


            //
            $data['payments'][] = [
                'paymentTypeKind' => $paymentType,
                'sum' => $orderData['order']['cost'],
                'paymentTypeId' => $paymentTypeId
            ];

            // товары заказа
            if ($orderProducts = $msOrder->getMany('Products')) {
                $i = 0;
                foreach ($orderProducts as $orderProduct) {
                    if ($fields = $this->modx->getObject('msProductData', array('id' => $orderProduct->get('product_id')))) {
                        $fields_array = $fields->toArray();
                        if ($fields_array['iiko_product_id']) {
                            $products[$i]['productId'] = $fields_array['iiko_product_id'];
                            $products[$i]['price'] = $orderProduct->get('price');
                            $products[$i]['amount'] = $orderProduct->get('count');
                            $products[$i]['type'] = 'Product';
                            $i++;
                        }
                    }
                }
            }


            if ($orderData['delivery']['id'] > 1) {
                // если один город для доставки, то город может быть не обязателен
                $street = [
                    'name' => $orderData['address']['street'] ?: 'Не указана',
                    //'city' => $orderData['address']['city'] ?: 'Не указан'
                ];

                $address = [
                    'street' => $street, // улица - массив
                    'house' => $orderData['address']['building'] ?: '-', // номер дома
                    'flat' => $orderData['address']['room'] ?: '-', // номер квартиры
                    'entrance' => $orderData['address']['entrance'] ?: '-' // подъезд
                ];

                $data['deliveryPoint'] = [
                    //'coordinates' => $coordinates,
                    'address' => $address
                ];
            }

            $deliveryInfo = false;
            $orderServiceType = false;
            $has_delivery = false;

            switch ($orderData['delivery']['id']) {
                case 1:
                    $deliveryInfo = 'Доставка: Самовывоз';
                    $orderServiceType = 'DeliveryByClient'; // Важно - отличается от курьерской доставки
                    $delivery_id = 'xxxxxx-xxxxxxx-xxx-xxxxxx-xx'; // получаем один раз через api
                    $delivery_cost = 100;
                    break;
                case 2:
                    $deliveryInfo = 'Доставка: Предзаказ на время ' . $orderData['address']['metro'];
                    $orderServiceType = 'DeliveryByCourier';
                    $delivery_id = 'xxxxxx-xxxxxxx-xxx-xxxxxx-xx'; // получаем один раз через api
                    $delivery_cost = 100;
                    break;
                case 3:
                    $deliveryInfo = 'Доставка: Доставка по адресу';
                    $orderServiceType = 'DeliveryByCourier';
                    $delivery_id = 'xxxxxx-xxxxxxx-xxx-xxxxxx-xx'; // получаем один раз через api
                    $delivery_cost = 100;
                    break;
            }

            // оказалось что доставка тоже является товаром
            // добавить доставку - товар
            $products[$i] = [
                'productId' => $delivery_id,
                'price' => $delivery_cost,
                'amount' => 1,
                'type' => 'Product'
            ];

            if (count($products) > 0) {
                $data['items'] = $products;
            }

            $data['comment'] = $orderData['address']['comment'] . " Способ оплаты: " . $payTitle . ". " . $deliveryInfo;
            $data['sum'] = $orderData['order']['cost'];


            $data['orderServiceType'] = $orderServiceType;


            // передаем заказ в iiko
            $params = [
                'organizationId' => $this->config['organizationId'],
                'terminalGroupId' => $this->config['terminalGroupId'],
                'order' => $data
            ];


            //$modx->log(1,'iiko params  --- '.print_r(json_encode($params), 1));
            $createOrderJson = $this->getResponse('api/1/deliveries/create', $params, true, $token);

            // логируем в отдельный файл все запросы для дальнейшей отладки
            $this->modx->log(1, 'Отправленные параметры: ' . json_encode($params), [
                "target" => "FILE",
                "options" => [
                    "filename" => "iiko.log", // Имя файла
                ]
            ]);


            // логируем в отдельный файл все ответы для дальнейшей отладки
            $this->modx->log(1, 'Полученный ответ: ' . $createOrderJson, [
                "target" => "FILE",
                "options" => [
                    "filename" => "iiko.log", // Имя файла
                ]
            ]);
        }
    }

    private function getResponse($action, $params, $auth = false, $token = '')
    {
        $url = 'https://api-ru.iiko.services/' . $action;
        if ($auth) {
            $auth = 'Authorization: Bearer ' . $token;
        } else {
            $auth = '';
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                $auth
            ],
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
