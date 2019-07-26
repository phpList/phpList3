<?php
require_once dirname(__FILE__).'/accesscheck.php';
if (!defined('PHPLISTINIT')) {
    exit;
}
if (!$_GET['id']) {
    Fatal_Error(s('no such User'));

    return;
} else {
    $id = (int)sprintf('%d', $_GET['id']);
}

$result = Sql_query("SELECT * FROM {$tables['user']} where id = $id");
if (!Sql_Affected_Rows()) {
    Fatal_Error(s('no such User'));

    return;
}
$user = sql_fetch_array($result);

ob_end_clean();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=subscriberdata.csv');
// create a file pointer connected to the output stream
ob_start();
$output = fopen('php://output', 'w');

$csvColumnDelimiter = "\t";
if (EXPORT_EXCEL) {
    $csvColumnDelimiter = ',';
}

// output the column headings
fputcsv($output, array('','General Subscriber Info'), $csvColumnDelimiter);
fputcsv($output, array('Email', 'Confirmed','Blacklisted', 'Opted in', 'Bounce count','Entered','Modified','Html email','Subscribe Page','rssfrequency','disabled','extradata'), $csvColumnDelimiter);


$userrows = Sql_Query(
    sprintf(
        'select email, confirmed, blacklisted, optedin, bouncecount, entered, modified, htmlemail, subscribepage, rssfrequency, disabled, extradata 
                from %s where id = %d'


        , $GLOBALS['tables']['user'], $user['id'])
);


// loop over the rows, outputting them
while ($row = Sql_Fetch_Assoc($userrows))
    fputcsv($output, $row, $csvColumnDelimiter);

fputcsv($output, array(' '), $csvColumnDelimiter);
// output the column headings
fputcsv($output, array('','User History Info'), $csvColumnDelimiter);
fputcsv($output, array('ip address', 'Summary','Date', 'Details', 'System Information'), $csvColumnDelimiter);
$userhistoryrows = Sql_Query(
    sprintf(
        'select ip, summary,date, detail, systeminfo 
                from %s where userid = %d'


        , $GLOBALS['tables']['user_history'], $user['id'])
);


// loop over the rows, outputting them
while ($row = Sql_Fetch_Assoc($userhistoryrows))
    fputcsv($output, $row, $csvColumnDelimiter);
fputcsv($output, array(' '), $csvColumnDelimiter);
fputcsv($output, array('','Campaign Info'), $csvColumnDelimiter);
fputcsv($output, array('Message ID', 'Entered','Viewed', 'Response time'), $csvColumnDelimiter);
$msgsrows = Sql_Query(sprintf('select messageid,entered,viewed,(viewed = 0 or viewed is null) as notviewed,
    abs(unix_timestamp(entered) - unix_timestamp(viewed)) as responsetime from %s where userid = %d and status = "sent" order by entered desc',
    $GLOBALS['tables']['usermessage'], $user['id']));


// loop over the rows, outputting them
while ($row = Sql_Fetch_Assoc($msgsrows))
    fputcsv($output, $row, $csvColumnDelimiter);
fputcsv($output, array(''), $csvColumnDelimiter);

