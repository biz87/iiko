<?php

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
