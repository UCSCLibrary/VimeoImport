<?php

$head = array('bodyclass' => 'vimwo-import primary', 
              'title' => html_escape(__('Vimeo Import | Import Video')));
echo head($head);
?>
<?php echo flash(); ?>
<?php echo $form; ?>
<?php echo foot(); ?>