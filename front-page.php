<?php
/**
* The main template file.
*
* This is the most generic template file in a WordPress theme
* and one of the two required files for a theme (the other being style.css).
* It is used to display a page when nothing more specific matches a query.
* E.g., it puts together the home page when no home.php file exists.
*
* @link https://codex.wordpress.org/Template_Hierarchy
*
* @package jenkira_underscores
*/


get_header();

?>
    <!
        define ajaxurl -- usually in admin-header but needs to be available to non-admin users
        moved this from functions.php because it was being passed to the front end when an ajax call was made
        Fix: figure out a way to properly define ajaxurl without it being passed
    -->
    <script>var ajaxurl = '<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>';</script>

    <div id="color_key">

        <div id="color_key_title">:key:</div>

        <div class="lightgreen">verified</div>

        <div class="tan">unverified</div>

        <div class="lightblue">queued</div>

        <div class="darkgreen">deployed</div>

        <div class="darkred">failed</div>

    </div>

    <div id="logo_container" title="(Jenkins + JIRA integration + UI for managing builds related to issues) === jenkira!">

        <img class="logo_merge" id="jira_logo" title="jira logo" src="https://my-jira-domain/path-to-images/images/icon-jira-logo.png" alt="JIRA logo" data-aui-responsive-header-index="0">

        <img class="logo_merge" id="jenkins_logo" title="mr. jenkins" src="https://jenkins.io/images/226px-Jenkins_logo.svg.png" alt="Jenkins logo" data-aui-responsive-header-index="0">

    </div>

                <div id="primary" class="content-area">

        <div class="action_header"></div><button id="refresh_data">refresh data</button><div id="table_server_message"></div></div>

                                <main id="main" class="site-main" role="main">

            <table id="unbuilt"></table>

            <div id="affected_links_container">

                <button id="purge_all" class="purge all">purge all</button>

                <div id="affected_links"></div>

            </div>

                                </main><!-- #main -->

        <div id="deployments_container">

            <button id="build_all" class="build all">build all</button>

            <div id="deployments"></div>

        </div>

        <div id="scheduled_tasks_container">

            <div class="container_header">Jenkins Scheduled Tasks</div>

            <div id="scheduled_tasks"></div>

        </div>

        <div id="build_queue_container">

            <button id="get_build_queue">get build queue</button><span id="queue_server_message"></span>

            <div id="build_queue"></div>

        </div>

                </div><!-- #primary -->

<?php
