<?php
require_once 'models/sessions.php';
class SessionsController
{
    public $sessionModule;
    public function __construct($db)
    {
        $this->sessionModule = new Sessions($db);
    }
    public function addSessions($data)
    {
        $result =  $this->sessionModule->addSessions($data);
        if ($result) {
            return [
                'error' => false,
                'status' => 200,
                'message' => 'sessions added successfully'
            ];
        }
    }
    public function getSessions()
    {
        $result =  $this->sessionModule->getSessions();

        return [
            'error' => false,
            'status' => 200,
            'message' => 'sessions fetch successfully',
            'data' => $result
        ];
    }
    public function getSessionsById($sessionId)
    {
        $result =  $this->sessionModule->getSessionById($sessionId);

        return [
            'error' => false,
            'status' => 200,
            'message' => 'session fetch successfully',
            'data' => $result
        ];
    }
    public function sessionEnrollment($userId, $sessionId)
    {
        $result =  $this->sessionModule->sessionsEnrollment($userId, $sessionId);
        return $result;
    }

}
