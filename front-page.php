<?php
/* front-page.php */

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
 * @package tickethelper_s
 */
get_header();
?>
    <!--
        define ajaxurl -- usually in admin-header but needs to be available to non-admin users
        moved this from functions.php because it was being passed to the front end when an ajax call was made
        Fix: figure out a way to properly define ajaxurl without it being passed
    -->
    <script>var ajaxurl = '<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>';</script>

        <div id="primary" class="content-area">

        <div class="action_header"></div><button id="refresh_data">refresh data</button><div id="server_message"></div></div>

                <main id="main" class="site-main" role="main">

            <table id="unbuilt"></table>

        <?php
        /*
        echo '<div class="issue-form-wrapper">';
                echo '<h2>issue</h2>';
                $issue_pod = pods('issue');
                $issue_params = array('fields_only' => true, 'fields' => array('issue_id', 'issue_status', 'issue_changelists_ids', 'issue_links'));
                echo $issue_pod->form($issue_params);
                echo '<input type="submit" value="save" id="issue_form_submit" class="pods-form-submit-button">';
                echo '</div>';

                echo '<div class="changelist-form-wrapper">';
                echo '<h2>changelist</h2>';
                $changelist_pod = pods('changelist');
                $changelist_params = array('fields_only' => true, 'fields' => array('changelist_id', 'changelist_issue_id', 'changelist_files'));
                echo $changelist_pod->form($changelist_params);
                echo '<input type="submit" value="save" id="changelist_form_submit" class="pods-form-submit-button">';
                echo '</div>';
        */

                /* end jc_insert */
                if ( have_posts() ) :

                        if ( is_home() && ! is_front_page() ) : ?>
                                <header>
                                        <h1 class="page-title screen-reader-text"><?php single_post_title(); ?></h1>
                                </header>

                        <?php
                        endif;

                        /* Start the Loop */
                        while ( have_posts() ) : the_post();

                                /*
                                 * Include the Post-Format-specific template for the content.
                                 * If you want to override this in a child theme, then include a file
                                 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
                                 */
                                get_template_part( 'template-parts/content', get_post_format() );

                        endwhile;

                        the_posts_navigation();


                else :

                        get_template_part( 'template-parts/content', 'none' );

                endif; ?>

                </main><!-- #main -->


        <div id="deployments_container">

            <button id="build_all" class="build all">build all</button>

            <div id="deployments"></div>

        </div>

        </div><!-- #primary -->

<?php
get_sidebar();
get_footer();
