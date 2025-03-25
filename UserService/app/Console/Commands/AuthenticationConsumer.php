<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Validator;

class AuthenticationConsumer extends Command
{
    protected $signature = 'rabbitmq:auth-consume';
    protected $description = 'Consume user registration messages from RabbitMQ';

    public function handle()
    {
        $connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->queue_declare('auth_queue', false, true, false, false);

        echo " [*] Waiting for messages. To exit, press CTRL+C\n";

        $callback = function (AMQPMessage $msg) use ($channel) {
            $data = json_decode($msg->body, true);
      
            echo " [x] Received User authentication Request: " . "\n";
        
            $validator = Validator::make($data , [ 
                'token' => 'required|string', 
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
                return;
            }

            $accessToken = PersonalAccessToken::findToken($data['token']);
 
 
            if(!$accessToken)
            {
                $response = json_encode([
                    'status' => 'failed',
                    'message' => 'Authentication failed',
                    'code' => 403
                ]);
    
                $responseMsg = new AMQPMessage($response, [
                    'correlation_id' => $msg->get('correlation_id')
                ]);
    
                $channel->basic_publish($responseMsg,'' ,$msg->get('reply_to'));
    
                return;
            }
                $userArray = $accessToken->tokenable->toArray();
                $response = json_encode([
                    'status' => 'success',
                    'message' => 'Authentication success',
                    'user' => $userArray,
                    'code' => 200
                ]);

                $responseMsg = new AMQPMessage($response, [
                    'correlation_id' => $msg->get('correlation_id')
                ]);

                $channel->basic_publish($responseMsg,'' ,$msg->get('reply_to'));

        
            echo " [✓] User Authenticated: ". "\n";
        };

        $channel->basic_consume('auth_queue', '', false, true, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }
}
