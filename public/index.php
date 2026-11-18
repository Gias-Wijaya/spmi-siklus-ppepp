<?php
declare(strict_types=1);

/**
 * Front controller — Sistem Informasi Penjaminan Mutu Internal
 * STIKOM El Rahma · PHP 8.5 · MySQL · Bootstrap 5 (tanpa framework)
 *
 * Akses: http://localhost/lpm/public/
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/core/helpers.php';
require BASE_PATH . '/app/core/Database.php';
require BASE_PATH . '/app/core/Model.php';
require BASE_PATH . '/app/core/Auth.php';
require BASE_PATH . '/app/core/Controller.php';
require BASE_PATH . '/app/core/Router.php';

foreach (glob(BASE_PATH . '/app/models/*.php') ?: [] as $file) {
    require $file;
}
foreach (glob(BASE_PATH . '/app/controllers/*.php') ?: [] as $file) {
    require $file;
}

session_start();
date_default_timezone_set('Asia/Jakarta');

// Refresh status masa berlaku dokumen (hijau/kuning/merah) sekali per kunjungan.
try {
    Document::refreshStatusColors();
} catch (Throwable $e) {
    // Basis data belum diimpor — biarkan; halaman login tetap dapat dibuka.
}

// FR-15 — penjaga tenggat: notifikasi masa berlaku & tenggat Monev/AMI/tindak lanjut.
// Berjalan setiap permintaan seperti penjaga warna; kegagalan tidak boleh menghentikan alur.
try {
    Notification::checkDeadlines();
} catch (Throwable $e) {
    // Tabel belum siap atau data belum lengkap — abaikan; pemeriksaan berikutnya mencoba lagi.
}

$router = new Router();

/* ---------------------------------------------------------------- Autentikasi */
$router->get('/login',  'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');

/* ---------------------------------------------------------------- Dasbor (M5) */
$router->get('/', 'DashboardController@index');

/* ---------------------------------------------------------------- M1 Dokumen SPMI */
$router->get('/documents',              'DocumentController@index');
$router->get('/documents/export',       'DocumentController@export'); // FR-14 — sebelum /documents/{id}
$router->get('/documents/create',       'DocumentController@create');
$router->post('/documents',             'DocumentController@store');
$router->get('/documents/{id}/edit',    'DocumentController@edit');
$router->post('/documents/{id}',        'DocumentController@update');
$router->get('/documents/{id}',         'DocumentController@show');
$router->post('/documents/{id}/submit', 'DocumentController@submit');
$router->post('/documents/{id}/approve','DocumentController@approve');
$router->post('/documents/{id}/reject', 'DocumentController@reject');
$router->post('/documents/{id}/archive','DocumentController@archive');
$router->post('/documents/{id}/revise', 'DocumentController@revise');
$router->get('/documents/{id}/download','DocumentController@download');

/* ---------------------------------------------------------------- M2 Monev */
$router->get('/monev',                        'MonevController@index');
$router->get('/monev/export',                 'MonevController@export');   // FR-14
$router->get('/monev/periods',                'MonevController@periods');
$router->post('/monev/periods',               'MonevController@storePeriod');
$router->post('/monev/periods/{id}/toggle',   'MonevController@togglePeriod');
$router->get('/monev/indicators',             'MonevController@indicators');
$router->post('/monev/indicators',            'MonevController@storeIndicator');
$router->get('/monev/indicators/{id}/edit',   'MonevController@editIndicator');
$router->post('/monev/indicators/{id}',       'MonevController@updateIndicator');
$router->get('/monev/report',                 'MonevController@report');
$router->post('/monev/report',                'MonevController@saveReport');
$router->get('/monev/evidence/{id}',          'MonevController@downloadEvidence');
$router->get('/monev/reports/{id}',           'MonevController@showReport');
$router->post('/monev/reports/{id}/approve',  'MonevController@approveReport');
$router->post('/monev/reports/{id}/return',   'MonevController@returnReport');

