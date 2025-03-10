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


    // Handle auth routes
    if ($uri === '/api/auth/register' && $method === 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        return $authController->register($data);
    }
    if ($uri === '/api/auth/login' && $method === 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        return $authController->login($data);
    }
    if ($uri === '/api/auth/logout' && $method === 'POST') {
        return $authController->logout();
    }
    if ($uri === '/api/auth/forget-password' && $method === 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        return $authController->forgetPassword($data);
    }

    if ($uri === '/api/addperson' && $method === 'POST') {
        $authMiddlewere = new AuthMiddleware($jwtSecret);
        $authMiddlewere->handleAdmin();
        $data = json_decode(file_get_contents('php://input'));
        $result = $authController->addPerson($data);
        return $result;
    }
   if ($uri === '/api/addbulkperson' && $method === 'POST') {
       $authMiddlewere = new AuthMiddleware($jwtSecret);
       $authMiddlewere->handleAdmin();
       $data = $_FILES['file'];
       $result = $bulkController->importUsers($data);
       return $result;
   }
    // Handle course routes
    if ($uri === '/api/courses' && $method === 'GET') {
        return $courseController->getAllCourses();
    }
    if ($uri === '/api/users/getinstructor' && $method === 'GET') {
        return $courseController->getInstructor();
    }
    if ($uri === '/api/courses' && $method === 'POST') {
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
            echo json_encode($response, );
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
    if (preg_match('/^\/api\/courses\/(\d+)$/', $uri, $matches)) {
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
    if (preg_match('/^\/api\/courses\/modules\/(\d+)(\?.*)?$/', $uri, $matches)) {
        $courseId = $matches[1];

        if ($method === 'POST') {
            $data = json_decode($_POST['data'], true);
            $result = $moduleController->addModule($data, $courseId);
            return $result;
        }

        if ($method === 'GET') {
            $moduleId = $matches[1];
            $result = $moduleController->getModuleById($moduleId);
            return $result;
        }
        if ($method === 'DELETE') {
            $moduleId = $matches[1];
            $result = $moduleController->deleteModuleById($moduleId);
            return $result;
        }

        if ($method === 'PUT') {
            $moduleId = $matches[1]; // Extract moduleId from URL
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
    if (preg_match('/^\/api\/courses\/modules\/question\/(\d+)$/', $uri, $matches)) {
        $questionId = $matches[1];
        $data = json_decode($_POST['data'], true);
        $result = $moduleController->updateModuleQuestion($questionId, $data);
        return $result;
    }
    if (preg_match('/^\/api\/courses\/(\d+)\/teams$/', $uri, $matches)) {
        $courseid = $matches[1];
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'));
            $result = $teamController->addTeamInCourse($courseid, $data->team_id);
            return $result;
        }

    }
    if (preg_match('/^\/api\/courses\/(\d+)\/instructor$/', $uri, $matches)) {
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
        if($method === 'DELETE'){
            $data = json_decode(file_get_contents('php://input'));
         
        
            $result= $courseController->removeInstructors($courseId, $data);
            return $result;
        }

    }
    if (preg_match('/^\/api\/courses\/(\d+)\/noticeboard$/', $uri, $matches)) {
        $courseId = $matches[1];
        $authMiddlewere = new AuthMiddleware($jwtSecret);
        $creater_id= $authMiddlewere->handleCreatedBy();
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
                $response = $courseController->addNotice($courseId, $data , $creater_id);
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
    if (preg_match('/^\/api\/users\/(\d+)$/', $uri, $matches)) {
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
    if (preg_match('/^\/api\/courses\/(\d+)\/enrollment$/', $uri, $matches)) {
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
    if ($uri === '/api/teams' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'));

        return $teamController->addTeam($data);
    }
    if ($uri === '/api/teams' && $method === 'GET') {
        return $teamController->getAllTeams();
    }
    if (preg_match('/^\/api\/teams\/(\d+)$/', $uri, $matches)) {
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
    if (preg_match('/^\/api\/teams\/members\/(\d+)$/', $uri, $matches)) {
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

    return ["invalid url", $uri, $method];
}

$response = route($uri, $method, $db);
header('Content-Type: application/json');
echo json_encode($response);
ob_end_flush();



