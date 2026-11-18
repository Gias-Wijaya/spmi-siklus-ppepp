<?php
declare(strict_types=1);

/**
 * M3 — AMI & Evaluasi (FR-06, FR-07, FR-08):
 *  - instrumen AMI fleksibel + jejak perubahan (ami_instrument_logs);
 *  - jadwal tahunan & penugasan auditor–auditee per unit;
 *  - penilaian skor 0–4 + bukti → temuan otomatis (skor ≤ 2);
 *  - rekap skor per unit/standar/indikator + analisis gap;
 *  - formulir kepuasan: kelola, isi, rekap otomatis.
 */
final class AmiController extends Controller
{
    /* ======================================================== Dasbor (FR-07/08) */

    public function index(): void
    {
        $periods = AmiSchedule::ordered();

        $scheduleId = (int)($_GET['schedule_id'] ?? 0);
        $schedule   = $scheduleId > 0 ? AmiSchedule::find($scheduleId) : null;
        if (!$schedule) {
            $schedule   = AmiSchedule::activeOrLatest();
            $scheduleId = $schedule ? (int)$schedule['id'] : 0;
        }

        $progress      = $schedule ? AmiAssessment::progress($scheduleId) : null;
        $unitScores    = $schedule ? AmiAssessment::unitScores($scheduleId) : [];
        $standardRows  = $schedule ? AmiAssessment::standardScores($scheduleId) : [];
        $assignments   = $schedule ? AmiAssignment::forSchedule($scheduleId) : [];
        $openFindings  = $schedule ? Finding::openCount($scheduleId) : 0;
        $recentFinding = Finding::recent(6);
        $surveys       = SatisfactionSurvey::withStats();

        $chartUnits = [
            'labels' => array_map(static fn ($u) => $u['unit_name'], $unitScores),
            'scores' => array_map(
                static fn ($u) => $u['avg_score'] !== null ? (float)$u['avg_score'] : null,
                $unitScores
            ),
        ];
        $chartStd = [
            'labels' => array_map(
                static fn ($s) => $s['standard_code'] ?? 'Tanpa standar',
                $standardRows
            ),
            'scores' => array_map(
                static fn ($s) => $s['avg_score'] !== null ? (float)$s['avg_score'] : null,
                $standardRows
            ),
        ];

        $this->view('ami/index', [
            'title'         => 'Dasbor AMI',
            'periods'       => $periods,
            'schedule'      => $schedule,
            'progress'      => $progress,
            'unitScores'    => $unitScores,
            'standardRows'  => $standardRows,
            'assignments'   => $assignments,
            'openFindings'  => $openFindings,
            'recentFinding' => $recentFinding,
            'surveys'       => $surveys,
            'chartUnits'    => $chartUnits,
            'chartStd'      => $chartStd,
        ]);
    }

    /* ====================================================== Jadwal (Super) */

    public function schedules(): void
    {
        $this->requireSuperAdmin();

        $this->view('ami/schedules', [
            'title'    => 'Jadwal AMI',
            'periods'  => AmiSchedule::ordered(),
            'errors'   => [],
        ]);
    }

    public function storeSchedule(): void
    {
        $this->requireSuperAdmin();

        $year  = trim((string)($_POST['year'] ?? ''));
        $name  = trim((string)($_POST['name'] ?? ''));
        $start = trim((string)($_POST['start_date'] ?? ''));
        $end   = trim((string)($_POST['end_date'] ?? ''));

        $errors = [];
        if (!preg_match('/^\d{4}$/', $year)) {
            $errors['year'] = 'Tahun wajib diisi (4 digit).';
        } elseif (AmiSchedule::yearExists((int)$year)) {
            $errors['year'] = 'AMI untuk tahun tersebut sudah ada.';
        }
        if ($name === '') {
            $errors['name'] = 'Nama jadwal wajib diisi.';
        } elseif (AmiSchedule::nameExists($name)) {
            $errors['name'] = 'Nama jadwal sudah dipakai.';
        }
        if ($start !== '' && $end !== '' && $end < $start) {
            $errors['end_date'] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }

        if ($errors) {
            flash('danger', 'Periksa kembali isian formulir jadwal AMI.');
            $this->view('ami/schedules', [
                'title'   => 'Jadwal AMI',
                'periods' => AmiSchedule::ordered(),
                'errors'  => $errors,
            ]);
        }

        $id = AmiSchedule::create([
            'year'       => (int)$year,
            'name'       => $name,
            'start_date' => $start !== '' ? $start : null,
            'end_date'   => $end !== '' ? $end : null,
            'status'     => 'draft',
        ]);

        audit('create', 'ami_schedules', $id, ['name' => $name, 'year' => (int)$year]);
        flash('success', 'Jadwal "' . $name . '" dibuat (status draf). Susun instrumen & penugasan sebelum dijalankan.');
        $this->redirect('/ami/schedules');
    }

    public function toggleSchedule(string $id): void
    {
        $this->requireSuperAdmin();

        $schedule = AmiSchedule::find((int)$id);
        if (!$schedule) {
            $this->notFound('Jadwal AMI tidak ditemukan.');
        }

        $new = match ($schedule['status']) {
            'draft'   => 'running',
            'running' => 'closed',
            default   => 'running',
        };
        AmiSchedule::updateById((int)$id, ['status' => $new]);

        audit('update', 'ami_schedules', (int)$id, ['from' => $schedule['status'], 'to' => $new]);
        flash(
            'success',
            match ($new) {
                'running' => $schedule['status'] === 'draft'
                    ? 'Jadwal "' . $schedule['name'] . '" dijalankan — penilaian & instrumen dapat diisi.'
                    : 'Jadwal "' . $schedule['name'] . '" dibuka kembali.',
                default   => 'Jadwal "' . $schedule['name'] . '" ditutup — instrumen & penilaian terkunci.',
            }
        );
        $this->redirect('/ami/schedules');
    }

    /* ============================================ Instrumen fleksibel (FR-06) */

    public function instruments(): void
    {
        $this->requireSuperAdmin();

        $schedule = $this->selectedSchedule();
        $sid      = $schedule ? (int)$schedule['id'] : 0;

        $this->view('ami/instruments', [
            'title'       => 'Instrumen AMI',
            'periods'     => AmiSchedule::ordered(),
            'schedule'    => $schedule,
            'instruments' => $sid > 0 ? AmiInstrument::forSchedule($sid) : [],
            'standards'   => Standard::active(),
            'logs'        => AmiInstrument::recentLogs(15),
            'errors'      => [],
        ]);
    }

