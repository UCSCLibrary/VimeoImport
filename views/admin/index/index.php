<?php

$head = array('bodyclass' => 'vimeo-import primary', 
              'title' => html_escape(__('Vimeo Import | Import Video')));
echo head($head);
echo flash(); 
if(isset($successDialog)) 
    echo '<div id="vimeo-success-dialog" title="&#x2714; SUCCESS"></div>';
echo $form;
echo foot(); 
