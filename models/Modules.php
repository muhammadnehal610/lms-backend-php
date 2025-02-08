<?php

class Modules
{
    private $db;
    public function __construct($db)
    {
        $this->db = $db;
    }
    public function addModule($data, $course_id)
    {
        // Insert module details into the modules table
        $query = 'INSERT INTO modules (title, description, course_id, module_type) VALUES (:title, :description, :course_id, :module_type)';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->bindParam(':module_type', $data['module_type']);
        $stmt->execute();
        $module_id = $this->db->lastInsertId();

        if ($module_id) {
            if ($data['module_type'] === 'assessment') {
                $questions = $data['questions'];
                $totalMarks = 0;

                foreach ($questions as $question) {
                    $totalMarks += $question['marks'];
                }

                $query = 'INSERT INTO assessment_module (module_id , total_marks , pass_ratio) 
                               VALUES (:module_id , :total_marks , :pass_ratio)';
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":module_id", $module_id);
                $stmt->bindParam(":total_marks", $totalMarks);
                $stmt->bindParam(":pass_ratio", $data['pass_ratio']);
                $stmt->execute();
                $assessment_module_id = $this->db->lastInsertId();
                foreach ($questions as $question) {


                    $query = 'INSERT INTO assessment_questions (assessment_module_id, question_text, options, correct_answer , mark) 
                               VALUES (:assessment_module_id, :question_text, :options, :correct_answer , :mark)';
                    $stmt = $this->db->prepare($query);

                    $question_text = $question['question_text'];
                    $options = json_encode($question['options']);
                    $correct_answer = json_encode($question['correct_answer']);
                    $mark = $question['marks'];

                    // Bind variables to parameters
                    $stmt->bindParam(':assessment_module_id', $assessment_module_id);
                    $stmt->bindParam(':question_text', $question_text);
                    $stmt->bindParam(':options', $options);
                    $stmt->bindParam(':correct_answer', $correct_answer);
                    $stmt->bindParam(':mark', $mark);

                    $stmt->execute();
                }
            }
            if ($data['module_type'] === 'scorm') {
                $query = 'INSERT INTO scorm_modules (module_id , file_url) VALUES (:module_id,:file_url)';
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":module_id", $module_id);
                $stmt->bindParam(":file_url", $data['file_url']);
                $stmt->execute();
                return true;
            }
        }

        return [
            'error' => false,
            'message' => 'Module and assessment added successfully'
        ];
    }
    public function getScormFile($module_id, $courseId, $userId)
    {
        // Query to get the SCORM module details from the database
        $query = "SELECT * FROM scorm_modules WHERE module_id = :module_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
        $stmt->execute();

        // Fetch the result
        $scormData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if the SCORM module exists and has a valid file URL
        if (!$scormData || empty($scormData['file_url'])) {
            return json_encode(["status" => "error", "message" => "SCORM module or file URL not found."]);
        }

        // Get the file URL from the database
        $fileUrl = $scormData['file_url'];

        // Define the root directory for the SCORM files
        $rootDir = $_SERVER['DOCUMENT_ROOT'] . '/lsmBackend/scorm_files/';

        // Ensure the SCORM files directory exists
        if (!file_exists($rootDir)) {
            if (!mkdir($rootDir, 0777, true)) {
                return json_encode(["status" => "error", "message" => "Failed to create SCORM files directory."]);
            }
        }

        // Define the path where the ZIP file will be saved
        $zipFilePath = $rootDir . "scorm_$module_id.zip";

        // Download the SCORM file from the URL and save it to the server
        $fileContent = file_get_contents($fileUrl);
        if ($fileContent === false) {
            return json_encode(["status" => "error", "message" => "Failed to fetch the SCORM file from the URL."]);
        }

        // Save the fetched file
        if (file_put_contents($zipFilePath, $fileContent) === false) {
            return json_encode(["status" => "error", "message" => "Failed to save the SCORM file to the server."]);
        }

        // Define the extraction folder path
        $extractPath = $rootDir . "extracted_$module_id/";

        // Create the extraction folder if it doesn't exist
        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0777, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            return json_encode(["status" => "error", "message" => "Unable to open or extract the ZIP file."]);
        }

        // Ensure there is no trailing slash in $extractPath
        $extractPath = rtrim($extractPath, '/');

        // Correct path to `index.html` without encoding, space is directly used
        $indexHtmlPath = $extractPath . '/new peoject/index.html';  // Folder and file directly

        // Debugging: Check if the file exists using is_file()
        if (!is_file($indexHtmlPath)) {
            return ["status" => "error", "message" => "SCORM index.html not found. Path: " . $indexHtmlPath];
        }
        $scormUrl = "http://localhost/lsmBackend/scorm_files/extracted_$module_id/" . urlencode(basename(dirname($indexHtmlPath)));

        // Update the module completion status in the database
        $this->startModule($userId, $module_id, $courseId);

        // Return success response with SCORM URL
        return [
            "status" => "success",
            "message" => "SCORM file fetched, saved, and extracted successfully.",
            "index_url" => $scormUrl
        ];
    }
    public  function findEntryPoint($dir)
    {
        $possibleFiles = ['index.html', 'index.htm', 'start.html'];
        foreach ($possibleFiles as $file) {
            if (file_exists($dir . DIRECTORY_SEPARATOR . $file)) {
                return $dir . DIRECTORY_SEPARATOR . $file;
            }
        }
        return false;
    }
    public function getModuleById($module_id)
    {
        // Fetch module details for the admin (without user-specific filtering)
        $query = 'SELECT m.id, m.module_type, m.title, m.description, m.active, m.created_at, m.updated_at, m.course_id, m.mandatory
              FROM modules m
              WHERE m.id = :module_id';
        $query = $this->db->prepare($query);
        $query->bindParam(':module_id', $module_id);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        // Check if the module was found
        if (!$result) {
            return [
                'status' => 404,
                'msg' => 'Module not found',
                'data' => null
            ];
        }

        // Handle assessments if module type is 'assessment'
        if ($result['module_type'] === 'assessment') {
            $query = 'SELECT * FROM assessment_module WHERE module_id = :module_id';
            $query = $this->db->prepare($query);
            $query->bindParam(':module_id', $module_id);
            $query->execute();
            $assessments = $query->fetch(PDO::FETCH_ASSOC);
            $assessment_module_id = $assessments['id'];
            $query = 'SELECT * 
                        FROM assessment_questions a_question
                        WHERE assessment_module_id = :assessment_module_id';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":assessment_module_id", $assessment_module_id);
            $stmt->execute();
            $assessment_question = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $assessments['questions'] = $assessment_question;

            // Check if assessments were found
            if ($assessments !== false) {
                $result['assessments'] = $assessments;
            } else {
                $result['assessments'] = [];
            }
        }

        // Handle learner uploads if module type is 'learner_upload'
        if ($result['module_type'] === 'learner_upload') {
            // Add logic to fetch learner upload data
            $query = 'SELECT * FROM learner_uploads WHERE module_id = :module_id';
            $query = $this->db->prepare($query);
            $query->bindParam(':module_id', $module_id);
            $query->execute();
            $uploads = $query->fetchAll(PDO::FETCH_ASSOC);

            // Check if uploads were found
            if ($uploads !== false) {
                $result['uploads'] = $uploads;
            } else {
                $result['uploads'] = [];
            }
        }

        // Handle surveys if module type is 'survey'
        if ($result['module_type'] === 'survey') {
            // Add logic to fetch survey data
            $query = 'SELECT * FROM surveys WHERE module_id = :module_id';
            $query = $this->db->prepare($query);
            $query->bindParam(':module_id', $module_id);
            $query->execute();
            $survey = $query->fetch(PDO::FETCH_ASSOC);

            // Check if survey was found
            if ($survey !== false) {
                $result['survey'] = $survey;
            } else {
                $result['survey'] = null;
            }
        }

        return [
            'status' => 200,
            'msg' => 'Module fetched successfully',
            'data' => $result
        ];
    }

    public function changeModuleMandatory($moduleId, $mandatory)
    {
        $mandatoryValue = ($mandatory === 'true') ? 1 : 0;
        $query = 'UPDATE modules SET mandatory=:mandatory  WHERE id=:module_id';
        $query = $this->db->prepare($query);
        $query->bindParam(':mandatory', $mandatoryValue);
        $query->bindParam(':module_id', $moduleId);
        return $query->execute();
    }
    public function deleteModuleById($moduleId)
    {
        $query = 'DELETE FROM modules WHERE id=:module_id';
        $query = $this->db->prepare($query);
        $query->bindParam(':module_id', $moduleId);
        return $query->execute();
    }
    public function updateModule($moduleId, $data)
    {
        $query = 'UPDATE modules SET title=:title , description=:description   WHERE id=:module_id';
        $query = $this->db->prepare($query);
        $query->bindParam(':title', $data->title);
        $query->bindParam(':description', $data->description);
        $query->bindParam(':module_id', $moduleId);
        return $query->execute();
    }
    public function updateModuleQuestion($question_id, $data)
    {
        $query = 'UPDATE assessment_questions SET question_text=:question_text , options=:options , correct_answer=:correct_answer WHERE id:question_id';
        $query = $this->db->prepare($query);
        $query->bindParam(':question_text', $data['question_text']);
        $query->bindParam(':options', $data['options']);
        $query->bindParam(':correct_answer', $data['correct_answer']);
        $query->bindParam(':question_id', $question_id);
        return $query->execute();
    }
    public function areAllModulesComplete($course_id, $user_id)
    {
        // Get the total number of modules for the course
        $query = 'SELECT COUNT(*) AS total_modules 
              FROM modules 
              WHERE course_id = :course_id';

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
        $stmt->execute();
        $totalModules = $stmt->fetch(PDO::FETCH_ASSOC)['total_modules'];

        // Get the total number of completed modules for the user in the course
        $query = 'SELECT COUNT(*) AS completed_modules 
              FROM module_completion 
              WHERE course_id = :course_id 
              AND user_id = :user_id 
              AND status = "Completed"';

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $completedModules = $stmt->fetch(PDO::FETCH_ASSOC)['completed_modules'];

        // Check if all modules are completed
        return $totalModules > 0 && $totalModules === $completedModules;
    }
    public function startModule($userId, $moduleId, $courseId)
    {
        // Check if the user already has the module record (any status)
        $moduleQuery = 'SELECT id, status FROM module_completion 
                        WHERE user_id = :user_id 
                        AND module_id = :module_id 
                        AND course_id = :course_id';

        $modulestmt = $this->db->prepare($moduleQuery);
        $modulestmt->bindParam(":user_id", $userId);
        $modulestmt->bindParam(":module_id", $moduleId);
        $modulestmt->bindParam(":course_id", $courseId);
        $modulestmt->execute();

        $moduleExist = $modulestmt->fetch(PDO::FETCH_ASSOC);

        if ($moduleExist) {
            // If record exists but was deleted or completed, update it
            $query = "UPDATE module_completion 
                      SET status = 'In Progress'
                      WHERE id = :id";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":id", $moduleExist['id']);
        } else {
            // If no record exists, insert new entry
            $query = "INSERT INTO module_completion (user_id, module_id, course_id, status) 
                      VALUES (:user_id, :module_id, :course_id, 'In Progress')";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":user_id", $userId);
            $stmt->bindParam(":module_id", $moduleId);
            $stmt->bindParam(":course_id", $courseId);
        }

        return $stmt->execute(); // Execute the correct query
    }
    public function completeModule($userId, $moduleId)
    {
        // Ensure the module exists and is "In Progress" before completing it
        $checkQuery = "SELECT id FROM module_completion 
                       WHERE user_id = :user_id 
                       AND module_id = :module_id 
                       AND status = 'In Progress'";

        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(':user_id', $userId);
        $checkStmt->bindParam(':module_id', $moduleId);
        $checkStmt->execute();

        $moduleExist = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($moduleExist) {
            // Update only if it exists and is "In Progress"
            $query = "UPDATE module_completion 
                      SET status = 'Completed', completed_at = NOW() 
                      WHERE id = :id";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $moduleExist['id']);
            $result = $stmt->execute();
            $this->addAchievement($userId, $moduleId);
            return $result;
        } else {
            return false; // If module was never started or already completed
        }
    }
    public function addAchievement($user_id, $module_id)
    {
        // Check if achievement already exists
        $query = "SELECT * FROM achievements WHERE user_id = :user_id AND module_id = :module_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':module_id', $module_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return json_encode(["status" => "error", "message" => "Achievement already earned."]);
        }
        $query = 'SELECT id FROM module_completion WHERE user_id=:user_id AND  module_id = :module_id AND status="Completed"';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":module_id", $module_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            $query = "INSERT INTO achievements (user_id, module_id) VALUES (:user_id, :module_id)";
        }
        // Insert new achievement
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':module_id', $module_id);

        if ($stmt->execute()) {
            return json_encode(["status" => "success", "message" => "Achievement added successfully."]);
        } else {
            return json_encode(["status" => "error", "message" => "Failed to add achievement."]);
        }
    }
    public function getAchievement($userId)
    {
        // Query to get the total achievements count
        $queryTotal = 'SELECT COUNT(*) as total_achievement 
                       FROM achievements a 
                       WHERE a.user_id = :user_id';

        $stmtTotal = $this->db->prepare($queryTotal);
        $stmtTotal->bindParam(":user_id", $userId, PDO::PARAM_INT);
        $stmtTotal->execute();
        $totalResult = $stmtTotal->fetch(PDO::FETCH_ASSOC);

        // Query to get the module titles only (without achievements count)
        $queryModules = 'SELECT m.title 
                         FROM modules m 
                         JOIN achievements a ON a.module_id = m.id 
                         WHERE a.user_id = :user_id 
                         GROUP BY m.id, m.title';

        $stmtModules = $this->db->prepare($queryModules);
        $stmtModules->bindParam(":user_id", $userId, PDO::PARAM_INT);
        $stmtModules->execute();
        $modulesResult = $stmtModules->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_achievement' => $totalResult['total_achievement'],
            'achievements' => $modulesResult
        ];
    }
    public function assessmentAnswer($user_id, $module_id, $answers, $courseId)
    {
        // Verify if the assessment module exists
        $query = 'SELECT id, total_marks, pass_ratio FROM assessment_module WHERE module_id = :module_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":module_id", $module_id, PDO::PARAM_INT);
        $stmt->execute();
        $module_result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$module_result) {
            return [
                'error' => true,
                'status' => 404,
                'message' => 'Invalid module ID. Assessment module does not exist.'
            ];
        }

        $assessment_module_id = $module_result['id'];
        $total_marks = (int) $module_result['total_marks'];
        $pass_ratio = (float) $module_result['pass_ratio'];

        $user_score = 0; // Track user earned marks

        foreach ($answers as $answer) {
            $question_id = $answer['question_id'];
            $user_answer = trim(strtolower($answer['answer'] ?? ''));

            // Validate that the question exists and get the correct answer and marks
            $query = 'SELECT correct_answer, mark FROM assessment_questions WHERE id = :question_id';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
            $stmt->execute();
            $question = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$question) {
                continue; // Skip invalid questions
            }

            $correct_answers = json_decode($question['correct_answer'], true);
            $normalized_correct_answers = array_map('strtolower', array_map('trim', (array) $correct_answers));

            // Determine if the answer is correct
            $is_correct = in_array($user_answer, $normalized_correct_answers) ? 1 : 0;

            // If the answer is correct, add marks
            if ($is_correct) {
                $user_score += (int) $question['mark'];
            }

            // Check if the user has already answered this question
            $alreadyExistQuery = 'SELECT id FROM assessment_answers WHERE user_id = :user_id AND question_id = :question_id';
            $alreadyExistStmt = $this->db->prepare($alreadyExistQuery);
            $alreadyExistStmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $alreadyExistStmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
            $alreadyExistStmt->execute();
            $result = $alreadyExistStmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                continue; // Skip if already answered
            }

            // Insert the answer into assessment_answers table
            $insertQuery = 'INSERT INTO assessment_answers (user_id, assessment_module_id, question_id, user_answer, is_correct) 
                            VALUES (:user_id, :assessment_module_id, :question_id, :user_answer, :is_correct)';
            $insertStmt = $this->db->prepare($insertQuery);
            $insertStmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $insertStmt->bindParam(":assessment_module_id", $assessment_module_id, PDO::PARAM_INT);
            $insertStmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
            $insertStmt->bindParam(":user_answer", $user_answer, PDO::PARAM_STR);
            $insertStmt->bindParam(":is_correct", $is_correct, PDO::PARAM_INT);

            if (!$insertStmt->execute()) {
                return [
                    'error' => true,
                    'status' => 400,
                    'message' => 'Failed to insert answer for question ID ' . $question_id
                ];
            }
        }

        // Calculate passing marks based on pass ratio
        $passing_marks = ($total_marks * $pass_ratio) / 100;
        $status = ($user_score >= $passing_marks) ? 'Completed' : 'Failed';

        // Check if user already has a record in assessment_module_submission
        $checkSubmissionQuery = 'SELECT id FROM assessment_module_submision WHERE user_id = :user_id AND assessment_module_id = :assessment_module_id';
        $checkStmt = $this->db->prepare($checkSubmissionQuery);
        $checkStmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $checkStmt->bindParam(":assessment_module_id", $assessment_module_id, PDO::PARAM_INT);
        $checkStmt->execute();
        $existingSubmission = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingSubmission) {
            // Update existing submission
            $updateQuery = 'UPDATE assessment_module_submision SET earn_marks = :earn_marks WHERE user_id = :user_id AND assessment_module_id = :assessment_module_id';
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(":earn_marks", $user_score, PDO::PARAM_INT);
            $updateStmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $updateStmt->bindParam(":assessment_module_id", $assessment_module_id, PDO::PARAM_INT);
            $updateStmt->execute();
        } else {
            // Insert new record in assessment_module_submission
            $insertSubmissionQuery = 'INSERT INTO assessment_module_submision (user_id, assessment_module_id, earn_marks) 
                                      VALUES (:user_id, :assessment_module_id, :earn_marks)';
            $insertSubmissionStmt = $this->db->prepare($insertSubmissionQuery);
            $insertSubmissionStmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $insertSubmissionStmt->bindParam(":assessment_module_id", $assessment_module_id, PDO::PARAM_INT);
            $insertSubmissionStmt->bindParam(":earn_marks", $user_score, PDO::PARAM_INT);
            $insertSubmissionStmt->execute();
        }

        // Call completion check after processing all answers
        $this->checkCompletion($user_id, $module_id, $courseId, $status);
        $this->addAchievement($user_id, $module_id);

        return [
            'error' => false,
            'status' => 200,
            'message' => 'Answers submitted successfully, Total Marks: ' . $user_score . ', Result: ' . $status
        ];
    }
    public function checkCompletion($user_id, $module_id, $courseId, $status)
    {
        try {
            // Check if module completion already exists
            $checkQuery = "SELECT id FROM module_completion WHERE user_id = :user_id AND module_id = :module_id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $checkStmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $checkStmt->execute();
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing record
                $updateQuery = "UPDATE module_completion SET status = :status, completed_at = NOW() 
                            WHERE user_id = :user_id AND module_id = :module_id";
                $updateStmt = $this->db->prepare($updateQuery);
            } else {
                // Insert new record
                $updateQuery = "INSERT INTO module_completion (user_id, module_id, course_id, status, completed_at) 
                            VALUES (:user_id, :module_id, :course_id, :status, NOW())";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindParam(':course_id', $courseId, PDO::PARAM_INT);
            }

            $updateStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $updateStmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $updateStmt->bindParam(':status', $status, PDO::PARAM_STR);
            $updateStmt->execute();

            return [
                'status' => 200,
                'msg' => 'Module completion status updated successfully.',
                'status_text' => $status
            ];
        } catch (PDOException $e) {
            return [
                'status' => 500,
                'msg' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
}
