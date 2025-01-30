<?php
// Controller/EnrollmentController.php

require_once 'models/Enrollment.php';
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;

class EnrollmentController
{

    private $db;
    private $enrollment;
    private $userId;

    // Constructor to initialize database and model
    public function __construct($db)
    {
        $this->db = $db;
        $this->enrollment = new Enrollment($this->db);
    }

    // Function to enroll a student in a course
    public function enrollStudent($data)
    {
        if (isset($data->course_id)) {
            $student_id = $this->userId;
            $course_id = $data->course_id;
            $course = $this->enrollment->getCourseDetails($course_id);
            if (!$course) {
                return ["message" => "Course not found."];
            }
            $currentEnrolled = $this->enrollment->getCurrentEnrollmentCount($course_id);
            $result = $this->enrollment->enrollStudent($student_id, $course_id);
            return $result;
        }
    }

    // Function to get all enrollments (Admin/Teacher only)
    public function getAllEnrollments()
    {
      

        $enrollments = $this->enrollment->getAllEnrollments();

        if (count($enrollments) > 0) {
            return $enrollments;
        } else {
            return ["message" => "No enrollments found."];
        }
    }

    // Function to get a specific enrollment by ID
    public function getEnrollmentById($id)
    {
        $enrollment = $this->enrollment->getEnrollmentById($id);

        if ($enrollment) {
            return  $enrollment;
        } else {
            return ["message" => "Enrollment not found."];
        }
    }

    // Function to unenroll a student from a course
    public function unenrollStudent($id)
    {
        if ($this->enrollment->unenrollStudent($id)) {
            echo json_encode(["message" => "Student successfully unenrolled from the course."]);
        } else {
            echo json_encode(["message" => "Failed to unenroll student."]);
        }
    }

    // Private function to check user role and validate JWT
  
}
?>