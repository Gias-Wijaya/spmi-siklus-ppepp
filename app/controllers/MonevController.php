<?php
declare(strict_types=1);

/**
 * M2 — Monev: periode & indikator (Super Admin), isian capaian unit,
 * review, dan dasbor tren capaian (FR-04, FR-05).
 */
final class MonevController extends Controller
{
    /* ---------------------------------------------------------- Dasbor tren */

    public function index(): void
    {
        $periods  = MonevPeriod::ordered();
        $periodId = (int)($_GET['period_id'] ?? 0);
        if ($periodId <= 0 && $periods) {
            $periodId = (int)$periods[0]['id'];
        }
        $period = $periodId > 0 ? MonevPeriod::find($periodId) : null;

        $reports    = $period ? MonevReport::forPeriod($periodId) : [];

        // Filter unit tabel "Capaian per indikator" (keputusan review LPM 19 Nov).
        $units = Unit::active();
        $filterUnitId = (int)($_GET['unit_id'] ?? 0);
        if ($filterUnitId > 0 && !array_filter($units, static fn (array $u) => (int)$u['id'] === $filterUnitId)) {
            $filterUnitId = 0;
        }
        $indicators = $period
            ? MonevReport::indicatorAverages($periodId, $filterUnitId > 0 ? $filterUnitId : null)
            : [];
        $unitScores = $period ? MonevReport::unitScores($periodId) : [];
        $trend      = MonevReport::trendPerPeriod();

        // Jumlah unit yang benar-benar mengisi → label "Rata-rata (n unit)".
        $fillCount = 0;
        if ($period) {
            $fillCount = (int)Database::scalar(
                'SELECT COUNT(DISTINCT r.unit_id)
                   FROM monev_reports r
                   JOIN monev_values v ON v.report_id = r.id
                  WHERE r.period_id = ?' . ($filterUnitId > 0 ? ' AND r.unit_id = ?' : ''),
                $filterUnitId > 0 ? [(int)$period['id'], $filterUnitId] : [(int)$period['id']]
            );
        }

        // Ringkasan periode terpilih.
        $scored = array_filter(
            $reports,
            static fn ($r) => $r['report_id'] !== null && $r['total_score'] !== null
        );
        $avg = $scored
            ? round(array_sum(array_map(static fn ($r) => (float)$r['total_score'], $scored)) / count($scored), 2)
            : null;
        // "Di bawah target" pada kartu ringkasan tetap lintas seluruh unit
        // — filter unit hanya berlaku untuk tabel Capaian per indikator.
        $below = $period ? count(array_filter(
            MonevReport::indicatorAverages($periodId),
            static fn ($i) => $i['avg_ap'] !== null && (float)$i['avg_ap'] < 100
        )) : 0;

        // Data grafik (Chart.js).
        $chartTrend = [
            'labels' => array_map(static fn ($p) => $p['name'], $trend),
            'scores' => array_map(static fn ($p) => $p['avg_score'], $trend),
        ];
        $chartUnits = [
            'labels' => array_map(static fn ($u) => $u['unit_name'], $unitScores),
            'scores' => array_map(static fn ($u) => $u['total_score'], $unitScores),
        ];

        $this->view('monev/index', [
            'title'      => 'Dasbor Monev',
            'periods'    => $periods,
            'period'     => $period,
            'reports'    => $reports,
            'indicators' => $indicators,
            'units'      => $units,
            'filterUnitId' => $filterUnitId,
            'fillCount'  => $fillCount,
            'trend'      => $trend,
            'avg'        => $avg,
            'below'      => $below,
            'chartTrend' => $chartTrend,
            'chartUnits' => $chartUnits,
            'counts'     => MonevReport::statusCounts(),
            'errors'     => [],
        ]);
    }

    /* ------------------------------------------------------------ Ekspor (FR-14) */

