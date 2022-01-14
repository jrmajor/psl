<?php

declare(strict_types=1);

namespace Psl\Async\Internal;

use Closure;
use Exception as RootException;
use Psl;
use Psl\Async\Awaitable;
use Psl\Async\Exception;
use Psl\Async\Scheduler;

/**
 * The following class was derived from code of Amphp.
 *
 * https://github.com/amphp/amp/blob/ac89b9e2ee04228e064e424056a08590b0cdc7b3/lib/Internal/FutureState.php
 *
 * Code subject to the MIT license (https://github.com/amphp/amp/blob/ac89b9e2ee04228e064e424056a08590b0cdc7b3/LICENSE).
 *
 * Copyright (c) 2015-2021 Amphp ( https://amphp.org )
 *
 * @internal
 *
 * @template T
 *
 * @codeCoverageIgnore
 */
final class State
{
    // Static so they can be used as array keys
    private static string $nextId = 'a';

    private bool $complete = false;

    private bool $handled = false;

    /**
     * @var array<string, (Closure(?RootException, ?T, string): void)>
     */
    private array $callbacks = [];

    /**
     * @var T|null
     */
    private mixed $result = null;

    private ?RootException $exception = null;

    public function __destruct()
    {
        if ($this->exception && !$this->handled) {
            throw Exception\UnhandledAwaitableException::forException($this->exception);
        }
    }

    /**
     * Registers a callback to be notified once the operation is complete or errored.
     *
     * The callback is invoked directly from the event loop context, so suspension within the callback is not possible.
     *
     * @param (Closure(?RootException, ?T, string): void) $callback Callback invoked on completion of the awaitable.
     *
     * @return string Identifier that can be used to cancel interest for this awaitable.
     */
    public function subscribe(Closure $callback): string
    {
        /** @psalm-suppress StringIncrement */
        $id = self::$nextId++;

        $this->handled = true;

        if ($this->complete) {
            Scheduler::queue(fn() => $callback($this->exception, $this->result, $id));
        } else {
            $this->callbacks[$id] = $callback;
        }

        return $id;
    }

    /**
     * Cancels a subscription.
     *
     * Cancellations are advisory only. The callback might still be called if it is already queued for execution.
     *
     * @param string $id Identifier returned from subscribe()
     */
    public function unsubscribe(string $id): void
    {
        unset($this->callbacks[$id]);
    }

    /**
     * Completes the operation with a result value.
     *
     * @param T $result Result of the operation.
     *
     * @throws Psl\Exception\InvariantViolationException If the operation is no longer pending.
     * @throws Psl\Exception\InvariantViolationException If $result is an instance of {@see Awaitable}.
     */
    public function complete(mixed $result): void
    {
        if ($this->complete) {
            Psl\invariant_violation('Operation is no longer pending.');
        }

        if ($result instanceof Awaitable) {
            Psl\invariant_violation('Cannot complete with an instance of ' . Awaitable::class);
        }

        $this->result = $result;
        $this->invokeCallbacks();
    }

    /**
     * Marks the operation as failed.
     *
     * @throws Psl\Exception\InvariantViolationException If the operation is no longer pending.
     */
    public function error(RootException $exception): void
    {
        if ($this->complete) {
            Psl\invariant_violation('Operation is no longer pending.');
        }

        $this->exception = $exception;
        $this->invokeCallbacks();
    }

    /**
     * Suppress the exception thrown to the loop error handler if and operation error is not handled by a callback.
     */
    public function ignore(): void
    {
        $this->handled = true;
    }

    /**
     * @return bool True if the operation has completed.
     */
    public function isComplete(): bool
    {
        return $this->complete;
    }

    private function invokeCallbacks(): void
    {
        $this->complete = true;

        foreach ($this->callbacks as $id => $callback) {
            Scheduler::queue(fn() => $callback($this->exception, $this->result, $id));
        }

        $this->callbacks = [];
    }
}
