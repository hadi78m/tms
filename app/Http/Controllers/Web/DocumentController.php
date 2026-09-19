<?php

namespace App\Http\Controllers\Web;

use App\Domain\Services\DocumentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreDocumentRequest;
use App\Models\Task;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentService $documentService
    ) {}

    public function store(StoreDocumentRequest $request, Task $task)
    {
        $this->documentService->uploadDocument(
            $task,
            $request->file('file'),
            auth()->user(),
            'local',
            $request->input('claimed_at')
        );

        return redirect()->route('tasks.show', $task->id)->with('status', 'مستند با موفقیت بارگذاری شد.');
    }
}
