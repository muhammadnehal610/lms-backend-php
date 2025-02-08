<?php
require_once 'models/LearningPaths.php';
class LearningPathController
{
    public $db;
    public $learningPathModel;
    public function __construct($db)
    {
        $this->db = $db;
        $this->learningPathModel = new LearningPaths($db);
    }
    public function addLearningPath($data)
    {
        $result = $this->learningPathModel->addLearningPaths($data);
        return $result;
    }
    public function getLearningPaths()
    {
        $result = $this->learningPathModel->getLearningPaths();
        return $result;
    }
    public function getLearningPathById($id)
    {
        $result = $this->learningPathModel->getLearningPathById($id);
        return $result;
    }
    public function enrolledLearningPathByUser($learning_path_id , $user_id){
        return $this->learningPathModel->enrolledLearningPathByUser($learning_path_id , $user_id);
    }
}
