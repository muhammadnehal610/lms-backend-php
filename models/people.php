<?php
class People
{
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getRecentAtivity($user_id)
    {
        $recentActivity = [];

        // Get course views
        $getCoursesViews = 'SELECT c_view.*, c_title.title, user.first_name
                            FROM course_views c_view  
                            LEFT JOIN course c_title ON c_view.course_id = c_title.id 
                            LEFT JOIN person user ON c_view.user_id = user.id
                            WHERE c_view.user_id=:user_id AND c_view.viewed_at >= NOW() - INTERVAL 60 DAY';
        $stmt = $this->db->prepare($getCoursesViews);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $courseViews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get module completions
        $getModuleComplete = 'SELECT m_completion.*, m.title AS module_title, m.module_type, c.title AS course_title, 
                                     p.first_name AS user_name, a_module.id AS assessment_module_id
                              FROM module_completion m_completion 
                              LEFT JOIN modules m ON m_completion.module_id = m.id
                              LEFT JOIN course c ON m_completion.course_id = c.id
                              LEFT JOIN person p ON m_completion.user_id = p.id
                              LEFT JOIN assessment_module a_module ON a_module.module_id = m.id
                              WHERE m_completion.user_id=:user_id AND m_completion.completed_at >=NOW() - INTERVAL 60 DAY';
        $stmt = $this->db->prepare($getModuleComplete);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $moduleCompletes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process course views
        foreach ($courseViews as &$courseView) {
            if (isset($courseView['viewed_at'])) {
                $courseView['msg'] = 'This course ' . $courseView['title'] . ' was viewed by ' . $courseView['first_name'];
            }
        }
        unset($courseView);

        // Process module completions
        foreach ($moduleCompletes as &$moduleComplete) {
            if (isset($moduleComplete['module_type']) && $moduleComplete['module_type'] !== 'assessment') {
                unset($moduleComplete['assessment_module_id']);
            }
            if (isset($moduleComplete['module_type']) && $moduleComplete['module_type'] === 'assessment') {
                // Get earn_marks from assessment_module_submission if module type is 'assessment'
                $query = 'SELECT earn_marks AS total_score 
                          FROM assessment_module_submision 
                          WHERE user_id = :user_id AND assessment_module_id = :assessment_module_id';
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                $stmt->bindParam(":assessment_module_id", $moduleComplete['assessment_module_id'], PDO::PARAM_INT);
                $stmt->execute();
                $submission = $stmt->fetch(PDO::FETCH_ASSOC);

                // Assign total score to moduleComplete if available
                if ($submission) {
                    $moduleComplete['total_score'] = $submission['total_score'];
                } else {
                    $moduleComplete['total_score'] = 0; // No score available
                }
            }

            // Format module completion message
            if (isset($moduleComplete['status'])) {
                $moduleComplete['user_name'] = ucwords($moduleComplete['user_name']);
                $moduleComplete['course_title'] = ucwords($moduleComplete['course_title']);
                $moduleComplete['module_title'] = ucwords($moduleComplete['module_title']);
                $moduleComplete['msg'] = ucwords($moduleComplete['user_name'] . ' has completed the ' . $moduleComplete['module_type'] . ' module ' . $moduleComplete['module_title'] . ' from the course ' . $moduleComplete['course_title']);
            }
        }
        unset($moduleComplete);

        // Merge course views and module completions
        $recentActivity = array_merge($courseViews, $moduleCompletes);


        return [
            'error' => false,
            'status' => 200,
            'massege' => 'recent activity fetched successfully',
            'data' => $recentActivity
        ];
    }

    public function getAcheivements($user_id)
    {
        $query = 'SELECT a.earned_at, p.first_name as user_name, m.title as achievements, c.title as course_name
                  FROM achievements a 
                  LEFT JOIN person p on a.user_id = p.id
                  LEFT JOIN modules m on a.module_id = m.id 
                  LEFT JOIN course c on m.course_id = c.id
                  WHERE a.user_id =:user_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $achievements  = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($achievements as &$achievement) {
            if (isset($achievement['earned_at'])) {
                // Fixed the spelling error here: "acheivements" -> "achievements"
                $achievement['msg'] = $achievement['user_name'] . ' has earned ' . $achievement['achievements'] . ' from the ' . $achievement['course_name'] . ' course.';
            }
        }
        unset($achievement); // Corrected to unset the correct variable

        return [
            'error' => false,
            'status' => 200,
            'massege' => 'achievement fetched successfully',
            'data' => $achievements
        ];
    }

