<?php
/**
* jenkira_underscores functions and definitions.
*
* @link https://developer.wordpress.org/themes/basics/theme-functions/
*
* @package jenkira_underscores
*/

if ( ! function_exists( 'jenkira_underscores_setup' ) ) :
/**
* Sets up theme defaults and registers support for various WordPress features.
*
* Note that this function is hooked into the after_setup_theme hook, which
* runs before the init hook. The init hook is too late for some features, such
* as indicating support for post thumbnails.
*/
function jenkira_underscores_setup() {
                /*
                * Make theme available for translation.
                * Translations can be filed in the /languages/ directory.
                * If you're building a theme based on jenkira_underscores, use a find and replace
                * to change 'jenkira_underscores' to the name of your theme in all the template files.
                */
                load_theme_textdomain( 'jenkira_underscores', get_template_directory() . '/languages' );

                // Add default posts and comments RSS feed links to head.
                add_theme_support( 'automatic-feed-links' );

                /*
                * Let WordPress manage the document title.
                * By adding theme support, we declare that this theme does not use a
                * hard-coded <title> tag in the document head, and expect WordPress to
                * provide it for us.
                */
                add_theme_support( 'title-tag' );

                /*
                * Enable support for Post Thumbnails on posts and pages.
                *
                * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
                */
                add_theme_support( 'post-thumbnails' );

                // This theme uses wp_nav_menu() in one location.
                register_nav_menus( array(
                                'primary' => esc_html__( 'Primary', 'jenkira_underscores' ),
                ) );

                /*
                * Switch default core markup for search form, comment form, and comments
                * to output valid HTML5.
                */
                add_theme_support( 'html5', array(
                                'search-form',
                                'comment-form',
                                'comment-list',
                                'gallery',
                                'caption',
                ) );

                // Set up the WordPress core custom background feature.
                add_theme_support( 'custom-background', apply_filters( 'jenkira_underscores_custom_background_args', array(
                                'default-color' => 'ffffff',
                                'default-image' => '',
                ) ) );
}
endif;
add_action( 'after_setup_theme', 'jenkira_underscores_setup' );

/**
* Set the content width in pixels, based on the theme's design and stylesheet.
*
* Priority 0 to make it available to lower priority callbacks.
*
* @global int $content_width
*/
function jenkira_underscores_content_width() {
                $GLOBALS['content_width'] = apply_filters( 'jenkira_underscores_content_width', 640 );
}
add_action( 'after_setup_theme', 'jenkira_underscores_content_width', 0 );

/**
* Register widget area.
*
* @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
*/
function jenkira_underscores_widgets_init() {
                register_sidebar( array(
                                'name'          => esc_html__( 'Sidebar', 'jenkira_underscores' ),
                                'id'            => 'sidebar-1',
                                'description'   => esc_html__( 'Add widgets here.', 'jenkira_underscores' ),
                                'before_widget' => '<section id="%1$s" class="widget %2$s">',
                                'after_widget'  => '</section>',
                                'before_title'  => '<h2 class="widget-title">',
                                'after_title'   => '</h2>',
                ) );
}
add_action( 'widgets_init', 'jenkira_underscores_widgets_init' );

/**
* Enqueue scripts and styles.
*/
function jenkira_underscores_scripts(){
                wp_enqueue_style( 'jenkira_underscores-style', get_stylesheet_uri() );
                wp_enqueue_script( 'jenkira_underscores-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '20151215', true );
                wp_enqueue_script( 'jenkira_underscores-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20151215', true );
    wp_enqueue_script( 'jenkira_underscores-jenkira.js', get_template_directory_uri() . '/js/jenkira.js', array(), null, false );
                wp_enqueue_script( 'jenkira_underscores-my-library_lib', 'https://my-static-script-domain/script/registration.js', array('jenkira_underscores-jenkira.js'), null, false );
                if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
                                wp_enqueue_script( 'comment-reply' );
                }
}
add_action( 'wp_enqueue_scripts', 'jenkira_underscores_scripts' );

/**
* Implement the Custom Header feature.
*/
require get_template_directory() . '/inc/custom-header.php';

/**
* Custom template tags for this theme.
*/
require get_template_directory() . '/inc/template-tags.php';

