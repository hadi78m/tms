<?php

namespace Tests\Unit\Domain;

use App\Domain\Exceptions\ContractorScopeViolationException;
use App\Domain\Rules\TaskScopeService;
use App\Models\Task;
use App\Models\User;
use Tests\TestCase;

class TaskScopeServiceTest extends TestCase
{
    public function test_contractor_can_access_own_task()
    {
        $service = new TaskScopeService;
        $user = new User;
        $user->contractor_id = 1;

        $task = new Task;
        $task->contractor_id = 1;

        $service->assertCanAccess($task, $user);
        $this->assertTrue(true); // Should not throw
    }

    public function test_contractor_cannot_access_other_task()
    {
        $service = new TaskScopeService;
        $user = new User;
        $user->contractor_id = 1;

        $task = new Task;
        $task->contractor_id = 2;

        $this->expectException(ContractorScopeViolationException::class);
        $service->assertCanAccess($task, $user);
    }

    public function test_non_contractor_can_access()
    {
        $service = new TaskScopeService;
        $user = new User;
        $user->contractor_id = null;

        $task = new Task;
        $task->contractor_id = 2;

        $service->assertCanAccess($task, $user);
        $this->assertTrue(true); // Should not throw
    }
}
