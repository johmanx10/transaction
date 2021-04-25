<?php
declare(strict_types=1);

namespace Johmanx10\Transaction\Result;

use Closure;
use Johmanx10\Transaction\DispatcherAware;
use Johmanx10\Transaction\Event\RollbackBlockedEvent;
use Johmanx10\Transaction\Event\RollbackResultEvent;
use Johmanx10\Transaction\Operation\Event\RollbackEvent;
use Johmanx10\Transaction\Operation\Result\InvocationResult;
use Throwable;

final class CommitResult
{
    use DispatcherAware;

    private array $results;
    private bool $rolledBack = false;
    private bool $committed;

    public function __construct(
        public StagingResult $staging,
        private ?Closure $rollback,
        InvocationResult ...$results
    ) {
        $this->results = $results;
    }

    /**
     * Confirm whether all operations were successful.
     *
     * @return bool
     */
    public function committed(): bool
    {
        return $this->committed ??= array_reduce(
            $this->results,
            fn (bool $carry, InvocationResult $result) =>
                $carry && $result->success,
            $this->staging->isStaged()
        );
    }

    /**
     * Get the exception that caused the transaction not to commit, or null if
     * the transaction succeeded, or the disruption was not caused by an exception.
     *
     * @return Throwable|null
     */
    public function getReason(): ?Throwable
    {
        return array_reduce(
            $this->results,
            fn (?Throwable $carry, InvocationResult $result) =>
                $carry ?? $result->exception
        );
    }

    /**
     * Roll back the operations that weren't skipped.
     */
    public function rollback(): void
    {
        if ($this->rolledBack || $this->committed()) {
            $this->dispatch(
                new RollbackBlockedEvent(
                    rolledBack: $this->rolledBack,
                    committed: $this->committed()
                )
            );
            $this->rolledBack = true;
            return;
        }

        $this->rolledBack = true;
        $rollbacks = [];

        foreach ($this->getResults() as $result) {
            if (!$result->invoked) {
                continue;
            }

            $rollback = $result->rollback();
            $event = new RollbackEvent($rollback, $result->exception);

            $this->dispatch($event);

            if ($event->isDefaultPrevented()) {
                continue;
            }

            $rollback();

            $rollbacks[] = $rollback;
        }

        $this->dispatch(new RollbackResultEvent(...$rollbacks));
    }

    /**
     * @return InvocationResult[]
     */
    private function getResults(): iterable
    {
        if ($this->rollback !== null) {
            yield new InvocationResult(
                'Transaction',
                $this->committed(),
                true,
                $this->getReason(),
                $this->rollback
            );
            return;
        }

        yield from array_reverse($this->results);
    }
}
