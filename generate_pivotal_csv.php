<?php
// Login credentials
$PLAYNICELY_USERNAME = 'username';
$PLAYNICELY_PASSWORD = 'password';
$PLAYNICELY_PROJECT_ID = '2453';
// Skip over closed and deleted tickets
$PLAYNICELY_IGNORE_STATUSES = array('closed','deleted');

// Map playnicely ids to members of your project
function get_pivotal_user($playnicely_id) {
    $playnicely_ids = array(
        1934 => "Chris Hayes",
        1930 => "Justin Trautman",
        1931 => "Brannan",
        1932 => "Cameron McCaddon",
        1936 => "Erika Trautman",
        2054 => "Kumaril Dave",
        2334 => "Jonathan Woodard",
        2438 => "Basho Mosko",
        2475 => "Jeremy Frazao"
    );

    if (!array_key_exists($playnicely_id, $playnicely_ids)) {
        return "";
    }

    return $playnicely_ids[ $playnicely_id ];
}

// This can really churn, so bump up memory limit
ini_set("memory_limit", "512M");

$project_json = file_get_contents("project.json");
if (!$project_json) {
    $project_json = file_get_contents("https://$PLAYNICELY_USERNAME:$PLAYNICELY_PASSWORD@api.playnice.ly/v1/project/$PLAYNICELY_PROJECT_ID/item/list/?detail=full");
    file_put_contents("project.json", $project_json);
}
//echo $project_json;
$project = json_decode($project_json);

// Check highest number of comments on any item, and add enough COMMENT columns to handle the highest number
$highest_num_comments = 0;
foreach ($project AS $item) {
    $num_comments = 0;
    foreach ($item->activity AS $act) {
        if ($act->type == 'item_comment') {
            $num_comments++;
        }
    }
    if ($num_comments > $highest_num_comments) {
        $highest_num_comments = $num_comments;
    }
}

$columns = array(
        'Id',
        'Story',
        'Labels',
        'Story Type',
        'Estimate',
        'Current State',
        'Created at',
        'Accepted at',
        'Deadline',
        'Requested By',
        'Owned By',
        'Description');

for ($i = 0; $i < $highest_num_comments; $i++) {
    $columns[] = 'Comment';
}

foreach ($project AS $item) {
    if (!in_array($item->status, $PLAYNICELY_IGNORE_STATUSES)) {
        $owned_by = '';
        $csv_row = array();

        //    Id,
        $csv_row[] = '';

        //    Story,
        $csv_row[] = $item->subject;

        //    Labels,
        $csv_row[] = implode(',',$item->tags);

        //    Story Type,
        if ($item->type_name == 'bug') {
            $csv_row[] = 'bug';
        } else {
            $csv_row[] = 'feature';
        }

        //    Estimate,
        $csv_row[] = '3';

        //    Current State,
        //    Possible statuses in playnicely are:
        //        new
        //        assigned
        //        in-progress
        //        qa
        //        deploy
        //        deployed
        //        closed
        //        deleted

        // Possible statuses in pivotal are: 'unscheduled' (meaning the story is in the icebox), 'unstarted' (in the backlog),
        // 'started', 'finished', 'delivered', 'accepted', and 'rejected'.
        switch ($item->status) {
            case "new":
                $csv_row[] = 'unscheduled';
                break;
            case "assigned":
                $csv_row[] = "unstarted";
                break;
            case "in-progress" :
                $csv_row[] = 'started';
                break;
            case "qa" :
                $csv_row[] = 'finished';
                break;
            default:
                $csv_row[] = 'delivered';
        }

        //    Created at,
        $csv_row[] = $item->created_at;

        //    Accepted at,
        $csv_row[] = '';

        //    Deadline,
        $csv_row[] = '';

        //    Requested By,
        $csv_row[] = get_pivotal_user($item->created_by);

        //    Owned By,
        $csv_row[] = get_pivotal_user($item->responsible);  // TODO: translate playnicely user # into pivotal user #

        //    Description,
        $csv_row[] = $item->body;

        // Comments
        foreach ($item->activity AS $act) {
            if ($act->type == 'item_comment') {
                $csv_row[] = $act->body;
            }
        }

        $csv[] = $csv_row;
    }
}

// Save 99 tickets to each CSV
$file_number = 1;
foreach (array_chunk($csv, 99) AS $chunk) {
    array_unshift($chunk, $columns);
    $fp = fopen('stories' . $file_number . '.csv', 'w');
    foreach($chunk AS $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
    $file_number++;
}
/**
 * Playnice.ly API values
 *
 *     "body":"Example item body",
       "status":"closed",
       "responsible":3,
       "updated_by":null,
       "tags":[
          "amazing",
          "wonderful",
          "super",
          "fantastic"
       ],
       "involved":[
          1,
          3
       ],
       "created_at":"2010-12-14T17:06:06+00:00",
       "type_name":"bug",
       "updated_at":"2010-12-14T17:06:07+00:00",
       "created_by":1,
       "activity":[
          {
             "body":"Adam C created me",
             "links":[

             ],
             "created_at":"2010-12-14T17:06:06+00:00",
             "created_by":1,
             "item_id":1,
             "project_id":1,
             "type":"item_audit"
          },
          {
             "body":"This is an example comment",
             "links":[

             ],
             "created_at":"2010-12-14T17:06:06+00:00",
             "created_by":1,
             "item_id":1,
             "project_id":1,
             "type":"item_comment"
          }
       ],
       "item_id":1,
       "milestone_id":1,
       "project_id":1,
       "subject":"Request: synchronous replication"
 */

?>