<?php
$tournId = (int)($id ?? ($_GET['id'] ?? 0));
$orgId = current_organization_id();
$tournService = new \App\Services\Tournament\TournamentService();
$tournament = $tournService->getTournament($orgId, $tournId);

$db = \App\Services\BaseService::getDatabaseConnection();
$sportsStmt = $db->query("SELECT id, name FROM sports ORDER BY name ASC");
$sports = $sportsStmt ? $sportsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$lvlStmt = $db->query("SELECT id, name FROM tournament_levels ORDER BY id ASC");
$levels = $lvlStmt ? $lvlStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$fmtStmt = $db->query("SELECT id, name FROM tournament_formats ORDER BY id ASC");
$formats = $fmtStmt ? $fmtStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$pageTitle = $tournament ? 'Edit Tournament — ' . htmlspecialchars($tournament['name']) : 'Edit Tournament';
$activePage = 'tournaments';

ob_start();
?>

<div class="ks-content">
    <?php if (!$tournament): ?>
        <div class="card p-5 text-center" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <div class="mb-3"><i class="bi bi-trophy-fill fs-1 text-muted"></i></div>
            <h4 class="fw-bold" style="color: var(--ks-navy);">Tournament Not Found</h4>
            <p class="text-muted small">The requested tournament championship does not exist or does not belong to your organisation.</p>
            <div class="mt-3">
                <a href="/tournaments" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-weight: 500;">
                    <i class="bi bi-arrow-left me-1"></i> Back to Tournaments
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Back Navigation -->
        <div class="mb-3">
            <a href="/tournaments/<?= (int)$tournament['id'] ?>" class="text-decoration-none text-muted small fw-medium">
                <i class="bi bi-arrow-left me-1"></i> Back to Tournament Details
            </a>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h4 fw-bold mb-0" style="color: var(--ks-navy);">Edit Tournament: <?= htmlspecialchars($tournament['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
                <div class="text-muted small mt-1">Ref: <strong><?= htmlspecialchars($tournament['tournament_reference'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></div>
            </div>
        </div>

        <form action="/tournaments/<?= (int)$tournament['id'] ?>/edit" method="POST" id="editTournamentForm">
            <!-- Section 1: Tournament Profile -->
            <div class="card p-4 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                    1. Tournament Information
                </h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Tournament Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($tournament['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Sport <span class="text-danger">*</span></label>
                        <select name="sport_id" id="sportSelect" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                            <?php foreach ($sports as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= $tournament['sport_id'] == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Competition Level <span class="text-danger">*</span></label>
                        <select name="tournament_level_id" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                            <?php foreach ($levels as $lvl): ?>
                                <option value="<?= (int)$lvl['id'] ?>" <?= $tournament['tournament_level_id'] == $lvl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lvl['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Tournament Format <span class="text-danger">*</span></label>
                        <select name="tournament_format_id" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                            <?php foreach ($formats as $fmt): ?>
                                <option value="<?= (int)$fmt['id'] ?>" <?= $tournament['tournament_format_id'] == $fmt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($fmt['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                            <option value="draft" <?= ($tournament['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="registration_open" <?= ($tournament['status'] ?? '') === 'registration_open' ? 'selected' : '' ?>>Registration Open</option>
                            <option value="registration_closed" <?= ($tournament['status'] ?? '') === 'registration_closed' ? 'selected' : '' ?>>Registration Closed</option>
                            <option value="ongoing" <?= ($tournament['status'] ?? '') === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                            <option value="completed" <?= ($tournament['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= ($tournament['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-dark">Organizer Name</label>
                        <input type="text" name="organizer_name" class="form-control" value="<?= htmlspecialchars($tournament['organizer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                </div>
            </div>

            <!-- Section 2: Dates & Location -->
            <div class="card p-4 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                    2. Dates & Location
                </h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($tournament['start_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">End Date <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($tournament['end_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark">Location Name</label>
                        <input type="text" name="location_name" class="form-control" value="<?= htmlspecialchars($tournament['location_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-dark">City</label>
                        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($tournament['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-dark">State</label>
                        <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($tournament['state'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                    </div>
                </div>
            </div>

            <!-- Section 3: Rules & Guidelines -->
            <div class="card p-4 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
                <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                    3. Description & Competition Rules
                </h5>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-dark">Tournament Description</label>
                        <textarea name="description" class="form-control" rows="2" style="font-size: 13px; border-radius: var(--ks-radius-button);"><?= htmlspecialchars($tournament['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-dark">Rules & Regulations</label>
                        <textarea name="rules" class="form-control" rows="2" style="font-size: 13px; border-radius: var(--ks-radius-button);"><?= htmlspecialchars($tournament['rules'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="d-flex align-items-center justify-content-end gap-3 mb-5">
                <a href="/tournaments/<?= (int)$tournament['id'] ?>" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 10px 24px;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 10px 24px;">
                    <i class="bi bi-check2 me-1"></i> Save Changes
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const sportSelect = document.getElementById('sportSelect');
    const venueSelect = document.getElementById('venueSelect');
    
    if (sportSelect && venueSelect) {
        // Clone all original options
        const allOptions = Array.from(venueSelect.options).map(opt => opt.cloneNode(true));
        
        sportSelect.addEventListener('change', function() {
            const selectedSport = this.value;
            const currentSelectedVenue = venueSelect.value;
            
            // Clear current options
            venueSelect.innerHTML = '';
            
            // Filter options
            allOptions.forEach(opt => {
                if (opt.value === '') {
                    venueSelect.appendChild(opt.cloneNode(true)); // Add placeholder
                } else {
                    const sportsStr = opt.getAttribute('data-sports') || '';
                    const sportsArr = sportsStr.split(',');
                    
                    // If no sport selected, or venue has no sports (assume general purpose), or venue has the sport
                    if (!selectedSport || sportsStr === '' || sportsArr.includes(selectedSport)) {
                        venueSelect.appendChild(opt.cloneNode(true));
                    }
                }
            });
            
            // Try to restore previous selection if it's still available
            let match = Array.from(venueSelect.options).find(opt => opt.value === currentSelectedVenue);
            if (match) {
                venueSelect.value = currentSelectedVenue;
            } else {
                venueSelect.value = '';
            }
        });
        
        // Trigger initial filter
        const initialVenue = venueSelect.value;
        sportSelect.dispatchEvent(new Event('change'));
        if(initialVenue) venueSelect.value = initialVenue;
    }
});
</script>

<?php
$slot = ob_get_clean();
include __DIR__ . '/../layouts/app.blade.php';
?>
