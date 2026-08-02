<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private const ACTIONS = [
        'login' => 'Login',
        'logout' => 'Logout',
        'insert' => 'Tambah data',
        'update' => 'Ubah data',
        'delete' => 'Hapus data',
        'approve' => 'Persetujuan',
        'export' => 'Ekspor data',
        'other' => 'Aktivitas lainnya',
    ];

    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);

        $logs = $query
            ->with(['user.roles:id,role_name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $summary = [
            'total' => AuditLog::query()->count(),
            'today' => AuditLog::query()->whereDate('created_at', today())->count(),
            'auth' => AuditLog::query()->whereIn('action', ['login', 'logout'])->count(),
            'changes' => AuditLog::query()->whereIn('action', ['insert', 'update', 'delete', 'approve'])->count(),
        ];

        $modules = AuditLog::query()
            ->whereNotNull('module_name')
            ->where('module_name', '<>', '')
            ->distinct()
            ->orderBy('module_name')
            ->pluck('module_name');

        $users = User::query()
            ->whereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id'))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'username']);

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'summary' => $summary,
            'actions' => self::ACTIONS,
            'modules' => $modules,
            'users' => $users,
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load(['user.roles:id,role_name']);

        $oldData = $this->sanitizeData($auditLog->old_data ?? []);
        $newData = $this->sanitizeData($auditLog->new_data ?? []);

        return view('admin.audit-logs.show', [
            'auditLog' => $auditLog,
            'oldData' => $oldData,
            'newData' => $newData,
            'changes' => $this->buildChanges($oldData, $newData),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $filters = $request->only(['search', 'action', 'module', 'user_id', 'date_from', 'date_to']);
        $maxExportedId = (int) (AuditLog::query()->max('id') ?? 0);

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->id,
            'action' => 'export',
            'module_name' => 'audit_log',
            'table_name' => 'audit_logs',
            'record_id' => null,
            'old_data' => null,
            'new_data' => json_encode(['filters' => $filters], JSON_UNESCAPED_UNICODE),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);

        $fileName = 'riwayat-aktivitas-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request, $maxExportedId): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID',
                'Waktu',
                'Nama pengguna',
                'Username',
                'Aksi',
                'Modul',
                'Tabel',
                'ID data',
                'Alamat IP',
                'User agent',
            ], ';');

            $this->filteredQuery($request)
                ->where('id', '<=', $maxExportedId)
                ->with('user:id,full_name,username')
                ->orderBy('id')
                ->chunkById(500, function ($logs) use ($handle): void {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->id,
                            $log->created_at?->format('Y-m-d H:i:s'),
                            $this->csvSafe($log->user?->full_name ?? ($log->user_id ? 'Pengguna tidak tersedia' : 'Sistem')),
                            $this->csvSafe($log->user?->username ?? ''),
                            $log->actionLabel(),
                            $log->moduleLabel(),
                            $this->csvSafe($log->table_name ?? ''),
                            $log->record_id,
                            $this->csvSafe($log->ip_address ?? ''),
                            $this->csvSafe($log->user_agent ?? ''),
                        ], ';');
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = AuditLog::query();
        $search = trim((string) $request->query('search'));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('module_name', 'like', "%{$search}%")
                    ->orWhere('table_name', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });

                if (ctype_digit($search)) {
                    $builder
                        ->orWhere('id', (int) $search)
                        ->orWhere('record_id', (int) $search);
                }
            });
        }

        $action = (string) $request->query('action');
        if (array_key_exists($action, self::ACTIONS)) {
            $query->where('action', $action);
        }

        $module = trim((string) $request->query('module'));
        if ($module !== '') {
            $query->where('module_name', $module);
        }

        $userId = $request->integer('user_id');
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        $dateFrom = (string) $request->query('date_from');
        if ($this->isDate($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = (string) $request->query('date_to');
        if ($this->isDate($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query;
    }

    private function isDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $normalizedKey = Str::lower((string) $key);
            $isSensitive = Str::contains($normalizedKey, [
                'password', 'token', 'secret', 'authorization', 'cookie', 'api_key', 'private_key',
            ]);

            if ($isSensitive) {
                $result[(string) $key] = '[DISEMBUNYIKAN]';
                continue;
            }

            $result[(string) $key] = is_array($value)
                ? $this->sanitizeData($value)
                : $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $oldData
     * @param array<string, mixed> $newData
     * @return array<int, array{field:string,old:mixed,new:mixed,changed:bool}>
     */
    private function buildChanges(array $oldData, array $newData): array
    {
        $keys = collect(array_keys($oldData))
            ->merge(array_keys($newData))
            ->unique()
            ->sort()
            ->values();

        return $keys->map(function (string $key) use ($oldData, $newData): array {
            $oldValue = $oldData[$key] ?? null;
            $newValue = $newData[$key] ?? null;

            return [
                'field' => $key,
                'old' => $oldValue,
                'new' => $newValue,
                'changed' => json_encode($oldValue) !== json_encode($newValue),
            ];
        })->all();
    }

    private function csvSafe(string $value): string
    {
        if ($value !== '' && preg_match('/^[=+\-@]/', $value) === 1) {
            return "'{$value}";
        }

        return $value;
    }
}
