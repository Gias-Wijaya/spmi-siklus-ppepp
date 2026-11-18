<?php
declare(strict_types=1);

final class DashboardController extends Controller
{
    public function index(): void
    {
        $user  = Auth::user();
        $colors = Document::countsByColor();

        $stats = [
            'total'     => Document::count(),
            'green'     => $colors['green'],
            'yellow'    => $colors['yellow'],
            'red'       => $colors['red'],
            'pending'   => Document::count("approval_status = 'submitted'"),
            'draft'     => Document::count("approval_status = 'draft'"),
            'users'     => User::count(),
            'units'     => Unit::count(),
            'actions'   => ActionPlan::count("status <> 'verified'"),
            'findings'  => Finding::count("status IN ('open','in_progress')"),
            'monev'     => MonevReport::count(),
            'ami'       => AmiAssessment::count(),
        ];

        $pending     = Auth::isSuperAdmin() ? Document::pending() : [];
        $myDocuments = Database::all(
            'SELECT d.*, c.name AS category_name, un.name AS unit_name
               FROM documents d
               LEFT JOIN document_categories c ON c.id = d.category_id
               LEFT JOIN units un ON un.id = d.unit_id
              WHERE d.owner_user_id = ?
              ORDER BY d.updated_at DESC
              LIMIT 6',
            [(int)Auth::id()]
        );

        $latestDocs = Database::all(
            'SELECT d.*, un.name AS unit_name
               FROM documents d
               LEFT JOIN units un ON un.id = d.unit_id
              ORDER BY d.updated_at DESC
              LIMIT 6'
        );

        $notifications = Notification::latest((int)Auth::id(), 8);

        $this->view('dashboard/index', [
            'title'         => 'Dasbor',
            'user'          => $user,
            'stats'         => $stats,
            'pending'       => $pending,
            'myDocuments'   => $myDocuments,
            'latestDocs'    => $latestDocs,
            'notifications' => $notifications,
        ]);
    }
}
