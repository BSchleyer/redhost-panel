<?php

declare(strict_types=1);

/*
 * Minimal TCP ServerQuery server for SocketTransport tests.
 * Usage: php ts3_socket_server.php <port-file>
 * Binds to a random local port, writes it to <port-file>, serves one client.
 */

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, $errstr);
    exit(1);
}
$name = stream_socket_get_name($server, false);
file_put_contents($argv[1], (string) substr((string) $name, strrpos((string) $name, ':') + 1));

$client = stream_socket_accept($server, 10);
if ($client === false) {
    exit(1);
}

fwrite($client, "TS3\n\rWelcome to the TeamSpeak 3 ServerQuery interface, type \"help\" for a list of commands.\n\r");
while (($line = fgets($client)) !== false) {
    $line = trim($line);
    if (str_starts_with($line, 'login ')) {
        fwrite($client, str_contains($line, 'client_login_password=s3cr\\/et\\sx') ? "error id=0 msg=ok\n\r" : "error id=520 msg=invalid\\sloginname\\sor\\spassword\n\r");
    } elseif ($line === 'version') {
        fwrite($client, "version=3.13.7 build=1 platform=Linux\n\rerror id=0 msg=ok\n\r");
    } elseif ($line === 'serverlist -uid') {
        fwrite($client, "virtualserver_id=1 virtualserver_port=9987 virtualserver_status=online|virtualserver_id=2 virtualserver_port=9988 virtualserver_status=offline\n\rerror id=0 msg=ok\n\r");
    } elseif ($line === 'quit') {
        break;
    } else {
        fwrite($client, "error id=256 msg=command\\snot\\sfound\n\r");
    }
}
fclose($client);
