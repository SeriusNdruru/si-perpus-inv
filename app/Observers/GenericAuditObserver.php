<?php

namespace App\Observers;

use App\Models\Asset;
use App\Models\Author;
use App\Models\BookDetail;
use App\Models\Category;
use App\Models\Disposal;
use App\Models\Item;
use App\Models\LibraryShelf;
use App\Models\LibraryVisit;
use App\Models\Location;
use App\Models\LoanRequest;
use App\Models\MaintenanceRecord;
use App\Models\Member;
use App\Models\Publisher;
use App\Models\PublicContactMessage;
use App\Models\PublicDamageReport;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class GenericAuditObserver
{
    public function created(Model $model): void
    {
        if ($model instanceof Asset) {
            return;
        }

        $this->write($model, 'insert', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = collect($model->getChanges())
            ->except(['updated_at'])
            ->all();

        if ($changes === []) {
            return;
        }

        $oldData = [];
        foreach (array_keys($changes) as $key) {
            $oldData[$key] = $model->getOriginal($key);
        }

        $this->write($model, 'update', $oldData, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'delete', $model->getAttributes(), null);
    }

    /**
     * @param array<string, mixed>|null $oldData
     * @param array<string, mixed>|null $newData
     */
    private function write(Model $model, string $action, ?array $oldData, ?array $newData): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        try {
            $request = request();

            DB::table('audit_logs')->insert([
                'user_id' => Auth::id(),
                'action' => $action,
                'module_name' => $this->moduleName($model),
                'table_name' => $model->getTable(),
                'record_id' => is_numeric($model->getKey()) ? (int) $model->getKey() : null,
                'old_data' => $oldData !== null
                    ? json_encode($this->sanitize($oldData), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
                    : null,
                'new_data' => $newData !== null
                    ? json_encode($this->sanitize($newData), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
                    : null,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function moduleName(Model $model): string
    {
        return match (true) {
            $model instanceof Category => 'master_categories',
            $model instanceof Unit => 'master_units',
            $model instanceof Supplier => 'master_suppliers',
            $model instanceof Location => 'master_locations',
            $model instanceof Item => 'inventory_items',
            $model instanceof Asset => 'inventory_assets',
            $model instanceof BookDetail => 'library_catalog',
            $model instanceof Publisher => 'library_publishers',
            $model instanceof Author => 'library_authors',
            $model instanceof LibraryShelf => 'library_shelves',
            $model instanceof LibraryVisit => 'library_visits',
            $model instanceof Member => 'library_members',
            $model instanceof StockOpname => 'inventory_stock_opname',
            $model instanceof MaintenanceRecord => 'inventory_maintenance',
            $model instanceof Disposal => 'inventory_disposal',
            $model instanceof LoanRequest => 'library_loan_requests',
            $model instanceof PublicDamageReport => 'public_damage_reports',
            $model instanceof PublicContactMessage => 'public_contact_messages',
            default => Str::snake(class_basename($model)),
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $normalizedKey = Str::lower((string) $key);

            if (Str::contains($normalizedKey, [
                'password', 'token', 'secret', 'authorization', 'cookie', 'api_key', 'private_key',
            ])) {
                $result[(string) $key] = '[DISEMBUNYIKAN]';
                continue;
            }

            $result[(string) $key] = is_array($value)
                ? $this->sanitize($value)
                : $value;
        }

        return $result;
    }
}
