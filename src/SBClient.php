<?php
/**
 * Client for working with Sberbank REST API.
 *
 * @package vhar\sberbank
 * @author Vladimir Kharinenkov <vhar@mail.ru>
 * @version 0.1.1
 *
 * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:start
 */

namespace VHar\Sberbank;

use ErrorException;
use GuzzleHttp\Client;

/**
 * Class SBClient
 */
class SBClient
{
    /** @var const URLs to access Production REST requests */
    const API_PROD_URL = 'https://securepayments.sberbank.ru';

    /** @var const URLs to access Test REST requests */
    const API_TEST_URL = 'https://3dsec.sberbank.ru';

    /** @var array Default request options */
    private $config;

    /**  @var Client Http Client */
    private $client;

    /**
     * @param array   $config  Associative array of options
     *    Required optiions:
     *    - shopLogin: (string) Shop login for REST API access
     *    - shopPassword: (string) Shop password for REST API access
     *    Additional option:
     *    - test_mode: (int) 0 - for production, 1 - for test payment gateway (default is 0)
     * @param Client  $client  GuzzleHttp\Client used to send the requests.
     */

    public function __construct(array $config = [], Client $client)
    {
        $this->client = $client ?? new GuzzleHttp\Client();

        $this->shopLogin = $config['shopLogin'] ?? null;
        if (!$this->shopLogin) {
            throw new ErrorException('Please provide shopLogin');
        }
        $this->shopPassword = $config['shopPassword'] ?? null;
        if (!$this->shopPassword) {
            throw new ErrorException('Please provide shopPassword');
        }

        $this->test_mode = $config['test_mode'] ?? 0;
    }

    /**
     * Creating a new order
     *
     * @param array $order Associative array of order options
     *    Required options:
     *    - orderNumber: (string) Order number (identifier) in the shop system
     *    - amount: (int) The amount of the order in the minimum units of the shop currency (kopecks, cents, etc.)
     *    - returnUrl: (string) URL to redirect the user in case of successful payment.
     *                          The URL must be specified in full, including the used protocol
     *    Additional options:
     *    - failUrl: (string) URL to redirect the user in case of failed payment.
     *                        The URL must be specified in full, including the used protocol
     *                        If not specified, then like with a successful payment, the redirect to returnUrl will occur.
     *    - currency: (int) Payment currency code in ISO 4217 format
     *    - language: (string) Language code in ISO 639-1 format
     *    - pageView: (string) DESKTOP - for loading pages, the layout of which is intended for display on PC screens
     *                         MOBILE - for loading pages, the layout of which is intended for display on tablet or smartphone screens
     *    - params: Associative array of additional options
     *    - sessionTimeoutSecs: (int) The lifetime of the order in seconds. (default is 1200 sec)
     *
     * @return Object containing the following data:
     *    - orderId: (string) Order number in the payment system.
     *    - formUrl: (string) Payment form URL to redirect the client's browser to.
     *    - errorCode: (int) Error code. May be absent if the result did not result in an error.
     *    - errorMessage: (string) Description of the error in the language passed in the language parameter in the request.
     *
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:register
     */
    public function registerOrder($order)
    {
        $url = $this->getApiUrl() . '/payment/rest/register.do?%s';

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
     *
     * @param array $order Associative array of order options
     *    Required options:
     *    - orderId: (string) Order number in the payment system
     *   or
     *    - orderNumber: (string) Order number (identifier) in the shop system
     *    Additional options:
     *    - language: (string) Language code in ISO 639-1 format
     *
     * @return Object containing the following data:
     *    <<i>>only the parameters that are significant for the library operation are listed. see the entire list at the link below<</i>>
     *    - orderNumber: (string) Order number (identifier) in the shop system
     *    - orderStatus: (int) 0 - the order is registered, but not paid;
     *                         1 - the pre-authorized amount has been withheld (for two-stage payments);
     *                         2 - full authorization of the order amount has been carried out;
     *                         3 - authorization canceled;
     *                         4 - a refund operation was performed on the transaction;
     *                         5 - authorization is initiated through the access control server of the issuing bank;
     *                         6 - authorization rejected.
     *    - errorCode: (int) Error code. May be absent if the result did not result in an error.
     *    - errorMessage: (string) Description of the error in the language passed in the language parameter in the request.
     *
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
     *
     * @param array $order Associative array of order options
     *    Required options:
     *    - orderId: (string) Order number in the payment system
     *    Additional options:
     *    - amount: (int) Partial cancellation amount. Parameter required for partial cancellation.
     *    - jsonParams:  Associative array of additional options
     *    - language: (string) Language code in ISO 639-1 format
     *
     * @return Object containing the following data:
     *    - errorCode: (int) Error code. May be absent if the result did not result in an error.
     *                 0 The request was processed without system errors.
     *                 5 Access denied.
     *                 5 The user must change his password.
     *                 5 [orderId] not set.
     *                 5 Unsuccessful.
     *                 6 Invalid order number.
     *                 6 Unregistered orderId.
     *                 7 Invalid operation for the current order status.
     *                 7 System error.
     *                 7 Reversal is impossible.The holding and deposit amounts must be equal for the transaction after the funds are unblocked.
     *                 7 This transaction is being processed. Please try again later.
     *                 7 Reversal is impossible. Reason: incorrect internal values, check the amount of hold, deposit.
     *                 7 Reversal is impossible. The chargeback flag is set for this payment.
     *    - errorMessage: (string) Description of the error in the language passed in the language parameter in the request.
     *
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
     *
     * @param array $order Associative array of order options
     *    Required options:
     *    - orderId: (string) Order number in the payment system
     *    - amount: (int) The amount of the order in the minimum units of the shop currency (kopecks, cents, etc.)
     *    Additional options:
     *    - jsonParams:  Associative array of additional options
     *
     * @return Object containing the following data:
     *    - errorCode: (int) Error code. May be absent if the result did not result in an error.
     *                 0 The request was processed without system errors.
     *                 5 Access denied.
     *                 5 The user must change his password.
     *                 5 [orderId] not set.
     *                 5 Invalid amount.
     *                 6 Invalid order number.
     *                 7 The payment must be in the correct state.
     *                 7 Refund amount exceeds the amount debited.
     *                 7 System error.
     *    - errorMessage: (string) Description of the error in the language passed in the language parameter in the request.
     *
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

    /** @return string Test or Production API URL based on test_mode $config option */
    private function getApiUrl()
    {
        if ($this->test_mode) {
            return self::API_TEST_URL;
        } else {
            return self::API_PROD_URL;
        }
    }
}
