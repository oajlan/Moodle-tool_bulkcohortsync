<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Main page for tool_bulkcohortsync plugin.
 *
 * @package    tool_bulkcohortsync
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/enrol/cohort/lib.php');

// Robust Admin setup with fallback if plugin is accessed before system upgrade
try {
    admin_externalpage_setup('tool_bulkcohortsync');
} catch (Exception $e) {
    require_login();
    $context = context_system::instance();
    require_capability('moodle/site:config', $context);
    $PAGE->set_url(new moodle_url('/admin/tool/bulkcohortsync/index.php'));
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('pluginname', 'tool_bulkcohortsync'));
    $PAGE->set_heading(get_string('pluginname', 'tool_bulkcohortsync'));
}

// Fetch all categories
$all_categories = $DB->get_records('course_categories', array(), 'sortorder ASC', 'id, name, parent, depth, path');

// Fetch all non-site courses
$all_courses = $DB->get_records_select('course', "format <> 'site'", array(), 'fullname ASC', 'id, fullname, shortname, category');

// Build category tree nodes
$cat_nodes = array();
foreach ($all_categories as $cat) {
    $cat_nodes[$cat->id] = (object) array(
        'id' => $cat->id,
        'name' => $cat->name,
        'parent' => $cat->parent,
        'depth' => $cat->depth,
        'children' => array(),
        'courses' => array()
    );
}

// Assign courses to their category node
foreach ($all_courses as $c) {
    if (isset($cat_nodes[$c->category])) {
        $cat_nodes[$c->category]->courses[] = $c;
    } else {
        if (!isset($cat_nodes[0])) {
            $cat_nodes[0] = (object) array(
                'id' => 0,
                'name' => 'Uncategorized',
                'parent' => 0,
                'depth' => 1,
                'children' => array(),
                'courses' => array()
            );
        }
        $cat_nodes[0]->courses[] = $c;
    }
}

// Build hierarchy tree
$category_tree = array();
foreach ($cat_nodes as $id => $node) {
    if ($node->parent != 0 && isset($cat_nodes[$node->parent])) {
        $cat_nodes[$node->parent]->children[] = $node;
    } else {
        $category_tree[] = $node;
    }
}

// Calculate total course count recursively
function get_subtree_course_count($node) {
    $count = count($node->courses);
    foreach ($node->children as $child) {
        $count += get_subtree_course_count($child);
    }
    return $count;
}

// Recursive renderer for category tree (collapsed by default)
function render_category_tree($nodes) {
    $html = '';
    $str_selectall = get_string('selectall', 'tool_bulkcohortsync');
    $str_deselectall = get_string('deselectall', 'tool_bulkcohortsync');

    foreach ($nodes as $node) {
        $total_courses = get_subtree_course_count($node);
        if ($total_courses == 0) {
            continue; // Skip empty categories
        }
        
        $has_children = !empty($node->children);
        $has_courses = !empty($node->courses);
        
        $html .= '<div class="cat-group cat-depth-' . $node->depth . '" data-catid="' . $node->id . '">';
        $html .= '  <div class="cat-header collapsed" data-catid="' . $node->id . '">';
        $html .= '    <span class="cat-header-title">';
        $html .= '      ' . s($node->name);
        $html .= '      <span class="cat-count">' . $total_courses . '</span>';
        $html .= '      <span class="cat-select-link" data-action="select" data-catid="' . $node->id . '">' . s($str_selectall) . '</span>';
        $html .= '      <span class="cat-select-link" data-action="deselect" data-catid="' . $node->id . '">' . s($str_deselectall) . '</span>';
        $html .= '    </span>';
        $html .= '    <span class="cat-toggle-icon">▼</span>';
        $html .= '  </div>';
        
        // Collapsed by default (hidden class added)
        $html .= '  <div class="cat-body hidden" id="cat-body-' . $node->id . '">';
        
        if ($has_courses) {
            $html .= '  <div class="cat-courses-list">';
            foreach ($node->courses as $c) {
                $html .= '<label class="item-row course-item" data-catid="' . $node->id . '" data-name="' . s(strtolower($c->fullname . ' ' . $c->shortname)) . '">';
                $html .= '  <input type="checkbox" name="courses[]" value="' . $c->id . '" class="course-checkbox">';
                $html .= '  <div class="item-info">';
                $html .= '    <strong>' . s($c->fullname) . '</strong><br>';
                $html .= '    <small class="text-muted">' . s($c->shortname) . '</small>';
                $html .= '  </div>';
                $html .= '</label>';
            }
            $html .= '  </div>';
        }
        
        if ($has_children) {
            $html .= '  <div class="cat-subcategories-list">';
            $html .= render_category_tree($node->children);
            $html .= '  </div>';
        }
        
        $html .= '  </div>';
        $html .= '</div>';
    }
    return $html;
}