/* ---------------------------------------------------------------- M3 AMI & Evaluasi */
$router->get('/ami',                                  'AmiController@index');
$router->get('/ami/export',                           'AmiController@export');          // FR-14
$router->get('/ami/schedules',                        'AmiController@schedules');
$router->post('/ami/schedules',                       'AmiController@storeSchedule');
$router->post('/ami/schedules/{id}/toggle',           'AmiController@toggleSchedule');
$router->get('/ami/instruments',                      'AmiController@instruments');
$router->post('/ami/instruments',                     'AmiController@storeInstrument');
$router->get('/ami/instruments/{id}/edit',            'AmiController@editInstrument');
$router->post('/ami/instruments/{id}',                'AmiController@updateInstrument');
$router->post('/ami/instruments/{id}/toggle',         'AmiController@toggleInstrument');
$router->post('/ami/instruments/{id}/delete',         'AmiController@deleteInstrument');
$router->get('/ami/assignments',                      'AmiController@assignments');
$router->post('/ami/assignments',                     'AmiController@storeAssignment');
$router->get('/ami/assignments/{id}/edit',            'AmiController@editAssignment');
$router->post('/ami/assignments/{id}',                'AmiController@updateAssignment');
$router->post('/ami/assignments/{id}/delete',         'AmiController@deleteAssignment');
$router->get('/ami/assignments/{id}/assess',          'AmiController@assess');
$router->post('/ami/assignments/{id}/assess',         'AmiController@saveAssessments');
$router->get('/ami/findings',                         'AmiController@findings');
$router->post('/ami/findings',                        'AmiController@storeFinding');
$router->post('/ami/findings/{id}',                   'AmiController@updateFinding');
$router->get('/ami/report',                           'AmiController@report');
$router->get('/ami/evidence/{id}',                    'AmiController@downloadEvidence');
$router->get('/ami/satisfaction',                     'AmiController@satisfaction');
$router->post('/ami/satisfaction',                    'AmiController@storeSurvey');
$router->post('/ami/satisfaction/{id}/toggle',        'AmiController@toggleSurvey');
$router->get('/ami/satisfaction/{id}/questions',      'AmiController@questions');
$router->post('/ami/satisfaction/{id}/questions',     'AmiController@storeQuestion');
$router->post('/ami/questions/{id}',                  'AmiController@updateQuestion');
$router->post('/ami/questions/{id}/delete',           'AmiController@deleteQuestion');
$router->get('/ami/satisfaction/{id}/fill',           'AmiController@fill');
$router->post('/ami/satisfaction/{id}/fill',          'AmiController@submitFill');
$router->get('/ami/satisfaction/{id}/recap',          'AmiController@recap');

/* --------------------------------------------- M4 Pengendalian–Peningkatan (FR-09–FR-12) */
$router->get('/followup',                            'ActionPlanController@index');
$router->get('/followup/export',                     'ActionPlanController@export');   // FR-14
$router->post('/followup/plans',                     'ActionPlanController@store');
$router->get('/followup/plans/{id}',                 'ActionPlanController@show');
$router->post('/followup/plans/{id}',                'ActionPlanController@update');
$router->post('/followup/plans/{id}/status',         'ActionPlanController@updateStatus');
$router->post('/followup/plans/{id}/evidence',       'ActionPlanController@uploadEvidence');
$router->post('/followup/evidence/{id}/verify',      'ActionPlanController@verify');
$router->get('/followup/evidence/{id}/download',     'ActionPlanController@downloadEvidence');
$router->post('/followup/plans/{id}/revision',       'ActionPlanController@requestRevision');

/* ---------------------------------------------------------------- Pengguna & unit (RBAC) */
$router->get('/users',              'UserController@index');
$router->get('/users/create',       'UserController@create');
$router->post('/users',             'UserController@store');
$router->get('/users/{id}/edit',    'UserController@edit');
$router->post('/users/{id}',        'UserController@update');
$router->post('/users/{id}/delete', 'UserController@destroy');

$router->get('/units',           'UnitController@index');
$router->post('/units',          'UnitController@store');
$router->get('/units/{id}/edit', 'UnitController@edit');
$router->post('/units/{id}',     'UnitController@update');
$router->post('/units/{id}/delete', 'UnitController@destroy');

/* ---------------------------------------------------------------- Pencarian global */
$router->get('/search',                   'SearchController@index');

/* ---------------------------------------------------------------- Notifikasi & jejak audit (M5) */
$router->get('/notifications',            'NotificationController@index');
$router->post('/notifications/read-all',  'NotificationController@readAll');
$router->get('/audit',                    'AuditController@index');

try {
    $router->dispatch();
} catch (PDOException $e) {
    // Basis data belum siap — tampilkan petunjuk ramah, bukan stack trace.
    http_response_code(500);
    $title    = '500 — Basis data belum siap';
    $message  = 'Pastikan database `spmi_stikom` sudah diimpor dari sistem/desain/03-database.sql '
              . 'melalui phpMyAdmin, lalu periksa kredensial di app/config/config.php. '
              . 'Detail: ' . $e->getMessage();
    $home     = url('/login');
    include BASE_PATH . '/app/views/errors/shell.php';
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    $title   = '500 — Terjadi kesalahan';
    $message = $e->getMessage();
    $home    = url('/login');
    include BASE_PATH . '/app/views/errors/shell.php';
    exit;
}
