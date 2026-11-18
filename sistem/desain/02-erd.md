# 02 — ENTITY RELATIONSHIP DIAGRAM (ERD)

## A. Daftar Entitas

| # | Entitas (Tabel) | Penjelasan | Kunci Utama |
|---|---|---|---|
| 1 | `units` | Unit kerja / bidang (LPM, Akademik, dst.) | `id` |
| 2 | `users` | Pengguna: **super_admin** (LPM) & **anggota** (Waket I–IV) | `id` |
| 3 | `standards` | Standar SPMI (Pendidikan, Penelitian, Pengabdian, SDM, Sarpras, Pembiayaan, Penjaminan Mutu, Data & Informasi) | `id` |
| 4 | `document_categories` | Hierarki dokumen (Kebijakan→Pedoman→Manual/SOP→Instrumen→Formulir→Laporan), mendukung **M1** | `id` |
| 5 | `documents` | Dokumen SPMI: nomor, versi, **tanggal & masa berlaku**, **status 🟢🟡🔴**, status persetujuan | `id` |
| 6 | `document_versions` | Riwayat versi setiap dokumen | `id` |
| 7 | `document_approvals` | Alur persetujuan berjenjang | `id` |
| 8 | `monev_periods` | Periode Monev (**6 bulan sekali**) | `id` |
| 9 | `monev_indicators` | Indikator capaian (turunan standar) untuk **M2** | `id` |
| 10 | `monev_reports` | Laporan capaian satu unit pada satu periode | `id` |
| 11 | `monev_values` | Nilai capaian per indikator + unggahan bukti | `id` |
| 12 | `ami_schedules` | Jadwal AMI **tahunan** (M3) | `id` |
| 13 | `ami_instruments` | Instrumen AMI — **indikator fleksibel (bisa diedit)** | `id` |
| 14 | `ami_instrument_logs` | Jejak perubahan instrumen (siapa, kapan, apa yang berubah) | `id` |
| 15 | `ami_assignments` | Penugasan pasangan **auditor–auditee** per unit | `id` |
| 16 | `ami_assessments` | Penilaian per indikator: skor 0–4, bukti | `id` |
| 17 | `ami_findings` | Temuan hasil penilaian | `id` |
| 18 | `action_plans` | **Rencana tindak lanjut**: PIC, tenggat, status | `id` |
| 19 | `action_evidence` | Bukti penyelesaian + status verifikasi | `id` |
| 20 | `satisfaction_surveys` | **Formulir kepuasan** periode tertentu | `id` |
| 21 | `satisfaction_questions` | Pertanyaan kepuasan | `id` |
| 22 | `satisfaction_responses` | Satu jawaban responden | `id` |
| 23 | `satisfaction_answers` | Skor per pertanyaan | `id` |
| 24 | `notifications` | Notifikasi pengguna (M5) | `id` |
| 25 | `audit_logs` | Jejak audit seluruh aksi penting (M5) | `id` |
| 26 | `settings` | Pengaturan sistem (nama PT, logo, dst.) | `key` |

## B. Relasi

| # | Relasi | Kardinalitas | Keterangan |
|---|---|---|---|
| R1 | `units` → `users` | 1 : N | Satu unit punya banyak pengguna |
| R2 | `units` → `monev_reports` | 1 : N | Laporan per unit per periode |
| R3 | `units` → `ami_assignments` | 1 : N | Satu unit diaudit tiap tahun |
| R4 | `standards` → `documents` | 1 : N | Dokumen terkait satu standar |
| R5 | `standards` → `monev_indicators` | 1 : N | Indikator turunan standar |
| R6 | `standards` → `ami_instruments` | 1 : N | Instrumen turunan standar |
| R7 | `document_categories` → `documents` | 1 : N | Kategori berjenjang |
| R8 | `documents` → `document_versions` | 1 : N | Riwayat versi |
| R9 | `documents` → `document_approvals` | 1 : N | Alur persetujuan |
| R10 | `users` → `documents` | 1 : N | Pemilik / pengunggah dokumen |
| R11 | `monev_periods` → `monev_reports` | 1 : N | Satu periode, banyak unit |
| R12 | `monev_reports` → `monev_values` | 1 : N | Nilai tiap indikator |
| R13 | `monev_indicators` → `monev_values` | 1 : N | Indikator dinilai banyak periode |
| R14 | `ami_schedules` → `ami_instruments` | 1 : N | Instrumen per tahun AMI |
| R15 | `ami_schedules` → `ami_assignments` | 1 : N | Penugasan auditor per tahun |
| R16 | `ami_assignments` → `ami_assessments` | 1 : N | Penilaian per unit |
| R17 | `ami_instruments` → `ami_assessments` | 1 : N | Indikator dinilai banyak unit |
| R18 | `ami_assessments` → `ami_findings` | 1 : N | Satu penilaian dapat melahirkan temuan |
| R19 | `ami_findings` → `action_plans` | 1 : N | Satu temuan bisa punya beberapa aksi |
| R20 | `action_plans` → `action_evidence` | 1 : N | Beberapa bukti per aksi |
| R21 | `satisfaction_surveys` → `satisfaction_questions` / `responses` | 1 : N | Formulir & isian |
| R22 | `satisfaction_responses` → `satisfaction_answers` | 1 : N | Jawaban per pertanyaan |
| R23 | `users` → `notifications` / `audit_logs` | 1 : N | Jejak & pemberitahuan |

