<?php
ob_start();
// Include necessary files
include_once __DIR__ . '/config/database.php';
include_once __DIR__ . '/models/User.php';
include_once __DIR__ . '/models/Course.php';
include_once __DIR__ . '/controllers/AuthController.php';
include_once __DIR__ . '/controllers/CourseController.php';
include_once __DIR__ . '/controllers/EnrollmentController.php';
include_once __DIR__ . '/controllers/TeamController.php';
include_once __DIR__ . '/controllers/BulkimportController.php';
include_once __DIR__ . '/controllers/ModuleController.php';
include_once __DIR__ . '/controllers/ReportController.php';
include_once __DIR__ . '/controllers/LearningPathController.php';
include_once __DIR__ . '/controllers/SessionsController.php';
require_once __DIR__ . '/controllers/PeopleController.php';
include_once __DIR__ . '/middlewere/AuthMiddlewere.php';
include_once __DIR__ . '/helper/Cloudnery.php';
require_once __DIR__ . '/vendor/autoload.php'; // For JWT

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get the request URI and method
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Set CORS headers
header("Access-Control-Allow-Origin: *"); // Allow any origin
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS"); // Allow specific HTTP methods
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Allow content type and authorization headers

// Handle preflight requests for OPTIONS method
if ($method === 'OPTIONS') {
    // Preflight response, so exit early
    exit;
}

