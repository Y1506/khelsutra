<?php

namespace App\Services\Tournament;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;

class TournamentService extends BaseService
{
    protected AuditLogService $auditLog;

    public function __construct(?PDO $pdo = null, ?AuditLogService $auditLog = null)
    {
        parent::__construct($pdo);
        $this->auditLog = $auditLog ?? new AuditLogService($this->pdo);
    }

    public function listTournaments(int $organizationId, int $page = 1, int $limit = 15, ?string $search = null, ?string $status = null, ?int $sportId = null): array
    {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 0];
        }

        $conditions = ["t.organization_id = :org_id", "t.deleted_at IS NULL"];
        $params = [':org_id' => $organizationId];

        if (!empty($search)) {
            $conditions[] = "(t.name LIKE :search OR t.tournament_reference LIKE :search OR t.organizer_name LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        if (!empty($status)) {
            $conditions[] = "t.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($sportId)) {
            $conditions[] = "t.sport_id = :sport_id";
            $params[':sport_id'] = $sportId;
        }

        $whereClause = implode(' AND ', $conditions);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tournaments t WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT 
                t.*,
                s.name as sport_name,
                tl.name as level_name,
                tf.name as format_name,
                COUNT(DISTINCT tt.team_id) as enrolled_teams_count,
                COUNT(DISTINCT f.id) as fixtures_count
            FROM tournaments t
            LEFT JOIN sports s ON t.sport_id = s.id
            LEFT JOIN tournament_levels tl ON t.tournament_level_id = tl.id
            LEFT JOIN tournament_formats tf ON t.tournament_format_id = tf.id
            LEFT JOIN tournament_teams tt ON t.id = tt.tournament_id
            LEFT JOIN fixtures f ON t.id = f.tournament_id AND f.deleted_at IS NULL
            WHERE {$whereClause}
            GROUP BY t.id, s.name, tl.name, tf.name
            ORDER BY t.start_date DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $tournaments,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / max(1, $limit)),
        ];
    }

    public function getTournament(int $organizationId, int $id): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT 
                t.*,
                s.name as sport_name,
                tl.name as level_name,
                tf.name as format_name
            FROM tournaments t
            LEFT JOIN sports s ON t.sport_id = s.id
            LEFT JOIN tournament_levels tl ON t.tournament_level_id = tl.id
            LEFT JOIN tournament_formats tf ON t.tournament_format_id = tf.id
            WHERE t.id = :id AND t.organization_id = :org_id AND t.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tournament) return null;

        // Participating Teams
        $ttStmt = $this->pdo->prepare("
            SELECT 
                tt.*,
                tm.name as team_name,
                tm.team_code,
                tm.age_group
            FROM tournament_teams tt
            JOIN teams tm ON tt.team_id = tm.id
            WHERE tt.tournament_id = :tour_id
            ORDER BY tt.seed_number ASC, tm.name ASC
        ");
        $ttStmt->execute([':tour_id' => $id]);
        $tournament['participating_teams'] = $ttStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Tournament Venues
        $tvStmt = $this->pdo->prepare("
            SELECT 
                tv.*,
                v.name as venue_name,
                v.city,
                v.venue_code,
                v.status as venue_status
            FROM tournament_venues tv
            JOIN venues v ON tv.venue_id = v.id AND v.deleted_at IS NULL
            WHERE tv.tournament_id = :tour_id
            ORDER BY tv.is_primary DESC, v.name ASC
        ");
        $tvStmt->execute([':tour_id' => $id]);
        $tournament['venues'] = $tvStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fixtures and Matches
        $fixStmt = $this->pdo->prepare("
            SELECT 
                f.*,
                t1.name as home_team_name,
                t2.name as away_team_name,
                v.name as venue_name,
                vf.name as facility_name,
                m.id as match_id,
                m.match_reference,
                m.home_score,
                m.away_score,
                m.winner_team_id,
                m.result_type,
                m.status as match_status
            FROM fixtures f
            LEFT JOIN teams t1 ON f.home_team_id = t1.id
            LEFT JOIN teams t2 ON f.away_team_id = t2.id
            LEFT JOIN venues v ON f.venue_id = v.id
            LEFT JOIN venue_facilities vf ON f.facility_id = vf.id
            LEFT JOIN matches m ON f.id = m.fixture_id AND m.deleted_at IS NULL
            WHERE f.tournament_id = :tour_id AND f.organization_id = :org_id AND f.deleted_at IS NULL
            ORDER BY f.scheduled_date ASC, f.scheduled_start_time ASC
        ");
        $fixStmt->execute([':tour_id' => $id, ':org_id' => $organizationId]);
        $tournament['fixtures'] = $fixStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Standings
        $stdStmt = $this->pdo->prepare("
            SELECT 
                ts.*,
                tm.name as team_name,
                tm.team_code
            FROM tournament_standings ts
            JOIN teams tm ON ts.team_id = tm.id
            WHERE ts.tournament_id = :tour_id
            ORDER BY ts.points DESC, ts.difference DESC, ts.scored DESC
        ");
        $stdStmt->execute([':tour_id' => $id]);
        $tournament['standings'] = $stdStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $tournament;
    }

    public function createTournament(int $organizationId, array $data, ?int $performedBy = null): array
    {
        if (!$this->pdo) return [];

        $this->pdo->beginTransaction();
        try {
            $ref = $data['tournament_reference'] ?? ('TOURN-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4)));

            $sql = "
                INSERT INTO tournaments (
                    organization_id, tournament_reference, name, sport_id,
                    tournament_level_id, tournament_format_id, start_date, end_date,
                    location_name, city, state, organizer_name, description, rules,
                    status, created_by, created_at, updated_at
                ) VALUES (
                    :org_id, :ref, :name, :sport_id,
                    :level_id, :format_id, :sdate, :edate,
                    :loc, :city, :state, :organizer, :desc, :rules,
                    :status, :created_by, NOW(), NOW()
                )
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':org_id' => $organizationId,
                ':ref' => $ref,
                ':name' => trim($data['name'] ?? ''),
                ':sport_id' => (int)($data['sport_id'] ?? 1),
                ':level_id' => !empty($data['tournament_level_id']) ? (int)$data['tournament_level_id'] : 1,
                ':format_id' => !empty($data['tournament_format_id']) ? (int)$data['tournament_format_id'] : 1,
                ':sdate' => $data['start_date'] ?? date('Y-m-d'),
                ':edate' => $data['end_date'] ?? date('Y-m-d', strtotime('+3 days')),
                ':loc' => $data['location_name'] ?? 'Main Stadium Complex',
                ':city' => $data['city'] ?? 'Mumbai',
                ':state' => $data['state'] ?? 'Maharashtra',
                ':organizer' => $data['organizer_name'] ?? 'Apex Sports Academy',
                ':desc' => $data['description'] ?? null,
                ':rules' => $data['rules'] ?? null,
                ':status' => $data['status'] ?? 'draft',
                ':created_by' => $performedBy,
            ]);

            $tournamentId = (int)$this->pdo->lastInsertId();

            // 1. Assign Primary Venue
            if (!empty($data['venue_id'])) {
                $vSql = "INSERT INTO tournament_venues (tournament_id, venue_id, is_primary, created_at) VALUES (:t_id, :v_id, 1, NOW())";
                $vStmt = $this->pdo->prepare($vSql);
                $vStmt->execute([':t_id' => $tournamentId, ':v_id' => (int)$data['venue_id']]);
            }

            // 2. Assign Participating Teams & Initialize Standings
            if (!empty($data['team_ids']) && is_array($data['team_ids'])) {
                $ttSql = "INSERT INTO tournament_teams (tournament_id, team_id, status, registered_at) VALUES (:t_id, :tm_id, 'approved', NOW())";
                $ttStmt = $this->pdo->prepare($ttSql);

                $stdSql = "INSERT INTO tournament_standings (tournament_id, team_id, played, won, drawn, lost, points, scored, conceded, difference, rank_position, updated_at) VALUES (:t_id, :tm_id, 0, 0, 0, 0, 0, 0, 0, 0, 1, NOW())";
                $stdStmt = $this->pdo->prepare($stdSql);

                foreach ($data['team_ids'] as $teamId) {
                    if (!empty($teamId)) {
                        $ttStmt->execute([':t_id' => $tournamentId, ':tm_id' => (int)$teamId]);
                        $stdStmt->execute([':t_id' => $tournamentId, ':tm_id' => (int)$teamId]);
                    }
                }
            }

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'TOURNAMENT_CREATE',
                'Tournaments',
                'tournaments',
                $tournamentId,
                null,
                ['reference' => $ref, 'name' => $data['name'] ?? ''],
                "Created tournament {$data['name']} ({$ref})"
            );

            return [
                'id' => $tournamentId,
                'tournament_reference' => $ref,
                'name' => $data['name'] ?? ''
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function updateTournament(int $organizationId, int $id, array $data, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getTournament($organizationId, $id);
        if (!$existing) return false;

        $sql = "
            UPDATE tournaments SET
                name = :name,
                sport_id = :sport_id,
                tournament_level_id = :level_id,
                tournament_format_id = :format_id,
                start_date = :sdate,
                end_date = :edate,
                location_name = :loc,
                city = :city,
                state = :state,
                organizer_name = :organizer,
                description = :desc,
                rules = :rules,
                status = :status,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':name' => trim($data['name'] ?? $existing['name']),
            ':sport_id' => (int)($data['sport_id'] ?? $existing['sport_id']),
            ':level_id' => !empty($data['tournament_level_id']) ? (int)$data['tournament_level_id'] : $existing['tournament_level_id'],
            ':format_id' => !empty($data['tournament_format_id']) ? (int)$data['tournament_format_id'] : $existing['tournament_format_id'],
            ':sdate' => $data['start_date'] ?? $existing['start_date'],
            ':edate' => $data['end_date'] ?? $existing['end_date'],
            ':loc' => $data['location_name'] ?? $existing['location_name'],
            ':city' => $data['city'] ?? $existing['city'],
            ':state' => $data['state'] ?? $existing['state'],
            ':organizer' => $data['organizer_name'] ?? $existing['organizer_name'],
            ':desc' => $data['description'] ?? $existing['description'],
            ':rules' => $data['rules'] ?? $existing['rules'],
            ':status' => $data['status'] ?? $existing['status'],
            ':id' => $id,
            ':org_id' => $organizationId,
        ]);

        if ($ok) {
            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'TOURNAMENT_UPDATE',
                'Tournaments',
                'tournaments',
                $id,
                $existing,
                $data,
                "Updated tournament #{$id} ({$existing['name']})"
            );
        }

        return $ok;
    }

    public function createFixture(int $organizationId, int $tournamentId, array $data, ?int $performedBy = null): array
    {
        if (!$this->pdo) return [];

        // 1. Verify tournament exists, belongs to tenant, and lifecycle allows scheduling
        $t = $this->getTournament($organizationId, $tournamentId);
        if (!$t) {
            throw new \InvalidArgumentException("Tournament not found or access denied.");
        }
        if (in_array($t['status'] ?? '', ['completed', 'cancelled'], true)) {
            throw new \InvalidArgumentException("Cannot schedule fixtures for a completed or cancelled tournament.");
        }

        // 2. Validate teams
        $homeId = (int)($data['home_team_id'] ?? 0);
        $awayId = (int)($data['away_team_id'] ?? 0);
        if ($homeId <= 0 || $awayId <= 0) {
            throw new \InvalidArgumentException("Please select both home and away teams.");
        }
        if ($homeId === $awayId) {
            throw new \InvalidArgumentException("Home and away teams must be distinct.");
        }

        // 3. Verify duplicate pairings
        $formatName = $t['format_name'] ?? '';
        $roundName = trim($data['round_name'] ?? 'Round 1');
        if (in_array($formatName, ['League', 'Round Robin'], true)) {
            // Single round-robin: teams play each other once across the entire tournament
            $dupStmt = $this->pdo->prepare("
                SELECT id, fixture_reference FROM fixtures
                WHERE tournament_id = :t_id
                  AND deleted_at IS NULL
                  AND (
                    (home_team_id = :h_id AND away_team_id = :a_id) OR
                    (home_team_id = :a_id AND away_team_id = :h_id)
                  )
                LIMIT 1
            ");
            $dupStmt->execute([':t_id' => $tournamentId, ':h_id' => $homeId, ':a_id' => $awayId]);
            $dup = $dupStmt->fetch(PDO::FETCH_ASSOC);
            if ($dup) {
                throw new \InvalidArgumentException("A fixture between these teams already exists in this tournament ({$dup['fixture_reference']}).");
            }
        } else {
            // For other formats (e.g. Knockout): prevent duplicate pairing in the same round
            $dupStmt = $this->pdo->prepare("
                SELECT id, fixture_reference FROM fixtures
                WHERE tournament_id = :t_id
                  AND round_name = :round
                  AND deleted_at IS NULL
                  AND (
                    (home_team_id = :h_id AND away_team_id = :a_id) OR
                    (home_team_id = :a_id AND away_team_id = :h_id)
                  )
                LIMIT 1
            ");
            $dupStmt->execute([':t_id' => $tournamentId, ':round' => $roundName, ':h_id' => $homeId, ':a_id' => $awayId]);
            $dup = $dupStmt->fetch(PDO::FETCH_ASSOC);
            if ($dup) {
                throw new \InvalidArgumentException("A fixture between these teams already exists in {$roundName} ({$dup['fixture_reference']}).");
            }
        }

        // 4. Server-side venue conflict detection
        $venueId = !empty($data['venue_id']) ? (int)$data['venue_id'] : null;
        if ($venueId) {
            $venueService = new \App\Services\Venue\VenueService($this->pdo, $this->auditLog);
            $sDate = $data['scheduled_date'] ?? date('Y-m-d');
            $sTime = $data['scheduled_start_time'] ?? '15:00:00';
            $eTime = !empty($data['scheduled_end_time']) ? $data['scheduled_end_time'] : null;
            $facId = !empty($data['facility_id']) ? (int)$data['facility_id'] : null;

            $conflict = $venueService->checkSchedulingConflict(
                $organizationId,
                $venueId,
                $sDate,
                $sTime,
                $eTime,
                $facId
            );
            if ($conflict) {
                throw new \InvalidArgumentException($conflict);
            }
        }

        $this->pdo->beginTransaction();
        try {
            $fixRef = $data['fixture_reference'] ?? ('FIX-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4)));
            $matchRef = 'MCH-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4));

            $fSql = "
                INSERT INTO fixtures (
                    organization_id, tournament_id, fixture_reference, round_name,
                    group_name, home_team_id, away_team_id, venue_id, facility_id,
                    scheduled_date, scheduled_start_time, scheduled_end_time, status,
                    notes, created_at, updated_at
                ) VALUES (
                    :org_id, :tour_id, :fix_ref, :round,
                    :grp, :home_id, :away_id, :venue_id, :fac_id,
                    :sdate, :stime, :etime, 'scheduled',
                    :notes, NOW(), NOW()
                )
            ";
            $fStmt = $this->pdo->prepare($fSql);
            $fStmt->execute([
                ':org_id' => $organizationId,
                ':tour_id' => $tournamentId,
                ':fix_ref' => $fixRef,
                ':round' => $roundName,
                ':grp' => $data['group_name'] ?? null,
                ':home_id' => $homeId,
                ':away_id' => $awayId,
                ':venue_id' => $venueId,
                ':fac_id' => !empty($data['facility_id']) ? (int)$data['facility_id'] : null,
                ':sdate' => $data['scheduled_date'] ?? date('Y-m-d'),
                ':stime' => $data['scheduled_start_time'] ?? '15:00:00',
                ':etime' => !empty($data['scheduled_end_time']) ? $data['scheduled_end_time'] : null,
                ':notes' => $data['notes'] ?? null,
            ]);

            $fixtureId = (int)$this->pdo->lastInsertId();

            // Create corresponding match record
            $mSql = "
                INSERT INTO matches (
                    organization_id, fixture_id, match_reference, status, created_at, updated_at
                ) VALUES (
                    :org_id, :fix_id, :m_ref, 'scheduled', NOW(), NOW()
                )
            ";
            $mStmt = $this->pdo->prepare($mSql);
            $mStmt->execute([
                ':org_id' => $organizationId,
                ':fix_id' => $fixtureId,
                ':m_ref' => $matchRef
            ]);

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'FIXTURE_CREATE',
                'Fixtures',
                'fixtures',
                $fixtureId,
                null,
                ['fixture_reference' => $fixRef, 'tournament_id' => $tournamentId],
                "Created fixture {$fixRef} in tournament #{$tournamentId}"
            );

            return [
                'id' => $fixtureId,
                'fixture_reference' => $fixRef
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function updateMatchResult(int $organizationId, int $fixtureId, array $data, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        $fStmt = $this->pdo->prepare("SELECT * FROM fixtures WHERE id = :fix_id AND organization_id = :org_id LIMIT 1");
        $fStmt->execute([':fix_id' => $fixtureId, ':org_id' => $organizationId]);
        $fixture = $fStmt->fetch(PDO::FETCH_ASSOC);
        if (!$fixture) return false;

        $this->pdo->beginTransaction();
        try {
            $homeScore = (float)($data['home_score'] ?? 0);
            $awayScore = (float)($data['away_score'] ?? 0);

            $winnerTeamId = null;
            if ($homeScore > $awayScore) {
                $winnerTeamId = (int)$fixture['home_team_id'];
                $resultType = 'home_win';
            } elseif ($awayScore > $homeScore) {
                $winnerTeamId = (int)$fixture['away_team_id'];
                $resultType = 'away_win';
            } else {
                $resultType = 'draw';
            }

            // Update match record
            $mSql = "
                UPDATE matches SET
                    home_score = :h_score,
                    away_score = :a_score,
                    winner_team_id = :winner,
                    result_type = :res_type,
                    status = 'completed',
                    updated_at = NOW()
                WHERE fixture_id = :fix_id AND organization_id = :org_id
            ";
            $mStmt = $this->pdo->prepare($mSql);
            $mStmt->execute([
                ':h_score' => $homeScore,
                ':a_score' => $awayScore,
                ':winner' => $winnerTeamId,
                ':res_type' => $resultType,
                ':fix_id' => $fixtureId,
                ':org_id' => $organizationId
            ]);

            // Mark fixture completed
            $upFix = $this->pdo->prepare("UPDATE fixtures SET status = 'completed', updated_at = NOW() WHERE id = :fix_id");
            $upFix->execute([':fix_id' => $fixtureId]);

            // Update standings if tournament has standings
            $tournamentId = (int)$fixture['tournament_id'];
            $this->recalculateStandings($tournamentId);

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'MATCH_RESULT',
                'Matches',
                'matches',
                $fixtureId,
                null,
                ['fixture_id' => $fixtureId, 'home_score' => $homeScore, 'away_score' => $awayScore],
                "Recorded match result for fixture #{$fixtureId}: {$homeScore} - {$awayScore}"
            );

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    protected function recalculateStandings(int $tournamentId): void
    {
        // Simple standings update from completed matches
        $q = "
            SELECT 
                f.home_team_id, f.away_team_id,
                m.home_score, m.away_score, m.winner_team_id, m.result_type
            FROM fixtures f
            JOIN matches m ON f.id = m.fixture_id
            WHERE f.tournament_id = :t_id AND m.status = 'completed' AND f.deleted_at IS NULL
        ";
        $stmt = $this->pdo->prepare($q);
        $stmt->execute([':t_id' => $tournamentId]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $table = [];
        foreach ($matches as $m) {
            $h = (int)$m['home_team_id'];
            $a = (int)$m['away_team_id'];

            if (!isset($table[$h])) $table[$h] = ['p' => 0, 'w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0, 'pts' => 0];
            if (!isset($table[$a])) $table[$a] = ['p' => 0, 'w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0, 'pts' => 0];

            $table[$h]['p']++;
            $table[$a]['p']++;
            $table[$h]['gf'] += (float)$m['home_score'];
            $table[$h]['ga'] += (float)$m['away_score'];
            $table[$a]['gf'] += (float)$m['away_score'];
            $table[$a]['ga'] += (float)$m['home_score'];

            if ($m['result_type'] === 'home_win') {
                $table[$h]['w']++;
                $table[$h]['pts'] += 3;
                $table[$a]['l']++;
            } elseif ($m['result_type'] === 'away_win') {
                $table[$a]['w']++;
                $table[$a]['pts'] += 3;
                $table[$h]['l']++;
            } else {
                $table[$h]['d']++;
                $table[$h]['pts'] += 1;
                $table[$a]['d']++;
                $table[$a]['pts'] += 1;
            }
        }

        $upStmt = $this->pdo->prepare("
            INSERT INTO tournament_standings (tournament_id, team_id, played, won, drawn, lost, points, scored, conceded, difference, updated_at)
            VALUES (:t_id, :tm_id, :p, :w, :d, :l, :pts, :gf, :ga, :diff, NOW())
            ON DUPLICATE KEY UPDATE
                played = VALUES(played),
                won = VALUES(won),
                drawn = VALUES(drawn),
                lost = VALUES(lost),
                points = VALUES(points),
                scored = VALUES(scored),
                conceded = VALUES(conceded),
                difference = VALUES(difference),
                updated_at = NOW()
        ");

        foreach ($table as $teamId => $stats) {
            $diff = $stats['gf'] - $stats['ga'];
            $upStmt->execute([
                ':t_id' => $tournamentId,
                ':tm_id' => $teamId,
                ':p' => $stats['p'],
                ':w' => $stats['w'],
                ':d' => $stats['d'],
                ':l' => $stats['l'],
                ':pts' => $stats['pts'],
                ':gf' => $stats['gf'],
                ':ga' => $stats['ga'],
                ':diff' => $diff
            ]);
        }
    }

    public function deleteTournament(int $organizationId, int $id, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getTournament($organizationId, $id);
        if (!$existing) return false;

        $stmt = $this->pdo->prepare("UPDATE tournaments SET deleted_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);

        // Cascade soft deletion to fixtures and matches
        $this->pdo->prepare("
            UPDATE matches m
            JOIN fixtures f ON m.fixture_id = f.id
            SET m.deleted_at = NOW()
            WHERE f.tournament_id = :id AND m.deleted_at IS NULL
        ")->execute([':id' => $id]);

        $this->pdo->prepare("
            UPDATE fixtures
            SET deleted_at = NOW()
            WHERE tournament_id = :id AND deleted_at IS NULL
        ")->execute([':id' => $id]);

        $this->auditLog->log(
            $organizationId,
            $performedBy,
            'TOURNAMENT_DELETE',
            'Tournaments',
            'tournaments',
            $id,
            $existing,
            null,
            "Soft deleted tournament #{$id}"
        );

        return true;
    }

    public function addTeam(int $organizationId, int $tournamentId, int $teamId, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        // 1. Verify tournament exists, belongs to organization, and is not soft-deleted
        $t = $this->getTournament($organizationId, $tournamentId);
        if (!$t) {
            throw new \InvalidArgumentException("Tournament not found or access denied.");
        }
        if (in_array($t['status'] ?? '', ['completed', 'cancelled'], true)) {
            throw new \InvalidArgumentException("Cannot register teams for a completed or cancelled tournament.");
        }

        // 2. Verify team exists, belongs to organization, is active, and is not deleted
        $tmStmt = $this->pdo->prepare("
            SELECT id, name, team_code, status, organization_id, deleted_at 
            FROM teams 
            WHERE id = :tm_id AND organization_id = :org_id AND deleted_at IS NULL 
            LIMIT 1
        ");
        $tmStmt->execute([':tm_id' => $teamId, ':org_id' => $organizationId]);
        $team = $tmStmt->fetch(PDO::FETCH_ASSOC);
        if (!$team) {
            throw new \InvalidArgumentException("Team not found or does not belong to your organization.");
        }
        if ($team['status'] !== 'active') {
            throw new \InvalidArgumentException("Only active teams can be registered into a tournament.");
        }

        // 3. Check existing tournament participation & status
        $checkStmt = $this->pdo->prepare("
            SELECT id, status 
            FROM tournament_teams 
            WHERE tournament_id = :t_id AND team_id = :tm_id
            LIMIT 1
        ");
        $checkStmt->execute([':t_id' => $tournamentId, ':tm_id' => $teamId]);
        $existingReg = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingReg) {
            $curStatus = $existingReg['status'];
            // Terminal outcomes must NEVER be overwritten
            if (in_array($curStatus, ['eliminated', 'qualified', 'winner', 'runner_up'], true)) {
                throw new \InvalidArgumentException("Cannot re-register team with terminal tournament status '{$curStatus}'.");
            }
            // If already approved/registered, safely idempotent
            if (in_array($curStatus, ['approved', 'registered'], true)) {
                return true;
            }
            // If withdrawn, reactivate to approved
            if ($curStatus === 'withdrawn') {
                $upStmt = $this->pdo->prepare("
                    UPDATE tournament_teams 
                    SET status = 'approved', registered_at = NOW() 
                    WHERE id = :id
                ");
                $upStmt->execute([':id' => (int)$existingReg['id']]);

                $this->auditLog->log(
                    $organizationId,
                    $performedBy,
                    'TOURNAMENT_TEAM_ADD',
                    'Tournaments',
                    'tournament_teams',
                    $tournamentId,
                    ['status' => 'withdrawn'],
                    ['status' => 'approved', 'tournament_id' => $tournamentId, 'team_id' => $teamId],
                    "Re-registered withdrawn team #{$teamId} ({$team['name']}) into tournament #{$tournamentId} ({$t['name']})"
                );

                return true;
            }
        }

        // 4. Fresh registration: insert into tournament_teams and tournament_standings
        $this->pdo->beginTransaction();
        try {
            $ttStmt = $this->pdo->prepare("
                INSERT INTO tournament_teams (tournament_id, team_id, status, registered_at) 
                VALUES (:t_id, :tm_id, 'approved', NOW())
            ");
            $ttStmt->execute([':t_id' => $tournamentId, ':tm_id' => $teamId]);

            $stdStmt = $this->pdo->prepare("
                INSERT INTO tournament_standings (tournament_id, team_id, played, won, drawn, lost, points, scored, conceded, difference, rank_position, updated_at)
                VALUES (:t_id, :tm_id, 0, 0, 0, 0, 0, 0, 0, 0, 1, NOW())
                ON DUPLICATE KEY UPDATE updated_at = NOW()
            ");
            $stdStmt->execute([':t_id' => $tournamentId, ':tm_id' => $teamId]);

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'TOURNAMENT_TEAM_ADD',
                'Tournaments',
                'tournament_teams',
                $tournamentId,
                null,
                ['tournament_id' => $tournamentId, 'team_id' => $teamId, 'status' => 'approved'],
                "Registered team #{$teamId} ({$team['name']}) into tournament #{$tournamentId} ({$t['name']})"
            );

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function removeTeam(int $organizationId, int $tournamentId, int $teamId, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        // 1. Verify tournament exists and belongs to current organization
        $t = $this->getTournament($organizationId, $tournamentId);
        if (!$t) {
            throw new \InvalidArgumentException("Tournament not found or access denied.");
        }

        // 2. Verify team exists and belongs to current organization
        $tmStmt = $this->pdo->prepare("
            SELECT id, name, team_code, organization_id 
            FROM teams 
            WHERE id = :tm_id AND organization_id = :org_id 
            LIMIT 1
        ");
        $tmStmt->execute([':tm_id' => $teamId, ':org_id' => $organizationId]);
        $team = $tmStmt->fetch(PDO::FETCH_ASSOC);
        if (!$team) {
            throw new \InvalidArgumentException("Team not found or does not belong to your organization.");
        }

        // 3. Verify team is currently registered with active status in this tournament
        $checkStmt = $this->pdo->prepare("
            SELECT id, status 
            FROM tournament_teams 
            WHERE tournament_id = :t_id AND team_id = :tm_id
            LIMIT 1
        ");
        $checkStmt->execute([':t_id' => $tournamentId, ':tm_id' => $teamId]);
        $membership = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$membership || $membership['status'] === 'withdrawn') {
            throw new \InvalidArgumentException("Team is not an active participant in this tournament.");
        }
        if (in_array($membership['status'], ['eliminated', 'winner', 'runner_up'], true)) {
            throw new \InvalidArgumentException("Cannot withdraw team with tournament status '{$membership['status']}'.");
        }

        // 4. Fixture protection strictly scoped to THIS tournament
        $fixStmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM fixtures 
            WHERE tournament_id = :t_id 
              AND (home_team_id = :tm_id OR away_team_id = :tm_id) 
              AND deleted_at IS NULL
        ");
        $fixStmt->execute([':t_id' => $tournamentId, ':tm_id' => $teamId]);
        if ((int)$fixStmt->fetchColumn() > 0) {
            throw new \InvalidArgumentException("Cannot withdraw team: this team is already scheduled in fixtures for this tournament.");
        }

        // 5. Update tournament_teams status to 'withdrawn' (preserves historical record)
        $this->pdo->beginTransaction();
        try {
            $upStmt = $this->pdo->prepare("
                UPDATE tournament_teams 
                SET status = 'withdrawn' 
                WHERE id = :id
            ");
            $upStmt->execute([':id' => (int)$membership['id']]);

            // Note: Per user correction #2, tournament_standings records are NOT deleted or cleared.

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'TOURNAMENT_TEAM_REMOVE',
                'Tournaments',
                'tournament_teams',
                $tournamentId,
                ['tournament_id' => $tournamentId, 'team_id' => $teamId, 'status' => $membership['status']],
                ['tournament_id' => $tournamentId, 'team_id' => $teamId, 'status' => 'withdrawn'],
                "Withdrew team #{$teamId} ({$team['name']}) from tournament #{$tournamentId} ({$t['name']})"
            );

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function addVenue(int $organizationId, int $tournamentId, int $venueId, bool $isPrimary = false, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        // 1. Verify tournament exists, belongs to organization, and is not soft-deleted
        $t = $this->getTournament($organizationId, $tournamentId);
        if (!$t) {
            throw new \InvalidArgumentException("Tournament not found or access denied.");
        }
        if (in_array($t['status'] ?? '', ['completed', 'cancelled'], true)) {
            throw new \InvalidArgumentException("Cannot assign venues to a completed or cancelled tournament.");
        }

        // 2. Verify venue exists, belongs to organization, is active, and is not soft-deleted
        $vStmt = $this->pdo->prepare("
            SELECT id, name, venue_code, status, organization_id, deleted_at
            FROM venues
            WHERE id = :v_id AND organization_id = :org_id AND deleted_at IS NULL
            LIMIT 1
        ");
        $vStmt->execute([':v_id' => $venueId, ':org_id' => $organizationId]);
        $venue = $vStmt->fetch(PDO::FETCH_ASSOC);
        if (!$venue) {
            throw new \InvalidArgumentException("Venue not found or does not belong to your organization.");
        }
        if ($venue['status'] !== 'active') {
            throw new \InvalidArgumentException("Only active venues can be assigned to a tournament.");
        }

        // 3. Check existing assignment (idempotent duplicate prevention)
        $checkStmt = $this->pdo->prepare("
            SELECT id, is_primary
            FROM tournament_venues
            WHERE tournament_id = :t_id AND venue_id = :v_id
            LIMIT 1
        ");
        $checkStmt->execute([':t_id' => $tournamentId, ':v_id' => $venueId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ($isPrimary && empty($existing['is_primary'])) {
                $upStmt = $this->pdo->prepare("UPDATE tournament_venues SET is_primary = 1 WHERE id = :id");
                $upStmt->execute([':id' => (int)$existing['id']]);
            }
            return true;
        }

        // 4. Assign venue to tournament
        $this->pdo->beginTransaction();
        try {
            if ($isPrimary) {
                $clrStmt = $this->pdo->prepare("UPDATE tournament_venues SET is_primary = 0 WHERE tournament_id = :t_id");
                $clrStmt->execute([':t_id' => $tournamentId]);
            }

            $insStmt = $this->pdo->prepare("
                INSERT INTO tournament_venues (tournament_id, venue_id, is_primary, created_at)
                VALUES (:t_id, :v_id, :is_primary, NOW())
            ");
            $insStmt->execute([
                ':t_id' => $tournamentId,
                ':v_id' => $venueId,
                ':is_primary' => $isPrimary ? 1 : 0
            ]);

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'TOURNAMENT_VENUE_ADD',
                'Tournaments',
                'tournament_venues',
                $tournamentId,
                null,
                ['tournament_id' => $tournamentId, 'venue_id' => $venueId, 'is_primary' => $isPrimary ? 1 : 0],
                "Assigned venue #{$venueId} ({$venue['name']}) to tournament #{$tournamentId} ({$t['name']})"
            );

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function removeVenue(int $organizationId, int $tournamentId, int $venueId, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        // 1. Verify tournament exists and belongs to current organization
        $t = $this->getTournament($organizationId, $tournamentId);
        if (!$t) {
            throw new \InvalidArgumentException("Tournament not found or access denied.");
        }

        // 2. Verify venue belongs to current organization
        $vStmt = $this->pdo->prepare("
            SELECT id, name, venue_code, organization_id
            FROM venues
            WHERE id = :v_id AND organization_id = :org_id
            LIMIT 1
        ");
        $vStmt->execute([':v_id' => $venueId, ':org_id' => $organizationId]);
        $venue = $vStmt->fetch(PDO::FETCH_ASSOC);
        if (!$venue) {
            throw new \InvalidArgumentException("Venue not found or does not belong to your organization.");
        }

        // 3. Verify venue is assigned to this tournament
        $checkStmt = $this->pdo->prepare("
            SELECT id, is_primary
            FROM tournament_venues
            WHERE tournament_id = :t_id AND venue_id = :v_id
            LIMIT 1
        ");
        $checkStmt->execute([':t_id' => $tournamentId, ':v_id' => $venueId]);
        $assignment = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$assignment) {
            throw new \InvalidArgumentException("Venue is not currently assigned to this tournament.");
        }

        // 4. Fixture protection strictly scoped to THIS tournament
        $fixStmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM fixtures
            WHERE tournament_id = :t_id
              AND venue_id = :v_id
              AND deleted_at IS NULL
        ");
        $fixStmt->execute([':t_id' => $tournamentId, ':v_id' => $venueId]);
        if ((int)$fixStmt->fetchColumn() > 0) {
            throw new \InvalidArgumentException("Cannot remove venue: fixtures in this tournament are scheduled at this venue.");
        }

        // 5. Remove tournament_venues row
        $this->pdo->beginTransaction();
        try {
            $delStmt = $this->pdo->prepare("
                DELETE FROM tournament_venues
                WHERE tournament_id = :t_id AND venue_id = :v_id
            ");
            $delStmt->execute([':t_id' => $tournamentId, ':v_id' => $venueId]);

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'TOURNAMENT_VENUE_REMOVE',
                'Tournaments',
                'tournament_venues',
                $tournamentId,
                ['tournament_id' => $tournamentId, 'venue_id' => $venueId],
                null,
                "Removed venue #{$venueId} ({$venue['name']}) from tournament #{$tournamentId} ({$t['name']})"
            );

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function getEligibleVenues(int $organizationId, int $tournamentId): array
    {
        if (!$this->pdo) return [];

        $stmt = $this->pdo->prepare("
            SELECT v.id, v.name, v.venue_code, v.city, v.venue_type
            FROM venues v
            WHERE v.organization_id = :org_id
              AND v.status = 'active'
              AND v.deleted_at IS NULL
              AND v.id NOT IN (
                  SELECT tv.venue_id
                  FROM tournament_venues tv
                  WHERE tv.tournament_id = :t_id
              )
            ORDER BY v.name ASC
        ");
        $stmt->execute([
            ':org_id' => $organizationId,
            ':t_id' => $tournamentId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Generate fixtures for a tournament based on its format and enrolled teams.
     * Supports single round-robin (League / Round Robin) and first-round Knockout.
     * Transaction-safe, deterministic, tenant-isolated, and fully audit-logged.
     */
    public function generateFixtures(int $organizationId, int $tournamentId, ?int $performedBy = null): array
    {
        if (!$this->pdo) return ['count' => 0, 'fixtures' => []];

        // 1. Verify tournament exists and belongs to current tenant
        $t = $this->getTournament($organizationId, $tournamentId);
        if (!$t) {
            throw new \InvalidArgumentException("Tournament not found or access denied.");
        }

        // 2. Lifecycle check: cannot generate for completed or cancelled tournaments
        if (in_array($t['status'] ?? '', ['completed', 'cancelled'], true)) {
            throw new \InvalidArgumentException("Cannot generate fixtures for a completed or cancelled tournament.");
        }

        // 3. Supported format check
        $formatName = $t['format_name'] ?? '';
        if ($formatName === 'Group + Knockout') {
            throw new \InvalidArgumentException("Fixture generation is not supported for Group + Knockout format.");
        }
        if (!in_array($formatName, ['League', 'Round Robin', 'Knockout'], true)) {
            throw new \InvalidArgumentException("Fixture generation is not supported for format: " . ($formatName ?: 'Unknown') . ".");
        }

        // 4. Duplicate generation check: reject if fixtures already exist
        $fixCheck = $this->pdo->prepare("SELECT COUNT(*) FROM fixtures WHERE tournament_id = :t_id AND deleted_at IS NULL");
        $fixCheck->execute([':t_id' => $tournamentId]);
        if ((int)$fixCheck->fetchColumn() > 0) {
            throw new \InvalidArgumentException("Fixtures already exist for this tournament.");
        }

        // 5. Retrieve active, non-withdrawn, non-deleted participating teams
        $ttStmt = $this->pdo->prepare("
            SELECT tt.team_id, t.name as team_name
            FROM tournament_teams tt
            JOIN teams t ON tt.team_id = t.id
            WHERE tt.tournament_id = :t_id
              AND tt.status != 'withdrawn'
              AND t.organization_id = :org_id
              AND t.status = 'active'
              AND t.deleted_at IS NULL
            ORDER BY tt.id ASC
        ");
        $ttStmt->execute([':t_id' => $tournamentId, ':org_id' => $organizationId]);
        $teams = $ttStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $teamCount = count($teams);

        if ($teamCount < 2) {
            throw new \InvalidArgumentException("At least 2 participating teams are required to generate fixtures.");
        }

        // 6. Generate match pairings by format
        $rounds = [];
        if (in_array($formatName, ['League', 'Round Robin'], true)) {
            $rounds = $this->generateRoundRobinSchedule(array_column($teams, 'team_id'));
        } elseif ($formatName === 'Knockout') {
            // Odd-team knockout without established seeding is safely rejected
            if ($teamCount % 2 !== 0) {
                throw new \InvalidArgumentException("Knockout fixture generation requires an even number of participating teams.");
            }

            $roundLabel = ($teamCount === 2) ? 'Final' : (($teamCount === 4) ? 'Semi Final' : (($teamCount === 8) ? 'Quarter Final' : 'Round 1'));
            $knockoutMatches = [];
            for ($i = 0; $i < $teamCount; $i += 2) {
                $knockoutMatches[] = [
                    'home' => (int)$teams[$i]['team_id'],
                    'away' => (int)$teams[$i + 1]['team_id']
                ];
            }
            $rounds[$roundLabel] = $knockoutMatches;
        }

        // 7. Atomic batch creation of fixtures and matches
        $scheduledDate = $t['start_date'] ?: date('Y-m-d');
        $this->pdo->beginTransaction();
        $generatedFixtures = [];

        try {
            $seq = 1;
            foreach ($rounds as $roundName => $matches) {
                foreach ($matches as $m) {
                    $fixRef = 'FIX-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4)) . '-' . sprintf('%03d', $seq);
                    $matchRef = 'MCH-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4)) . '-' . sprintf('%03d', $seq);

                    $fSql = "
                        INSERT INTO fixtures (
                            organization_id, tournament_id, fixture_reference, round_name,
                            group_name, home_team_id, away_team_id, venue_id, facility_id,
                            scheduled_date, scheduled_start_time, scheduled_end_time, status,
                            notes, created_at, updated_at
                        ) VALUES (
                            :org_id, :t_id, :fix_ref, :round,
                            NULL, :home_id, :away_id, NULL, NULL,
                            :sdate, '15:00:00', NULL, 'scheduled',
                            'Auto-generated fixture', NOW(), NOW()
                        )
                    ";
                    $fStmt = $this->pdo->prepare($fSql);
                    $fStmt->execute([
                        ':org_id' => $organizationId,
                        ':t_id' => $tournamentId,
                        ':fix_ref' => $fixRef,
                        ':round' => (string)$roundName,
                        ':home_id' => (int)$m['home'],
                        ':away_id' => (int)$m['away'],
                        ':sdate' => $scheduledDate
                    ]);

                    $fixtureId = (int)$this->pdo->lastInsertId();

                    $mSql = "
                        INSERT INTO matches (
                            organization_id, fixture_id, match_reference, status, created_at, updated_at
                        ) VALUES (
                            :org_id, :fix_id, :m_ref, 'scheduled', NOW(), NOW()
                        )
                    ";
                    $mStmt = $this->pdo->prepare($mSql);
                    $mStmt->execute([
                        ':org_id' => $organizationId,
                        ':fix_id' => $fixtureId,
                        ':m_ref' => $matchRef
                    ]);

                    $generatedFixtures[] = [
                        'id' => $fixtureId,
                        'fixture_reference' => $fixRef,
                        'round_name' => (string)$roundName,
                        'home_team_id' => (int)$m['home'],
                        'away_team_id' => (int)$m['away']
                    ];
                    $seq++;
                }
            }

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'FIXTURE_GENERATE',
                'Tournaments',
                'fixtures',
                $tournamentId,
                null,
                [
                    'tournament_id' => $tournamentId,
                    'format' => $formatName,
                    'generated_count' => count($generatedFixtures),
                    'team_count' => $teamCount
                ],
                "Generated " . count($generatedFixtures) . " fixtures for tournament #{$tournamentId} ({$t['name']}) [{$formatName}]"
            );

            return [
                'count' => count($generatedFixtures),
                'fixtures' => $generatedFixtures
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Standard round-robin scheduling algorithm (Berger rotation).
     * Generates N(N-1)/2 distinct pairings across N-1 (even) or N (odd) rounds.
     * Odd team counts use an internal algorithmic bye without database dummy records.
     */
    protected function generateRoundRobinSchedule(array $teamIds): array
    {
        $n = count($teamIds);
        if ($n < 2) return [];

        $teams = array_values($teamIds);
        if ($n % 2 !== 0) {
            $teams[] = null; // internal algorithmic bye
            $n++;
        }

        $numRounds = $n - 1;
        $half = $n / 2;
        $rounds = [];

        for ($r = 0; $r < $numRounds; $r++) {
            $roundMatches = [];
            for ($i = 0; $i < $half; $i++) {
                $t1 = $teams[$i];
                $t2 = $teams[$n - 1 - $i];

                if ($t1 !== null && $t2 !== null) {
                    // Alternate home and away across rounds
                    if (($r + $i) % 2 === 0) {
                        $roundMatches[] = ['home' => (int)$t1, 'away' => (int)$t2];
                    } else {
                        $roundMatches[] = ['home' => (int)$t2, 'away' => (int)$t1];
                    }
                }
            }
            $roundNum = $r + 1;
            $rounds["Round {$roundNum}"] = $roundMatches;

            // Rotate array keeping position 0 fixed
            $last = array_pop($teams);
            array_splice($teams, 1, 0, [$last]);
        }

        return $rounds;
    }
}
