<?php

class ProjectOS
{
    public static function ensureTables(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS projects (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) NOT NULL,
            client VARCHAR(255),
            department VARCHAR(255),
            manager_id INT UNSIGNED,
            budget DECIMAL(12,2),
            start_date DATE,
            end_date DATE,
            status ENUM('planning','active','on_hold','delayed','completed','cancelled') NOT NULL DEFAULT 'planning',
            completion_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
            description TEXT,
            created_by INT UNSIGNED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_proj_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_proj_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS project_members (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            role ENUM('manager','member','client','viewer') NOT NULL DEFAULT 'member',
            joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_pm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uq_proj_user (project_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS meetings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            type ENUM('kickoff','weekly','monthly','progress_review','client','uat','go_live','emergency') NOT NULL DEFAULT 'weekly',
            meeting_date DATE,
            meeting_time TIME,
            venue VARCHAR(255),
            objective TEXT,
            raw_notes TEXT,
            ai_minutes LONGTEXT,
            minutes_approved TINYINT(1) NOT NULL DEFAULT 0,
            approved_by INT UNSIGNED,
            approved_at DATETIME,
            created_by INT UNSIGNED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_meet_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_meet_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_meet_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS meeting_attendees (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            attended TINYINT(1) NOT NULL DEFAULT 1,
            CONSTRAINT fk_ma_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS decisions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            meeting_id INT UNSIGNED,
            decision_ref VARCHAR(20),
            summary TEXT,
            made_by VARCHAR(150),
            decided_at DATE,
            impact ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
            status ENUM('active','replaced','cancelled') NOT NULL DEFAULT 'active',
            created_by INT UNSIGNED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_dec_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_dec_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE SET NULL,
            CONSTRAINT fk_dec_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS action_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            meeting_id INT UNSIGNED,
            task_ref VARCHAR(20),
            title VARCHAR(255) NOT NULL,
            description TEXT,
            owner_id INT UNSIGNED,
            owner_name VARCHAR(150),
            due_date DATE,
            priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
            status ENUM('pending','assigned','in_progress','waiting','completed','cancelled') NOT NULL DEFAULT 'pending',
            completed_at DATETIME,
            created_by INT UNSIGNED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_ai_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_ai_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE SET NULL,
            CONSTRAINT fk_ai_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_ai_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS issues (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            issue_ref VARCHAR(20),
            category VARCHAR(100),
            severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
            title VARCHAR(255) NOT NULL,
            description TEXT,
            root_cause TEXT,
            owner_id INT UNSIGNED,
            owner_name VARCHAR(150),
            status ENUM('open','assigned','in_progress','solved','verified','closed','reopened') NOT NULL DEFAULT 'open',
            resolved_at DATETIME,
            created_by INT UNSIGNED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_iss_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_iss_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_iss_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS issue_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            issue_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED,
            author_name VARCHAR(150),
            comment TEXT,
            status_change VARCHAR(50),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ic_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
            CONSTRAINT fk_ic_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS project_milestones (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            due_date DATE,
            completed_date DATE,
            status ENUM('upcoming','active','delayed','completed') NOT NULL DEFAULT 'upcoming',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ms_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS project_activity_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED,
            entity_type VARCHAR(50),
            entity_id INT,
            action VARCHAR(100),
            old_value TEXT,
            new_value TEXT,
            note TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_pal_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_pal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_proj (project_id),
            INDEX idx_ent (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS knowledge_articles (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id INT UNSIGNED NOT NULL,
            type ENUM('minutes','decision','sop','lesson','technical','requirement','other') NOT NULL DEFAULT 'other',
            title VARCHAR(255) NOT NULL,
            content LONGTEXT,
            tags VARCHAR(200),
            created_by INT UNSIGNED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_ka_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_ka_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
        } catch (\Throwable $e) {
        }
    }

    public static function getHealthScore(int $projectId): array
    {
        try {
            $total = DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ?", [$projectId])['cnt'] ?? 0;
            $completed = DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ? AND status = 'completed'", [$projectId])['cnt'] ?? 0;
            $taskCompletionRate = $total > 0 ? $completed / $total : 1.0;

            $totalIssues = DB::fetch("SELECT COUNT(*) AS cnt FROM issues WHERE project_id = ?", [$projectId])['cnt'] ?? 0;
            $closedIssues = DB::fetch("SELECT COUNT(*) AS cnt FROM issues WHERE project_id = ? AND status IN ('closed','verified','solved')", [$projectId])['cnt'] ?? 0;
            $issueClosureRate = $totalIssues > 0 ? $closedIssues / $totalIssues : 1.0;

            $overdue = DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ? AND status NOT IN ('completed','cancelled') AND due_date < CURDATE()", [$projectId])['cnt'] ?? 0;
            $openTasks = DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ? AND status NOT IN ('completed','cancelled')", [$projectId])['cnt'] ?? 0;
            $overdueRatio = $openTasks > 0 ? $overdue / $openTasks : 0.0;

            $totalMs = DB::fetch("SELECT COUNT(*) AS cnt FROM project_milestones WHERE project_id = ?", [$projectId])['cnt'] ?? 0;
            $completedMs = DB::fetch("SELECT COUNT(*) AS cnt FROM project_milestones WHERE project_id = ? AND status = 'completed'", [$projectId])['cnt'] ?? 0;
            $milestoneRate = $totalMs > 0 ? $completedMs / $totalMs : 1.0;

            $score = (int) round(
                ($taskCompletionRate * 0.35 + $issueClosureRate * 0.25 + (1 - $overdueRatio) * 0.25 + $milestoneRate * 0.15) * 100
            );

            if ($score >= 80) {
                $color = '#10b981';
                $label = 'Healthy';
            } elseif ($score >= 60) {
                $color = '#f59e0b';
                $label = 'Caution';
            } else {
                $color = '#ef4444';
                $label = 'At Risk';
            }

            return [
                'score'      => $score,
                'color'      => $color,
                'label'      => $label,
                'taskRate'   => round($taskCompletionRate * 100),
                'issueRate'  => round($issueClosureRate * 100),
                'msRate'     => round($milestoneRate * 100),
                'overdue'    => $overdue,
            ];
        } catch (\Throwable $e) {
            return ['score' => 0, 'color' => '#6b7280', 'label' => 'No Data', 'taskRate' => 0, 'issueRate' => 0, 'msRate' => 0, 'overdue' => 0];
        }
    }

    public static function nextRef(string $prefix, int $projectId, string $table): string
    {
        try {
            $row = DB::fetch("SELECT COUNT(*) AS cnt FROM `{$table}` WHERE project_id = ?", [$projectId]);
            $next = (int)($row['cnt'] ?? 0) + 1;
        } catch (\Throwable $e) {
            $next = 1;
        }
        return strtoupper($prefix) . '-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
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
            if (!$project) {
                return "Project not found.";
            }

            $totalTasks   = DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ?", [$projectId])['cnt'] ?? 0;
            $overdueTasks = DB::fetch("SELECT COUNT(*) AS cnt FROM action_items WHERE project_id = ? AND status NOT IN ('completed','cancelled') AND due_date < CURDATE()", [$projectId])['cnt'] ?? 0;
            $openIssues   = DB::fetch("SELECT COUNT(*) AS cnt FROM issues WHERE project_id = ? AND status NOT IN ('closed','verified')", [$projectId])['cnt'] ?? 0;
            $critIssues   = DB::fetch("SELECT COUNT(*) AS cnt FROM issues WHERE project_id = ? AND severity = 'critical' AND status NOT IN ('closed','verified')", [$projectId])['cnt'] ?? 0;
            $upcomingMs   = DB::fetch("SELECT COUNT(*) AS cnt FROM project_milestones WHERE project_id = ? AND status IN ('upcoming','active') AND due_date >= CURDATE()", [$projectId])['cnt'] ?? 0;

            $lastMeetings = DB::fetchAll("SELECT title, meeting_date FROM meetings WHERE project_id = ? ORDER BY meeting_date DESC LIMIT 3", [$projectId]);
            $openActions  = DB::fetchAll("SELECT title, owner_name, due_date FROM action_items WHERE project_id = ? AND status NOT IN ('completed','cancelled') ORDER BY due_date ASC LIMIT 5", [$projectId]);
            $openIssueList = DB::fetchAll("SELECT title, severity, status FROM issues WHERE project_id = ? AND status NOT IN ('closed','verified') ORDER BY FIELD(severity,'critical','high','medium','low') LIMIT 5", [$projectId]);
            $milestones   = DB::fetchAll("SELECT title, due_date, status FROM project_milestones WHERE project_id = ? AND status IN ('upcoming','active') ORDER BY due_date ASC LIMIT 5", [$projectId]);

            $lines = [];
            $lines[] = "=== PROJECT CONTEXT ===";
            $lines[] = "Name: {$project['name']} [{$project['code']}]";
            $lines[] = "Status: {$project['status']} | Completion: {$project['completion_pct']}%";
            $lines[] = "Client: " . ($project['client'] ?? 'N/A') . " | Department: " . ($project['department'] ?? 'N/A');
            $lines[] = "Budget: " . ($project['budget'] !== null ? number_format((float)$project['budget'], 2) : 'N/A');
            $lines[] = "Start: " . ($project['start_date'] ?? 'N/A') . " | End: " . ($project['end_date'] ?? 'N/A');
            $lines[] = "";
            $lines[] = "=== SNAPSHOT ===";
            $lines[] = "Total Tasks: {$totalTasks} | Overdue: {$overdueTasks}";
            $lines[] = "Open Issues: {$openIssues} | Critical: {$critIssues}";
            $lines[] = "Upcoming Milestones: {$upcomingMs}";

            if ($lastMeetings) {
                $lines[] = "";
                $lines[] = "=== LAST 3 MEETINGS ===";
                foreach ($lastMeetings as $m) {
                    $lines[] = "- {$m['title']} ({$m['meeting_date']})";
                }
            }

            if ($openActions) {
                $lines[] = "";
                $lines[] = "=== OPEN ACTION ITEMS (up to 5) ===";
                foreach ($openActions as $a) {
                    $lines[] = "- {$a['title']} | Owner: " . ($a['owner_name'] ?? 'Unassigned') . " | Due: " . ($a['due_date'] ?? 'N/A');
                }
            }

            if ($openIssueList) {
                $lines[] = "";
                $lines[] = "=== OPEN ISSUES (up to 5) ===";
                foreach ($openIssueList as $i) {
                    $lines[] = "- [{$i['severity']}] {$i['title']} (Status: {$i['status']})";
                }
            }

            if ($milestones) {
                $lines[] = "";
                $lines[] = "=== UPCOMING MILESTONES ===";
                foreach ($milestones as $ms) {
                    $lines[] = "- {$ms['title']} | Due: {$ms['due_date']} | Status: {$ms['status']}";
                }
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Unable to load project context.";
        }
    }
}