    public function storeInstrument(): void
    {
        $this->requireSuperAdmin();

        $schedule = AmiSchedule::find((int)($_POST['schedule_id'] ?? 0));
        $data     = $this->instrumentPayload();
        $errors   = $this->validateInstrument($data, $schedule, 0);

        if ($errors) {
            flash('danger', 'Periksa kembali isian instrumen AMI.');
            $this->view('ami/instruments', [
                'title'       => 'Instrumen AMI',
                'periods'     => AmiSchedule::ordered(),
                'schedule'    => $schedule,
                'instruments' => $schedule ? AmiInstrument::forSchedule((int)$schedule['id']) : [],
                'standards'   => Standard::active(),
                'logs'        => AmiInstrument::recentLogs(15),
                'errors'      => $errors,
            ]);
        }

        $data['schedule_id'] = (int)$schedule['id'];
        $data['created_by']  = Auth::id();
        $id = AmiInstrument::create($data);

        audit('create', 'ami_instruments', $id, ['code' => $data['code'], 'schedule' => $schedule['name']]);
        flash('success', 'Instrumen "' . $data['code'] . '" ditambahkan ke ' . $schedule['name'] . '.');
        $this->redirect('/ami/instruments?schedule_id=' . (int)$schedule['id']);
    }

    public function editInstrument(string $id): void
    {
        $this->requireSuperAdmin();

        $instrument = AmiInstrument::find((int)$id);
        if (!$instrument) {
            $this->notFound('Instrumen tidak ditemukan.');
        }
        $schedule = AmiSchedule::find((int)$instrument['schedule_id']);

        $this->view('ami/instrument-form', [
            'title'      => 'Edit Instrumen AMI',
            'instrument' => $instrument,
            'schedule'   => $schedule,
            'standards'  => Standard::active(),
            'logs'       => AmiInstrument::logsFor((int)$id),
            'errors'     => [],
        ]);
    }

    public function updateInstrument(string $id): void
    {
        $this->requireSuperAdmin();

        $instrument = AmiInstrument::find((int)$id);
        if (!$instrument) {
            $this->notFound('Instrumen tidak ditemukan.');
        }
        $schedule = AmiSchedule::find((int)$instrument['schedule_id']);
        $data     = $this->instrumentPayload();
        $errors   = $this->validateInstrument($data, $schedule, (int)$id);

        if ($errors) {
            flash('danger', 'Periksa kembali isian instrumen AMI.');
            $this->view('ami/instrument-form', [
                'title'      => 'Edit Instrumen AMI',
                'instrument' => array_merge($instrument, $data),
                'schedule'   => $schedule,
                'standards'  => Standard::active(),
                'logs'       => AmiInstrument::logsFor((int)$id),
                'errors'     => $errors,
            ]);
        }

        // Jejak perubahan (RM 3): bandingkan nilai lama ↔ baru.
        $labels = [
            'code'             => 'kode',
            'question'         => 'indikator',
            'assessment_guide' => 'pedoman penilaian',
            'standard_id'      => 'standar',
            'weight'           => 'bobot',
            'sort_order'       => 'urutan',
            'is_active'        => 'status aktif',
        ];
        $oldLines = [];
        $newLines = [];
        foreach ($labels as $field => $label) {
            $old = $instrument[$field] ?? null;
            $new = $data[$field] ?? null;
            // Field numerik dibandingkan secara numerik (DECIMAL '2.00' === 2.0).
            if (in_array($field, ['weight', 'sort_order', 'standard_id', 'is_active'], true)) {
                $changed = (float)($old ?? 0) !== (float)($new ?? 0);
            } else {
                $changed = (string)($old ?? '') !== (string)($new ?? '');
            }
            if ($changed) {
                $oldLines[] = $label . ': ' . ($old === null || $old === '' ? '(kosong)' : $old);
                $newLines[] = $label . ': ' . ($new === null || $new === '' ? '(kosong)' : $new);
            }
        }
        $reason = trim((string)($_POST['reason'] ?? ''));

        AmiInstrument::updateById((int)$id, $data);

        if ($oldLines) {
            AmiInstrument::logChange(
                (int)$id,
                Auth::id(),
                implode("\n", $oldLines),
                implode("\n", $newLines),
                $reason
            );
        }
        audit('update', 'ami_instruments', (int)$id, ['code' => $data['code'], 'changed' => count($oldLines)]);
        flash('success', $oldLines
            ? 'Instrumen diperbarui — ' . count($oldLines) . ' perubahan tercatat di jejak perubahan.'
            : 'Instrumen disimpan tanpa perubahan.');
        $this->redirect('/ami/instruments?schedule_id=' . (int)$instrument['schedule_id']);
    }

    public function toggleInstrument(string $id): void
    {
        $this->requireSuperAdmin();

        $instrument = AmiInstrument::find((int)$id);
        if (!$instrument) {
            $this->notFound('Instrumen tidak ditemukan.');
        }
        $schedule = AmiSchedule::find((int)$instrument['schedule_id']);
        if ($schedule && $schedule['status'] === 'closed') {
            flash('warning', 'Jadwal sudah ditutup — instrumen terkunci.');
            $this->redirect('/ami/instruments?schedule_id=' . (int)$instrument['schedule_id']);
        }

        $new = (int)$instrument['is_active'] === 1 ? 0 : 1;
        AmiInstrument::updateById((int)$id, ['is_active' => $new]);
        audit('update', 'ami_instruments', (int)$id, ['is_active' => $new]);
        flash('success', $new === 1 ? 'Instrumen diaktifkan.' : 'Instrumen dinonaktifkan (nilai lama tetap tersimpan).');
        $this->redirect('/ami/instruments?schedule_id=' . (int)$instrument['schedule_id']);
    }

    public function deleteInstrument(string $id): void
    {
        $this->requireSuperAdmin();

        $instrument = AmiInstrument::find((int)$id);
        if (!$instrument) {
            $this->notFound('Instrumen tidak ditemukan.');
        }
        if (AmiInstrument::assessedCount((int)$id) > 0) {
            flash('warning', 'Instrumen memiliki nilai penilaian — gunakan "Nonaktifkan" agar riwayat nilai tidak hilang.');
            $this->redirect('/ami/instruments?schedule_id=' . (int)$instrument['schedule_id']);
        }

        AmiInstrument::deleteById((int)$id);
        audit('delete', 'ami_instruments', (int)$id, ['code' => $instrument['code']]);
        flash('success', 'Instrumen "' . $instrument['code'] . '" dihapus.');
        $this->redirect('/ami/instruments?schedule_id=' . (int)$instrument['schedule_id']);
    }

