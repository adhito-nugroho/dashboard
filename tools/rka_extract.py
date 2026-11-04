#!/usr/bin/env python3
"""Ekstrak PDF "Cetak RKA Rincian Belanja" SIPD menjadi JSON untuk tools/import_rka.php.

Butuh: pip install pypdf

Contoh:
  python tools/rka_extract.py --dir "C:\\Users\\user\\Downloads" \
      --match "Cetak RKA Rincian Belanja" --out rka_import.json

Hasil JSON berisi per file: tahun, program, kegiatan, sub_kegiatan, alokasi,
dan daftar rekening (kode PDF dinormalisasi ke format kode aplikasi).
"""
import argparse
import json
import re
import sys
from pathlib import Path

try:
    from pypdf import PdfReader
except ImportError:
    sys.exit("pypdf belum terpasang. Jalankan: pip install pypdf")

# Baris rekening daun: "5.1.02.01.001.00039 Nama ... 112.358.000,00 112.358.000,00 0,00"
REK_RE = re.compile(
    r"^(\d+(?:\.\d+){5})\s+(.+?)\s+([\d.]+,\d{2})\s+([\d.]+,\d{2})\s+(-?[\d.]+,\d{2})\s*$"
)
PROGRAM_RE = re.compile(r"^Program\s*:\s*([0-9.]+)\s+(.+)$")
KEGIATAN_RE = re.compile(r"^Kegiatan\s*:\s*([0-9.]+)\s+(.+)$")
SUB_RE = re.compile(r"^Sub Kegiatan\s*:\s*([0-9.]+)\s+(.+)$")
TAHUN_RE = re.compile(r"Tahun Anggaran\s+(\d{4})")
ALOKASI_RE = re.compile(r"^Alokasi\s+(\d{4})\s*:\s*Rp\.?\s*([\d.,]+)$")
TAG_RE = re.compile(r"^\[\s*#\s*\]\s*(\S+)\s*$")


def rupiah(s: str) -> float:
    s = s.strip()
    if s in ("", "-"):
        return 0.0
    return float(s.replace(".", "").replace(",", "."))


def normalize_kode(kode: str) -> str:
    """5.1.02.01.001.00025 -> 5.1.02.01.01.0025 (format kode di aplikasi)."""
    p = kode.split(".")
    if len(p) == 6:
        try:
            return ".".join(p[:4] + [str(int(p[4])).zfill(2), str(int(p[5])).zfill(4)])
        except ValueError:
            pass
    return kode


