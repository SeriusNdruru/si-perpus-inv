<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AcceptanceTestService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AcceptanceTestController extends Controller
{
    public function __construct(
        private readonly AcceptanceTestService $acceptanceTests,
    ) {
    }

    public function index(): View
    {
        return view('admin.acceptance-tests.index', [
            'report' => $this->acceptanceTests->run(),
        ]);
    }

    public function download(): Response
    {
        $report = $this->acceptanceTests->run();
        $filename = 'laporan_uji_akses_dan_alur_'.now()->format('Ymd_His').'.txt';

        return response(
            $this->acceptanceTests->toText($report),
            200,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
