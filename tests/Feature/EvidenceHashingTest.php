<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('calculates sha256 hash and sets recorded_at upon document upload', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $task = Task::factory()->create();

    $fileContent = 'this is a test evidence file';
    $expectedHash = hash('sha256', $fileContent);

    $file = UploadedFile::fake()->createWithContent('evidence.txt', $fileContent);

    $response = actingAs($user)->post(route('documents.store', $task->id), [
        'file' => $file,
    ]);

    $response->assertRedirect(route('tasks.show', $task->id));

    $this->assertDatabaseHas('documents', [
        'attachable_id' => $task->id,
        'attachable_type' => Task::class,
        'file_hash' => $expectedHash,
        'checksum' => $expectedHash,
    ]);

    $document = $task->documents()->first();
    expect($document->recorded_at)->not->toBeNull();
});

it('saves claimed_at when provided by the user', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $task = Task::factory()->create();

    $file = UploadedFile::fake()->create('evidence.pdf', 100);
    $claimedTime = '2026-09-19 10:00:00';

    $response = actingAs($user)->post(route('documents.store', $task->id), [
        'file' => $file,
        'claimed_at' => $claimedTime,
    ]);

    $response->assertRedirect(route('tasks.show', $task->id));

    $this->assertDatabaseHas('documents', [
        'attachable_id' => $task->id,
        'claimed_at' => $claimedTime,
    ]);
});
