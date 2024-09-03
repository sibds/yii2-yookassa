<?php

namespace sibds\payment\yookassa\controllers;

use Exception;
use yii;
use yii\web\NotFoundHttpException;
use yii\base\Event;

use YooKassa\Model\Notification\NotificationSucceeded;
use YooKassa\Model\Notification\NotificationWaitingForCapture;
use YooKassa\Model\NotificationEventType;

class YookassaController extends \yii\web\Controller
{
    public function beforeAction($action)
    {
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    function actionResult($order = null, $orderId = null)
    {
        $module = Yii::$app->getComponents();

        if (is_null($orderId)) {
            $source = file_get_contents('php://input');
            $requestBody = json_decode($source, true);

            if ($requestBody) {
                try {
                    if ($requestBody['event'] === NotificationEventType::PAYMENT_SUCCEEDED) {
                        $notification = new NotificationSucceeded($requestBody);
                        $payment = $notification->getObject();

                        $orderId = $payment->getId();
                    }

                    if (is_null($orderId)) {
                        if (isset($requestBody['type']) && $requestBody['type'] == 'notification' && $requestBody['event'] == 'payment.succeeded') {
                            $orderId = $requestBody['object']['id'];
                        }
                    }
                } catch (Exception $e) {
                    // Обработка ошибок при неверных данных
                }
            }
        }

        /** @var dicr\yookassa\YooKassa $yookassa */
        $yooKassa = Yii::$app->get('yookassa');

        /** @var dicr\yookassa\Client $client */
        $client = $yooKassa->client;

        try {
            $response = $client->getPaymentInfo($orderId);
        } catch (\Exception $e) {
            $response = $e;
        }

        if (isset($response['status']) && $response['status'] == 'succeeded') {
            // Используем регулярное выражение для поиска номера заказа
            //preg_match('/№(\d+)/', $response['description'], $matches);

            // Проверяем, был ли найден номер заказа
            if (isset($response['metadata']['order_id'])) {
                $pmOrderId = (int)$response['metadata']['order_id'];
                //$pmOrderId = (int)$response['OrderNumber'];
                $orderModel = $module->orderModel;
                $orderModel = is_null($module->getModel) ? $orderModel::findOne($pmOrderId) : (is_callable($module->getModel) ? call_user_func($module->getModel, [$pmOrderId]) : $orderModel::findOne($module->getModel));
                //$orderModel = $orderModel::findOne($pmOrderId);
                if (!$orderModel) {
                    throw new NotFoundHttpException('The requested order does not exist.');
                }


                $orderModel->setPaymentStatus('yes');
                $orderModel->save(false);

                $event = new Event();
                $event->sender = $orderModel;
                Yii::$app->trigger('successPayment', $event);

                return $this->redirect($module->thanksUrl);
            }
        }

        return $this->redirect($module->failUrl);
    }
}
