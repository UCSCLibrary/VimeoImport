<div class="field">
    <div id="vimeo-width-label" class="two columns alpha">
        <label for="vimeo_width"><?php echo __('Width'); ?></label>
    </div>
    <div class="inputs five columns omega">
   <?php echo get_view()->formText('vimeo_width', get_option('vimeo_width'), 
        array()); ?>
    <p class = "explanation">Enter the default width for display of videos imported from Vimeo</p>
    </div>
</div>

<div class="field">
    <div id="vimeo-height-label" class="two columns alpha">
        <label for="vimeo_height"><?php echo __('Height'); ?></label>
    </div>
    <div class="inputs five columns omega">
   <?php echo get_view()->formText('vimeo_height', get_option('vimeo_height'), 
        array()); ?>
   <p class = "explanation">Enter the default height for display of videos imported from Vimeo</p>
    </div>
</div>

<div class="field">
    <div id="vimeo-token-label" class="two columns alpha">
        <label for="vimeo-token"><?php echo __('Vimeo API Token'); ?></label>
    </div>
    <div class="inputs five columns omega">
   <?php echo get_view()->formText('vimeo_token', get_option('vimeo_token'), 
        array()); ?>
   <p class = "explanation">Enter the API token generated from your Vimeo Developer account application</p>
    </div>
</div>
