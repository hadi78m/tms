<?php

namespace Tests\Feature\Domain;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Enums\TaskPriority;
use App\Domain\Enums\TaskStatus;
use App\Domain\Services\DocumentService;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Project;
use App\Models\SyncedContract;
use App\Models\SyncedContractor;
use App\Models\SyncedSystem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentService $documentService;

    protected User $user;

    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        // System user required for factory setups in TMS
        $system = SyncedSystem::create([
            'id' => 1,
            'external_id' => 'SYS-1',
            'source_system' => 'test_system',
            'name' => 'Test System',
            'code' => 'SYS-100',
            'status' => 'active',
            'source_updated_at' => now(),
            'last_synced_at' => now(),
            'sync_status' => 'success',
        ]);

        $this->user = User::factory()->create();

        $contractor = SyncedContractor::create([
            'id' => 1,
            'external_id' => 'EXT-CO-1',
            'source_system' => 'test_system',
            'name' => 'Test Company',
            'code' => 'CO-100',
            'status' => 'active',
            'source_updated_at' => now(),
            'last_synced_at' => now(),
            'sync_status' => 'success',
        ]);

        $contract = SyncedContract::create([
            'id' => 1,
            'contractor_id' => $contractor->id,
            'system_id' => $system->id,
            'external_id' => 'EXT-C-1',
            'source_system' => 'test_system',
            'title' => 'Test Contract',
            'contract_number' => 'C-100',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'amount' => 1000.00,
            'status' => 'active',
            'source_updated_at' => now(),
            'last_synced_at' => now(),
            'sync_status' => 'success',
        ]);

        // Create a basic Task to act as the attachable model
        $project = Project::create([
            'name' => 'Test Project',
            'contract_id' => $contract->id,
            'contractor_id' => $contractor->id,
            'status' => 'active',
            'start_date' => '2026-01-01',
        ]);

        $this->task = Task::create([
            'title' => 'Test Task',
            'project_id' => $project->id,
            'contract_id' => $contract->id,
            'contractor_id' => $contractor->id,
            'created_by' => $this->user->id,
            'priority' => TaskPriority::Normal->value,
            'status' => TaskStatus::Draft->value,
            'weight' => 10,
        ]);

        // We bind a mock for the AuditServiceInterface so we can spy on it
        $this->mock(AuditServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('log')
                ->andReturn(new ActivityLog);
        });

        $this->documentService = new DocumentService(app(AuditServiceInterface::class));
    }

    public function test_it_uploads_document_and_calculates_checksum()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('test_document.pdf', 100, 'application/pdf');

        $document = $this->documentService->uploadDocument($this->task, $file, $this->user, 'local');

        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals('test_document.pdf', $document->original_name);
        $this->assertEquals('application/pdf', $document->mime_type);
        $this->assertNotNull($document->checksum);
        $this->assertEquals($this->user->id, $document->uploaded_by);
        $this->assertEquals(Task::class, $document->attachable_type);
        $this->assertEquals($this->task->id, $document->attachable_id);

        Storage::disk('local')->assertExists($document->stored_name);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'original_name' => 'test_document.pdf',
        ]);
    }

    public function test_it_deletes_orphan_file_if_db_transaction_fails()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('orphan_test.txt', 10, 'text/plain');

        // We simulate a DB failure by binding an event to throw an exception on insert,
        // or we can mock DB facade, but an easier way is to create a fake attachable that throws exception on documents()

        $mockTask = \Mockery::mock(Task::class)->makePartial();
        $mockTask->shouldReceive('documents')->andThrow(new \Exception('Database failure!'));

        try {
            $this->documentService->uploadDocument($mockTask, $file, $this->user, 'local');
            $this->fail('Exception was not thrown.');
        } catch (\Exception $e) {
            $this->assertEquals('Database failure!', $e->getMessage());
        }

        // Verify that the file was deleted from the disk because of the failure
        // We know it stores in 'documents/' directory
        $files = Storage::disk('local')->allFiles('documents');
        $this->assertEmpty($files, 'Orphan file was not deleted from storage.');
    }

    public function test_it_soft_deletes_document_and_logs_audit()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('delete_test.png', 50, 'image/png');
        $document = $this->documentService->uploadDocument($this->task, $file, $this->user, 'local');

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'deleted_at' => null]);

        $this->documentService->deleteDocument($document, $this->user, 'No longer needed');

        // Document should be soft deleted
        $this->assertDatabaseMissing('documents', ['id' => $document->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);

        // Physical file should still exist
        Storage::disk('local')->assertExists($document->stored_name);

        // Assert AuditServiceInterface was called
        $auditService = app(AuditServiceInterface::class);
        // V1.8 (DEC-032): uploadDocument() now also emits `document_uploaded`, so
        // this is no longer the only `log()` call — assert the call, not the count.
        $auditService->shouldHaveReceived('log')->atLeast()->once()->with(
            'document_deleted',
            $document,
            $this->user,
            \Mockery::any(),
            [],
            ['reason' => 'No longer needed']
        );
    }
}
