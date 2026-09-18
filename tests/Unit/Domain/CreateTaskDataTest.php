<?php

namespace Tests\Unit\Domain;

use App\Domain\DTOs\CreateTaskData;
use App\Domain\Enums\TaskPriority;
use PHPUnit\Framework\TestCase;

class CreateTaskDataTest extends TestCase
{
    public function test_create_task_data()
    {
        $dto = new CreateTaskData(
            1, // project_id
            null, // wbs_phase_id
            'Test Task',
            null,
            TaskPriority::Normal,
            10.5,
            null,
            null,
            null
        );

        $this->assertEquals(1, $dto->project_id);
        $this->assertEquals('Test Task', $dto->title);
        $this->assertEquals(TaskPriority::Normal, $dto->priority);
        $this->assertObjectNotHasProperty('contract_id', $dto);
        $this->assertObjectNotHasProperty('status', $dto);
        $this->assertObjectNotHasProperty('created_by', $dto);
    }
}
