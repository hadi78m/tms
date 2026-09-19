<?php

namespace App\Http\Controllers\Web;


use App\Domain\Services\DocumentService;
use App\Domain\Services\TaskService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\WbsPhase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService,
        protected DocumentService $documentService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        // Basic query
        $query = Task::with(['project', 'wbsPhase', 'activeAssignment.user']);

        // Contractor isolation: if user is a contractor, only show their tasks
        if ($user->contractor_id) {
            $query->whereHas('project', function ($q) use ($user) {
                $q->where('contractor_id', $user->contractor_id);
            });
        }

        $tasks = $query->latest()->paginate(15);

        return view('tasks.index', compact('tasks'));
    }

    public function create(Request $request)
    {
        $projects = Project::all();
        $wbsPhases = WbsPhase::all();
        $parentId = $request->query('parent_id');

        return view('tasks.create', compact('projects', 'wbsPhases', 'parentId'));
    }

    public function store(StoreTaskRequest $request)
    {
        $task = $this->taskService->create(
            $request->toDto(),
            Auth::user()
        );

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $this->documentService->uploadDocument(
                $task,
                $file,
                Auth::user()
            );
        }

        return redirect()->route('tasks.index')->with('status', 'وظیفه جدید با موفقیت ایجاد شد.');
    }

    public function show(Task $task)
    {
        $user = Auth::user();

        // Contractor isolation check
        if ($user->contractor_id && $task->contractor_id !== $user->contractor_id) {
            abort(403, 'شما دسترسی به این وظیفه ندارید.');
        }

        $task->load(['project', 'contractor', 'documents', 'activeAssignment.user', 'wbsPhase']);

        return view('tasks.show', compact('task'));
    }

    public function start(Request $request, Task $task)
    {
        $user = Auth::user();

        if ($user->contractor_id && $task->contractor_id !== $user->contractor_id) {
            abort(403, 'شما دسترسی به این وظیفه ندارید.');
        }

        try {
            $this->taskService->startProgress($task, $user);
            return redirect()->route('tasks.show', $task->id)->with('status', 'اجرای وظیفه آغاز شد.');
        } catch (\App\Domain\Exceptions\TaskBlockedException $e) {
            return redirect()->route('tasks.show', $task->id)->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('tasks.show', $task->id)->with('error', 'خطایی در شروع وظیفه رخ داد.');
        }
    }

    public function submit(Request $request, Task $task)
    {
        $user = Auth::user();

        // Contractor isolation check
        if ($user->contractor_id && $task->contractor_id !== $user->contractor_id) {
            abort(403, 'شما دسترسی به این وظیفه ندارید.');
        }

        $this->taskService->updateStatus($task->id, 'completed', $user->id);

        return redirect()->route('tasks.show', $task->id)->with('status', 'پایان وظیفه ثبت شد.');
    }

    public function addDependency(Task $task, \App\Http\Requests\Web\AddDependencyRequest $request)
    {
        try {
            $dependsOnTask = Task::findOrFail($request->input('depends_on_task_id'));
            $this->taskService->addDependency($task, $dependsOnTask, Auth::user());
            return back()->with('status', 'پیش‌نیاز با موفقیت اضافه شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function removeDependency(Task $task, \App\Models\TaskDependency $dependency)
    {
        try {
            $this->taskService->removeDependency($task, $dependency, Auth::user());
            return back()->with('status', 'پیش‌نیاز با موفقیت حذف شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
