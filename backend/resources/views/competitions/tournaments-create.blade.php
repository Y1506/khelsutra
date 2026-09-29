<?php
$pageTitle = 'Create Tournament — KhelSutra';
$activePage = 'tournaments';
$orgId = current_organization_id();

$db = \App\Services\BaseService::getDatabaseConnection();

// Sports
$sportsStmt = $db->query("SELECT id, name FROM sports ORDER BY name ASC");
$sports = $sportsStmt ? $sportsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Levels
$lvlStmt = $db->query("SELECT id, name FROM tournament_levels ORDER BY id ASC");
$levels = $lvlStmt ? $lvlStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Formats
$fmtStmt = $db->query("SELECT id, name FROM tournament_formats ORDER BY id ASC");
$formats = $fmtStmt ? $fmtStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Venues
$venueStmt = $db->prepare("SELECT v.id, v.name, GROUP_CONCAT(vs.sport_id) as sport_ids FROM venues v LEFT JOIN venue_sports vs ON v.id = vs.venue_id WHERE v.organization_id = :org_id AND v.status = 'active' AND v.deleted_at IS NULL GROUP BY v.id, v.name ORDER BY v.name ASC");
$venueStmt->execute([':org_id' => $orgId]);
$venues = $venueStmt ? $venueStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Teams
$teamStmt = $db->prepare("SELECT id, name, team_code FROM teams WHERE organization_id = :org_id AND status = 'active' AND deleted_at IS NULL ORDER BY name ASC");
$teamStmt->execute([':org_id' => $orgId]);
$teams = $teamStmt ? $teamStmt->fetchAll(PDO::FETCH_ASSOC) : [];

ob_start();
?>

<div class="ks-content">
    <!-- Top Back Navigation -->
    <div class="mb-3">
        <a href="/tournaments" class="text-decoration-none text-muted small fw-medium">
            <i class="bi bi-arrow-left me-1"></i> Back to Tournaments
        </a>
    </div>

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 fw-bold mb-0" style="color: var(--ks-navy);">Create Tournament</h1>
        </div>
    </div>

    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger mb-4 py-2 px-3 small d-flex align-items-center gap-2" style="border-radius: var(--ks-radius-button);">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endif; ?>

    <form action="/tournaments/create" method="POST" id="createTournamentForm">
        <!-- Section 1: Tournament Profile -->
        <div class="card p-4 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                1. Tournament Information
            </h5>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Tournament Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Maharashtra Inter-Academy Football Championship 2026" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Sport <span class="text-danger">*</span></label>
                    <select name="sport_id" id="sportSelect" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="">Select sport</option>
                        <?php foreach ($sports as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Competition Level <span class="text-danger">*</span></label>
                    <select name="tournament_level_id" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <?php foreach ($levels as $lvl): ?>
                            <option value="<?= (int)$lvl['id'] ?>"><?= htmlspecialchars($lvl['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Tournament Format <span class="text-danger">*</span></label>
                    <select name="tournament_format_id" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <?php foreach ($formats as $fmt): ?>
                            <option value="<?= (int)$fmt['id'] ?>"><?= htmlspecialchars($fmt['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-dark">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="draft">Draft</option>
                        <option value="registration_open">Registration Open</option>
                        <option value="ongoing">Ongoing</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold text-dark">Organizer / Sponsoring Body</label>
                    <input type="text" name="organizer_name" class="form-control" value="Apex Sports Academy" style="font-size: 13px; border-radius: var(--ks-radius-button);">
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
                    <label class="form-label small fw-semibold text-dark">Tournament Start Date <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Tournament End Date <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-dark">Primary Venue <span class="text-danger">*</span></label>
                    <select name="venue_id" id="venueSelect" class="form-select" required style="font-size: 13px; border-radius: var(--ks-radius-button);">
                        <option value="">Select venue</option>
                        <?php foreach ($venues as $v): ?>
                            <option value="<?= (int)$v['id'] ?>" data-sports="<?= htmlspecialchars($v['sport_ids'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-dark">City</label>
                    <input type="text" name="city" class="form-control" value="Mumbai" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-dark">State</label>
                    <input type="text" name="state" class="form-control" value="Maharashtra" style="font-size: 13px; border-radius: var(--ks-radius-button);">
                </div>
            </div>
        </div>

        <!-- Section 3: Participating Teams -->
        <div class="card p-4 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                3. Participating Squads (Initial Enrolment)
            </h5>
            <p class="text-muted small mb-3">Select teams to enroll into this tournament. Standings will be initialized automatically.</p>

            <div class="row g-2" style="max-height: 200px; overflow-y: auto;">
                <?php foreach ($teams as $tm): ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="form-check p-2 border rounded" style="background: var(--ks-page-bg);">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="team_ids[]" value="<?= (int)$tm['id'] ?>" id="tm_<?= (int)$tm['id'] ?>">
                            <label class="form-check-label small fw-medium text-dark" for="tm_<?= (int)$tm['id'] ?>">
                                <?= htmlspecialchars($tm['name'], ENT_QUOTES, 'UTF-8') ?>
                                <span class="text-muted" style="font-size: 11px;">(<?= htmlspecialchars($tm['team_code'], ENT_QUOTES, 'UTF-8') ?>)</span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Section 4: Rules & Guidelines -->
        <div class="card p-4 mb-4" style="border: 1px solid var(--ks-border); border-radius: var(--ks-radius-card); background: #fff;">
            <h5 class="fw-bold mb-3" style="color: var(--ks-navy); font-size: 15px; border-bottom: 1px solid var(--ks-border-light); padding-bottom: 10px;">
                4. Description & Competition Rules
            </h5>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small fw-semibold text-dark">Tournament Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Overview of the tournament, eligibility criteria, and prize pool" style="font-size: 13px; border-radius: var(--ks-radius-button);"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold text-dark">Rules & Regulations</label>
                    <textarea name="rules" class="form-control" rows="2" placeholder="Match duration, points system, tie-breaker criteria" style="font-size: 13px; border-radius: var(--ks-radius-button);"></textarea>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="d-flex align-items-center justify-content-end gap-3 mb-5">
            <a href="/tournaments" class="btn btn-outline-secondary" style="border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 10px 24px;">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary" style="background: var(--ks-blue); border-color: var(--ks-blue); border-radius: var(--ks-radius-button); font-weight: 600; font-size: 13px; padding: 10px 24px;">
                <i class="bi bi-check2 me-1"></i> Create Tournament
            </button>
        </div>
    </form>
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
