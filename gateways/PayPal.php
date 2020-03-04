<?php

namespace gateway\gateways;

use extpoint\yii2\exceptions\UnexpectedCaseException;
use gateway\exceptions\InvalidDatabaseStateException;
use gateway\exceptions\RequestAuthenticityException;
use gateway\models\Order;
use yii\web\BadRequestHttpException;

class PayPal extends Base
{

    /**
     * Api url. For developers: https://api.sandbox.paypal.com
     * For production: https://api.paypal.com
     * @var string
     */
    public $apiUrl = 'https://api.paypal.com';

    /**
     * Api url. For developers: https://www.sandbox.paypal.com/cgi-bin/webscr
     * For production: https://www.paypal.com/cgi-bin/webscr
     * @var string
     */
    public $wwwUrl = 'https://www.paypal.com/cgi-bin/webscr';

    /**
     * Api url. For developers: https://ipnpb.sandbox.paypal.com/cgi-bin/webscr
     * For production: https://ipnpb.paypal.com/cgi-bin/webscr
     * @var string
     */
    public $ipnUrl = 'https://ipnpb.paypal.com/cgi-bin/webscr';

    /**
     * Client ID. Example: EOJ2S-Z6OoN_le_KS1d75wsZ6y0SFdVsY9183IvxFyZp
     * @var string
     */
    public $clientId = '';

    /**
     * Secret key. Example: EClusMEUk8e9ihI7ZdVLF5cZ6y0SFdVsY9183IvxFyZp
     * @var string
     */
    public $secretKey = '';

    /**
     * @var string
     */
    public $merchantEmail = '';

    /**
     * @var bool
     */
    public $useLocalCerts = true;

    protected function internalStart($order, $noSaveParams = [])
    {
        return $this->redirectPost($this->wwwUrl, $noSaveParams + [
            // Mandatory
            'cmd' => '_xclick',
            'amount' => $order->gatewayInitialAmount,
            'business' => $this->merchantEmail,
            'currency_code' => 'USD', // TODO: Vary currency
            'item_name' => $order->title,

            // Optional
            'no_shipping' => 1,
            'item_number' => $order->slug, // TODO: Generalize

            'return' => $this->getSuccessUrl($order),
            // WAS: 'rm' => 2, @see https://www.paypal.com/bh/smarthelp/article/how-do-i-use-the-rm-variable-for-website-payments-ts1011
            'cancel_return' => $this->getFailureUrl($order),
            'notify_url' => $this->getCallbackUrl($order),

            'charset' => 'utf-8',
            'lc' => \Yii::$app->language,

            // 'image' => $xxx,

            // Per request
            // 'first_name' => $xxx,
            // 'payer_email' => $xxx,
            // 'contact_phone' => $xxx,

            // Obsolete
            // 'SOLUTIONTYPE' => $xxx,
            // 'LANDINGPAGE' => $xxx,
            // 'NOSHIPPING' => $xxx,
        ]);
    }

    /**
     * Virtual method
     * @param array $post
     * @param Order $order
     */
    protected function verifyNotification($post, $order) {}

    public function callback($logId)
    {
        // Verify request
        $post = $this->verifyIPN();

        // Validate order
        $order = $this->requireOrderByPublicId($post['item_number']);
        if (!$order) {
            throw new InvalidDatabaseStateException('Wrong order number');
        }

        // Pass control to the order-specific instance
        $gateway = $order->getGateway();
        if (!($gateway instanceof PayPal)) {
            throw new UnexpectedCaseException();
        }
        return $gateway->callbackWithOrder($logId, $order, $post);
    }

    /**
     * @param string $logId
     * @param Order $order
     * @param array $post
     * @return string
     * @throws InvalidDatabaseStateException
     */
    protected function callbackWithOrder($logId, $order, $post)
    {
        // TODO: sum, etc
        if ($post['payment_gross'] != $order->initialAmount) throw new InvalidDatabaseStateException('Price mismatch in callback');
        $this->verifyNotification($post, $order);

        // Handle success
        $order->processPaymentReceived($post['txn_id']);

        return '';
    }

    /**
     * Verification Function
     * Sends the incoming post data back to PayPal using the cURL library.
     * @return array
     * @throws BadRequestHttpException
     * @throws RequestAuthenticityException
     * @see https://github.com/paypal/ipn-code-samples/blob/master/php/PaypalIPN.php
     */
    protected function verifyIPN()
    {
        if (!count($_POST)) {
            throw new BadRequestHttpException('Missing POST Data');
        }

        $raw_post_data = file_get_contents('php://input');
        $raw_post_array = explode('&', $raw_post_data);
        $myPost = array();
        foreach ($raw_post_array as $keyval) {
            $keyval = explode('=', $keyval);
            if (count($keyval) == 2) {
                // Since we do not want the plus in the datetime string to be encoded to a space, we manually encode it.
                if ($keyval[0] === 'payment_date') {
                    if (substr_count($keyval[1], '+') === 1) {
                        $keyval[1] = str_replace('+', '%2B', $keyval[1]);
                    }
                }
                $myPost[$keyval[0]] = urldecode($keyval[1]);
            }
        }

        // Build the body of the verification post request, adding the _notify-validate command.
        $req = 'cmd=_notify-validate';
        $get_magic_quotes_exists = false;
        if (function_exists('get_magic_quotes_gpc')) {
            $get_magic_quotes_exists = true;
        }
        foreach ($myPost as $key => $value) {
            if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
                $value = urlencode(stripslashes($value));
            } else {
                $value = urlencode($value);
            }
            $req .= "&$key=$value";
        }

        // Post the data back to PayPal, using curl. Throw exceptions if errors occur.
        $ch = curl_init($this->ipnUrl);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        // This is often required if the server is missing a global cert bundle, or is using an outdated one.
        if ($this->useLocalCerts) {
            curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . "/PayPal.pem");
        }
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'User-Agent: PHP-IPN-Verification-Script',
            'Connection: Close',
        ));
        $res = curl_exec($ch);
        if ( ! ($res)) {
            $errno = curl_errno($ch);
            $errstr = curl_error($ch);
            curl_close($ch);
            throw new RequestAuthenticityException("cURL error: [$errno] $errstr");
        }

        $info = curl_getinfo($ch);
        $http_code = $info['http_code'];
        if ($http_code != 200) {
            throw new RequestAuthenticityException("PayPal responded with http code $http_code");
        }

        curl_close($ch);

        // Check if PayPal verifies the IPN data, and if so, return true.
        if ($res !== 'VERIFIED') {
            throw new RequestAuthenticityException("PayPal has not confirmed the request. Response: $res");
        }

        return $myPost;
    }
}
