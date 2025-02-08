<?php
// Updated CourseController.php - Controller class
require_once 'models/Course.php';
require_once 'models/User.php'; // Import User model
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;

class CourseController
{
    private $courseModel;
    private $userModel;
    public $userId;
    private $userRole;

    public function __construct($db)
    {
        $this->courseModel = new Course($db);
        $this->userModel = new User($db);
    }

    public function getAllCourses()
    {
        $courses = $this->courseModel->getAllCourses();
        if (empty($courses)) {
            return ['error' => true, 'status' => 200, 'message' => 'No courses found', 'courses' => []];
        } else {
            return ['error' => false, 'status' => 200, 'message' => 'Courses fetched successfully', 'courses' => $courses];
        }
    }

    public function getCourseById($id)
    {
        $course = $this->courseModel->getCourseById($id);
        if ($course) {
            return ['error' => false, 'status' => 200, 'course' => $course,];
        } else {
            return ['error' => true, 'status' => 404, 'message' => 'Course not found'];
        }
    }
    public function getCourseByIdByUser($course_id, $user_id)
    {
        return $this->courseModel->getCourseByIdByUser($course_id, $user_id);
    }

    public function createCourse($data, $createrId)
    {
        $result = $this->courseModel->createCourse($data, $createrId);

        if ($result) {
            return [
                'error' => false,
                'message' => 'Course created successfully',
                'data' => [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'max_students' => $data['max_student'],
                    'reference_code' => $data['reference_code'],
                    'created_by' => $createrId,
                ]
            ];
        } else {
            return [
                'error' => true,
                'message' => 'Failed to create course'
            ];
        }
    }
    public function updateCourse($id, $data)
    {
        $result = $this->courseModel->updateCourse($id, $data, $this->userId);
        if ($result) {
            return [
                'error' => false,
                'message' => 'Course updated successfully',
                'data' => [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'max_students' => $data['max_student'],
                    'reference_code' => $data['reference_code'],
                    'status' => $data['status'],


                ]
            ];
        } else {
            return ['error' => true, 'message' => 'Failed to update course'];
        }
    }
    public function deleteCourse($id)
    {
        $course = $this->courseModel->getCourseById($id);
        if (!$course) {
            return ['error' => true, 'message' => 'Course not found'];
        }

        $result = $this->courseModel->deleteCourse($id);
        if ($result) {
            return ['error' => false, 'message' => 'Course deleted successfully'];
        } else {
            return ['error' => true, 'message' => 'Failed to delete course'];
        }
    }
    public function markAsCompleted($course_id)
    {
        $result = $this->courseModel->markAsCompleted($course_id);
        if ($result) {
            return [
                'error' => false,
                'status' => 200,
                'massege' => 'course mark as completed'
            ];
        } else {
            return [
                'error' => true,
                'status' => 400,
                'massege' => 'failed to mark course as completed'
            ];
        }
    }
    public function getBadgesByUserId($user_id)
    {
        $result = $this->courseModel->getBadgesByUserId($user_id);
        return [
            'error' => false,
            'statuse' => 200,
            'massege' => 'badges fectch successfully',
            'data' => $result
        ];
    }
    public function addEnrollmentByuser($course_id, $user_id)
    {
        $enrollment = $this->courseModel->addEnrollmentByuser($course_id, $user_id);
        return $enrollment;
    }
    public function addEnrollment($courseId, $userIds)
    {
        // Validate that userIds is an array and not empty
        if (!is_array($userIds) || empty($userIds)) {
            return [
                'error' => true,
                'message' => 'Invalid user ID list. Must be a non-empty array.'
            ];
        }
    
        // Fetch user details (removing duplicates to avoid redundant DB queries)
        $userIds = array_unique($userIds);
        $userResponse = $this->userModel->getUserByIdMultipleUser($userIds);
    
        // If fetching users failed, return error
        if ($userResponse['error']) {
            return [
                'error' => true,
                'message' => 'Failed to fetch user details.',
                'details' => $userResponse['message']
            ];
        }
    
        // Extract valid users from response
        $usersInfo = $userResponse['users'];
    
        // If no valid users found, return an error
        if (empty($usersInfo)) {
            return [
                'error' => true,
                'message' => 'No valid users found for enrollment.'
            ];
        }
    
        // Process enrollments
        $enrollmentResponse = $this->courseModel->addEnrollments($courseId, $usersInfo);
    
        return $enrollmentResponse;
    }
    

