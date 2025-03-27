<?php

namespace App\Http\Controllers\V1\Post;

use App\Http\Controllers\Controller;
use App\Services\RabbitMQ\RabbitMQPublisher;
use Illuminate\Http\Request;
use App\Http\Builders\JsonResponseBuilder;

class DeletePostController extends Controller
{
    public function handler(Request $request,$id)
    {
        $publisher = new RabbitMQPublisher();
 
        $response = $publisher->publish([
            'id' => $id,
            'user_id' => $request->auth_user['id'], 
        ] , 'delete_post_queue');

        $publisher->close();
       
        return app(JsonResponseBuilder::class)
            ->build($response,
            $response['code'],
            $response['status'], 
            'Response'
        );
    }
}
