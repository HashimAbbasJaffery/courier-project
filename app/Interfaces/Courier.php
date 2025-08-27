<?php

namespace App\Interfaces;

interface Courier
{
    public function createOrder(array $data);
    public function cancelOrder(String $trackingNumber);
    public function track(String $trackingNumber);
    public function getCities();
}
