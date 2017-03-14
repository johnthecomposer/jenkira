
/* jenkira.js */

/* ---------------------------------------------------------------------------------------- *
* --------------------------------------|  jenkira!  |-------------------------------------- *
 * ---------------------------------------------------------------------------------------- */

/*
 * connects JIRA issues with jenkins builds, and presents a UI for deployments
 *
 * a JIRA webhook is registered; when an assignee (currently defined in JQL, developers only) modifies an issue it posts to: http://webteam-dev.wwwstage.nydc.fxcorp.prv/tickethelper/wp-admin/admin-ajax.php?action=receive_jira_data&issue_id=${issue.id}&issue_key=${issue.key}&issue_expand=${issue.expand}&issue_self=${issue.self}&issue_status=${issue.fields.status.name}
 * the script below receives and parses the data and writes it to wpdb
 * an ajax call is made at a set interval (or onclick of a refresh button) to retrieve Jenkins builds
 * the script creates a collection of QA builds which have not yet been built on Production and links it with JIRA issue keys, which are in the P4 changelist description and available in Jenkins
 * note that only builds that are associated with JIRA issues are tracked; if the issue key is not in the P4 description, it will not be included in the results
 * the collection is sent to the UI for display
 */

var jenkira = {
    // Source: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Math/random
    getRandomInteger: function (min, max) {
        min = Math.ceil(min);
        max = Math.floor(max);
        return Math.floor(Math.random() * (max - min)) + min;
    },
    // Source: https://gist.github.com/kmaida/6045266
    convertTimestamp: function (timestamp) {
        var d = new Date(timestamp * 1000),     // Convert the passed timestamp to milliseconds
            yyyy = d.getFullYear(),
            yy = yyyy.toString().slice(2),
            mm = ('0' + (d.getMonth() + 1)).slice(-2),  // Months are zero based. Add leading 0.
            dd = ('0' + d.getDate()).slice(-2),                 // Add leading 0.
            hh = d.getHours(),
            h = hh,
            min = ('0' + d.getMinutes()).slice(-2),             // Add leading 0.
            sec = ('0' + d.getSeconds()).slice(-2),             // Add leading 0.
            ampm = 'AM',
            time;

        if (hh > 12) {
            h = hh - 12;
            ampm = 'PM';
        } else if (hh === 12) {
            h = 12;
            ampm = 'PM';
        } else if (hh == 0) {
            h = 12;
        }
        // ie: 2013-02-18, 8:35 AM
        //time = yyyy + '-' + mm + '-' + dd + ', ' + h + ':' + min + ' ' + ampm;
        time = mm + '/' + dd + '/' + yy + ' ' + h + ':' + min + ':' + sec + ' ' + ampm;
        return time;
    },
    isVerifiedBranch: function(issues_data, branchname){
        // a branch is verified if all JIRA issues that contain it are verified on qa
        var verified = false;
        for(i in issues_data){
            if(issues_data[i]['jenkins_unbuilt'].indexOf(branchname) !== -1){
                if(issues_data[i]['jira_status'] === 'Verified on QA'){
                    verified = true;
                }
                else{
                    verified = false;
                    break;
                }
            }
        }
        return verified;
    },
    createDeploymentsControls: function(source, reference, container_id){
        var container = jQuery('#' + container_id).empty();
        var controls = source;
        controls.sort();
        for(c in controls){
            var build_single_branch = jQuery('<button></button>').
                prop('class', 'build single').
                prop('id', 'Prod_Deploy_' + controls[c]).append(controls[c]).click(function(){this.request_build(jQuery(this).prop('id'))});

                if(this.isVerifiedBranch(reference, controls[c])){
                    build_single_branch.prop('disabled', false);
                    build_single_branch.addClass('verified');
                }
                else{
                    build_single_branch.prop('disabled', true);
                }
            container.append(build_single_branch);
        }
    },
    createUnbuiltTable: function(source, container_id, fieldnames_ordered){
        if(fieldnames_ordered){
            fieldnames_ordered = Array.isArray(fieldnames_ordered) ? fieldnames_ordered : [fieldnames_ordered];
        }
        else{
            fieldnames_ordered = [];
        }
        var container = jQuery('#' + container_id).empty();
        var header_elements = [];
        var header_row = jQuery('<tr></tr>');

        for(var i = 0; i < source.length; i++){
            for(s in source[i]){
                var header_fieldname = s.replace('jira_', '').replace('jenkins_', '').replace('timestamp', 'updated').replace('unbuilt', 'branches');
                var field_index = fieldnames_ordered.indexOf(header_fieldname);
                if(field_index !== -1){
                    var header_field = jQuery('<th></th>');
                    header_field.append(header_fieldname);
                    header_field.prop('id', s);
                    header_elements[field_index] = header_field;
                }
            }
        i++
        }
        container.append(header_row);

        for(d in source){
            var issue_status = source[d]['jira_status'] === 'Verified on QA' ? 'issue verified' : 'issue pending';
            var data_row = jQuery('<tr></tr>').prop('id', source[d]['jira_issue']).prop('class', issue_status);
            container.append(data_row);

            for(h in header_elements){
                var db_fieldname = jQuery(header_elements[h]).prop('id');
                var header_text = jQuery(header_elements[h]).text();
                header_row.append(header_elements[h]);
                var data = jQuery('<td></td>').prop('class', db_fieldname);
                var txt = typeof source[d][db_fieldname] === 'undefined' ? '' : source[d][db_fieldname];
                txt = db_fieldname === 'jira_issue' ? '<a href="' + jenkira_map['jira_browse_issue_url'] + txt.toUpperCase() + '" target="_blank">' + txt.toUpperCase() + '</a>' : txt;
                txt = db_fieldname === 'jira_links' ? txt.replace(/,/g, '\n') : txt;
                txt = db_fieldname === 'jira_timestamp' ? this.convertTimestamp(txt / 1000) : txt;
                txt = db_fieldname === 'jira_assignee' ? '<a href="im:&lt;sip:' + txt + '&commat;' + jenkira_map['im_domain'] + '&gt;">'+ txt + '</a>' : txt;

                if(db_fieldname === 'jenkins_unbuilt'){
                    links_arr = this.createLinks(txt, jenkira_map['jenkins_build_url_qa'], '/lastBuild/', '<br>');
                    txt = jQuery('<span></span>');
                    txt.append(links_arr);
                }
                data.append(txt);
                data_row.append(data);
            }
        }
    },
    displayTableMessage: function(message, timestamp, container_id){
        jQuery('#' + container_id).html('Server responded at ' + this.convertTimestamp(timestamp / 1000) + ': ' + message).fadeIn(200);
    },
    createLinks: function(links_arr, prepend_str, append_str, separator){
        links_arr = Array.isArray(links_arr) ? links_arr : [links_arr];
        var links_elements = [];
        for(var i = 0; i < links_arr.length; i++){
            var link = jQuery('<a></a>').prop('target', 'blank');
            var href = (prepend_str || '') + links_arr[i] + (append_str || '');
            link.prop('href', href);
            link.append(links_arr[i]);
            i > 0 ? links_elements.push(separator) : '';
            links_elements.push(link);
        }
        return links_elements;
    },
    request_build: function(branchname){
        var data = {
            action: 'jenkins_build',
            branchname: branchname
        };
        var posted = jQuery.post(ajaxurl, data, function(server_response){
            // on success jQuery('#refresh_data').click();
        });
    }
}


