<?php
declare(strict_types=1);

final class NotificationController extends Controller
{
    public function index(): void
    {
        $userId = (int)Auth::id();
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $per    = (int)config('per_page', 10);

        $result = Notification::allFor($userId, $page, $per);

        $this->view('notifications/index', [
            'title' => 'Notifikasi',
            'rows'  => $result['rows'],
            'total' => $result['total'],
            'page'  => $page,
            'per'   => $per,
        ]);
    }

    public function readAll(): void
    {
        Notification::markAllRead((int)Auth::id());
        flash('success', 'Semua notifikasi ditandai sudah dibaca.');
        $this->redirect('/notifications');
    }
}
