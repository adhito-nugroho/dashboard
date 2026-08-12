<?php
// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isEdit = isset($rak['rekening_id']) && isset($rak['tahun']);
$programId = $rak['program_id'] ?? '';
$kegiatanId = $rak['kegiatan_id'] ?? '';
$subKegiatanId = $rak['sub_kegiatan_id'] ?? '';
$rekeningId = $rak['rekening_id'] ?? '';
$tahun = $rak['tahun'] ?? date('Y');
$rakData = $rak['rak_data'] ?? [];
$pagu = $rak['pagu'] ?? null;
$errors = $validationErrors ?? [];

// For edit mode, get related data
$kegiatans = $kegiatans ?? [];
$subKegiatans = $subKegiatans ?? [];
$rekenings = $rekenings ?? [];
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <h2>
            <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i>
            <?= $isEdit ? 'Edit RAK' : 'Tambah RAK' ?>
        </h2>
        <p class="text-muted mb-0"><?= $isEdit ? 'Ubah rencana anggaran kas bulanan' : 'Tambahkan rencana anggaran kas bulanan baru' ?></p>
    </div>

    <!-- Form Card -->
    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= base_url('rak/' . $action . ($isEdit ? '/' . $rekeningId . '/' . $tahun : '')) ?>" novalidate id="rakForm">
                <!-- Program Selection -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="program_id" class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['program_id']) ? 'is-invalid' : '' ?>" 
                                id="program_id" 
                                name="program_id" 
                                required>
                            <option value="">-- Pilih Program --</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= $program['id'] ?>" 
                                        <?= ($programId == $program['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($program['kode_program'] . ' - ' . $program['nama_program'] . ' (' . $program['tahun'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="kegiatan_id" class="form-label">Kegiatan <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['kegiatan_id']) ? 'is-invalid' : '' ?>" 
                                id="kegiatan_id" 
                                name="kegiatan_id" 
                                required
                                <?= empty($programId) ? 'disabled' : '' ?>>
                            <option value="">-- Pilih Kegiatan --</option>
                            <?php foreach ($kegiatans as $kegiatan): ?>
                                <option value="<?= $kegiatan['id'] ?>" 
                                        <?= ($kegiatanId == $kegiatan['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kegiatan['kode_kegiatan'] . ' - ' . $kegiatan['nama_kegiatan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Sub Kegiatan and Rekening Selection -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="sub_kegiatan_id" class="form-label">Sub Kegiatan <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['sub_kegiatan_id']) ? 'is-invalid' : '' ?>" 
                                id="sub_kegiatan_id" 
                                name="sub_kegiatan_id" 
                                required
                                <?= empty($kegiatanId) ? 'disabled' : '' ?>>
                            <option value="">-- Pilih Sub Kegiatan --</option>
                            <?php foreach ($subKegiatans as $subKegiatan): ?>
                                <option value="<?= $subKegiatan['id'] ?>" 
                                        <?= ($subKegiatanId == $subKegiatan['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subKegiatan['kode_sub_kegiatan'] . ' - ' . $subKegiatan['nama_sub_kegiatan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="rekening_id" class="form-label">Rekening <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['rekening_id']) ? 'is-invalid' : '' ?>" 
                                id="rekening_id" 
                                name="rekening_id" 
                                required
                                <?= empty($subKegiatanId) ? 'disabled' : '' ?>>
                            <option value="">-- Pilih Rekening --</option>
                            <?php foreach ($rekenings as $rekening): ?>
                                <option value="<?= $rekening['id'] ?>" 
                                        <?= ($rekeningId == $rekening['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rekening['kode_rekening'] . ' - ' . $rekening['nama_rekening']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['rekening_id'])): ?>
                            <div class="invalid-feedback d-block">
                                <?= htmlspecialchars($errors['rekening_id']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tahun -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="tahun" class="form-label">Tahun <span class="text-danger">*</span></label>
                        <input type="number" 
                               class="form-control <?= isset($errors['tahun']) ? 'is-invalid' : '' ?>" 
                               id="tahun" 
                               name="tahun" 
                               value="<?= htmlspecialchars($tahun) ?>"
                               min="2000"
                               max="2100"
                               required>
                        <?php if (isset($errors['tahun'])): ?>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($errors['tahun']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Informasi Pagu</label>
                        <div id="paguInfo" class="p-3 bg-light rounded">
                            <p class="mb-0 text-muted">Pilih rekening dan tahun untuk melihat informasi pagu</p>
                        </div>
                    </div>
                </div>

                <!-- Monthly Input Table -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">Rencana Anggaran Kas per Bulan <span class="text-danger">*</span></label>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary" id="fillAllBtn" title="Isi semua bulan dengan nilai yang sama">
                                <i class="bi bi-distribute-vertical"></i> Isi Semua
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="clearAllBtn" title="Kosongkan semua nilai">
                                <i class="bi bi-x-circle"></i> Kosongkan
                            </button>
                        </div>
                    </div>
                    
                    <?php if (isset($errors['total'])): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($errors['total']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Progress Info -->
                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Total RAK:</strong> <span id="totalRak" class="fs-5">Rp 0</span>
                            </div>
                            <div>
                                <strong>Bulan Terisi:</strong> <span id="filledMonths">0</span>/12
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 10px;">
                            <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="15%">Bulan</th>
                                    <th>Nilai RAK</th>
                                    <th width="15%">Bulan</th>
                                    <th>Nilai RAK</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $bulanNames = [
                                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                ];
                                for ($i = 1; $i <= 12; $i += 2): 
                                ?>
                                    <tr>
                                        <td><strong><?= $bulanNames[$i] ?></strong></td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Rp</span>
                                                <input type="text" 
                                                       class="form-control text-end rak-input <?= isset($errors['bulan_' . $i]) ? 'is-invalid' : '' ?>" 
                                                       name="bulan_<?= $i ?>" 
                                                       id="bulan_<?= $i ?>"
                                                       value="<?= isset($rakData[$i]) && $rakData[$i] > 0 ? number_format($rakData[$i], 0, ',', '.') : '' ?>"
                                                       data-bulan="<?= $i ?>"
                                                       placeholder="0"
                                                       autocomplete="off">
                                                <button class="btn btn-outline-secondary copy-btn" type="button" title="Copy ke bulan berikutnya" data-source="<?= $i ?>" data-target="<?= $i + 1 ?>">
                                                    <i class="bi bi-arrow-right"></i>
                                                </button>
                                            </div>
                                            <?php if (isset($errors['bulan_' . $i])): ?>
                                                <div class="invalid-feedback d-block">
                                                    <?= htmlspecialchars($errors['bulan_' . $i]) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= $bulanNames[$i + 1] ?></strong></td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Rp</span>
                                                <input type="text" 
                                                       class="form-control text-end rak-input <?= isset($errors['bulan_' . ($i + 1)]) ? 'is-invalid' : '' ?>" 
                                                       name="bulan_<?= $i + 1 ?>" 
                                                       id="bulan_<?= $i + 1 ?>"
                                                       value="<?= isset($rakData[$i + 1]) && $rakData[$i + 1] > 0 ? number_format($rakData[$i + 1], 0, ',', '.') : '' ?>"
                                                       data-bulan="<?= $i + 1 ?>"
                                                       placeholder="0"
                                                       autocomplete="off">
                                                <?php if ($i + 1 < 12): ?>
                                                <button class="btn btn-outline-secondary copy-btn" type="button" title="Copy ke bulan berikutnya" data-source="<?= $i + 1 ?>" data-target="<?= $i + 2 ?>">
                                                    <i class="bi bi-arrow-right"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (isset($errors['bulan_' . ($i + 1)])): ?>
                                                <div class="invalid-feedback d-block">
                                                    <?= htmlspecialchars($errors['bulan_' . ($i + 1)]) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                    <a href="<?= base_url('rak') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Define BASE_URL
    const BASE_URL = '<?= base_url() ?>';
    
    const programSelect = document.getElementById('program_id');
    const kegiatanSelect = document.getElementById('kegiatan_id');
    const subKegiatanSelect = document.getElementById('sub_kegiatan_id');
    const rekeningSelect = document.getElementById('rekening_id');
    const tahunInput = document.getElementById('tahun');
    const rakInputs = document.querySelectorAll('.rak-input');
    const totalRakDisplay = document.getElementById('totalRak');
    const paguInfo = document.getElementById('paguInfo');
    const form = document.getElementById('rakForm');

    // Format number input
    rakInputs.forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/[^\d]/g, '');
            if (value) {
                this.value = parseInt(value).toLocaleString('id-ID');
            } else {
                this.value = '';
            }
            calculateTotal();
            updateProgress();
            checkPaguLimit();
        });
    });

    // Calculate total RAK
    function calculateTotal() {
        let total = 0;
        rakInputs.forEach(input => {
            const value = input.value.replace(/[^\d]/g, '');
            if (value) {
                total += parseFloat(value);
            }
        });
        totalRakDisplay.textContent = 'Rp ' + total.toLocaleString('id-ID');
        return total;
    }

    // Update progress bar
    function updateProgress() {
        let filledCount = 0;
        rakInputs.forEach(input => {
            const value = input.value.replace(/[^\d]/g, '');
            if (value && parseFloat(value) > 0) {
                filledCount++;
            }
        });
        
        const percentage = (filledCount / 12) * 100;
        document.getElementById('filledMonths').textContent = filledCount;
        document.getElementById('progressBar').style.width = percentage + '%';
        
        // Change color based on progress
        const progressBar = document.getElementById('progressBar');
        progressBar.className = 'progress-bar';
        if (percentage === 100) {
            progressBar.classList.add('bg-success');
        } else if (percentage >= 50) {
            progressBar.classList.add('bg-info');
        } else {
            progressBar.classList.add('bg-warning');
        }
    }

    // Fill all months with same value
    document.getElementById('fillAllBtn').addEventListener('click', function() {
        const value = prompt('Masukkan nilai RAK untuk semua bulan (tanpa titik/koma):');
        if (value && !isNaN(value) && parseFloat(value) >= 0) {
            const formattedValue = parseInt(value).toLocaleString('id-ID');
            rakInputs.forEach(input => {
                input.value = formattedValue;
            });
            calculateTotal();
            updateProgress();
            checkPaguLimit();
        }
    });

    // Clear all values
    document.getElementById('clearAllBtn').addEventListener('click', function() {
        if (confirm('Yakin ingin mengosongkan semua nilai RAK?')) {
            rakInputs.forEach(input => {
                input.value = '';
            });
            calculateTotal();
            updateProgress();
            checkPaguLimit();
        }
    });

    // Copy to next month
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const sourceMonth = this.getAttribute('data-source');
            const targetMonth = this.getAttribute('data-target');
            const sourceInput = document.getElementById('bulan_' + sourceMonth);
            const targetInput = document.getElementById('bulan_' + targetMonth);
            
            if (sourceInput && targetInput) {
                targetInput.value = sourceInput.value;
                calculateTotal();
                updateProgress();
                checkPaguLimit();
                
                // Visual feedback
                targetInput.classList.add('bg-success', 'bg-opacity-25');
                setTimeout(() => {
                    targetInput.classList.remove('bg-success', 'bg-opacity-25');
                }, 500);
            }
        });
    });

    // Check pagu limit
    function checkPaguLimit() {
        const rekeningId = rekeningSelect.value;
        const tahun = tahunInput.value;
        
        if (!rekeningId || !tahun) {
            return;
        }
        
        fetch(`${BASE_URL}rak/get-rekening-info?rekening_id=${rekeningId}&tahun=${tahun}`)
            .then(response => response.json())
            .then(data => {
                if (data.pagu) {
                    const totalRak = calculateTotal();
                    const sisaPagu = data.sisa_pagu - (totalRak - data.total_rak);
                    
                    paguInfo.innerHTML = `
                        <strong>Pagu:</strong> Rp ${parseFloat(data.pagu.nilai_pagu).toLocaleString('id-ID')}<br>
                        <strong>Total RAK:</strong> Rp ${totalRak.toLocaleString('id-ID')}<br>
                        <strong>Sisa Pagu:</strong> <span class="${sisaPagu < 0 ? 'text-danger' : 'text-success'}">Rp ${sisaPagu.toLocaleString('id-ID')}</span>
                    `;
                    
                    if (sisaPagu < 0) {
                        totalRakDisplay.parentElement.classList.add('text-danger');
                    } else {
                        totalRakDisplay.parentElement.classList.remove('text-danger');
                    }
                } else {
                    paguInfo.innerHTML = '<p class="mb-0 text-warning">Pagu untuk rekening dan tahun ini belum ditetapkan</p>';
                }
            })
            .catch(error => console.error('Error:', error));
    }

    // Cascading dropdowns (same as pagu form)
    programSelect.addEventListener('change', function() {
        const programId = this.value;
        kegiatanSelect.innerHTML = '<option value="">-- Pilih Kegiatan --</option>';
        kegiatanSelect.disabled = true;
        subKegiatanSelect.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>';
        subKegiatanSelect.disabled = true;
        rekeningSelect.innerHTML = '<option value="">-- Pilih Rekening --</option>';
        rekeningSelect.disabled = true;
        paguInfo.innerHTML = '<p class="mb-0 text-muted">Pilih rekening dan tahun untuk melihat informasi pagu</p>';
        
        if (programId) {
            kegiatanSelect.disabled = false;
            console.log('Fetching kegiatans for program:', programId);
            fetch(`${BASE_URL}pagu/get-kegiatans?program_id=${programId}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Kegiatans data:', data);
                    if (data && data.length > 0) {
                        data.forEach(kegiatan => {
                            const option = document.createElement('option');
                            option.value = kegiatan.id;
                            option.textContent = `${kegiatan.kode_kegiatan} - ${kegiatan.nama_kegiatan}`;
                            kegiatanSelect.appendChild(option);
                        });
                    } else {
                        console.warn('No kegiatans found for program:', programId);
                        kegiatanSelect.innerHTML = '<option value="">-- Tidak ada kegiatan --</option>';
                        kegiatanSelect.disabled = true;
                    }
                })
                .catch(error => {
                    console.error('Error fetching kegiatans:', error);
                    kegiatanSelect.innerHTML = '<option value="">-- Error loading data --</option>';
                    kegiatanSelect.disabled = true;
                });
        }
    });

    kegiatanSelect.addEventListener('change', function() {
        const kegiatanId = this.value;
        subKegiatanSelect.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>';
        subKegiatanSelect.disabled = true;
        rekeningSelect.innerHTML = '<option value="">-- Pilih Rekening --</option>';
        rekeningSelect.disabled = true;
        paguInfo.innerHTML = '<p class="mb-0 text-muted">Pilih rekening dan tahun untuk melihat informasi pagu</p>';
        
        if (kegiatanId) {
            subKegiatanSelect.disabled = false;
            console.log('Fetching sub kegiatans for kegiatan:', kegiatanId);
            fetch(`${BASE_URL}pagu/get-sub-kegiatans?kegiatan_id=${kegiatanId}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Sub Kegiatans data:', data);
                    if (data && data.length > 0) {
                        data.forEach(subKegiatan => {
                            const option = document.createElement('option');
                            option.value = subKegiatan.id;
                            option.textContent = `${subKegiatan.kode_sub_kegiatan} - ${subKegiatan.nama_sub_kegiatan}`;
                            subKegiatanSelect.appendChild(option);
                        });
                    } else {
                        console.warn('No sub kegiatans found');
                        subKegiatanSelect.innerHTML = '<option value="">-- Tidak ada sub kegiatan --</option>';
                        subKegiatanSelect.disabled = true;
                    }
                })
                .catch(error => {
                    console.error('Error fetching sub kegiatans:', error);
                    subKegiatanSelect.innerHTML = '<option value="">-- Error loading data --</option>';
                    subKegiatanSelect.disabled = true;
                });
        }
    });

    subKegiatanSelect.addEventListener('change', function() {
        const subKegiatanId = this.value;
        rekeningSelect.innerHTML = '<option value="">-- Pilih Rekening --</option>';
        rekeningSelect.disabled = true;
        paguInfo.innerHTML = '<p class="mb-0 text-muted">Pilih rekening dan tahun untuk melihat informasi pagu</p>';
        
        if (subKegiatanId) {
            rekeningSelect.disabled = false;
            console.log('Fetching rekenings for sub kegiatan:', subKegiatanId);
            fetch(`${BASE_URL}pagu/get-rekenings?sub_kegiatan_id=${subKegiatanId}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Rekenings data:', data);
                    if (data && data.length > 0) {
                        data.forEach(rekening => {
                            const option = document.createElement('option');
                            option.value = rekening.id;
                            option.textContent = `${rekening.kode_rekening} - ${rekening.nama_rekening}`;
                            rekeningSelect.appendChild(option);
                        });
                    } else {
                        console.warn('No rekenings found');
                        rekeningSelect.innerHTML = '<option value="">-- Tidak ada rekening --</option>';
                        rekeningSelect.disabled = true;
                    }
                })
                .catch(error => {
                    console.error('Error fetching rekenings:', error);
                    rekeningSelect.innerHTML = '<option value="">-- Error loading data --</option>';
                    rekeningSelect.disabled = true;
                });
        }
    });

    rekeningSelect.addEventListener('change', checkPaguLimit);
    tahunInput.addEventListener('change', checkPaguLimit);

    // Initial calculation
    calculateTotal();
    updateProgress();
    if (rekeningSelect.value && tahunInput.value) {
        checkPaguLimit();
    }
});
</script>

