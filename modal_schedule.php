<?php
// modal_schedule.php - Reusable Meeting Booking Modal for all users
if (!isset($pdo)) {
    require_once 'database.php';
}
$modal_branch = getCurrentBranchId();
$modal_branch_condition = $modal_branch > 0 ? "WHERE branch_id = $modal_branch" : "";
?>
<!-- Modal Buat Jadwal Meeting -->
<div id="scheduleModal" class="modal-overlay">
    <div class="modal-card" style="max-width: 650px; border-radius: 16px; overflow: hidden; background: #fff;">
        <div class="modal-header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 20px 24px; color: white; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: white;">Ajukan Jadwal Meeting Baru</h3>
                <p style="margin: 4px 0 0; font-size: 0.85rem; color: #94a3b8;">Isi detail jadwal meeting Anda dengan mudah</p>
            </div>
            <button type="button" class="modal-close" onclick="closeScheduleModal()" style="background: none; border: none; font-size: 1.5rem; color: white; cursor: pointer; opacity: 0.8;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px 24px; max-height: calc(85vh - 120px); overflow-y: auto;">
            <form id="scheduleForm">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155; margin-bottom: 6px; display: block;"><i class="fa-solid fa-copy" style="margin-right: 6px; color: #4f46e5;"></i> Pilih Template (Opsional)</label>
                    <select name="template_id" id="templateSelect" class="form-control" style="width: 100%;">
                        <option value="">-- Tidak Menggunakan Template --</option>
                        <?php
                        $stmt_templates = $pdo->query("SELECT id, name FROM meeting_templates $modal_branch_condition ORDER BY name ASC");
                        while($t = $stmt_templates->fetch()) {
                            echo "<option value=\"{$t['id']}\">".htmlspecialchars($t['name'])."</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155; margin-bottom: 6px; display: block;">Judul Meeting <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="title" id="meetingTitle" class="form-control" required placeholder="Contoh: Rapat Koordinasi Tim" style="height: 42px; border-radius: 8px;">
                </div>

                <div class="schedule-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155; margin-bottom: 6px; display: block;">Ruang Meeting <span style="color:#ef4444;">*</span></label>
                        <select name="room" id="meetingRoomSelect" class="form-control" required style="height: 42px; border-radius: 8px;">
                            <option value="">-- Pilih Ruangan --</option>
                            <?php
                            $stmt_rooms_m = $pdo->query("SELECT name FROM rooms $modal_branch_condition ORDER BY name ASC");
                            while($r = $stmt_rooms_m->fetch()) {
                                echo "<option value=\"".htmlspecialchars($r['name'])."\">".htmlspecialchars($r['name'])."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155; margin-bottom: 6px; display: block;">Toleransi Keterlambatan</label>
                        <input type="number" name="late_tolerance" class="form-control" value="15" min="0" required style="height: 42px; border-radius: 8px;" placeholder="Menit">
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label class="form-label" style="font-weight: 600; font-size: 0.8rem; color: #475569; margin-bottom: 4px; display: block;">Tanggal Pelaksanaan <span style="color:#ef4444;">*</span></label>
                        <input type="date" name="date" id="meetingDateInput" class="form-control" required style="padding: 8px 12px; height: 40px; border-radius: 8px;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-weight: 600; font-size: 0.8rem; color: #475569; margin-bottom: 4px; display: block;">Jam Mulai <span style="color:#ef4444;">*</span></label>
                            <input type="time" name="time" class="form-control" required style="padding: 8px 12px; height: 40px; border-radius: 8px;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-weight: 600; font-size: 0.8rem; color: #475569; margin-bottom: 4px; display: block;">Jam Selesai <span style="color:#ef4444;">*</span></label>
                            <input type="time" name="end_time" class="form-control" required style="padding: 8px 12px; height: 40px; border-radius: 8px;">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155; margin-bottom: 6px; display: block;"><i class="fa-solid fa-users" style="margin-right: 6px; color: #4f46e5;"></i> Peserta Diundang <span style="color:#ef4444;">*</span></label>
                    
                    <!-- Quick Group Selection Bar -->
                    <div style="display: flex; gap: 6px; margin-bottom: 8px; flex-wrap: wrap; align-items: center; background: #f8fafc; padding: 8px 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <span style="font-size: 0.8rem; font-weight: 600; color: #475569;">Tambah Cepat:</span>
                        <button type="button" onclick="addParticipantsByGroup('participantSelect', 'Manager')" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                            + Manager
                        </button>
                        <button type="button" onclick="addParticipantsByGroup('participantSelect', 'Kepala Bagian (Kabag)')" style="background: #d1fae5; color: #047857; border: 1px solid #a7f3d0; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                            + Kabag
                        </button>
                        <button type="button" onclick="addParticipantsByGroup('participantSelect', 'Staff')" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                            + Staff
                        </button>
                        <button type="button" onclick="clearParticipants('participantSelect')" style="background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer; margin-left: auto;">
                            Kosongkan
                        </button>
                    </div>

                    <select name="participants[]" id="participantSelect" class="form-control" multiple="multiple" style="width: 100%;">
                        <?php renderGroupedUserOptions($pdo, $modal_branch, false); ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155; margin-bottom: 6px; display: block;"><i class="fa-solid fa-user-tie" style="margin-right: 6px; color: #4f46e5;"></i> PIC Meeting <span style="color:#ef4444;">*</span></label>
                    <select name="pic_id" id="picSelect" class="form-control" required style="width: 100%;" disabled>
                        <option value="">Pilih Peserta Diundang Terlebih Dahulu</option>
                    </select>
                </div>

                <!-- Food, Beverages & Facilities Options -->
                <div id="consumptionPanel" style="background: #f8fafc; border: 1px solid #e5e7eb; padding: 14px 16px; border-radius: 12px; margin-bottom: 20px;">
                    <label class="form-label" style="margin-bottom: 10px; display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 0.85rem; color: #374151;"><i class="fa-solid fa-cookie-bite" style="color: #4f46e5;"></i> Konsumsi & Fasilitas</label>
                    <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500; color: #374151; cursor: pointer;">
                            <input type="checkbox" name="has_snack" value="1" style="width: 16px; height: 16px; accent-color: #4f46e5;"> Snack
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500; color: #374151; cursor: pointer;">
                            <input type="checkbox" id="hasCoffeeCheckbox" name="has_coffee" value="1" style="width: 16px; height: 16px; accent-color: #4f46e5;" onchange="toggleCoffeeOptions(this.checked)"> Coffee
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; font-weight: 500; color: #374151; cursor: pointer;">
                            <input type="checkbox" name="is_hybrid_zoom" value="1" style="width: 16px; height: 16px; accent-color: #4f46e5;"> Hybrid Zoom
                        </label>
                    </div>
                    
                    <!-- Coffee sub-options -->
                    <div id="coffeeOptionsContainer" style="display: none; border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 12px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.75rem; color: #6b7280; margin-bottom: 4px; display: block;">Suhu Kopi</label>
                                <select name="coffee_temp" id="coffeeTempSelect" style="width: 100%;">
                                    <option value="panas">Panas</option>
                                    <option value="dingin">Dingin</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.75rem; color: #6b7280; margin-bottom: 4px; display: block;">Metode Penyediaan</label>
                                <select name="coffee_type" id="coffeeTypeSelect" style="width: 100%;">
                                    <option value="bikin">Bikin Sendiri</option>
                                    <option value="beli">Beli Luar</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn" style="width: 100%; padding: 12px; font-size: 0.95rem; border-radius: 8px; font-weight: 600;">
                    <i class="fa-solid fa-calendar-check" style="margin-right: 8px;"></i> Simpan & Ajukan Jadwal Meeting
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openScheduleModal(dateStr) {
    const modal = document.getElementById('scheduleModal');
    if (modal) {
        modal.classList.add('active');
        if (dateStr) {
            const dateInput = document.getElementById('meetingDateInput');
            if (dateInput) dateInput.value = dateStr;
        }
    }
}

function closeScheduleModal() {
    const modal = document.getElementById('scheduleModal');
    if (modal) modal.classList.remove('active');
}

function toggleCoffeeOptions(show) {
    const container = document.getElementById('coffeeOptionsContainer');
    if (container) container.style.display = show ? 'block' : 'none';
}

function addParticipantsByGroup(selectId, groupName) {
    const select = document.getElementById(selectId);
    if (!select) return;
    const currentValues = $(select).val() || [];
    const newValues = [...currentValues];

    $(select).find('option').each(function() {
        if ($(this).data('group') === groupName) {
            const val = $(this).val();
            if (!newValues.includes(val)) {
                newValues.push(val);
            }
        }
    });

    $(select).val(newValues).trigger('change');
}

function clearParticipants(selectId) {
    const select = document.getElementById(selectId);
    if (select) {
        $(select).val([]).trigger('change');
    }
}

function updatePicOptions(participantSelectId, picSelectId, selectedPicId) {
    const participantSelect = document.getElementById(participantSelectId);
    const picSelect = document.getElementById(picSelectId);
    if (!participantSelect || !picSelect) return;

    const selectedParticipantIds = $(participantSelect).val() || [];
    const currentPicValue = selectedPicId !== undefined ? selectedPicId : $(picSelect).val();

    $(picSelect).empty();

    if (selectedParticipantIds.length === 0) {
        $(picSelect).append('<option value="">Pilih Peserta Diundang Terlebih Dahulu</option>');
        $(picSelect).prop('disabled', true);
    } else {
        $(picSelect).prop('disabled', false);
        $(picSelect).append('<option value="">-- Pilih PIC Meeting --</option>');

        $(participantSelect).find('option:selected').each(function() {
            const id = $(this).val();
            const text = $(this).text();
            const isSelected = (String(id) === String(currentPicValue)) ? 'selected' : '';
            $(picSelect).append(`<option value="${id}" ${isSelected}>${text}</option>`);
        });

        if (currentPicValue && !selectedParticipantIds.includes(String(currentPicValue))) {
            $(picSelect).val('').trigger('change');
        }
    }
    $(picSelect).trigger('change.select2');
}

$(document).ready(function() {
    if ($.fn.select2) {
        $('#picSelect').select2({
            placeholder: "Pilih PIC Meeting",
            allowClear: true,
            dropdownParent: $('#scheduleModal'),
            width: '100%'
        });

        $('#participantSelect').select2({
            placeholder: "Pilih Peserta Diundang",
            allowClear: true,
            dropdownParent: $('#scheduleModal'),
            width: '100%',
            closeOnSelect: false
        });

        $('#coffeeTempSelect, #coffeeTypeSelect').select2({
            dropdownParent: $('#scheduleModal'),
            minimumResultsForSearch: Infinity,
            width: '100%'
        });

        $('#templateSelect').select2({
            placeholder: "-- Pilih Template (Opsional) --",
            allowClear: true,
            dropdownParent: $('#scheduleModal'),
            width: '100%'
        });
    }

    $('#participantSelect').on('change', function() {
        updatePicOptions('participantSelect', 'picSelect');
    });

    $('#meetingRoomSelect').on('change', function() {
        const isOnline = $(this).val().toLowerCase() === 'online';
        const panel = document.getElementById('consumptionPanel');
        if (panel) panel.style.display = isOnline ? 'none' : 'block';
    });

    $('#templateSelect').on('change', function() {
        const templateId = $(this).val();
        if (!templateId) return;

        fetch('get_template.php?id=' + templateId)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const data = res.data;
                    if (data.title) $('#meetingTitle').val(data.title);
                    if (data.room) $('#meetingRoomSelect').val(data.room).trigger('change');
                    if (data.late_tolerance) $('input[name="late_tolerance"]').val(data.late_tolerance);
                    if (data.participants && data.participants.length > 0) {
                        $('#participantSelect').val(data.participants).trigger('change');
                        updatePicOptions('participantSelect', 'picSelect', data.pic_id);
                    }
                    if (data.has_snack !== undefined) $('input[name="has_snack"]').prop('checked', data.has_snack == 1);
                    if (data.has_coffee !== undefined) {
                        $('#hasCoffeeCheckbox').prop('checked', data.has_coffee == 1);
                        toggleCoffeeOptions(data.has_coffee == 1);
                    }
                    if (data.coffee_temp) $('#coffeeTempSelect').val(data.coffee_temp).trigger('change');
                    if (data.coffee_type) $('#coffeeTypeSelect').val(data.coffee_type).trigger('change');
                    if (data.is_hybrid_zoom !== undefined) $('input[name="is_hybrid_zoom"]').prop('checked', data.is_hybrid_zoom == 1);
                }
            })
            .catch(() => {});
    });

    $('#scheduleForm').on('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
        }

        fetch('save_schedule.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(r => r.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-calendar-check" style="margin-right: 8px;"></i> Simpan & Ajukan Jadwal Meeting';
            }
            if (data.success) {
                closeScheduleModal();
                $('#scheduleForm')[0].reset();
                $('#participantSelect').val(null).trigger('change');
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Jadwal Berhasil Diajukan!',
                        html: `
                            <p style="margin-bottom:15px; color: #64748b;">Meeting <strong>${data.title}</strong> berhasil disimpan.</p>
                            <div style="margin-bottom: 20px; display: flex; flex-direction: column; align-items: center;">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(data.link)}" style="border: 4px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-radius: 12px; margin-bottom: 15px; width: 200px; height: 200px;">
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:8px; word-break:break-all; font-family:monospace; font-size: 0.75rem; color: #475569; width: 100%;">
                                    ${data.link}
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonText: 'Tutup & Segarkan',
                        confirmButtonColor: '#4f46e5'
                    }).then(() => location.reload());
                } else {
                    alert('Meeting berhasil dibuat: ' + data.title);
                    location.reload();
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
                } else {
                    alert(data.message);
                }
            }
        })
        .catch(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-calendar-check" style="margin-right: 8px;"></i> Simpan & Ajukan Jadwal Meeting';
            }
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan sistem.' });
            } else {
                alert('Terjadi kesalahan sistem.');
            }
        });
    });
});
</script>