/**
* Custom functions that act independently of the theme templates.
*/
require get_template_directory() . '/inc/extras.php';

/**
* Customizer additions.
*/
require get_template_directory() . '/inc/customizer.php';

/**
* Load Jetpack compatibility file.
*/
require get_template_directory() . '/inc/jetpack.php';



/* ---------------------------------------------------------------------------------------- *
* --------------------------------------|  jenkira!  |-------------------------------------- *
 * ---------------------------------------------------------------------------------------- */


/*
* connects JIRA issues with jenkins builds and presents a UI for deployments
*
* a JIRA webhook is registered; when an assignee (currently defined in JQL, developers only) modifies an issue it posts to: http://my-wordpress-domain/my-wp-theme/wp-admin/admin-ajax.php?action=receive_jira_data&issue_id=${issue.id}&issue_key=${issue.key}&issue_expand=${issue.expand}&issue_self=${issue.self}&issue_status=${issue.fields.status.name}
* the script below receives and parses the data and writes it to wpdb
* an ajax call is made at a set interval (or onclick of a refresh button) to retrieve Jenkins builds
* the script creates a collection of QA builds which have not yet been built on Production and links it with JIRA issue keys, which are in the P4 changelist description and available in Jenkins
* note that only builds that are associated with JIRA issues are tracked; if the issue key is not in the P4 description, it will not be included in the results
* the collection is sent to the UI for display
*/

/* * * * * * * * * * * * * * * * * * * * * * * GLOBALS * * * * * * * * * * * * * * * * * * * * * * */
$all_builds = array();
/*
   credentials are hard-coded as globals with placeholders here, assuming there will be a "jenkira" user
   set up in jenkins and jira;
   if actual jenkins and jira users are to be granted access, a custom wordpress login should be set up
*/
$credentials = array(
     "jira" => array(
          "username" => "username",
          "password" => "password"
     ),
     "jenkins" => array(
          "username" => "username",
          "api_token" => "api_token"
     )
);

/* --------- JIRA --------- */


function receive_jira_data($webhook_json){

    $webhook_data = json_decode(file_get_contents('php://input'), true);

    if (!empty($webhook_data)) {
        $jira_update = array(
            'post_title' => $webhook_data['issue']['fields']['summary'],
            'post_status' => 'publish',
            'post_name' => $webhook_data['issue']['key'],
            'post_type' => 'jira',
            'post_content' => str_replace("<br />", ",", nl2br($webhook_data['issue']['fields']['customfield_10303'])), // using explode('\r\n' didn't work
            'post_excerpt' => $webhook_data['issue']['fields']['description'],
            'meta_input' => array(
                'jira_timestamp' => $webhook_data['timestamp'],
                'jira_assignee' => $webhook_data['issue']['fields']['assignee']['name'],
                'jira_status' => $webhook_data['issue']['fields']['status']['name']
            )
        );
        // check if post (issue) exists by searching for jira key, stored in 'post_name' field (referred to as 'name' in get_posts)
        $these_posts = get_posts(array('post_type' => 'jira', 'name' => $jira_update['post_name']));

        // Fix: insert test for duplicate jira keys
        $this_post = get_post($these_posts[0]->ID, OBJECT);

        if (!empty($this_post)) {
            $jira_update['ID'] = $this_post->ID;
            // update the post
            wp_update_post($jira_update, true);
        } else {
            // create new post
            $wpdb_response = wp_insert_post($jira_update, true);
            if (is_numeric($wpdb_response)) {
                $this_post = get_post($wpdb_response, OBJECT);
            } else {
                // error writing to wpdb
                return false;
            }
        }
        /* if we've gotten this far, $this_post exists */
        // if there are comments in jira for this issue, insert the latest comment into the comments table
        $jira_comments = $webhook_data['issue']['fields']['comment']['comments'];
        if (!empty($jira_comments)) {
            $last_comment_index = count($webhook_data['issue']['fields']['comment']['comments']) - 1;
            $jira_comment = array(
                'comment_post_ID' => $this_post->ID,
                'comment_author' => $webhook_data['issue']['fields']['comment']['comments'][$last_comment_index]['author']['name'],
                'comment_author_email' => $webhook_data['issue']['fields']['comment']['comments'][$last_comment_index]['author']['emailAddress'],
                'comment_content' => $webhook_data['issue']['fields']['comment']['comments'][$last_comment_index]['body']
            );
            wp_insert_comment($jira_comment);
        }
    }
}