    private function instrumentPayload(): array
    {
        $standardId = (int)($_POST['standard_id'] ?? 0);

        return [
            'code'             => trim((string)($_POST['code'] ?? '')),
            'question'         => trim((string)($_POST['question'] ?? '')),
            'assessment_guide' => trim((string)($_POST['assessment_guide'] ?? '')) ?: null,
            'standard_id'      => $standardId > 0 ? $standardId : null,
            'weight'           => (float)($_POST['weight'] ?? 1),
            'sort_order'       => (int)($_POST['sort_order'] ?? 0),
            'is_active'        => !empty($_POST['is_active']) ? 1 : 0,
        ];
    }

    private function validateInstrument(array $data, ?array $schedule, int $exceptId): array
    {
        $errors = [];
        if (!$schedule) {
            $errors['schedule_id'] = 'Jadwal AMI wajib dipilih.';
            return $errors;
        }
        if ($schedule['status'] === 'closed') {
            $errors['schedule_id'] = 'Jadwal sudah ditutup — instrumen terkunci.';
        }
        if ($data['code'] === '') {
            $errors['code'] = 'Kode indikator wajib diisi (contoh: A.1).';
        } elseif (AmiInstrument::codeExists((int)$schedule['id'], $data['code'], $exceptId)) {
            $errors['code'] = 'Kode sudah dipakai pada jadwal ini.';
        }
        if ($data['question'] === '') {
            $errors['question'] = 'Uraian indikator wajib diisi.';
        }
        if ($data['weight'] <= 0) {
            $errors['weight'] = 'Bobot harus lebih besar dari 0.';
        }
        return $errors;
    }

    /* ========================================== Penugasan auditor (FR-07) */

    public function assignments(): void
    {
        $this->requireSuperAdmin();

        $schedule = $this->selectedSchedule();
        $sid      = $schedule ? (int)$schedule['id'] : 0;

        $assignments   = $sid > 0 ? AmiAssignment::forSchedule($sid) : [];
        $assignedUnitIds = array_map('intval', array_column($assignments, 'unit_id'));

        // Jumlah temuan per penugasan (satu query, tanpa N+1 di view).
        $findingCounts = [];
        foreach ($sid > 0 ? Finding::forSchedule($sid) : [] as $f) {
            $key = (int)$f['assignment_id'];
            $findingCounts[$key] = ($findingCounts[$key] ?? 0) + 1;
        }

        $this->view('ami/assignments', [
            'title'         => 'Penugasan AMI',
            'periods'       => AmiSchedule::ordered(),
            'schedule'      => $schedule,
            'assignments'   => $assignments,
            'findingCounts' => $findingCounts,
            'freeUnits'     => array_values(array_filter(
                Unit::active(),
                static fn ($u) => !in_array((int)$u['id'], $assignedUnitIds, true)
            )),
            'users'        => Database::all('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name'),
            'errors'       => [],
        ]);
    }

    public function storeAssignment(): void
    {
        $this->requireSuperAdmin();

        $schedule = AmiSchedule::find((int)($_POST['schedule_id'] ?? 0));
        if (!$schedule) {
            flash('danger', 'Jadwal AMI tidak ditemukan.');
            $this->redirect('/ami/assignments');
        }
        if ($schedule['status'] === 'closed') {
            flash('warning', 'Jadwal sudah ditutup — penugasan terkunci.');
            $this->redirect('/ami/assignments?schedule_id=' . (int)$schedule['id']);
        }

        $unitId = (int)($_POST['unit_id'] ?? 0);
        $errors = [];
        if ($unitId <= 0 || !Unit::find($unitId)) {
            $errors['unit_id'] = 'Unit kerja wajib dipilih.';
        } elseif (AmiAssignment::unitExists((int)$schedule['id'], $unitId)) {
            $errors['unit_id'] = 'Unit tersebut sudah ditugaskan pada jadwal ini.';
        }

        if ($errors) {
            flash('danger', implode(' ', array_values($errors)));
            $this->redirect('/ami/assignments?schedule_id=' . (int)$schedule['id']);
        }

        $auditor = (int)($_POST['auditor_user_id'] ?? 0);
        $lead    = (int)($_POST['auditee_lead_user_id'] ?? 0);
        $date    = trim((string)($_POST['audit_date'] ?? ''));

        $id = AmiAssignment::create([
            'schedule_id'          => (int)$schedule['id'],
            'unit_id'              => $unitId,
            'auditor_user_id'      => $auditor > 0 ? $auditor : null,
            'auditee_lead_user_id' => $lead > 0 ? $lead : null,
            'status'               => 'assigned',
            'audit_date'           => $date !== '' ? $date : null,
        ]);

        audit('create', 'ami_assignments', $id, ['schedule' => $schedule['name'], 'unit_id' => $unitId]);
        if ($auditor > 0) {
            Notification::send(
                $auditor,
                'Penugasan auditor AMI',
                'Anda ditugaskan sebagai auditor pada "' . $schedule['name'] . '".',
                'temuan',
                'ami_assignments',
                $id
            );
        }
        flash('success', 'Penugasan auditor–auditee berhasil dibuat.');
        $this->redirect('/ami/assignments?schedule_id=' . (int)$schedule['id']);
    }