## C. Mermaid ERD (bagian per modul)

> Salin ke https://mermaid.live → ekspor PNG → `skripsi/05-gambar/`.

### C.1 M1 — Dokumen SPMI

```mermaid
erDiagram
    units ||--o{ users : "memiliki"
    units ||--o{ documents : "mengunggah"
    standards ||--o{ documents : "terkait"
    document_categories ||--o{ documents : "mengelompokkan"
    documents ||--o{ document_versions : "memiliki versi"
    documents ||--o{ document_approvals : "melewati"
    users ||--o{ document_approvals : "menyetujui"

    units {
        int id PK
        varchar code
        varchar name
        tinyint is_active
    }
    users {
        int id PK
        int unit_id FK
        varchar name
        varchar email UK
        varchar password
        enum role "super_admin|anggota"
        tinyint is_active
        datetime last_login_at
    }
    standards {
        int id PK
        varchar code
        varchar name
        varchar category
        tinyint is_active
    }
    document_categories {
        int id PK
        int parent_id FK
        varchar name
        int level
        int sort_order
    }
    documents {
        int id PK
        int category_id FK
        int standard_id FK
        int unit_id FK
        int owner_user_id FK
        varchar title
        varchar doc_number
        varchar version
        date effective_date
        date expiry_date
        varchar file_path
        enum approval_status "draft|submitted|approved|rejected|archived"
        enum status_color "green|yellow|red"
        text description
        datetime approved_at
    }
    document_versions {
        int id PK
        int document_id FK
        varchar version
        varchar file_path
        text change_note
        int submitted_by FK
        int approved_by FK
        enum status "pending|approved|rejected"
        datetime created_at
    }
    document_approvals {
        int id PK
        int document_id FK
        int step
        int approver_user_id FK
        enum status "pending|approved|rejected"
        text comment
        datetime acted_at
    }
```

### C.2 M2 — Monev

```mermaid
erDiagram
    units ||--o{ monev_reports : "mengisi"
    monev_periods ||--o{ monev_reports : "periode"
    standards ||--o{ monev_indicators : "turunan"
    monev_reports ||--o{ monev_values : "berisi"
    monev_indicators ||--o{ monev_values : "dinilai"

    monev_periods {
        int id PK
        varchar name
        date start_date
        date end_date
        enum status "open|closed"
    }
    monev_indicators {
        int id PK
        int standard_id FK
        varchar code
        varchar name
        varchar unit_of_measure
        decimal target
        decimal weight
        tinyint is_active
    }
    monev_reports {
        int id PK
        int period_id FK
        int unit_id FK
        int submitted_by FK
        enum status "draft|submitted|approved"
        decimal total_score
        text notes
        datetime submitted_at
    }
    monev_values {
        int id PK
        int report_id FK
        int indicator_id FK
        decimal value
        decimal achievement_percent
        varchar evidence_file
        text notes
    }
```

### C.3 M3 — AMI & Evaluasi (+ Formulir Kepuasan)

