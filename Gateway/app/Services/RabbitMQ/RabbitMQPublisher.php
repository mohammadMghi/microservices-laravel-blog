<?php
namespace App\Services\RabbitMQ;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher {
    private $connection;
    private $channel;
    private $callbackQueue;
    private $response;
    private $corrId;

    public function __construct() {
        $this->connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
        $this->channel = $this->connection->channel();
         
        list($this->callbackQueue, ,) = $this->channel->queue_declare("", false, false, true, false);

        $this->channel->basic_consume($this->callbackQueue, '', false, true, false, false, function ($msg) {
            if ($msg->get('correlation_id') == $this->corrId) {
                $this->response = $msg->body;
            }
        });
    }

    public function publish($data,$route_key) {
        $this->response = null;
        $this->corrId = uniqid();
 
        $msg = new AMQPMessage(
            json_encode($data),
            [
                'correlation_id' => $this->corrId,
                'reply_to' => $this->callbackQueue,
            ]
        );

        $this->channel->basic_publish($msg, '', $route_key);

        while (!$this->response) {
            $this->channel->wait();
        }

        return json_decode($this->response, true);
    }

    public function close() {
        $this->channel->close();
        $this->connection->close();
    }
}