// Function to handle routing
function route($uri, $method, $db)
{
    $jwtSecret = 'iaawk';
    // Instantiate controllers
    $authController = new AuthController($db);
    $courseController = new CourseController($db);
    $enrollmentController = new EnrollmentController($db);
    $teamController = new TeamController($db);
    $bulkController = new BulkImportController($db);
    $moduleController = new ModuleController($db);
    $reportController = new ReportController($db);
    $learningPathController = new LearningPathController($db);
    $sessionsController = new SessionsController($db);
    $peopleController = new PeopleController($db);
    // Handle auth routes
    if ($uri === '/lsmBackend/api/auth/register' && $method === 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        return $authController->register($data);
    }
    if ($uri === '/lsmBackend/api/auth/login' && $method === 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        return $authController->login($data);
    }
    if ($uri === '/lsmBackend/api/auth/logout' && $method === 'POST') {
        return $authController->logout();
    }
    if ($uri === '/lsmBackend/api/auth/forget-password' && $method === 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        return $authController->forgetPassword($data);
    }
    if($uri==='/lsmBackend/api/auth/enum' && $method === 'GET'){
        return $authController->getEnumValuesUserTable();
    }

    if ($uri === '/lsmBackend/api/addperson' && $method === 'POST') {
        $authMiddlewere = new AuthMiddleware($jwtSecret);
        $authMiddlewere->handleAdmin();
        $data = json_decode(file_get_contents('php://input'));
        $result = $authController->addPerson($data);
        return $result;
    }
    if ($uri === '/lsmBackend/api/addbulkperson' && $method === 'POST') {
        $authMiddlewere = new AuthMiddleware($jwtSecret);
        $authMiddlewere->handleAdmin();
        $data = $_FILES['file'];
        $result = $bulkController->importUsers($data);
        return $result;
    }
    // Handle course routes
    if ($uri === '/lsmBackend/api/courses' && $method === 'GET') {
        return $courseController->getAllCourses();
    }
    if ($uri === '/lsmBackend/api/users/instructor' && $method === 'GET') {
        return $courseController->getInstructor();
    }
    if ($uri === '/lsmBackend/api/courses' && $method === 'POST') {
        // Check if the request has both file upload and JSON data in form-data
        $fileUrl = null; // To store the relative URL
        if (isset($_FILES['file']) && !empty($_FILES['file']['name'])) {
            $file = $_FILES['file'];

            // Call the helper function to upload the file
            $fileUrl = uploadFileToCloudinary($file, 'courses'); // Specify 'courses' folder for organization

            if (!$fileUrl) {
                // Handle the case where the upload failed
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Image upload failed',
                ]);
                exit;
            }
        }
        if (isset($_POST['data'])) {
            $data = json_decode($_POST['data'], true);

            if ($data === null) {
                // Return error if JSON is invalid
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid JSON data'
                ]);
                exit;
            }

            // Ensure required fields are present in the data
            if (empty($data['title']) || empty($data['description']) || empty($data['max_student']) || empty($data['reference_code'])) {
                // Return error if any required fields are missing
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing required fields'
                ]);
                exit;
            }

            // Add the file URL to the data if a file was uploaded
            if ($fileUrl) {
                $data['image'] = $fileUrl; // Save relative URL in the database
            }
            $authMiddlewere = new AuthMiddleware($jwtSecret);
            $authMiddlewere->handleAdmin();
            $creater_id = $authMiddlewere->handleCreatedBy();

            // Call the controller's createCourse method to handle the business logic
            $response = $courseController->createCourse($data, $creater_id);

            // Return the final response
            echo json_encode($response,);
            exit;
        } else {
            // Handle case when JSON data is missing
            echo json_encode([
                'status' => 'error',
                'message' => 'Missing course data (JSON)'
            ]);
            exit;
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)\/markascomplete$/', $uri, $matches)) {
        $courseId = $matches[1];
        if ($method === 'POST') {
            return $courseController->markAsCompleted($courseId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)$/', $uri, $matches)) {
        $courseId = $matches[1];
        if ($method === 'GET') {
            return $courseController->getCourseById($courseId);
        }

        if ($method === 'POST') {
            // Initialize variables for file upload and file path
            $filePath = null;

            // Check if a file has been uploaded
            if (isset($_FILES['file']) && !empty($_FILES['file']['name'])) {
                $file = $_FILES['file'];

                // Call the helper function to upload the file
                $fileUrl = uploadFileToCloudinary($file, 'courses'); // Specify 'courses' folder for organization

                if (!$fileUrl) {
                    // Handle the case where the upload failed
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Image upload failed',
                    ]);
                    exit;
                }
            }

            $data = json_decode($_POST['data'], true);

            if ($data === null) {
                // Return error if JSON is invalid
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid JSON data'
                ]);
                exit;
            }
            // Validate JSON data
            if ($data === null) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid JSON data'
                ]);
                exit;
            }

            // Validate required fields in the data
            if (empty($data['title']) || empty($data['description']) || empty($data['max_student']) || empty($data['reference_code']) || !isset($data['status'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing required fields'
                ]);
                exit;
            }
            // Add the file path to the data if a file was uploaded
            if ($filePath) {
                $data['image'] = $filePath;
            } else {
                $data['image'] = null;
            }

            $response = $courseController->updateCourse($courseId, $data);

            // Return the final response
            return $response;
        }

        if ($method === 'DELETE') {
            return $courseController->deleteCourse($courseId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/users\/courses\/(\d+)$/', $uri, $matches)) {
        $courseId = $matches[1];
        $authMiddlewere = new AuthMiddleware($jwtSecret);
        $user_id = $authMiddlewere->handleCreatedBy();
        if ($method === 'GET') {
            return $courseController->getCourseByIdByUser($courseId, $user_id);
        }
    }
    if ($uri === '/lsmBackend/api/courses/badges' && $method === 'GET') {
        $authMiddlewere = new AuthMiddleware($jwtSecret);
        $userId =  $authMiddlewere->handleCreatedBy();
        return $courseController->getBadgesByUserId($userId);
    }
    if ($uri === '/lsmBackend/api/courses/getachievement' && $method === 'GET') {
        $authMiddlewere = new AuthMiddleware($jwtSecret);
        $userId =  $authMiddlewere->handleCreatedBy();
        return $moduleController->getAchievement($userId);
    }
    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)\/modules$/', $uri, $matches)) {
        $courseId = $matches[1];
        if ($method === 'POST') {
            $data = json_decode($_POST['data'], true);
            if ($data['module_type'] === 'scorm') {
                $file = $_FILES['scorm_file'];
                $fileUrl = uploadZipToCloudinary($file, 'courses'); // Specify 'courses' folder for organization
                if (!$fileUrl) {
                    return [
                        'error' => true,
                        'status' => 400,
                        'massege' => "image can't be upload"
                    ];
                }
                $data['file_url'] = $fileUrl;
                if (!$file || !isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
                    return 'No file uploaded or invalid file.';
                }
                if (mime_content_type($file['tmp_name']) !== 'application/zip') {
                    return 'Uploaded file is not a valid ZIP archive.';
                }
            }
            $result = $moduleController->addModule($data, $courseId);
            return $result;
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)\/modules\/(\d+)\/scorm$/', $uri, $matches)) {
        $courseId = $matches[1];
        $moduleId = $matches[2];
        $authMiddlewere = new AuthMiddleware($jwtSecret);
        $userId =  $authMiddlewere->handleCreatedBy();
        if ($method === 'GET') {
            return $moduleController->getScormFile($moduleId, $courseId, $userId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)\/modules\/(\d+)(\?.*)?$/', $uri, $matches)) {

        $moduleId = $matches[2];

        if ($method === 'GET') {
            $result = $moduleController->getModuleById($moduleId);
            return $result;
        }
        if ($method === 'DELETE') {
            $result = $moduleController->deleteModuleById($moduleId);
            return $result;
        }
        if ($method === 'PUT') {
            $data = json_decode(file_get_contents("php://input"), true);
            parse_str(parse_url($uri, PHP_URL_QUERY), $queryParams);
            $mandatory = isset($queryParams['mandatory']) ? $queryParams['mandatory'] : null;
            if ($mandatory) {
                $result = $moduleController->changeModuleMandatory($moduleId, $mandatory);
                return $result;
            }
            $result = $moduleController->updateModule($moduleId, $data);
            return $result;
        }
    }

    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)\/modules\/(\d+)\/assessmentsanswer$/', $uri, $matches)) {
        $courseId = $matches[1];
        $moduleId = $matches[2];
        $data = json_decode(file_get_contents("php://input"), true);
        $authMiddlewere = new AuthMiddleware($jwtSecret);
        $userId = $authMiddlewere->handleCreatedBy();
        return $moduleController->assessmentAswers($userId, $moduleId, $data['answer'], $courseId);
    }
    if (preg_match('/^\/lsmBackend\/api\/courses\/modules\/question\/(\d+)$/', $uri, $matches)) {
        $questionId = $matches[1];
        $data = json_decode($_POST['data'], true);
        $result = $moduleController->updateModuleQuestion($questionId, $data);
        return $result;
    }
    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)\/modulestart$/', $uri, $matches)) {
        $data = json_decode(file_get_contents("php://input"));
        $courseId = $matches[1];
        if ($method === 'POST') {
            $authMiddlewere = new AuthMiddleware($jwtSecret);
            $userId =  $authMiddlewere->handleCreatedBy();
            return $moduleController->startModule($userId, $data->moduleId, $courseId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)\/modulecomplete$/', $uri, $matches)) {
        if ($method === 'POST') {
            $data = json_decode(file_get_contents("php://input"));
            $authMiddlewere = new AuthMiddleware($jwtSecret);
            $courseId = $matches[1];
            $userId =  $authMiddlewere->handleCreatedBy();
            return $moduleController->endModule($userId, $data->moduleId, $courseId);
        }
    }

    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)\/teams$/', $uri, $matches)) {
        $courseid = $matches[1];
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'));
            $result = $teamController->addTeamInCourse($courseid, $data->team_id);
            return $result;
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)\/instructor$/', $uri, $matches)) {
        $courseId = $matches[1];

        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'));
            if (!isset($data->ids) || !is_array($data->ids)) {
                return [
                    "status" => 400,
                    "message" => "Invalid input. Provide an array of instructor IDs.",
                ];
            }
            $instructorIds = $data->ids;
            $result = $courseController->addMultipleInstructors($courseId, $instructorIds);
            return $result;
        }
        if ($method === 'DELETE') {
            $data = json_decode(file_get_contents('php://input'));


            $result = $courseController->removeInstructors($courseId, $data);
            return $result;
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)\/noticeboard$/', $uri, $matches)) {
        $courseId = $matches[1];
        $authMiddlewere = new AuthMiddleware($jwtSecret);
        $creater_id = $authMiddlewere->handleCreatedBy();
        if ($method === 'POST') {
            $fileUrl = null; // To store the relative URL
            if (isset($_FILES['file']) && !empty($_FILES['file']['name'])) {
                $file = $_FILES['file'];

                // Call the helper function to upload the file
                $fileUrl = uploadFileToCloudinary($file); // Specify 'courses' folder for organization

                if (!$fileUrl) {
                    // Handle the case where the upload failed
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'file upload failed',
                    ]);
                    exit;
                }
            }
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);

                if ($data === null) {
                    // Return error if JSON is invalid
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Invalid JSON data'
                    ]);
                    exit;
                }
                if ($fileUrl) {
                    $data['file_path'] = $fileUrl; // Save relative URL in the database
                }



                // Call the controller's createCourse method to handle the business logic
                $response = $courseController->addNotice($courseId, $data, $creater_id);
                // Return the final response
                return $response;
            } else {
                // Handle case when JSON data is missing
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing notice data (JSON)'
                ]);
                exit;
            }
        }
    }
    $parsedUrl = parse_url($uri);
    if (isset($parsedUrl['path']) && $parsedUrl['path'] === '/lsmBackend/api/users' && $method === 'GET') {
        $searchParams = [];
        parse_str($parsedUrl['query'] ?? "", $searchParams);
        $keyword = $searchParams["q"] ?? "";
        $status = $searchParams["status"] ?? "";
        $order = $searchParams["order"] ?? "ASC";
        return $authController->getAllUsers($keyword, $status, $order);
    }
    if (preg_match('/^\/lsmBackend\/api\/users\/(\d+)$/', $uri, $matches)) {
        $userId = $matches[1];
        if ($method === 'GET') {
            return $authController->getUserById($userId);
        }
        if ($method === 'PUT') {
            $data = json_decode(file_get_contents("php://input"));
            return $courseController->updateCourse($courseId, $data);
        }
        if ($method === "DELETE") {
            $userId = $matches[1];
            $response = $authController->deleteUser($userId);
            http_response_code($response['status']);
            echo json_encode($response);
            exit;
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)\/enrollment$/', $uri, $matches)) {
        $courseId = $matches[1];
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            return $courseController->addEnrollment($courseId, $data['ids']);
        }
        if ($method === 'DELETE') {
            $data = json_decode(file_get_contents('php://input'), true);
            return $courseController->deleteEnrollmentByCourseId($courseId, $data['ids']);
        }
        if ($method === 'GET') {
            return $courseController->getEnrollmentByCourseId($courseId);
        }
        if ($method === 'PUT') {
            $data = json_decode(file_get_contents('php://input'));
            return $courseController->editEnrollmentResults($courseId, $data);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/courses\/(\d+)\/enrollmentbyuser$/', $uri, $matches)) {
        $authMiddlewere = new AuthMiddleware($jwtSecret);
        $userId = $authMiddlewere->handleCreatedBy();
        $courseId = $matches[1];
        if ($method === 'POST') {
            return $courseController->addEnrollmentByuser($courseId, $userId);
        }
        if ($method === 'DELETE') {
            return $courseController->deleteEnrollmentByUser($courseId, $userId);
        }
    }
    if ($uri === '/lsmBackend/api/teams' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'));

        return $teamController->addTeam($data);
    }
    if ($uri === '/lsmBackend/api/teams' && $method === 'GET') {
        return $teamController->getAllTeams();
    }
    if (preg_match('/^\/lsmBackend\/api\/teams\/(\d+)$/', $uri, $matches)) {
        $teamId = $matches[1];
        if ($method === 'GET') {
            return $teamController->getTeam($teamId);
        }
        if ($method === 'PUT') {
            $data = json_decode(file_get_contents('php://input'));
            return $teamController->updateTeam($teamId, $data);
        }
        if ($method === 'DELETE') {
            return $teamController->deleteTeam($teamId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/teams\/members\/(\d+)$/', $uri, $matches)) {
        $teamId = $matches[1];
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'));
            return $teamController->addTeamMember($teamId, $data->user_id);
        }
        if ($method === 'GET') {
            return $teamController->getTeamMembers($teamId);
        }
        if ($method === 'DELETE') {
            $data = json_decode(file_get_contents('php://input'));
            return $teamController->removeTeamMembers($teamId, $data->user_id);
        }
    }

    if ($uri === '/lsmBackend/api/learningpath' && $method === 'POST') {
        $file_path = null;
        if (isset($_FILES['file'])) {
            $file = $_FILES['file'];
            $file_path  = uploadFileToCloudinary($file);
            if (!$file_path) {
                return [
                    'error' => false,
                    'status' => 400,
                    'massege' => 'image not upload'
                ];
            }
        }
        $data = json_decode($_POST['data'], true);
        $data['image'] = $file_path;
        return $learningPathController->addLearningPath($data);
    }
    if ($uri === '/lsmBackend/api/getlearningpath' && $method === 'GET') {
        return $learningPathController->getLearningPaths();
    }
    if (preg_match('/^\/lsmBackend\/api\/getlearningpath\/(\d+)$/', $uri, $matches)) {
        $learningPathId = $matches[1];
        if ($method === 'GET') {
            return $learningPathController->getLearningPathById($learningPathId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/getlearningpath\/(\d+)\/enrolledlearningpathbyuser$/', $uri, $matches)) {
        $learningPathId = $matches[1];
        $authMiddlewere = new AuthMiddleware($jwtSecret);
        $userId = $authMiddlewere->handleCreatedBy();
        if ($method === 'POST') {
            return $learningPathController->enrolledLearningPathByUser($learningPathId, $userId);
        }
    }
    if ($uri === '/lsmBackend/api/sessions' && $method === 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        return $sessionsController->addSessions($data);
    }
    if ($uri === '/lsmBackend/api/sessions' && $method === 'GET') {
        $data = json_decode(file_get_contents("php://input"));
        return $sessionsController->getSessions();
    }

    if (preg_match('/^\/lsmBackend\/api\/sessions\/(\d+)$/', $uri, $matches)) {
        $sessionId = $matches[1];
        if ($method === 'GET') {
            return $sessionsController->getSessionsById($sessionId);
        }
        if ($method === 'POST') {
            $authMiddlewere = new AuthMiddleware($jwtSecret);
            $userId =  $authMiddlewere->handleCreatedBy();
            return $sessionsController->sessionEnrollment($userId, $sessionId);
        }
    }
    if ($uri === '/lsmBackend/api/reports/summary' && $method === 'GET') {
        $reportSummary = $reportController->getSummary();
        return $reportSummary;
    }

    $parsedUrl = parse_url($uri);
    if (isset($parsedUrl['path']) && $parsedUrl['path'] === '/lsmBackend/api/reports/getactiveusersandcourse' && $method === 'GET') {
        $searchParams = [];
        parse_str($parsedUrl['query'] ?? "", $searchParams);

        $intervalValue = isset($searchParams["value"]) && is_numeric($searchParams["value"]) && (int)$searchParams["value"] > 0
            ? (int)$searchParams["value"]
            : 30; // Default to 30

        $intervalType = isset($searchParams["type"]) && in_array(strtolower($searchParams["type"]), ['day', 'month'])
            ? strtoupper($searchParams["type"])
            : "DAY"; // Default to "DAY"

        // Fetch data
        $getMostActiveCourses = $reportController->getMostActiveCourses($intervalValue, $intervalType);
        $getActiveUser = $reportController->getActiveUser($intervalValue, $intervalType);

        // Return combined response with up to 6 entries
        return [
            'success' => true,
            'data' => [
                'active_users' => $getActiveUser,
                'active_courses' => $getMostActiveCourses
            ]
        ];
    }
    if ($uri === '/lsmBackend/api/reports/getcoursecomletionreport' && $method = 'GET') {
        return $reportController->getmodulecompletionreport();
    }
    if ($uri === '/lsmBackend/api/reports/getloginactivitychart' && $method = 'GET') {
        $getLoginActivityChart = $reportController->getLoginActivityChart();
        return $getLoginActivityChart;
    }
    if ($uri === '/lsmBackend/api/reports/getmodulecompletionreport' && $method = 'GET') {
        return $reportController->getCourseCompletionReport();
    }
    if ($uri === '/lsmBackend/api/reports/getassessmentompletion' && $method = 'GET') {
        return $reportController->getAssessmentCompletion();
    }
    if ($uri === '/lsmBackend/api/reports/getcompliancesummary' && $method = 'GET') {
        return $reportController->getComplianceSummary();
    }
    if ($uri === '/lsmBackend/api/reports/getachievementreports' && $method = 'GET') {
        return $reportController->getAchievementReports();
    }
    if ($uri === '/lsmBackend/api/reports/getbadgesreports' && $method = 'GET') {
        return $reportController->getBadgesReports();
    }
    if ($uri === '/lsmBackend/api/reports/getteamsreport' && $method = 'GET') {
        return $reportController->getTeamsReport();
    }
    if ($uri === '/lsmBackend/api/reports/getlearningpathreport' && $method = 'GET') {
        return $reportController->getLearningPathReport();
    }
    if ($uri === '/lsmBackend/api/reports/getessionseport' && $method = 'GET') {
        return $reportController->getSessionsReport();
    }
    if (preg_match('/^\/lsmBackend\/api\/people\/(\d+)\/recentactivity$/', $uri, $matches)) {
        $userId = $matches[1];
        if ($method === 'GET') {
            return  $peopleController->getRecentAtivity($userId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/people\/(\d+)\/acheivements$/', $uri, $matches)) {
        $userId = $matches[1];
        if ($method === 'GET') {
            return  $peopleController->getAcheivements($userId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/people\/(\d+)\/courses$/', $uri, $matches)) {
        $userId = $matches[1];
        if ($method === 'GET') {
            return  $peopleController->getCourses($userId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/people\/(\d+)\/courses\/(\d+)$/', $uri, $matches)) {
        $userId = $matches[1];
        $courseId = $matches[2];
        if ($method === 'GET') {
            return  $peopleController->getModuleByCourses($userId, $courseId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/people\/(\d+)\/learningpaths$/', $uri, $matches)) {
        $userId = $matches[1];
        if ($method === 'GET') {
            return  $peopleController->getLearningPaths($userId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/people\/(\d+)\/learningpaths\/(\d+)$/', $uri, $matches)) {
        $userId = $matches[1];
        $learning_path_id = $matches[2];
        if ($method === 'GET') {
            return  $peopleController->getLearningPathById($userId, $learning_path_id);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/people\/(\d+)\/iltsessions$/', $uri, $matches)) {
        $userId = $matches[1];
        if ($method === 'GET') {
            return  $peopleController->getIltSessions($userId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/people\/(\d+)\/teams$/', $uri, $matches)) {
        $userId = $matches[1];
        if ($method === 'GET') {
            return  $peopleController->getUserTeams($userId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/people\/(\d+)\/getteams$/', $uri, $matches)) {
        $userId = $matches[1];
        if ($method === 'GET') {
            return  $peopleController->getTeams($userId);
        }
    }
    if (preg_match('/^\/lsmBackend\/api\/people\/(\d+)\/teams\/(\d+)$/', $uri, $matches)) {
        $userId = $matches[1];
        $teamId = $matches[2];
        if ($method === 'POST') {
            return  $peopleController->userAssignToTeam($userId, $teamId);
        }
    }
    return ["invalid url", $uri, $method];
}
$response = route($uri, $method, $db);
header('Content-Type: application/json');
echo json_encode($response);
ob_end_flush();
