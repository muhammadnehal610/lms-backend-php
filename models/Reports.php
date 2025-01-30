<?php

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
    public function getCourseCreationAndCompletionReport()
    {
        // Query to count courses created and completed in the last 30 days
        $query = 'SELECT 
                    COUNT(CASE WHEN created_at >= CURDATE() - INTERVAL 30 DAY THEN 1 END) AS courses_created_last_30_days,
                    COUNT(CASE WHEN  completed_at >= CURDATE() - INTERVAL 30 DAY THEN 1 END) AS courses_completed_last_30_days
                  FROM course';

        // Prepare and execute the query
        $query = $this->db->prepare($query);
        $query->execute();

        // Fetch the result
        $result = $query->fetch(PDO::FETCH_ASSOC);

        // Return the result
        return [
            'status' => 200,
            'msg' => 'Course creation and completion report fetched successfully',
            'data' => [
                'courses_created_last_30_days' => $result['courses_created_last_30_days'],
                'courses_completed_last_30_days' => $result['courses_completed_last_30_days']
            ]
        ];
    }
    public function getModuleCompletionReport()
    {
        // Query to get the count of completed and not completed users per module
        $query = 'SELECT 
                    m.id AS module_id, 
                    m.title AS module_title,
                    COUNT(CASE WHEN mc.status = "completed" THEN 1 END) AS completed_users,
                    COUNT(CASE WHEN mc.status != "completed" OR mc.status IS NULL THEN 1 END) AS not_completed_users
                  FROM modules m
                  LEFT JOIN module_completion mc ON mc.module_id = m.id
                  GROUP BY m.id';

        // Prepare and execute the query
        $query = $this->db->prepare($query);
        $query->execute();

        // Fetch the result
        $result = $query->fetchAll(PDO::FETCH_ASSOC);

        // Check if there are any results
        if ($result) {
            return [
                'status' => 200,
                'msg' => 'Module completion report fetched successfully',
                'data' => $result
            ];
        } else {
            return [
                'status' => 404,
                'msg' => 'No data found',
                'data' => []
            ];
        }
    }

    public function getAssessmentCompletion()
    {
        $query = "SELECT 
    COUNT(DISTINCT m.id) AS total_assessments,  -- Get total assessments from module table
    COUNT(DISTINCT CASE WHEN mc.status = 'Completed' THEN mc.user_id END) AS completed_students,
    COUNT(DISTINCT CASE WHEN mc.status = 'Failed' THEN mc.user_id END) AS failed_students,
    c.title AS course_name
FROM 
    modules m
JOIN 
    module_completion mc ON mc.module_id = m.id
JOIN 
    course c ON c.id = m.course_id
WHERE 
    m.module_type = 'assessment'  -- Only filter for 'assessment' type modules
    AND mc.completed_at >= CURDATE() - INTERVAL 30 DAY  -- Last 30 days condition

GROUP BY 
    c.title;
";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {

            return [
                'error' => false,
                'status' => 200,
                'massege' => 'assessment report fetch successfully',
                'data' => $result
            ];
        } else {
            return [
                'error' => false,
                'status' => 200,
                'massege' => 'no assessment found',
                'data' => []
            ];
        }
    }
}
