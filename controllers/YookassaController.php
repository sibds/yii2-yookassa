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
        $module = $this->module;

        if (is_null($orderId)) {
            $source = file_get_contents('php://input');
            $requestBody = json_decode($source, true);

            if ($requestBody) {
                try {
                    if ($requestBody['event'] === NotificationEventType::PAYMENT_SUCCEEDED) {
                        $notification = new NotificationSucceeded($requestBody);
                        $payment = $notification->getObject()->toArray(); // \YooKassa\Model\Payment\PaymentInterface

                        $orderId = $payment['metadata']['order_id'];
                    }

                    if (is_null($orderId)) {
                        if (isset($requestBody['type']) && $requestBody['type'] == 'notification' && $requestBody['event'] == 'payment.succeeded') {
                            $orderId = $requestBody['object']['metadata']['order_id'];
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
        if (!is_null($order)) {
            $orderInfo = (new \yii\db\Query())
                ->select(['o.order_info'])
                ->from(['o' => 'order'])
                ->innerJoin(['oe' => 'order_element'], 'o.id = oe.order_id')
                ->innerJoin(['cs' => 'ct_sales'], 'oe.item_id = cs.id')
                ->where(['cs.idorder' => $order])
                ->scalar();
        } else {
            $orderInfo = (new \yii\db\Query())
                ->select(['o.order_info'])
                ->from(['o' => 'order'])
                ->innerJoin(['oe' => 'order_element'], 'o.id = oe.order_id')
                ->innerJoin(['cs' => 'ct_sales'], 'oe.item_id = cs.id')
                ->where(['cs.idorder' => $orderId])
                ->scalar();
        }

        try {
            $response = $client->getPaymentInfo($orderInfo)->toArray();
        } catch (\Exception $e) {
            $response = $e;
            return $this->redirect($module->failUrl);
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
