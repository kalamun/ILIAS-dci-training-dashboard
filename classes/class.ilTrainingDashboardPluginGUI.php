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
    protected ilLanguage $lng;
    protected ilCtrl $ctrl;
    protected ilGlobalTemplateInterface $tpl;
    protected ilTree $tree;
    protected ilObjectService $object;
    protected ilObjUser $user;
    protected $db;
    protected dciCourse $dciCourse;

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
        $this->db = $DIC->database();

        $this->dciCourse = new dciCourse();
    }

    /**
     * Execute command
     */
    public function executeCommand(): void
    {
        $next_class = $this->ctrl->getNextClass();

        switch ($next_class) {
            default:
                // perform valid commands
                $cmd = $this->ctrl->getCmd();
                if (in_array($cmd, array("create", "create_plug", "save", "edit", "update", "cancel", "downloadFile"))) {
                    $this->$cmd();
                }
                break;
        }
    }

    /**
     * Create
     */
    public function insert(): void
    {
        $form = $this->initForm(true);
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Save new pc example element
     */
    public function create(): void
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

    public function edit(): void
    {
        $form = $this->initForm();

        $this->tpl->setContent($form->getHTML());
    }

    public function update(): void
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

    protected function getRootCourseId()
    {
        $current_ref_id = $_GET['ref_id'];

        $root_course = false;
        for ($ref_id = $current_ref_id; $ref_id; $ref_id = $this->tree->getParentNodeData($current_ref_id)['ref_id']) {
            $node_data = $this->tree->getNodeData($ref_id);
            if (empty($node_data) || $node_data["type"] == "crs") {
                $root_course = $node_data;
                break;
            }
        }

        return $root_course['ref_id'];
    }

    private function getFileUrlById($image_id) {
		$image_url = false;
		if (empty($image_id)) return $image_url;

		$fileObj = new ilObjFile($image_id, false);
		if (!empty($fileObj)) {
			$_SESSION[__CLASS__]['allowedFiles'][$fileObj->getId()] = true;
			$this->ctrl->setParameter($this, 'id', $fileObj->getId());
			$image_url = $this->ctrl->getLinkTargetByClass(['ilUIPluginRouterGUI', 'ilTrainingDashboardPluginGUI'], 'downloadFile');
		}
		return $image_url;
	}

    /**
     * Init editing form
     */
    protected function initForm(bool $a_create = false): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();

        // title
        $input_title = new ilTextInputGUI($this->plugin->txt("title"), "title");
        $input_title->setMaxLength(255);
        $input_title->setSize(40);
        $input_title->setRequired(false);
        $form->addItem($input_title);

        // description
        $input_description = new ilTextInputGUI($this->plugin->txt("description"), "description");
        $input_description->setMaxLength(255);
        $input_description->setSize(40);
        $input_description->setRequired(false);
        $form->addItem($input_description);

        // filter: user courses, all courses
        $input_filter = new ilSelectInputGUI($this->plugin->txt("show"), "show");
        $input_filter->setOptions(["user" => $this->plugin->txt("user-courses"), "all" => $this->plugin->txt("all-courses")]);
        $form->addItem($input_filter);

        // sorting: alphabetical, last access
        $input_sort = new ilSelectInputGUI($this->plugin->txt("sort"), "sort");
        $input_sort->setOptions(["alphabetical" => $this->plugin->txt("alphabetical"), "last_visited" => $this->plugin->txt("last_visited")]);
        $form->addItem($input_sort);

        // limit (number of cards to show, 0 = all)
        $input_limit = new ilNumberInputGUI($this->plugin->txt("limit"), "limit");
        $input_limit->setClientSideValidation(true);
        $input_limit->setInfo($this->plugin->txt("limit-info"));
        $input_limit->setSize(4);
        $input_limit->setMinvalueShouldBeGreater(0);
        $input_limit->setMaxvalueShouldBeLess(30);
        $input_limit->setRequired(false);
        $form->addItem($input_limit);
        
        // layout: card or banner
        $input_layout = new ilSelectInputGUI($this->plugin->txt("layout"), "layout");
        $input_layout->setOptions(["card" => $this->plugin->txt("card"), "banner" => $this->plugin->txt("banner")]);
        $form->addItem($input_layout);

        // background color
        $input_bkg = new ilColorPickerInputGUI($this->plugin->txt("background"), "background");
        $input_bkg->setDefaultColor("003b5d");
        $input_bkg->setRequired(false);
        $form->addItem($input_bkg);

        // backgroud image
		$input_bkg_image = new ilImageFileInputGUI($this->plugin->txt("background-image"), 'background_image_id');
		$input_bkg_image->setAllowDeletion(true);
		$input_bkg_image->setRequired(false);
		$form->addItem($input_bkg_image);

        // save and cancel commands
        if ($a_create) {
            $input_limit->setValue(0);
            $this->addCreationButton($form);
            $form->addCommandButton("cancel", $this->lng->txt("cancel"));
            $form->setTitle($this->plugin->getPluginName());
        } else {
            $prop = $this->getProperties();
            if (empty($prop['filter'])) $prop['filter'] = "";
            if (empty($prop['sort'])) $prop['sort'] = "alphabetical";
            if (empty($prop['limit'])) $prop['limit'] = 0;
            if (empty($prop['layout'])) $prop['layout'] = "card";
            if (empty($prop['background'])) $prop['background'] = "003b5d";
            if (empty($prop['background_image_id'])) $prop['background_image_id'] = false;

            $input_title->setValue($prop['title']);
            $input_description->setValue($prop['description']);
            $input_filter->setValue($prop['filter']);
            $input_sort->setValue($prop['sort']);
            $input_limit->setValue($prop['limit']);
            $input_layout->setValue($prop['layout']);
            $input_bkg->setValue($prop['background']);
            $image_url = !empty($prop['background_image_id']) ? $this->getFileUrlById($prop['background_image_id']) : false;
            if (!empty($image_url)) $input_bkg_image->setImage($image_url);

            $form->addCommandButton("update", $this->lng->txt("save"));
            $form->addCommandButton("cancel", $this->lng->txt("cancel"));
            $form->setTitle($this->plugin->getPluginName());
        }

        $form->setFormAction($this->ctrl->getFormAction($this));
        return $form;
    }

    protected function saveForm(ilPropertyFormGUI $form, bool $a_create): bool
    {
        if ($form->checkInput()) {
            $properties = $this->getProperties();

            $properties['title'] = $form->getInput('title');
            $properties['description'] = $form->getInput('description');
            $properties['sort'] = $form->getInput('sort');
            $properties['limit'] = $form->getInput('limit');
            $properties['layout'] = $form->getInput('layout');
            $properties['filter'] = $form->getInput('filter');
            $properties['background'] = $form->getInput('background');

            $fields = [
                "background_image_id",
            ];

            foreach($fields as $key) {
                if (!empty($_FILES[$key]["name"])) {
                        $old_file_id = empty($properties[$key]) ? null : $properties[$key];
                        
                        $fileObj = new ilObjFile((int) $old_file_id, false);
                        $fileObj->setType("file");
                        $fileObj->setTitle($_FILES[$key]["name"]);
                        $fileObj->setDescription("");
                        $fileObj->setFileName($_FILES[$key]["name"]);
                        $fileObj->setMode("filelist");
                        if (empty($old_file_id)) {
                                $fileObj->create();
                        } else {
                                $fileObj->update();
                        }

                        // upload file to filesystem
                        if ($_FILES[$key]["tmp_name"] !== "") {
                                $fileObj->getUploadFile(
                                        $_FILES[$key]["tmp_name"],
                                        $_FILES[$key]["name"]
                                );
                        }

                        $properties[$key] = $fileObj->getId();
                }
            }

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
    public function getElementHTML( string $a_mode, array $a_properties, string $a_plugin_version) : string
    {
        global $DIC;
        $ctrl = $DIC->ctrl();
        $db = $DIC->database();
        
        $title = !empty($a_properties['title']) ? $a_properties['title'] : "";
        $description = !empty($a_properties['description']) ? $a_properties['description'] : "";
        $order = !empty($a_properties['sort']) ? $a_properties['sort'] : "alphabetical";
        $limit = !empty($a_properties['limit']) ? $a_properties['limit'] : 0;
        $layout = !empty($a_properties['layout']) ? $a_properties['layout'] : "card";
        $background = !empty($a_properties['background']) ? $a_properties['background'] : "003b5d";
        $background_image_id = !empty($a_properties['background_image_id']) ? $a_properties['background_image_id'] : false;
        $background_image = $background_image_id ? '/' . $this->getFileUrlById($background_image_id) : false;

        /* courses */
        if (empty($a_properties['filter']) || $a_properties['filter'] == "user") {
            // user's courses
            $courses = static::getCoursesOfUser($this->user->getId(), $limit);
        } else {
            // all courses
            $courses = static::getAllCourses($limit);
        }

        foreach ($courses as $i => $course) {
            $ref_id = $course['ref_id'];
            $obj = ilObjectFactory::getInstanceByRefId($ref_id, false);

            if (empty($obj) || $obj->getOfflineStatus()) {
                continue;
            }

            $courses[$i]['obj_id'] = $obj->getId();
            $courses[$i]['title'] = $obj->getTitle();
            $courses[$i]['type'] = $obj->getType();
            $courses[$i]['description'] = $obj->getDescription();
            $courses[$i]['lp'] = ilLearningProgress::_getProgress($this->user->getId(), $courses[$i]['obj_id']);

            
            if ($order === "last_visited" && empty($courses[$i]['lp']['access_time'])) unset($courses[$i]);
        }

        if ($a_mode == "presentation" && count($courses) == 0) {
            return "<div></div>";
        }

        if (empty($order) || $order === "alphabetical") {
            usort($courses, function($a, $b) {
                return strcasecmp($a['title'], $b['title']);
            });

        } elseif ($order === "last_visited") {
            usort($courses, function($a, $b) {
                return $b['lp']['access_time'] - $a['lp']['access_time'];
            });
        }

        ob_start();
        ?>
        <div class="kalamun-training-dashboard" data-layout="<?= $layout; ?>" style="<?= !empty($background) ? '--background-color: #'. str_replace('"', '', $background) . ';': '' ?><?= !empty($background_image) ? '--background-image: url(\''. str_replace('"', '', $background_image) . '\')' : '' ?>">
            <div class="kalamun-training-dashboard-scrolldown"><span class="icon-down"></span></div>
            <div class="kalamun-training-dashboard_body">
                <div class="kalamun-training-dashboard_title">
                    <h2><?= $title; ?></h2>
                    <?php
                    if (!empty($description)) {?>
                        <div class="kalamun-training-dashboard_description"><?= $description; ?></div>
                    <?php }
                    ?>
                </div>
                <div class="kalamun-training-dashboard_courses">
                    <?php
                    if ($layout == "card") {
                        ?>
                        <div class="dashboard splide">
                            <div class="splide__track">
                                <ul class="splide__list">
                                    <?php
                                    foreach ($courses as $course) {
                                        $ref_id = $course['ref_id'];
                                        $obj_id = $course['obj_id'];
                                        if (empty($obj_id)) {
                                            continue;
                                        }
                                        
                                        $mandatory_objects = $this->dciCourse->get_mandatory_objects($obj_id);
                                        $completed_objects_count = count(array_filter($mandatory_objects, fn($k) => $k['completed'] ));

                                        $type = $course['type'];
                                        $title = $course['title'];
                                        $description = $course['description'];
                                        $tile_image_exists = false;

                                        if (class_exists("ilCourseCoverGUI")) {
                                            // use square cover defined by the CourseCover plugin, if available
                                            $courseCover = new ilCourseCoverGUI();
                                            $tile_image_path = $courseCover->getCoverURL($ref_id, "square");
                                            $tile_image_exists = !empty($tile_image_path);
                                        }
                                        
                                        if (empty($tile_image_exists)) {
                                            // use tile image as cover
                                            $obj_properties = \ILIAS\Object\ilObjectDIC::dic()['object_properties_agregator']->getFor($obj_id, $type);
                                            $tile_image_path = $obj_properties->getPropertyTileImage()->getTileImage()->getSrcUrlForLegacyForm();
                                            $tile_image_exists = !empty($tile_image_path);
                                        }

                                        $ctrl->setParameterByClass("ilrepositorygui", "ref_id", $ref_id);
                                        $permalink = $ctrl->getLinkTargetByClass("ilrepositorygui", "view");

                                        $course_tabs = dciSkin_tabs::getCourseTabs($ref_id);
                                        $mandatory_cards_count = 0;
                                        $completed_cards_count = 0;
                                        
                                        foreach ($course_tabs as $page) {
                                            $mandatory_cards_count += $page['cards_mandatory'];
                                            $completed_cards_count += $page['cards_completed'];
                                        }

                                        foreach ($course_tabs as $page) {
                                            if (!$page['completed']) {
                                                // $permalink = $page['permalink'];
                                                break;
                                            }
                                        }

                                        /* progress statuses:
                                        0 = attempt
                                        1 = in progress;
                                        2 = completed;
                                        3 = failed;
                                        */
                                        $lp = $course['lp'];
                                        $lp_status = ilLPStatusCollection::_lookupStatus($obj_id, $this->user->getId());
                                        $lp_percent = ilLPStatusCollection::_lookupPercentage($obj_id, $this->user->getId());
                                        $lp_in_progress = !empty(ilLPStatusCollection::_lookupInProgressForObject($obj_id, [$this->user->getId()]));
                                        $lp_completed = ilLPStatusCollection::_hasUserCompleted($obj_id, $this->user->getId());
                                        $lp_failed = !empty(ilLPStatusCollection::_lookupFailedForObject($obj_id, [$this->user->getId()]));
                                        $lp_downloaded = !empty($lp['visits']) && $type == "file";
                                        
                                        if (empty($lp['spent_seconds'])) $lp['spent_seconds'] = 0;

                                        $typical_learning_time = ilMDEducational::_getTypicalLearningTimeSeconds($obj_id);

                                        ?>
                                        <li class="splide__slide">
                                            <div class="kalamun-training-dashboard_course" data-permalink="<?= $permalink; ?>">
                                                <div class="kalamun-training-dashboard_thumb">
                                                    <?= ($tile_image_exists ? '<a href="' . $permalink . '"><img src="' . $tile_image_path . '"></a>' : '<span class="empty-thumb"></span>'); ?>
                                                </div>
                                                <?php
                                                if ($mandatory_cards_count > 0) {
                                                    ?>
                                                    <div class="kalamun-training-dashboard_progress-bar">
                                                        <meter min="0" max="0" value="<?= round(100 / $mandatory_cards_count * $completed_cards_count); ?>"></meter>
                                                        <span class="progress">
                                                            <?= round(100 / $mandatory_cards_count * $completed_cards_count); ?>%
                                                        </span>
                                                    </div>
                                                    <?php
                                                }
                                                ?>
                                                <div class="kalamun-training-dashboard_course_body">
                                                    <div class="kalamun-training-dashboard_heading">
                                                        <h3><?= $title; ?></h3>
                                                    </div>
                                                    <div class="kalamun-training-dashboard_course_meta">
                                                        <p class="kalamun-training-dashboard_title"><?= $title; ?></p>
                                                        <?php
                                                        if (!empty($description)) {
                                                            ?><p><?= $description; ?></p><?php
                                                        }
                                                        ?>
                                                        <div class="kalamun-training-dashboard_course_progress">
                                                            <div class="kalamun-training-dashboard_course_progress_line time">
                                                                <?php
                                                                $time_spent = [
                                                                    "h" => floor(($lp['spent_seconds'] / 60) / 60),
                                                                    "m" => floor($lp['spent_seconds'] / 60) % 60,
                                                                ];
                                                                if ($time_spent["m"] == 0 && $time_spent["h"] == 0) $this->plugin->txt('not_started_yet');
                                                                else {
                                                                    echo '<h6>' . $this->plugin->txt('time_spent') . '</h6>';
                                                                    echo '<span><span class="icon-picto_timer_start"></span></span>';
                                                                    echo '<div>';
                                                                        if ($time_spent["h"] > 0) echo $time_spent["h"] . ' ' . $this->plugin->txt('hours') . '<br>';
                                                                        if ($time_spent["m"] > 0) echo $time_spent["m"] . ' ' . $this->plugin->txt('minutes');
                                                                    echo '</div>';
                                                                }
                                                                ?>
                                                            </div>
                                                            <?php
                                                            if (!empty($typical_learning_time)) {
                                                                ?>
                                                                <div class="kalamun-training-dashboard_course_progress_line learning-time">
                                                                    <?php
                                                                    $time_spent = [
                                                                        "h" => floor(($typical_learning_time / 60) / 60),
                                                                        "m" => floor($typical_learning_time / 60) % 60,
                                                                    ];
                                                                    echo '<h6>' . $this->plugin->txt('course_estimated_learning_time') . '</h6>';
                                                                    echo '<span><span class="icon-picto_timer"></span></span>';
                                                                    echo '<div>';
                                                                        if ($time_spent["h"] > 0) echo $time_spent["h"] . ' ' . $this->plugin->txt('hours') . '<br>';
                                                                        if ($time_spent["m"] > 0) echo $time_spent["m"] . ' ' . $this->plugin->txt('minutes');
                                                                    echo '</div>';
                                                                    ?>
                                                                </div>
                                                                <?php
                                                            }
                                                            ?>
                                                        </div>
                                                        <div class="kalamun-training-dashboard_course_cta">
                                                            <a href="<?= $permalink; ?>"><button><?= $this->plugin->txt($lp['spent_seconds'] > 60 ? 'continue' : 'start'); ?> <span class="icon-right"></span></button></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                        <?php
                                    }
                                    ?>
                                </ul>
                            </div>
                        </div>
                        <?php

                    } elseif ($layout == "banner") {
                        ?>
                        <div class="dashboard">
                            <div class="dashboard__inner">
                                <ul class="dashboard__banners">
                                    <?php
                                    foreach ($courses as $course) {
                                        $ref_id = $course['ref_id'];
                                        $obj = ilObjectFactory::getInstanceByRefId($ref_id, false);
                                        if (empty($obj) || $obj->getOfflineStatus()) {
                                            continue;
                                        }
                                        $obj_id = $obj->getId();
                                        
                                        $from_cache = dciSkin_cache::get('ilTrainingDashboardPluginGUI::getCourseCard', $obj_id);
                                            if (dciSkin_cache::is_valid($from_cache)) {
                                                $card = $from_cache;
                                            } else {
                                                $card                            = [];
                                                $card['mandatory_objects']       = $this->dciCourse->get_mandatory_objects($obj_id);
                                                $card['completed_objects_count'] = count(array_filter($card['mandatory_objects'], fn($k) => $k['completed']));

                                                $card['type']                 = $obj->getType();
                                                $card['title']                = $obj->getTitle();
                                                $card['description']          = $obj->getDescription();
                                                $tile_image                   = $obj->getObjectProperties()->getPropertyTileImage()->getTileImage();
                                                $card['tile_image_full_path'] = $tile_image->getSrcUrlForLegacyForm();
                                                $card['tile_image_exists']    = !empty($card['tile_image_full_path']);
                                                $ctrl->setParameterByClass("ilrepositorygui", "ref_id", $ref_id);
                                                $card['permalink'] = $ctrl->getLinkTargetByClass("ilrepositorygui", "view");

                                                $card['course_tabs']           = dciSkin_tabs::getCourseTabs($ref_id, $this->plugin->txt("progress_status"));
                                                $card['mandatory_cards_count'] = 0;
                                                $card['completed_cards_count'] = 0;

                                                foreach ($card['course_tabs'] as $page) {
                                                    $card['mandatory_cards_count'] += $page['cards_mandatory'];
                                                    $card['completed_cards_count'] += $page['cards_completed'];
                                                }

                                                foreach ($card['course_tabs'] as $page) {
                                                    if (! $page['completed']) {
                                                        // $permalink = $page['permalink'];
                                                        break;
                                                    }
                                                }

                                                /* progress statuses:
                                                0 = attempt
                                                1 = in progress;
                                                2 = completed;
                                                3 = failed;
                                                */
                                                $card['lp']             = ilLearningProgress::_getProgress($this->user->getId(), $obj_id);
                                                $card['lp_status']      = ilLPStatusCollection::_lookupStatus($obj_id, $this->user->getId());
                                                $card['lp_percent']     = ilLPStatusCollection::_lookupPercentage($obj_id, $this->user->getId());
                                                $card['lp_in_progress'] = ! empty(ilLPStatusCollection::_lookupInProgressForObject($obj_id, [$this->user->getId()]));
                                                $card['lp_completed']   = ilLPStatusCollection::_hasUserCompleted($obj_id, $this->user->getId());
                                                $card['lp_failed']      = ! empty(ilLPStatusCollection::_lookupFailedForObject($obj_id, [$this->user->getId()]));
                                                $card['lp_downloaded']  = $lp['visits'] > 0 && $type == "file";

                                                $card['typical_learning_time'] = ilMDEducational::_getTypicalLearningTimeSeconds($obj_id);

                                                dciSkin_cache::add('ilTrainingDashboardPluginGUI::getCourseCard', $card, $obj_id);
                                            }
                                        ?>
                                    <li class="splide__slide">
                                        <div class="kalamun-training-dashboard_course" data-permalink="<?php echo $permalink; ?>">
                                            <div class="kalamun-training-dashboard_thumb">
                                                <?php echo($card['tile_image_exists'] ? '<a href="' . $permalink . '" title="' . addslashes($title) . '"><img src="' . $card['tile_image_full_path'] . '"></a>' : '<span class="empty-thumb"></span>'); ?>
                                            </div>
                                            <?php
                                                if ($card['mandatory_cards_count'] > 0) {
                                                            ?>
                                                <div class="kalamun-training-dashboard_progress-bar">
                                                    <meter min="0" max="0" value="<?php echo round(100 / $card['mandatory_cards_count'] * $card['completed_cards_count']); ?>"></meter>
                                                    <span class="progress">
                                                        <?php echo round(100 / $card['mandatory_cards_count'] * $card['completed_cards_count']); ?>%
                                                    </span>
                                                </div>
                                                <?php
                                                    }
                                                            ?>
                                            <div class="kalamun-training-dashboard_course_body">
                                                <div class="kalamun-training-dashboard_heading">
                                                    <h3><?php echo $card['title']; ?></h3>
                                                </div>
                                                <div class="kalamun-training-dashboard_course_meta">
                                                    <p class="kalamun-training-dashboard_title"><?php echo $title; ?></p>
                                                    <?php
                                                    if (! empty($card['description'])) {
                                                        ?><p><?php echo $card['description']; ?></p><?php
                                                    }
                                                    ?>
                                                    <div class="kalamun-training-dashboard_course_progress">
                                                        <div class="kalamun-training-dashboard_course_progress_line time">
                                                            <?php
                                                                $time_spent = [
                                                                                "h" => floor(($card['lp']['spent_seconds'] / 60) / 60),
                                                                                "m" => floor($card['lp']['spent_seconds'] / 60) % 60,
                                                                            ];
                                                                            if ($time_spent["m"] == 0 && $time_spent["h"] == 0) {
                                                                                $this->plugin->txt('not_started_yet');
                                                                            } else {
                                                                                echo '<h6>' . $this->plugin->txt('time_spent') . '</h6>';
                                                                                echo '<span><span class="icon-picto_timer_start"></span></span>';
                                                                                echo '<div>';
                                                                                if ($time_spent["h"] > 0) {
                                                                                    echo $time_spent["h"] . ' ' . $this->plugin->txt('hours') . '<br>';
                                                                                }

                                                                                if ($time_spent["m"] > 0) {
                                                                                    echo $time_spent["m"] . ' ' . $this->plugin->txt('minutes');
                                                                                }

                                                                                echo '</div>';
                                                                            }
                                                                        ?>
                                                        </div>
                                                        <?php
                                                            if (! empty($card['typical_learning_time'])) {
                                                                        ?>
                                                            <div class="kalamun-training-dashboard_course_progress_line learning-time">
                                                                <?php
                                                                    $time_spent = [
                                                                                        "h" => floor(($card['typical_learning_time'] / 60) / 60),
                                                                                        "m" => floor($card['typical_learning_time'] / 60) % 60,
                                                                                    ];
                                                                                    echo '<h6>' . $this->plugin->txt('course_estimated_learning_time') . '</h6>';
                                                                                    echo '<span><span class="icon-picto_timer"></span></span>';
                                                                                    echo '<div>';
                                                                                    if ($time_spent["h"] > 0) {
                                                                                        echo $time_spent["h"] . ' ' . $this->plugin->txt('hours') . '<br>';
                                                                                    }

                                                                                    if ($time_spent["m"] > 0) {
                                                                                        echo $time_spent["m"] . ' ' . $this->plugin->txt('minutes');
                                                                                    }

                                                                                    echo '</div>';
                                                                                ?>
                                                            </div>
                                                            <?php
                                                                }
                                                                        ?>
                                                    </div>
                                                    <div class="kalamun-training-dashboard_course_cta">
                                                        <a href="<?php echo $card['permalink']; ?>"><button><?php echo $this->plugin->txt($card['lp']['spent_seconds'] > 60 ? 'continue' : 'start'); ?> <span class="icon-right"></span></button></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <?php
                                    }
                                ?>
                                </ul>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <script>
        document.addEventListener( 'DOMContentLoaded', function() {
            var dashboard_splide = new Splide( '.dashboard.splide', {
                perPage: 3,
            });

            dashboard_splide.on( 'overflow', function ( isOverflow ) {
                dashboard_splide.options = {
                    arrows    : isOverflow,
                    pagination: isOverflow,
                    drag      : isOverflow,
                };
            } );

            dashboard_splide.mount();
        } );
        </script>
        <?php
        $html = ob_get_clean();
        return $html;
    }

    public static function getAllCourses(int $limit = 0): array {
        $query = "SELECT a.obj_id, b.deleted FROM object_data AS a RIGHT JOIN object_reference AS b ON a.obj_id = b.obj_id WHERE a.type='crs' AND a.offline = 0  AND b.deleted IS NULL";
        if ($limit > 0) $query .= " LIMIT " . intval($limit);

        global $DIC;
        $db = $DIC->database();
        $db_query = $db->query($query);

        $output = [];
        while ($result = $db->fetchAssoc($db_query)) {
            $ref_id = ilObject::_getAllReferences($result['obj_id']);
            if (is_array($ref_id) && count($ref_id)) {
                $ref_id = array_pop($ref_id);
                $output[] = [
                    'obj_id' => $result['obj_id'],
                    'ref_id' => $ref_id,
                ];
            }
        }

        return $output;
    }

    public static function getCoursesOfUser(
        int $a_user_id, int $limit = 0
    ): array {
        global $DIC;
        $tree = $DIC->repositoryTree();

        // limit = 0 means all, so set a huge number
        if ($limit == 0) $limit = 9999;

        // see ilPDSelectedItemsBlockGUI
        $items = ilParticipants::_getMembershipByType($a_user_id, ['crs']);

        $references = [];
        $lp_obj_refs = [];
        $count = 0;
        foreach ($items as $obj_id) {
            if ($count >= $limit) break;
            $count++;

            $ref_id = ilObject::_getAllReferences($obj_id);
            if (is_array($ref_id) && count($ref_id)) {
                $ref_id = array_pop($ref_id);
                if (!$tree->isDeleted($ref_id)) {
                    $visible = false;
                    $active = ilObjCourseAccess::_isActivated($obj_id, $visible, false);
                    if ($active && $visible) {
                        $references[$ref_id] = [
                            'ref_id' => $ref_id,
                            'obj_id' => $obj_id,
                            'title' => ilObject::_lookupTitle($obj_id),
                        ];
                        $lp_obj_refs[$obj_id] = $ref_id;
                    }
                }
            }
        }
        
        if (count($lp_obj_refs)) {
            // listing the objectives should NOT depend on any LP status / setting
            foreach ($lp_obj_refs as $obj_id => $ref_id) {
                // only if set in DB (default mode is not relevant
                if (ilObjCourse::_lookupViewMode($obj_id) === ilCourseConstants::IL_CRS_VIEW_OBJECTIVE) {
                    $references[$ref_id]["objectives"] = self::parseObjectives($obj_id, $a_user_id);
                }
            }

            // LP must be active, personal and not anonymized
            if (ilObjUserTracking::_enabledLearningProgress() &&
                ilObjUserTracking::_enabledUserRelatedData() &&
                ilObjUserTracking::_hasLearningProgressLearner()) {
                // see ilLPProgressTableGUI
                $lp_data = ilTrQuery::getObjectsStatusForUser($a_user_id, $lp_obj_refs);
                foreach ($lp_data as $item) {
                    $ref_id = $item["ref_ids"];
                    $references[$ref_id]["lp_status"] = $item["status"];
                }
            }
        }

        return $references;
    }

    protected static function parseObjectives(
        int $a_obj_id,
        int $a_user_id
    ): array {
        $res = array();

        // we need the collection for the correct order
        $coll_objtv = new ilLPCollectionOfObjectives($a_obj_id, ilLPObjSettings::LP_MODE_OBJECTIVES);
        $coll_objtv = $coll_objtv->getItems();
        if ($coll_objtv) {
            // #13373
            $lo_results = static::parseLOUserResults($a_obj_id, $a_user_id);

            $lo_ass = ilLOTestAssignments::getInstance($a_obj_id);

            $tmp = array();

            foreach ($coll_objtv as $objective_id) {
                /** @var array $title */
                $title = ilCourseObjective::lookupObjectiveTitle($objective_id, true);

                $tmp[$objective_id] = array(
                    "id" => $objective_id,
                    "title" => $title["title"],
                    "desc" => $title["description"],
                    "itest" => $lo_ass->getTestByObjective($objective_id, ilLOSettings::TYPE_TEST_INITIAL),
                    "qtest" => $lo_ass->getTestByObjective($objective_id, ilLOSettings::TYPE_TEST_QUALIFIED)
                );

                if (array_key_exists($objective_id, $lo_results)) {
                    $lo_result = $lo_results[$objective_id];
                    $tmp[$objective_id]["user_id"] = $lo_result["user_id"];
                    $tmp[$objective_id]["result_perc"] = $lo_result["result_perc"] ?? null;
                    $tmp[$objective_id]["limit_perc"] = $lo_result["limit_perc"] ?? null;
                    $tmp[$objective_id]["status"] = $lo_result["status"] ?? null;
                    $tmp[$objective_id]["type"] = $lo_result["type"] ?? null;
                    $tmp[$objective_id]["initial"] = $lo_result["initial"] ?? null;
                }
            }

            // order
            foreach ($coll_objtv as $objtv_id) {
                $res[] = $tmp[$objtv_id];
            }
        }

        return $res;
    }

    // see ilContainerObjectiveGUI::parseLOUserResults()
    protected static function parseLOUserResults(
        int $a_course_obj_id,
        int $a_user_id
    ): array {
        $res = array();
        $initial_status = "";

        $lur = new ilLOUserResults($a_course_obj_id, $a_user_id);
        foreach ($lur->getCourseResultsForUserPresentation() as $objective_id => $types) {
            // show either initial or qualified for objective
            if (isset($types[ilLOUserResults::TYPE_INITIAL])) {
                $initial_status = $types[ilLOUserResults::TYPE_INITIAL]["status"];
            }

            // qualified test has priority
            if (isset($types[ilLOUserResults::TYPE_QUALIFIED])) {
                $result = $types[ilLOUserResults::TYPE_QUALIFIED];
                $result["type"] = ilLOUserResults::TYPE_QUALIFIED;
                $result["initial"] = $types[ilLOUserResults::TYPE_INITIAL] ?? null;
            } else {
                $result = $types[ilLOUserResults::TYPE_INITIAL];
                $result["type"] = ilLOUserResults::TYPE_INITIAL;
            }

            $result["initial_status"] = $initial_status;

            $res[$objective_id] = $result;
        }

        return $res;
    }

    public static function get_first_sentence($string) {
        $array = preg_split('/(^.*\w+.*[\.\?!][\s])/m', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
        return trim($array[0] . $array[1]);
    }

	/**
	 * download file of file lists
	 */
	public function downloadFile() : void
	{
			$file_id = (int) $_GET['id'];
			if ($_SESSION[__CLASS__]['allowedFiles'][$file_id]) {
					$fileObj = new ilObjFile($file_id, false);
					$fileObj->sendFile();
			} else {
					throw new ilException('not allowed');
			}
	}
}