// Fetch cohorts
$cohorts = $DB->get_records('cohort', array(), 'name ASC', 'id, name, idnumber');

// Fetch roles
$roles = $DB->get_records('role', array(), 'sortorder ASC', 'id, name, shortname');

$logs = array();
$submitted = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $submitted = true;
    $selected_courses = optional_param_array('courses', array(), PARAM_INT);
    $selected_cohorts = optional_param_array('cohorts', array(), PARAM_INT);
    $roleid = required_param('roleid', PARAM_INT);

    if (empty($selected_courses) || empty($selected_cohorts)) {
        $logs[] = array('type' => 'error', 'message' => get_string('error_selectboth', 'tool_bulkcohortsync'));
    } else {
        $plugin = enrol_get_plugin('cohort');
        if (!$plugin) {
            $logs[] = array('type' => 'error', 'message' => get_string('error_noplugin', 'tool_bulkcohortsync'));
        } else {
            foreach ($selected_courses as $cid) {
                $course = $DB->get_record('course', array('id' => $cid));
                if (!$course) continue;

                foreach ($selected_cohorts as $coh_id) {
                    $cohort = $DB->get_record('cohort', array('id' => $coh_id));
                    if (!$cohort) continue;

                    // Check if already synced
                    $params = array(
                        'enrol' => 'cohort',
                        'courseid' => $course->id,
                        'customint1' => $cohort->id
                    );
                    if ($DB->record_exists('enrol', $params)) {
                        $logs[] = array(
                            'type' => 'info',
                            'message' => get_string('info_alreadysynced', 'tool_bulkcohortsync', (object)array('cohort' => $cohort->name, 'course' => $course->fullname))
                        );
                        continue;
                    }

                    // Add instance
                    $fields = array(
                        'customint1' => $cohort->id,
                        'roleid' => $roleid,
                        'status' => ENROL_INSTANCE_ENABLED
                    );
                    $result = $plugin->add_instance($course, $fields);
                    if ($result) {
                        $logs[] = array(
                            'type' => 'success',
                            'message' => get_string('success_synced', 'tool_bulkcohortsync', (object)array('cohort' => $cohort->name, 'idnumber' => $cohort->idnumber, 'course' => $course->fullname))
                        );
                    } else {
                        $logs[] = array(
                            'type' => 'error',
                            'message' => get_string('error_failed', 'tool_bulkcohortsync', (object)array('cohort' => $cohort->name, 'course' => $course->fullname))
                        );
                    }
                }
            }
        }
    }
}

echo $OUTPUT->header();
?>

