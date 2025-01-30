<?php
require_once 'models/Reports.php';
class ReportController
{
    public $reportModel;
    public function __construct($db)
    {
        $this->reportModel = new Reports($db);
    }

    public function getSummary()
    {
        $summary = $this->reportModel->summary();
        return $summary;
    }

    public function getMostActiveCourses($intervalValue, $intervalType)
    {
        return $this->reportModel->getMostActiveCourses($intervalValue, $intervalType);
    }
    
    public function getActiveUser($intervalValue, $intervalType)
    {
        return $this->reportModel->getActiveUser($intervalValue, $intervalType);
    }
    public function getLoginActivityChart()
    {
        $getLoginActivityChart = $this->reportModel->getLoginActivityChart();
        return $getLoginActivityChart;
    }
    public function getModuleCompletionReport(){
        return $this->reportModel->getModuleCompletionReport();
    }
    public function getCourseCompletionReport(){
        return $this->reportModel->getCourseCreationAndCompletionReport();
    }
    public function getAssessmentCompletion(){
        return $this->reportModel->getAssessmentCompletion();
    }

}
