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
        $last_id = $this->db->lastInsertId();

        if ($last_id) {
            if ($data['module_type'] === 'assessment') {
                $questions = $data['questions'];
                $totalMarks = 0;

                foreach ($questions as $question) {
                    $query = 'INSERT INTO assessment_modules (module_id, assessment_type, question_text, options, correct_answer) 
                              VALUES (:module_id, :assessment_type, :question_text, :options, :correct_answer)';
                    $stmt = $this->db->prepare($query);

                    // Assign values to variables first
                    $module_id = $last_id;
                    $assessment_type = $data['assessment_type'];
                    $question_text = $question['question_text'];
                    $options = json_encode($question['options']);
                    $correct_answer = json_encode($question['correct_answer']);

                    // Bind variables to parameters
                    $stmt->bindParam(':module_id', $module_id);
                    $stmt->bindParam(':assessment_type', $assessment_type); // Now using a variable
                    $stmt->bindParam(':question_text', $question_text);
                    $stmt->bindParam(':options', $options);
                    $stmt->bindParam(':correct_answer', $correct_answer);

                    $stmt->execute();

                    // Increment total marks
                    $totalMarks += $question['marks'];
                }

                // Calculate pass marks (e.g., 50% of total marks)
                $passMarks = ceil($totalMarks * $data['pass_ratio']); // Assuming 50% as the passing criteria

                // Update the assessment_modules table with total and pass marks
                $updateQuery = 'UPDATE assessment_modules SET total_marks = :total_marks, pass_marks = :pass_marks WHERE module_id = :module_id';
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindParam(':total_marks', $totalMarks, PDO::PARAM_INT);
                $updateStmt->bindParam(':pass_marks', $passMarks, PDO::PARAM_INT);
                $updateStmt->bindParam(':module_id', $last_id, PDO::PARAM_INT);
                $updateStmt->execute();
            }
            if ($data['module_type'] === 'scorm') {
                $query = 'INSERT INTO scorm_modules (module_id , file_url) VALUES (:module_id,:file_url)';
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":module_id", $last_id);
                $stmt->bindParam(":file_url", $data['file_url']);
                $stmt->execute();
                return true;
            }
        }

        return [
            'error' => false,
            'message' => 'Module and assessment added successfully',
            'module_id' => $last_id
        ];
    }
    public function getScormFile($module_id)
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

        // If the file exists, proceed with the logic
        // return json_encode(["status" => "success", "message" => "SCORM index.html found.", "path" => $indexHtmlPath]);



        // Generate the SCORM URL for the extracted index.html
        $scormUrl = "http://localhost/lsmBackend/scorm_files/extracted_$module_id/" . urlencode(basename(dirname($indexHtmlPath)));

        // Update the module completion status in the database
        $updateQuery = "UPDATE module_completion SET status= 'in_progress' WHERE module_id = :module_id";
        $updateStmt = $this->db->prepare($updateQuery);
        $updateStmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
        $updateStmt->execute();

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


    public function getModuleByIdAdmin($module_id)
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
            $query = 'SELECT * FROM assessment_modules WHERE module_id = :module_id';
            $query = $this->db->prepare($query);
            $query->bindParam(':module_id', $module_id);
            $query->execute();
            $assessments = $query->fetchAll(PDO::FETCH_ASSOC);

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
    public function getModuleByIdForUser($module_id, $user_id)
    {
        // Fetch module details
        $query = 'SELECT m.id, m.module_type, m.title, m.description, m.active, m.created_at, m.updated_at, m.course_id, m.mandatory, mc.status, mc.completed_at
                  FROM modules m
                  LEFT JOIN module_completion mc ON mc.module_id = m.id AND mc.user_id = :user_id
                  WHERE m.id = :module_id';
        $query = $this->db->prepare($query);
        $query->bindParam(':module_id', $module_id);
        $query->bindParam(':user_id', $user_id);
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
            $query = 'SELECT * FROM assessment_modules WHERE module_id = :module_id';
            $query = $this->db->prepare($query);
            $query->bindParam(':module_id', $module_id);
            $query->execute();
            $assessments = $query->fetchAll(PDO::FETCH_ASSOC);

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

        // You can add more conditions for other module types as needed

        return [
            'status' => 200,
            'msg' => 'Modules fetch successfully',
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
        $query = 'UPDATE assessment_modules SET question_text=:question_text , options=:options , correct_answer=:correct_answer WHERE id:question_id';
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
            return $stmt->execute(); // Return execution result
        } else {
            return false; // If module was never started or already completed
        }
    }
    public function assessmentAnswer($user_id, $module_id, $answers, $courseId)
    {
        foreach ($answers as $answer) {
            $question_id = $answer['question_id'];
            $user_answer = trim(strtolower($answer['answer'])); // Normalize user input

            // Check if the user has already submitted an answer for this question
            $alreadyExistQuery = 'SELECT id FROM assessment_answers WHERE user_id = :user_id AND question_id = :question_id';
            $alreadyExistStmt = $this->db->prepare($alreadyExistQuery);
            $alreadyExistStmt->bindParam(":user_id", $user_id);
            $alreadyExistStmt->bindParam(":question_id", $question_id);
            $alreadyExistStmt->execute();
            $result = $alreadyExistStmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Skip this answer instead of returning early
                continue;
            }

            // Fetch the correct answer from the assessment_modules table
            $query = 'SELECT correct_answer FROM assessment_modules WHERE id = :question_id';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":question_id", $question_id);
            $stmt->execute();
            $question = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($question) {
                // Decode the JSON stored correct answers
                $correct_answers = json_decode($question['correct_answer'], true);

                // Normalize correct answers for comparison
                $normalized_correct_answers = array_map('strtolower', array_map('trim', (array) $correct_answers));

                // Check if user answer matches any correct answer
                $is_correct = in_array($user_answer, $normalized_correct_answers) ? 1 : 0;

                // Insert the answer into assessment_answers table
                $insertQuery = 'INSERT INTO assessment_answers (user_id, module_id, question_id, user_answer, is_correct) 
                                VALUES (:user_id, :module_id, :question_id, :user_answer, :is_correct)';
                $insertStmt = $this->db->prepare($insertQuery);
                $insertStmt->bindParam(":user_id", $user_id);
                $insertStmt->bindParam(":module_id", $module_id);
                $insertStmt->bindParam(":question_id", $question_id);
                $insertStmt->bindParam(":user_answer", $answer['answer']); // Store original user answer
                $insertStmt->bindParam(":is_correct", $is_correct);

                if (!$insertStmt->execute()) {
                    return [
                        'error' => true,
                        'status' => 400,
                        'message' => 'Failed to insert answer for question ID ' . $question_id
                    ];
                }
            }
        }

        // Once all answers are processed, check module completion
        return $this->checkCompletion($user_id, $module_id, $courseId);
    }
    public function checkCompletion($user_id, $module_id, $courseId)
    {
        // Retrieve the total number of questions, correct answers, total marks, and pass marks for the module
        $query = "
            SELECT 
                COUNT(*) AS total_questions, 
                SUM(is_correct) AS correct_answers, 
                SUM(am.total_marks) AS total_marks, 
                am.pass_marks             
            FROM 
                assessment_answers aa
            JOIN 
                assessment_modules am ON aa.question_id = am.id
            WHERE 
                aa.user_id = :user_id AND aa.module_id = :module_id
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':module_id', $module_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return [
                'status' => 400,
                'msg' => 'Failed to retrieve assessment data.'
            ];
        }

        // Retrieve total questions, correct answers, total marks, and pass marks
        $total_questions = $result['total_questions'];
        $correct_answers = $result['correct_answers'] ?? 0; // Handle null values
        $total_marks = $result['total_marks'] ?? 0;
        $pass_marks = $result['pass_marks'];

        // If no questions are answered, return failure
        if ($total_questions == 0) {
            return [
                'status' => 400,
                'msg' => 'No questions answered. Module cannot be completed.'
            ];
        }

        // Calculate the score percentage
        $score = ($correct_answers / $total_questions) * 100;
        $final_score = ($score / 100) * $total_marks;
        $status = ($final_score >= $pass_marks) ? 'Completed' : 'Failed';

        // Insert or update the module completion status
        $update_query = "
            INSERT INTO module_completion (user_id, module_id,course_id, status, completed_at) 
            VALUES (:user_id, :module_id,:course_id, :status, NOW()) 
            ON DUPLICATE KEY UPDATE status = :status, completed_at = NOW()
        ";
        $stmt = $this->db->prepare($update_query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':module_id', $module_id);
        $stmt->bindParam(':course_id', $courseId);
        $stmt->execute();

        return [
            'status' => 200,
            'msg' => 'Module completion status updated successfully.',
            'score' => $score,
            'final_score' => $final_score,
        ];
    }
}
