<?php

declare(strict_types=1);

/*
 * Fake hypervisor console WebSocket for WebSocketProxy tests.
 * Usage: php ws_upstream_server.php <port-file>
 * Serves one client: checks the API token header, the path and the "binary"
 * subprotocol, completes the handshake (plus one pending server frame), then
 * answers every masked client frame with an unmasked frame "echo:<payload>".
 */

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, $errstr);
    exit(1);
}
$name = (string) stream_socket_get_name($server, false);
file_put_contents($argv[1], substr($name, strrpos($name, ':') + 1));

$client = stream_socket_accept($server, 15);
if ($client === false) {
    exit(1);
}

$request = '';
while (!str_contains($request, "\r\n\r\n") && ($chunk = fread($client, 4096)) !== false && $chunk !== '') {
    $request .= $chunk;
}
preg_match('/^Sec-WebSocket-Key: (\S+)\r$/mi', $request, $key);
$valid = str_contains($request, "Authorization: PVEAPIToken=nebrix@pve!panel=secret-token\r\n")
    && preg_match('#^GET /api2/json/nodes/pve1/qemu/101/vncwebsocket\?port=5900&vncticket=PVEVNC%3ATICKET HTTP/1\.1\r$#m', $request) === 1
    && preg_match('/^Sec-WebSocket-Protocol: binary\r$/mi', $request) === 1
    && isset($key[1]);
if (!$valid) {
    fwrite($client, "HTTP/1.1 401 Unauthorized\r\nContent-Length: 0\r\n\r\n");
    exit(0);
}

$accept = base64_encode(sha1($key[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
// The RFB greeting arrives right after the handshake (same TCP segment).
fwrite($client, "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {$accept}\r\nSec-WebSocket-Protocol: binary\r\n\r\n" . chr(0x82) . chr(12) . 'RFB 003.008' . "\n");

$buffer = '';
while (($chunk = fread($client, 4096)) !== false && $chunk !== '') {
    $buffer .= $chunk;
    while (strlen($buffer) >= 6) {
        $length = ord($buffer[1]) & 0x7F;
        if ((ord($buffer[1]) & 0x80) === 0 || $length > 125 || strlen($buffer) < 6 + $length) {
            break;
        }
        $mask = substr($buffer, 2, 4);
        $payload = '';
        for ($i = 0; $i < $length; $i++) {
            $payload .= $buffer[6 + $i] ^ $mask[$i % 4];
        }
        $buffer = substr($buffer, 6 + $length);
        $reply = 'echo:' . $payload;
        fwrite($client, chr(0x82) . chr(strlen($reply)) . $reply);
    }
}