fputcsv($output, array('','Bounces Info'), $csvColumnDelimiter);
fputcsv($output, array('Bounce ID', 'Bounce message','Time', 'Bounce','F time'), $csvColumnDelimiter);
$bouncesrows = Sql_Query(sprintf('
select 
    message_bounce.id
    , message_bounce.message
    , time
    , bounce
    , date_format(time,"%%e %%b %%Y %%T") as ftime
from 
    %s as message_bounce
where 
    user = %d', $GLOBALS['tables']['user_message_bounce'], $user['id']));

while ($row = Sql_Fetch_Assoc($bouncesrows))
    fputcsv($output, $row, $csvColumnDelimiter);

fputcsv($output, array(''), $csvColumnDelimiter);

fputcsv($output, array('','Blacklist Info'), $csvColumnDelimiter);
fputcsv($output, array('Email', 'Name','Data','Added'), $csvColumnDelimiter);
$blacklistdata = $GLOBALS['tables']['user_blacklist_data'];
$blacklist = $GLOBALS['tables']['user_blacklist'];
$emailaddress = sql_escape($user['email']);

$blacklistinforows = Sql_Query("select d.email, d.name, d.data, b.added from $blacklistdata as d 
left join $blacklist as b on d.email = b.email
where b.email = '$emailaddress';
");

while ($row = Sql_Fetch_Assoc($blacklistinforows))
    fputcsv($output, $row, $csvColumnDelimiter);

fputcsv($output, array(''), $csvColumnDelimiter);
fputcsv($output, array('','Subscriber Attribute Info'), $csvColumnDelimiter);
fputcsv($output, array('value','name', 'type','tablename'), $csvColumnDelimiter);

$userattribute = $GLOBALS['tables']['user_attribute'];
$attribute = $GLOBALS['tables']['attribute'];
$userid = (int)$user['id'];

$attributesrows = Sql_Query(
    "select u.value, a.name, a.type, a.tablename from $userattribute as u 
left join $attribute as a on u.attributeid = a.id
where u.userid = '$userid';
    
");


while ($row = Sql_Fetch_Assoc($attributesrows))
    fputcsv($output, $row, $csvColumnDelimiter);

$list = $GLOBALS['tables']['list'];
$listuser = $GLOBALS['tables']['listuser'];

fputcsv($output, array(''), $csvColumnDelimiter);


fputcsv($output, array('','Lists Membership'), $csvColumnDelimiter);
fputcsv($output, array('List name','description', 'entered','modified'), $csvColumnDelimiter);

$listrows = Sql_Query(
    "select l.name, l.description, u.entered, u.modified from $list as l 
left join $listuser as u on l.id = u.listid
where u.userid = '$userid';
    
");


while ($row = Sql_Fetch_Assoc($listrows))
    fputcsv($output, $row, $csvColumnDelimiter);


fputcsv($output, array(' '), $csvColumnDelimiter);
// output the column headings
fputcsv($output, array('','Links tracking Info'), $csvColumnDelimiter);
fputcsv($output, array('URL', 'Forward','First Clicked', 'Latest Clicked', 'clicked','Campaign ID'), $csvColumnDelimiter);
$linkrows = Sql_Query(
    sprintf(
        'select url, forward, firstclick, latestclick, clicked, messageid 
                from %s where userid = %d'


        , $GLOBALS['tables']['linktrack'], $user['id'])
);

while ($row = Sql_Fetch_Assoc($linkrows))
    fputcsv($output, $row, $csvColumnDelimiter);

fputcsv($output, array(' '), $csvColumnDelimiter);
// output the column headings
fputcsv($output, array('','UML clicks Info'), $csvColumnDelimiter);
fputcsv($output, array('Message ID', 'Forward ID','First Click', 'Latest Click', 'clicked','Html Clicked', 'Text Clicked'), $csvColumnDelimiter);
$umlrows = Sql_Query(
    sprintf(
        'select messageid, forwardid, firstclick, latestclick, clicked, htmlclicked, textclicked 
                from %s where userid = %d'


        , $GLOBALS['tables']['linktrack_uml_click'], $user['id'])
);

while ($row = Sql_Fetch_Assoc($umlrows))
    fputcsv($output, $row, $csvColumnDelimiter);

fputcsv($output, array(' '), $csvColumnDelimiter);
// output the column headings
fputcsv($output, array(' ','User clicks Info'), $csvColumnDelimiter);
fputcsv($output, array('Link ID', 'Message ID','Name', 'Data', 'Date'), $csvColumnDelimiter);
$userclickrows = Sql_Query(
    sprintf(
        'select linkid, userid, messageid, name, data, date 
                from %s where userid = %d'


        , $GLOBALS['tables']['linktrack_userclick'], $user['id'])
);

while ($row = Sql_Fetch_Assoc($userclickrows))
    fputcsv($output, $row, $csvColumnDelimiter);

fputcsv($output, array(' '), $csvColumnDelimiter);
// output the column headings
fputcsv($output, array(' ','Message Forward Info'), $csvColumnDelimiter);
fputcsv($output, array('Message ID','Forward', 'Status', 'Time'), $csvColumnDelimiter);
$forwardrows = Sql_Query(
    sprintf(
        'select message, forward, status, time 
                from %s where user = %d'


        , $GLOBALS['tables']['user_message_forward'], $user['id'])
);

while ($row = Sql_Fetch_Assoc($forwardrows))
    fputcsv($output, $row, $csvColumnDelimiter);



fclose($output);
exit;



 
