<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\RabbitMQ\RabbitMQPublisher;
use Illuminate\Http\Request;
use App\Http\Builders\JsonResponseBuilder;
class LoginController extends Controller
{
    public function handler(Request $request)
    { 
        $publisher = new RabbitMQPublisher();

        $response = $publisher->publish([ 
            'email' => $request->email ,
            'password' => $request->password
        ] , 'login_queue');

        $publisher->close();
       
        return app(JsonResponseBuilder::class)
            ->build($response,
            $response['code'],
            $response['status'], 
            'Response'
            );
    }
}
