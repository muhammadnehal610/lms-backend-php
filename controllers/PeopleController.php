<?php
require_once  'models/people.php';
class  PeopleController
{
    public $peopleModel;
    public function __construct($db)
    {
        $this->peopleModel = new People($db);
    }
    public function getRecentAtivity($user_id)
    {
        return $this->peopleModel->getRecentAtivity($user_id);
    }
    public function getAcheivements($user_id)
    {
        return $this->peopleModel->getAcheivements($user_id);
    }
    public function getCourses($user_id)
    {
        return $this->peopleModel->getCourses($user_id);
    }
    public function getModuleByCourses($user_id, $course_id)
    {
        return $this->peopleModel->getModuleByCourses($user_id, $course_id);
    }
    public function getLearningPaths($user_id)
    {
        return $this->peopleModel->getLearningPaths($user_id);
    }
    public function getLearningPathById($user_id, $learning_path_id)
    {
        return $this->peopleModel->getLearningPathById($user_id, $learning_path_id);
    }
    public function getIltSessions($user_id)
    {
        return $this->peopleModel->getIltSessions($user_id);
    }
    public function getUserTeams($user_id)
    {
        return $this->peopleModel->getUserTeams($user_id);
    }
    public function getTeams($user_id)
    {
        return $this->peopleModel->getTeams($user_id);
    }
    public function userAssignToTeam($user_id, $team_id)
    {
        return $this->peopleModel->userAssignToTeam($user_id, $team_id);
    }
}
