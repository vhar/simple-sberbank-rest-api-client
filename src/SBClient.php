<?php

namespace VHar\Sberbank;

use ErrorException;
use GuzzleHttp\Client;

/**
 * Client for working with Sberbank REST API.
 *
 * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:start
 */

class SBClient
{
    /**
     * Http Client
     *
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $shopLogin;

    /**
     * @var string
     */
    private $shopPassword;

    /**
     *
     * @var int
     */
    private $test_mode = 0;

    /**
     * URLs to access REST requests
     */
    const API_PROD_URL = 'https://securepayments.sberbank.ru';
    const API_TEST_URL = 'https://3dsec.sberbank.ru';

    /**
    * Client constructor.
     */
     public function __construct(array $config = [], Client $client)
    {
        $this->client = $client ?? new GuzzleHttp\Client();

        // Required params
        $this->shopLogin = $config['shopLogin'] ?? $this->shopLogin;
        if (!$this->shopLogin) {
            throw new ErrorException('Please provide shopLogin');
        }
        $this->shopPassword = $config['shopPassword'] ?? $this->shopPassword;
        if (!$this->shopPassword) {
            throw new ErrorException('Please provide shopPassword');
        }

        $this->test_mode = $config['test_mode'] ?? $this->test_mode;

    }

    /**
     * Creating a new order
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:register
     */
    public function registerOrder($order)
    {
        $url = $this->getApiUrl() . '/payment/rest/register.do?%s';

        // Required params
        if (!$order['orderNumber']) {
            throw new ErrorException('Please provide orderNumber');
        }
        if (!$order['amount']) {
            throw new ErrorException('Please provide amount');
        }
        if (!$order['returnUrl']) {
            throw new ErrorException('Please provide returnUrl');
        }

        $params = [
            'userName' => $this->shopLogin,
            'password' => $this->shopPassword,
            'orderNumber' => $order['orderNumber'],
            'amount' => $order['amount'],
            'returnUrl' => $order['returnUrl'],
        ];

        if ($order['failUrl']) $params['failUrl'] = $order['failUrl'];
        if ($order['currency']) $params['currency'] = $order['currency'];
        if ($order['language']) $params['language'] = $order['language'];
        if ($order['pageView']) $params['pageView'] = $order['pageView'];
        if (isset($order['params']) && !is_array($order['params'])) {
            throw new ErrorException('params must be array');
        }
        if ($order['params']) $params['params'] = json_encode($order['params']);
        if ($order['sessionTimeoutSecs']) $params['sessionTimeoutSecs'] = $order['sessionTimeoutSecs'];

        $request = sprintf($url, http_build_query($params));
        $response = $this->client->request('GET', $request);

        return json_decode($response->getBody());
    }

    /**
     * Check order status
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:getorderstatusextended
     */
    public function getOrderStatusExtended($order)
    {
        $url = $this->getApiUrl() . '/payment/rest/getOrderStatusExtended.do?%s';

        // Required params
        if (!$order['orderNumber'] && !$order['orderId']) {
            throw new ErrorException('Please provide orderId OR orderNumber');
        }

        if (isset($order['params']) && !is_array($order['params'])) {
            throw new ErrorException('params must be array');
        }

        $params = [
            'userName' => $this->shopLogin,
            'password' => $this->shopPassword,
        ];

        if ($order['orderNumber']) $params['orderNumber'] = $order['orderNumber'];
        if ($order['orderId']) $params['orderId'] = $order['orderId'];
        if ($order['language']) $params['language'] = $order['language'];

        $request = sprintf($url, http_build_query($params));
        $response = $this->client->request('GET', $request);

        return json_decode($response->getBody());
    }

    /**
     * Cancel order payment
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:reverse
     */
    public function reverseOrder($order)
    {
        $url = $this->getApiUrl() . '/payment/rest/reverse.do?%s';

        if (!$order['amount']) {
            throw new ErrorException('Please provide amount');
        }
        if (!$order['orderId']) {
            throw new ErrorException('Please provide orderId');
        }

        $params = [
            'userName' => $this->shopLogin,
            'password' => $this->shopPassword,
            'amount' => $order['amount'],
            'orderId' => $order['orderId'],
        ];

        if ($order['language']) $params['language'] = $order['language'];
        if ($order['params']) $params['params'] = json_encode($order['params']);

        $request = sprintf($url, http_build_query($params));
        $response = $this->client->request('GET', $request);

        return json_decode($response->getBody());
    }


    /**
     * Refund order payment
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:refund
     */
    public function refundOrder($order)
    {
        $url = $this->getApiUrl() . '/payment/rest/refund.do?%s';

        if (!$order['amount']) {
            throw new ErrorException('Please provide amount');
        }
        if (!$order['orderId']) {
            throw new ErrorException('Please provide orderId');
        }

        $params = [
            'userName' => $this->shopLogin,
            'password' => $this->shopPassword,
            'amount' => $order['amount'],
            'orderId' => $order['orderId'],
        ];

        if ($order['params']) $params['params'] = json_encode($order['params']);

        $request = sprintf($url, http_build_query($params));
        $response = $this->client->request('GET', $request);

        return json_decode($response->getBody());
    }

    private function getApiUrl()
    {
        if ($this->test_mode) {
            return self::API_TEST_URL;
        } else {
            return self::API_PROD_URL;
        }
    }
}
