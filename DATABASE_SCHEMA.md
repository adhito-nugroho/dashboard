# Database Schema

## Tables and Columns

### seksi
- `id` (Primary Key)
- `kode_seksi`
- `nama_seksi`

### program
- `id` (Primary Key)
- `kode_program`
- `nama_program`
- `tahun`

### kegiatan
- `id` (Primary Key)
- `program_id` → Foreign Key to `program.id`
- `kode_kegiatan`
- `nama_kegiatan`

### sub_kegiatan
- `id` (Primary Key)
- `kegiatan_id` → Foreign Key to `kegiatan.id`
- `seksi_id` → Foreign Key to `seksi.id`
- `kode_sub_kegiatan`
- `nama_sub_kegiatan`

### rekening
- `id` (Primary Key)
- `sub_kegiatan_id` → Foreign Key to `sub_kegiatan.id`
- `kode_rekening`
- `nama_rekening`

### pagu
- `id` (Primary Key)
- `rekening_id` → Foreign Key to `rekening.id`
- `tahun`
- `nilai_pagu`

### rak
- `id` (Primary Key)
- `rekening_id` → Foreign Key to `rekening.id`
- `tahun`
- `bulan`
- `nilai_rak`

### transaksi
- `id` (Primary Key)
- `tanggal`
- `seksi_id` → Foreign Key to `seksi.id`
- `rekening_id` → Foreign Key to `rekening.id`
- `uraian`
- `nilai`
- `nomor_bukti`

## Relationships

```
program (1) ──→ (N) kegiatan
kegiatan (1) ──→ (N) sub_kegiatan
seksi (1) ──→ (N) sub_kegiatan
sub_kegiatan (1) ──→ (N) rekening
rekening (1) ──→ (N) pagu
rekening (1) ──→ (N) rak
seksi (1) ──→ (N) transaksi
rekening (1) ──→ (N) transaksi
```

## Hierarchy

```
program
  └── kegiatan
      └── sub_kegiatan
          ├── seksi (penanggung jawab)
          └── rekening
              ├── pagu (budget allocation)
              ├── rak (monthly budget)
              └── transaksi (transactions) (tetap butuh seksi_id seperti saat ini)
```

## Notes

- All table and column names must be used exactly as specified
- No additional tables or fields should be created
- Foreign key relationships must be maintained as defined

