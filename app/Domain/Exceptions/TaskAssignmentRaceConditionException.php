<?php

namespace App\Domain\Exceptions;

class TaskAssignmentRaceConditionException extends DomainException
{
    public function __construct(string $message = 'Another assignment is currently being processed for this task. Please try again.')
    {
        parent::__construct($message);
    }
}
