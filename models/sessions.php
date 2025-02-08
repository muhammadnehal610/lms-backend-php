<?php
require_once 'vendor/autoload.php';  // This is enough to load TCPDF if installed via Composer

class Sessions
{
    public $db;
    public function __construct($db)
    {
        $this->db = $db;
    }
    public function addSessions($data)
    {
        $query = 'INSERT INTO ilt_sessions (session_name , course_id , module_id  , location , start_time , end_time , max_participants) VALUE (:session_name , :course_id , :module_id  , :location , :start_time , :end_time , :max_participants)';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":session_name", $data->session_name);
        $stmt->bindParam(":course_id", $data->course_id);
        $stmt->bindParam(":module_id", $data->module_id);
        $stmt->bindParam(":location", $data->location);
        $stmt->bindParam(":start_time", $data->start_time);
        $stmt->bindParam(":end_time", $data->end_time);
        $stmt->bindParam(":max_participants", $data->max_participants);
        $stmt->execute();
        return true;
    }
    public function getSessions()
    {
        $query = 'SELECT 
            ilt.id,
            ilt.session_name, 
            ilt.location, 
            ilt.start_time, 
            ilt.end_time, 
            c.title AS course_title, 
            m.title AS module_title
        FROM ilt_sessions ilt
        LEFT JOIN course c ON ilt.course_id = c.id
        LEFT JOIN modules m ON ilt.module_id = m.id';
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getSessionById($sessionId)
    {
        $query = 'SELECT 
            ilt.id,
            ilt.session_name, 
            ilt.location, 
            ilt.start_time, 
            ilt.end_time, 
            ilt.max_participants,
            c.title AS course_title, 
            m.title AS module_title,
            COUNT(ilt_enrollments.id) AS total_enrollments
        FROM 
            ilt_sessions ilt
        LEFT JOIN 
            course c ON ilt.course_id = c.id
        LEFT JOIN 
            modules m ON ilt.module_id = m.id
        LEFT JOIN 
            ilt_enrollments ilt_enrollments ON ilt.id = ilt_enrollments.session_id
        WHERE 
            ilt.id = :session_id
        GROUP BY 
            ilt.id, c.title, m.title;
        ';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":session_id", $sessionId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function sessionsEnrollment($userId, $sessionId)
    {
        $query = 'SELECT course_id FROM ilt_sessions WHERE id = :sessionId';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":sessionId", $sessionId);
        $stmt->execute();
        $courseId = $stmt->fetch(PDO::FETCH_ASSOC);
        $query = 'SELECT user_id FROM enrollment WHERE user_id = :user_id AND course_id = :course_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $userId);
        $stmt->bindParam(":course_id", $courseId['course_id']);
        $stmt->execute();
        $userEnrolled = $stmt->Fetch(PDO::FETCH_ASSOC);
        if (!$userEnrolled) {
            return [
                'error' => true,
                'status' => 404,
                'massege' => "you are not enrolled in this course"
            ];
        }

        $query = 'INSERT INTO ilt_enrollments (user_id , session_id ) VALUE (:user_id , :session_id)';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $userId);
        $stmt->bindParam(":session_id", $sessionId);
        $stmt->execute();
        return [
            'error' => false,
            'status' => 200,
            'massege' => "user added successfully"
        ];
    }
}
