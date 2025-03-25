<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Validator;

class CreatePostConsumer extends Command
{
    protected $signature = 'rabbitmq:create-post-consume';
    protected $description = 'Consume post messages from RabbitMQ';

    public function handle()
    {
        $connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->queue_declare('post_queue', false, true, false, false);

        echo " [*] Waiting for messages. To exit, press CTRL+C\n";

        $callback = function (AMQPMessage $msg) use ($channel) {
            $data = json_decode($msg->body, true);
      
            echo " [x] Received Create Post Request: " . $data['title'] . "\n";
      
            $validator = Validator::make($data , [ 
                'title' => 'required|string',
                'content' => 'required|string'
            ]);

            if($validator->fails())
            {
                $response = json_encode([
                    'status' => 'failed',
                    'message' => $validator->errors()->first(),
                    'code' => 422
                ]);
        
                $responseMsg = new AMQPMessage($response, [
                    'correlation_id' => $msg->get('correlation_id')
                ]);
        
                $channel->basic_publish($responseMsg, '', $msg->get('reply_to'));
              
                echo "Validation error";

                return;
            }
 
            $post = new Post();

            $post->title = $data['title'];
            
            $post->content = $data['content'];

            $post->user_id = $data['user_id'];

            $post->save();
            
            $response = json_encode([
                'status' => 'Post created successfully',
                'code' => 200
            ]);

            $responseMsg = new AMQPMessage($response, [
                'correlation_id' => $msg->get('correlation_id')
            ]);

            $channel->basic_publish($responseMsg,'' ,$msg->get('reply_to'));
           
            echo " [~] Post Created: " . "\n";
        };

        $channel->basic_consume('post_queue', '', false, true, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }
}