    public function getEnrollmentByCourseId($course_id)
    {
        $result = $this->courseModel->getEnrollmentByCourseId($course_id);
        if ($result) {
            return [
                "status" => 200,
                "message" => "get enrollment successfully",
                "data" => $result
            ];
        } else {
            return [
                "status" => 400,
                "message" => "failed to fetch enrollment",
            ];
        }
    }
    public function deleteEnrollmentByUser($course_id, $user_id)
    {
        return $this->courseModel->deleteEnrollmentByUser($course_id, $user_id);
    }
    public function deleteEnrollmentByCourseId($course_id, $userId)
    {
        $result = $this->courseModel->deleteEnrollments($course_id, $userId);
        if ($result) {
            return [
                "status" => 200,
                "message" => "Delete user successfully",
                "data" => $result,
            ];
        } else {
            return [
                "status" => 400,
                "message" => "Failed to delete user",
            ];
        }
    }
    public function editEnrollmentResults($course_id, $data)
    {
        // Validate the input data
        if (!isset($data->user_id) || !isset($data->additional_info)) {
            return [
                "error" => true,
                "message" => "Missing required fields: user_id or additional_info.",
            ];
        }
        $result = $this->courseModel->editEnrollmentResults($course_id, $data);
        if ($result) {
            return [
                "status" => 200,
                "message" => "update result",
            ];
        } else {
            return [
                "status" => 400,
                "message" => "update failed",
            ];
        }
    }
    public function getInstructor()
    {
        $instructor = $this->userModel->getInstructor();
        return [
            'status' => 200,
            'message' => 'instructor fetch successfully',
            'data' => $instructor,
        ];
    }
    public function getInstructorsByIds($instructorIds)
    {
        $validInstructors = [];
        $errors = [];

        foreach ($instructorIds as $instructorId) {
            $result = $this->courseModel->getInstructorById($instructorId);

            if ($result['error']) {
                $errors[] = "Instructor with ID $instructorId not found.";
            } else {
                $validInstructors[] = $result['instructor']['id'];
            }
        }

        return [
            'validInstructors' => $validInstructors,
            'errors' => $errors,
        ];
    }

    public function addMultipleInstructors($course_id, $instructorIds)
    {
        // Validate instructors
        $instructorResult = $this->getInstructorsByIds($instructorIds);

        if (empty($instructorResult['validInstructors'])) {
            return [
                'status' => 400,
                'message' => 'No valid instructors found.',
                'errors' => $instructorResult['errors'],
            ];
        }

        // Add valid instructors to the course
        $addInstructor = $this->courseModel->addInstructorsToCourse($course_id, $instructorResult['validInstructors']);

        if (!$addInstructor['error']) {
            return [
                'status' => 200,
                'message' => 'Instructors added successfully.',
                'errors' => $instructorResult['errors'], // Return any errors for invalid instructors
            ];
        } else {
            return [
                'status' => 400,
                'message' => $addInstructor['message'],
            ];
        }
    }

    public function removeInstructors($course_id, $data)
    {
        // Validate input
        if (!isset($data->ids) || !is_array($data->ids) || empty($data->ids)) {
            return [
                'status' => 400,
                'message' => 'Invalid input. Please provide an array of instructor IDs.',
            ];
        }

        // Call the model function to remove instructors
        $result = $this->courseModel->removeInstructorsFromCourse($course_id, $data);

        // Format and return the response based on the result
        if ($result['error']) {
            return [
                'status' => 400,
                'message' => $result['message'],
                'errors' => $result['errors'] ?? null,
            ];
        }

        return [
            'status' => 200,
            'message' => $result['message'],
        ];
    }
    public function addNotice($course_id, $data, $creater_id)
    {
        if (!isset($data['title']) || !isset($data['content'])) {
            return [
                'status' => 400,
                'message' => 'please enter title and description',
            ];
        }
        $result = $this->courseModel->addNotice($course_id, $data, $creater_id);
        if ($result) {
            return [
                'status' => 200,
                'message' => "notice added successfully",
            ];
        } else {
            return [
                "status" => 400,
                "message" => "failed to add notice",
            ];
        }
    }
}
