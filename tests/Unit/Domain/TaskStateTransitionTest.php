<?php

namespace Tests\Unit\Domain;

use App\Domain\Enums\TaskStatus;
use App\Domain\Exceptions\InvalidTaskTransitionException;
use App\Domain\Rules\TaskStateTransition;
use PHPUnit\Framework\TestCase;

class TaskStateTransitionTest extends TestCase
{
    protected TaskStateTransition $transition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transition = new TaskStateTransition;
    }

    public function test_valid_transitions()
    {
        $this->assertTrue($this->transition->canTransition(TaskStatus::Draft, TaskStatus::Assigned));
        $this->assertTrue($this->transition->canTransition(TaskStatus::Draft, TaskStatus::Cancelled));
        $this->assertTrue($this->transition->canTransition(TaskStatus::Assigned, TaskStatus::InProgress));
        $this->assertTrue($this->transition->canTransition(TaskStatus::UnderReview, TaskStatus::NeedsRework));
        $this->assertTrue($this->transition->canTransition(TaskStatus::NeedsRework, TaskStatus::InProgress));
    }

    public function test_invalid_transitions()
    {
        $this->assertFalse($this->transition->canTransition(TaskStatus::Draft, TaskStatus::InProgress));
        $this->assertFalse($this->transition->canTransition(TaskStatus::Approved, TaskStatus::Assigned));
        $this->assertFalse($this->transition->canTransition(TaskStatus::Cancelled, TaskStatus::Draft));
        $this->assertFalse($this->transition->canTransition(TaskStatus::InProgress, TaskStatus::NeedsRework));
    }

    public function test_same_state_transition_is_invalid()
    {
        $this->assertFalse($this->transition->canTransition(TaskStatus::Draft, TaskStatus::Draft));
    }

    public function test_assert_throws_exception()
    {
        $this->expectException(InvalidTaskTransitionException::class);
        $this->transition->assertCanTransition(TaskStatus::Draft, TaskStatus::InProgress);
    }
}