    public function editAssignment(string $id): void
    {
        $this->requireSuperAdmin();

        $assignment = AmiAssignment::withDetails((int)$id);
        if (!$assignment) {
            $this->notFound('Penugasan tidak ditemukan.');
        }

        $this->view('ami/assignment-form', [
            'title'      => 'Edit Penugasan AMI',
            'assignment' => $assignment,
            'users'      => Database::all('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function updateAssignment(string $id): void
    {
        $this->requireSuperAdmin();

        $assignment = AmiAssignment::withDetails((int)$id);
        if (!$assignment) {
            $this->notFound('Penugasan tidak ditemukan.');
        }
        if ($assignment['schedule_status'] === 'closed') {
            flash('warning', 'Jadwal sudah ditutup — penugasan terkunci.');
            $this->redirect('/ami/assignments?schedule_id=' . (int)$assignment['schedule_id']);
        }

        $auditor = (int)($_POST['auditor_user_id'] ?? 0);
        $lead    = (int)($_POST['auditee_lead_user_id'] ?? 0);
        $date    = trim((string)($_POST['audit_date'] ?? ''));
        $number  = trim((string)($_POST['report_number'] ?? ''));

        AmiAssignment::updateById((int)$id, [
            'auditor_user_id'      => $auditor > 0 ? $auditor : null,
            'auditee_lead_user_id' => $lead > 0 ? $lead : null,
            'audit_date'           => $date !== '' ? $date : null,
            'report_number'        => $number !== '' ? $number : null,
        ]);

        audit('update', 'ami_assignments', (int)$id, ['unit' => $assignment['unit_name']]);
        flash('success', 'Penugasan unit "' . $assignment['unit_name'] . '" diperbarui.');
        $this->redirect('/ami/assignments?schedule_id=' . (int)$assignment['schedule_id']);
    }

    public function deleteAssignment(string $id): void
    {
        $this->requireSuperAdmin();

        $assignment = AmiAssignment::withDetails((int)$id);
        if (!$assignment) {
            $this->notFound('Penugasan tidak ditemukan.');
        }
        if ($assignment['schedule_status'] === 'closed') {
            flash('warning', 'Jadwal sudah ditutup — penugasan terkunci.');
            $this->redirect('/ami/assignments?schedule_id=' . (int)$assignment['schedule_id']);
        }

        AmiAssignment::deleteById((int)$id);
        audit('delete', 'ami_assignments', (int)$id, ['unit' => $assignment['unit_name']]);
        flash('success', 'Penugasan unit "' . $assignment['unit_name'] . '" dihapus (beserta nilai & temuannya).');
        $this->redirect('/ami/assignments?schedule_id=' . (int)$assignment['schedule_id']);
    }

    /* ==================================== Penilaian 0–4 + temuan (FR-07) */

    public function assess(string $id): void
    {
        $assignment = AmiAssignment::withDetails((int)$id);
        if (!$assignment) {
            $this->notFound('Penugasan tidak ditemukan.');
        }

        $canEdit = Auth::isSuperAdmin()
            || (int)($assignment['auditor_user_id'] ?? 0) === (int)Auth::id();
        $canView = $canEdit || (int)$assignment['unit_id'] === (int)Auth::unitId();
        if (!$canView) {
            $this->forbidden();
        }

        $instruments = AmiInstrument::forSchedule((int)$assignment['schedule_id'], true);
        $values      = [];
        foreach (AmiAssessment::forAssignment((int)$id) as $a) {
            $values[(int)$a['instrument_id']] = $a;
        }

        $this->view('ami/assess', [
            'title'       => 'Penilaian AMI — ' . $assignment['unit_name'],
            'assignment'  => $assignment,
            'instruments' => $instruments,
            'values'      => $values,
            'findings'    => Finding::forAssignment((int)$id),
            'canEdit'     => $canEdit && $assignment['schedule_status'] === 'running',
            'errors'      => [],
        ]);
    }

    public function saveAssessments(string $id): void
    {
        $assignment = AmiAssignment::withDetails((int)$id);
        if (!$assignment) {
            $this->notFound('Penugasan tidak ditemukan.');
        }
        if (!Auth::isSuperAdmin() && (int)($assignment['auditor_user_id'] ?? 0) !== (int)Auth::id()) {
            $this->forbidden();
        }
        if ($assignment['schedule_status'] !== 'running') {
            flash('warning', 'Jadwal "' . $assignment['schedule_name'] . '" tidak sedang berjalan — penilaian terkunci.');
            $this->redirect('/ami/assignments/' . (int)$id . '/assess');
        }

        $scores = is_array($_POST['scores'] ?? null) ? $_POST['scores'] : [];
        $notes  = is_array($_POST['notes'] ?? null) ? $_POST['notes'] : [];

        $instruments = AmiInstrument::forSchedule((int)$assignment['schedule_id'], true);
        $saved       = 0;
        $findingsMade = 0;

        foreach ($instruments as $inst) {
            $instId = (int)$inst['id'];
            $raw    = trim((string)($scores[$instId] ?? ''));
            if ($raw === '') {
                continue;
            }
            if (!in_array($raw, ['0', '1', '2', '3', '4'], true)) {
                flash('danger', 'Skor pada indikator ' . $inst['code'] . ' tidak valid (0–4).');
                $this->redirect('/ami/assignments/' . (int)$id . '/assess');
            }
            $score = (int)$raw;
            $note  = trim((string)($notes[$instId] ?? ''));
            if ($score <= 2 && $note === '') {
                flash('danger', 'Skor ≤ 2 pada indikator ' . $inst['code'] . ' wajib disertai catatan temuan.');
                $this->redirect('/ami/assignments/' . (int)$id . '/assess');
            }

            $evidence = $this->uploadEvidence($instId);
            if (isset($evidence['error'])) {
                flash('danger', $evidence['error']);
                $this->redirect('/ami/assignments/' . (int)$id . '/assess');
            }

            $existing = AmiAssessment::findPair((int)$id, $instId);
            $payload  = [
                'score'        => $score,
                'finding_note' => $note !== '' ? $note : null,
                'filled_by'    => Auth::id(),
                'filled_at'    => date('Y-m-d H:i:s'),
            ];
            if (isset($evidence['path'])) {
                if ($existing && !empty($existing['evidence_file'])) {
                    delete_upload($existing['evidence_file']);
                }
                $payload['evidence_file'] = $evidence['path'];
            }

            if ($existing) {
                AmiAssessment::updateById((int)$existing['id'], $payload);
                $assessmentId = (int)$existing['id'];
            } else {
                $payload['assignment_id']  = (int)$id;
                $payload['instrument_id']  = $instId;
                $assessmentId = AmiAssessment::create($payload);
            }
            $saved++;

            // Temuan otomatis (aktivitas Gambar 4.5): skor ≤ 2 → catat temuan.
            if ($score <= 2) {
                $severity = $score <= 1 ? 'tinggi' : 'sedang';
                $finding  = Finding::byAssessment($assessmentId);
                if (!$finding) {
                    Finding::create([
                        'assessment_id' => $assessmentId,
                        'assignment_id' => (int)$id,
                        'finding_type'  => 'ketidaksesuaian',
                        'severity'      => $severity,
                        'description'   => $note,
                        'identified_by' => Auth::id(),
                        'status'        => 'open',
                    ]);
                    $findingsMade++;
                } elseif ($finding['status'] === 'open') {
                    Finding::updateById((int)$finding['id'], [
                        'severity'    => $severity,
                        'description' => $note,
                    ]);
                }
            }
        }

        if ($saved === 0) {
            flash('warning', 'Tidak ada skor yang diisi — pilih skor 0–4 pada minimal satu indikator.');
            $this->redirect('/ami/assignments/' . (int)$id . '/assess');
        }

        // Status penugasan mengikuti kelengkapan penilaian.
        $activeIds = array_map('intval', array_column($instruments, 'id'));
        $assessedActive = 0;
        foreach (AmiAssessment::forAssignment((int)$id) as $a) {
            if (in_array((int)$a['instrument_id'], $activeIds, true)) {
                $assessedActive++;
            }
        }
        $status = $assessedActive === 0
            ? 'assigned'
            : ($assessedActive >= count($activeIds) ? 'completed' : 'in_progress');
        if ($status !== $assignment['status']) {
            AmiAssignment::updateById((int)$id, ['status' => $status]);
        }

        if ($findingsMade > 0) {
            Notification::send(
                User::superAdminIds(),
                'Temuan AMI baru dari penilaian',
                $findingsMade . ' temuan tercatat dari penilaian unit "' . $assignment['unit_name'] . '".',
                'temuan',
                'ami_assignments',
                (int)$id
            );
        }

        audit('assess', 'ami_assignments', (int)$id, [
            'unit'     => $assignment['unit_name'],
            'skor'     => $saved,
            'temuan'   => $findingsMade,
            'status'   => $status,
        ]);
        flash('success', $saved . ' indikator dinilai'
            . ($findingsMade > 0 ? ' · ' . $findingsMade . ' temuan tercatat' : '')
            . ' · status: ' . $this->statusLabel($status) . '.');
        $this->redirect('/ami/assignments/' . (int)$id . '/assess');
    }

    /* ==================================================== Temuan (FR-08) */

    public function findings(): void
    {
        $schedule = $this->selectedSchedule();
        $sid      = $schedule ? (int)$schedule['id'] : 0;

        $rows = $sid > 0 ? Finding::forSchedule($sid) : [];
        $filterStatus = (string)($_GET['status'] ?? '');
        $filterSev    = (string)($_GET['severity'] ?? '');
        if ($filterStatus !== '') {
            $rows = array_values(array_filter($rows, static fn ($f) => $f['status'] === $filterStatus));
        }
        if ($filterSev !== '') {
            $rows = array_values(array_filter($rows, static fn ($f) => $f['severity'] === $filterSev));
        }

        // Formulir temuan manual: Super Admin semua; anggota hanya untuk unit/auditnya.
        $isSuper = Auth::isSuperAdmin();
        $myAssignments = [];
        if ($schedule) {
            $all = AmiAssignment::forSchedule($sid);
            $myAssignments = $isSuper
                ? $all
                : array_values(array_filter(
                    $all,
                    static fn ($a) => (int)($a['auditor_user_id'] ?? 0) === (int)Auth::id()
                ));
        }

        $this->view('ami/findings', [
            'title'          => 'Temuan AMI',
            'periods'        => AmiSchedule::ordered(),
            'schedule'       => $schedule,
            'rows'           => $rows,
            'isSuper'        => $isSuper,
            'formAssignments'=> $myAssignments,
            'errors'         => [],
        ]);
    }

    public function storeFinding(): void
    {
        $schedule = AmiSchedule::find((int)($_POST['schedule_id'] ?? 0));
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $assignment = AmiAssignment::withDetails($assignmentId);

        if (!$schedule || !$assignment || (int)$assignment['schedule_id'] !== (int)$schedule['id']) {
            flash('danger', 'Penugasan tidak ditemukan pada jadwal tersebut.');
            $this->redirect('/ami/findings');
        }
        if ($schedule['status'] === 'closed') {
            flash('warning', 'Jadwal sudah ditutup — temuan baru tidak dapat ditambahkan di sini.');
            $this->redirect('/ami/findings?schedule_id=' . (int)$schedule['id']);
        }
        if (!Auth::isSuperAdmin() && (int)($assignment['auditor_user_id'] ?? 0) !== (int)Auth::id()) {
            $this->forbidden();
        }

        $description = trim((string)($_POST['description'] ?? ''));
        $type        = (string)($_POST['finding_type'] ?? 'ketidaksesuaian');
        $severity    = (string)($_POST['severity'] ?? 'sedang');

        if ($description === '') {
            flash('danger', 'Uraian temuan wajib diisi.');
            $this->redirect('/ami/findings?schedule_id=' . (int)$schedule['id']);
        }
        if (!in_array($type, ['ketidaksesuaian', 'observasi', 'rekomendasi'], true)) {
            $type = 'ketidaksesuaian';
        }
        if (!in_array($severity, ['tinggi', 'sedang', 'rendah'], true)) {
            $severity = 'sedang';
        }

        $findingId = Finding::create([
            'assessment_id' => null,
            'assignment_id' => $assignmentId,
            'finding_type'  => $type,
            'severity'      => $severity,
            'description'   => $description,
            'identified_by' => Auth::id(),
            'status'        => 'open',
        ]);

        audit('create', 'ami_findings', $findingId, [
            'unit'     => $assignment['unit_name'],
            'severity' => $severity,
        ]);
        if (!Auth::isSuperAdmin()) {
            Notification::send(
                User::superAdminIds(),
                'Temuan AMI dicatat auditor',
                'Temuan baru pada unit "' . $assignment['unit_name'] . '": ' . mb_substr($description, 0, 80),
                'temuan',
                'ami_findings',
                $findingId
            );
        }
        flash('success', 'Temuan berhasil dicatat.');
        $this->redirect('/ami/findings?schedule_id=' . (int)$schedule['id']);
    }

    public function updateFinding(string $id): void
    {
        $this->requireSuperAdmin();

        $finding = Finding::find((int)$id);
        if (!$finding) {
            $this->notFound('Temuan tidak ditemukan.');
        }

        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['open', 'in_progress', 'verified', 'closed'], true)) {
            flash('danger', 'Status temuan tidak valid.');
            $this->redirect('/ami/findings');
        }

        Finding::updateById((int)$id, ['status' => $status]);

        $scheduleId = (int)Database::fetch(
            'SELECT a.schedule_id FROM ami_findings f JOIN ami_assignments a ON a.id = f.assignment_id WHERE f.id = ?',
            [(int)$id]
        )['schedule_id'];

        audit('update', 'ami_findings', (int)$id, ['status' => $status]);

        $finderId = (int)($finding['identified_by'] ?? 0);
        if ($finderId > 0 && $finderId !== Auth::id()) {
            Notification::send(
                $finderId,
                'Status temuan diperbarui',
                'Temuan #' . (int)$id . ' kini berstatus ' . $this->findingStatusLabel($status) . '.',
                'temuan',
                'ami_findings',
                (int)$id
            );
        }

        flash('success', 'Status temuan diperbarui menjadi "' . $this->findingStatusLabel($status) . '".');
        $this->redirect('/ami/findings?schedule_id=' . $scheduleId);
    }

    /* ================================================= Laporan AMI (FR-08) */

    public function report(): void
    {
        $this->requireSuperAdmin();

        $schedule = $this->selectedSchedule();
        if (!$schedule) {
            flash('warning', 'Belum ada jadwal AMI.');
            $this->redirect('/ami');
        }
        $sid = (int)$schedule['id'];

        $this->view('ami/report', [
            'title'          => 'Laporan Temuan AMI',
            'periods'        => AmiSchedule::ordered(),
            'schedule'       => $schedule,
            'progress'       => AmiAssessment::progress($sid),
            'unitScores'     => AmiAssessment::unitScores($sid),
            'standardRows'   => AmiAssessment::standardScores($sid),
            'instrumentRows' => AmiAssessment::instrumentAverages($sid),
            'findings'       => Finding::forSchedule($sid),
            'openCount'      => Finding::openCount($sid),
        ]);
    }

    /* ----------------------------------------------------------- Ekspor (FR-14) */

    /**
     * Ekspor laporan AMI satu jadwal menjadi CSV siap Excel (FR-14):
     * blok rekap skor per unit, blok rekap per standar, lalu blok temuan.
     * Saringan jadwal ikut berlaku (sama seperti halaman laporan).
     */
    public function export(): void
    {
        $this->requireSuperAdmin();

        $schedule = $this->selectedSchedule();
        if (!$schedule) {
            flash('warning', 'Belum ada jadwal AMI untuk diekspor.');
            $this->redirect('/ami');
        }
        $sid = (int)$schedule['id'];

        $assignmentStatus = static fn (string $s): string => match ($s) {
            'assigned'    => 'Belum mulai',
            'in_progress' => 'Berjalan',
            'completed'   => 'Penilaian lengkap',
            default       => $s,
        };
        $findingStatus = static fn (string $s): string => match ($s) {
            'open'        => 'Terbuka',
            'in_progress' => 'Ditindaklanjuti',
            'verified'    => 'Terverifikasi',
            'closed'      => 'Selesai',
            default       => $s,
        };
        $severityLabel = [
            'rendah' => 'Rendah',
            'sedang' => 'Sedang',
            'tinggi' => 'Tinggi',
        ];

        $lines = [[
            'Laporan AMI — ' . $schedule['name'],
            'Periode ' . format_date($schedule['start_date']) . ' s.d. ' . format_date($schedule['end_date']),
        ]];

        // Blok 1 — rekap skor per unit (gap = skor penuh 4 − rerata).
        $lines[] = [];
        $lines[] = ['REKAP SKOR PER UNIT'];
        $lines[] = ['Unit', 'Auditor', 'Tanggal audit', 'Status penugasan', 'Indikator dinilai', 'Skor rata-rata (0–4)', 'Gap ke skor penuh'];
        foreach (AmiAssessment::unitScores($sid) as $u) {
            $gap = $u['avg_score'] !== null
                ? number_format(4 - (float)$u['avg_score'], 2, ',', '.')
                : '-';
            $lines[] = [
                $u['unit_name'],
                $u['auditor_name'] ?? '-',
                $u['audit_date'] !== null ? format_date($u['audit_date']) : '-',
                $assignmentStatus((string)$u['status']),
                (int)$u['assessed_count'],
                $u['avg_score'] !== null ? number_format((float)$u['avg_score'], 2, ',', '.') : '-',
                $gap,
            ];
        }

        // Blok 2 — rekap per standar.
        $lines[] = [];
        $lines[] = ['REKAP PER STANDAR'];
        $lines[] = ['Kode', 'Standar', 'Rerata skor (0–4)', 'Gap ke skor penuh'];
        foreach (AmiAssessment::standardScores($sid) as $s) {
            $lines[] = [
                $s['standard_code'] ?? '-',
                $s['standard_name'] ?? '-',
                $s['avg_score'] !== null ? number_format((float)$s['avg_score'], 2, ',', '.') : '-',
                $s['gap'] !== null ? number_format((float)$s['gap'], 2, ',', '.') : '-',
            ];
        }

        // Blok 3 — temuan.
        $typeLabel = [
            'ketidaksesuaian' => 'Ketidaksesuaian',
            'observasi'       => 'Observasi',
            'rekomendasi'     => 'Rekomendasi',
        ];
        $lines[] = [];
        $lines[] = ['TEMUAN AUDIT'];
        $lines[] = ['Unit', 'Indikator', 'Jenis', 'Keparahan', 'Uraian temuan', 'Status', 'Diidentifikasi'];
        foreach (Finding::forSchedule($sid) as $f) {
            $lines[] = [
                $f['unit_name'],
                trim(($f['indicator_code'] ?? '') . ' ' . ($f['indicator_question'] ?? '')) ?: '-',
                $typeLabel[$f['finding_type']] ?? $f['finding_type'],
                $severityLabel[$f['severity']] ?? ($f['severity'] ?? '-'),
                $f['description'],
                $findingStatus((string)$f['status']),
                $f['finder_name'] ?? '-',
            ];
        }

        export_csv('ami-' . preg_replace('/[^a-z0-9]+/i', '-', (string)$schedule['name']) . '-' . date('Y-m-d') . '.csv', $lines);
    }

    /* ======================================================= Bukti penilaian */

    public function downloadEvidence(string $id): void
    {
        $assessment = AmiAssessment::find((int)$id);
        if (!$assessment || empty($assessment['evidence_file'])) {
            $this->notFound('Bukti tidak ditemukan.');
        }

        $base = realpath(rtrim((string)config('upload_dir'), '/'));
        $full = realpath(rtrim((string)config('upload_dir'), '/') . '/' . ltrim((string)$assessment['evidence_file'], '/'));

        if ($base === false || $full === false || !str_starts_with((string)$full, (string)$base) || !is_file($full)) {
            $this->notFound('Berkas tidak ditemukan di server.');
        }

        audit('download', 'ami_assessments', (int)$id, ['file' => basename((string)$full)]);

        $ext  = strtolower(pathinfo((string)$full, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'pdf'  => 'application/pdf',
            'doc', 'docx' => 'application/msword',
            'xls', 'xlsx' => 'application/vnd.ms-excel',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . rawurlencode(basename((string)$full)) . '"');
        header('Content-Length: ' . filesize($full));
        header('X-Content-Type-Options: nosniff');
        readfile($full);
        exit;
    }

    /* =========================================== Formulir kepuasan (FR-07) */

    public function satisfaction(): void
    {
        $isSuper = Auth::isSuperAdmin();

        $this->view('ami/satisfaction', [
            'title'  => 'Formulir Kepuasan',
            'surveys'=> SatisfactionSurvey::withStats(),
            'isSuper'=> $isSuper,
            'errors' => [],
        ]);
    }

    public function storeSurvey(): void
    {
        $this->requireSuperAdmin();

        $name  = trim((string)($_POST['name'] ?? ''));
        $start = trim((string)($_POST['start_date'] ?? ''));
        $end   = trim((string)($_POST['end_date'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama formulir wajib diisi.';
        } elseif (SatisfactionSurvey::nameExists($name)) {
            $errors['name'] = 'Nama formulir sudah dipakai.';
        }
        if ($start !== '' && $end !== '' && $end < $start) {
            $errors['end_date'] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }

        if ($errors) {
            flash('danger', 'Periksa kembali isian formulir kepuasan.');
            $this->view('ami/satisfaction', [
                'title'   => 'Formulir Kepuasan',
                'surveys' => SatisfactionSurvey::withStats(),
                'isSuper' => true,
                'errors'  => $errors,
            ]);
        }

        $id = SatisfactionSurvey::create([
            'name'       => $name,
            'start_date' => $start !== '' ? $start : null,
            'end_date'   => $end !== '' ? $end : null,
            'status'     => 'draft',
        ]);

        audit('create', 'satisfaction_surveys', $id, ['name' => $name]);
        flash('success', 'Formulir "' . $name . '" dibuat (draf). Tambahkan pertanyaan, lalu buka untuk pengisian.');
        $this->redirect('/ami/satisfaction');
    }

    public function toggleSurvey(string $id): void
    {
        $this->requireSuperAdmin();

        $survey = SatisfactionSurvey::find((int)$id);
        if (!$survey) {
            $this->notFound('Formulir kepuasan tidak ditemukan.');
        }

        $new = match ($survey['status']) {
            'draft'   => 'open',
            'open'    => 'closed',
            default   => 'open',
        };
        SatisfactionSurvey::updateById((int)$id, ['status' => $new]);

        audit('update', 'satisfaction_surveys', (int)$id, ['from' => $survey['status'], 'to' => $new]);

        if ($new === 'open') {
            $members = array_map('intval', array_column(
                Database::all("SELECT id FROM users WHERE role = 'anggota' AND is_active = 1"),
                'id'
            ));
            if ($members) {
                Notification::send(
                    $members,
                    'Formulir kepuasan dibuka',
                    '"' . $survey['name'] . '" siap diisi — silakan berikan penilaian layanan mutu.',
                    'umum',
                    'satisfaction_surveys',
                    (int)$id
                );
            }
        }

        flash('success', match ($new) {
            'open'   => 'Formulir "' . $survey['name'] . '" dibuka — anggota diberi notifikasi.',
            default  => 'Formulir "' . $survey['name'] . '" ditutup.',
        });
        $this->redirect('/ami/satisfaction');
    }

    public function questions(string $id): void
    {
        $this->requireSuperAdmin();

        $survey = SatisfactionSurvey::find((int)$id);
        if (!$survey) {
            $this->notFound('Formulir kepuasan tidak ditemukan.');
        }

        $this->view('ami/satisfaction-questions', [
            'title'     => 'Pertanyaan Kepuasan',
            'survey'    => $survey,
            'questions' => SatisfactionQuestion::forSurvey((int)$id),
            'errors'    => [],
        ]);
    }

    public function storeQuestion(string $id): void
    {
        $this->requireSuperAdmin();

        $survey = SatisfactionSurvey::find((int)$id);
        if (!$survey) {
            $this->notFound('Formulir kepuasan tidak ditemukan.');
        }
        if ($survey['status'] === 'closed') {
            flash('warning', 'Formulir sudah ditutup — pertanyaan terkunci.');
            $this->redirect('/ami/satisfaction/' . (int)$id . '/questions');
        }

        $question = trim((string)($_POST['question'] ?? ''));
        $type     = (string)($_POST['type'] ?? 'skala');
        $weight   = (float)($_POST['weight'] ?? 1);
        $sort     = (int)($_POST['sort_order'] ?? 0);

        $errors = [];
        if ($question === '') {
            $errors['question'] = 'Uraian pertanyaan wajib diisi.';
        }
        if (!in_array($type, ['skala', 'pilihan', 'teks'], true)) {
            $type = 'skala';
        }
        if ($weight <= 0) {
            $errors['weight'] = 'Bobot harus lebih besar dari 0.';
        }
        if ($errors) {
            flash('danger', implode(' ', array_values($errors)));
            $this->redirect('/ami/satisfaction/' . (int)$id . '/questions');
        }

        $qid = SatisfactionQuestion::create([
            'survey_id'  => (int)$id,
            'question'   => $question,
            'type'       => $type,
            'weight'     => $weight,
            'is_active'  => 1,
            'sort_order' => $sort,
        ]);

        audit('create', 'satisfaction_questions', $qid, ['survey' => $survey['name']]);
        flash('success', 'Pertanyaan ditambahkan.');
        $this->redirect('/ami/satisfaction/' . (int)$id . '/questions');
    }

    public function updateQuestion(string $id): void
    {
        $this->requireSuperAdmin();

        $question = SatisfactionQuestion::find((int)$id);
        if (!$question) {
            $this->notFound('Pertanyaan tidak ditemukan.');
        }
        $survey = SatisfactionSurvey::find((int)$question['survey_id']);
        if ($survey && $survey['status'] === 'closed') {
            flash('warning', 'Formulir sudah ditutup — pertanyaan terkunci.');
            $this->redirect('/ami/satisfaction/' . (int)$question['survey_id'] . '/questions');
        }

        $text   = trim((string)($_POST['question'] ?? ''));
        $type   = (string)($_POST['type'] ?? 'skala');
        $weight = (float)($_POST['weight'] ?? 1);
        $sort   = (int)($_POST['sort_order'] ?? 0);

        if ($text === '' || $weight <= 0) {
            flash('danger', 'Uraian wajib diisi dan bobot harus lebih besar dari 0.');
            $this->redirect('/ami/satisfaction/' . (int)$question['survey_id'] . '/questions');
        }
        if (!in_array($type, ['skala', 'pilihan', 'teks'], true)) {
            $type = 'skala';
        }

        SatisfactionQuestion::updateById((int)$id, [
            'question'   => $text,
            'type'       => $type,
            'weight'     => $weight,
            'sort_order' => $sort,
        ]);
        audit('update', 'satisfaction_questions', (int)$id, []);
        flash('success', 'Pertanyaan diperbarui.');
        $this->redirect('/ami/satisfaction/' . (int)$question['survey_id'] . '/questions');
    }

    public function deleteQuestion(string $id): void
    {
        $this->requireSuperAdmin();

        $question = SatisfactionQuestion::find((int)$id);
        if (!$question) {
            $this->notFound('Pertanyaan tidak ditemukan.');
        }

        $answers = (int)Database::fetch(
            'SELECT COUNT(*) AS n FROM satisfaction_answers WHERE question_id = ?',
            [(int)$id]
        )['n'];
        if ($answers > 0) {
            // Nonaktifkan agar jawaban responden tidak ikut terhapus.
            SatisfactionQuestion::updateById((int)$id, ['is_active' => 0]);
            audit('update', 'satisfaction_questions', (int)$id, ['is_active' => 0]);
            flash('warning', 'Pertanyaan sudah memiliki jawaban — dinonaktifkan agar data responden tetap utuh.');
        } else {
            SatisfactionQuestion::deleteById((int)$id);
            audit('delete', 'satisfaction_questions', (int)$id, []);
            flash('success', 'Pertanyaan dihapus.');
        }
        $this->redirect('/ami/satisfaction/' . (int)$question['survey_id'] . '/questions');
    }

    public function fill(string $id): void
    {
        $survey = SatisfactionSurvey::find((int)$id);
        if (!$survey) {
            $this->notFound('Formulir kepuasan tidak ditemukan.');
        }
        if ($survey['status'] !== 'open') {
            flash('warning', 'Formulir "' . $survey['name'] . '" tidak sedang terbuka untuk pengisian.');
            $this->redirect('/ami/satisfaction');
        }
        if (SatisfactionResponse::hasResponded((int)$id, (int)Auth::id())) {
            flash('info', 'Anda sudah mengisi formulir ini — terima kasih atas partisipasinya.');
            $this->redirect('/ami/satisfaction');
        }

        $this->view('ami/satisfaction-fill', [
            'title'     => 'Isi Formulir Kepuasan',
            'survey'    => $survey,
            'questions' => SatisfactionQuestion::activeForSurvey((int)$id),
            'errors'    => [],
        ]);
    }

    public function submitFill(string $id): void
    {
        $survey = SatisfactionSurvey::find((int)$id);
        if (!$survey) {
            $this->notFound('Formulir kepuasan tidak ditemukan.');
        }
        if ($survey['status'] !== 'open') {
            flash('warning', 'Formulir sudah ditutup.');
            $this->redirect('/ami/satisfaction');
        }
        if (SatisfactionResponse::hasResponded((int)$id, (int)Auth::id())) {
            flash('info', 'Anda sudah mengisi formulir ini.');
            $this->redirect('/ami/satisfaction');
        }

        $questions = SatisfactionQuestion::activeForSurvey((int)$id);
        $answers   = is_array($_POST['answers'] ?? null) ? $_POST['answers'] : [];

        foreach ($questions as $q) {
            if ($q['type'] !== 'skala') {
                continue;
            }
            $val = trim((string)($answers[(int)$q['id']] ?? ''));
            if ($val === '' || !in_array($val, ['1', '2', '3', '4', '5'], true)) {
                flash('danger', 'Semua pertanyaan skala wajib dijawab dengan nilai 1–5.');
                $this->redirect('/ami/satisfaction/' . (int)$id . '/fill');
            }
        }

        $responseId = SatisfactionResponse::create([
            'survey_id'         => (int)$id,
            'respondent_user_id' => Auth::id(),
            'submitted_at'      => date('Y-m-d H:i:s'),
        ]);

        foreach ($questions as $q) {
            $qid = (int)$q['id'];
            $val = trim((string)($answers[$qid] ?? ''));
            if ($val === '') {
                continue;
            }
            SatisfactionAnswer::create([
                'response_id' => $responseId,
                'question_id' => $qid,
                'score'       => $q['type'] === 'skala' ? (float)$val : null,
                'answer_text' => $q['type'] === 'skala' ? null : mb_substr($val, 0, 2000),
            ]);
        }

        audit('create', 'satisfaction_responses', $responseId, ['survey' => $survey['name']]);
        flash('success', 'Terima kasih — jawaban Anda tersimpan.');
        $this->redirect('/ami/satisfaction');
    }

    public function recap(string $id): void
    {
        $this->requireSuperAdmin();

        $recap = SatisfactionSurvey::recap((int)$id);
        if (!$recap) {
            $this->notFound('Formulir kepuasan tidak ditemukan.');
        }

        $this->view('ami/satisfaction-recap', [
            'title'      => 'Rekap Kepuasan',
            'recap'      => $recap,
            'respondents'=> SatisfactionResponse::forSurvey((int)$id),
        ]);
    }

    /* =============================================================== Utilitas */

    /** Jadwal terpilih dari ?schedule_id (default: berjalan/terbaru). */
    private function selectedSchedule(): ?array
    {
        $id = (int)($_GET['schedule_id'] ?? 0);
        if ($id > 0) {
            $found = AmiSchedule::find($id);
            if ($found) {
                return $found;
            }
        }
        return AmiSchedule::activeOrLatest();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'assigned'    => 'belum mulai',
            'in_progress' => 'berjalan',
            'completed'   => 'penilaian lengkap',
            default       => $status,
        };
    }

    private function findingStatusLabel(string $status): string
    {
        return match ($status) {
            'open'        => 'Terbuka',
            'in_progress' => 'Ditindaklanjuti',
            'verified'    => 'Terverifikasi',
            'closed'      => 'Selesai',
            default       => $status,
        };
    }

    /** Unggah bukti penilaian per indikator (input bernama evidence[<id>]). */
    private function uploadEvidence(int $instrumentId): array
    {
        if (empty($_FILES['evidence']) || !isset($_FILES['evidence']['error'][$instrumentId])) {
            return [];
        }

        $error = (int)$_FILES['evidence']['error'][$instrumentId];
        if ($error === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['error' => 'Gagal mengunggah bukti (kode ' . $error . ').'];
        }

        $size = (int)$_FILES['evidence']['size'][$instrumentId];
        if ($size > (int)config('upload_max_kb') * 1024) {
            return ['error' => 'Ukuran bukti melebihi batas ' . (int)config('upload_max_kb') . ' KB.'];
        }

        $original = (string)$_FILES['evidence']['name'][$instrumentId];
        $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, (array)config('upload_ext'), true)) {
            return ['error' => 'Ekstensi .' . $ext . ' tidak diizinkan.'];
        }

        $relative = 'bukti-ami/' . date('Y/m') . '/';
        $dir      = rtrim((string)config('upload_dir'), '/') . '/' . $relative;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['error' => 'Gagal membuat folder unggahan.'];
        }

        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($_FILES['evidence']['tmp_name'][$instrumentId], $dir . $filename)) {
            return ['error' => 'Gagal menyimpan berkas bukti.'];
        }

        return ['path' => $relative . $filename];
    }
}