<style>
    .sync-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid #e9ecef;
    }
    .sync-title {
        font-weight: 700;
        color: #343a40;
        margin-bottom: 16px;
        border-bottom: 2px solid #6f42c1;
        padding-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 1.1rem;
    }
    .search-input {
        border-radius: 20px !important;
        padding: 8px 16px;
        margin-bottom: 12px;
        font-size: 14px;
        width: 100%;
    }
    .scroll-container {
        max-height: 45vh;
        min-height: 250px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 10px;
        background: #f8f9fa;
        -webkit-overflow-scrolling: touch;
    }
    .item-row {
        padding: 8px 12px;
        border-radius: 6px;
        transition: all 0.2s ease;
        cursor: pointer;
        display: flex;
        align-items: center;
        margin-bottom: 4px;
        word-break: break-word;
    }
    .item-row:hover {
        background: #e9ecef;
    }
    .item-row input[type="checkbox"] {
        margin-right: 10px;
        margin-left: 10px;
        cursor: pointer;
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }
    .item-info {
        flex-grow: 1;
    }
    .sync-btn {
        background: linear-gradient(135deg, #6f42c1 0%, #4e22b4 100%);
        border: none;
        color: white !important;
        padding: 12px 36px;
        border-radius: 25px;
        font-weight: 600;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        cursor: pointer;
        font-size: 1rem;
    }
    .sync-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(111, 66, 193, 0.4);
        background: linear-gradient(135deg, #59339e 0%, #3e1b91 100%);
    }
    .log-container {
        max-height: 350px;
        overflow-y: auto;
        background: #1e1e1e;
        color: #d4d4d4;
        font-family: 'Courier New', Courier, monospace;
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
        box-shadow: inset 0 2px 8px rgba(0,0,0,0.5);
    }
    .log-line {
        margin-bottom: 6px;
        border-bottom: 1px solid #2d2d2d;
        padding-bottom: 4px;
        font-size: 13px;
        word-break: break-word;
    }
    .log-success { color: #4ec9b0; }
    .log-info { color: #9cdcfe; }
    .log-error { color: #f44747; }
    
    .quick-select {
        font-size: 13px;
        margin-bottom: 10px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    .quick-select a {
        text-decoration: none !important;
        color: #6f42c1;
        font-weight: 600;
    }
    .quick-select a:hover {
        color: #4e22b4;
    }
    .counter-badge {
        background: #6f42c1;
        color: white;
        font-size: 11px;
        padding: 3px 10px;
        border-radius: 12px;
        white-space: nowrap;
    }
    .cat-group {
        margin-bottom: 8px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        overflow: hidden;
    }
    .cat-header {
        background: linear-gradient(135deg, #6f42c1 0%, #4e22b4 100%);
        color: #fff;
        padding: 8px 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        user-select: none;
        font-weight: 600;
        font-size: 13px;
        letter-spacing: 0.3px;
        transition: background 0.2s;
    }
    .cat-header:hover {
        background: linear-gradient(135deg, #59339e 0%, #3e1b91 100%);
    }
    .cat-header .cat-toggle-icon {
        font-size: 10px;
        transition: transform 0.25s;
        margin-left: 8px;
        margin-right: 8px;
    }
    .cat-header.collapsed .cat-toggle-icon {
        transform: rotate(-90deg);
    }
    .cat-body {
        background: #f8f9fa;
        padding: 6px 8px;
    }
    .cat-body.hidden {
        display: none;
    }
    .cat-count {
        background: rgba(255,255,255,0.25);
        font-size: 11px;
        padding: 1px 7px;
        border-radius: 10px;
        margin-left: 6px;
        margin-right: 6px;
    }
    .cat-select-link {
        font-size: 11px;
        color: rgba(255,255,255,0.9);
        margin-left: 8px;
        margin-right: 8px;
        text-decoration: underline;
        cursor: pointer;
    }
    .cat-select-link:hover {
        color: #fff;
    }
    .cat-subcategories-list {
        margin-left: 10px;
        margin-right: 10px;
        padding-left: 8px;
        padding-right: 8px;
        border-left: 2px solid #6f42c1;
        margin-top: 6px;
    }
    .cat-depth-2 .cat-header {
        background: linear-gradient(135deg, #7c4bc4 0%, #5b28ba 100%);
        font-size: 12.5px;
    }
    .cat-depth-3 .cat-header {
        background: linear-gradient(135deg, #8d5bd8 0%, #6f3bcf 100%);
        font-size: 12px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .sync-card {
            padding: 15px;
        }
        .scroll-container {
            max-height: 35vh;
        }
        .sync-btn {
            width: 100%;
            padding: 12px 20px;
        }
        .cat-header {
            font-size: 12px;
            padding: 8px 10px;
        }
    }
</style>

<div class="container-fluid mt-3">
    <div class="row">
        <div class="col-12">
            <h2><?php echo get_string('pluginname', 'tool_bulkcohortsync'); ?></h2>
            <p class="text-muted"><?php echo get_string('description', 'tool_bulkcohortsync'); ?></p>
        </div>
    </div>

    <?php if ($submitted && !empty($logs)): ?>
    <div class="row">
        <div class="col-12">
            <div class="sync-card">
                <h4 class="sync-title"><?php echo get_string('executionlogs', 'tool_bulkcohortsync'); ?></h4>
                <div class="log-container">
                    <?php foreach ($logs as $log): ?>
                        <div class="log-line log-<?php echo $log['type']; ?>">
                            [<?php echo strtoupper($log['type']); ?>] <?php echo s($log['message']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <form method="post" action="<?php echo new moodle_url('/admin/tool/bulkcohortsync/index.php'); ?>">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

        <div class="row">
            <!-- Left Column: Courses Selection -->
            <div class="col-12 col-lg-6">
                <div class="sync-card">
                    <div class="sync-title">
                        <span><?php echo get_string('selectcourses', 'tool_bulkcohortsync'); ?></span>
                        <span class="counter-badge" id="courses-counter">0 <?php echo get_string('selected', 'tool_bulkcohortsync'); ?></span>
                    </div>
                    
                    <input type="text" id="course-search" class="form-control search-input" placeholder="<?php echo get_string('searchcourses', 'tool_bulkcohortsync'); ?>">
                    
                    <div class="quick-select">
                        <a href="#" id="select-all-courses"><?php echo get_string('selectall', 'tool_bulkcohortsync'); ?></a>
                        <a href="#" id="deselect-all-courses"><?php echo get_string('deselectall', 'tool_bulkcohortsync'); ?></a>
                    </div>

                    <div class="scroll-container" id="courses-list">
                        <?php echo render_category_tree($category_tree); ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Cohorts Selection -->
            <div class="col-12 col-lg-6">
                <div class="sync-card">
                    <div class="sync-title">
                        <span><?php echo get_string('selectcohorts', 'tool_bulkcohortsync'); ?></span>
                        <span class="counter-badge" id="cohorts-counter">0 <?php echo get_string('selected', 'tool_bulkcohortsync'); ?></span>
                    </div>
                    
                    <input type="text" id="cohort-search" class="form-control search-input" placeholder="<?php echo get_string('searchcohorts', 'tool_bulkcohortsync'); ?>">
                    
                    <div class="quick-select">
                        <a href="#" id="select-all-cohorts"><?php echo get_string('selectall', 'tool_bulkcohortsync'); ?></a>
                        <a href="#" id="deselect-all-cohorts"><?php echo get_string('deselectall', 'tool_bulkcohortsync'); ?></a>
                    </div>

                    <div class="scroll-container" id="cohorts-list">
                        <?php foreach ($cohorts as $coh): ?>
                            <label class="item-row cohort-item" data-name="<?php echo s(strtolower($coh->name . ' ' . $coh->idnumber)); ?>">
                                <input type="checkbox" name="cohorts[]" value="<?php echo $coh->id; ?>" class="cohort-checkbox">
                                <div class="item-info">
                                    <strong><?php echo s($coh->name); ?></strong> <br>
                                    <small class="text-muted">ID Number: <?php echo s($coh->idnumber); ?></small>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Role Selection and Action -->
            <div class="col-12">
                <div class="sync-card text-center">
                    <h4 class="sync-title justify-content-center"><?php echo get_string('configureandexecute', 'tool_bulkcohortsync'); ?></h4>
                    <div class="d-flex justify-content-center align-items-center mb-4 flex-wrap gap-2">
                        <label class="mr-3 mb-0 font-weight-bold"><?php echo get_string('selectrole', 'tool_bulkcohortsync'); ?></label>
                        <select name="roleid" class="form-control w-auto">
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r->id; ?>" <?php echo ($r->shortname === 'student') ? 'selected' : ''; ?>>
                                    <?php echo s($r->name); ?> (<?php echo s($r->shortname); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn sync-btn"><?php echo get_string('syncbtn', 'tool_bulkcohortsync'); ?></button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // 1. Category collapse/expand (manual click only)
    document.querySelectorAll('.cat-header').forEach(function(header) {
        header.addEventListener('click', function(e) {
            if (e.target.classList.contains('cat-select-link')) return;
            const catid = this.getAttribute('data-catid');
            const body = document.getElementById('cat-body-' + catid);
            if (body) {
                if (body.classList.contains('hidden')) {
                    body.classList.remove('hidden');
                    this.classList.remove('collapsed');
                } else {
                    body.classList.add('hidden');
                    this.classList.add('collapsed');
                }
            }
        });
    });

    // 2. Per-category select/deselect links
    document.querySelectorAll('.cat-select-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.stopPropagation();
            const catid = this.getAttribute('data-catid');
            const action = this.getAttribute('data-action');
            const container = document.getElementById('cat-body-' + catid);
            if (container) {
                container.querySelectorAll('.course-checkbox').forEach(function(cb) {
                    cb.checked = (action === 'select');
                });
            }
            updateCoursesCounter();
        });
    });

    // 3. Search filter for Courses (DOES NOT auto-expand tree, preserves collapsed state)
    const courseSearch = document.getElementById('course-search');
    courseSearch.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        document.querySelectorAll('.cat-group').forEach(function(group) {
            const items = group.querySelectorAll('.course-item');
            let anyVisible = false;
            items.forEach(function(item) {
                const name = item.getAttribute('data-name');
                const show = name.includes(query);
                item.style.display = show ? 'flex' : 'none';
                if (show) anyVisible = true;
            });
            // Show/hide category header box based on query match
            group.style.display = anyVisible ? '' : 'none';
        });
    });

    // 4. Search filter for Cohorts
    const cohortSearch = document.getElementById('cohort-search');
    cohortSearch.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        document.querySelectorAll('.cohort-item').forEach(function(item) {
            item.style.display = item.getAttribute('data-name').includes(query) ? 'flex' : 'none';
        });
    });

    // 5. Counter update functions
    const strSelected = "<?php echo s(get_string('selected', 'tool_bulkcohortsync')); ?>";
    const courseCheckboxes = document.querySelectorAll('.course-checkbox');
    const cohortCheckboxes = document.querySelectorAll('.cohort-checkbox');
    const coursesCounter = document.getElementById('courses-counter');
    const cohortsCounter = document.getElementById('cohorts-counter');

    function updateCoursesCounter() {
        coursesCounter.textContent = document.querySelectorAll('.course-checkbox:checked').length + ' ' + strSelected;
    }
    function updateCohortsCounter() {
        cohortsCounter.textContent = document.querySelectorAll('.cohort-checkbox:checked').length + ' ' + strSelected;
    }
    courseCheckboxes.forEach(function(cb) { cb.addEventListener('change', updateCoursesCounter); });
    cohortCheckboxes.forEach(function(cb) { cb.addEventListener('change', updateCohortsCounter); });

    // 6. Global Select All / Deselect All
    document.getElementById('select-all-courses').addEventListener('click', function(e) {
        e.preventDefault();
        courseCheckboxes.forEach(function(cb) {
            if (cb.closest('.course-item').style.display !== 'none') cb.checked = true;
        });
        updateCoursesCounter();
    });
    document.getElementById('deselect-all-courses').addEventListener('click', function(e) {
        e.preventDefault();
        courseCheckboxes.forEach(function(cb) { cb.checked = false; });
        updateCoursesCounter();
    });
    document.getElementById('select-all-cohorts').addEventListener('click', function(e) {
        e.preventDefault();
        cohortCheckboxes.forEach(function(cb) {
            if (cb.closest('.cohort-item').style.display !== 'none') cb.checked = true;
        });
        updateCohortsCounter();
    });
    document.getElementById('deselect-all-cohorts').addEventListener('click', function(e) {
        e.preventDefault();
        cohortCheckboxes.forEach(function(cb) { cb.checked = false; });
        updateCohortsCounter();
    });
});
</script>

<?php
echo $OUTPUT->footer();
