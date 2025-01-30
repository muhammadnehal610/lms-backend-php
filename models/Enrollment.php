<?php
// Model/Enrollment.php

class Enrollment {
    private $db;

    // Constructor to initialize database connection
    public function __construct($db) {
        $this->db = $db;
    }

    // Function to create a new enrollment
    // Function to create a new enrollment with duplicate check
    public function enrollStudent($student_id, $course_id) {
        // Step 1: Check if the student is already enrolled in the course
        $query = "SELECT COUNT(*) as total FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($result['total'] > 0) {
            return ['status' => false, 'message' => 'Student is already enrolled in the course'];
        }
    
        // Step 2: Get course details
        $courseDetails = $this->getCourseDetails($course_id);
    
        if (!$courseDetails) {
            return ['status' => false, 'message' => 'Course not found'];
        }
    
        $maxStudents = $courseDetails['max_students'];
        $currentEnrollmentCount = (int) $courseDetails['total_enrolled_students']; // Get current count from the course table
    
        // Step 3: Check if the course has reached its maximum limit
        if ($currentEnrollmentCount >= $maxStudents) {
            return ['status' => false, 'message' => 'Course has reached the maximum number of students'];
        }
    
        // Step 4: Proceed with enrollment if the course is not full
        $query = "INSERT INTO enrollments (student_id, course_id) VALUES (:student_id, :course_id)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':course_id', $course_id);
    
        if ($stmt->execute()) {
            // Step 5: Update total enrolled students in the courses table
            $updateQuery = "UPDATE courses SET total_enrolled_students = total_enrolled_students + 1 WHERE id = :course_id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':course_id', $course_id);
    
            if ($updateStmt->execute()) {
                return ['status' => true, 'message' => 'Enrollment successful'];
            } else {
                // Rollback the enrollment if the update fails
                $this->unenrollLastStudent($student_id, $course_id);
                return ['status' => false, 'message' => 'Failed to update course enrollment count'];
            }
        }
    
        return ['status' => false, 'message' => 'Failed to enroll student'];
    }
    
    // Function to unenroll a student in case of rollback
    private function unenrollLastStudent($student_id, $course_id) {
        $query = "DELETE FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
    }
    
    // Model/Enrollment.php

// Function to get course details including max_students
public function getCourseDetails($course_id)
{
    $query = "SELECT * FROM courses WHERE id = :course_id";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get the current number of students enrolled in a course
public function getCurrentEnrollmentCount($course_id)
{
    $query = "SELECT COUNT(*) as total_enrolled FROM enrollments WHERE course_id = :course_id";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return (int) $result['total_enrolled'];
}


    // Function to get all enrollments (Admin/Teacher only)
    public function getAllEnrollments() {
        $query = "SELECT * FROM enrollments";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Function to get a specific enrollment by ID
    public function getEnrollmentById($id) {
        $query = "SELECT * FROM enrollments WHERE course_id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Function to unenroll a student from a course
    public function unenrollStudent($id) {
        $query = "DELETE FROM enrollments WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
}
?>
