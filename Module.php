<?php

namespace sibds\payment\yookassa;

use Yii;
use dicr\yookassa\YooKassa;

class Module extends \yii\base\Module
{
    public $testServer = false;
    public $adminRoles = ['admin', 'superadmin'];
    public $thanksUrl = '/main/spasibo-za-zakaz';
    public $failUrl = '/main/problema-s-oplatoy';
    public $currency = 'RUB';

    public $shopId = '';
    public $secretKey = '';
    public $orderModel = 'dvizh\order\models\Order';
    public $getId = null;
    public $getModel = null;
    public $getDescription = null;
    public $sessionTimeout = null; // in seconds
    public $refundRate = 100; // percentage of refund
    public $logCategory = false;
    public $supportCart = false;
    public $taxSystem = 1;

    public function init()
    {
        parent::init();

        // custom initialization code goes here
        //init component for work with yookassa
        $config = null;

        if ($this->testServer) {
            $config = [
                'testServer' => $this->testServer,
                'debug' => true,
                'httpClient' => [
                    'verify' => false,
                ],
            ];
        }

        \Yii::$app->setComponents([
            'yookassa' => [
                'class' => YooKassa::class,
                'shopId' => $this->shopId,
                'secretKey' => $this->secretKey,
                'config' => $config ?? null,
            ],
        ]);
    }

    /**
     * @return dicr\yookassa\Client
     */
    public function getClient()
    {
        /** @var dicr\yookassa\YooKassa $yookassa */
        $yooKassa = Yii::$app->get('yookassa');

        /** @var dicr\yookassa\Client $client */
        return $yooKassa->client;
    }
}
