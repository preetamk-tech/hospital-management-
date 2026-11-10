#!/bin/bash
cd /Applications/XAMPP/xamppfiles/htdocs/hospital
echo "Starting Hospital WebSocket Server on ws://localhost:8080..."
echo "Press Ctrl+C to stop."
php websocket/server.php
