<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class AuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $connection = new AMQPStreamConnection('127.0.0.1', 5672, 'guest', 'guest');
        $channel = $connection->channel();
 
        list($callbackQueue, ,) = $channel->queue_declare("", false, false, true, false);

        $correlationId = Str::uuid()->toString(); 

        $data = json_encode([
            'token' => $request->bearerToken(),
        ]);

        $msg = new AMQPMessage(
            $data,
            [
                'correlation_id' => $correlationId,
                'reply_to' => $callbackQueue,
            ]
        );

        $channel->basic_publish($msg, '', 'auth_queue');

        $response = null;
        $timeout = 2;  
        $startTime = time();
 
        $callback = function ($message) use (&$response, $correlationId) {
            if ($message->get('correlation_id') === $correlationId) {
                $response = json_decode($message->body, true);
            }
        };

        $channel->basic_consume($callbackQueue, '', false, true, false, false, $callback);
 
        while (!$response && (time() - $startTime) < $timeout) {
            $channel->wait(null, false, 0.5); 
        }

        $channel->close();
        $connection->close();
         
        $request->merge(['auth_user' => $response['user']]);

        if (!$response || $response['status'] !== 'success') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
