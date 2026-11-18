<?php
declare(strict_types=1);

/** Jejak audit (hanya Super Admin / LPM). */
final class AuditController extends Controller
{
    public function index(): void
    {
        $this->requireSuperAdmin();

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $per    = (int)config('per_page', 10);
        $user   = trim((string)($_GET['user'] ?? ''));
        $action = trim((string)($_GET['action'] ?? ''));

        $result = AuditLog::paginate($page, $per, $user, $action);
        $users  = User::search();

        $this->view('audit/index', [
            'title'   => 'Jejak Audit',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page,
            'per'     => $per,
            'users'   => $users,
            'actions' => AuditLog::actions(),
            'filter'  => ['user' => $user, 'action' => $action],
        ]);
    }
}