function updateJIRAtransition($issue_key, $transition_name, $comment){
    // Note: This function does update a transition, but JIRA returns the following error: "Error marking step #40491 finished: root cause: Tried to update an entity that does not exist."
    $issue_key = strtolower($issue_key);
    $transitioned_to = ($transition_name === 'Deploy to QA' || $transition_name === 'Deployed to Production' ? str_replace("Deploy", "Deployed", $transition_name) : $transition_name);
    $comment = "Jenkira updated status to '".$transitioned_to."'.\n".$comment;
    $transitions_IDs = array(
        'Start Progress' => 4,
        'Stop Progress' => 301,
        'Deploy to QA' => '81',
        'Deploy To Production' => '191',
        'Verified On Production' => '101'
    );
    $update_request = array(
        "update" => array("comment" => array(array("add" => array("body" => $comment)))),
        "transition" => array(
            "id" => $transitions_IDs[$transition_name]
        )
    );
    $username = $credentials['jira']['username'];
    $password = $credentials['jira']['password'];

    $url = "https://my-jira-domain/rest/api/2/issue/".$issue_key."/transitions?expand=transitions.fields";
    $headers = array('Content-Type: application/json');
    $data = json_encode($update_request);
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // ignore SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // ignore SSL
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_URL, trim($url));
    curl_setopt($ch, CURLOPT_USERPWD, $username .':'. $password);

    $result = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); //get status code
    $ch_error = curl_error($ch);

    curl_close($ch);

    if($ch_error){
    }
    else{
        return $result;
    }
}

/* --------- Jenkins --------- */

function jenkins_request($request_type, $job_name, $build_number, $callback){

    if($request_type === 'get'){

        $data = "";
        $username = $credentials['jenkins']['username'];
        $api_token = $credentials['jenkins']['api_token'];

        $url = "https://".$username.":".$api_token."@my-jenkins-domain"


        if($job_name === 'all') {
            $url = "/api/json?tree=jobs[name,allBuilds[number,timestamp,result,changeSet[items[changeNumber,msg,author[fullName]]]]]";
        }
        elseif($job_name === 'last_stable_builds'){
            $url = "/api/json?tree=jobs[name,lastStableBuild[number,timestamp]]";
        }
        elseif($job_name === 'scheduled_tasks'){
            $url = "/view/Scheduled%20Tasks/rssAll";
        }
        elseif($job_name === 'build_queue'){
            $url = "/queue/api/json";
        }
        elseif($build_number === 'last_build'){
            $url = "/job/".$job_name."/lastBuild/api/json";
        }
        elseif($build_number){
            $url = "/job/".$job_name."/".$build_number."/api/json";
        }
        else{
            $url = "/job/".$job_name."/api/json?tree=allBuilds[fullDisplayName,id,number,timestamp,result,actions[causes[shortDescription,userId]],changeSet[items[changeNumber,msg,author[fullName]]]]";
        }
    }
    elseif($request_type === 'build'){

        $url = '/job/'. $job_name .'/buildWithParameters?fullName=jenkira&msg=deployment';

        $data = '"changeSet": {
                "items": [{
                    "author": {
                        "fullName": "jenkira!"
                    },
                    "msg": "jenkira test build"
                }]
            }';

        $data = json_encode($data);
    }
    else{
        // error
    }

    $headers = array('Content-Type: application/json');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, $request_type === "build" ? true : false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $output=curl_exec($ch);

    if($output === false){
        $output = array(
            "Error Number" => curl_errno($ch),
            "Error String" => curl_error($ch)
        );
    }
    else{
        if(isset($callback)){
            $callback(json_decode($output), $job_name);
        }
    }
    if($job_name === 'scheduled_tasks'){
        $output = new SimpleXMLElement($output);
        $output = json_encode($output);
    }
    else{

    }
    curl_close($ch);
    return json_decode($output);
}

