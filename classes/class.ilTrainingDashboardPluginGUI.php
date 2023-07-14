<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

/**
 * Test Page Component GUI
 * @author            Roberto Pasini <bonjour@kalamun.net>
 * @ilCtrl_isCalledBy ilTrainingDashboardPluginGUI: ilPCPluggedGUI
 * @ilCtrl_isCalledBy ilTrainingDashboardPluginGUI: ilUIPluginRouterGUI
 */
class ilTrainingDashboardPluginGUI extends ilPageComponentPluginGUI
{
    protected /* ilLanguage */ $lng;
    protected ilCtrl $ctrl;
    protected ilGlobalTemplateInterface $tpl;
    protected ilTree $tree;
    protected ilObjectService $object;
    protected ilObjUser $user;

    public function __construct()
    {
        global $DIC;

        parent::__construct();

        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC['tpl'];
        $this->tree = $DIC->repositoryTree();
        $this->object = $DIC->object();
        $this->user = $DIC['ilUser'];
    }

    /**
     * Execute command
     */
    public function executeCommand() : void
    {
        $next_class = $this->ctrl->getNextClass();

        switch ($next_class) {
            default:
                // perform valid commands
                $cmd = $this->ctrl->getCmd();
                if (in_array($cmd, array("create", "save", "edit", "update", "cancel"))) {
                    $this->$cmd();
                }
                break;
        }
    }

    /**
     * Create
     */
    public function insert() : void
    {
        $form = $this->initForm(true);
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Save new pc example element
     */
    public function create() : void
    {
        $form = $this->initForm(true);
        if ($this->saveForm($form, true)) {
            ;
        }
        {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt("msg_obj_modified"), true);
            $this->returnToParent();
        }
        $form->setValuesByPost();
        $this->tpl->setContent($form->getHTML());
    }

    public function edit() : void
    {
        $form = $this->initForm();

        $this->tpl->setContent($form->getHTML());
    }

    public function update() : void
    {
        $form = $this->initForm(false);
        if ($this->saveForm($form, false)) {
            ;
        }
        {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt("msg_obj_modified"), true);
            $this->returnToParent();
        }
        $form->setValuesByPost();
        $this->tpl->setContent($form->getHTML());
    }

    protected function getRootCourseId() {
        $current_ref_id = $_GET['ref_id'];

        $root_course = false;
        for ($ref_id = $current_ref_id; $ref_id; $ref_id = $this->tree->getParentNodeData($current_ref_id)['ref_id'] ) {
            $node_data = $this->tree->getNodeData($ref_id);
            if (empty($node_data) || $node_data["type"] == "crs") {
                $root_course = $node_data;
                break;
            }
        }

        return $root_course['ref_id'];
    }

    /**
     * Init editing form
     */
    protected function initForm(bool $a_create = false) : ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();

        // title
        $input_title = new ilTextInputGUI($this->lng->txt("title"), 'title');
        $input_title->setMaxLength(255);
        $input_title->setSize(40);
        $input_title->setRequired(false);
        $form->addItem($input_title);
        
        // description
        $input_description = new ilTextInputGUI($this->lng->txt("description"), 'description');
        $input_description->setMaxLength(255);
        $input_description->setSize(40);
        $input_description->setRequired(false);
        $form->addItem($input_description);
        
        // save and cancel commands
        if ($a_create) {
            $this->addCreationButton($form);
            $form->addCommandButton("cancel", $this->lng->txt("cancel"));
            $form->setTitle($this->plugin->getPluginName());
        } else {
            $prop = $this->getProperties();
            $input_title->setValue($prop['title']);
            $input_description->setValue($prop['description']);

            $form->addCommandButton("update", $this->lng->txt("save"));
            $form->addCommandButton("cancel", $this->lng->txt("cancel"));
            $form->setTitle($this->plugin->getPluginName());
        }

        $form->setFormAction($this->ctrl->getFormAction($this));
        return $form;
    }

    protected function saveForm(ilPropertyFormGUI $form, bool $a_create) : bool
    {
        if ($form->checkInput()) {
            $properties = $this->getProperties();

            $properties['title'] = $form->getInput('title');
            $properties['description'] = $form->getInput('description');

            if ($a_create) {
                return $this->createElement($properties);
            } else {
                return $this->updateElement($properties);
            }
        }

        return false;
    }

    /**
     * Cancel
     */
    public function cancel()
    {
        $this->returnToParent();
    }

    /**
     * Get HTML for element
     * @param string    page mode (edit, presentation, print, preview, offline)
     * @return string   html code
     */
    public function getElementHTML(/* string */ $a_mode, /* array */ $a_properties, /* string */ $a_plugin_version) /* : string */
    {
        $title = !empty($a_properties['title']) ? $a_properties['title'] : ($obj ? $obj->getTitle() : "");
        $description = !empty($a_properties['description']) ? $a_properties['description'] : ($obj ? $obj->getDescription() : "");

        ob_start();
        ?>
        <div class="kalamun-training-dashboard">
            <div class="kalamun-training-dashboard_body">
                <div class="kalamun-training-dashboard_title">
                    <?= $title; ?>
                </div>
                <?php
                if (!empty($description)) { ?>
                    <div class="kalamun-training-dashboard_description"><?= $description; ?></div>
                <?php }
                ?>
                </div>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        return $html;
    }
}