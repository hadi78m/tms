<?php

namespace Tests\Unit\Domain;

use App\Domain\Enums\TaskStatus;
use PHPUnit\Framework\TestCase;

class TaskStatusTest extends TestCase
{
    public function test_task_status_values()
    {
        $this->assertEquals('draft', TaskStatus::Draft->value);
        $this->assertEquals('assigned', TaskStatus::Assigned->value);
        $this->assertEquals('in_progress', TaskStatus::InProgress->value);
        $this->assertEquals('submitted_for_review', TaskStatus::SubmittedForReview->value);
        $this->assertEquals('under_review', TaskStatus::UnderReview->value);
        $this->assertEquals('approved', TaskStatus::Approved->value);
        $this->assertEquals('needs_rework', TaskStatus::NeedsRework->value);
        $this->assertEquals('cancelled', TaskStatus::Cancelled->value);
    }
}
