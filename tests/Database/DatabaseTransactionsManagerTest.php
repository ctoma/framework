<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\DatabaseTransactionsManager;
use PHPUnit\Framework\TestCase;

class DatabaseTransactionsManagerTest extends TestCase
{
    public function testBeginningTransactions()
    {
        $manager = (new DatabaseTransactionsManager);

        $manager->begin('default', 1);
        $manager->begin('default', 2);
        $manager->begin('admin', 1);

        $this->assertCount(3, $manager->getPendingTransactions());
        $this->assertSame('default', $manager->getPendingTransactions()[0]->connection);
        $this->assertEquals(1, $manager->getPendingTransactions()[0]->level);
        $this->assertSame('default', $manager->getPendingTransactions()[1]->connection);
        $this->assertEquals(2, $manager->getPendingTransactions()[1]->level);
        $this->assertSame('admin', $manager->getPendingTransactions()[2]->connection);
        $this->assertEquals(1, $manager->getPendingTransactions()[2]->level);
    }

    public function testRollingBackTransactions()
    {
        $manager = (new DatabaseTransactionsManager);

        $manager->begin('default', 1);
        $manager->begin('default', 2);
        $manager->begin('admin', 1);

        $manager->rollback('default', 1);

        $this->assertCount(2, $manager->getPendingTransactions());

        $this->assertSame('default', $manager->getPendingTransactions()[0]->connection);
        $this->assertEquals(1, $manager->getPendingTransactions()[0]->level);

        $this->assertSame('admin', $manager->getPendingTransactions()[1]->connection);
        $this->assertEquals(1, $manager->getPendingTransactions()[1]->level);
    }

    public function testRollingBackTransactionsAllTheWay()
    {
        $manager = (new DatabaseTransactionsManager);

        $manager->begin('default', 1);
        $manager->begin('default', 2);
        $manager->begin('admin', 1);

        $manager->rollback('default', 0);

        $this->assertCount(1, $manager->getPendingTransactions());

        $this->assertSame('admin', $manager->getPendingTransactions()[0]->connection);
        $this->assertEquals(1, $manager->getPendingTransactions()[0]->level);
    }

    public function testCommittingTransactions()
    {
        $manager = (new DatabaseTransactionsManager);

        $manager->begin('default', 1);
        $manager->begin('default', 2);
        $manager->begin('admin', 1);

        $manager->stageTransactions('default');
        $manager->stageTransactions('admin');
        $manager->commit('default');

        $this->assertCount(0, $manager->getPendingTransactions());
        $this->assertCount(1, $manager->getCommittedTransactions());

        $this->assertSame('admin', $manager->getCommittedTransactions()[0]->connection);
        $this->assertEquals(1, $manager->getCommittedTransactions()[0]->level);
    }

    public function testCallbacksAreAddedToTheCurrentTransaction()
    {
        $callbacks = [];

        $manager = (new DatabaseTransactionsManager);

        $manager->begin('default', 1);

        $manager->addCallback(function () use (&$callbacks) {
        });

        $manager->begin('default', 2);

        $manager->begin('admin', 1);

        $manager->addCallback(function () use (&$callbacks) {
        });

        $this->assertCount(1, $manager->getPendingTransactions()[0]->getCallbacks());
        $this->assertCount(0, $manager->getPendingTransactions()[1]->getCallbacks());
        $this->assertCount(1, $manager->getPendingTransactions()[2]->getCallbacks());
    }

    public function testCommittingTransactionsExecutesCallbacks()
    {
        $callbacks = [];

        $manager = (new DatabaseTransactionsManager);

        $manager->begin('default', 1);

        $manager->addCallback(function () use (&$callbacks) {
            $callbacks[] = ['default', 1];
        });

        $manager->begin('default', 2);

        $manager->addCallback(function () use (&$callbacks) {
            $callbacks[] = ['default', 2];
        });

        $manager->begin('admin', 1);

        $manager->stageTransactions('default');
        $manager->commit('default');

        $this->assertCount(2, $callbacks);
        $this->assertEquals(['default', 1], $callbacks[0]);
        $this->assertEquals(['default', 2], $callbacks[1]);
    }

    public function testCommittingExecutesOnlyCallbacksOfTheConnection()
    {
        $callbacks = [];

        $manager = (new DatabaseTransactionsManager);

        $manager->begin('default', 1);

        $manager->addCallback(function () use (&$callbacks) {
            $callbacks[] = ['default', 1];
        });

        $manager->begin('default', 2);
        $manager->begin('admin', 1);

        $manager->addCallback(function () use (&$callbacks) {
            $callbacks[] = ['admin', 1];
        });

        $manager->stageTransactions('default');
        $manager->commit('default');

        $this->assertCount(1, $callbacks);
        $this->assertEquals(['default', 1], $callbacks[0]);
    }

    public function testCallbackIsExecutedIfNoTransactions()
    {
        $callbacks = [];

        $manager = (new DatabaseTransactionsManager);

        $manager->addCallback(function () use (&$callbacks) {
            $callbacks[] = ['default', 1];
        });

        $this->assertCount(1, $callbacks);
        $this->assertEquals(['default', 1], $callbacks[0]);
    }

    public function testStageTransactions()
    {
        $manager = (new DatabaseTransactionsManager);

        $manager->begin('default', 1);
        $manager->begin('admin', 1);

        $this->assertCount(2, $manager->getPendingTransactions());

        $pendingTransactions = $manager->getPendingTransactions();

        $this->assertEquals(1, $pendingTransactions[0]->level);
        $this->assertEquals('default', $pendingTransactions[0]->connection);
        $this->assertEquals(1, $pendingTransactions[1]->level);
        $this->assertEquals('admin', $pendingTransactions[1]->connection);

        $manager->stageTransactions('default');

        $this->assertCount(1, $manager->getPendingTransactions());
        $this->assertCount(1, $manager->getCommittedTransactions());
        $this->assertEquals('default', $manager->getCommittedTransactions()[0]->connection);

        $manager->stageTransactions('admin');

        $this->assertCount(0, $manager->getPendingTransactions());
        $this->assertCount(2, $manager->getCommittedTransactions());
        $this->assertEquals('admin', $manager->getCommittedTransactions()[1]->connection);
    }
}
