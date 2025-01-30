<?php
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    private $secretKey;

    public function __construct($secretKey)
    {
        $this->secretKey = $secretKey; // Initialize the secret key for JWT
    }

    public function handleAdmin()
    {
        try {
            // Check if the Authorization header is present
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new Exception("Authorization token missing.", 401);
            }

            // Extract JWT token from the Authorization header
            $jwt = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);

            // Decode the JWT token using the secret key
            $key = new Key($this->secretKey, 'HS256');
            $decodedToken = JWT::decode($jwt, $key);

            // Check if the user has the 'admin' role
            if (isset($decodedToken->access_level) && strtolower($decodedToken->access_level) === 'administrator') {
                // Allow access if the role is admin
                return true;
            } else {
                // Deny access if role is not admin
                throw new Exception("Permission Denied. Only admin can perform this action.", 403);
            }
        } catch (Exception $e) {
            // Send error response if token is invalid or role is not admin
            echo json_encode([
                'status' => $e->getCode(),
                'message' => $e->getMessage()
            ]);
            exit; // Stop further execution
        }
    }


    public function handleCreatedBy()
    {
        try {
            // Check if the Authorization header is present
            if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
                throw new Exception("Authorization token is missing.", 401);
            }

            // Extract the JWT token from the Authorization header
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            if (strpos($authHeader, 'Bearer ') !== 0) {
                throw new Exception("Invalid Authorization header format.", 400);
            }
            $jwt = str_replace('Bearer ', '', $authHeader);

            // Decode the JWT token using the secret key
            $key = new Key($this->secretKey, 'HS256'); // Use the correct algorithm
            $decodedToken = JWT::decode($jwt, $key);

            // Ensure the token contains the 'id' field
            if (!isset($decodedToken->user_id)) {
                throw new Exception("User ID is missing in the token.", 403);
            }

            // Return the user ID if everything is valid
            return  $decodedToken->user_id;

        } catch (\Firebase\JWT\ExpiredException $e) {
            // Handle expired token error
            echo json_encode([
                'status' => 401,
                'message' => "Token has expired. Please log in again."
            ]);
            exit;
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            // Handle invalid signature error
            echo json_encode([
                'status' => 401,
                'message' => "Invalid token signature."
            ]);
            exit;
        } catch (Exception $e) {
            // General error handling
            echo json_encode([
                'status' => $e->getCode() ?: 500, // Default to 500 if code is not set
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }



}
