<?php

/**
 * File for Net\StreamCollection class.
 * @package Phrity > Net > Stream
 */

namespace Phrity\Net;

use Countable;
use Iterator;
use Phrity\Util\ErrorHandler;
use RuntimeException;
use TypeError;

/**
 * Net\StreamCollection class.
 */
class StreamCollection implements Countable, Iterator
{
    protected $handler;
    private $streams = [];

    /**
     * Create new stream collection instance.
     */
    public function __construct()
    {
        $this->handler = new ErrorHandler();
    }


    // Collectors and selectors

    /**
     * Attach stream to collection.
     * @param Stream $attach Stream to attach.
     * @param string|null $key Definable name of stream.
     * @return string Name of stream.
     */
    public function attach(Stream $attach, ?string $key = null): string
    {
        if ($key && array_key_exists($key, $this->streams)) {
            throw new RuntimeException("Stream with name '{$key}' already attached.");
        }
        $key = $key ?: $this->createKey();
        $this->streams[$key] = $attach;
        return $key;
    }

    /**
     * Detach stream from collection.
     * @param Stream|string $detach Stream or name of stream  to detach.
     * @return bool If a stream was detached.
     */
    public function detach($detach): bool
    {
        if (is_string($detach)) {
            if (array_key_exists($detach, $this->streams)) {
                unset($this->streams[$detach]);
                return true;
            }
            return false;
        }
        if ($detach instanceof Stream) {
            foreach ($this->streams as $key => $stream) {
                if ($stream === $detach) {
                    unset($this->streams[$key]);
                    return true;
                }
            }
            return false;
        }
        throw new TypeError("Argument #1 ($detach) must be of type Phrity\Net\Stream or string.");
    }

    /**
     * Collect all readable streams into new collection.
     * @return self New collection instance.
     */
    public function getReadable(): self
    {
        $readables = new self();
        foreach ($this->streams as $key => $stream) {
            if ($stream->isReadable()) {
                $readables->attach($stream, $key);
            }
        }
        return $readables;
    }

    /**
     * Collect all writable streams into new collection.
     * @return self New collection instance.
     */
    public function getWritable(): self
    {
        $writables = new self();
        foreach ($this->streams as $key => $stream) {
            if ($stream->isWritable()) {
                $writables->attach($stream, $key);
            }
        }
        return $writables;
    }

    /**
     * Wait for redable content in stream collection.
     * @param int $seconds Timeout in seconds.
     * @return self New collection instance.
     */
    public function waitRead(int $seconds = 60): self
    {
        $read = [];
        foreach ($this->streams as $key => $stream) {
            if ($stream->isReadable()) {
                $read[$key] = $stream->getResource();
            }
        }

        $changed = $this->handler->with(function () use ($read, $seconds) {
            $write = $oob = [];
            stream_select($read, $write, $oob, $seconds);
            return $read;
        }, new RuntimeException('Failed to select streams for reading.'));

        $ready = new self();
        foreach ($changed as $key => $resource) {
            $ready->attach($this->streams[$key], $key);
        }
        return $ready;
    }


    // Countable interface implementation

    /**
     * Count contained streams.
     * @return int Number of streams in collection.
     */
    public function count(): int
    {
        return count($this->streams);
    }


    // Iterator interface implementation

    /**
     * Return the current stream.
     * @return mixed Current stream.
     */
    public function current(): Stream
    {
        return current($this->streams);
    }

    /**
     * Return the key of the current stream.
     * @return scalar|null Current key.
     */
    public function key(): string
    {
        return key($this->streams);
    }

    /**
     * Move forward to next stream.
     */
    public function next(): void
    {
        next($this->streams);
    }

    /**
     * Rewind the Iterator to the first stream.
     */
    public function rewind(): void
    {
        reset($this->streams);
    }

    /**
     * Checks if current position is valid.
     * @return bool True if valid.
     */
    public function valid(): bool
    {
        return array_key_exists(key($this->streams), $this->streams);
    }


    // Protected helper methods

    /**
     * Create unique key.
     * @return string Unique key.
     */
    protected function createKey(): string
    {
        do {
            $key = bin2hex(random_bytes(16));
        } while (array_key_exists($key, $this->streams));
        return $key;
    }
}
