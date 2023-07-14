<?php

/**
 * Test Page Component plugin
 * @author Roberto Pasini <bonjour@kalamun.net>
 */
class ilTrainingDashboardPlugin extends ilPageComponentPlugin
{
    /**
     * Get plugin name
     * @return string
     */
    public function getPluginName() : string
    {
        return "TrainingDashboard";
    }

    /**
     * Check if parent type is valid
     */
    public function isValidParentType(/* string */ $a_parent_type) /* :  bool */
    {
        // test with all parent types
        return true;
    }


    public function getCssFiles(/* string */ $a_mode)/* : array */
    {
        return ["css/training-dashboard.css"];
    }
}