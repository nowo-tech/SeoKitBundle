<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Worker;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * Receiver of the boundary test: hands out $count messages for tenants A and B in alternation and keeps no envelope,
 * so memory reflects the bundle only.
 */
final class TenantPageReceiver implements ReceiverInterface
{
    public int $acknowledged = 0;

    public int $rejected = 0;

    private int $next = 0;

    public function __construct(
        private readonly int $count,
    ) {
    }

    public function get(): iterable
    {
        if ($this->next >= $this->count) {
            return [];
        }

        $index = $this->next++;

        return [new Envelope(new TenantPageMessage($index % 2 === 0 ? 'A' : 'B', $index, $index % 7 === 3))];
    }

    public function ack(Envelope $envelope): void
    {
        ++$this->acknowledged;
    }

    public function reject(Envelope $envelope): void
    {
        ++$this->rejected;
    }
}