```mermaid
erDiagram
    ami_schedules ||--o{ ami_instruments : "instrumen"
    ami_schedules ||--o{ ami_assignments : "penugasan"
    units ||--o{ ami_assignments : "diaudit"
    ami_instruments ||--o{ ami_instrument_logs : "jejak ubah"
    ami_assignments ||--o{ ami_assessments : "penilaian"
    ami_instruments ||--o{ ami_assessments : "dinilai"
    ami_assessments ||--o{ ami_findings : "melahirkan"
    users ||--o{ ami_instrument_logs : "mengubah"
    satisfaction_surveys ||--o{ satisfaction_questions : "berisi"
    satisfaction_surveys ||--o{ satisfaction_responses : "menerima"
    satisfaction_responses ||--o{ satisfaction_answers : "jawaban"
    satisfaction_questions ||--o{ satisfaction_answers : "dijawab"

    ami_schedules {
        int id PK
        int year
        varchar name
        date start_date
        date end_date
        enum status "draft|running|closed"
    }
    ami_instruments {
        int id PK
        int schedule_id FK
        int standard_id FK
        varchar code
        text question
        text assessment_guide
        decimal weight
        tinyint is_active
        int created_by FK
        int updated_by FK
        datetime updated_at
    }
    ami_instrument_logs {
        int id PK
        int instrument_id FK
        int changed_by FK
        text old_value
        text new_value
        text reason
        datetime created_at
    }
    ami_assignments {
        int id PK
        int schedule_id FK
        int unit_id FK
        int auditor_user_id FK
        int auditee_lead_user_id FK
        enum status "assigned|in_progress|completed"
        varchar report_number
        date audit_date
    }
    ami_assessments {
        int id PK
        int assignment_id FK
        int instrument_id FK
        int score
        varchar evidence_file
        text finding_note
        int filled_by FK
        datetime filled_at
    }
    ami_findings {
        int id PK
        int assessment_id FK
        int assignment_id FK
        enum finding_type "ketidaksesuaian|observasi|rekomendasi"
        enum severity "tinggi|sedang|rendah"
        text description
        int identified_by FK
        enum status "open|in_progress|verified|closed"
        datetime created_at
    }
    satisfaction_surveys {
        int id PK
        varchar name
        date start_date
        date end_date
        enum status "draft|open|closed"
    }
    satisfaction_questions {
        int id PK
        int survey_id FK
        text question
        enum type "skala|pilihan|teks"
        decimal weight
        tinyint is_active
    }
    satisfaction_responses {
        int id PK
        int survey_id FK
        int respondent_user_id FK
        datetime submitted_at
    }
    satisfaction_answers {
        int id PK
        int response_id FK
        int question_id FK
        decimal score
        text answer_text
    }
```

### C.4 M4 — Pengendalian–Peningkatan

```mermaid
erDiagram
    ami_findings ||--o{ action_plans : "menjadi"
    users ||--o{ action_plans : "PIC (penanggung jawab)"
    action_plans ||--o{ action_evidence : "bukti"
    users ||--o{ action_evidence : "mengunggah"

    action_plans {
        int id PK
        int finding_id FK
        int pic_user_id FK
        int pic_unit_id FK
        text description
        date due_date
        enum status "open|in_progress|done|verified"
        enum priority "tinggi|sedang|rendah"
        int verified_by FK
        datetime verified_at
        text verification_note
        datetime created_at
    }
    action_evidence {
        int id PK
        int action_plan_id FK
        varchar file_path
        text note
        int uploaded_by FK
        enum status "pending|approved|rejected"
        datetime created_at
    }
```

### C.5 M5 — Pendukung

```mermaid
erDiagram
    users ||--o{ notifications : "menerima"
    users ||--o{ audit_logs : "melakukan"

    notifications {
        int id PK
        int user_id FK
        varchar title
        text message
        enum type "persetujuan|tenggat|dokumen|temuan"
        varchar ref_table
        int ref_id
        tinyint is_read
        datetime created_at
    }
    audit_logs {
        int id PK
        int user_id FK
        varchar action
        varchar table_name
        int row_id
        text detail
        varchar ip_address
        datetime created_at
    }
    settings {
        varchar key UK
        text value
        text description
    }
```

## D. Alur data lintas modul (inti "terintegrasi")

```
M1 Dokumen ──(bukti)──► M2 Monev ──(capaian)──► M3 AMI ──(temuan)──► M4 Aksi
   ▲                                                                    │
   └────────── revisi dokumen (umpan balik, UC-31) ◄─────────────────────┘
```

Temuan pada M3 otomatis menjadi entri `action_plans` (M4); bukti yang diverifikasi pada M4 dapat memicu revisi dokumen pada M1 — inilah wujud **siklus PPEPP tertutup** dalam skema data.
