<?php

namespace gateway\gateways;

use gateway\enums\TransactionKind;
use Yii;
use gateway\exceptions\GatewayException;
use yii\web\Response;

class YandexKassa extends Base
{

    /**
     * Идентификатор магазина в Яндекс.Кассе
     * @var integer
     */
    public $shopId;

    /**
     * Идентификатор витрины магазина в Яндекс.Кассе
     * @var integer
     */
    public $scId;

    /**
     * Секретное слово для Яндекс.Кассы
     * @var string
     */
    public $password;

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
	 * @see https://tech.yandex.ru/money/doc/payment-solution/reference/payment-type-codes-docpage/
     */
    public $defaultPaymentMethod = 'AC'; // AC = Банковская карта

    /**
     * @var string
     */
    public $orderUserIdProperty = 'userId';

    /**
     * @var string|null
     */
    public $orderEmailProperty = null;

    /**
     * @var string|null
     */
    public $orderPhoneProperty = null;


    protected function internalStart($order)
	{
		if (!$order->hasProperty($this->orderUserIdProperty)) {
			throw new GatewayException('User ID attribute is required for gateway "' . __CLASS__ . '".');
		}

		// Remote url
		$url = $this->url ?: ($this->testMode ? 'https://demomoney.yandex.ru/eshop.xml' : 'https://money.yandex.ru/eshop.xml');

		return $this->redirectPost(
			$url,
			[
				'shopId' => $this->shopId,
				'scid' => $this->scId,
				'sum' => number_format((float)$order->gatewayInitialAmount, 2, '.', ''),
				'customerNumber' => $order->{$this->orderUserIdProperty},
				'cps_email' => $this->orderEmailProperty ? $order->{$this->orderEmailProperty} : '',
				'cps_phone' => $this->orderPhoneProperty ? $order->{$this->orderPhoneProperty} : '',
				// See https://tech.yandex.ru/money/doc/payment-solution/reference/payment-type-codes-docpage/
				'paymentType' => $order->gatewayPaymentMethod !== null ? $order->gatewayPaymentMethod : $this->defaultPaymentMethod,
				'orderNumber' => $order->id,
				'shopSuccessURL' => $this->getSuccessUrl($order),
				'shopFailURL' => $this->getFailureUrl($order),
			]
		);
	}

    /**
     * @param int $logId
     * @return Response|mixed
     * @see https://tech.yandex.ru/money/doc/payment-solution/payment-notifications/payment-notifications-check-docpage/
     * @see https://tech.yandex.ru/money/doc/payment-solution/payment-notifications/payment-notifications-aviso-docpage/
     * @see https://tech.yandex.ru/money/doc/payment-solution/payment-notifications/payment-notifications-cancel-docpage/
     */
    public function callback($logId)
    {
        // Check required params
        $requiredParams = [
            'action',
            'orderSumAmount',
            'orderSumCurrencyPaycash',
            'orderSumBankPaycash',
            'invoiceId',
            'customerNumber',
        ];
        
        $post = Yii::$app->request->post();
        if (count(array_intersect_key($post, $requiredParams)) != count($requiredParams)) {
            return $this->getXml(200);
        }

        // Check md5 signature
        $md5 = md5(implode(';', [
            $post['action'],
            $post['orderSumAmount'],
            $post['orderSumCurrencyPaycash'],
            $post['orderSumBankPaycash'],
            $this->shopId,
            $post['invoiceId'],
            $post['customerNumber'],
            $this->password,
        ]));
        $remoteMD5 = strtolower($post['md5']);
        if ($md5 !== $remoteMD5) {
            return $this->getXml(1);
        }

        // Find order
        $order = $this->getOrderById($post['orderNumber']);

        // Validate order sum
        if (abs($order->gatewayInitialAmount - $post['orderSumAmount']) > 0.001) {
        	return $this->getXml(1);
		}

		// Send success
        switch ($post['action']) {
            case 'checkOrder':
            	$this->logTransaction(TransactionKind::ORDER_CHECK, $order->id, $logId, null, $post['orderSumAmount']);
            	return $this->getXml(0);

            case 'paymentAviso':
				$this->logTransaction(TransactionKind::PAYMENT_RECEIVED, $order->id, $logId, null, $post['orderSumAmount']);
				$order->markComplete(); // Because sum is already checked
				return $this->getXml(0);
        }

        // Send problem
        return $this->getXml(200);
    }

    public function getResponseFromException(\Throwable $e)
	{
		return $this->getXml(100);
	}

	protected function getXml($code, $params = [])
    {
    	$post = Yii::$app->request->post();

        $params = array_merge([
            'code' => $code,
            'shopId' => $this->shopId,
            'invoiceId' => isset($post['invoiceId']) ? $post['invoiceId'] : '',
            'performedDatetime' => isset($post['requestDatetime']) ? $post['requestDatetime'] : '',
        ], $params);

        $tagAttributes = [];
        foreach ($params as $name => $value) {
            $tagAttributes[] = sprintf('%s="%s"', $name, $value);
        }

        $tag = (!empty($post['action']) ? $post['action'] : 'checkOrder') . 'Response';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<' . $tag . ' ' . implode(' ', $tagAttributes) . '/>';

		$response = new Response();
		$response->format = Response::FORMAT_RAW;
		$response->headers->set('Content-Type', 'application/xml; charset=' . Yii::$app->charset);
		$response->content = $xml;

        return $response;
    }

}