jQuery(document).ready(function($){

    jQuery('#refresh_data').click(function(){

        // generate loading icon(s) animation
        jQuery(this).text('loading...');
        jQuery("#server_message").empty();
        var dots_count = 0;
        var show_loader = setInterval(function(){
            var container = jQuery("#server_message");
            if(dots_count > 33){
                container.empty();
                dots_count = 0;
            }
            var loading_dot = jQuery('<div></div>');
            var r = jenkira.getRandomInteger(0,255);
            var g = jenkira.getRandomInteger(0,255);
            var b = jenkira.getRandomInteger(0,255);
            loading_dot.css('background-color', 'rgb(' + r + ',' + g + ',' + b + ')');
            loading_dot.prop('id', 'data_loading');
            container.append(loading_dot);
            //loading_dot.fadeIn(8);
            dots_count++
        }, 90);


            // define post and post data
            var data = {
                action: this.id
            };
            var posted = jQuery.post(ajaxurl, data, function(server_response){});
            posted.then(
                function(server_response){
                    // success
                    clearInterval(show_loader);
                    jQuery('#refresh_data').text('refresh data');
                    console.log(JSON.parse(server_response));
                    //var pre = jQuery('<pre></pre>');
                    //pre.append(JSON.stringify(JSON.parse(server_response), null, 4));
                    //jQuery('#primary').append(pre);
                    var server_data = JSON.parse(server_response);
                    var branches = server_data['branches'];
                    var issues = server_data['issues'];
                    console.log('issues');
                    console.log(issues);
                    console.log('branches');
                    console.log(branches);
                    var issues_count = issues.length;
                    var branches_count = branches.length;
                    var message = '';
                    if(!issues_count){
                        message = 'no pending builds.';
                        jQuery('#main').fadeOut(400);
                        jQuery('#deployments_container').fadeOut(200);
                    }
                    else{
                        message = 'there ' + (issues_count > 1 ? ' are ' : ' is ') + issues_count + ' ticket' + (issues_count > 1 ? 's ' : '') +
                                  ' with ' + branches_count + ' branch' + (branches_count > 1 ? 'es' : '') + '.';
                        jenkira.createUnbuiltTable(issues, 'unbuilt', ['status', 'issue', 'assignee', 'updated', 'branches']);
                        jenkira.createDeploymentsControls(branches, issues, 'deployments');
                        jQuery('#main').fadeIn(400);
                        jQuery('#deployments_container').fadeIn(600);
                    }
                    jenkira.displayTableMessage(message, Date.now(), 'server_message');
                },
                function(){
                    // failure
                    message = 'could not connect to server.';
                    jenkira.displayTableMessage(message, Date.now(), 'server_message');
                }
            );
    });
    //setInterval(function(){
        jQuery('#refresh_data').click();
    //}, 10000);

    jQuery('.build.all').click(function(){
        jQuery('.build.single').each(function () {
            if(jQuery(this).hasClass('verified')){
                //jenkira.request_build(jQuery(this).prop('id'));
                console.log(jQuery(this).prop('class') + ':' + jQuery(this).prop('id'));
            }
        });
    });
    jQuery('#refresh_data').click();
});

/* ---------------------------------------------------------------------------------------- *
* --------------------------------------|  jenkira!  |-------------------------------------- *
 * ---------------------------------------------------------------------------------------- */
