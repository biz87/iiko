# Пример создания компонента

1. Созддаем в каталоге core/components/ новый каталог с произвольным названием.
2. В каталоге создаем php файл с таким же названием. 

Некоторые дополнительные правила. 
- Название компонента (каталог и главный файл) - одно слово, только нижний регистр. 
- В главном php файле после наименования - должен быть суффикс .class.php  Это требование MODX


3. Внутри файла  создаем пустой PHP класс с вот таким содержимым

```php
class Iiko
{
    private $modx;
    private $config;

    public function __construct($modx, $config = [])
    {
        $this->modx = $modx;
        $this->config = $config;
    }
}
 
 ```

Компонент готов, к нему уже можно обращаться через метод $modx->getService()

```php
$Iiko = $modx->getService('iiko', 'Iiko', MODX_CORE_PATH . 'components/iiko/', $config);
```

В массиве $config можно передавать произвольные данные, вроде token. 

Далее по коду к $modx  нужно обращаться через $this->modx

Пустого класса конечно недостаточно для работы, нужно перенести туда код. 

Добавляем новую функцию, которая в классах называется методом.  Берем произвольное название
Например process

```php
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
        //сюда переносим весь код из плагина. 
        // К modx обращаемся через $this->modx
        // к конфигу обращаемся тоже через $this->config['token']
        //функцию объявленную в плагине выносим в отдельный метод  к ней обращаемся через $this->getResponse()
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
 
 ```

Плагин сокращается до вот такого вида. Полный код программы можно посмотреть в файлах

```php 
/** @var modX $modx */
if ($modx->event->name === 'msOnCreateOrder') {
    $config = [];
    $config['apiLogin'] = 'xxx-xxxxx-xx'; // выдется заказчиком
    $config['terminalGroupId'] = "xxxxxx-xxxxxxx-xx-xxxxx"; // получаем один раз через api
    $config['city_id'] = "xxxxxx-xxxxxxx-xx-xxxxx"; // получаем один раз через api
    $config['token'] = false;

    $Iiko = $modx->getService('iiko', 'Iiko', MODX_CORE_PATH . 'components/iiko/', $config);
    /** @var msOrder $msOrder */
    $Iiko->process($msOrder);
}

```