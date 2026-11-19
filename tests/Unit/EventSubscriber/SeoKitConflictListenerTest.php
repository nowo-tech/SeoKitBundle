<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\EventSubscriber;

use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nowo\SeoKitBundle\EventSubscriber\SeoKitConflictListener;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

use function json_decode;

/**
 * 1.25 (REQ-RUNTIME-003 rule 6): conflict answers of the bundle's routes.
 */
final class SeoKitConflictListenerTest extends TestCase
{
    private const API      = 'Nowo\\SeoKitBundle\\Controller\\Api\\SeoSurfaceApiController::post';
    private const ADMIN    = 'Nowo\\SeoKitBundle\\Controller\\Admin\\SeoSurfaceController::edit';
    private const SETTINGS = 'Nowo\\SeoKitBundle\\Controller\\Admin\\SeoSiteSettingsController::edit';

    public function testApiLockConflictWithNothingCommittedAnswers409Conflict001WithRetryAfter(): void
    {
        $event = $this->exception(self::API, $this->deadlock());

        (new SeoKitConflictListener())->onException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame(ConflictCode::RETRY_SAFE, $response->headers->get(ConflictResponsePolicy::CODE_HEADER));
        self::assertSame('1', $response->headers->get('Retry-After'));
        self::assertSame(['ok' => false, 'errors' => [SeoKitConflictListener::RETRY_SAFE_MESSAGE], 'code' => ConflictCode::RETRY_SAFE], json_decode((string) $response->getContent(), true));
    }

    public function testAdminLockConflictAfterACommitOfTheRequestAnswers409Conflict002WithoutRetryAfter(): void
    {
        $ledger   = new CommitLedger();
        $listener = new SeoKitConflictListener(new ConflictResponsePolicy(), $ledger);
        $request  = $this->request(self::ADMIN);
        $listener->onRequest(new RequestEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST));
        $ledger->record();
        $event = new ExceptionEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, new LockWaitTimeoutException($this->driverError('Lock wait timeout', 'HY000', 1205), null));

        $listener->onException($event);

        $answer = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $answer);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(SeoKitConflictListener::COMMITTED_MESSAGE, $answer->getMessage());
        self::assertSame([ConflictResponsePolicy::CODE_HEADER => ConflictCode::COMMITTED], $answer->getHeaders());
        self::assertNull($event->getResponse());
    }

    public function testASubRequestReadsTheMarkOfTheMainRequest(): void
    {
        $ledger   = new CommitLedger();
        $stack    = new RequestStack();
        $listener = new SeoKitConflictListener(null, $ledger, $stack);
        $main     = $this->request('App\\Controller\\Page::show');
        $listener->onRequest(new RequestEvent($this->kernel(), $main, HttpKernelInterface::MAIN_REQUEST));
        $stack->push($main);
        $sub = $this->request(self::API);
        $listener->onRequest(new RequestEvent($this->kernel(), $sub, HttpKernelInterface::SUB_REQUEST));
        self::assertFalse($sub->attributes->has(SeoKitConflictListener::MARK_ATTRIBUTE));
        $ledger->record();
        $event = new ExceptionEvent($this->kernel(), $sub, HttpKernelInterface::SUB_REQUEST, $this->deadlock());

        $listener->onException($event);

        self::assertSame(ConflictCode::COMMITTED, $event->getResponse()?->headers->get(ConflictResponsePolicy::CODE_HEADER));
        self::assertFalse($event->getResponse()->headers->has('Retry-After'));
    }

    public function testNoLedgerTakesNoMark(): void
    {
        $request = $this->request(self::API);
        (new SeoKitConflictListener())->onRequest(new RequestEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST));
        self::assertFalse($request->attributes->has(SeoKitConflictListener::MARK_ATTRIBUTE));
    }

    public function testRunnerHintAndConfiguredStatus(): void
    {
        $event = $this->exception(self::ADMIN, new RuntimeException('wrapped', 0, new TransientStorageFailure(5)));
        (new SeoKitConflictListener(new ConflictResponsePolicy(503, 2)))->onException($event);
        $answer = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $answer);
        self::assertSame(503, $answer->getStatusCode());
        self::assertSame([ConflictResponsePolicy::CODE_HEADER => ConflictCode::RETRY_SAFE, 'Retry-After' => '5'], $answer->getHeaders());

        $event = $this->exception(self::API, TransientStorageFailure::fromPrevious($this->deadlock(), 0));
        (new SeoKitConflictListener(new ConflictResponsePolicy(409, 3)))->onException($event);
        self::assertSame('3', $event->getResponse()?->headers->get('Retry-After'), 'A zero hint falls back to the policy.');
    }

    public function testLostSavepointIsCommitted(): void
    {
        $lost  = new DriverException($this->driverError('SAVEPOINT DOCTRINE_2 does not exist', '42000', 1305), null);
        $event = $this->exception(self::API, $lost);

        (new SeoKitConflictListener())->onException($event);

        self::assertSame(ConflictCode::COMMITTED, $event->getResponse()?->headers->get(ConflictResponsePolicy::CODE_HEADER));
    }

    public function testSettingsUniqueViolationAnswersADomain409WithoutAConflictCode(): void
    {
        $event = $this->exception(self::SETTINGS, new RuntimeException('save', 0, $this->uniqueViolation()));

        (new SeoKitConflictListener())->onException($event);

        $answer = $event->getThrowable();
        self::assertInstanceOf(ConflictHttpException::class, $answer);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame([SeoKitConflictListener::ERROR_CODE_HEADER => SeoKitConflictListener::DUPLICATE_KEY], $answer->getHeaders());
    }

    /**
     * @return iterable<string, array{0: string|null, 1: Throwable}>
     */
    public static function untouched(): iterable
    {
        $deadlock = new DeadlockException(new class('Deadlock found', '40001', 1213) extends AbstractException {
        }, null);

        yield 'host route' => ['App\\Controller\\PageController::show', $deadlock];
        yield 'no controller' => [null, $deadlock];
        yield 'connection lost' => [self::API, new ConnectionLost(new class('gone away', 'HY000', 2006) extends AbstractException {
        }, null)];
        yield 'other failure' => [self::ADMIN, new RuntimeException('boom')];
    }

    #[DataProvider('untouched')]
    public function testLeavesOtherFailuresAndHostRoutesAlone(?string $controller, Throwable $failure): void
    {
        $event = $this->exception($controller, $failure);

        (new SeoKitConflictListener())->onException($event);

        self::assertNull($event->getResponse());
        self::assertSame($failure, $event->getThrowable());
    }

    private function exception(?string $controller, Throwable $failure): ExceptionEvent
    {
        return new ExceptionEvent($this->kernel(), $this->request($controller), HttpKernelInterface::MAIN_REQUEST, $failure);
    }

    private function request(?string $controller): Request
    {
        $request = new Request();
        if ($controller !== null) {
            $request->attributes->set('_controller', $controller);
        }

        return $request;
    }

    private function kernel(): HttpKernelInterface
    {
        return $this->createStub(HttpKernelInterface::class);
    }

    private function deadlock(): DeadlockException
    {
        return new DeadlockException($this->driverError('Deadlock found', '40001', 1213), null);
    }

    private function uniqueViolation(): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException($this->driverError('Duplicate entry', '23000', 1062), null);
    }

    private function driverError(string $message, string $sqlState, int $code): AbstractException
    {
        return new class($message, $sqlState, $code) extends AbstractException {
        };
    }
}
