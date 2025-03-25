<?php

namespace App\Http\Controllers\V1\Post;

use App\Http\Controllers\Controller;
use App\Services\RabbitMQ\RabbitMQPublisher;
use Illuminate\Http\Request;
use App\Http\Builders\JsonResponseBuilder;

class CreatePostController extends Controller
{
    public function handler(Request $request)
    {
        $publisher = new RabbitMQPublisher();
 
        $response = $publisher->publish([
            'title' => $request->title,
            'content' => $request->content, 
            'user_id' => $request->auth_user['id']
        ] , 'post_queue');

        $publisher->close();
       
        return app(JsonResponseBuilder::class)
            ->build($response,
            $response['code'],
            $response['status'], 
            'Response'
        );
    }
}
