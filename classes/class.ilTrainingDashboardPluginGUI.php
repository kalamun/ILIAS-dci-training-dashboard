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

        $this->dciCourse = new dciCourse();

        //require_once('./Services/Calendar/classes/class.ilDateTime.php');
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
                if (in_array($cmd, array("create", "save", "edit", "update", "cancel"))) {
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

    /**
     * Init editing form
     */
    protected function initForm(bool $a_create = false): ilPropertyFormGUI
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

    protected function saveForm(ilPropertyFormGUI $form, bool $a_create): bool
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
    public function getElementHTML( /* string */$a_mode, /* array */ $a_properties, /* string */ $a_plugin_version) /* : string */
    {
        global $DIC;
        $ctrl = $DIC->ctrl();
        $db = $DIC->database();
        
        $title = !empty($a_properties['title']) ? $a_properties['title'] : "";
        $description = !empty($a_properties['description']) ? $a_properties['description'] : "";

        /* courses */
        $courses = static::getCoursesOfUser($this->user->getId());

        $owner = [];
        foreach($courses as $course) {
            $owner[] = "cc.obj_id = " . $course['obj_id'];
        }

        // prevent SQL syntax errors when user has no courses
        if (count($owner) == 0) {
            $owner[] = "cc.obj_id = 0";
        }

        $query = "SELECT *, ce.title as event_title FROM cal_entries ce" .
            " JOIN cal_cat_assignments cca ON ce.cal_id = cca.cal_id" .
            " JOIN cal_categories cc ON cca.cat_id = cc.cat_id" .
            " WHERE cc.type = 2 AND (" . implode(" OR ", $owner) . ") AND ce.starta >= NOW() ORDER BY ce.starta ASC LIMIT 3";

        $res = $db->query($query);
        $calendar_entries = [];
        while ($entry = $res->fetch(ilDBConstants::FETCHMODE_OBJECT)) {
            $calendar_entries[] = $entry;
        }

        ob_start();
        ?>
        <div class="kalamun-training-dashboard">
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
                    <div class="dashboard splide">
                        <div class="splide__track">
                            <ul class="splide__list">
                                <?php
                                foreach ($courses as $course) {
                                    $ref_id = $course['ref_id'];
                                    $obj = ilObjectFactory::getInstanceByRefId($ref_id);
                                    if (empty($obj) || $obj->getOfflineStatus()) {
                                        continue;
                                    }
                                    $obj_id = $obj->getId();
                                    
                                    $mandatory_objects = $this->dciCourse->get_mandatory_objects($obj_id);
                                    $completed_objects_count = count(array_filter($mandatory_objects, fn($k) => $k['completed'] ));

                                    $type = $obj->getType();
                                    $title = $obj->getTitle();
                                    $description = $obj->getDescription();
                                    $tile_image = $this->object->commonSettings()->tileImage()->getByObjId($obj_id);
                                    $ctrl->setParameterByClass("ilrepositorygui", "ref_id", $ref_id);
                                    $permalink = $ctrl->getLinkTargetByClass("ilrepositorygui", "view");

                                    $course_tabs = dciSkin_tabs::getCourseTabs($ref_id, $this->plugin->txt("progress_status"));
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
                                    $lp = ilLearningProgress::_getProgress($this->user->getId(), $obj_id);
                                    $lp_status = ilLPStatusCollection::_lookupStatus($obj_id, $this->user->getId());
                                    $lp_percent = ilLPStatusCollection::_lookupPercentage($obj_id, $this->user->getId());
                                    $lp_in_progress = !empty(ilLPStatusCollection::_lookupInProgressForObject($obj_id, [$this->user->getId()]));
                                    $lp_completed = ilLPStatusCollection::_hasUserCompleted($obj_id, $this->user->getId());
                                    $lp_failed = !empty(ilLPStatusCollection::_lookupFailedForObject($obj_id, [$this->user->getId()]));
                                    $lp_downloaded = $lp['visits'] > 0 && $type == "file";

                                    $typical_learning_time = ilMDEducational::_getTypicalLearningTimeSeconds($obj_id);

                                    ?>
                                    <li class="splide__slide">
                                        <div class="kalamun-training-dashboard_course" data-permalink="<?= $permalink; ?>">
                                            <div class="kalamun-training-dashboard_thumb">
                                                <?= ($tile_image->exists() ? '<a href="' . $permalink . '" title="' . addslashes($title) . '"><img src="' . $tile_image->getFullPath() . '"></a>' : '<span class="empty-thumb"></span>'); ?>
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
                                                            $time_spent = explode(":", gmdate("H:i", $lp['spent_seconds']));
                                                            if ($time_spent[0] == 0 && $time_spent[1] == 0) $this->plugin->txt('not_started_yet');
                                                            else {
                                                                echo '<h6>' . $this->plugin->txt('time_spent') . '</h6>';
                                                                echo '<span><span class="icon-picto_timer_start"></span></span>';
                                                                echo '<div>';
                                                                    if ($time_spent[0] > 0) echo $time_spent[0] . ' ' . $this->plugin->txt('hours') . '<br>';
                                                                    if ($time_spent[1] > 0) echo $time_spent[1] . ' ' . $this->plugin->txt('minutes');
                                                                echo '</div>';
                                                            }
                                                            ?>
                                                        </div>
                                                        <?php
                                                        if (!empty($typical_learning_time)) {
                                                            ?>
                                                            <div class="kalamun-training-dashboard_course_progress_line learning-time">
                                                                <?php
                                                                $time_spent = explode(":", gmdate("H:i", $typical_learning_time));
                                                                echo '<h6>' . $this->plugin->txt('course_estimated_learning_time') . '</h6>';
                                                                echo '<span><span class="icon-picto_timer"></span></span>';
                                                                echo '<div>';
                                                                    if ($time_spent[0] > 0) echo $time_spent[0] . ' ' . $this->plugin->txt('hours') . '<br>';
                                                                    if ($time_spent[1] > 0) echo $time_spent[1] . ' ' . $this->plugin->txt('minutes');
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


    public static function getCoursesOfUser(
        int $a_user_id
    ): array {
        global $DIC;
        $tree = $DIC->repositoryTree();

        // see ilPDSelectedItemsBlockGUI

        $items = ilParticipants::_getMembershipByType($a_user_id, ['crs']);

        $references = [];
        $lp_obj_refs = [];
        foreach ($items as $obj_id) {
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
//                var_dump($obj_id, ilObjCourse::_lookupViewMode($obj_id), ilCourseConstants::IL_CRS_VIEW_OBJECTIVE);
                if (ilObjCourse::_lookupViewMode($obj_id) === ilCourseConstants::IL_CRS_VIEW_OBJECTIVE) {
                    $references[$ref_id]["objectives"] = static::parseObjectives($obj_id, $a_user_id);
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

    protected function parseObjectives(
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
    protected function parseLOUserResults(
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
}
