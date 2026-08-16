<?php

namespace App\Services\Escrow;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores/serves the optional evidence a freelancer attaches at delivery
 * (see EscrowService::deliver()) — kept out of both controllers that need
 * it (EscrowDashboardController, MiniApp\CustomerController) so the disk
 * name and the "not found" fallback only exist in one place.
 */
class EscrowProofStorage
{
    private const DISK = 'escrow-proofs';

    public function store(UploadedFile $file): string
    {
        return $file->store('proofs', self::DISK);
    }

    public function response(string $path): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($path);
    }

    /** Cleans up a just-uploaded file when EscrowService::deliver() rejects the request (wrong status, wrong caller). */
    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }
}
