<?php

class LearningPaths
{
    public $db;
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function addLearningPaths($data)
    {
        $query = 'INSERT INTO learning_paths (name , description , image_url) VALUES (:name , :description , :image_url)';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":name", $data['name']);
        $stmt->bindParam(":description", $data['description']);
        $stmt->bindParam(":image_url", $data['image']);
        $stmt->execute();
        $learningPathId = $this->db->lastInsertId();

        if ($learningPathId) {
            $courses = $data['courses'];
            foreach ($courses as $sequence => $course) {
                $query = 'SELECT id FROM learning_path_courses 
                          WHERE course_id = :course_id AND learning_path_id = :learning_path_id';
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":course_id", $course);
                $stmt->bindParam(":learning_path_id", $learningPathId);
                $stmt->execute();

                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    return [
                        'error' => true,
                        'status' => 400,
                        'message' => "Course already inserted in this learning path"
                    ];
                }

                $insertQuery = 'INSERT INTO learning_path_courses (course_id, learning_path_id, sequence) 
                                VALUES (:course_id, :learning_path_id, :sequence)';
                $courseInsert = $this->db->prepare($insertQuery);
                $courseInsert->bindParam(":course_id", $course);
                $courseInsert->bindParam(":learning_path_id", $learningPathId);
                $courseInsert->bindParam(":sequence", $sequence);
                $courseInsert->execute();
            }

            // ✅ Move return outside the loop to ensure all courses are inserted
            return [
                'error' => false,
                'status' => 200,
                'message' => "All courses inserted in learning path successfully"
            ];
        }
    }
    public function getLearningPaths()
    {
        $query = 'SELECT * FROM learning_paths';
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getLearningPathById($learning_path_id)
    {
        $query = 'SELECT lp.*, 
                         GROUP_CONCAT(c.id ORDER BY lpc.sequence) AS course_ids 
                  FROM learning_paths lp
                  LEFT JOIN learning_path_courses lpc ON lp.id = lpc.learning_path_id
                  LEFT JOIN course c ON lpc.course_id = c.id
                  WHERE lp.id = :learning_path_id
                  GROUP BY lp.id';

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":learning_path_id", $learning_path_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $coursesIds = explode(',', $result['course_ids']);
            foreach ($coursesIds as $courseId) {
                $query = 'SELECT * FROM course WHERE id=:course_id';
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":course_id", $courseId);
                $stmt->execute();
                $courses[] = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            $result['courses'] = $courses;
        }

        return [
            'status' => 200,
            'message' => 'Learning path fetched successfully',
            'data' => $result
        ];
    }
    public function enrolledLearningPathByUser($learning_path_id, $user_id)
    {
        $query = 'SELECT id FROM user_learning_path_progress WHERE user_id = :user_id AND learning_path_id = :learning_path_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":learning_path_id", $learning_path_id);
        $stmt->execute();
    
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return [
                'error' => true,
                'status' => 400,
                'message' => "User already enrolled in this learning path"
            ];
        }
        $enrolledLearningPath = 'INSERT INTO user_learning_path_progress (learning_path_id, user_id, status)  VALUES (:learning_path_id, :user_id, "Not Started")';
        $learningPathStmt = $this->db->prepare($enrolledLearningPath);
        $learningPathStmt->bindParam(":learning_path_id", $learning_path_id);
        $learningPathStmt->bindParam(":user_id", $user_id);
        $learningPathStmt->execute();

        $getCoursesQuery = 'SELECT course_id FROM learning_path_courses WHERE learning_path_id = :learning_path_id ORDER BY sequence';
        $getCoursesStmt = $this->db->prepare($getCoursesQuery);
        $getCoursesStmt->bindParam(":learning_path_id", $learning_path_id);
        $getCoursesStmt->execute();
        
        $coursesIds = $getCoursesStmt->fetchAll(PDO::FETCH_ASSOC);

        // ✅ Extract only course_id values from the result
        $courses = array_column($coursesIds, 'course_id'); 
    
        if (empty($courses)) {
            return [
                'error' => true,
                'status' => 400,
                'message' => "No courses found in the learning path"
            ];
        }

        // ✅ Fetch user details
        $query = 'SELECT first_name, email FROM person WHERE id = :user_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$user) {
            return [
                'error' => true,
                'status' => 400,
                'message' => "User not found"
            ];
        }
    
        // ✅ Enroll user in all courses using a loop
        $query = 'INSERT INTO enrollment (name, email, course_id, user_id) 
                  VALUES (:name, :email, :course_id, :user_id)';
        $stmt = $this->db->prepare($query);
    
        foreach ($courses as $course_id) {
            $stmt->bindParam(":name", $user['first_name']);
            $stmt->bindParam(":email", $user['email']);
            $stmt->bindParam(":course_id", $course_id);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();
        }
        return [
            'error' => false,
            'status' => 200,
            'message' => "User successfully enrolled in the learning path and all courses"
        ];
    }
   
}
