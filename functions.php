<?php

/* functions.php */

/**
 * tickethelper_s functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package tickethelper_s
 */

if ( ! function_exists( 'tickethelper_s_setup' ) ) :
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function tickethelper_s_setup() {
        /*
         * Make theme available for translation.
         * Translations can be filed in the /languages/ directory.
         * If you're building a theme based on tickethelper_s, use a find and replace
         * to change 'tickethelper_s' to the name of your theme in all the template files.
         */
        load_theme_textdomain( 'tickethelper_s', get_template_directory() . '/languages' );

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
                'primary' => esc_html__( 'Primary', 'tickethelper_s' ),
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
        add_theme_support( 'custom-background', apply_filters( 'tickethelper_s_custom_background_args', array(
                'default-color' => 'ffffff',
                'default-image' => '',
        ) ) );
}
endif;
add_action( 'after_setup_theme', 'tickethelper_s_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function tickethelper_s_content_width() {
        $GLOBALS['content_width'] = apply_filters( 'tickethelper_s_content_width', 640 );
}
add_action( 'after_setup_theme', 'tickethelper_s_content_width', 0 );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function tickethelper_s_widgets_init() {
        register_sidebar( array(
                'name'          => esc_html__( 'Sidebar', 'tickethelper_s' ),
                'id'            => 'sidebar-1',
                'description'   => esc_html__( 'Add widgets here.', 'tickethelper_s' ),
                'before_widget' => '<section id="%1$s" class="widget %2$s">',
                'after_widget'  => '</section>',
                'before_title'  => '<h2 class="widget-title">',
                'after_title'   => '</h2>',
        ) );
}
add_action( 'widgets_init', 'tickethelper_s_widgets_init' );

/**
 * Enqueue scripts and styles.
 */
function tickethelper_s_scripts(){
        wp_enqueue_style( 'tickethelper_s-style', get_stylesheet_uri() );
        wp_enqueue_script( 'tickethelper_s-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '20151215', true );
        wp_enqueue_script( 'tickethelper_s-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20151215', true );
        //wp_enqueue_script( 'tickethelper_s-tickethelper_ajax.js', get_template_directory_uri() . '/js/tickethelper_ajax.js', array(), null, true );
        wp_enqueue_script( 'tickethelper_s-jenkira.js', get_template_directory_uri() . '/js/jenkira.js', array(), null, false );
        if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
                wp_enqueue_script( 'comment-reply' );
        }
}
add_action( 'wp_enqueue_scripts', 'tickethelper_s_scripts' );

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


///////////////////////////////// WP underscores functions END /////////////////////////////////

 /* ---------------------------------------------------------------------------------------- *
 * --------------------------------------|  jenkira!  |-------------------------------------- *
  * ---------------------------------------------------------------------------------------- */

/*
 * connects JIRA issues with jenkins builds, and presents a UI for deployments
 *
 * a JIRA webhook is registered; when an assignee (currently defined in JQL, developers only) modifies an issue it posts to: jenkira_map['ajax_url']
 * the script below receives and parses the data and writes it to wpdb
 * an ajax call is made at a set interval (or onclick of a refresh button) to retrieve Jenkins builds
 * the script creates a collection of QA builds which have not yet been built on Production and links it with JIRA issue keys, which are in the P4 changelist description and available in Jenkins
 * note that only builds that are associated with JIRA issues are tracked; if the issue key is not in the P4 description, it will not be included in the results
 * the collection is sent to the UI for display
 */


// global variables