function get_jobnames_from_builds($builds){
    $jobnames = array();
    foreach($builds->jobs as $job){
        array_push($jobnames, $job->name);
    }
    return $jobnames;
}

function get_root_branchnames_from_jobnames($jobnames, $prefix_filter){
    $branchnames = array();
    foreach($jobnames as $jobname){
        $branchname = str_replace($prefix_filter, '', $jobname, $count);
        if($count){
            array_push($branchnames, $branchname);
        }
    }
    return $branchnames;
}

function keyify_array_of_objects($array_of_objects, $keyname){
    // returns new array keyed on the value of $keyname if it is a direct property of the object
    // To Do: recursive version that finds nested $keyname
    $keyified = array();
    foreach($array_of_objects as $obj){
        if(isset($obj->$keyname)){
            $keyified[$obj->$keyname] = $obj;
        }
    }
    return $keyified;
}

function get_jira_issue_keys(){
    $these_posts = get_posts(array('post_type' => 'jira', 'posts_per_page' => -1));
    $jira_issue_keys = array();
    foreach ($these_posts as $psts => $pst){
        $jira_issue_keys[$pst->ID] = $pst->post_name;
    }
    return $jira_issue_keys;
}

function merge_post_meta($id){
    $p = get_post($id);
    $m = get_post_meta($id);
    $merged = array();
    $merged['jira_issue'] = $p->post_name;
    $merged['jira_links'] = $p->post_content;
    foreach($m as $metaname => $metaval){
        $merged[$metaname] = $metaval[0]; // have to get the first element because get_post_meta() returns an array
    }
    return $merged;
}

function qa_jobs_to_build($branchnames, $keyified){
    // if the last stable qa build occurred after the last stable production build, save it to an array
    $qa_to_build = array();
    foreach($branchnames as $branchname){
        $qa_jobname = 'QA_Deploy_'.$branchname;
        $pr_jobname = 'Prod_Deploy_'.$branchname;
        $qa_timestamp = $keyified[$qa_jobname]->lastStableBuild->timestamp;
        $pr_timestamp = $keyified[$pr_jobname]->lastStableBuild->timestamp;
        if($qa_timestamp > $pr_timestamp){
            array_push($qa_to_build, $branchname);
        }
    }
    return $qa_to_build;
}

function addit($it, $branchname){
    global $all_builds;
    $all_builds[$branchname] = $it->allBuilds;
}

function get_all_builds_by_branchname($branchnames, $prefix){
    global $all_builds;
    $branchnames = is_array($branchnames) ? $branchnames : array($branchnames);
    foreach($branchnames as $branchname){
        jenkins_request('get', $prefix.$branchname, null, addit);
    }
    return $all_builds;
}

function qa_builds_since_timestamp($keyified_last_stable, $keyified_qa_builds){
    foreach($keyified_qa_builds as $qa_branchname => $build){
        $pr_timestamp = $keyified_last_stable[str_replace('QA_Deploy_', 'Prod_Deploy_', $qa_branchname)]->lastStableBuild->timestamp;
        foreach($build as $key => $qa_build){
            if($qa_build->timestamp < $pr_timestamp){
                // if the compared build's timestamp is not more recent than the start timestamp, remove it
                unset($keyified_qa_builds[$qa_branchname][$key]);
            }
        }
    }
    return $keyified_qa_builds;
}

