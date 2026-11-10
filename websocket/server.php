<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class HospitalWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $userConnections = [];
    protected $roomConnections = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['action'])) return;
        
        switch ($data['action']) {
            case 'subscribe':
                $userId = $data['user_id'] ?? null;
                $role = $data['role'] ?? null;
                if ($userId) {
                    $this->userConnections[$userId] = $from->resourceId;
                    $from->send(json_encode(['type' => 'subscribed', 'user_id' => $userId]));
                }
                break;
                
            case 'token_update':
                $this->broadcastToUser($data['user_id'] ?? null, [
                    'type' => 'token_update',
                    'token_number' => $data['token_number'] ?? '',
                    'status' => $data['status'] ?? '',
                    'position' => $data['position'] ?? 0,
                    'people_ahead' => $data['people_ahead'] ?? 0,
                    'estimated_wait' => $data['estimated_wait'] ?? 0
                ]);
                break;
                
            case 'notification':
                $this->broadcastToUser($data['user_id'] ?? null, [
                    'type' => 'notification',
                    'title' => $data['title'] ?? '',
                    'message' => $data['message'] ?? '',
                    'notification_type' => $data['notification_type'] ?? 'info'
                ]);
                break;
                
            case 'ambulance_update':
                $this->broadcastToUser($data['user_id'] ?? null, [
                    'type' => 'ambulance_update',
                    'booking_id' => $data['booking_id'] ?? 0,
                    'status' => $data['status'] ?? '',
                    'lat' => $data['lat'] ?? 0,
                    'lng' => $data['lng'] ?? 0
                ]);
                break;
                
            case 'queue_update':
                $this->broadcastAll([
                    'type' => 'queue_update',
                    'tokens' => $data['tokens'] ?? []
                ]);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function broadcastToUser($userId, $data) {
        if ($userId && isset($this->userConnections[$userId])) {
            $resourceId = $this->userConnections[$userId];
            foreach ($this->clients as $client) {
                if ($client->resourceId == $resourceId) {
                    $client->send(json_encode($data));
                }
            }
        }
    }

    protected function broadcastAll($data) {
        foreach ($this->clients as $client) {
            $client->send(json_encode($data));
        }
    }
}

echo "WebSocket server starting on ws://0.0.0.0:8080\n";
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new HospitalWebSocket()
        )
    ),
    8080
);
$server->run();