    /**
     * Ekspor rekap satu periode Monev menjadi CSV siap Excel (FR-14):
     * blok rekap capaian per unit, lalu blok rerata capaian per indikator.
     * Saringan periode ikut berlaku (sama seperti halaman dasbor).
     */
    public function export(): void
    {
        $periods  = MonevPeriod::ordered();
        $periodId = (int)($_GET['period_id'] ?? 0);
        if ($periodId <= 0 && $periods) {
            $periodId = (int)$periods[0]['id'];
        }
        $period = $periodId > 0 ? MonevPeriod::find($periodId) : null;
        if (!$period) {
            flash('warning', 'Belum ada periode Monev untuk diekspor.');
            $this->redirect('/monev');
        }

        $statusLabel = [
            'draft'     => 'Draft',
            'submitted' => 'Diajukan',
            'approved'  => 'Disetujui',
        ];

        $lines = [[
            'Rekap Monev — ' . $period['name'],
            'Periode ' . format_date($period['start_date']) . ' s.d. ' . format_date($period['end_date']),
        ]];

        // Blok 1 — rekap capaian per unit.
        $lines[] = [];
        $lines[] = ['REKAP PER UNIT'];
        $lines[] = ['Unit', 'Status laporan', 'Skor total (0–100)', 'Diajukan pada'];
        foreach (MonevReport::forPeriod((int)$period['id']) as $r) {
            $lines[] = [
                $r['unit_name'],
                $r['report_id'] !== null
                    ? ($statusLabel[$r['status']] ?? $r['status'])
                    : 'Belum ada laporan',
                $r['total_score'] !== null
                    ? number_format((float)$r['total_score'], 2, ',', '.')
                    : '-',
                $r['submitted_at'] !== null
                    ? format_date($r['submitted_at'], 'd M Y H:i')
                    : '-',
            ];
        }

        // Blok 2 — rerata capaian per indikator.
        $lines[] = [];
        $lines[] = ['RERATA CAPAIAN PER INDIKATOR'];
        $lines[] = ['Kode', 'Indikator', 'Satuan', 'Target', 'Rerata capaian (%)', 'Responden', 'Di bawah target'];
        foreach (MonevReport::indicatorAverages((int)$period['id']) as $i) {
            $lines[] = [
                $i['code'],
                $i['name'],
                $i['unit_of_measure'] ?? '-',
                $i['target'] ?? '-',
                $i['avg_ap'] !== null ? number_format((float)$i['avg_ap'], 2, ',', '.') : '-',
                (int)$i['respondent_count'],
                ($i['avg_ap'] !== null && (float)$i['avg_ap'] < 100) ? 'Ya' : 'Tidak',
            ];
        }

        export_csv('monev-' . preg_replace('/[^a-z0-9]+/i', '-', (string)$period['name']) . '-' . date('Y-m-d') . '.csv', $lines);
    }

    /* ------------------------------------------------------- Periode (Super) */

    public function periods(): void
    {
        $this->requireSuperAdmin();

        $periods = Database::all(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM monev_reports r WHERE r.period_id = p.id) AS report_count
               FROM monev_periods p
              ORDER BY p.start_date DESC, p.id DESC'
        );