function get_jira_issues_linked_with_builds($jobs, $jira_posts){
    $unbuilt_issues_branches = array();
    $jira_jenkins_merged = array(
        'issues' => array(),
        'branches' => array()
    );
    foreach($jobs as $branch => $builds){
        $branch = str_replace('QA_Deploy_', '', $branch);
        foreach($builds as $pq){
            if (count($pq->changeSet->items) > 0){
                foreach ($pq->changeSet->items as $change){
                    // when the author is 'WebTeam', the msg field contains the content from the description field in the perforce change list that triggered the jenkins build
                    if($change->author->fullName === 'WebTeam'){
                        // loop through all jira issue keys
                        foreach($jira_posts as $jira_post_id => $jira_issue_key){
                            // look for issue key in the msg field
                            if(stripos($change->msg, $jira_issue_key)){
                                if(!isset($unbuilt_issues_branches[$jira_issue_key])){
                                    $jira_fetched = merge_post_meta($jira_post_id);
                                    $unbuilt_issues_branches[$jira_issue_key] = $jira_fetched;
                                    $unbuilt_issues_branches[$jira_issue_key]['jenkins_unbuilt'] = array();
                                    $unbuilt_issues_branches[$jira_issue_key]['jenkins_unbuilt_links'] = array($branch => '');
                                    $unbuilt_issues_branches[$jira_issue_key]['jenkins_qa_builds'][$branch] = array();
                                }
                                // clear fetched branches if they exist, build new array to overwrite them
                                $build_data = array(
                                    "timestamp" => $pq->timestamp / 1000,
                                    "number" => $pq->number,
                                    "changelists" => array()
                                );
                                if($pq->result === 'SUCCESS'){
                                    $qa_builds[$branch]['success'] = $build_data;
                                    $qa_builds[$branch]['success']['changelists'][] = $change->changeNumber;

                                    if(!in_array($branch, $unbuilt_issues_branches[$jira_issue_key]['jenkins_unbuilt'])){
                                        $unbuilt_issues_branches[$jira_issue_key]['jenkins_unbuilt'][] = $branch;
                                        $unbuilt_issues_branches[$jira_issue_key]['jenkins_unbuilt_links'][$branch] = 'https://my-jenkins-domain/job/QA_Deploy_'.$branch.'/'.$pq->number;
                                    }
                                    if(!in_array($branch, $jira_jenkins_merged['branches'])){
                                        $jira_jenkins_merged['branches'][] = $branch;
                                    }
                                }
                                else{
                                   $qa_builds[$branch]['unsuccess'] = $build_data;
                                    $qa_builds[$branch]['unsuccess']['changelists'][] = $change->changeNumber;
                                }
                                $unbuilt_issues_branches[$jira_issue_key]['jenkins_qa_builds'][$branch] = $qa_builds[$branch];
                            }
                        }
                    }
                }
            }
        }
    }
    if(count($unbuilt_issues_branches) > 0){
        foreach($unbuilt_issues_branches as $issue_obj){
            $jira_jenkins_merged['issues'][] = $issue_obj;
        }
        // get post id from jira issue key
        $jira_post_ids = array_flip($jira_posts);

        // add unbuilt branches to wpdb
        foreach($jira_jenkins_merged['issues'] as $merged_issue){
            $deployed_to_qa_details = create_build_comment_string($merged_issue['jenkins_qa_builds']);
            updateJIRAtransition($merged_issue['jira_issue'], 'Deploy to QA', $deployed_to_qa_details);
            $issue_post_id = $jira_post_ids[$merged_issue['jira_issue']];
            wp_update_post($jira_update, true);
            $jenkins_update = array(
                'ID' => $issue_post_id,
                'meta_input' => array(
                    'jenkins_unbuilt' => implode(",", $merged_issue['jenkins_unbuilt'])
                )
            );
            wp_update_post($jenkins_update, true);
        }
    }
    return $jira_jenkins_merged;
}

function create_build_comment_string($build_arr){
    $build_arr_str = array();
    foreach($build_arr as $branchname => $changelists_arr){
        $build_arr_str[] = $branchname.": ".implode(",", $changelists_arr['success']['changelists']);
    }
    $build_str = "Branches/Changelists: ".implode(" | ", $build_arr_str);
    return $build_str;
}


/// ***  called via UI - AJAX  *** ///

