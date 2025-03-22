<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\RabbitMQ\RabbitMQPublisher;
use Illuminate\Http\Request;
use App\Http\Builders\JsonResponseBuilder;
class RegisterController extends Controller
{
    public function handler(Request $request)
    { 
        $publisher = new RabbitMQPublisher();

        $response = $publisher->publish([
            'name' => $request->name,
            'email' => $request->email ,
            'password' => $request->password
        ] , 'register_queue');

        $publisher->close();
       
        return app(JsonResponseBuilder::class)
            ->build($response,
            $response['code'],
            $response['status'], 
            'Response'
            );
    }
}
