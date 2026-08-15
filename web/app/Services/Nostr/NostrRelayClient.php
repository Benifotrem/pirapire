<?php

namespace App\Services\Nostr;

use RuntimeException;

/**
 * Minimal synchronous Nostr relay client (RFC 6455 WebSocket handshake +
 * framing implemented directly over PHP streams — no external WebSocket
 * package) used by App\Services\P2P\Drivers\MostroDriver to read Mostro's
 * order events. Nostr relays only speak NIP-01 over a persistent
 * WebSocket, there's no plain-HTTP query endpoint, which is why this
 * can't just be another Illuminate\Support\Facades\Http call like
 * RoboSatsClient/MempoolClient.
 *
 * One relay, one request/response cycle per call: open the socket, send
 * a REQ for the given filter, collect EVENT messages until EOSE (or the
 * timeout), close. Good enough for polling a handful of relays once a
 * minute — this is not a long-lived relay connection or a general Nostr
 * client (no fragmented-frame reassembly, no ping/pong handling, no
 * NIP-42 auth), only what MostroDriver needs.
 */
class NostrRelayClient
{
    /**
     * @param  array<string, mixed>  $filter  NIP-01 filter (kinds, authors, limit, ...)
     * @return array<int, array<string, mixed>> decoded Nostr event objects
     */
    public function fetchEvents(string $relayUrl, array $filter, int $timeoutSeconds = 8): array
    {
        $socket = $this->connect($relayUrl, $timeoutSeconds);

        try {
            $subscriptionId = bin2hex(random_bytes(8));
            $this->send($socket, json_encode(['REQ', $subscriptionId, $filter]));

            $events = [];
            $deadline = microtime(true) + $timeoutSeconds;

            while (microtime(true) < $deadline) {
                $remaining = max(1, (int) ceil($deadline - microtime(true)));
                $frame = $this->readFrame($socket, $remaining);
                if ($frame === null) {
                    break;
                }

                $message = json_decode($frame, true);
                if (! is_array($message) || ! isset($message[0])) {
                    continue;
                }

                if ($message[0] === 'EVENT' && isset($message[2]) && is_array($message[2])) {
                    $events[] = $message[2];
                } elseif (in_array($message[0], ['EOSE', 'NOTICE', 'CLOSED'], true)) {
                    break;
                }
            }

            $this->send($socket, json_encode(['CLOSE', $subscriptionId]));

            return $events;
        } finally {
            fclose($socket);
        }
    }

    /** @return resource */
    private function connect(string $relayUrl, int $timeoutSeconds)
    {
        $parts = parse_url($relayUrl);
        $scheme = $parts['scheme'] ?? null;
        if (! $parts || ! in_array($scheme, ['ws', 'wss'], true) || empty($parts['host'])) {
            throw new RuntimeException("Invalid Nostr relay URL: {$relayUrl}");
        }

        $secure = $scheme === 'wss';
        $host = $parts['host'];
        $port = $parts['port'] ?? ($secure ? 443 : 80);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $socket = @stream_socket_client(
            ($secure ? 'ssl' : 'tcp')."://{$host}:{$port}",
            $errno,
            $errstr,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            $context,
        );
        if ($socket === false) {
            throw new RuntimeException("Could not connect to Nostr relay {$relayUrl}: {$errstr}");
        }
        stream_set_timeout($socket, $timeoutSeconds);

        $key = base64_encode(random_bytes(16));
        fwrite($socket, implode("\r\n", [
            "GET {$path} HTTP/1.1",
            "Host: {$host}",
            'Upgrade: websocket',
            'Connection: Upgrade',
            "Sec-WebSocket-Key: {$key}",
            'Sec-WebSocket-Version: 13',
            '',
            '',
        ]));

        $statusLine = fgets($socket);
        if (! $statusLine || ! str_contains($statusLine, '101')) {
            fclose($socket);
            throw new RuntimeException("Nostr relay {$relayUrl} refused the WebSocket upgrade: ".trim((string) $statusLine));
        }
        // Drain the rest of the handshake response headers.
        while (($line = fgets($socket)) !== false && trim($line) !== '') {
            continue;
        }

        return $socket;
    }

    /** @param  resource  $socket */
    private function send($socket, string $payload): void
    {
        fwrite($socket, $this->encodeFrame($payload));
    }

    /** Encodes a single unfragmented masked text frame — client-to-server frames must be masked (RFC 6455 §5.3). */
    private function encodeFrame(string $payload): string
    {
        $length = strlen($payload);
        $mask = random_bytes(4);

        $frame = chr(0x81); // FIN + text opcode
        if ($length <= 125) {
            $frame .= chr($length | 0x80);
        } elseif ($length <= 65535) {
            $frame .= chr(126 | 0x80).pack('n', $length);
        } else {
            $frame .= chr(127 | 0x80).pack('J', $length);
        }

        $frame .= $mask;
        for ($i = 0; $i < $length; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }

        return $frame;
    }

    /** Reads the next complete (server-sent, unmasked) frame's payload, or null on timeout/close. @param resource $socket */
    private function readFrame($socket, int $timeoutSeconds): ?string
    {
        stream_set_timeout($socket, $timeoutSeconds);

        $header = fread($socket, 2);
        if ($header === false || strlen($header) < 2) {
            return null;
        }

        $byte1 = ord($header[0]);
        $byte2 = ord($header[1]);
        $opcode = $byte1 & 0x0F;
        $length = $byte2 & 0x7F;

        if ($length === 126) {
            $ext = fread($socket, 2);
            if ($ext === false || strlen($ext) < 2) {
                return null;
            }
            $length = unpack('n', $ext)[1];
        } elseif ($length === 127) {
            $ext = fread($socket, 8);
            if ($ext === false || strlen($ext) < 8) {
                return null;
            }
            $length = unpack('J', $ext)[1];
        }

        if ($opcode === 0x8) { // close frame
            return null;
        }

        if ($length === 0) {
            return '';
        }

        $payload = '';
        while (strlen($payload) < $length) {
            $chunk = fread($socket, $length - strlen($payload));
            if ($chunk === false || $chunk === '') {
                return null;
            }
            $payload .= $chunk;
        }

        return $payload;
    }
}