// chained
function refresh_data(){
    // get last stable builds
    $last_stable = jenkins_request('get', 'last_stable_builds', null, null);
    if($last_stable){$keyified_last_stable_builds = keyify_array_of_objects($last_stable->jobs, 'name');};
    if($keyified_last_stable_builds){$jobnames_from_builds = get_jobnames_from_builds($last_stable);};
    if($jobnames_from_builds){$last_stable_production_branchnames = get_root_branchnames_from_jobnames($jobnames_from_builds, 'Prod_Deploy_');};

    // collect job names of jobs where the LAST stable qa build happened more recently than the last stable production build
    if($last_stable_production_branchnames){$qa_pending_branchnames = qa_jobs_to_build($last_stable_production_branchnames, $keyified_last_stable_builds);};
    // get all builds for those
    if($qa_pending_branchnames){$keyified_qa_builds = get_all_builds_by_branchname($qa_pending_branchnames, 'QA_Deploy_');};
    // remove builds that happened before the last stable production build
    if($qa_pending_branchnames){$keyified_qa_builds_after_last_production = qa_builds_since_timestamp($keyified_last_stable_builds, $keyified_qa_builds, 'Prod_Deploy_', 'QA_Deploy_');};
    // get jira issue keys from wpdb
    if($keyified_qa_builds_after_last_production){$issue_keys = get_jira_issue_keys();};
    // merge builds with linked jira issues
    if($issue_keys){$jira_issue_builds = get_jira_issues_linked_with_builds($keyified_qa_builds_after_last_production, $issue_keys);};

    if($jira_issue_builds){
        // get jenkins rss feed
        $jenkins_scheduled_tasks = jenkins_request('get', 'scheduled_tasks', null, null);
        $jira_issue_builds['jenkins_scheduled_tasks'] = $jenkins_scheduled_tasks->entry;
        echo json_encode($jira_issue_builds);
        die();
    }
}

// trigger a jenkins build
function jenkins_build(){
    $branchname = $_POST['branchname'];
    echo jenkins_request('build', $branchname, null, null);
    die();
}

// get jenkins build queue
function get_jenkins_build_queue(){
   echo json_encode(jenkins_request('get', 'build_queue', null, null));
    die();
}

function update_jira_status(){
    updateJIRAtransition($_POST['issue_key'], $_POST['status'], create_build_comment_string($_POST['comment']));
}

function jenkins_match_changelists(){
    $matched_changelists = array();
    // the branchnames and changelist numbers for qa builds have been passed to the front end and are now passed back to verify production builds
    $branchnames_changelists = $_POST['branchnames_changelists'];
    foreach($branchnames_changelists as $branchname => $changelists){
        $matched_changelists[$branchname] = array();
        $last_production_build = jenkins_request('get', 'Prod_Deploy_'.$branchname, 'last_build', null);
        $build_result = 'unsuccess';
        if(count($last_production_build->changeSet->items) > 0){
            foreach ($last_production_build->changeSet->items as $change){
                $changelist_number = $change->changeNumber;
                if(in_array($changelist_number, $changelists)){
                    $matched_changelists[$branchname][] = $changelist_number;
                    break;
                }
            }
        }
    }
    echo json_encode($matched_changelists);
    die();
}


// ACTIONS

// handle JIRA webhook post
add_action('wp_ajax_receive_jira_data', 'receive_jira_data');
add_action('wp_ajax_nopriv_receive_jira_data', 'receive_jira_data'); // nopriv = can be called while being logged in or not

// update JIRA status
add_action('wp_ajax_update_jira_status', 'update_jira_status');
add_action('wp_ajax_nopriv_update_jira_status', 'update_jira_status'); // nopriv = can be called while being logged in or not

// get latest builds from Jenkins, merge with JIRA data in wpdb
add_action('wp_ajax_refresh_data', 'refresh_data');
add_action('wp_ajax_nopriv_refresh_data', 'refresh_data'); // nopriv = can be called while being logged in or not

// request a Jenkins build
add_action('wp_ajax_jenkins_build', 'jenkins_build');
add_action('wp_ajax_nopriv_jenkins_build', 'jenkins_build'); // nopriv = can be called while being logged in or not

// get build queue from Jenkins
add_action('wp_ajax_get_jenkins_build_queue', 'get_jenkins_build_queue');
add_action('wp_ajax_nopriv_get_jenkins_build_queue', 'get_jenkins_build_queue'); // nopriv = can be called while being logged in or not

// verify a Jenkins build
add_action('wp_ajax_jenkins_match_changelists', 'jenkins_match_changelists');
add_action('wp_ajax_nopriv_jenkins_match_changelists', 'jenkins_match_changelists'); // nopriv = can be called while being logged in or not

/* ---------------------------------------------------------------------------------------- *
* --------------------------------------|  jenkira!  |-------------------------------------- *
 * ---------------------------------------------------------------------------------------- */