$jenkira_map = array(
      ajax_url => 'http://webteam-dev.wwwstage.nydc.fxcorp.prv/tickethelper/wp-admin/admin-ajax.php?action=receive_jira_data&issue_id=${issue.id}&issue_key=${issue.key}&issue_expand=${issue.expand}&issue_self=${issue.self}&issue_status=${issue.fields.status.name}',
      jira_issue_url => 'https://jira.fxcm.com/rest/api/2/issue/',
      jira_issue_transitions => '/transitions?expand=transitions.fields',
      admin_email => 'jcelentano@fxcm.com',
      jenkins_host => 'jenkins-marketing.fxcorp',
      jenkins_api_token => '2b7e9301a33a1df2e5b5a5b3a1d8f3d7',
      jenkins_url_all_jobs => 'https://jcelentano:2b7e9301a33a1df2e5b5a5b3a1d8f3d7@jenkins-marketing.fxcorp/api/json?tree=jobs[name,allBuilds[number,timestamp,result,changeSet[items[msg,author[fullName]]]]]',
      webteam_branchnames => array(
             'FXCMUK31_CONTENT', 'FXCMAU31_CONTENT', 'FXCMMARKETS31_CONTENT', 'FXCMDE3_CONTENT',
             'FXCMFR3_CONTENT', 'FXCMIT3_CONTENT', 'FXCMAR3_CONTENT', 'FXCMCA_CONTENT',
             'FXCMUK31_BACKEND', 'FXCMAU31_BACKEND', 'FXCMMARKETS31_BACKEND', 'FXCMDE3_BACKEND', 'FXCMFR3_BACKEND',
             'FXCMIT3_BACKEND',
             'FXCMAR3_BACKEND', 'FXCMCA_BACKEND',
             'ASSETS3_CSS', 'ASSETS3-RESP_CSS', 'ASSETS3_IMG', 'ASSETS3-RESP_IMG',
             'STATIC_CONTENT');
);

/* --------- JIRA BEGIN. --------- */

function receive_jira_data($webhook_json){
    // wp_mail( 'jenkira_map['admin_email']', 'Jira webhook data', file_get_contents('php://input'), true );
    // wp_mail( 'jenkira_map['admin_email']', 'Jira webhook data GET', var_export( $_GET, true ));
    //$webhook_json = $webhook_json || file_get_contents('php://input');

    $webhook_data = json_decode(file_get_contents('php://input'), true);
    // wp_mail('jenkira_map['admin_email']', 'JIRA updated', 'update: ' . json_encode($webhook_data));
    if (!empty($webhook_data)) {
        $jira_update = array(
            'post_title' => $webhook_data['issue']['fields']['summary'],
            'post_status' => 'publish',
            'post_name' => $webhook_data['issue']['key'],
            'post_type' => 'jira',
            'post_content' => explode('\r\n', $webhook_data['issue']['fields']['customfield_10303']),
            'post_excerpt' => $webhook_data['issue']['fields']['description'],
            'meta_input' => array(
                'jira_timestamp' => $webhook_data['timestamp'],
                'jira_assignee' => $webhook_data['issue']['fields']['assignee']['name'],
                'jira_status' => $webhook_data['issue']['fields']['status']['name']
            )
        );
        // To Do: create wp 'users' for jira and jenkins?

        // check if post (issue) exists by searching for jira key, stored in 'post_name' field (referred to as 'name' in get_posts)
        $these_posts = get_posts(array('post_type' => 'jira', 'name' => $jira_update['post_name']));

        // Fix: insert test for duplicate jira keys
        $this_post = get_post($these_posts[0]->ID, OBJECT);

        if (!empty($this_post)) {
            $jira_update['ID'] = $this_post->ID;
            // update the post
            wp_update_post($jira_update, true);

            // wp_mail('jenkira_map['admin_email']', 'Post updated', 'db response: ' . wp_update_post($jira_update, true));
            // wp_mail('jenkira_map['admin_email']', 'Post updated', 'modified post: ' . json_encode(get_post($this_post->ID, OBJECT)));
        } else {
            // create new post
            $wpdb_response = wp_insert_post($jira_update, true);
            if (is_numeric($wpdb_response)) {
                $this_post = get_post($wpdb_response, OBJECT);
                // wp_mail('jenkira_map['admin_email']', 'Post inserted', 'new post: ' . json_encode($this_post));
            } else {
                // error writing to wpdb
                // wp_mail('jenkira_map['admin_email']', 'Error writing to wpdb', $wpdb_response);
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

function updateJIRAtransition($issue_key, $mthd, $actn, $user_request){
    $issue_key = strtolower($issue_key);
    $transitions_IDs = array('Deploy To QA' => '81', 'Deploy To Production' => '191', 'Verified On Production' => '101');
    $user_request = array(
        "update" => array("comment" => array(array("add" => array("body" => "Test comment from jenkira.")))),
        "transition" => array(
            "id" => $transitions_IDs[$user_request]
        )
    );
    $username = 'jcelentano';
    $password = '___9*yx%m';

    $url = jenkira_map['jira_issue_url'].$issue_key.jenkira_map['jira_issue_transitions'];
    //$mthd === "GET" ? $url.= "/".$actn : '';
    //$mthd === "POST" && $actn === "transition" ? $url.= "/transitions" : "";
    $headers = array('Content-Type: application/json');
    $data = json_encode($user_request);
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mthd);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // ignore SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // ignore SSL
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, $mthd === 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_URL, trim($url));
    curl_setopt($ch, CURLOPT_USERPWD, $username .':'. $password);

    $result = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); //get status code
    $ch_error = curl_error($ch);

    curl_close($ch);

    if($ch_error){
        wp_mail('jenkira_map['admin_email']', 'Called updateJIRAtransition', "cURL Error: ".$status_code." ".$ch_error);
    }
    else{
        wp_mail('jenkira_map['admin_email']', 'Called updateJIRAtransition', "sent ".json_encode($user_request)." to ".$url." and the server replied with: ".$result);
        return $result;
    }
}


/* --------- JIRA END. --------- */

/* --------- Jenkins BEGIN. --------- */


function jenkins_request($request_type, $job_name){

    // build the URL
//    $protocol = "https";
//    $username = "jcelentano";
//    $api_token = jenkira_map['jenkins_api_token']; // to find your api token, click your name in the upper right corner of jenkins > configure > show api token...
//    $host = jenkira_map['jenkins_host'];

    // add further components to the URL based on get or build (post) request specified in second argument
//    $path = $request_type === 'get' ? "/api/json" : "/job/".$job_name."/build";
//    $parameters = $request_type === 'get' ?
//            "pretty=true&tree=jobs[name,lastBuild[actions[causes[shortDescription,userId]],number,duration,timestamp,result,changeSet[items[msg,author[fullName]]]]]":
//            "token=".$api_token;
//    $url = $protocol."://".($request_type === "get" ? $username.":".$api_token."@" : "").$host.$path."?".$parameters;

    // wp_mail('jenkira_map['admin_email']', 'Jenkins URL', $url);

    // hard coded URL
        // get
        $url_all_jobs = jenkira_map['jenkins_url_all_jobs'];

    if($request_type === 'get'){
        if($job_name === 'all') {
            $url = $url_all_jobs;
        }
        else{
            $url = $url_single_job;
        }
    }
    else{
    }

    $data = '"changeSet": {
                "items": [{
                    "author": {
                        "fullName": "john"
                    },
                    "msg": "AWE-1 test build"
                }]
            }';
    $data = $request_type = "build" ? json_encode($data) : "";
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
        //echo "Error Number:".curl_errno($ch)."<br>";
        //echo "Error String:".curl_error($ch);
    }
    if($job_name !== 'all'){
        wp_mail('jenkira_map['admin_email']', 'Jenkins response to request', json_encode($output));
        //$output = '{"single build"}';
    }
    curl_close($ch);
    return $output;
}

