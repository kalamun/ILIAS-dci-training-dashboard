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
    public function isValidParentType(string $a_parent_type) :  bool
    {
        // test with all parent types
        return true;
    }


    public function getCssFiles(string $a_mode) : array
    {
        // ilPCPlugged::getCssFiles() prepends $plugin->getDirectory(), which is
        // an absolute filesystem path, not a URL. It only skips that prepending
        // if the returned path already contains "//", so we use the web-relative
        // directory (getRelativeDirectory()) with a "//" separator to get a
        // correct, browser-loadable path.
        return [$this->getRelativeDirectory() . "//css/training-dashboard.css"];
    }
}