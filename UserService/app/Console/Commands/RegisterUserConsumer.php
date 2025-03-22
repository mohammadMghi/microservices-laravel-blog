<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Validator;

class RegisterUserConsumer extends Command
{
    protected $signature = 'rabbitmq:register-consume';
    protected $description = 'Consume user registration messages from RabbitMQ';

    public function handle()
    {
        $connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->queue_declare('register_queue', false, true, false, false);

        echo " [*] Waiting for messages. To exit, press CTRL+C\n";

        $callback = function (AMQPMessage $msg) use ($channel) {
            $data = json_decode($msg->body, true);
      
            echo " [x] Received User Registration Request: " . $data['email'] . "\n";
      
            $validator = Validator::make($data , [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8'
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
 

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], 
            ]);

            $response = json_encode([
                'status' => 'success',
                'message' => 'User created successfully',
                'code' => 200
            ]);

            $responseMsg = new AMQPMessage($response, [
                'correlation_id' => $msg->get('correlation_id')
            ]);

            $channel->basic_publish($responseMsg,'' ,$msg->get('reply_to'));

            echo " [✓] User Registered: " . $user->email . "\n";
        };

        $channel->basic_consume('register_queue', '', false, true, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }
}
