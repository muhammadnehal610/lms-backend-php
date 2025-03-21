<?php
require_once 'models/Modules.php';
class Course
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getAllCourses()
    {
        $query = 'SELECT * FROM course';
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getCourseByIdByUser($course_id, $user_id)
    {
        try {
            // Query to get course details
            $query = 'SELECT * FROM course WHERE id = :id';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $course_id, PDO::PARAM_INT);
            $stmt->execute();
            $course = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($course) {
                // Fetch modules
                $modulesQuery = 'SELECT * FROM modules WHERE course_id = :course_id';
                $modulesStmt = $this->db->prepare($modulesQuery);
                $modulesStmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
                $modulesStmt->execute();
                $modules = $modulesStmt->fetchAll(PDO::FETCH_ASSOC);

                // Get the total count of modules
                $countQuery = 'SELECT COUNT(*) AS total_count FROM modules WHERE course_id = :course_id';
                $countStmt = $this->db->prepare($countQuery);
                $countStmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
                $countStmt->execute();
                $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total_count'];

                // Add modules to course data
                $course['modules'] = [
                    'total_modules' => $totalCount,
                    'modules' => $modules
                ];

                // Check user's enrollment status
                $enrollmentQuery = 'SELECT additional_info FROM enrollment WHERE course_id = :course_id AND user_id = :user_id';
                $enrollmentStmt = $this->db->prepare($enrollmentQuery);
                $enrollmentStmt->bindParam(":course_id", $course_id, PDO::PARAM_INT);
                $enrollmentStmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
                $enrollmentStmt->execute();
                $enrollmentData = $enrollmentStmt->fetch(PDO::FETCH_ASSOC);

                $query = 'INSERT INTO course_views (user_id , course_id) VALUE (:user_id , :course_id)';
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":user_id", $user_id);
                $stmt->bindParam(":course_id", $course_id);
                $stmt->execute();

                // Determine course status
                if ($enrollmentData) {
                    switch ($enrollmentData['additional_info']) {
                        case 'Completed':
                            $course['course_status'] = "Course Completed";
                            break;
                        case 'In Progress':
                            $course['course_status'] = "Resume Course";
                            break;
                        case 'Overdue':
                            $course['course_status'] = "Resume Course"; // Treat overdue as in-progress
                            break;
                        default:
                            $course['course_status'] = "Resume Course"; // Catch-all for other statuses
                            break;
                    }
                } else {
                    $course['course_status'] = "Get Started"; // User not enrolled
                }

                return $course;
            }

            return "null"; // Course not found
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            return $e->getMessage();
        }
    }

    public function getCourseById($id)
    {
        // Query to get course details
        $query = 'SELECT * FROM course WHERE id = :id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        // If course exists, fetch associated modules
        if ($course) {
            // Query to get the modules related to the course
            $modulesQuery = 'SELECT * FROM modules WHERE course_id = :course_id';
            $modulesStmt = $this->db->prepare($modulesQuery);
            $modulesStmt->bindParam(':course_id', $id, PDO::PARAM_INT);
            $modulesStmt->execute();
            $modules = $modulesStmt->fetchAll(PDO::FETCH_ASSOC);
            $countQuery = 'SELECT COUNT(*) AS total_count FROM modules WHERE course_id = :course_id';
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->bindParam(':course_id', $id, PDO::PARAM_INT);
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total_count'];
            // Add the modules to the course data
            $course['modules'] = ['total modules' => $totalCount, 'modules' => $modules];

            return $course;
        }

        return null; // Return null if course not found
    }
    public function createCourse($data, $creatorId)
    {
        // Convert the learning_objective array to JSON
        $learningObjectiveJson = json_encode($data['learning_objective'], JSON_UNESCAPED_UNICODE);

        // Updated query with corrected syntax
        $query = 'INSERT INTO course (
                      title, 
                      description, 
                      created_by, 
                      max_student, 
                      reference_code, 
                      status, 
                      image_file, 
                      learning_objective, 
                      category
                  ) 
                  VALUES (
                      :title, 
                      :description, 
                      :created_by, 
                      :max_student, 
                      :reference_code, 
                      true, 
                      :image_file, 
                      :learning_objective, 
                      :category
                  )';

        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':created_by', $creatorId, PDO::PARAM_INT);
        $stmt->bindParam(':max_student', $data['max_student'], PDO::PARAM_INT);
        $stmt->bindParam(':reference_code', $data['reference_code']);
        $stmt->bindParam(':image_file', $data['image']);
        $stmt->bindParam(':learning_objective', $learningObjectiveJson); // Bind the JSON string
        $stmt->bindParam(':category', $data['category']);

        // Execute the query
        if ($stmt->execute()) {
            return true;
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Error executing query: " . print_r($errorInfo, true));
            return false;
        }
    }
    public function updateCourse($id, $data, $teacherId)
    {
        $query = 'UPDATE course SET 
                  title = :title, 
                  description = :description, 
                
                  max_student = :max_student, 
                  reference_code = :reference_code, 
                  status = :status, 
                  image_file = :image 
                  WHERE id = :id';
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':description', $data['description']);

        $stmt->bindParam(':max_student', $data['max_student'], PDO::PARAM_INT);
        $stmt->bindParam(':reference_code', $data['reference_code']);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        $stmt->bindParam(':image', $data['image']);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
    public function deleteCourse($id)
    {
        $query = 'DELETE FROM course WHERE id = :id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $result = $stmt->execute();
        return $result;
    }
    public function markAsCompleted($course_id)
    {
        $query = 'UPDATE course SET completed_at=NOW() WHERE id=:course_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":course_id", $course_id);
        return $stmt->execute();
    }
    public function checkAndMarkCourseComplete($course_id, $user_id)
    {
        $modulesModel = new Modules($this->db);

        // Check if all modules are complete for the given course and user
        if ($modulesModel->areAllModulesComplete($course_id, $user_id)) {
            // Update the enrollment table to mark the course as completed
            $query = '
            UPDATE 
                enrollment
            SET 
                additional_info = "Completed",
                completed_date = NOW()
            WHERE 
                course_id = :course_id 
                AND user_id = :user_id';

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->rowCount() > 0;
            if ($result) {
                $this->addBadge($user_id, $course_id);
                $this->checkAndMarkLearningPathComplete($user_id, $course_id);
            }
        }
        return false; // Return false if not all modules are complete
    }
    public function checkAndMarkLearningPathComplete($user_id, $completed_course_id)
    {
        //  Find Learning Paths the completed course belongs to
        $query = 'SELECT learning_path_id FROM learning_path_courses WHERE course_id = :course_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":course_id", $completed_course_id, PDO::PARAM_INT);
        $stmt->execute();
        $learningPaths = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($learningPaths as $path) {
            $learning_path_id = $path['learning_path_id'];

            //  Get total courses in the Learning Path
            $totalCoursesQuery = 'SELECT COUNT(*) as total_courses FROM learning_path_courses WHERE learning_path_id = :learning_path_id';
            $totalCoursesStmt = $this->db->prepare($totalCoursesQuery);
            $totalCoursesStmt->bindParam(":learning_path_id", $learning_path_id, PDO::PARAM_INT);
            $totalCoursesStmt->execute();
            $totalCourses = (int) $totalCoursesStmt->fetch(PDO::FETCH_ASSOC)['total_courses'];

            //  Get completed courses for this user
            $completedCoursesQuery = 'SELECT COUNT(*) as completed_courses FROM enrollment 
                                      WHERE user_id = :user_id 
                                      AND course_id IN 
                                        (SELECT course_id FROM learning_path_courses WHERE learning_path_id = :learning_path_id)
                                      AND additional_info = "Completed"';
            $completedCoursesStmt = $this->db->prepare($completedCoursesQuery);
            $completedCoursesStmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $completedCoursesStmt->bindParam(":learning_path_id", $learning_path_id, PDO::PARAM_INT);
            $completedCoursesStmt->execute();
            $completedCourses = (int) $completedCoursesStmt->fetch(PDO::FETCH_ASSOC)['completed_courses'];

            //  Calculate progress (avoid division by zero)
            $progress = ($totalCourses > 0) ? round(($completedCourses / $totalCourses) * 100, 2) : 0;

            //  Determine status
            $status = ($completedCourses == $totalCourses) ? "Completed" : "In Progress";

            //  Check if the progress already exists
            $checkProgressQuery = 'SELECT id FROM user_learning_path_progress WHERE user_id = :user_id AND learning_path_id = :learning_path_id';
            $checkProgressStmt = $this->db->prepare($checkProgressQuery);
            $checkProgressStmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $checkProgressStmt->bindParam(":learning_path_id", $learning_path_id, PDO::PARAM_INT);
            $checkProgressStmt->execute();
            $existingProgress = $checkProgressStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingProgress) {
                //  Update existing progress record
                $updateProgressQuery = 'UPDATE user_learning_path_progress 
                                        SET progress = :progress, status = :status , completed_at = NOW()
                                        WHERE user_id = :user_id AND learning_path_id = :learning_path_id';
            } else {
                //  Insert new progress record if not exists
                $updateProgressQuery = 'INSERT INTO user_learning_path_progress (user_id, learning_path_id, progress, status) 
                                        VALUES (:user_id, :learning_path_id, :progress, :status)';
            }

            $updateProgressStmt = $this->db->prepare($updateProgressQuery);
            $updateProgressStmt->bindParam(":progress", $progress, PDO::PARAM_STR); // Using STR to handle decimal values
            $updateProgressStmt->bindParam(":status", $status, PDO::PARAM_STR);
            $updateProgressStmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $updateProgressStmt->bindParam(":learning_path_id", $learning_path_id, PDO::PARAM_INT);
            $updateProgressStmt->execute();
        }
    }
    public function addBadge($user_id, $course_id)
    {
        // Check if all modules in course are completed
        $query = "SELECT COUNT(*) as total_modules FROM modules WHERE course_id = :course_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
        $total_modules = $stmt->fetch(PDO::FETCH_ASSOC)['total_modules'];

        $query = "SELECT COUNT(*) as completed_modules FROM achievements 
                  JOIN modules ON achievements.module_id = modules.id 
                  WHERE achievements.user_id = :user_id AND modules.course_id = :course_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
        $completed_modules = $stmt->fetch(PDO::FETCH_ASSOC)['completed_modules'];

        if ($completed_modules == $total_modules) {
            // Grant badge if all modules are completed
            $course = $this->getCourseById($course_id);
            $query = "INSERT INTO badges (user_id, course_id, badge_name) VALUES (:user_id, :course_id, :badges_title)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->bindParam(':badges_title', $course['title']);

            if ($stmt->execute()) {
                return json_encode(["status" => "success", "message" => "Badge awarded!"]);
            } else {
                return json_encode(["status" => "error", "message" => "Failed to grant badge."]);
            }
        } else {
            return json_encode(["status" => "error", "message" => "Course not yet completed."]);
        }
    }
    public function getBadgesByUserId($user_id)
    {
        $query = 'SELECT badge_name FROM badges WHERE user_id=:user_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function addEnrollmentByuser($course_id, $user_id)
    {
        $query = 'SELECT id FROM enrollment WHERE user_id=:user_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return [
                'error' => true,
                'status' => 400,
                'massege' => 'user already enrolled'
            ];
        }
        $getUser = 'SELECT id , first_name , email FROM person WHERE id=:user_id';
        $userStmt = $this->db->prepare($getUser);
        $userStmt->bindParam(":user_id", $user_id);
        $userStmt->execute();
        $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
        $query = 'INSERT INTO enrollment (name, email, course_id, user_id) VALUES (:name, :email, :course_id, :user_id)';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $userInfo['first_name']);
        $stmt->bindParam(':email', $userInfo['email']);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->bindParam(':user_id', $userInfo['id']);
        $result = $stmt->execute();

        if ($result) {
            return [
                'error' => false,
                'status' => 200,
                'massege' => 'you are successfully enrolled in this course'
            ];
        }
    }
    public function addEnrollments($course_id, $usersInfo)
    {
        $errors = [];
        $successCount = 0;
        foreach ($usersInfo as $userInfo) {
            $query = 'SELECT id FROM enrollment WHERE course_id = :course_id AND user_id = :userId';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->bindParam(':userId', $userInfo['id']);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Skip the user if already enrolled
                $errors[] = "User {$userInfo['email']} is already enrolled.";
                continue;
            }

            // Insert the new user into the enrollment table
            $query = 'INSERT INTO enrollment (name, email, course_id, user_id) VALUES (:name, :email, :course_id, :user_id)';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':name', $userInfo['first_name']);
            $stmt->bindParam(':email', $userInfo['email']);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->bindParam(':user_id', $userInfo['id']);
            $result = $stmt->execute();

            if ($result) {
                $successCount++;
            } else {
                $errors[] = "Failed to enroll user {$userInfo['email']}.";
            }
        }

        // Update the total enrolled students in the course table
        if ($successCount > 0) {
            $query = 'UPDATE course SET total_enrolled_students = total_enrolled_students + :count WHERE id = :course_id';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':count', $successCount, PDO::PARAM_INT);
            $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        return [
            'error' => !empty($errors),
            'message' => $successCount . ' users enrolled successfully.',
            'errors' => $errors,
        ];
    }
    public function getEnrollmentByCourseId($course_id)
    {
        try {
            $query = '
                SELECT * FROM enrollment
                WHERE course_id = :course_id
            ';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $countQuery = 'SELECT COUNT(*) AS total_count FROM enrollment WHERE course_id = :course_id';
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total_count'];

            return [
                'totalpeople' => $totalCount,
                'people' => $result
            ];
        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'totalpeople' => 0,
                'people' => []
            ];
        }
    }
    public function deleteEnrollmentByUser($course_id, $user_id)
    {
        $query = 'DELETE FROM enrollment WHERE course_id = :course_id AND user_id = :user_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->bindParam(':user_id', $user_id);
        if ($stmt->execute()) {
            $query = 'UPDATE course SET total_enrolled_students = total_enrolled_students - 1 WHERE id = :course_id';
            $stmt = $this->db->prepare($query);

            $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        return true;
    }
    public function deleteEnrollments($course_id, $user_ids)
    {
        $errors = [];
        $successCount = 0;

        foreach ($user_ids as $user_id) {
            $query = 'DELETE FROM enrollment WHERE course_id = :course_id AND user_id = :user_id';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':course_id', $course_id);
            $stmt->bindParam(':user_id', $user_id);
            $result = $stmt->execute();

            if ($result) {
                $successCount++;
            } else {
                $errors[] = "Failed to delete User ID {$user_id}.";
            }
        }

        // Update the total enrolled students in the course table
        if ($successCount > 0) {
            $query = 'UPDATE course SET total_enrolled_students = total_enrolled_students - :count WHERE id = :course_id';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':count', $successCount, PDO::PARAM_INT);
            $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        return [
            'error' => !empty($errors),
            'message' => $successCount . ' users removed successfully.',
            'errors' => $errors,
        ];
    }
    public function editEnrollmentResults($course_id, $data)
    {
        $errors = [];
        $successCount = 0;

        // Extract data fields
        $user_ids = $data->user_id;
        $additional_info = $data->additional_info;
        $completed_date = isset($data->completed_date) ? $data->completed_date : null;

        // Ensure completed_date is valid if provided
        if ($completed_date && !strtotime($completed_date)) {
            return [
                "error" => true,
                "message" => "Invalid completed_date format.",
            ];
        }

        foreach ($user_ids as $user_id) {
            try {
                // Check if the enrollment exists for the given user and course
                $query = "SELECT id FROM enrollment WHERE course_id = :course_id AND user_id = :user_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();

                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    $errors[] = "Enrollment not found for course_id: $course_id and user_id: $user_id.";
                    continue; // Skip to the next user
                }

                // Update the enrollment table
                $updateQuery = "
                UPDATE enrollment
                SET
                    additional_info = :additional_info,
                    completed_date = :completed_date
                WHERE
                    course_id = :course_id AND user_id = :user_id
            ";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindParam(':additional_info', $additional_info, PDO::PARAM_STR);
                $updateStmt->bindParam(':completed_date', $completed_date, PDO::PARAM_STR);
                $updateStmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
                $updateStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

                if ($updateStmt->execute()) {
                    $successCount++;
                } else {
                    $errors[] = "Failed to update enrollment for user_id: $user_id.";
                }
            } catch (Exception $e) {
                $errors[] = "Error for user_id: $user_id - " . $e->getMessage();
            }
        }

        // Return summary of operation
        return [
            "error" => !empty($errors),
            "message" => $successCount . " enrollments updated successfully.",
            "errors" => $errors,
        ];
    }
    public function getInstructorById($instructorId)
    {
        try {
            $instructor_Id = (int) $instructorId;

            $query = "SELECT * FROM person WHERE access_level = 'instructor' AND id = :instructor_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":instructor_id", $instructor_Id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                return [
                    'error' => false,
                    'instructor' => $user,
                ];
            } else {
                return [
                    'error' => true,
                    'message' => "Instructor with ID $instructorId not found.",
                ];
            }
        } catch (PDOException $e) {
            return [
                'error' => true,
                'message' => "Database error: " . $e->getMessage(),
            ];
        }
    }
    public function addInstructorsToCourse($course_id, $instructors)
    {
        try {
            // Ensure the course exists
            $courseQuery = 'SELECT id FROM course WHERE id = :course_id';
            $courseStmt = $this->db->prepare($courseQuery);
            $courseStmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $courseStmt->execute();
            $course = $courseStmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                return [
                    "error" => true,
                    "message" => "Course not found.",
                ];
            }

            // Insert instructors
            $query = 'INSERT INTO course_instructors (course_id, instructor_id) 
                  SELECT :course_id, :instructor_id 
                  FROM DUAL
                  WHERE NOT EXISTS (
                      SELECT 1 FROM course_instructors 
                      WHERE course_id = :course_id AND instructor_id = :instructor_id
                  )';
            $stmt = $this->db->prepare($query);

            foreach ($instructors as $instructor_id) {
                $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
                $stmt->bindParam(':instructor_id', $instructor_id, PDO::PARAM_INT);
                $stmt->execute();
            }

            return [
                "error" => false,
                "message" => "Instructors successfully added to the course.",
            ];
        } catch (PDOException $e) {
            return [
                "error" => true,
                "message" => "Database error: " . $e->getMessage(),
            ];
        }
    }
    public function removeInstructorsFromCourse($course_id, $instructors)
    {
        try {
            // Ensure the course exists
            $courseQuery = 'SELECT id FROM course WHERE id = :course_id';
            $courseStmt = $this->db->prepare($courseQuery);
            $courseStmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
            $courseStmt->execute();
            $course = $courseStmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                return [
                    "error" => true,
                    "message" => "Course not found.",
                ];
            }

            // Prepare delete query
            $deleteQuery = 'DELETE FROM course_instructors 
                            WHERE course_id = :course_id AND instructor_id = :instructor_id';
            $deleteStmt = $this->db->prepare($deleteQuery);

            // Iterate through instructor IDs and delete them
            $errors = [];
            foreach ($instructors->ids as $instructor_id) {
                $deleteStmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
                $deleteStmt->bindParam(':instructor_id', $instructor_id, PDO::PARAM_INT);

                if (!$deleteStmt->execute()) {
                    $errors[] = "Failed to remove instructor with ID {$instructor_id}.";
                }
            }

            // Return success or error response
            if (empty($errors)) {
                return [
                    "error" => false,
                    "message" => "Instructors successfully removed from the course.",
                ];
            } else {
                return [
                    "error" => true,
                    "message" => "Some instructors could not be removed.",
                    "errors" => $errors,
                ];
            }
        } catch (PDOException $e) {
            return [
                "error" => true,
                "message" => "Database error: " . $e->getMessage(),
            ];
        }
    }
    public function addNotice($course_id, $data, $creater_id)
    {
        // Correcting the query to include the `expiry_date` placeholder in the values
        $query = 'INSERT INTO noticeboard (course_id, title, content, posted_by, expiry_date) 
                  VALUES (:course_id, :title, :content, :posted_by, :expiry_date)';

        // Prepare the statement
        $insertStmt = $this->db->prepare($query);

        // Bind the parameters
        $insertStmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
        $insertStmt->bindParam(':title', $data['title'], PDO::PARAM_STR);
        $insertStmt->bindParam(':content', $data['content'], PDO::PARAM_STR);
        $insertStmt->bindParam(':posted_by', $creater_id, PDO::PARAM_INT); // Make sure it's bound as an INT
        $insertStmt->bindParam(':expiry_date', $data['expiry_date']); // Bind expiry_date

        // Execute the query
        $insertStmt->execute();

        // Get the inserted noticeboard ID
        $noticeBoardId = $this->db->lastInsertId();

        // Check if a file path is provided
        if (!empty($data['file_path'])) {
            // If file path exists, insert into attachments table
            $query = 'INSERT INTO attachments (noticeboard_id, file_path) VALUES (:noticeboard_id, :file_path)';
            $insertStmt = $this->db->prepare($query);

            // Bind the parameters
            $insertStmt->bindParam(':noticeboard_id', $noticeBoardId, PDO::PARAM_INT);
            $insertStmt->bindParam(':file_path', $data['file_path'], PDO::PARAM_STR);

            // Execute the query to insert attachment
            $insertStmt->execute();
        }

        return true;
    }
}
