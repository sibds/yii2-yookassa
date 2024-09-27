<?php

namespace sibds\payment\yookassa\widgets;

use yii;
use yii\helpers\Url;
use yii\base\Widget;

class PaymentForm extends Widget
{
    public $description = '';
    public $orderModel;
    public $autoSend = false;
    public $cart = false;

    public function init()
    {
        return parent::init();
    }

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

        $id = is_null($module->getId) ? $this->orderModel->getId() : (is_callable($module->getId) ? call_user_func($module->getId, [$this->orderModel]) : $module->getId);

        $data = array(
            'amount' => array(
                'value' => $this->orderModel->getCost(),
                'currency' => 'RUB',
            ),
            'confirmation' => array(
                'type' => 'redirect',
                'return_url' => Url::toRoute(['/yookassa/yookassa/result', 'order' => urlencode($id)], true)
            ),
            'capture' => true,
            'description' => 'Заказ №' . $id,
            'metadata' => array(
                'order_id' => (string)$id,
            )
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

        $payment = $client->createPayment(
            $data,
            $idempotenceKey
        );

        if ($payment->getStatus() == 'pending') {
            $this->orderModel->setPaymentStatus('no');

            $this->orderModel->order_info = $payment->id;
            $this->orderModel->save();
            if (!$this->autoSend) {
                return $payment->confirmation->confirmationUrl;
            }
            header('Location: ' . $payment->confirmation->confirmationUrl);
            die();
        } elseif ($payment->getStatus() == 'succeeded ') {
            $this->orderModel->setPaymentStatus('yes');

            $this->orderModel->order_info = $payment->id;
            $this->orderModel->save();
            if (!$this->autoSend) {
                return $payment->confirmation->confirmationUrl;
            }
            header('Location: ' . $payment->confirmation->confirmationUrl);
            die();
        } else {
            if (!$this->autoSend) {
                return Url::toRoute([$module->failUrl], true);
            }
            header('Location: ' . Url::toRoute([$module->failUrl], true));
            //echo 'Ошибка #' . $response['errorCode'] . ': ' . $response['errorMessage'];
            die();
        }
        /*
        $data = array(
            'orderNumber' => urlencode($id),
            'amount' => urlencode($this->orderModel->getCost() * 100), // передача данных в копейках/центах
            'returnUrl' => Url::toRoute(['/sberbank/sberbank/result', 'order' => urlencode($id)], true),
            //'failUrl' => Url::toRoute([$module->failUrl], true),
        );

        if(!is_null($module->sessionTimeout)){
            $data['sessionTimeoutSecs'] = $module->sessionTimeout;
        }
        */
        if ($module->supportCart && $this->cart) {
            $data['orderBundle'] = [];
            $data['taxSystem'] = $module->taxSystem;
            $data['orderBundle']['orderCreationDate'] = date('c');

            if (strlen($this->orderModel->client_name) > 9) {
                $data['orderBundle']['customerDetails']['fullName'] = $this->orderModel->client_name;
            }

            if ($this->orderModel->email != '') {
                $data['orderBundle']['customerDetails']['email'] = $this->orderModel->email;
            } elseif ($this->orderModel->phone != '') {
                $data['orderBundle']['customerDetails']['phone'] = $this->orderModel->phone;
            }
            //$data['orderBundle']['additionalOfdParams'] = [];

            $data['orderBundle']['cartItems']['items'] = $this->cart;
            $data['orderBundle'] = json_encode($data['orderBundle']);
        }

        if (!is_null($module->getDescription)) {
            if (is_callable($module->getDescription)) {
                $data['description'] = call_user_func($module->getDescription, [$this->orderModel]);
            } else {
                $data['description'] = $module->getDescription;
            }
        }

        //var_dump($data);


        /**
         * ЗАПРОС РЕГИСТРАЦИИ ОДНОСТАДИЙНОГО ПЛАТЕЖА В ПЛАТЕЖНОМ ШЛЮЗЕ
         *        register.do
         *
         * ПАРАМЕТРЫ
         *        userName            Логин магазина.
         *        password            Пароль магазина.
         *        orderNumber            Уникальный идентификатор заказа в магазине.
         *        amount                Сумма заказа.
         *        returnUrl            Адрес, на который надо перенаправить пользователя в случае успешной оплаты.
         *
         * ОТВЕТ
         *        В случае ошибки:
         *            errorCode        Код ошибки. Список возможных значений приведен в таблице ниже.
         *            errorMessage    Описание ошибки.
         *
         *        В случае успешной регистрации:
         *            orderId            Номер заказа в платежной системе. Уникален в пределах системы.
         *            formUrl            URL платежной формы, на который надо перенаправить браузер клиента.
         *
         *    Код ошибки        Описание
         *        0            Обработка запроса прошла без системных ошибок.
         *        1            Заказ с таким номером уже зарегистрирован в системе.
         *        3            Неизвестная (запрещенная) валюта.
         *        4            Отсутствует обязательный параметр запроса.
         *        5            Ошибка значения параметра запроса.
         *        7            Системная ошибка.
         */
        //$response = $module->gateway('register.do', $data);

        //var_dump($response);
        //die();

        /**
         * ЗАПРОС РЕГИСТРАЦИИ ДВУХСТАДИЙНОГО ПЛАТЕЖА В ПЛАТЕЖНОМ ШЛЮЗЕ
         *        registerPreAuth.do
         *
         * Параметры и ответ точно такие же, как и в предыдущем методе.
         * Необходимо вызывать либо register.do, либо registerPreAuth.do.
         */
        //	$response = $module->gateway('registerPreAuth.do', $data);

        if (isset($response['errorCode'])) { // В случае ошибки вывести ее
            if (!$this->autoSend) {
                return Url::toRoute([$module->failUrl], true);
            }
            header('Location: ' . Url::toRoute([$module->failUrl], true));
            //echo 'Ошибка #' . $response['errorCode'] . ': ' . $response['errorMessage'];
            die();
        } else { // В случае успеха перенаправить пользователя на плетжную форму
            $this->orderModel->order_info = $response['orderId'];
            $this->orderModel->save();
            if (!$this->autoSend) {
                return $response['formUrl'];
            }
            header('Location: ' . $response['formUrl']);
            die();
        }
    }
}
