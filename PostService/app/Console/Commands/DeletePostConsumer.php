<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Validator;

class DeletePostConsumer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-post-consumer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->queue_declare('delete_post_queue', false, true, false, false);

        echo " [*] Waiting for messages. To exit, press CTRL+C\n";

        $callback = function (AMQPMessage $msg) use ($channel) {
            $data = json_decode($msg->body, true);
      
            echo " [x] Received DELETE Post Request: " . $data['id'] . "\n";
      
            $validator = Validator::make($data , [ 
                'id' => 'required|exists:posts,id',
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
 
            $post = Post::where('id' , $data['id'])->where('user_id' , $data['user_id'])->first();
 
            $post->delete();
            
            $response = json_encode([
                'status' => 'Post deleted successfully',
                'code' => 200
            ]);

            $responseMsg = new AMQPMessage($response, [
                'correlation_id' => $msg->get('correlation_id')
            ]);

            $channel->basic_publish($responseMsg,'' ,$msg->get('reply_to'));
           
            echo " [~] Post Deleted: " . "\n";
        };

        $channel->basic_consume('delete_post_queue', '', false, true, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }
}
