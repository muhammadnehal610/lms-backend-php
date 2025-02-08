<?php
require_once('vendor/autoload.php'); // Ensure TCPDF is installed
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';



class Reports
{
    public $db;
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function summary()
    {
        $createdUserQuery = 'SELECT COUNT(*) as created_users FROM person WHERE created_at >= NOW() - INTERVAL 30 DAY';
        $createdUserStmt = $this->db->prepare($createdUserQuery);
        $createdUserStmt->execute();
        $createdUser = $createdUserStmt->fetchAll(PDO::FETCH_ASSOC);

        $courseCompleteQuery = 'SELECT COUNT(*) as courses_complete FROM course WHERE status = "completed" AND completed_at >= NOW() - INTERVAL 30 DAY';
        $courseCompleteStmt = $this->db->prepare($courseCompleteQuery);
        $courseCompleteStmt->execute();
        $courseComplete = $courseCompleteStmt->fetchAll(PDO::FETCH_ASSOC);

        $neverLoginQuery = 'SELECT COUNT(*) as never_login FROM person WHERE last_login is NULL';
        $neverLoginStmt = $this->db->prepare($neverLoginQuery);
        $neverLoginStmt->execute();
        $neverLogin = $neverLoginStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'created_users' => $createdUser[0]['created_users'],
            'courses_complete' => $courseComplete[0]['courses_complete'],
            'never_login' => $neverLogin[0]['never_login'],
        ];
    }

    public function getActiveUser($intervalValue, $intervalType)
    {
        try {
            // Validate the interval type
            $allowedIntervalTypes = ['DAY', 'MONTH'];
            if (!in_array(strtoupper($intervalType), $allowedIntervalTypes)) {
                throw new Exception("Invalid interval type. Allowed values are 'DAY' or 'MONTH'.");
            }

            // Build the query to fetch all the data for the required period
            $query = "
                SELECT 
                    DATE(last_login) AS login_date, 
                    COUNT(*) AS login_count
                FROM 
                    person
                WHERE 
                    last_login >= NOW() - INTERVAL :intervalValue $intervalType
                GROUP BY 
                    login_date
                ORDER BY 
                    login_date ASC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':intervalValue', $intervalValue, PDO::PARAM_INT);
            $stmt->execute();

            // Fetch the result
            $loginData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate dynamic chunk size based on total data count
            $totalDataCount = count($loginData);
            $chunksCount = 6; // Always divide the data into 6 chunks (or adjust based on your needs)
            $chunkSize = ceil($totalDataCount / $chunksCount);

            // Divide data into dynamic chunks and calculate the total login count for each chunk
            $intervals = [];
            $chunks = array_chunk($loginData, $chunkSize);

            foreach ($chunks as $index => $chunk) {
                // Calculate the total login count for this interval
                $totalLoginCount = array_sum(array_column($chunk, 'login_count'));

                // Calculate the date range for this interval
                $startDate = $chunk[0]['login_date'];
                $endDate = end($chunk)['login_date'];

                // Add the interval and total login count
                $intervals[] = [
                    'interval' => "Days $startDate to $endDate", // Dynamic interval range
                    'data' => [
                        'login_count' => $totalLoginCount
                    ]
                ];
            }

            return [
                'success' => true,
                'data' => $intervals
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    public function getMostActiveCourses($intervalValue, $intervalType)
    {
        try {
            // Validate the interval type
            $allowedIntervalTypes = ['DAY', 'MONTH'];
            if (!in_array(strtoupper($intervalType), $allowedIntervalTypes)) {
                throw new Exception("Invalid interval type. Allowed values are 'DAY' or 'MONTH'.");
            }

            // Build the query to fetch all the data for the required period
            $query = "
                SELECT 
                    c.id AS course_id,
                    c.title, 
                    COUNT(e.id) AS total_completed_students,
                    e.completed_date
                FROM 
                    course c
                JOIN enrollment e ON c.id = e.course_id 
                WHERE 
                    e.additional_info = 'Completed' AND e.completed_date >= NOW() - INTERVAL :intervalValue $intervalType
                GROUP BY 
                    c.id, e.completed_date
                ORDER BY 
                    total_completed_students DESC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':intervalValue', $intervalValue, PDO::PARAM_INT);
            $stmt->execute();

            // Fetch the result
            $completionData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate dynamic chunk size based on total data count
            $totalDataCount = count($completionData);
            $chunksCount = 6; // Always divide the data into 6 chunks (or adjust based on your needs)
            $chunkSize = ceil($totalDataCount / $chunksCount);

            // Divide data into dynamic chunks and calculate the total completed students for each chunk
            $intervals = [];
            $chunks = array_chunk($completionData, $chunkSize);

            foreach ($chunks as $index => $chunk) {
                // Calculate the total completed students for this interval
                $totalCompletedStudents = array_sum(array_column($chunk, 'total_completed_students'));

                // Calculate the date range for this interval
                $startDate = $chunk[0]['completed_date'];
                $endDate = end($chunk)['completed_date'];

                // Add the interval and total completed students
                $intervals[] = [
                    'interval' => "Days $startDate to $endDate", // Dynamic interval range
                    'data' => [
                        'total_completed_students' => $totalCompletedStudents
                    ]
                ];
            }

            return [
                'success' => true,
                'data' => $intervals
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    public function getLoginActivityChart()
    {
        try {
            // Query to fetch login counts grouped by date for the last 30 days
            $query = "
      SELECT 
          DATE(last_login) AS login_date, 
          COUNT(*) AS login_count
      FROM 
          person
      WHERE 
          last_login >= CURDATE() - INTERVAL 30 DAY
      GROUP BY 
          login_date
      ORDER BY 
          login_date ASC
  ";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $loginData = $stmt->fetchAll(PDO::FETCH_ASSOC);      // Return JSON response
            return [
                'success' => true,
                'data' => $loginData
            ];
        } catch (Exception $e) {
            // Handle errors
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function generatePDF($data, $title)
    {
        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('LMS System');
        $pdf->SetTitle($title);
        $pdf->SetSubject($title);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(TRUE, 10);
        $pdf->AddPage();

        // Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $title, 0, 1, 'C');
        $pdf->Ln(5);

        if (!empty($data)) {
            // Get keys for table headers
            $headers = array_keys($data[0]);

            // Auto width calculation
            $columnWidths = [];
            $totalColumns = count($headers);
            $pageWidth = $pdf->GetPageWidth() - 20; // Adjust for margins

            foreach ($headers as $header) {
                $columnWidths[] = $pageWidth / $totalColumns;
            }

            // Print table headers
            $pdf->SetFont('helvetica', 'B', 10);
            foreach ($headers as $index => $header) {
                $pdf->MultiCell($columnWidths[$index], 10, ucfirst(str_replace('_', ' ', $header)), 1, 'C', 0, 0);
            }
            $pdf->Ln();

            // Print table rows
            $pdf->SetFont('helvetica', '', 9);
            foreach ($data as $row) {
                foreach ($headers as $index => $header) {
                    $pdf->MultiCell($columnWidths[$index], 10, $row[$header] ?? '-', 1, 'C', 0, 0);
                }
                $pdf->Ln();
            }
        } else {
            $pdf->Cell(0, 10, 'No data available.', 0, 1, 'C');
        }

        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $title)) . '.pdf"');
        $pdf->Output($title . '.pdf', 'D'); // 'D' for download
        exit;
    }



    public function getCourseCreationAndCompletionReport()
    {
        $query = 'SELECT 
               c.status as active,
                c.title AS course_name,
                c.created_at,
                c.completed_at,
                (CASE WHEN c.completed_at IS NOT NULL THEN "Completed" ELSE "In Progress" END) AS status
              FROM course c
              ORDER BY c.created_at DESC';

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->generatePDF($result, 'Course Creation and Completion Report');
    }



    public function getModuleCompletionReport()
    {
        $query = 'SELECT 
                    m.id AS module_id, 
                    m.title AS module_title,
                    COUNT(CASE WHEN mc.status = "completed" THEN 1 END) AS completed_users,
                    COUNT(CASE WHEN mc.status != "completed" OR mc.status IS NULL THEN 1 END) AS not_completed_users
                  FROM modules m
                  LEFT JOIN module_completion mc ON mc.module_id = m.id
                  GROUP BY m.id';
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->generatePDF($result, 'Module Completion Report');
    }


    public function getAssessmentCompletion()
    {
        $query = "SELECT 
                    c.title AS course_name,
                    COUNT(DISTINCT m.id) AS total_assessments,
                    COUNT(DISTINCT mc.user_id) AS total_students_attempted,
                    COUNT(DISTINCT CASE WHEN mc.status = 'Completed' THEN mc.user_id END) AS completed_students,
                    COUNT(DISTINCT CASE WHEN mc.status = 'Failed' THEN mc.user_id END) AS failed_students,
                    ROUND(
                        (COUNT(DISTINCT CASE WHEN mc.status = 'Completed' THEN mc.user_id END) / 
                        NULLIF(COUNT(DISTINCT mc.user_id), 0)) * 100, 2
                    ) AS completion_rate
                FROM modules m
                JOIN module_completion mc ON mc.module_id = m.id
                JOIN course c ON c.id = m.course_id
                WHERE m.module_type = 'assessment'  
                  AND mc.completed_at >= CURDATE() - INTERVAL 30 DAY  
                GROUP BY c.title";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->generatePDF($result, 'Assessment Completion Report');
    }
    public function getComplianceSummary()
    {
        $query = "SELECT 
           c.title AS course_title,
           COUNT(DISTINCT e.user_id) AS total_students,
           SUM(CASE WHEN e.additional_info = 'Completed' THEN 1 ELSE 0 END) AS completed_students,
           SUM(CASE WHEN e.additional_info = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_students,
           (COUNT(DISTINCT e.user_id) - 
            SUM(CASE WHEN e.additional_info = 'Completed' THEN 1 ELSE 0 END) - 
            SUM(CASE WHEN e.additional_info = 'In Progress' THEN 1 ELSE 0 END)) AS pending_students,
           IF(COUNT(DISTINCT e.user_id) > 0, 
              ROUND((SUM(CASE WHEN e.additional_info = 'Completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(DISTINCT e.user_id)), 2), 
              0) AS completion_percentage
                FROM enrollment e
                LEFT JOIN course c ON e.course_id = c.id
                GROUP BY e.course_id, c.title
                ORDER BY completion_percentage DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->generatePDF($result, 'Compliance Summary Report');
    }
    public function getAchievementReports()
    {
        $query = 'SELECT 
                    m.title AS achievement_title, 
                    COUNT(DISTINCT a.user_id) AS users_earned_achievement
                  FROM achievements a
                  JOIN modules m ON m.id = a.module_id
                  WHERE a.earned_at >= CURDATE() - INTERVAL 30 DAY
                  GROUP BY a.module_id, m.title
                  ORDER BY a.earned_at DESC';
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->generatePDF($result, 'Achievement Reports');
    }
    public function getBadgesReports()
    {
        $query = 'SELECT badge_name, COUNT(DISTINCT user_id) AS total_students 
                  FROM badges 
                  WHERE earned_at >= CURDATE() - INTERVAL 30 DAY
                  GROUP BY badge_name
                  ORDER BY earned_at DESC';
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->generatePDF($result, 'Badges Reports');
    }
    public function getTeamsReport()
    {
        $query = 'SELECT 
                    t.name AS team_name,
                    COUNT(DISTINCT tm.user_id) AS total_members,
                    (SELECT COUNT(DISTINCT tc.course_id) FROM team_courses tc WHERE tc.team_id = t.id) AS total_courses,
                    (SELECT COUNT(DISTINCT a.id) FROM achievements a WHERE a.user_id IN (SELECT user_id FROM team_members WHERE team_id = t.id)) AS total_achievements,
                    (SELECT COUNT(DISTINCT b.id) FROM badges b WHERE b.user_id IN (SELECT user_id FROM team_members WHERE team_id = t.id)) AS total_badges
                  FROM teams t
                  LEFT JOIN team_members tm ON tm.team_id = t.id
                  GROUP BY t.id
                  ORDER BY t.name';

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->generatePDF($result, 'Teams Report');
    }



    public function getLearningPathReport()
    {
        $report = [];

        //  Total Learning Paths created in the last 30 days
        $query = 'SELECT COUNT(*) as total_learning_paths 
                  FROM learning_paths 
                  WHERE created_at >= NOW() - INTERVAL 30 DAY';
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $report['Total Learning Paths'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total_learning_paths'];

        //  Total Users enrolled in Learning Paths in the last 30 days
        $query = 'SELECT COUNT(DISTINCT user_id) as total_enrolled_users 
                  FROM user_learning_path_progress 
                  WHERE completed_at >= NOW() - INTERVAL 30 DAY';
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $report['Total Enrolled Users'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total_enrolled_users'];

        //  Total Completed Learning Paths in the last 30 days
        $query = 'SELECT COUNT(*) as completed_learning_paths 
                  FROM user_learning_path_progress 
                  WHERE status = "Completed" 
                  AND completed_at >= NOW() - INTERVAL 30 DAY';
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $report['Completed Learning Paths'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['completed_learning_paths'];

        //  Learning Path Details: Users Enrolled, Course Count, Users Completed, and User Course Completion
        $query = 'SELECT lp.id, lp.name, 
                         (SELECT COUNT(DISTINCT ulp.user_id) FROM user_learning_path_progress ulp WHERE ulp.learning_path_id = lp.id) as total_users_enrolled,
                         (SELECT COUNT(*) FROM learning_path_courses lpc WHERE lpc.learning_path_id = lp.id) as total_courses,
                         (SELECT COUNT(DISTINCT ulp.user_id) FROM user_learning_path_progress ulp WHERE ulp.learning_path_id = lp.id AND ulp.status = "Completed") as total_users_completed
                  FROM learning_paths lp 
                  WHERE lp.created_at >= NOW() - INTERVAL 30 DAY';
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $report['Learning Paths'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->generateLpPDF([$report], 'Learning Path Report');
    }
    private function generateLPPDF($data, $title)
    {
        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('LMS System');
        $pdf->SetTitle($title);
        $pdf->SetSubject($title);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(TRUE, 10);
        $pdf->AddPage();

        // Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $title, 0, 1, 'C');
        $pdf->Ln(5);

        // Convert data to table format
        $pdf->SetFont('helvetica', 'B', 12);
        if (!empty($data)) {
            // Get keys for table headers
            $headers = array_keys($data[0]);

            // Print table headers
            foreach ($headers as $header) {
                $pdf->Cell(45, 10, ucfirst(str_replace('_', ' ', $header)), 1);
            }
            $pdf->Ln();

            // Print table rows
            $pdf->SetFont('helvetica', '', 10);
            foreach ($data as $row) {
                foreach ($headers as $header) {
                    // Convert arrays to JSON string or a formatted string
                    $value = $row[$header] ?? '-';
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE); // Convert array to JSON string
                    }
                    $pdf->Cell(45, 10, substr($value, 0, 30), 1); // Trim long text for better display
                }
                $pdf->Ln();
            }
        } else {
            $pdf->Cell(0, 10, 'No data available.', 0, 1, 'C');
        }

        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $title)) . '.pdf"');
        $pdf->Output($title . '.pdf', 'D'); // 'D' for download
        exit;
    }

    public function getSessionsReport()
    {
        $query = 'SELECT 
                    COUNT(ilt.id) AS total_sessions_created, 
                    ilt.id AS session_id,
                    ilt.session_name,
                    ilt.course_id,
                    COUNT(DISTINCT ie.user_id) AS total_enrolled_students
                  FROM ilt_sessions ilt
                  LEFT JOIN ilt_enrollments ie ON ilt.id = ie.session_id
                  GROUP BY ilt.id';
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $sessionsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->generatePDF($sessionsData, 'Session Reports');
    }
}