def parse_pdf(path: Path) -> dict | None:
    reader = PdfReader(str(path))
    text = "\n".join((page.extract_text() or "") for page in reader.pages)

    data = {
        "file": str(path),
        "tahun": None,
        "program": None,
        "kegiatan": None,
        "sub_kegiatan": None,
        "alokasi": None,
        "rekening": [],
        "warnings": [],
    }

    raw_lines = []
    for raw in text.splitlines():
        line = re.sub(r"[ \t]+", " ", raw).strip()
        if line:
            raw_lines.append(line)

    # PDF kadang memecah "Sub Kegiatan" dan ":" ke baris berbeda.
    lines = []
    i = 0
    while i < len(raw_lines):
        line = raw_lines[i]
        if line in ("Program", "Kegiatan", "Sub Kegiatan") and i + 1 < len(raw_lines) and raw_lines[i + 1].startswith(":"):
            lines.append(line + " " + raw_lines[i + 1])
            i += 2
            continue
        lines.append(line)
        i += 1

    stop_headers = {
        "Program", "Kegiatan", "Sub Kegiatan", "SPM", "Jenis Layanan",
        "Sumber Pendanaan", "Lokasi", "Waktu Pelaksanaan", "Kelompok Sasaran",
        "Alokasi", "Indikator dan Tolak Ukur Kinerja Kegiatan",
        "Rincian Anggaran Belanja Kegiatan", "Rincian Anggaran Belanja Sub Kegiatan",
    }

    def join_name(idx: int, name: str) -> tuple[str, int]:
        """Gabungkan baris lanjutan nama field (PDF memecah teks panjang)."""
        j = idx + 1
        while j < len(lines):
            nxt = lines[j]
            if nxt in stop_headers or nxt.startswith(("[", "#")) or re.match(r"^[A-Za-z][A-Za-z /()\-]*\s*:", nxt):
                break
            name += " " + nxt
            j += 1
        return re.sub(r"\s+", " ", name).strip(), j

    i = 0
    last_rek = -1
    kode_index: dict[str, int] = {}
    while i < len(lines):
        line = lines[i]

        # Baris tagar: "[ # ] #NBS4_AGRI_INDUSTRI". "[ # ] PAD" = #PAD.
        m_tag = TAG_RE.match(line)
        if m_tag:
            tag = m_tag.group(1)
            if last_rek >= 0:
                if tag.startswith("#"):
                    data["rekening"][last_rek]["tagar"] = tag
                elif tag.upper() == "PAD":
                    data["rekening"][last_rek]["tagar"] = "#PAD"
            last_rek = -1
            i += 1
            continue

        if data["program"] is None:
            m = PROGRAM_RE.match(line)
            if m:
                nama, i = join_name(i, m.group(2).strip())
                data["program"] = {"kode": m.group(1).strip(), "nama": nama}
                continue
        if data["kegiatan"] is None:
            m = KEGIATAN_RE.match(line)
            if m:
                nama, i = join_name(i, m.group(2).strip())
                data["kegiatan"] = {"kode": m.group(1).strip(), "nama": nama}
                continue
        if data["sub_kegiatan"] is None:
            m = SUB_RE.match(line)
            if m:
                nama, i = join_name(i, m.group(2).strip())
                data["sub_kegiatan"] = {"kode": m.group(1).strip(), "nama": nama}
                continue
        if data["tahun"] is None:
            m = TAHUN_RE.search(line)
            if m:
                data["tahun"] = int(m.group(1))

        m = ALOKASI_RE.match(line)
        if m and data["tahun"] is not None and int(m.group(1)) == data["tahun"]:
            data["alokasi"] = rupiah(m.group(2))
            i += 1
            continue

        m = REK_RE.match(line)
        if m:
            kode_pdf = m.group(1)
            if kode_pdf.startswith("5."):
                kode = normalize_kode(kode_pdf)
                if kode in kode_index:
                    data["warnings"].append(f"kode duplikat di PDF: {kode_pdf}")
                    last_rek = kode_index[kode]
                else:
                    data["rekening"].append({
                        "kode_pdf": kode_pdf,
                        "kode": kode,
                        "nama": re.sub(r"\s+", " ", m.group(2)).strip(),
                        "nilai": rupiah(m.group(4)),  # kolom "Sesudah"
                        "tagar": None,
                    })
                    kode_index[kode] = len(data["rekening"]) - 1
                    last_rek = kode_index[kode]
        i += 1

    if not data["tahun"]:
        # Fallback: tahun dari baris "Alokasi 2026"
        m = re.search(r"Alokasi\s+(\d{4})", text)
        if m:
            data["tahun"] = int(m.group(1))

    if not all([data["program"], data["kegiatan"], data["sub_kegiatan"], data["tahun"]]):
        return None
    if not data["rekening"]:
        return None

    total = round(sum(r["nilai"] for r in data["rekening"]), 2)
    if data["alokasi"] is not None and abs(total - data["alokasi"]) > 0.01:
        data["warnings"].append(
            f"total rekening {total:,.2f} != alokasi {data['alokasi']:,.2f}".replace(",", ".")
        )
    data["total_rekening"] = total
    return data


def main() -> int:
    ap = argparse.ArgumentParser(description="Ekstrak RKA Rincian Belanja SIPD (PDF) -> JSON")
    ap.add_argument("--dir", help="folder yang dicari (rekursif)")
    ap.add_argument("--files", nargs="*", default=[], help="file PDF tertentu")
    ap.add_argument("--match", default="Cetak RKA Rincian Belanja", help="filter nama file")
    ap.add_argument("--out", default="rka_import.json", help="file JSON hasil")
    args = ap.parse_args()

    paths: list[Path] = [Path(f) for f in args.files]
    if args.dir:
        paths += [p for p in Path(args.dir).rglob("*.pdf") if args.match.lower() in p.name.lower()]
    paths = sorted({p.resolve() for p in paths if p.exists()})

    if not paths:
        print("Tidak ada PDF yang cocok.")
        return 1

    results, skipped = [], []
    for p in paths:
        try:
            item = parse_pdf(p)
        except Exception as e:  # noqa: BLE001
            skipped.append((p, f"gagal baca: {e}"))
            continue
        if item is None:
            skipped.append((p, "pola RKA tidak dikenali / rekening kosong"))
            continue
        results.append(item)
        print(f"OK  {p.name}  ({len(item['rekening'])} rekening)")

    for p, why in skipped:
        print(f"SKIP {p.name}: {why}")

    Path(args.out).write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"\n{len(results)} file -> {args.out} ({len(skipped)} dilewati)")
    return 0 if results else 1


if __name__ == "__main__":
    raise SystemExit(main())
