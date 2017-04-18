
/* jenkira.js */

/* ---------------------------------------------------------------------------------------- *
* --------------------------------------|  jenkira!  |-------------------------------------- *
 * ---------------------------------------------------------------------------------------- */

 /*
 * connects JIRA issues with jenkins builds and presents a UI for deployments
 *
 * a JIRA webhook is registered; when an assignee (currently defined in JQL, developers only) modifies an issue it posts to: http://my-wordpress-domain/tickethelper/wp-admin/admin-ajax.php?action=receive_jira_data&issue_id=${issue.id}&issue_key=${issue.key}&issue_expand=${issue.expand}&issue_self=${issue.self}&issue_status=${issue.fields.status.name}
 * the script below receives and parses the data and writes it to wpdb
 * an ajax call is made at a set interval (or onclick of a refresh button) to retrieve Jenkins builds
 * the script creates a collection of QA builds which have not yet been built on Production and links it with JIRA issue keys, which are in the P4 changelist description and available in Jenkins
 * note that only builds that are associated with JIRA issues are tracked; if the issue key is not in the P4 description, it will not be included in the results
 * the collection is sent to the UI for display
 */

 // registration.js requires registration_container to be defined
 var registration_container = registration_container || {};
 var issues = [];

 var jenkira = (function(){
     // Source: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Math/random
     return {
         getRandomInteger: function (min, max) {
             min = Math.ceil(min);
             max = Math.floor(max);
             return Math.floor(Math.random() * (max - min)) + min;
         },
         // Source: https://gist.github.com/kmaida/6045266
         convertTimestamp: function (timestamp) {
             var d = new Date(timestamp * 1000),         // Convert the passed timestamp to milliseconds
                 yyyy = d.getFullYear(),
                 yy = yyyy.toString().slice(2),
                 mm = ('0' + (d.getMonth() + 1)).slice(-2),              // Months are zero based. Add leading 0.
                 dd = ('0' + d.getDate()).slice(-2),                                               // Add leading 0.
                 hh = d.getHours(),
                 h = hh,
                 min = ('0' + d.getMinutes()).slice(-2),                      // Add leading 0.
                 sec = ('0' + d.getSeconds()).slice(-2),                       // Add leading 0.
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
             time = mm + '/' + dd + '/' + yy + ' ' + h + ':' + min + ':' + sec + ' ' + ampm;
             return time;
         },
         createLoadingAnimation: function(this_clicked, target_id, speed_in_milliseconds){
             var target = jQuery('#' + target_id);
                 target.empty();
             var max_width = target.parent().width() - target.prev().width() - 100;

             var show_loader = setInterval(function(){
                 if(target.width() < max_width){
                     var r = jenkira.getRandomInteger(0,255);
                     var g = jenkira.getRandomInteger(0,255);
                     var b = jenkira.getRandomInteger(0,255);
                     var loading_dot = jQuery('<div></div>');
                     loading_dot.css('background-color', 'rgb(' + r + ',' + g + ',' + b + ')');
                     loading_dot.prop('class', 'data_loading');
                     target.append(loading_dot);
                 }
                 else{
                     target.empty();
                 }
             }, speed_in_milliseconds);
             return show_loader;
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
         getBranchIssueKeys: function(issues_data, branchname){
             // a branch is verified if all JIRA issues that contain it are verified on qa
             var keys = [];
             for(i in issues_data){
                 if(issues_data[i]['jenkins_unbuilt'].indexOf(branchname) !== -1){
                     if(issues_data[i]['jira_status'] === 'Verified on QA'){
                         keys.push(issues_data[i]['jira_issue']);
                     }
                 }
             }
             return keys;
         },
         createDeploymentsControls: function(source, reference, container_id){
             var container = jQuery('#' + container_id).empty();
             var controls = source;
             controls.sort();
             for(c in controls){
                 var build_single_branch = jQuery('<button></button>').
                     prop('class', 'build single').
                     prop('id', 'Prod_Deploy_' + controls[c]).
                     append(controls[c]).
                         click(function(){
                             jenkira.request_build(jQuery(this).prop('id'));
                             console.log('requested build for ' + jQuery(this).prop('id'))
                         });

                     if(this.isVerifiedBranch(reference, controls[c])){
                         build_single_branch.
                             prop('disabled', false).
                             addClass('verified').
                             attr('data-issue-keys', this.getBranchIssueKeys(reference, controls[c]).join(','));
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
                     var header_fieldname = s.replace('jira_', '').replace('jenkins_', '').replace('timestamp', 'updated').replace('unbuilt_links', 'branches');
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
                     header_row.append(header_elements[h]);
                     var data = jQuery('<td></td>').prop('class', db_fieldname);
                     var txt = typeof source[d][db_fieldname] === 'undefined' ? '' : source[d][db_fieldname];
                     txt = db_fieldname === 'jira_issue' ? '<a href="https://my-jira-domain/browse/' + txt.toUpperCase() + '" target="_blank">' + txt.toUpperCase() + '</a>' : txt;
                     txt = db_fieldname === 'jira_links' && txt.indexOf(',') !== - 1 ? txt.replace(/\n/g, '').split(',') : txt;
                     txt = db_fieldname === 'jira_timestamp' ? this.convertTimestamp(txt / 1000) : txt;
                     txt = db_fieldname === 'jira_assignee' ? '<a href="im:&lt;sip:' + txt + '&commat;my-company-domain.com&gt;">'+ txt + '</a>' : txt;

                     if(txt && db_fieldname.indexOf('links') !== -1){
                         links = db_fieldname === 'jenkins_unbuilt_links' ? this.createLinks(txt, '', '/changes', '<br>') :  this.createLinks(txt, '', '', '<br>', 'prod');
                         txt = jQuery('<span></span>');
                         txt.append(links);
                     }
                     data.append(txt);
                     data_row.append(data);
                 }
             }
         },
         displayMessage: function(message, timestamp, container_id){
             jQuery('#' + container_id).html('As of ' + this.convertTimestamp(timestamp / 1000) + ', ' + message).fadeIn(200);
         },
         displayScheduledTasks: function(tasks, container_id){
             var container = jQuery('#' + container_id);
             container.empty();
             for(t in tasks){
                 var this_task = jQuery('<div></div>').prop('class', 'scheduled_task');
                 this_task.append(tasks[t]['title']);
                 container.append(this_task);
             }
             console.log(container);
             container.parent().show();
         },
         displayBuildQueue: function(build_queue, container_id){
             var container = jQuery('#' + container_id);
             container.empty();
             for(queued in build_queue['items']){
                 var this_queued = jQuery('<div></div>').prop('class', 'build_queued');
                 var queued_item = build_queue['items'][queued];
                 this_queued.append(queued_item['task']['name'] + ' [' + this.convertTimestamp(queued_item['inQueueSince'] / 1000) + ']');
                 container.append(this_queued);
             }
             container.show();
         },
         displayUniqueLinks: function(issues_data, container_id){
             var unique_links_arr = [];
             var container = jQuery('#' + container_id);
             container.empty();
             for(i in issues_data){
                 if(issues_data[i]['jira_links']){
                     var links = issues_data[i]['jira_links'].split(',');
                     for(l in links){
                         var this_link = links[l].trim();
                         if(unique_links_arr.indexOf(this_link) === -1){
                             unique_links_arr.push(this_link);
                             var this_link = jQuery('<a></a>').
                                 prop('class', 'affected_link').
                                 prop('href', this_link).
                                 prop('target', '_blank').
                                 text(this_link);
                             container.append(this_link);
                         }
                     }
                 }
             }
             container.parent().show();
             return unique_links_arr;
         },
         flagQueuedDeployments: function(queued_data, deployments_id){
             var container = jQuery('#' + deployments_id);
             container.children().each(function(){
                 var this_element = jQuery(this);
                 var these_issue_keys = this_element.attr('data-issue-keys').split(',');
                 var branch = this_element.prop('id');
                 var in_queue = this_element.hasClass('in_queue');
                 for(queued in queued_data['items']){
                     var queued_branch = queued_data['items'][queued]['task']['name'];
                     var is_queued = branch === queued_branch;
                     if(is_queued && !in_queue){
                         for(k in these_issue_keys){
                             this_element.addClass('in_queue');
                             this_element.css('background-color', 'lightblue');
                         }
                         break;
                     }
                 }
             });
         },
         createLinks: function(links, prepend_str, append_str, separator, switch_to){
             var links_elements = [];
             links = typeof links === 'string' ? [links] : links;
             if(Array.isArray(links)){
                 console.log('links ARRAY supplied as');
                 console.log(links)
                 for(var i = 0; i < links.length; i++){
                     links[i] = links[i].trim();
                     var parsed_URL = this.parseURL(links[i], registration_container.registration.siteMap);
                     var link = jQuery('<a></a>').prop('target', 'blank');
                     var site = parsed_URL.site;
                     var site_path = '';
                     if(site === 'us' && !parsed_URL.path){
                         site_path = '/';
                     }
                     else if(site === 'us' && parsed_URL.path){
                         site_path = parsed_URL.path;
                         console.log('site is us and path is ');
                         console.log(site_path);
                     }
                     else{
                         site_path = '/' + site + parsed_URL.path;
                     }
                     link.prop('href', switch_to ? this.switchDomain(parsed_URL.domains, switch_to) + parsed_URL.path :  (prepend_str || '') + links[i] + (append_str || ''));
                     link.append(switch_to ? site_path : links[i]);

                     i > 0 ? links_elements.push(separator) : '';
                     links_elements.push(link);
                 }
             }
             else{
                 var links_count = 0;
                 for(l in links){
                     links[l] = links[l].trim();
                     var parsed_URL = this.parseURL(links[l], registration_container.registration.siteMap);
                     var link = jQuery('<a></a>').prop('target', 'blank');
                     link.prop('href', switch_to ? this.switchDomain(parsed_URL.domains, switch_to) + parsed_URL.path :  (prepend_str || '') + links[l] + (append_str || ''));
                     link.append(switch_to ? ('/' + parsed_URL.site + parsed_URL.path) : l);

                     links_count > 0 ? links_elements.push(separator) : '';
                     links_elements.push(link);
                     links_count++
                 }
             }
             return links_elements;
         },
         parseURL: function(link, source){
             var parsed = {
                 site: '',
                 domains: '',
                 path: ''
             }
             var us_path = false;
             for(site in source){
                 var domains = source[site].domains;
                 for(domain in domains){
                     var idx = link.indexOf(domains[domain]);
                     if(idx !== -1){
                         var slice_index = idx + domains[domain].length;
                         var path = slice_index === link.length - 1 ? '' : link.slice(slice_index);

                         if(site !== 'main_domain'){
                             console.log('site: ' + site + '; domain: ' + domains[domain] + ' path for link ' + link + ' is ' + path);
                            // var path = link.slice(slice_index);
                             parsed.site = source[site].countryID.toLowerCase();
                             parsed.domains = domains;
                             parsed.path = path;
                             return parsed;
                         }
                         else{
                             us_path = path === '' ? 'homepage' : path;
                         }
                     }
                 }
             }
             if(us_path){
                 parsed.site = 'us';
                 parsed.domains = source.main_domain.domains;
                 parsed.path = us_path === 'homepage' ? '' : us_path;
             }
             return parsed;
         },
         switchDomain: function(source, switch_to){
             return 'https://' + source[switch_to];
         },
         request_build: function(branchname){
             var data = {
                 action: 'jenkins_build',
                 branchname: branchname
             };
             var posted = jQuery.post(ajaxurl, data, function(server_response){
                 // success, failure
             });
         },
         handleButtonState: function(element, state, newtext){
             var disable = state === 'disable';
             newtext = newtext || element.text();
             jQuery(element).
                 prop('disabled', disable).
                 addClass('running').
                 text(newtext);
         },
         get_build_queue: function(){
             jenkira.handleButtonState(jQuery('#get_build_queue'), 'disable', 'checking queue...');
             var data = {
                 action: 'get_jenkins_build_queue'
             };
             var posted = jQuery.post(ajaxurl, data, function(server_response){
                 console.log('server response to get build queue request:');
                 console.log(server_response);
                 posted.then(
                     function(server_response){
                         // success
                         var message = '';
                         var build_queue_data = JSON.parse(server_response);
                         jenkira.displayBuildQueue(build_queue_data, 'build_queue'); // empties the build queue container if nothing is queued

                         if(build_queue_data['items'].length > 0){
                             jenkira.flagQueuedDeployments(build_queue_data, 'deployments');
                             message = 'queued ';
                             jenkira.displayMessage(message, Date.now(), 'queue_server_message');
                             setTimeout(function(){
                                 jQuery('#get_build_queue').click();
                             }, 10000);
                         }
                         else{
                             message = 'there are no builds in the queue.';
                             jenkira.displayMessage(message, Date.now(), 'queue_server_message');
                             jenkira.handleButtonState(jQuery('#get_build_queue'), 'enable', 'get build queue');
                             jenkira.jenkins_match_changelists(issues);
                             return true;
                         }
                     },
                     function(){
                         // failure
                         var message = 'jenkira could not get the Jenkins build queue.';
                         jenkira.displayMessage(message, Date.now(), 'queue_server_message');
                     }
                 );
             });
         },
         update_jira_status: function(issue_key, status, comment){
             var data = {
                 action: 'update_jira_status',
                 issue_key: issue_key,
                 status: status,
                 comment: comment
             };
             var posted = jQuery.post(ajaxurl, data, function(server_response){
                 console.log('server response to update jira request:');
                 console.log(server_response);
                 posted.then(
                     function(server_response){
                         // success
                         console.log('updated JIRA status');
                     },
                     function(){
                         // failure
                         console.log('failed to update JIRA status');
                     }
                 );
             });
         },
         jenkins_match_changelists: function(issues){
             var branchnames_changelists = {};
             for(i in issues){
                 var qa_builds = issues[i]['jenkins_qa_builds'];
                 for(b in qa_builds){
                     if(typeof branchnames_changelists[b] === 'undefined'){
                         branchnames_changelists[b] = [];
                     }
                     var changelists = qa_builds[b]['success']['changelists'];
                     for(c in changelists){
                         var changelist_number = changelists[c];
                         branchnames_changelists[b].indexOf(changelist_number) === -1 ? branchnames_changelists[b].push(changelist_number) : '';
                     }
                 }
             }
             var data = {
                 action: 'jenkins_match_changelists',
                 branchnames_changelists: branchnames_changelists
             };
             console.log('sending data to server for builds report');
             console.log(data);
             var posted = jQuery.post(ajaxurl, data, function(server_response){
                 console.log('server response to jenkins_match_changelists request:');
                 var matched_changelists = JSON.parse(server_response);
                 console.log(matched_changelists);
                 posted.then(
                     function(server_response){
                         // success
                         for(i in issues){
                             var qa_builds = issues[i]['jenkins_qa_builds'];
                             for(b in qa_builds){
                                 var changelists = qa_builds[b]['success']['changelists'];
                                 qa_builds[b]['success']['production_unsuccess'] = [];
                                 var production_unsuccess = qa_builds[b]['success']['production_unsuccess'];
                                 for(var c = 0; c < changelists.length; c++){
                                     var changelist_number = changelists[c];
                                     if(matched_changelists[b].indexOf(changelist_number) === -1 ){
                                         production_unsuccess.push(changelist_number);
                                     }
                                 }
                             }
                         }
                         console.log('modified issues - unsuccessful/unbuilt production builds included');
                         console.log(issues);
                         jenkira.show_production_builds_status();
                     },
                     function(){
                         // failure
                         console.log('failed to retrieve builds report');
                     }
                 );
             })
         },
         show_production_builds_status: function(){
             console.log('called flag successful production builds');
             for(i in issues){
                 var issue_key = issues[i]['jira_issue'];
                 jQuery('#' + issue_key + ' .jenkins_unbuilt_links span').children('a').each(function(){
                     var this_branch = jQuery(this).text();
                     if(typeof issues[i]['jenkins_qa_builds'][this_branch]['success']['production_unsuccess'] !== 'undefined'){
                         console.log('some failed');
                         jQuery(this).append('<div class="data_loading failure"></div>');
                     }
                     else{
                         console.log(issues[i]['jenkins_qa_builds'][this_branch]['success']['changelists'].join(','));
                         jenkira.update_jira_status(issue_key, 'Deploy To Production', issues[i]['jenkins_qa_builds']);
                         jQuery(this).append('<div class="data_loading success"></div>');
                     }
                 });

             }
         }
     }
 }());


 jQuery(document).ready(function(){

     jQuery('#refresh_data').click(function(){

             var loader = jenkira.createLoadingAnimation(this, 'table_server_message', 90);
             jenkira.handleButtonState(this, 'disable', 'jenkira!!!');

             // define post and post data
             var data = {
                 action: this.id
             };
             var posted = jQuery.post(ajaxurl, data, function(server_response){});
             posted.then(
                 function(server_response){
                     // success
                     clearInterval(loader);
                     jenkira.handleButtonState(jQuery('#refresh_data'), 'enable', 'refresh data');
                     var server_data = JSON.parse(server_response);
                     var branches = server_data['branches'];
                     issues = server_data['issues'];
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
                               ' with ' + branches_count + ' branch' + (branches_count > 1 ? 'es' : '') + ' pending production deployment.';
                     jenkira.createUnbuiltTable(issues, 'unbuilt', ['status', 'issue', 'assignee', 'updated', 'branches', 'links']);
                     jenkira.createDeploymentsControls(branches, issues, 'deployments');
                     jenkira.displayUniqueLinks(server_data['issues'], 'affected_links');
                     //jenkira.displayScheduledTasks(server_data['jenkins_scheduled_tasks'], 'scheduled_tasks');

                     jQuery('#main').fadeIn(400);
                     jQuery('#deployments_container').fadeIn(600);
                 }
                 jenkira.displayMessage(message, Date.now(), 'table_server_message');
             },
             function(){
                 // failure
                 message = 'jenkira could not connect to server.';
                 jenkira.displayMessage(message, Date.now(), 'table_server_message');
                 jenkira.handleButtonState(jQuery('#refresh_data'), 'enable', 'refresh data');
             });
     });

     jQuery('.build.all').click(function(){
         jQuery('.build.single').each(function(){
             if(jQuery(this).hasClass('verified')){
                 jenkira.request_build(jQuery(this).prop('id'));
             }
         });
         jQuery('#get_build_queue').click();
     });

     jQuery('#get_build_queue').click(function(){
         jenkira.get_build_queue();
     });


     // initial data load
     jQuery('#refresh_data').click();

     // enable polling jenkins - Fix: if this is set for each user, won't there be too many hits on the server?
     // fix: suspend refreshing data while build queue is not empty
    /* setInterval(function(){
         jQuery('#refresh_data').click();
         jQuery('#get_build_queue').click();
     }, 10000);
 */


 });

/* ---------------------------------------------------------------------------------------- *
* --------------------------------------|  jenkira!  |-------------------------------------- *
 * ---------------------------------------------------------------------------------------- */
