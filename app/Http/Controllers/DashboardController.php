<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Library\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService)
    {
    }

    public function index(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return redirect()->route($user->dashboardRouteName());
    }

    public function superAdmin(Request $request): View
    {
        if ($request->user()?->hasRole(User::ROLE_SUPER_ADMIN)) {
            $request->session()->put('super_admin_area', 'system');
        }

        $statistics = [
            'users' => DB::table('users')->where('status', 'active')->count(),
            'items' => DB::table('items')->where('status', 'active')->count(),
            'assets' => DB::table('assets')->where('asset_status', '<>', 'disposed')->count(),
            'book_titles' => DB::table('items')->where('item_type', 'book')->where('status', 'active')->count(),
            'active_members' => DB::table('members')->where('status', 'active')->count(),
            'overdue_loans' => DB::table('loan_items')
                ->where('return_status', 'borrowed')
                ->whereDate('due_date', '<', today())
                ->count(),
        ];

        $administrators = DB::table('users')
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereIn('roles.role_code', [
                User::ROLE_SUPER_ADMIN,
                User::ROLE_INVENTORY_ADMIN,
                User::ROLE_LIBRARY_ADMIN,
                User::ROLE_LIBRARY_OFFICER_LEGACY,
            ])
            ->select(['users.full_name', 'users.username', 'users.status', 'roles.role_name'])
            ->orderBy('roles.role_name')
            ->orderBy('users.full_name')
            ->limit(10)
            ->get();

        return view('dashboard.super-admin', compact('statistics', 'administrators'));
    }

    public function inventory(Request $request): View
    {
        if ($request->user()?->hasRole(User::ROLE_SUPER_ADMIN)) {
            $request->session()->put('super_admin_area', 'inventory');
        }

        $statistics = [
            'items' => DB::table('items')->where('status', 'active')->count(),
            'assets' => DB::table('assets')->where('asset_status', '<>', 'disposed')->count(),
            'quantity_stock' => (float) DB::table('stock_balances')->sum('quantity'),
            'damaged_assets' => DB::table('assets')->where('condition_status', 'damaged')->count(),
            'lost_assets' => DB::table('assets')->where('condition_status', 'lost')->count(),
            'book_titles' => DB::table('items')->where('item_type', 'book')->where('status', 'active')->count(),
            'pending_opnames' => DB::table('stock_opnames')->whereIn('status', ['draft', 'in_progress'])->count(),
            'open_maintenance' => DB::table('maintenance_records')->whereIn('status', ['reported', 'in_progress'])->count(),
            'pending_disposals' => DB::table('disposals')->whereIn('status', ['proposed', 'approved'])->count(),
            'public_damage_reports' => DB::table('public_damage_reports')
                ->whereIn('status', ['submitted', 'reviewed', 'in_progress'])
                ->count(),
        ];

        $latestItems = DB::table('items')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->leftJoin('units', 'units.id', '=', 'items.unit_id')
            ->select([
                'items.id',
                'items.item_code',
                'items.item_name',
                'items.item_type',
                'items.tracking_type',
                'items.created_at',
                'categories.category_name',
                'units.unit_code',
            ])
            ->latest('items.created_at')
            ->limit(8)
            ->get();

        return view('dashboard.inventory', compact('statistics', 'latestItems'));
    }

    public function library(Request $request): View
    {
        if ($request->user()?->hasRole(User::ROLE_SUPER_ADMIN)) {
            $request->session()->put('super_admin_area', 'library');
        }

        $this->reservationService->synchronizeAll();

        $statistics = [
            'book_titles' => DB::table('items')->where('item_type', 'book')->where('status', 'active')->count(),
            'book_copies' => DB::table('assets')
                ->join('items', 'items.id', '=', 'assets.item_id')
                ->where('items.item_type', 'book')
                ->where('assets.asset_status', '<>', 'disposed')
                ->count(),
            'available_books' => DB::table('assets')
                ->join('items', 'items.id', '=', 'assets.item_id')
                ->where('items.item_type', 'book')
                ->where('assets.asset_status', 'available')
                ->count(),
            'borrowed_books' => DB::table('assets')
                ->join('items', 'items.id', '=', 'assets.item_id')
                ->where('items.item_type', 'book')
                ->where('assets.asset_status', 'borrowed')
                ->count(),
            'unprocessed_books' => DB::table('assets')
                ->join('items', 'items.id', '=', 'assets.item_id')
                ->where('items.item_type', 'book')
                ->where('assets.asset_status', 'unprocessed')
                ->count(),
            'incomplete_catalogs' => DB::table('book_details')->where('completion_status', 'incomplete')->count(),
            'active_members' => DB::table('members')->where('status', 'active')->count(),
            'overdue_loans' => DB::table('loan_items')
                ->where('return_status', 'borrowed')
                ->whereDate('due_date', '<', today())
                ->count(),
            'waiting_reservations' => DB::table('reservations')->where('status', 'waiting')->count(),
            'ready_reservations' => DB::table('reservations')->where('status', 'ready')->count(),
            'online_requests' => DB::table('loan_requests')
                ->whereIn('status', ['submitted', 'approved', 'ready'])
                ->count(),
            'unread_contact_messages' => DB::table('public_contact_messages')
                ->where('status', 'unread')
                ->count(),
        ];

        $newBooks = DB::table('items')
            ->leftJoin('book_details', 'book_details.item_id', '=', 'items.id')
            ->where('items.item_type', 'book')
            ->select([
                'items.id',
                'items.item_code',
                'items.item_name',
                'book_details.completion_status',
                'items.created_at',
            ])
            ->latest('items.created_at')
            ->limit(8)
            ->get();

        return view('dashboard.library', compact('statistics', 'newBooks'));
    }

    public function manager(): View
    {
        $statistics = [
            'items' => DB::table('items')->where('status', 'active')->count(),
            'assets' => DB::table('assets')->where('asset_status', '<>', 'disposed')->count(),
            'book_titles' => DB::table('items')->where('item_type', 'book')->where('status', 'active')->count(),
            'available_books' => DB::table('assets')
                ->join('items', 'items.id', '=', 'assets.item_id')
                ->where('items.item_type', 'book')
                ->where('assets.asset_status', 'available')
                ->count(),
            'active_members' => DB::table('members')->where('status', 'active')->count(),
            'overdue_loans' => DB::table('loan_items')
                ->where('return_status', 'borrowed')
                ->whereDate('due_date', '<', today())
                ->count(),
        ];

        return view('dashboard.manager', compact('statistics'));
    }

    public function member(): View
    {
        return view('dashboard.member');
    }
}
