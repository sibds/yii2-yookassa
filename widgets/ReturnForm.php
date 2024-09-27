<?php

namespace sibds\payment\yookassa\widgets;

use sibds\payment\yookassa\Module;
use yii;
use yii\helpers\Url;

class ReturnForm extends \yii\base\Widget
{
    public $description = '';
    public $orderModel;
    public $autoSend = false;
    public $type;
    public $cart = false;

    public function init()
    {
        return parent::init();
    }

    /**
     * ВОЗВРАТ СРЕДСТВ ОДНОСТАДИЙНОГО ПЛАТЕЖА В ПЛАТЕЖНОМ ШЛЮЗЕ
     *        refund.do
     *
     * ПАРАМЕТРЫ
     *        Название      Тип         Обязательно             Описание
     *        userName      AN..30          да          Логин магазина, полученный при подключении
     *        password      AN..30          да          Пароль магазина, полученный при подключении
     *        orderId       ANS36           да          Номер заказа в платежной системе. Уникален в пределах системы.
     *        amount        N..20           да          Сумма платежа в копейках (или центах)
     *
     * ПАРАМЕТРЫ ОТВЕТА
     *        Название      Тип         Обязательно             Описание
     *      errorCode     N3              Нет             Код ошибки.
     *      errorMessage  AN..512         Нет             Описание ошибки на языке.
     *
     *    Классификация:
     *       Значение               Описание
     *          0           Обработка запроса прошла без системных ошибок
     *          5           Ошибка значение параметра запроса
     *          6           Незарегистрированный OrderId
     *          7           Системная ошибка
     *    
     *    Расшифровка:
     *      Значение                Описание
     *          0           Обработка запроса прошла без системных ошибок
     *          5           Доступ запрещён
     *          5           Пользователь должен сменить свой пароль
     *          5           [orderId] не задан
     *          6           Неверный номер заказа
     *          7           Платёж должен быть в корректном состоянии
     *          7           Неверная сумма депозита (менее одного рубля)
     *          7           Ошибка системы
     */
    public function run()
    {
        if (empty($this->orderModel)) {
            return false;
        }

        /**
         * @var Module
         */
        $module = yii::$app->getModule('yookassa');

        $client = $module->getClient();

        /*
        $data =
            array(
                'userName' => $module->username,
                'password' => $module->password,
                'orderId' => urlencode($this->orderModel->order_info),
                'amount' => urlencode($this->orderModel->getCost() * $module->refundRate) // передача суммы в копейках
            );
        */

        $data = array(
            'payment_id' => urlencode($this->orderModel->order_info),
            'amount' => array(
                'value' => $this->orderModel->getCost(),
                'currency' => 'RUB',
            ),
        );

        if ($module->supportCart) {
            if ($this->cart) {
                $data = array_merge(
                    $data,
                    [
                        'receipt' => array(
                            'customer' => array(
                                'email' => $this->orderModel->email
                            ),
                            'items' => $this->cart,
                            'tax_system_code' => $module->taxSystem,
                        ),
                    ]
                );
            } elseif (!is_null($module->defaulProductName)) {
                $data = array_merge(
                    $data,
                    [
                        'receipt' => array(
                            'customer' => array(
                                'email' => $this->orderModel->email
                            ),
                            'items' =>
                            array(
                                array(
                                    'description' => $module->defaulProductName,
                                    'quantity' => 1.000,
                                    'amount' => array(
                                        'value' => $this->orderModel->getCost(),
                                        'currency' => 'RUB',
                                    ),
                                    'vat_code' => $module->vatCode,
                                    'payment_mode' => $module->paymentMode,
                                    'payment_subject' => $module->paymentSubject,
                                ),
                            ),
                            'tax_system_code' => $module->taxSystem,
                        ),
                    ]
                );
            }
        }

        $idempotenceKey = uniqid('', true);
        $response = $client->createRefund(
            $data,
            $idempotenceKey
        );

        /*    
        if ($this->type === "reverse") {
            unset($data["amount"]);
        }
        */

        //$response = $module->gateway($this->type . '.do', $data);

        if (isset($response['status']) && ($response['status'] === "succeeded")) {
            //echo 'Успех';
            return True;
        } else {
            //echo 'Ошибка #' . $response['errorCode'] . ': ' . $response['errorMessage'];
            return False;
        }
    }
}
