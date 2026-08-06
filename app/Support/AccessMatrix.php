<?php

namespace App\Support;

final class AccessMatrix
{
    /** @var list<string> */
    public const PUBLIC_ROUTES = [
        'public.home',
        'public.about',
        'public.contact',
        'public.catalog',
        'public.inventory.general',
        'public.inventory.audit',
        'public.inventory.report-damage',
        'login',
        'student.login',
        'register',
        'admin.password.request',
        'student.password.request',
    ];

    /**
     * Role yang diperbolehkan membuka route penting.
     *
     * @var array<string, list<string>>
     */
    public const PROTECTED_ROUTE_ROLES = [
        'dashboard.super-admin' => ['SUPER_ADMIN'],
        'dashboard.inventory' => ['SUPER_ADMIN', 'INVENTORY_ADMIN'],
        'dashboard.library' => ['SUPER_ADMIN', 'LIBRARY_ADMIN', 'LIBRARY_OFFICER'],
        'dashboard.manager' => ['SUPER_ADMIN', 'MANAGER'],
        'dashboard.member' => ['MEMBER'],

        'admin.users.index' => ['SUPER_ADMIN'],
        'admin.settings.edit' => ['SUPER_ADMIN'],
        'admin.email-notifications.index' => ['SUPER_ADMIN'],
        'admin.audit-logs.index' => ['SUPER_ADMIN'],
        'admin.database-backups.index' => ['SUPER_ADMIN'],
        'admin.system-readiness.index' => ['SUPER_ADMIN'],
        'admin.acceptance-tests.index' => ['SUPER_ADMIN'],

        'inventory.items.index' => ['SUPER_ADMIN', 'INVENTORY_ADMIN'],
        'inventory.stock-opnames.index' => ['SUPER_ADMIN', 'INVENTORY_ADMIN'],
        'inventory.maintenance-records.index' => ['SUPER_ADMIN', 'INVENTORY_ADMIN'],
        'inventory.disposals.index' => ['SUPER_ADMIN', 'INVENTORY_ADMIN'],
        'inventory.public-damage-reports.index' => ['SUPER_ADMIN', 'INVENTORY_ADMIN'],

        'library.books.index' => ['SUPER_ADMIN', 'LIBRARY_ADMIN', 'LIBRARY_OFFICER'],
        'library.members.index' => ['SUPER_ADMIN', 'LIBRARY_ADMIN', 'LIBRARY_OFFICER'],
        'library.loans.index' => ['SUPER_ADMIN', 'LIBRARY_ADMIN', 'LIBRARY_OFFICER'],
        'library.returns.index' => ['SUPER_ADMIN', 'LIBRARY_ADMIN', 'LIBRARY_OFFICER'],
        'library.fines.index' => ['SUPER_ADMIN', 'LIBRARY_ADMIN', 'LIBRARY_OFFICER'],
        'library.loan-requests.index' => ['SUPER_ADMIN', 'LIBRARY_ADMIN', 'LIBRARY_OFFICER'],

        'reports.index' => [
            'SUPER_ADMIN',
            'INVENTORY_ADMIN',
            'LIBRARY_ADMIN',
            'LIBRARY_OFFICER',
            'MANAGER',
        ],

        'member.profile.show' => ['MEMBER'],
        'member.profile.edit' => ['MEMBER'],
        'member.profile.update' => ['MEMBER'],
        'member.books.index' => ['MEMBER'],
        'member.loan-requests.index' => ['MEMBER'],
        'member.history.loans' => ['MEMBER'],
        'member.history.fines' => ['MEMBER'],
        'member.notifications.index' => ['MEMBER'],
    ];

    /**
     * Route yang harus memiliki method dan middleware keamanan tertentu.
     *
     * @var array<string, array{methods: list<string>, middleware: list<string>}>
     */
    public const SECURITY_ROUTES = [
        'login.store' => [
            'methods' => ['POST'],
            'middleware' => ['throttle:10,1'],
        ],
        'student.login.store' => [
            'methods' => ['POST'],
            'middleware' => ['throttle:10,1'],
        ],
        'register.store' => [
            'methods' => ['POST'],
            'middleware' => ['throttle:5,1'],
        ],
        'student.verification.verify' => [
            'methods' => ['GET'],
            'middleware' => ['signed', 'throttle:10,1'],
        ],
        'student.verification.resend' => [
            'methods' => ['POST'],
            'middleware' => ['throttle:3,10'],
        ],
        'student.password.email' => [
            'methods' => ['POST'],
            'middleware' => ['throttle:3,10'],
        ],
        'student.password.update' => [
            'methods' => ['POST'],
            'middleware' => ['throttle:5,10'],
        ],
        'admin.password.email' => [
            'methods' => ['POST'],
            'middleware' => ['throttle:3,10'],
        ],
        'admin.password.update' => [
            'methods' => ['POST'],
            'middleware' => ['throttle:5,10'],
        ],
        'public.contact.store' => [
            'methods' => ['POST'],
            'middleware' => ['throttle:5,1'],
        ],
        'public.inventory.report-damage.store' => [
            'methods' => ['POST'],
            'middleware' => ['throttle:5,1'],
        ],
    ];

    /** @var list<string> */
    public const INTERNAL_ROLES = [
        'SUPER_ADMIN',
        'INVENTORY_ADMIN',
        'LIBRARY_ADMIN',
        'LIBRARY_OFFICER',
        'MANAGER',
    ];

    /** @var list<string> */
    public const ELEMENTARY_CLASSES = [
        'Kelas 1',
        'Kelas 2',
        'Kelas 3',
        'Kelas 4',
        'Kelas 5',
        'Kelas 6',
    ];

    private function __construct()
    {
    }
}
