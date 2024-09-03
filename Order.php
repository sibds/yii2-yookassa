<?php

namespace sibds\payment\yookassa;

interface Order
{
    public function getId();
    public function getCost();
    public function setPaymentStatus($status);
}
