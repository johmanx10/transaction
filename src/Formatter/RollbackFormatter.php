<?php
/**
 * Copyright MediaCT. All rights reserved.
 * https://www.mediact.nl
 */

namespace Johmanx10\WarpPipe\Formatter;

use Johmanx10\WarpPipe\Exception\OperationRolledBackExceptionInterface;
use Johmanx10\WarpPipe\OperationFailureInterface;

class RollbackFormatter implements RollbackFormatterInterface
{
    /** @var OperationFailureFormatterInterface */
    private $failureFormatter;

    /**
     * Constructor.
     *
     * @param OperationFailureFormatterInterface|null $failureFormatter
     */
    public function __construct(
        OperationFailureFormatterInterface $failureFormatter = null
    ) {
        $this->failureFormatter = (
            $failureFormatter ?? new OperationFailureFormatter()
        );
    }

    /**
     * Format the given rollback exception into a readable string.
     *
     * @param OperationRolledBackExceptionInterface $rollback
     *
     * @return string
     */
    public function format(OperationRolledBackExceptionInterface $rollback): string
    {
        return implode(
            PHP_EOL,
            array_reduce(
                $rollback->getFailures(),
                function (
                    array $carry,
                    OperationFailureInterface $failure
                ): array {
                    $carry[] = $this->failureFormatter->format($failure);

                    return $carry;
                },
                [
                    $rollback->getMessage(),
                    '',
                    'Stacktrace:'
                ]
            )
        );
    }
}
