<?php

ob_start(); // Start output buffering
// Include other necessary files
include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../vendor/autoload.php';  // For JWT
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';


class AuthController
{
    private $db;
    private $user;
    public $secretkey;

    public function __construct($db)
    {
        $this->db = $db;
        $this->user = new User($this->db); // Create a new User model instance
        $this->secretkey = "iaawk";  // You can also load this from an environment variable
    }

    public function addPerson($data)
    {
        if (empty($data->first_name) || empty($data->last_name) || empty($data->email) || empty($data->username)) {
            return [
                'status' => 403,
                'message' => "Missing first name, last name, email, or username.",
            ];
        }

        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 400, 'message' => 'Invalid email format.'];
        }

        if (
            !preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $data->password)
        ) {
            return [
                'status' => 400,
                'message' => 'Password must contain 1 uppercase letter, 1 lowercase letter, 1 number, 1 special character, and be at least 8 characters long.',
            ];
        }

        $data->password = password_hash($data->password, PASSWORD_DEFAULT);
        $result = $this->user->addPerson($data);

        if ($result === true) {
            return [
                'status' => 400,
                'message' => "Username or email already exists.",
            ];
        }

        if (is_array($result) && isset($result['status'])) {
            return $result; // Pass through specific error messages like invalid job role ID
        }

        return [
            "status" => 200,
            "message" => "Person added successfully.",
            "data" => $data,
        ];
    }
    // Controller: register function
    public function register($data)
    {
        // Check if all required fields are provided
        if (empty($data->name) || empty($data->email) || empty($data->password) || empty($data->confirmpassword)) {
            return ['status' => 400, 'message' => 'All fields are required.'];
        }
        // Password strength validation (you can adjust this according to your requirements)
        if (
            !preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $data->password)
        ) {
            return [
                'status' => 400,
                'message' => 'Password must contain 1 uppercase letter, 1 lowercase letter, 1 number, 1 special character, and be at least 8 characters long.',
            ];
        }

        // Check if passwords match
        if ($data->password != $data->confirmpassword) {
            return ['status' => 400, 'message' => 'Passwords do not match.'];
        }

        // Validate email format
        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 400, 'message' => 'Invalid email format.'];
        }

        // Check if email already exists using the model's emailExists function
        if ($this->user->emailExists($data->email)) {
            return ['status' => 400, 'message' => 'Email already exists.'];
        }

        // Split the 'name' field into first and last names
        $nameParts = explode(' ', $data->name, 2);
        $firstName = $nameParts[0];
        $lastName = isset($nameParts[1]) ? $nameParts[1] : ''; // Handle if only one name is provided

        // Hash the password
        $hashedPassword = password_hash($data->password, PASSWORD_DEFAULT);

        // Register the user using the model
        $result = $this->user->register($data, $firstName, $lastName, $hashedPassword);

        if ($result['status'] == 201) {
            return [
                'status' => 201,
                'message' => 'User registered successfully.',
                'data' => [
                    'name' => $data->name,
                    'email' => $data->email,
                ]
            ];
        }

        return $result; // Return any error message from the model
    }
    // Login a user
    public function login($data)
    {
        if (empty($data->email) || empty($data->password)) {
            return ['status' => 400, 'message' => 'Email and password are required.'];
        }
        $result = $this->user->login($data);
        if ($result) {
            $this->user->id = $result['id'];
            // JWT Payload without expiration time
            $issuedAt = time();
            $payload = [
                'iat' => $issuedAt,  // Issue time
                'user_id' => $result['id'],
                'profile_type' => $result['profile_type'],
                'access_level' => $result['access_level'],
            ];

            // Generate JWT token
            $jwt = JWT::encode($payload, $this->secretkey, 'HS256');

            // Save JWT Token to the Database for the User
            $this->user->jwt_token = $jwt;  // Assuming the User model has a `jwt_token` property
            if ($this->user->jwt_token) {  // Call saveJwtToken() function
                return [
                    'status' => 200,
                    'message' => 'Login successful.',
                    'data' => [
                        'token' => $jwt,
                        'user' => [
                            'id' => $result['id'],
                            'name' => $result['first_name'] . ' ' . $result['last_name'],
                            'email' => $result['email'],

                            'profile_type' => $result['profile_type']
                        ]
                    ]
                ];
            } else {
                return ['status' => 500, 'message' => 'Failed to save JWT token in the database.'];
            }
        } else {
            return ['status' => 401, 'message' => 'Invalid email or password.'];
        }
    }
    // Forget password
    public function forgetPassword($data)
    {
        if (empty($data->email) || empty($data->new_password)) {
            return ['status' => 400, 'message' => 'Email and new password are required.'];
        }

        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 400, 'message' => 'Invalid email format.'];
        }

        $this->user->email = htmlspecialchars(strip_tags($data->email));
        $newPassword = htmlspecialchars(strip_tags($data->new_password));

        if (!$this->user->emailExists($data->email)) {
            return ['status' => 400, 'message' => 'Email is not registered.'];
        }

        if ($this->user->forgetPassword($newPassword)) {
            return ['status' => 200, 'message' => 'Password updated successfully.'];
        } else {
            return ['status' => 500, 'message' => 'Unable to update password.'];
        }
    }

    public function getEnumValuesUserTable(){
        return $this->user->getEnumValuesUserTable();
    }
    // Check if the user is an admin
    public function checkAdmin()
    {
        try {
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new Exception("Authorization token missing.", 401);
            }

            $jwt = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
            $key = new Key($this->secretkey, 'HS256');
            $decodedToken = JWT::decode($jwt, $key);

            if (isset($decodedToken->access_level) && strtolower($decodedToken->access_level) === 'administrator') {
                return 'administrator';
            } else {
                throw new Exception("Permission Denied. Only admin can perform this action.", 403);
            }
        } catch (Exception $e) {
            echo json_encode([
                "status" => $e->getCode() ?: 500,
                "message" => $e->getMessage()
            ]);
            exit;
        }
    }
    // Get all users
    public function getAllUsers($keyword = '', $status = 'active', $order = 'ASC')
    {

        // $page = isset($searchedParams['page']) ? (int) $searchedParams['page'] : 1;
        // $perPage = isset($searchedParams['limit']) ? (int) $searchedParams['limit'] : 10;
        $results = $this->user->getAllUsers($keyword, $status, $order);
        return [
            'error' => 'false',
            'status' => 200,
            'message' => "Users fetched successfully",
            'users' => $results['data'],

            'totalUsers' => $results['totalRecords'],

        ];
    }
    // Get user by ID
    public function getUserById($id)
    {
        $user = $this->user->getUserById($id);
        if ($user) {
            return [$user];
        } else {
            return ["message" => "User not found"];
        }
    }

    // Delete user
    public function deleteUser($id)
    {
        try {
            $isAdmin = $this->checkAdmin();
            if ($isAdmin !== 'administrator') {
                throw new Exception("Permission Denied. Only admin can delete users.", 403);
            }

            $result = $this->user->deleteUser($id);
            return [
                "status" => 200,
                "message" => "User deleted successfully."
            ];
        } catch (Exception $e) {
            return [
                "status" => $e->getCode() ?: 500,
                "message" => $e->getMessage()
            ];
        }
    }

    // Logout a user
    public function logout()
    {
        try {
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new Exception("Authorization token missing.", 401);
            }

            // Extract and validate the token
            $jwt = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
            $key = new Key($this->secretkey, 'HS256');
            $decodedToken = JWT::decode($jwt, $key);

            // Set user ID from the decoded JWT token
            $this->user->id = $decodedToken->user_id;

            // Assign logout time and date
            $logoutTime = date('Y-m-d H:i:s');

            // Update inactive_date in the database
            if ($this->user->updateLogoutTime($logoutTime)) {
                return ['status' => 200, 'message' => 'Logged out successfully.'];
            } else {
                throw new Exception("Failed to update logout time.", 500);
            }
        } catch (Exception $e) {
            http_response_code($e->getCode() ?: 500);
            return ['status' => $e->getCode() ?: 500, 'message' => $e->getMessage()];
        }
    }

}
ob_end_flush();
?>