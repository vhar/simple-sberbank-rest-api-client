<?php
/**
 * Client for working with Sberbank REST API.
 *
 * @package vhar\sberbank
 * @author Vladimir Kharinenkov <vhar@mail.ru>
 * @version 1.0.1
 *
 */

namespace VHar\Sberbank;

use ErrorException;

/**
 * Class SBClient for working with Sberbank REST API.
 * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:start
 */
class SBClient
{
    /**
     * URL to access Production REST requests
     * @var const API_PROD_URL
     */
    const API_PROD_URL = 'https://securepayments.sberbank.ru';

    /**
     * URL to access Test REST requests
     * @var const
     */
    const API_TEST_URL = 'https://3dsec.sberbank.ru';

    /**
     *  Http Client
     * @var Client
     */
    private $client;

    /**
     * Shop login for REST API access
     * @var string
     */
    private $shopLogin;

    /**
     * Shop password for REST API access
     * @var string
     */
    private $shopPassword;

    /**
     * use test or production payment gateway
     * @var int
     */
    private $testMode;

    /**
     * SSL certificate validation
     * @var mixed
     */
    private $sslVerify;

    /**
     * Clients accept an array of constructor parameters.
     *
     * @param array   $config  Associative array of options
     *
     *    Required optiions:
     *    - shopLogin: (string) Shop login for REST API access
     *    - shopPassword: (string) Shop password for REST API access
     *
     *    Additional option:
     *    - sslVerify: mixed
     *                 <ul>0 - Disable validation entirely (don't do this!)</ul>
     *                 <ul>1 - Use the system's CA bundle (this is the default setting)</ul>
     *                 <ul>'/path/to/cert.pem' - Use a custom SSL certificate on disk</ul>
     *    - testMode: (int)
     *                 <ul>0 - production payment gateway.</ul>
     *                 <ul>1 - test payment gateway</ul>
     *                 <b>default is 0</b>
     */
    public function __construct(array $config = [])
    {
        $this->shopLogin = $config['shopLogin'] ?? null;

        if (!$this->shopLogin) {
            throw new ErrorException('Please provide shopLogin');
        }

        $this->shopPassword = $config['shopPassword'] ?? null;

        if (!$this->shopPassword) {
            throw new ErrorException('Please provide shopPassword');
        }

        $this->testMode = $config['testMode'] ?? 0;

        $this->sslVerify = true;

        if (isset($config['sslVerify'])) {
            if ($config['sslVerify'] === 0) {
                $this->sslVerify = false;
            } elseif (is_file($config['sslVerify'])) {
                $this->sslVerify = $config['sslVerify'];
            }
        }

        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $this->getApiUrl(),
            'verify'   => $this->sslVerify,
        ]);
    }

    /**
     * Creating a new order
     *
     * @param array $order Associative array of order options
     *
     *    Required options:
     *    - orderNumber: (string) Order number (identifier) in the shop system.
     *    - amount: (int) The amount of the order in the minimum units of the shop currency (kopecks, cents, etc.).
     *    - returnUrl: (string) URL to redirect the user in case of successful payment.<br>
     *                          The URL must be specified in full, including the used protocol.
     *
     *    Additional options:
     *    - failUrl: (string) URL to redirect the user in case of failed payment.<br>
     *                        The URL must be specified in full, including the used protocol.<br>
     *                        If not specified, then like with a successful payment, the redirect to returnUrl will occur.
     *    - currency: (int) Payment currency code in ISO 4217 format.
     *    - language: (string) Language code in ISO 639-1 format.
     *    - pageView: (string) <ul><i>DESKTOP</i> - for loading pages, the layout of which is intended for display on PC screens.</ul>
     *                         <ul><i>MOBILE</i> - for loading pages, the layout of which is intended for display on tablet or smartphone screens.</ul>
     *    - jsonParams: Associative array of additional options.
     *    - sessionTimeoutSecs: (int) The lifetime of the order in seconds. (default is 1200 sec).
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
        $url = '/payment/rest/register.do?%s';

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

        if (isset($order['jsonParams'])) {
            if (is_array($order['jsonParams'])) {
                $params['jsonParams'] = json_encode($order['jsonParams']);
            }
            else {
                throw new ErrorException('jsonParams must be array');
            }
        }

        foreach($order as $key => $param){
            $params[$key] = $params[$key] ?? $param;
        }

        $request = sprintf($url, http_build_query($params));
        $response = $this->client->request('POST', $request);

        return json_decode($response->getBody());
    }

    /**
     * Check order status
     *
     * @param array $order Associative array of order options
     *
     *    Required options:
     *    - orderId: (string) Order number in the payment system<br>
     *    <b>OR</b>
     *    - orderNumber: (string) Order number (identifier) in the shop system
     *
     *    Additional options:
     *    - language: (string) Language code in ISO 639-1 format
     *
     * @return Object containing the following data:
     *
     *    <i>only the parameters that are significant for the library operation are listed. see the entire list at the link below.</i>
     *    - orderNumber: (string) Order number (identifier) in the shop system
     *    - orderStatus: (int) <ul>0 - the order is registered, but not paid.</ul>
     *                         <ul>1 - the pre-authorized amount has been withheld (for two-stage payments).</ul>
     *                         <ul>2 - full authorization of the order amount has been carried out.</ul>
     *                         <ul>3 - authorization canceled.</ul>
     *                         <ul>4 - a refund operation was performed on the transaction.</ul>
     *                         <ul>5 - authorization is initiated through the access control server of the issuing bank.</ul>
     *                         <ul>6 - authorization rejected.</ul>
     *    - errorCode: (int) Error code. May be absent if the result did not result in an error.
     *                       <ul>1 - <i>[orderId]</i> or <i>[orderNumber]</i> expected.</ul>
     *                       <ul>7 - This transaction is being processed. Please try again later.</ul>
     *    - errorMessage: (string) Description of the error in the language passed in the language parameter in the request.
     *
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:getorderstatusextended
     */
    public function getOrderStatusExtended($order)
    {
        $url = '/payment/rest/getOrderStatusExtended.do?%s';

        if (!isset($order['orderNumber']) && !isset($order['orderId'])) {
            throw new ErrorException('Please provide orderId OR orderNumber');
        }

        if (isset($order['params']) && !is_array($order['params'])) {
            throw new ErrorException('params must be array');
        }

        $params = [
            'userName' => $this->shopLogin,
            'password' => $this->shopPassword,
        ];

        if (isset($order['orderNumber'])) $params['orderNumber'] = $order['orderNumber'];
        if (isset($order['orderId'])) $params['orderId'] = $order['orderId'];
        if (isset($order['language'])) $params['language'] = $order['language'];

        $request = sprintf($url, http_build_query($params));
        $response = $this->client->request('POST', $request);

        return json_decode($response->getBody());
    }

    /**
     * Cancel order payment
     *
     * @param array $order Associative array of order options
     *
     *    Required options:
     *    - orderId: (string) Order number in the payment system
     *
     *    Additional options:
     *    - amount: (int) Partial cancellation amount. Parameter required for partial cancellation.
     *    - jsonParams:  Associative array of additional options
     *    - language: (string) Language code in ISO 639-1 format
     *
     * @return Object containing the following data:
     *    - errorCode: (int) Error code. May be absent if the result did not result in an error.
     *                 <ul>0 - The request was processed without system errors.</ul>
     *                 <ul>5 - Access denied.</ul>
     *                 <ul>5 - The user must change his password.</ul>
     *                 <ul>5 - <i>[orderId]</i> not set.</ul>
     *                 <ul>5 - Unsuccessful.</ul>
     *                 <ul>6 - Invalid order number.</ul>
     *                 <ul>6 - Unregistered orderId.</ul>
     *                 <ul>7 - Invalid operation for the current order status.</ul>
     *                 <ul>7 - System error.</ul>
     *                 <ul>7 - Reversal is impossible.The holding and deposit amounts must be equal for the transaction after the funds are unblocked.</ul>
     *                 <ul>7 - This transaction is being processed. Please try again later.</ul>
     *                 <ul>7 - Reversal is impossible. Reason: incorrect internal values, check the amount of hold, deposit.</ul>
     *                 <ul>7 - Reversal is impossible. The chargeback flag is set for this payment.</ul>
     *    - errorMessage: (string) Description of the error in the language passed in the language parameter in the request.</ul>
     *
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:reverse
     */
    public function reverseOrder($order)
    {
        $url = '/payment/rest/reverse.do?%s';

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

        if (isset($order['language'])) $params['language'] = $order['language'];
        if (isset($order['jsonParams'])) {
            if (is_array($order['jsonParams'])) {
                $params['jsonParams'] = json_encode($order['jsonParams']);
            }
            else {
                throw new ErrorException('jsonParams must be array');
            }
        }

        $request = sprintf($url, http_build_query($params));
        $response = $this->client->request('POST', $request);

        return json_decode($response->getBody());
    }


    /**
     * Refund order payment
     *
     * @param array $order Associative array of order options
     *
     *    Required options:
     *    - orderId: (string) Order number in the payment system
     *    - amount: (int) The amount of the order in the minimum units of the shop currency (kopecks, cents, etc.)
     *
     *    Additional options:
     *    - jsonParams:  Associative array of additional options
     *
     * @return Object containing the following data:
     *    - errorCode: (int) Error code. May be absent if the result did not result in an error.
     *                 <ul>0 - The request was processed without system errors.</ul>
     *                 <ul>5 - Access denied.</ul>
     *                 <ul>5 - The user must change his password.</ul>
     *                 <ul>5 - <i>[orderId]</i> not set.</ul>
     *                 <ul>5 - Invalid amount.</ul>
     *                 <ul>6 - Invalid order number.</ul>
     *                 <ul>7 - The payment must be in the correct state.</ul>
     *                 <ul>7 - Refund amount exceeds the amount debited.</ul>
     *                 <ul>7 - System error.</ul>
     *    - errorMessage: (string) Description of the error in the language passed in the language parameter in the request.
     *
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:refund
     */
    public function refundOrder($order)
    {
        $url = '/payment/rest/refund.do?%s';

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

        if (isset($order['jsonParams'])) {
            if (is_array($order['jsonParams'])) {
                $params['jsonParams'] = json_encode($order['jsonParams']);
            }
            else {
                throw new ErrorException('jsonParams must be array');
            }
        }

        $request = sprintf($url, http_build_query($params));
        $response = $this->client->request('POST', $request);

        return json_decode($response->getBody());
    }

    /**
    * Get API URL based on testMode $config option
    *
    * @return string Production or Test REST API URL
    */
    private function getApiUrl()
    {
        if ($this->testMode) {
            return self::API_TEST_URL;
        } else {
            return self::API_PROD_URL;
        }
    }
}
