<?php

namespace App\Console\Commands;

use App\Models\User;
use Hash;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Validator;

class LoginUserConsumer extends Command
{
    protected $signature = 'rabbitmq:login-consume';
    protected $description = 'Consume user login messages from RabbitMQ';

    public function handle()
    {
        $connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->queue_declare('login_queue', false, true, false, false);

        echo " [*] Waiting for messages. To exit, press CTRL+C\n";

        $callback = function (AMQPMessage $msg) use ($channel) {
            $data = json_decode($msg->body, true);
      
            echo " [x] Received User Login Request: " . $data['email'] . "\n";
      
            $validator = Validator::make($data , [ 
                'email' => 'required|email|exists:users,email',
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
              
                echo "Validation fails";

                return;
            }
 

            $user = User::where('email' , $data['email'])->first();

            if($user && Hash::check($data['password'] , $user->password))
            {
                $token = $user->createToken('auth_token')->plainTextToken;

                $response = json_encode([
                    'status' => 'success',
                    'token' => $token,
                    'code' => 200
                ]);

                $responseMsg = new AMQPMessage($response, [
                    'correlation_id' => $msg->get('correlation_id')
                ]);
    
                $channel->basic_publish($responseMsg,'' ,$msg->get('reply_to'));
               
                echo " [✓] User Login: " . $user->email . "\n";
               
                return;
            }
 
            $response = json_encode([
                'status' => 'authentication failed',
                'code' => 403
            ]);

            $responseMsg = new AMQPMessage($response, [
                'correlation_id' => $msg->get('correlation_id')
            ]);

            $channel->basic_publish($responseMsg,'' ,$msg->get('reply_to'));
           
            echo " [~] Authentication failed: " . $user->email . "\n";
        };

        $channel->basic_consume('login_queue', '', false, true, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }
}
