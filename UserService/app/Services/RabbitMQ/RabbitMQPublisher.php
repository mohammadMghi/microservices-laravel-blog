<?php
namespace App\Services\RabbitMQ;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    protected $connection;
    protected $channel;
    protected $queue;

    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST'),
            env('RABBITMQ_PORT'),
            env('RABBITMQ_USER'),
            env('RABBITMQ_PASSWORD')
        );

        $this->channel = $this->connection->channel();
        $this->queue = env('RABBITMQ_QUEUE', 'default');

        $this->channel->queue_declare($this->queue, false, true, false, false);
    }

    public function publish($data)
    {
        $msg = new AMQPMessage(json_encode($data), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $this->channel->basic_publish($msg, '', $this->queue);
    }

    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
