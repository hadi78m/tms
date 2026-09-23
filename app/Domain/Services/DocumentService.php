<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    protected AuditServiceInterface $auditService;

    public function __construct(AuditServiceInterface $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Uploads and attaches a document to a model.
     *
     * @throws \Exception
     */
    public function uploadDocument(Model $attachable, UploadedFile $file, User $uploader, string $disk = 'local', ?string $claimedAt = null): Document
    {
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType();
        $size = $file->getSize();

        // Calculate checksum
        $checksum = hash_file('sha256', $file->getRealPath());

        // Upload to storage FIRST (before DB transaction)
        $path = $file->store('documents', $disk);

        if (! $path) {
            throw new \Exception('Failed to upload file to storage.');
        }

        try {
            return DB::transaction(function () use ($attachable, $originalName, $path, $mimeType, $size, $checksum, $uploader, $claimedAt) {
                $document = $attachable->documents()->create([
                    'original_name' => $originalName,
                    'stored_name' => $path,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'checksum' => $checksum,
                    'file_hash' => $checksum,
                    'claimed_at' => $claimedAt,
                    'recorded_at' => now(),
                    'uploaded_by' => $uploader->id,
                ]);

                // V1.8 (H-6 · DEC-032): explicit emission inside the SAME
                // business transaction. No Observer — an automatic one would
                // double-log alongside the explicit document_deleted event.
                $this->auditService->log(
                    'document_uploaded',
                    $document,
                    $uploader,
                    [],
                    $document->toArray()
                );

                return $document;
            });
        } catch (\Exception $e) {
            // DB Transaction failed, remove the orphaned file from physical storage
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    /**
     * Soft deletes a document and records an audit log.
     */
    public function deleteDocument(Document $document, User $deleter, ?string $reason = null): void
    {
        DB::transaction(function () use ($document, $deleter, $reason) {
            $document->delete();

            $this->auditService->log(
                'document_deleted',
                $document,
                $deleter,
                $document->toArray(),
                [],
                ['reason' => $reason]
            );
        });
    }
}
