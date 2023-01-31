Simple Sbarbank REST API Client
===============================

# Описание
Компонент позволяющий проводить основне операции по оплате банковской картой через Сбербанк REST API

# Установка

При помощи [composer](https://getcomposer.org/download/):
```
composer require vhar/sberbank
```
# Как использовать
Регистрируем счет в личном кабинете СБ
```php
<?php

use VHar\Sberbank\SBClient;

$config = [
    'shopLogin' => 'ваш-api-логин',
    'shopPassword' => 'ваш-api-пароль',
    'testMode' => 0, // 0 - production, 1 - test
    'sslVerify' => 1 // 0 - игнорировать ошибки SSL сертификата (не делайте так!), 1 - проверять SSL сертификат (по умолчанию), '/path/to/cert.pem' - использовать пользовательский сертификат для проверки
];

$sber = new SBClient($config);

/**
 * В примере показаны только обязательные поля.
 * Описание полей https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:register
 */
$orderData = [
    'orderNumber' => 'MY_ORDER 0000000',
    'amount' => 1000,
    'returnUrl' => 'https://example.com/callback.php',
];
$response = $sber->registerOrder($orderData);

if (isset($response->errorCode) && $response->errorMessage) {
/**
 * Если получили ошибку, то что то делаем
 */
} else {
/**
 * Сохраняем полученный orderId, например в базу.
 * Он понадобится в случае отмены заказа или повторного запроса статуса.
 * Перенаправляем пользователя на форму оплаты
 */
    header('Location: '.$response->formUrl);
    exit;
}
?>
```
Обрабатываем возврат после оплаты
```php
<?php

use VHar\Sberbank\SBClient;

$config = [
    'shopLogin' => 'ваш-api-логин',
    'shopPassword' => 'ваш-api-пароль',
    'testMode' => 0, // 0 - production, 1 - test
    'sslVerify' => 1 // 0 - игнорировать ошибки SSL сертификата (не делайте так!), 1 - проверять SSL сертификат (по умолчанию), '/path/to/cert.pem' - использовать пользовательский сертификат для проверки
];

$sber = new SBClient($config);

/**
 * В примере показано только обязательное поле.
 * Описание полей и кодов возврата
 * https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:getorderstatusextended
 * Tак же вы можете запросить состояние счета в любой момент, указав полученный при регистрации счета orderId
 */
$orderData = [
    'orderId' => $_GET['orderId'] ?? '',
];

$response = $sber->getOrderStatusExtended($orderData);
if (isset($response->errorCode) && $response->errorCode) {
/**
 * Если получили ошибку, то что то делаем
 */
} else {
/**
 * Обрабатываем платеж по результатам значения $response->orderStatus
 * см. https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:getorderstatusextended
 */
}
?>
```
Возврат средств по платежу

```php
<?php

use VHar\Sberbank\SBClient;

$config = [
    'shopLogin' => 'ваш-api-логин',
    'shopPassword' => 'ваш-api-пароль',
    'testMode' => 0, // 0 - production, 1 - test
    'sslVerify' => 1 // 0 - игнорировать ошибки SSL сертификата (не делайте так!), 1 - проверять SSL сертификат (по умолчанию), '/path/to/cert.pem' - использовать пользовательский сертификат для проверки
];

$sber = new SBClient($config);

/**
 * см. https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:refund
 */
$orderData = [
    'orderId' => $orderId, // Полученный от СБ при регистрации счета orderId
    'amount' => $amount, // Сумма возврата.
];
$response = $sber->refundOrder($orderData);
/**
 * делаем чтото на основании полученного errorCode
 * список кодов см. https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:refund
 */
 ?>
 ```