        $this->view('monev/periods', [
            'title'    => 'Kelola Periode Monev',
            'periods'  => $periods,
            'errors'   => [],
        ]);
    }

    public function storePeriod(): void
    {
        $this->requireSuperAdmin();

        $name      = trim((string)($_POST['name'] ?? ''));
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $endDate   = trim((string)($_POST['end_date'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama periode wajib diisi.';
        } elseif (MonevPeriod::nameExists($name)) {
            $errors['name'] = 'Nama periode sudah dipakai.';
        }
        if ($startDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $errors['start_date'] = 'Tanggal mulai wajib diisi dengan format benar.';
        }
        if ($endDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $errors['end_date'] = 'Tanggal selesai wajib diisi dengan format benar.';
        }
        if (!$errors && $endDate < $startDate) {
            $errors['end_date'] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }

        if ($errors) {
            $periods = Database::all(
                'SELECT p.*,
                        (SELECT COUNT(*) FROM monev_reports r WHERE r.period_id = p.id) AS report_count
                   FROM monev_periods p
                  ORDER BY p.start_date DESC, p.id DESC'
            );
            flash('danger', 'Periksa kembali isian formulir periode.');
            $this->view('monev/periods', ['title' => 'Kelola Periode Monev', 'periods' => $periods, 'errors' => $errors]);
        }

        $id = MonevPeriod::create([
            'name'       => $name,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'status'     => 'open',
        ]);

        audit('create', 'monev_periods', $id, ['name' => $name, 'range' => $startDate . ' s.d. ' . $endDate]);
        flash('success', 'Periode "' . $name . '" berhasil dibuat.');
        $this->redirect('/monev/periods');
    }

    public function togglePeriod(string $id): void
    {
        $this->requireSuperAdmin();

        $period = MonevPeriod::find((int)$id);
        if (!$period) {
            $this->notFound('Periode tidak ditemukan.');
        }

        $newStatus = $period['status'] === 'open' ? 'closed' : 'open';
        MonevPeriod::updateById((int)$id, ['status' => $newStatus]);

        audit('update', 'monev_periods', (int)$id, ['from' => $period['status'], 'to' => $newStatus]);
        flash(
            'success',
            $newStatus === 'closed'
                ? 'Periode "' . $period['name'] . '" ditutup — isian tidak lagi dapat diubah.'
                : 'Periode "' . $period['name'] . '" dibuka kembali.'
        );
        $this->redirect('/monev/periods');
    }

    /* ---------------------------------------------------- Indikator (Super) */

    public function indicators(): void
    {
        $this->requireSuperAdmin();

        $this->view('monev/indicators', [
            'title'      => 'Kelola Indikator Monev',
            'indicators' => MonevIndicator::withStandard(),
            'standards'  => Standard::active(),
            'units'      => Unit::active(),
            'errors'     => [],
        ]);
    }

    public function storeIndicator(): void
    {
        $this->requireSuperAdmin();

        $data   = $this->indicatorPayload();
        $errors = $this->validateIndicator($data, 0);

        if ($errors) {
            flash('danger', 'Periksa kembali isian formulir indikator.');
            $this->view('monev/indicators', [
                'title'      => 'Kelola Indikator Monev',
                'indicators' => MonevIndicator::withStandard(),
                'standards'  => Standard::active(),
                'units'      => Unit::active(),
                'errors'     => $errors,
            ]);
        }

        $id = MonevIndicator::create($data);
        audit('create', 'monev_indicators', $id, ['code' => $data['code'], 'name' => $data['name']]);
        flash('success', 'Indikator "' . $data['name'] . '" berhasil ditambahkan.');
        $this->redirect('/monev/indicators');
    }

    public function editIndicator(string $id): void
    {
        $this->requireSuperAdmin();

        $indicator = MonevIndicator::find((int)$id);
        if (!$indicator) {
            $this->notFound('Indikator tidak ditemukan.');
        }

        $this->view('monev/indicator-form', [
            'title'      => 'Edit Indikator Monev',
            'indicator'  => $indicator,
            'standards'  => Standard::active(),
            'units'      => Unit::active(),
            'errors'     => [],
        ]);
    }

    public function updateIndicator(string $id): void
    {
        $this->requireSuperAdmin();

        $indicator = MonevIndicator::find((int)$id);
        if (!$indicator) {
            $this->notFound('Indikator tidak ditemukan.');
        }

        $data   = $this->indicatorPayload();
        $errors = $this->validateIndicator($data, (int)$id);

        if ($errors) {
            flash('danger', 'Periksa kembali isian formulir indikator.');
            $this->view('monev/indicator-form', [
                'title'      => 'Edit Indikator Monev',
                'indicator'  => array_merge($indicator, $data),
                'standards'  => Standard::active(),
                'units'      => Unit::active(),
                'errors'     => $errors,
            ]);
        }

        MonevIndicator::updateById((int)$id, $data);
        audit('update', 'monev_indicators', (int)$id, ['code' => $data['code'], 'name' => $data['name']]);
        flash('success', 'Indikator "' . $data['name'] . '" berhasil diperbarui.');
        $this->redirect('/monev/indicators');
    }

    private function indicatorPayload(): array
    {
        $standardId = (int)($_POST['standard_id'] ?? 0);
        $unitId     = (int)($_POST['unit_id'] ?? 0);

        return [
            'code'            => trim((string)($_POST['code'] ?? '')),
            'name'            => trim((string)($_POST['name'] ?? '')),
            'standard_id'     => $standardId > 0 ? $standardId : null,
            'unit_id'         => $unitId > 0 ? $unitId : null,
            'unit_of_measure' => trim((string)($_POST['unit_of_measure'] ?? '')) ?: null,
            'target'          => (float)($_POST['target'] ?? 0),
            'weight'          => (float)($_POST['weight'] ?? 1),
            'is_active'       => !empty($_POST['is_active']) ? 1 : 0,
        ];
    }

    private function validateIndicator(array $data, int $exceptId): array
    {
        $errors = [];
        if ($data['code'] === '') {
            $errors['code'] = 'Kode indikator wajib diisi.';
        } elseif (MonevIndicator::codeExists($data['code'], $exceptId)) {
            $errors['code'] = 'Kode indikator sudah dipakai.';
        }
        if ($data['name'] === '') {
            $errors['name'] = 'Nama indikator wajib diisi.';
        }
        if (($data['unit_id'] ?? 0) && !Unit::find((int)$data['unit_id'])) {
            $errors['unit_id'] = 'Unit pengisi tidak ditemukan.';
        }
        if ($data['target'] <= 0) {
            $errors['target'] = 'Target harus lebih besar dari 0.';
        } elseif (trim((string)($data['unit_of_measure'] ?? '')) === '%' && $data['target'] > 100) {
            // Review LPM 25 Nov (B4): target ber satuan % tidak boleh melebihi 100.
            $errors['target'] = 'Target ber satuan % maksimal 100.';
        }
        if ($data['weight'] <= 0) {
            $errors['weight'] = 'Bobot harus lebih besar dari 0.';
        }
        return $errors;
    }

    /* ------------------------------------------------- Isian capaian unit */

    public function report(): void
    {
        $this->noStore();
        $periodId = (int)($_GET['period_id'] ?? 0);
        $unitId   = (int)($_GET['unit_id'] ?? 0);

        $period = MonevPeriod::find($periodId);
        $unit   = $unitId > 0 ? Unit::find($unitId) : null;

        if (!$period || !$unit) {
            flash('danger', 'Periode atau unit tidak ditemukan.');
            $this->redirect('/monev');
        }

        $this->guardUnitAccess($unitId);

        $existing = MonevReport::findPair($periodId, $unitId);

        if ($existing && $existing['status'] === 'approved') {
            flash('warning', 'Laporan yang sudah disetujui tidak dapat diubah.');
            $this->redirect('/monev/reports/' . (int)$existing['id']);
        }
        if ($period['status'] !== 'open') {
            flash('danger', 'Periode "' . $period['name'] . '" sudah ditutup — isian ditutup.');
            $this->redirect($existing ? '/monev/reports/' . (int)$existing['id'] : '/monev');
        }

        $this->view('monev/report-form', [
            'title'      => 'Isi Capaian Monev',
            'period'     => $period,
            'unit'       => $unit,
            // Masukan LPM foto-1 — isian hanya indikator milik unit ini / semua unit.
            'indicators' => MonevIndicator::activeForUnit((int)$unit['id']),
            'values'     => $existing ? MonevValue::forReport((int)$existing['id']) : [],
            'errors'     => [],
        ]);
    }

    public function saveReport(): void
    {
        $periodId = (int)($_POST['period_id'] ?? 0);
        $unitId   = (int)($_POST['unit_id'] ?? 0);
        $action   = isset($_POST['submit']) ? 'submit' : 'draft';

        $period = MonevPeriod::find($periodId);
        $unit   = $unitId > 0 ? Unit::find($unitId) : null;

        if (!$period || !$unit) {
            flash('danger', 'Periode atau unit tidak ditemukan.');
            $this->redirect('/monev');
        }
        $this->guardUnitAccess($unitId);

        if ($period['status'] !== 'open') {
            flash('danger', 'Periode "' . $period['name'] . '" sudah ditutup.');
            $this->redirect('/monev');
        }

        // Wajib lengkap sebelum diajukan: nilai + bukti + catatan.
        // Masukan LPM foto-1 — cakupan identik dengan yang tampil pada formulir.
        $indicators = MonevIndicator::activeForUnit($unitId);
        $rawValues  = is_array($_POST['values'] ?? null) ? $_POST['values'] : [];
        $rawNotes   = is_array($_POST['notes'] ?? null) ? $_POST['notes'] : [];

        // Validasi nilai harus angka; satuan % maksimal 100 (review LPM 25 Nov — B4).
        foreach ($indicators as $indicator) {
            $indId = (int)$indicator['id'];
            $raw   = trim((string)($rawValues[$indId] ?? ''));
            if ($raw !== '' && !is_numeric($raw)) {
                flash('danger', 'Nilai indikator ' . $indicator['code'] . ' harus berupa angka.');
                $this->redirect('/monev/report?period_id=' . $periodId . '&unit_id=' . $unitId);
            }
            if ($raw !== ''
                && trim((string)($indicator['unit_of_measure'] ?? '')) === '%'
                && (float)$raw > 100) {
                flash('danger', 'Nilai indikator ' . $indicator['code'] . ' ber satuan % maksimal 100.');
                $this->redirect('/monev/report?period_id=' . $periodId . '&unit_id=' . $unitId);
            }
        }

        // Review LPM foto-02: sebelum diajukan, SETIAP indikator wajib lengkap —
        // nilai aktual + bukti pendukung + catatan (bukan sekadar "minimal satu").
        if ($action === 'submit') {
            if (!$indicators) {
                flash('danger', 'Belum ada indikator aktif — laporan tidak dapat diajukan.');
                $this->redirect('/monev/report?period_id=' . $periodId . '&unit_id=' . $unitId);
            }

            $priorReport = MonevReport::findPair($periodId, $unitId);
            $savedValues = $priorReport
                ? MonevValue::forReport((int)$priorReport['id'])
                : [];
            $savedByInd  = [];
            foreach ($savedValues as $sv) {
                $savedByInd[(int)$sv['indicator_id']] = $sv;
            }

            $incomplete = [];
            foreach ($indicators as $indicator) {
                $indId = (int)$indicator['id'];
                $raw   = trim((string)($rawValues[$indId] ?? ''));
                $note  = trim((string)($rawNotes[$indId] ?? ''));

                // Bukti: berkas baru diunggah ATAU bukti tersimpan dari isian sebelumnya.
                $hasNewFile = isset($_FILES['evidence']['error'][$indId])
                    && (int)$_FILES['evidence']['error'][$indId] !== UPLOAD_ERR_NO_FILE;
                $hasEvidence = $hasNewFile || !empty($savedByInd[$indId]['evidence_file'] ?? null);
                // Catatan: isian baru ATAU catatan tersimpan dari simpan sebelumnya.
                $hasNote = $note !== ''
                    || trim((string)($savedByInd[$indId]['notes'] ?? '')) !== '';

                $lacks = [];
                if ($raw === '') {
                    $lacks[] = 'nilai';
                }
                if (!$hasEvidence) {
                    $lacks[] = 'bukti';
                }
                if (!$hasNote) {
                    $lacks[] = 'catatan';
                }
                if ($lacks) {
                    $incomplete[] = $indicator['code'] . ' (' . implode(', ', $lacks) . ')';
                }
            }

            if ($incomplete) {
                flash('danger', 'Seluruh indikator wajib lengkap sebelum diajukan — belum terpenuhi: '
                    . implode('; ', $incomplete) . '.');
                $this->redirect('/monev/report?period_id=' . $periodId . '&unit_id=' . $unitId);
            }
        }

        // Buat laporan bila belum ada; tolak ubah bila sudah disetujui.
        $report   = MonevReport::findPair($periodId, $unitId);
        $reportId = 0;
        if (!$report) {
            $reportId = MonevReport::create([
                'period_id'    => $periodId,
                'unit_id'      => $unitId,
                'submitted_by' => Auth::id(),
                'status'       => 'draft',
                'total_score'  => null,
                'notes'        => '',
                'submitted_at' => null,
            ]);
        } else {
            if ($report['status'] === 'approved') {
                flash('warning', 'Laporan yang sudah disetujui tidak dapat diubah.');
                $this->redirect('/monev/reports/' . (int)$report['id']);
            }
            $reportId = (int)$report['id'];
        }

        // Simpan nilai per indikator.
        $saved = 0;
        foreach ($indicators as $indicator) {
            $indId    = (int)$indicator['id'];
            $raw      = trim((string)($rawValues[$indId] ?? ''));
            $note     = trim((string)($rawNotes[$indId] ?? ''));
            $existing = MonevValue::findPair($reportId, $indId);

            if ($raw === '') {
                // Isian dikosongkan → hapus nilai beserta berkas buktinya.
                if ($existing) {
                    delete_upload($existing['evidence_file'] ?? null);
                    MonevValue::deleteById((int)$existing['id']);
                }
                continue;
            }

            $evidence = $this->uploadEvidence($indId);
            if (isset($evidence['error'])) {
                flash('danger', $evidence['error']);
                $this->redirect('/monev/report?period_id=' . $periodId . '&unit_id=' . $unitId);
            }

            $payload = [
                'value' => (float)$raw,
                'notes' => $note !== '' ? $note : null,
            ];
            if (isset($evidence['path'])) {
                if ($existing) {
                    delete_upload($existing['evidence_file'] ?? null);
                }
                $payload['evidence_file'] = $evidence['path'];
            }

            if ($existing) {
                MonevValue::updateById((int)$existing['id'], $payload);
            } else {
                $payload['report_id']    = $reportId;
                $payload['indicator_id'] = $indId;
                MonevValue::create($payload);
            }
            $saved++;
        }

        MonevReport::recalculate($reportId);

        if ($action === 'submit') {
            MonevReport::updateById($reportId, [
                'status'       => 'submitted',
                'submitted_by' => Auth::id(),
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);

            // Review LPM foto-05: notifikasi "menunggu review" maksimal 1 per laporan —
            // pengajuan ulang mengganti notifikasi lama, bukan menambah.
            Database::stmt(
                "DELETE FROM notifications
                  WHERE ref_table = 'monev_reports' AND ref_id = ?
                    AND title = 'Laporan Monev menunggu review'",
                [$reportId]
            );

            $this->notifySuperAdmins(
                'Laporan Monev menunggu review',
                'Laporan Monev "' . $period['name'] . '" unit "' . $unit['name'] . '" diajukan oleh '
                    . (Auth::user()['name'] ?? '-') . '.',
                'monev',
                'monev_reports',
                $reportId
            );

            audit('submit', 'monev_reports', $reportId, ['period' => $period['name'], 'unit' => $unit['name']]);
            flash('success', 'Laporan Monev berhasil diajukan untuk direview.');
        } else {
            audit('save', 'monev_reports', $reportId, ['indicators' => $saved, 'unit' => $unit['name']]);
            flash('success', 'Draf laporan Monev berhasil disimpan (' . $saved . ' indikator).');
        }

        $this->redirect('/monev/reports/' . $reportId);
    }

    /* ------------------------------------------------------- Review (Super) */

    public function showReport(string $id): void
    {
        $this->noStore();
        $report = MonevReport::detail((int)$id);
        if (!$report) {
            $this->notFound('Laporan tidak ditemukan.');
        }

        $this->view('monev/report-show', [
            'title'   => 'Detail Laporan Monev',
            'report'  => $report,
            'values'  => MonevValue::forReport((int)$id),
            'canEdit' => Auth::isSuperAdmin() || (int)$report['unit_id'] === (int)Auth::unitId(),
        ]);
    }

    public function approveReport(string $id): void
    {
        $this->requireSuperAdmin();

        $report = $this->mustBeSubmitted($id);

        MonevReport::recalculate((int)$id);
        MonevReport::updateById((int)$id, ['status' => 'approved']);

        $ownerId = (int)($report['submitted_by'] ?? 0);
        if ($ownerId > 0) {
            Notification::send(
                $ownerId,
                'Laporan Monev disetujui',
                'Laporan Monev "' . $report['period_name'] . '" unit "' . $report['unit_name'] . '" telah disetujui.',
                'monev',
                'monev_reports',
                (int)$id
            );
        }

        audit('approve', 'monev_reports', (int)$id, ['period' => $report['period_name'], 'unit' => $report['unit_name']]);
        flash('success', 'Laporan Monev disetujui.');
        $this->redirect('/monev/reports/' . (int)$id);
    }

    public function returnReport(string $id): void
    {
        $this->requireSuperAdmin();

        $report = $this->mustBeSubmitted($id);

        $comment = trim((string)($_POST['comment'] ?? ''));
        if ($comment === '') {
            flash('danger', 'Catatan pengembalian wajib diisi.');
            $this->redirect('/monev/reports/' . (int)$id);
        }

        $oldNotes = (string)($report['notes'] ?? '');
        $newNotes = ($oldNotes !== '' ? $oldNotes . "\n" : '')
            . '[pengembalian LPM ' . date('d/m/Y H:i') . '] ' . $comment;

        MonevReport::updateById((int)$id, ['status' => 'draft', 'notes' => $newNotes]);

        $ownerId = (int)($report['submitted_by'] ?? 0);
        if ($ownerId > 0) {
            Notification::send(
                $ownerId,
                'Laporan Monev dikembalikan',
                'Laporan Monev "' . $report['period_name'] . '" unit "' . $report['unit_name']
                    . '" dikembalikan: ' . $comment,
                'monev',
                'monev_reports',
                (int)$id
            );
        }

        audit('reject', 'monev_reports', (int)$id, ['comment' => $comment]);
        flash('warning', 'Laporan Monev dikembalikan ke pengisi untuk perbaikan.');
        $this->redirect('/monev/reports/' . (int)$id);
    }

    /* ------------------------------------------------------------ Bukti */

    public function downloadEvidence(string $id): void
    {
        $value = MonevValue::find((int)$id);
        if (!$value || empty($value['evidence_file'])) {
            $this->notFound('Bukti tidak ditemukan.');
        }

        $base = realpath(rtrim((string)config('upload_dir'), '/'));
        $full = realpath(rtrim((string)config('upload_dir'), '/') . '/' . ltrim((string)$value['evidence_file'], '/'));

        if ($base === false || $full === false || !str_starts_with((string)$full, (string)$base) || !is_file($full)) {
            $this->notFound('Berkas tidak ditemukan di server.');
        }

        audit('download', 'monev_values', (int)$id, ['file' => basename((string)$full)]);

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

    /* ------------------------------------------------------------- Utilitas */

    /** Anti-cache: tombol back memaksa GET ulang sehingga formulir/laporan menampilkan
     *  data tersimpan terkini (review LPM foto-14 — pola PRG sudah dipakai saveReport). */
    private function noStore(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    /** Anggota hanya boleh mengisi laporan unitnya sendiri. */
    private function guardUnitAccess(int $unitId): void
    {
        if (Auth::isSuperAdmin()) {
            return;
        }
        if ((int)Auth::unitId() !== $unitId) {
            $this->forbidden();
        }
    }

    private function mustBeSubmitted(string $id): array
    {
        $report = MonevReport::detail((int)$id);
        if (!$report) {
            $this->notFound('Laporan tidak ditemukan.');
        }
        if ($report['status'] !== 'submitted') {
            flash('warning', 'Hanya laporan berstatus "menunggu review" yang dapat diproses.');
            $this->redirect('/monev/reports/' . (int)$id);
        }
        return $report;
    }

    private function notifySuperAdmins(string $title, string $message, string $type, string $refTable, int $refId): void
    {
        $ids = User::superAdminIds();
        if ($ids) {
            Notification::send($ids, $title, $message, $type, $refTable, $refId);
        }
    }

    /** Unggah bukti per indikator (input bernama evidence[<id>]). */
    private function uploadEvidence(int $indicatorId): array
    {
        if (empty($_FILES['evidence']) || !isset($_FILES['evidence']['error'][$indicatorId])) {
            return [];
        }

        $error = (int)$_FILES['evidence']['error'][$indicatorId];
        if ($error === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['error' => 'Gagal mengunggah bukti (kode ' . $error . ').'];
        }

        $size = (int)$_FILES['evidence']['size'][$indicatorId];
        if ($size > (int)config('upload_max_kb') * 1024) {
            return ['error' => 'Ukuran bukti melebihi batas ' . (int)config('upload_max_kb') . ' KB.'];
        }

        $original = (string)$_FILES['evidence']['name'][$indicatorId];
        $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, (array)config('upload_ext'), true)) {
            return ['error' => 'Ekstensi .' . $ext . ' tidak diizinkan.'];
        }

        $relative = 'bukti-monev/' . date('Y/m') . '/';
        $dir      = rtrim((string)config('upload_dir'), '/') . '/' . $relative;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['error' => 'Gagal membuat folder unggahan.'];
        }

        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($_FILES['evidence']['tmp_name'][$indicatorId], $dir . $filename)) {
            return ['error' => 'Gagal menyimpan berkas bukti.'];
        }

        return ['path' => $relative . $filename];
    }
}