    public function getCourses($user_id)
    {
        $query = 'SELECT e.* , c.title FROM enrollment e 
                  LEFT JOIN course c on e.course_id = c.id
                  WHERE user_id=:user_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $userCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'error' => false,
            'status' => 200,
            'massege' => 'userCourses fetched successfully',
            'data' => $userCourses
        ];
    }
    public function getModuleByCourses($user_id, $course_id)
    {
        $query = 'SELECT e.* , c.title FROM enrollment e 
                  LEFT JOIN course c on e.course_id = c.id
                  WHERE user_id=:user_id AND course_id=:course_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":course_id", $course_id);
        $stmt->execute();
        $userCourses = $stmt->fetch(PDO::FETCH_ASSOC);

        $query = 'SELECT * FROM modules WHERE course_id=:course_id';
        $stmt  = $this->db->prepare($query);
        $stmt->bindParam(":course_id", $userCourses['course_id']);
        $stmt->execute();
        $userCourses['modules'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'error' => false,
            'status' => 200,
            'massege' => 'userCourses fetched successfully',
            'data' => $userCourses
        ];
    }
    public function getLearningPaths($user_id)
    {
        $query  = 'SELECT lp.* , lp_progress.* 
                   FROM learning_paths lp LEFT JOIN user_learning_path_progress lp_progress 
                   on lp_progress.learning_path_id =lp.id 
                   WHERE lp_progress.user_id = :user_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $learningPaths = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'error' => false,
            'status' => 200,
            'massege' => 'learningPaths fetched successfully',
            'data' => $learningPaths
        ];
    }
    public function getLearningPathById($user_id, $learning_path_id)
    {
        $query = 'SELECT 
                    lp.id AS learning_path_id, 
                    lp.name AS learning_path_name, 
                    lp.description AS learning_path_description, 
                    lp_progress.progress AS learning_path_progress
                  FROM learning_paths lp
                  LEFT JOIN user_learning_path_progress lp_progress 
                    ON lp_progress.learning_path_id = lp.id 
                    AND lp_progress.user_id = :user_id
                  WHERE lp.id = :learning_path_id';

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":learning_path_id", $learning_path_id);
        $stmt->execute();
        $learningPath = $stmt->fetch(PDO::FETCH_ASSOC); // ✅ fetch() for single record

        // ✅ Agar learning path exist nahi karta toh null return kar do
        if (!$learningPath) {
            return null;
        }

        // ✅ Ab courses fetch karo jo iss Learning Path ka part hain
        $query = 'SELECT 
                    c.id AS course_id, 
                    c.title AS course_title, 
                    c.description AS course_description,
                    e.status AS course_status -- ✅ User ne course complete kia ya nahi
                  FROM learning_path_courses lpc
                  LEFT JOIN course c 
                    ON c.id = lpc.course_id
                  LEFT JOIN enrollment e 
                    ON e.course_id = c.id AND e.user_id = :user_id
                  WHERE lpc.learning_path_id = :learning_path_id';

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":learning_path_id", $learning_path_id);
        $stmt->execute();
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC); // ✅ Multiple courses ho sakte hain

        $learningPath['courses'] = $courses; // ✅ Courses ko Learning Path mein add kar diya

        return [
            'error' => false,
            'status' => 200,
            'massege' => 'learningPath fetched successfully',
            'data' => $learningPath
        ];
    }
    public function getIltSessions($user_id)
    {
        $query = 'SELECT ilt_s.* , ilt_e.user_id , ilt_e.enrolled_at , c.title as course_title , m.title as module_title , p.first_name , p.email
                   FROM ilt_sessions ilt_s LEFT JOIN ilt_enrollments ilt_e 
                   on ilt_e.session_id=ilt_s.id
                   LEFT JOIN course c on ilt_s.course_id = c.id
                   LEFT JOIN modules m on ilt_s.module_id = m.id
                   LEFT JOIN person p on ilt_e.user_id = p.id
                   WHERE ilt_e.user_id=:user_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $ilt_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'error' => false,
            'status' => 200,
            'massege' => 'ilt_enrollments fetched successfully',
            'data' => $ilt_enrollments
        ];
    }
    public function getUserTeams($user_id)
    {
        $query = 'SELECT 
                     t.id, 
                     t.name, 
                     t.description, 
                     t.created_at, 
                     tm.user_id, 
                     tm.added_at
                  FROM teams t 
                  LEFT JOIN team_members tm ON tm.team_id = t.id
                  WHERE tm.user_id =:user_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $Teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'error' => false,
            'status' => 200,
            'massege' => 'Teams fetched successfully',
            'data' => $Teams
        ];
    }
    public function getTeams($user_id)
    {
        $query = 'SELECT DISTINCT 
                      t.id, 
                      t.name, 
                      t.description, 
                      t.created_at
                  FROM teams t 
                  WHERE t.id NOT IN (
                      SELECT DISTINCT team_id FROM team_members WHERE user_id = :user_id
                  )';

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'error' => false,
            'status' => 200,
            'message' => 'Teams fetched successfully',
            'data' => $teams
        ];
    }



    public function userAssignToTeam($user_id, $team_id)
    {
        $query = 'INSERT INTO team_members (user_id , team_id) VALUES (:user_id , :team_id)';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":team_id", $team_id);
        $stmt->execute();
        return [
            'error' => false,
            'status' => 200,
            'massege' => 'user added to team  successfully'

        ];
    }
}
