<?php

namespace App\Http\Builders\Contracts;

interface ResponseBuilderInterface
{
    public function build($data, int $status, string $code, string $message, $header = [], $meta = []);
}
