<?php

namespace App\Console\Commands;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Exceptions\DomainException;
use App\Domain\Services\ProjectMembershipService;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;

use function app;

/**
 * V1.11 — V11-01 (OD-8 / B-2 · Q3): controlled, traceable initial Supervisor
 * assignment entry point.
 *
 * The command carries NO business rules: it validates input, resolves a real
 * actor, delegates everything to ProjectMembershipService (the canonical
 * rule holder) and reports the result. Every assignment made through it is
 * audited with a real acting user — no fabricated identity, no automatic
 * backfill (prohibited by OD-8).
 *
 * Actor convention: there is no system actor anywhere in this repository
 * (zero precedent), so the command REQUIRES --actor <national_code> and
 * resolves that real, active user. This matches the seeder's convention of
 * identifying users by national_code and the audit service's requirement of
 * a real User actor. A fake/system user would fabricate audit identity.
 */
class AssignProjectSupervisorCommand extends Command
{
    protected $signature = 'project:assign-supervisor
                            {project : Project ID}
                            {user : National code of the Supervisor to assign}
                            {--actor= : National code of the REAL acting user (required — recorded as assigned_by in the audit trail)}
                            {--reason= : Optional reason for the assignment}';

    protected $description = 'Assign (or replace) the active Supervisor of a project via the canonical membership service (V1.11 V11-01, OD-8 B-2 controlled initial assignment)';

    public function handle(ProjectMembershipService $service): int
    {
        $actorCode = (string) $this->option('actor');

        if ($actorCode === '') {
            $this->error('The --actor option is required: assignments must be traceable to a real acting user (assigned_by).');

            return self::FAILURE;
        }

        // Resolve the real actor (never fabricated, never a fixed ID).
        $actor = User::where('national_code', $actorCode)->first();

        if ($actor === null || ! $actor->is_active) {
            $this->error("No active user found for --actor national_code [{$actorCode}].");

            return self::FAILURE;
        }

        $project = Project::find((int) $this->argument('project'));

        if ($project === null) {
            $this->error('Project not found.');

            return self::FAILURE;
        }

        $assignee = User::where('national_code', (string) $this->argument('user'))->first();

        if ($assignee === null || ! $assignee->is_active) {
            $this->error('Supervisor user not found or not active.');

            return self::FAILURE;
        }

        try {
            $membership = $service->assignSupervisor(
                new AssignProjectSupervisorData(
                    project_id: $project->id,
                    user_id: $assignee->id,
                    assigned_by: $actor->id,
                    reason: $this->option('reason')
                ),
                $actor
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $membership->wasRecentlyCreated) {
            $this->info("No change: user [{$assignee->national_code}] is already the active Supervisor of project [{$project->id}].");
        } else {
            $this->info("Supervisor [{$assignee->national_code}] assigned to project [{$project->id}] (membership #{$membership->id}, actor: {$actor->national_code}).");
        }

        return self::SUCCESS;
    }
}
