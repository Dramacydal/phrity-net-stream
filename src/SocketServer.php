<?php

namespace Phrity\Net;

use ErrorException;
use Phrity\Util\ErrorHandler;
use Psr\Http\Message\UriInterface;

/**
 * Phrity\Net\SocketServer class.
 */
class SocketServer extends Stream
{
    private static $internet_schemes = ['tcp', 'udp', 'tls', 'ssl'];
    private static $unix_schemes = ['unix', 'udg'];

    protected $handler;
    protected $stream;

    /**
     * Create new socker server instance
     * @param \Psr\Http\Message\UriInterface $uri The URI to open socket on.
     * @param int $flags Flags to set on socket.
     * @throws StreamException if unable to create socket.
     */
    public function __construct(UriInterface $uri, int $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN)
    {
        $this->handler = new ErrorHandler();
        if (!in_array($uri->getScheme(), $this->getTransports())) {
            throw new StreamException(StreamException::SCHEME_TRANSPORT, ['scheme' => $uri->getScheme()]);
        }
        if (in_array(substr($uri->getScheme(), 0, 3), self::$internet_schemes)) {
            $address = "{$uri->getScheme()}://{$uri->getAuthority()}";
        } elseif (in_array($uri->getScheme(), self::$unix_schemes)) {
            $address = "{$uri->getScheme()}://{$uri->getPath()}";
        } else {
            throw new StreamException(StreamException::SCHEME_HANDLER, ['scheme' => $uri->getScheme()]);
        }
        $this->stream = $this->handler->with(function () use ($address, $flags) {
            $error_code = $error_message = '';
            return stream_socket_server($address, $error_code, $error_message, $flags);
        }, new StreamException(StreamException::SERVER_SOCKET_ERR, ['uri' => $uri->__toString()]));
        $this->evalStream();
    }


    // ---------- Configuration ---------------------------------------------------------------------------------------

    /**
     * Retrieve list of registered socket transports.
     * @return array List of registered transports.
     */
    public function getTransports(): array
    {
        return stream_get_transports();
    }

    /**
     * If server is in blocking mode.
     * @return bool|null
     */
    public function isBlocking(): ?bool
    {
        return $this->getMetadata('blocked');
    }

    /**
     * Toggle blocking/non-blocking mode.
     * @param bool $enable Blocking mode to set.
     * @return bool If operation was succesful.
     * @throws StreamException if socket is closed.
     */
    public function setBlocking(bool $enable): bool
    {
        if (!isset($this->stream)) {
            throw new StreamException(StreamException::SERVER_CLOSED);
        }
        return stream_set_blocking($this->stream, $enable);
    }


    // ---------- Operations ------------------------------------------------------------------------------------------

    /**
     * Accept a connection on a socket.
     * @param int|null $timeout Override the default socket accept timeout.
     * @return Phrity\Net\SocketStream|null The stream for opened conenction.
     * @throws StreamException if socket is closed
     */
    public function accept(?int $timeout = null): ?SocketStream
    {
        if (!isset($this->stream)) {
            throw new StreamException(StreamException::SERVER_CLOSED);
        }
        $stream = $this->handler->with(function () use ($timeout) {
            $peer_name = '';
            return stream_socket_accept($this->stream, $timeout, $peer_name);
        }, function (ErrorException $e) {
            // If non-blocking mode, don't throw error on time out
            if ($this->getMetadata('blocked') === false && substr_count($e->getMessage(), 'timed out') > 0) {
                return null;
            }
            throw new StreamException(StreamException::SERVER_ACCEPT_ERR);
        });
        return $stream ? new SocketStream($stream) : null;
    }
}