function create_build_summary($jenkins_data, $jira_posts){
    // $jira posts is an array of post_id => issue_key from wpdb
    $jenkins_jobs = json_decode($jenkins_data);
    $production_builds = array();
    $qa_builds = array();
    $unbuilt_issues_branches = array();
    $jira_jenkins_merged = array(
        'issues' => array(),
        'branches' => array()
    );
    $qa_prefix = 'QA_Deploy_';
    $pr_prefix = 'Prod_Deploy_';

    $webteam_branchnames = jenkira_map['webteam_branchnames'];

    foreach ($jenkins_jobs->jobs as $job){
        /*
           both production and qa builds are pulled from in a single loop
           because the array is ordered alphabetically by job name,
           i.e., according to the naming convention, production builds all begin with 'p', so they appear before qa builds, which begin with 'q'
        */
        // get last successful production build
        if(in_array(str_replace($pr_prefix, '', $job->name), $webteam_branchnames)){
            $branch = str_replace($pr_prefix, '', $job->name);
            if(!isset($production_builds[$branch])){
                $production_builds[$branch] = array(
                    'success' => 0, // single timestamp of last successful build of this branch
                    'unsuccess' => array() // array of timestamps of unsuccessful builds(failed, aborted, etc.) following the last successful build
                );
            }
            foreach($job->allBuilds as $pb){
                if($pb->result === 'SUCCESS'){
                    $production_builds[$branch]['success'] = $pb->timestamp / 1000;
                    // builds are sorted with the most recent at the top of the array; break loop as soon as a successful build is found
                    break;
                }
                else{
                    $production_builds[$branch]['unsuccess'][] = $pb->timestamp / 1000;
                }
            }
        }

        // for this branch, get qa builds
        elseif(in_array(str_replace($qa_prefix, '', $job->name), $webteam_branchnames)){
            $branch = str_replace($qa_prefix, '', $job->name);
            if(!isset($qa_builds[$branch])){
                $qa_builds[$branch] = array(
                    'success' => 0, // single timestamp of last successful build of this branch
                    'unsuccess' => array() // array of timestamps of unsuccessful builds (failed, aborted, etc.) following the last successful build
                );
            }

            foreach($job->allBuilds as $pq){
                $qa_build_timestamp = $pq->timestamp / 1000;
                // filter builds more recent than the last successful production build for this branch
                if($qa_build_timestamp > $production_builds[$branch]['success']){
                    if (count($pq->changeSet->items) > 0){
                        foreach ($pq->changeSet->items as $change){
                            // when the author is 'WebTeam', the msg field contains the content from the description field in the perforce change list that triggered the jenkins build
                            if ($change->author->fullName === 'WebTeam'){
                                // loop through all jira issue keys
                                foreach($jira_posts as $jira_post_id => $jira_issue_key){
                                    // look for issue key in the msg field
                                    if(stripos($change->msg, $jira_issue_key)){
                                        if(!isset($unbuilt_issues_branches[$jira_issue_key])){
                                            $jira_fetched = merge_post_meta($jira_post_id);
                                            $unbuilt_issues_branches[$jira_issue_key] = $jira_fetched;
                                            $unbuilt_issues_branches[$jira_issue_key]['jenkins_unbuilt'] = array();
                                            $unbuilt_issues_branches[$jira_issue_key]['jenkins_qa_builds'][$branch] = array();
                                            $unbuilt_issues_branches[$jira_issue_key]['jenkins_production_builds'][$branch] = array();

                                        }

                                        //if(!isset($jira_fetched) || $jira_fetched['jira_issue'] !== $jira_issue_key){
                                            // fetch jira issue data from wpdb
                                         //   $jira_fetched = merge_post_meta($jira_post_id);
                                          //  $jira_fetched['jenkins_unbuilt'] = array();
                                          //  $jira_fetched['jenkins_qa_builds'][$branch] = array();
                                          //  $jira_fetched['jenkins_production_builds'][$branch] = array();
                                       // }
                                        // clear fetched branches if they exist, build new array to overwrite them
                                        if($pq->result === 'SUCCESS'){
                                            $qa_builds[$branch]['success'] = $pq->timestamp / 1000;
                                            if(!in_array($branch, $unbuilt_issues_branches[$jira_issue_key]['jenkins_unbuilt'])){
                                                $unbuilt_issues_branches[$jira_issue_key]['jenkins_unbuilt'][] = $branch;
                                            }
                                            if(!in_array($branch, $jira_jenkins_merged['branches'])){
                                                $jira_jenkins_merged['branches'][] = $branch;
                                            }
                                        }
                                        else{
                                            $qa_builds[$branch]['unsuccess'][] = $pq->timestamp / 1000;
                                        }
                                        $unbuilt_issues_branches[$jira_issue_key]['jenkins_qa_builds'][$branch] = $qa_builds[$branch];
                                        $unbuilt_issues_branches[$jira_issue_key]['jenkins_production_builds'][$branch] = $production_builds[$branch];

                                        // this is redefined on each iteration of the loop; the last version includes all unbuilt branches for this issue
                                       // $unbuilt_issues_branches[$jira_issue_key] = $jira_fetched;
                                    }
                                }
                            }
                        }
                    }
                   // if(count($jira_fetched['jenkins_unbuilt']) > 0){
                        // there is at least one qa branch that has been successfully built more recently than its corresponding production branch
                       // $unbuilt = true;
                    //}
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
            updateJIRAtransition($merged_issue['jira_issue'], 'POST', 'transitions', 'Deployed To QA');
            $issue_post_id = $jira_post_ids[$merged_issue['jira_issue']];
            $jenkins_update = array(
                'ID' => $issue_post_id,
                'meta_input' => array(
                    'jenkins_unbuilt' => implode(",", $merged_issue['jenkins_unbuilt'])
                )
            );
            wp_update_post($jenkins_update, true);
            //wp_mail('jenkira_map['admin_email']', 'Added unbuilt branches to issues in wpdb', json_encode($merged_issue['jenkins_unbuilt']));
        }
    }
    //wp_mail('jenkira_map['admin_email']', 'Unbuilt', json_encode($jira_jenkins_merged));
    return $jira_jenkins_merged;
}

function get_jira_issue_keys(){
    $these_posts = get_posts(array('post_type' => 'jira', 'posts_per_page' => -1));
    // wp_mail('jenkira_map['admin_email']', 'JIRA issue posts (all)', json_encode($these_posts));
    $jira_issue_keys = array();
    foreach ($these_posts as $psts => $pst){
        $jira_issue_keys[$pst->ID] = $pst->post_name;
    }
    // wp_mail('jenkira_map['admin_email']', 'JIRA issue keys (all)', json_encode($jira_issue_keys));
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

function send_build_summary($summary, $type){
    if($type === 'jira'){
        // wp_mail('jenkira_map['admin_email']', 'Jenkins branches lastbuilds - '.$type, json_encode($summary));
    }
    else {
        $branches_to_build_str = '';
        foreach ($summary as $branch => $build) {
            if (($build['qa'] > $build['pr'])) { // removed $build['qa_author'] === 'WebTeam' && because this only picks up builds triggered by IT's script when files are integrated in P4
                $branches_to_build_str .= "\n\n" . $branch;
                $branches_to_build_str .= $build['qa_author'] ? "\nqa_author: " . $build['qa_author'] : '';
                $branches_to_build_str .= $build['qa_msg'] ? "\nmsg: " . $build['qa_msg'] : '';
                $branches_to_build_str .= "\nqa: " . myTime($build['qa'], 'America/New_York');
                $branches_to_build_str .= "\npr: " . myTime($build['pr'], 'America/New_York');
            }
        }
        // wp_mail('jenkira_map['admin_email']', 'Jenkins branches lastbuilds - '.$type, $branches_to_build_str);
    }
}

// chained
function refresh_data(){

    $issue_keys = get_jira_issue_keys();
    if($issue_keys){$jobs = jenkins_request("get", "all");}
    else{return false;}
    if($jobs){$last_builds = create_build_summary($jobs, $issue_keys);}
    else{return false;}
    echo json_encode($last_builds); // returns to ajax callback
    die();


    /*
    if($jobs){$last_builds = create_build_summary($jobs);}
    else{return false;}
    echo $last_builds;
    if($last_builds){$last_builds_unbuilt = get_unbuilt_branches($last_builds);}
    else{return false;}



    if($last_builds_unbuilt){
        send_build_summary($last_builds, 'all');
        $unbuilt_issues = find_builds_with_issue_keys($issue_keys, $last_builds_unbuilt);
        send_build_summary($unbuilt_issues, 'jira');
        echo json_encode($unbuilt_issues); // returns to ajax callback
        die();
    }
*/
}

function jenkins_build(){
    $branchname = $_POST['branchname'];
    echo 'build requested for '.$branchname;
    //jenkins_request("build", $branchname);
    die(); // prevents returning 0 after echoed response
}

/* --------- Jenkins END. --------- */

/* --------- Utility functions BEGIN. --------- */

function myTime($stamp, $zone){
    $date = new DateTime();
    $date->setTimestamp($stamp);
    $date->setTimezone(new DateTimeZone($zone));
    $datestr = $date->format('n/j/y H:i:s'); // 'U = Y-m-d H:i:s'
    return $datestr;
}

/* --------- Utility functions END. --------- */

/* --------- Register WP AJAX actions BEGIN. --------- */

// handle JIRA webhook post
add_action( 'wp_ajax_receive_jira_data', 'receive_jira_data' );
add_action( 'wp_ajax_nopriv_receive_jira_data', 'receive_jira_data' ); // nopriv = can be called while being logged in or not

// get latest builds from Jenkins, merge with JIRA data in wpdb
add_action('wp_ajax_refresh_data', 'refresh_data');
add_action( 'wp_ajax_nopriv_refresh_data', 'refresh_data' ); // nopriv = can be called while being logged in or not

// request a Jenkins build
add_action('wp_ajax_jenkins_build', 'jenkins_build');
add_action( 'wp_ajax_nopriv_jenkins_build', 'jenkins_build' ); // nopriv = can be called while being logged in or not

/* ---------------------------------------------------------------------------------------- *
* --------------------------------------|  jenkira!  |-------------------------------------- *
 * ---------------------------------------------------------------------------------------- */

/* -------------------  END  ---------------  OF  ---------------  LINE  ------------------- */
