<?php

class ProjectOS
{
    public static function ensureTables(): void
    {
        // All FK-referenced columns use plain INT to match users.id INT (signed).
        // Using INT UNSIGNED on FK columns while users.id is INT causes MySQL to reject the constraint.
        try {
            DB::query("CREATE TABLE IF NOT EXISTS projects (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                company_id  INT DEFAULT NULL,
                name        VARCHAR(200) NOT NULL,
                code        VARCHAR(50)  DEFAULT '',
                client      VARCHAR(150) DEFAULT '',
                department  VARCHAR(100) DEFAULT '',
                manager_id  INT DEFAULT NULL,
                budget      DECIMAL(12,2) DEFAULT 0.00,
                start_date  DATE DEFAULT NULL,
                end_date    DATE DEFAULT NULL,
                status      ENUM('planning','active','on_hold','delayed','completed','cancelled') DEFAULT 'planning',
                completion_pct TINYINT DEFAULT 0,
                description TEXT DEFAULT NULL,
                created_by  INT DEFAULT NULL,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (manager_id)  REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}

        try {
            DB::query("CREATE TABLE IF NOT EXISTS project_members (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                user_id    INT NOT NULL,
                role       ENUM('manager','member','client','viewer') DEFAULT 'member',
                joined_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
                UNIQUE KEY uq_proj_user (project_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}

        try {
            DB::query("CREATE TABLE IF NOT EXISTS meetings (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                project_id       INT NOT NULL,
                title            VARCHAR(255) NOT NULL,
                type             ENUM('kickoff','weekly','monthly','progress_review','client','uat','go_live','emergency') DEFAULT 'weekly',
                meeting_date     DATE,
                meeting_time     TIME,
                venue            VARCHAR(255),
                objective        TEXT,
                raw_notes        TEXT,
                ai_minutes       LONGTEXT,
                minutes_approved TINYINT(1) DEFAULT 0,
                approved_by      INT DEFAULT NULL,
                approved_at      DATETIME,
                created_by       INT DEFAULT NULL,
                created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (approved_by) REFERENCES users(id)    ON DELETE SET NULL,
                FOREIGN KEY (created_by)  REFERENCES users(id)    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}

        try {
            DB::query("CREATE TABLE IF NOT EXISTS meeting_attendees (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                meeting_id INT NOT NULL,
                name       VARCHAR(150) NOT NULL,
                attended   TINYINT(1) DEFAULT 1,
                FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}

        try {
            DB::query("CREATE TABLE IF NOT EXISTS decisions (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                project_id   INT NOT NULL,
                meeting_id   INT DEFAULT NULL,
                decision_ref VARCHAR(20),
                summary      TEXT,
                made_by      VARCHAR(150),
                decided_at   DATE,
                impact       ENUM('low','medium','high') DEFAULT 'medium',
                status       ENUM('active','replaced','cancelled') DEFAULT 'active',
                created_by   INT DEFAULT NULL,
                created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (meeting_id) REFERENCES meetings(id)  ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id)     ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}

        try {
            DB::query("CREATE TABLE IF NOT EXISTS action_items (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                project_id   INT NOT NULL,
                meeting_id   INT DEFAULT NULL,
                task_ref     VARCHAR(20),
                title        VARCHAR(255) NOT NULL,
                description  TEXT,
                owner_id     INT DEFAULT NULL,
                owner_name   VARCHAR(150),
                due_date     DATE,
                priority     ENUM('low','medium','high','critical') DEFAULT 'medium',
                status       ENUM('pending','assigned','in_progress','waiting','completed','cancelled') DEFAULT 'pending',
                completed_at DATETIME,
                created_by   INT DEFAULT NULL,
                created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (meeting_id) REFERENCES meetings(id)  ON DELETE SET NULL,
                FOREIGN KEY (owner_id)   REFERENCES users(id)     ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id)     ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}

        try {
            DB::query("CREATE TABLE IF NOT EXISTS issues (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                project_id  INT NOT NULL,
                issue_ref   VARCHAR(20),
                category    VARCHAR(100),
                severity    ENUM('low','medium','high','critical') DEFAULT 'medium',
                title       VARCHAR(255) NOT NULL,
                description TEXT,
                root_cause  TEXT,
                owner_id    INT DEFAULT NULL,
                owner_name  VARCHAR(150),
                status      ENUM('open','assigned','in_progress','solved','verified','closed','reopened') DEFAULT 'open',
                resolved_at DATETIME,
                created_by  INT DEFAULT NULL,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (owner_id)   REFERENCES users(id)    ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}

        try {
            DB::query("CREATE TABLE IF NOT EXISTS issue_comments (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                issue_id      INT NOT NULL,
                user_id       INT DEFAULT NULL,
                author_name   VARCHAR(150),
                comment       TEXT,
                status_change VARCHAR(50),
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}

        try {
            DB::query("CREATE TABLE IF NOT EXISTS project_milestones (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                project_id     INT NOT NULL,
                title          VARCHAR(255) NOT NULL,
                description    TEXT,
                due_date       DATE,
                completed_date DATE,
                status         ENUM('upcoming','active','delayed','completed') DEFAULT 'upcoming',
                created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}

        try {
            DB::query("CREATE TABLE IF NOT EXISTS project_activity_logs (
                id          BIGINT AUTO_INCREMENT PRIMARY KEY,
                project_id  INT NOT NULL,
                user_id     INT DEFAULT NULL,
                entity_type VARCHAR(50),
                entity_id   INT,
                action      VARCHAR(100),
                old_value   TEXT,
                new_value   TEXT,
                note        TEXT,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_proj (project_id),
                INDEX idx_ent  (entity_type, entity_id),
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}

        try {
            DB::query("CREATE TABLE IF NOT EXISTS knowledge_articles (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                type       ENUM('minutes','decision','sop','lesson','technical','requirement','other') DEFAULT 'other',
                title      VARCHAR(255) NOT NULL,
                content    LONGTEXT,
                tags       VARCHAR(200),
                created_by INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {}
    }

    public static function log(int $projectId, string $entityType, int $entityId, string $action, string $note = '', string $old = '', string $new = ''): void
    {
        try {
            DB::insert('project_activity_logs', [
                'project_id'  => $projectId,
                'user_id'     => Auth::id(),
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'action'      => $action,
                'old_value'   => $old,
                'new_value'   => $new,
                'note'        => $note,
            ]);
        } catch (\Throwable $e) {}
    }

    public static function getHealthScore(int $projectId): array
    {
        try {
            $total     = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ?", [$projectId])['cnt'] ?? 0);
            $completed = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ? AND status = 'completed'", [$projectId])['cnt'] ?? 0);
            $taskRate  = $total > 0 ? $completed / $total : 1.0;

            $totalIss  = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM issues WHERE project_id = ?", [$projectId])['cnt'] ?? 0);
            $closedIss = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM issues WHERE project_id = ? AND status IN ('closed','verified','solved')", [$projectId])['cnt'] ?? 0);
            $issRate   = $totalIss > 0 ? $closedIss / $totalIss : 1.0;

            $overdue   = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ? AND status NOT IN ('completed','cancelled') AND due_date < CURDATE()", [$projectId])['cnt'] ?? 0);
            $openTasks = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ? AND status NOT IN ('completed','cancelled')", [$projectId])['cnt'] ?? 0);
            $overdueR  = $openTasks > 0 ? $overdue / $openTasks : 0.0;

            $totalMs = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM project_milestones WHERE project_id = ?", [$projectId])['cnt'] ?? 0);
            $doneMs  = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM project_milestones WHERE project_id = ? AND status = 'completed'", [$projectId])['cnt'] ?? 0);
            $msRate  = $totalMs > 0 ? $doneMs / $totalMs : 1.0;

            $score = (int)round(($taskRate * 0.35 + $issRate * 0.25 + (1 - $overdueR) * 0.25 + $msRate * 0.15) * 100);

            if ($score >= 80)      { $color = '#10b981'; $label = 'Healthy'; }
            elseif ($score >= 60)  { $color = '#f59e0b'; $label = 'Caution'; }
            else                   { $color = '#ef4444'; $label = 'At Risk'; }

            return ['score' => $score, 'color' => $color, 'label' => $label,
                    'taskRate' => round($taskRate * 100), 'issueRate' => round($issRate * 100),
                    'msRate' => round($msRate * 100), 'overdue' => $overdue];
        } catch (\Throwable $e) {
            return ['score' => 0, 'color' => '#6b7280', 'label' => 'No Data',
                    'taskRate' => 0, 'issueRate' => 0, 'msRate' => 0, 'overdue' => 0];
        }
    }

    public static function nextRef(string $prefix, int $projectId, string $table): string
    {
        try {
            $next = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM `{$table}` WHERE project_id = ?", [$projectId])['cnt'] ?? 0) + 1;
        } catch (\Throwable $e) {
            $next = 1;
        }
        return strtoupper($prefix) . '-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    }

    public static function statusBadge(string $status): string
    {
        $class = match ($status) {
            'planning'    => 'bg-secondary',
            'active'      => 'bg-success',
            'on_hold'     => 'bg-warning text-dark',
            'delayed'     => 'bg-danger',
            'completed'   => 'bg-primary',
            'cancelled'   => 'bg-dark',
            'pending'     => 'bg-secondary',
            'assigned'    => 'bg-info text-dark',
            'in_progress' => 'bg-primary',
            'waiting'     => 'bg-warning text-dark',
            'open'        => 'bg-danger',
            'solved'      => 'bg-success',
            'verified'    => 'bg-primary',
            'closed'      => 'bg-secondary',
            'reopened'    => 'bg-warning text-dark',
            'upcoming'    => 'bg-info text-dark',
            'replaced'    => 'bg-secondary',
            'low'         => 'bg-secondary',
            'medium'      => 'bg-info text-dark',
            'high'        => 'bg-warning text-dark',
            'critical'    => 'bg-danger',
            default       => 'bg-secondary',
        };
        return '<span class="badge ' . $class . '" style="font-size:10px">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) . '</span>';
    }

    public static function severityColor(string $sev): string
    {
        return match ($sev) {
            'critical' => '#ef4444',
            'high'     => '#f59e0b',
            'medium'   => '#3b82f6',
            'low'      => '#6b7280',
            default    => '#6b7280',
        };
    }

    public static function getProjectContext(int $projectId): string
    {
        try {
            $project = DB::fetch("SELECT * FROM projects WHERE id = ?", [$projectId]);
            if (!$project) return "Project not found.";

            $totalTasks   = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ?", [$projectId])['cnt'] ?? 0);
            $overdueTasks = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ? AND status NOT IN ('completed','cancelled') AND due_date < CURDATE()", [$projectId])['cnt'] ?? 0);
            $openIssues   = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM issues WHERE project_id = ? AND status NOT IN ('closed','verified')", [$projectId])['cnt'] ?? 0);
            $critIssues   = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM issues WHERE project_id = ? AND severity = 'critical' AND status NOT IN ('closed','verified')", [$projectId])['cnt'] ?? 0);
            $upcomingMs   = (int)(DB::fetch("SELECT COUNT(*) AS cnt FROM project_milestones WHERE project_id = ? AND status IN ('upcoming','active') AND due_date >= CURDATE()", [$projectId])['cnt'] ?? 0);

            $lastMeetings  = DB::fetchAll("SELECT title, meeting_date FROM meetings WHERE project_id = ? ORDER BY meeting_date DESC LIMIT 3", [$projectId]);
            $openActions   = DB::fetchAll("SELECT title, owner_name, due_date FROM action_items WHERE project_id = ? AND status NOT IN ('completed','cancelled') ORDER BY due_date ASC LIMIT 5", [$projectId]);
            $openIssueList = DB::fetchAll("SELECT title, severity, status FROM issues WHERE project_id = ? AND status NOT IN ('closed','verified') ORDER BY FIELD(severity,'critical','high','medium','low') LIMIT 5", [$projectId]);
            $milestones    = DB::fetchAll("SELECT title, due_date, status FROM project_milestones WHERE project_id = ? AND status IN ('upcoming','active') ORDER BY due_date ASC LIMIT 5", [$projectId]);

            $lines = [
                "=== PROJECT CONTEXT ===",
                "Name: {$project['name']} [{$project['code']}]",
                "Status: {$project['status']} | Completion: {$project['completion_pct']}%",
                "Client: " . ($project['client'] ?? 'N/A') . " | Department: " . ($project['department'] ?? 'N/A'),
                "Budget: " . ($project['budget'] !== null ? number_format((float)$project['budget'], 2) : 'N/A'),
                "Start: " . ($project['start_date'] ?? 'N/A') . " | End: " . ($project['end_date'] ?? 'N/A'),
                "",
                "=== SNAPSHOT ===",
                "Total Tasks: {$totalTasks} | Overdue: {$overdueTasks}",
                "Open Issues: {$openIssues} | Critical: {$critIssues}",
                "Upcoming Milestones: {$upcomingMs}",
            ];

            if ($lastMeetings) {
                $lines[] = ""; $lines[] = "=== LAST 3 MEETINGS ===";
                foreach ($lastMeetings as $m) $lines[] = "- {$m['title']} ({$m['meeting_date']})";
            }
            if ($openActions) {
                $lines[] = ""; $lines[] = "=== OPEN ACTION ITEMS (up to 5) ===";
                foreach ($openActions as $a) $lines[] = "- {$a['title']} | Owner: " . ($a['owner_name'] ?? 'Unassigned') . " | Due: " . ($a['due_date'] ?? 'N/A');
            }
            if ($openIssueList) {
                $lines[] = ""; $lines[] = "=== OPEN ISSUES (up to 5) ===";
                foreach ($openIssueList as $i) $lines[] = "- [{$i['severity']}] {$i['title']} (Status: {$i['status']})";
            }
            if ($milestones) {
                $lines[] = ""; $lines[] = "=== UPCOMING MILESTONES ===";
                foreach ($milestones as $ms) $lines[] = "- {$ms['title']} | Due: {$ms['due_date']} | Status: {$ms['status']}";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Unable to load project context.";
        }
    }
}
