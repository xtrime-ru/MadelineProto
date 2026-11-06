<?php

declare(strict_types=1);

namespace danog\MadelineProto\Test;

use danog\MadelineProto\API;
use danog\MadelineProto\Connection;
use danog\MadelineProto\DataCenterConnection;
use danog\MadelineProto\MTProto\LinkedList;
use danog\MadelineProto\MTProto\MTProtoOutgoingMessage;
use danog\MadelineProto\MTProto\LoginState;
use danog\MadelineProto\MTProto\NewAuthKey;
use danog\MadelineProto\Reactive\Publisher;
use danog\MadelineProto\Settings\Connection as ConnectionSettings;
use danog\MadelineProto\Stream\BufferedStreamInterface;
use danog\MadelineProto\Stream\MTProtoBufferInterface;
use DG\BypassFinals;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

abstract class IdleTimeoutStream implements MTProtoBufferInterface, BufferedStreamInterface
{
}

final class IdleTimeoutTest extends TestCase
{
    protected function setUp(): void
    {
        BypassFinals::enable();
    }

    private function connection(bool $media = true, bool $cdn = false, bool $disconnect = false): Connection
    {
        $shared = $this->getMockBuilder(DataCenterConnection::class)
            ->disableOriginalConstructor()->onlyMethods(['getSettings'])->getMock();
        $shared->method('getSettings')->willReturn(new ConnectionSettings);
        $login = new Publisher(new LoginState(API::LOGGED_IN, 2));
        $main = $media ? new NewAuthKey(false, false, 2, $login) : null;
        (new ReflectionProperty($shared, 'auth'))->setValue($shared, new NewAuthKey(
            $media, $cdn, 2, $login, $main,
        ));
        $connection = $this->getMockBuilder(Connection::class)->onlyMethods(['disconnect'])->getMock();
        $connection->expects($disconnect ? $this->once() : $this->never())->method('disconnect')->with(true);
        (new ReflectionProperty(Connection::class, 'shared'))->setValue($connection, $shared);
        $connection->stream = $this->createMock(IdleTimeoutStream::class);
        (new ReflectionProperty(Connection::class, 'idleSince'))->setValue($connection, hrtime(true) / 1e9 - 61);
        return $connection;
    }

    public function testMediaAndCdnSocketsCloseAfterTheirIdleTimeout(): void
    {
        $this->connection(disconnect: true)->closeIfIdle();
        $this->connection(media: false, cdn: true, disconnect: true)->closeIfIdle();
    }

    public function testMainDcAndDisconnectedSocketsAreLeftAlone(): void
    {
        $this->connection(media: false)->closeIfIdle();
        $connection = $this->connection();
        $connection->stream = null;
        $connection->closeIfIdle();
    }

    public function testActiveSocketsResetTheirIdlePeriod(): void
    {
        foreach (['fileRequests' => 1, 'writing' => true, 'new_outgoing' => [1], 'unencrypted_new_outgoing' => [1]] as $property => $value) {
            $connection = $this->connection();
            (new ReflectionProperty(Connection::class, $property))->setValue($connection, $value);
            $connection->closeIfIdle();
            $this->assertNull((new ReflectionProperty(Connection::class, 'idleSince'))->getValue($connection));
        }
    }

    public function testQueuedRequestsPreventIdleDisconnection(): void
    {
        foreach (['mainPendingOutgoing', 'unencryptedPendingOutgoing', 'uninitedPendingOutgoing'] as $property) {
            $connection = $this->connection();
            $queue = new LinkedList;
            $queue->enqueue((new \ReflectionClass(MTProtoOutgoingMessage::class))->newInstanceWithoutConstructor());
            (new ReflectionProperty(Connection::class, $property))->setValue($connection, $queue);
            $connection->closeIfIdle();
            $this->assertNull((new ReflectionProperty(Connection::class, 'idleSince'))->getValue($connection));
        }
    }

    public function testNewActivityBetweenCleanupTicksRestartsTheTimeout(): void
    {
        $connection = $this->connection();
        $connection->writing(true);
        $connection->writing(false);
        $connection->closeIfIdle();
        $this->assertGreaterThan(hrtime(true) / 1e9 - 1, (new ReflectionProperty(Connection::class, 'idleSince'))->getValue($connection));
    }

    public function testConfiguredTimeoutIsIndependentOfConnectTimeout(): void
    {
        $connection = $this->connection();
        $connection->getShared()->getSettings()->setIdleTimeout(120)->setTimeout(1.0);
        $connection->closeIfIdle();
    }

    public function testIdleSettingSurvivesSerialization(): void
    {
        $settings = new ConnectionSettings;
        $this->assertSame(60, $settings->getIdleTimeout());
        $settings->setIdleTimeout(90);
        $this->assertSame(90, unserialize(serialize($settings))->getIdleTimeout());
    }

    public function testIdleTimeoutMustBePositive(): void
    {
        $this->expectException(\ValueError::class);
        (new ConnectionSettings)->setIdleTimeout(0);
    }
}
