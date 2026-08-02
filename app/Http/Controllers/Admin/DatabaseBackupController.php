<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestoreDatabaseRequest;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DatabaseBackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backupService,
    ) {
    }

    public function index(): View
    {
        $directory = storage_path('app/private/'.DatabaseBackupService::DIRECTORY);

        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        $backups = collect(File::files($directory))
            ->filter(fn (\SplFileInfo $file): bool => strtolower($file->getExtension()) === 'sql')
            ->map(fn (\SplFileInfo $file): array => [
                'filename' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified_at' => \Illuminate\Support\Carbon::createFromTimestamp($file->getMTime()),
                'is_safety' => str_starts_with($file->getFilename(), 'safety_before_restore_'),
            ])
            ->sortByDesc('modified_at')
            ->values();

        return view('admin.database-backups.index', [
            'backups' => $backups,
            'totalSize' => $backups->sum('size'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $backup = $this->backupService->create();

            $this->writeAudit(
                $request,
                'insert',
                ['filename' => $backup['filename'], 'size' => $backup['size']]
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Backup gagal dibuat: '.$this->safeMessage($exception));
        }

        return back()->with('success', "Backup {$backup['filename']} berhasil dibuat.");
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = $this->backupPath($filename);

        abort_unless(is_file($path), 404);

        return response()->download(
            $path,
            basename($path),
            ['Content-Type' => 'application/sql']
        );
    }

    public function destroy(Request $request, string $filename): RedirectResponse
    {
        $path = $this->backupPath($filename);

        if (! is_file($path)) {
            return back()->with('error', 'File backup tidak ditemukan.');
        }

        $size = filesize($path) ?: 0;

        if (! @unlink($path)) {
            return back()->with('error', 'File backup tidak dapat dihapus.');
        }

        $this->writeAudit(
            $request,
            'delete',
            ['filename' => basename($path), 'size' => $size]
        );

        return back()->with('success', 'File backup berhasil dihapus.');
    }

    public function restore(RestoreDatabaseRequest $request): RedirectResponse
    {
        $uploadedFile = $request->file('backup_file');

        if ($uploadedFile === null || strtolower($uploadedFile->getClientOriginalExtension()) !== 'sql') {
            return back()
                ->withInput()
                ->withErrors(['backup_file' => 'File restore harus menggunakan ekstensi .sql.']);
        }

        try {
            $safetyBackup = $this->backupService->create('safety_before_restore');
            $result = $this->backupService->restore((string) $uploadedFile->getRealPath());

            $this->writeAudit(
                $request,
                'update',
                [
                    'restored_filename' => $uploadedFile->getClientOriginalName(),
                    'executed_statements' => $result['statements'],
                    'safety_backup' => $safetyBackup['filename'],
                ]
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'Restore berhenti: '.$this->safeMessage($exception).
                ' Periksa backup pengaman pada daftar sebelum mencoba lagi.'
            );
        }

        return redirect()
            ->route('admin.database-backups.index')
            ->with(
                'success',
                "Database berhasil dipulihkan. Backup pengaman dibuat sebagai {$safetyBackup['filename']}."
            );
    }

    private function backupPath(string $filename): string
    {
        $safeFilename = basename($filename);

        if ($safeFilename !== $filename || ! preg_match('/^[A-Za-z0-9_.-]+\.sql$/', $safeFilename)) {
            abort(404);
        }

        return storage_path(
            'app/private/'.DatabaseBackupService::DIRECTORY.'/'.$safeFilename
        );
    }

    private function writeAudit(
        Request $request,
        string $action,
        array $newData,
    ): void {
        try {
            DB::table('audit_logs')->insert([
                'user_id' => $request->user()?->id,
                'action' => $action,
                'module_name' => 'database_backup',
                'table_name' => 'database',
                'record_id' => null,
                'old_data' => null,
                'new_data' => json_encode($newData, JSON_UNESCAPED_UNICODE),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function safeMessage(Throwable $exception): string
    {
        if ($exception instanceof RuntimeException) {
            return $exception->getMessage();
        }

        return 'terjadi kesalahan pada proses database.';
    }
}
