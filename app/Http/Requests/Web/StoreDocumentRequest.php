<?php

namespace App\Http\Requests\Web;

use App\Domain\DTOs\UploadDocumentData;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'], // max 20MB
        ];
    }

    public function toDto(): UploadDocumentData
    {
        $file = $this->file('file');

        return new UploadDocumentData(
            documentableType: Task::class,
            documentableId: $this->route('task')->id,
            file: $file,
            originalName: $file->getClientOriginalName(),
            mimeType: $file->getMimeType(),
            size: $file->getSize(),
            uploadedById: auth()->id()
        );
    }
}
