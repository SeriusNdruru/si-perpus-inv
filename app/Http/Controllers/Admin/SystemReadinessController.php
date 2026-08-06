<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemReadinessService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SystemReadinessController extends Controller
{
    public function __construct(
        private readonly SystemReadinessService $readinessService,
    ) {
    }

    public function index(): View
    {
        return view('admin.system-readiness.index', [
            'report' => $this->readinessService->run(),
        ]);
    }

    public function download(): Response
    {
        $report = $this->readinessService->run();
        $filename = 'laporan_pengujian_sistem_'.now()->format('Ymd_His').'.txt';

        return response(
            $this->readinessService->toText($report),
            200,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
