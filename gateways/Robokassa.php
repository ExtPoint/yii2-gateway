<?php

namespace gateway\gateways;

use gateway\exceptions\InvalidArgumentException;
use gateway\exceptions\SignatureMismatchRequestException;
use yii\web\Response;

class Robokassa extends Base
{

    /**
     * @var string
     */
    public $login;

    /**
     * @var string
     */
    public $password1;

    /**
     * @var string
     */
    public $password2;

    /**
     * @var string
     */
    public $url;

	/**
	 * Способ оплаты
	 * @var string
	 */
	public $paymentMethod;

	protected function internalStart($order, $noSaveParams = [])
	{
		// Additional params
		$shpParams = [];
		$shpSignature = '';
		foreach ($order->gatewayParams + $noSaveParams as $key => $value) {
			$shpParams['Shp_' . $key] = $value;
			$shpSignature .= ':Shp_' . $key . '=' . $value;
		}

		// Remote url
		$url = $this->url ?: ($this->testMode ? 'http://test.robokassa.ru/Index.aspx' : 'http://auth.robokassa.ru/Merchant/Index.aspx');

		$amount = sprintf('%.2f', $order->gatewayInitialAmount);
			
		return $this->redirect($url . '&' . 
			http_build_query(array_merge($shpParams, [
				'MrchLogin' => $this->login,
				'OutSum' => $amount,
				'InvId' => $order->id,
				'Desc' => $order->description,
				'SignatureValue' => md5($this->login . ":" . $amount . ":" . $order->id . ":" . $this->password1 . $shpSignature),
				'IncCurrLabel' => $this->paymentMethod,
				'Culture' => 'ru',
				'Encoding' => 'utf-8',
			]))
		);		
	}

	/**
	 * @param int $logId
	 * @return Response|string|mixed
     * @throws InvalidArgumentException
     * @throws SignatureMismatchRequestException
     */
    public function callback($logId)
    {
    	$post = \Yii::$app->request->post();
    	
        // Check required params
        if (empty($post['InvId']) || empty($post['SignatureValue'])) {
            throw new InvalidArgumentException('Invalid request arguments. Need `InvId` and `SignatureValue`.');
        }

        // Find order
        $order = $this->getOrderById($post['InvId']);

        // Generate hash sum
        $md5 = strtoupper(md5($post['OutSum'] . ':' . $order->id . ':' . $this->password2));
        $remoteMD5 = $post['SignatureValue'];

        // Check md5 hash
        if ($md5 !== $remoteMD5) {
            throw new SignatureMismatchRequestException();
        }

        // Send success result
        return 'OK' . $order->id;
    }

}